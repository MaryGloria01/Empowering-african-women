<?php
require_once __DIR__ . '/db.php';
cors();

// ── Admin-only guard ──────────────────────────────────────────────────────────
start_session();
if (empty($_SESSION['is_admin'])) json_out(['error' => 'Forbidden'], 403);

// Issue a fresh CSRF token on each GET (admin JS reads it from a header/response)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$pdo    = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Auto-create payments table on first use
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_email VARCHAR(254) NOT NULL,
        student_name VARCHAR(120),
        course_id VARCHAR(50),
        course_title VARCHAR(200),
        amount VARCHAR(50),
        depositor_name VARCHAR(120),
        pay_date VARCHAR(20),
        pay_time VARCHAR(20),
        ref_code VARCHAR(100),
        receipt_data MEDIUMTEXT,
        status ENUM('pending','confirmed','rejected') DEFAULT 'pending',
        submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        confirmed_at DATETIME
    )");
} catch (PDOException $e) {
    debug_log("admin.php: CREATE TABLE payments failed: " . $e->getMessage());
}

// ── Helper: validate status whitelist ────────────────────────────────────────
function valid_status($s) {
    return in_array($s, ['pending', 'approved', 'rejected'], true) ? $s : null;
}

// ── Audit log helper ─────────────────────────────────────────────────────────
function audit_log($pdo, $action, $detail = '') {
    try {
        $pdo->prepare("INSERT INTO admin_audit_log (action, detail, performed_at) VALUES (?, ?, NOW())")
            ->execute([$action, $detail]);
    } catch (Exception $e) { /* table may not exist yet — fail silently */ }
}

// ── GET actions ───────────────────────────────────────────────────────────────
if ($method === 'GET') {
    // Return CSRF token so admin JS can attach it to POST requests
    header('X-CSRF-Token: ' . ($_SESSION['csrf_token'] ?? ''));

    switch ($action) {

        case 'users':
            $stmt = $pdo->query('SELECT id, first_name, last_name, email, phone, role, is_verified, created_at FROM users ORDER BY created_at DESC');
            json_out(['success' => true, 'users' => $stmt->fetchAll()]);

        case 'tutors':
            $stmt = $pdo->prepare("SELECT id, first_name, last_name, email, phone, role, is_verified, created_at FROM users WHERE role IN ('tutor-pending','tutor') ORDER BY created_at DESC");
            $stmt->execute();
            json_out(['success' => true, 'tutors' => $stmt->fetchAll()]);

        case 'enrollments':
            $stmt = $pdo->query('SELECT e.id, u.first_name, u.last_name, u.email, e.course_slug, e.enrolled_at FROM enrollments e JOIN users u ON u.id = e.user_id ORDER BY e.enrolled_at DESC');
            json_out(['success' => true, 'enrollments' => $stmt->fetchAll()]);

        case 'stats':
            $total      = $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
            $students   = $pdo->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn();
            $tutors     = $pdo->query("SELECT COUNT(*) FROM users WHERE role='tutor'")->fetchColumn();
            $pending    = $pdo->query("SELECT COUNT(*) FROM users WHERE role='tutor-pending'")->fetchColumn();
            $enrolCount = $pdo->query('SELECT COUNT(*) FROM enrollments')->fetchColumn();
            json_out(['success' => true, 'stats' => compact('total','students','tutors','pending','enrolCount')]);

        case 'tutor-applications':
            $status = valid_status($_GET['status'] ?? '');
            if ($status) {
                $stmt = $pdo->prepare("SELECT * FROM tutor_applications WHERE status=? ORDER BY submitted_at DESC");
                $stmt->execute([$status]);
            } else {
                $stmt = $pdo->query("SELECT * FROM tutor_applications ORDER BY submitted_at DESC");
            }
            json_out(['success' => true, 'applications' => $stmt->fetchAll()]);

        case 'badges':
            $tutorPending   = $pdo->query("SELECT COUNT(*) FROM tutor_applications WHERE status='pending'")->fetchColumn();
            $coursePending  = (int)0;
            try { $coursePending = $pdo->query("SELECT COUNT(*) FROM course_submissions WHERE status='pending'")->fetchColumn(); } catch(PDOException $e) {}
            $payPending     = (int)0;
            try { $payPending = $pdo->query("SELECT COUNT(*) FROM payments WHERE status='pending'")->fetchColumn(); } catch(PDOException $e) {}
            json_out(['success' => true, 'tutorPending' => (int)$tutorPending, 'coursePending' => (int)$coursePending, 'paymentPending' => (int)$payPending]);

        case 'course-submissions':
            try {
                $stmt = $pdo->query('SELECT id, tutor_id, tutor_name, title, category, description, pricing, modules_count, status, submitted_at FROM course_submissions ORDER BY submitted_at DESC');
                json_out(['success' => true, 'submissions' => $stmt->fetchAll()]);
            } catch (PDOException $e) {
                json_out(['success' => true, 'submissions' => []]);
            }

        case 'payments':
            $filterStatus = in_array($_GET['status'] ?? '', ['pending', 'confirmed', 'rejected'], true) ? $_GET['status'] : null;
            $sql = 'SELECT id, student_email AS studentEmail, student_name AS studentName, course_id AS courseId, course_title AS courseTitle, amount, depositor_name AS depositorName, pay_date AS payDate, pay_time AS payTime, ref_code AS `ref`, receipt_data AS receiptData, status, submitted_at AS submittedAt, confirmed_at AS confirmedAt FROM payments';
            if ($filterStatus) {
                $stmt = $pdo->prepare($sql . ' WHERE status=? ORDER BY submitted_at DESC');
                $stmt->execute([$filterStatus]);
            } else {
                $stmt = $pdo->query($sql . ' ORDER BY submitted_at DESC');
            }
            json_out(['success' => true, 'payments' => $stmt->fetchAll()]);

        default:
            json_out(['error' => 'Unknown action'], 400);
    }
}

// ── POST actions ──────────────────────────────────────────────────────────────
if ($method === 'POST') {
    verify_csrf();
    $data = get_input();

    switch ($action) {

        case 'approve-tutor':
            $id = (int)($data['id'] ?? 0);
            if (!$id) json_out(['error' => 'ID required.'], 400);
            $stmt = $pdo->prepare("UPDATE tutor_applications SET status='approved' WHERE id=?");
            $stmt->execute([$id]);
            $stmt = $pdo->prepare("SELECT email, proposed_courses FROM tutor_applications WHERE id=?");
            $stmt->execute([$id]);
            $app = $stmt->fetch();
            if ($app && $app['email']) {
                $pdo->prepare("UPDATE users SET role='tutor' WHERE email=?")->execute([$app['email']]);
                // Assign courses: insert into tutor_courses
                $tutorRow = $pdo->prepare("SELECT id FROM users WHERE email=?");
                $tutorRow->execute([$app['email']]);
                $tutorId = (int)$tutorRow->fetchColumn();
                if ($tutorId && $app['proposed_courses']) {
                    $courses = json_decode($app['proposed_courses'], true) ?: [];
                    $ins = $pdo->prepare("INSERT IGNORE INTO tutor_courses (tutor_id, course_slug) VALUES (?, ?)");
                    foreach ($courses as $slug) {
                        if (in_array($slug, VALID_COURSE_SLUGS, true)) {
                            $ins->execute([$tutorId, $slug]);
                        }
                    }
                }
                audit_log($pdo, 'approve-tutor', "id={$id} email={$app['email']}");
            }
            json_out(['success' => true]);

        case 'reject-tutor':
            $id = (int)($data['id'] ?? 0);
            if (!$id) json_out(['error' => 'ID required.'], 400);
            $stmt = $pdo->prepare("UPDATE tutor_applications SET status='rejected' WHERE id=?");
            $stmt->execute([$id]);
            $stmt = $pdo->prepare("SELECT email FROM tutor_applications WHERE id=?");
            $stmt->execute([$id]);
            $email = $stmt->fetchColumn();
            if ($email) {
                $pdo->prepare("UPDATE users SET role='student' WHERE email=? AND role='tutor-pending'")->execute([$email]);
                audit_log($pdo, 'reject-tutor', "id={$id} email={$email}");
            }
            json_out(['success' => true]);

        case 'delete-user':
            $id = (int)($data['id'] ?? 0);
            if (!$id) json_out(['error' => 'User ID required.'], 400);
            // Prevent deleting admin user
            $check = $pdo->prepare('SELECT role FROM users WHERE id=?');
            $check->execute([$id]);
            $target = $check->fetch();
            if (!$target) json_out(['error' => 'User not found.'], 404);
            if ($target['role'] === 'admin') json_out(['error' => 'Cannot delete admin accounts.'], 403);
            // Wrap all deletes in a transaction so partial failure leaves DB consistent
            try {
                $pdo->beginTransaction();
                $pdo->prepare('DELETE FROM enrollments WHERE user_id=?')->execute([$id]);
                $pdo->prepare('DELETE FROM progress WHERE user_id=?')->execute([$id]);
                $pdo->prepare('DELETE FROM certificates WHERE user_id=?')->execute([$id]);
                $pdo->prepare('DELETE FROM tutor_courses WHERE tutor_id=?')->execute([$id]);
                $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log('[EAW] delete-user failed: ' . $e->getMessage());
                json_out(['error' => 'Failed to delete user. Please try again.'], 500);
            }
            audit_log($pdo, 'delete-user', "id={$id}");
            json_out(['success' => true]);

        case 'update-submission-status':
            $id     = (int)($data['id'] ?? 0);
            $status = valid_status($data['status'] ?? '');
            if (!$id || !$status) json_out(['error' => 'ID and valid status required.'], 400);
            try {
                $stmt = $pdo->prepare('UPDATE course_submissions SET status = ? WHERE id = ?');
                $stmt->execute([$status, $id]);
            } catch (PDOException $e) {
                json_out(['error' => 'Could not update submission status.'], 500);
            }
            audit_log($pdo, 'update-submission-status', "id={$id} status={$status}");
            json_out(['success' => true]);

        case 'confirm-payment':
            $id = (int)($data['id'] ?? 0);
            if (!$id) json_out(['error' => 'Payment ID required.'], 400);
            $stmt = $pdo->prepare("UPDATE payments SET status='confirmed', confirmed_at=NOW() WHERE id=?");
            $stmt->execute([$id]);
            audit_log($pdo, 'confirm-payment', "id={$id}");
            json_out(['success' => true]);

        case 'reject-payment':
            $id = (int)($data['id'] ?? 0);
            if (!$id) json_out(['error' => 'Payment ID required.'], 400);
            $stmt = $pdo->prepare("UPDATE payments SET status='rejected' WHERE id=?");
            $stmt->execute([$id]);
            audit_log($pdo, 'reject-payment', "id={$id}");
            json_out(['success' => true]);

        case 'add-manual-payment':
            $studentEmail  = substr(trim($data['studentEmail']  ?? ''), 0, 254);
            $studentName   = substr(trim($data['studentName']   ?? $studentEmail), 0, 120);
            $courseId      = substr(trim($data['courseId']      ?? ''), 0, 50);
            $courseTitle   = substr(trim($data['courseTitle']   ?? ''), 0, 200);
            $amount        = substr(trim($data['amount']        ?? 'Free'), 0, 50);
            $depositorName = substr(trim($data['depositorName'] ?? ''), 0, 120);
            $payDate       = substr(trim($data['payDate']       ?? ''), 0, 20);
            $payTime       = substr(trim($data['payTime']       ?? ''), 0, 20);
            $refCode       = substr(trim($data['ref']           ?? 'Manual'), 0, 100);
            $receiptData   = $data['receiptData'] ?? null;
            if (!$studentEmail || !$depositorName || !$payDate) {
                json_out(['error' => 'Student email, depositor name, and payment date are required.'], 400);
            }
            if (!filter_var($studentEmail, FILTER_VALIDATE_EMAIL)) {
                json_out(['error' => 'Invalid student email address.'], 400);
            }
            $stmt = $pdo->prepare('INSERT INTO payments (student_email, student_name, course_id, course_title, amount, depositor_name, pay_date, pay_time, ref_code, receipt_data, status, confirmed_at) VALUES (?,?,?,?,?,?,?,?,?,?,\'confirmed\',NOW())');
            $stmt->execute([$studentEmail, $studentName, $courseId, $courseTitle, $amount, $depositorName, $payDate, $payTime, $refCode, $receiptData]);
            audit_log($pdo, 'add-manual-payment', "email={$studentEmail} course={$courseId}");
            json_out(['success' => true, 'id' => (int)$pdo->lastInsertId()]);

        case 'change-admin-password':
            $newPass = $data['password'] ?? '';
            // Stronger policy for admin: 12+ chars, uppercase, digit, special
            if (strlen($newPass) < 12) json_out(['error' => 'Admin password must be at least 12 characters.'], 400);
            if (!preg_match('/[A-Z]/', $newPass)) json_out(['error' => 'Must contain an uppercase letter.'], 400);
            if (!preg_match('/[0-9]/', $newPass)) json_out(['error' => 'Must contain a number.'], 400);
            if (!preg_match('/[^A-Za-z0-9]/', $newPass)) json_out(['error' => 'Must contain a special character.'], 400);
            $hash = password_hash($newPass, PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $pdo->prepare("INSERT INTO settings (key_name, value) VALUES ('admin_password_hash',?) ON DUPLICATE KEY UPDATE value=?");
            $stmt->execute([$hash, $hash]);
            // Invalidate all other admin sessions by rotating session ID
            session_regenerate_id(true);
            audit_log($pdo, 'change-admin-password', 'password changed');
            json_out(['success' => true]);

        default:
            json_out(['error' => 'Unknown action'], 400);
    }
}

json_out(['error' => 'Method not allowed'], 405);

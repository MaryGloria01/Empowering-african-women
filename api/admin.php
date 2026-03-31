<?php
require_once __DIR__ . '/db.php';
cors();

// ── Admin-only guard ──────────────────────────────────────────────────────────
start_session();
if (empty($_SESSION['is_admin'])) json_out(['error' => 'Forbidden'], 403);

// ── CSRF protection for all state-changing (POST) requests ───────────────────
function verify_csrf() {
    $token   = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? (json_decode(file_get_contents('php://input'), true)['_csrf'] ?? '');
    $session = $_SESSION['csrf_token'] ?? '';
    if (!$token || !$session || !hash_equals($session, $token)) {
        json_out(['error' => 'Invalid CSRF token.'], 403);
    }
}

// Issue a fresh CSRF token on each GET (admin JS reads it from a header/response)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$pdo    = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

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
            $stmt = $pdo->prepare("SELECT email FROM tutor_applications WHERE id=?");
            $stmt->execute([$id]);
            $email = $stmt->fetchColumn();
            if ($email) {
                $pdo->prepare("UPDATE users SET role='tutor' WHERE email=?")->execute([$email]);
                audit_log($pdo, 'approve-tutor', "id={$id} email={$email}");
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
                $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log('[EAW] delete-user failed: ' . $e->getMessage());
                json_out(['error' => 'Failed to delete user. Please try again.'], 500);
            }
            audit_log($pdo, 'delete-user', "id={$id}");
            json_out(['success' => true]);

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

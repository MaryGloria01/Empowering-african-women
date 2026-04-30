<?php
require_once __DIR__ . '/db.php';
cors();
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

// Auto-create course_submissions table on first use
try {
    getDB()->exec("CREATE TABLE IF NOT EXISTS course_submissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tutor_id INT NOT NULL,
        tutor_name VARCHAR(120),
        title VARCHAR(200) NOT NULL,
        category VARCHAR(100),
        description TEXT,
        pricing VARCHAR(50),
        modules_count INT DEFAULT 0,
        status ENUM('pending','approved','rejected') DEFAULT 'pending',
        submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
} catch (PDOException $e) {
    debug_log("tutor-courses.php: CREATE TABLE failed: " . $e->getMessage());
}

$user = current_user();
if (!$user) {
    debug_log("tutor-courses.php: not authenticated | method=" . ($_SERVER['REQUEST_METHOD'] ?? '?'));
    json_out(['error' => 'Not authenticated'], 401);
}
if (!in_array($user['role'], ['tutor', 'tutor-pending', 'admin'], true)) {
    debug_log("tutor-courses.php: forbidden | user_id={$user['id']} | role={$user['role']}");
    json_out(['error' => 'Forbidden'], 403);
}

$method = $_SERVER['REQUEST_METHOD'];
$pdo    = getDB();

debug_log("REQUEST tutor-courses.php | method=$method | user_id={$user['id']} | role={$user['role']}");

// GET — assigned courses + submissions for this tutor
if ($method === 'GET') {
    $stmt = $pdo->prepare('SELECT course_slug FROM tutor_courses WHERE tutor_id = ?');
    $stmt->execute([$user['id']]);
    $assignedCourses = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $stmt2 = $pdo->prepare('SELECT id, title, category, description, pricing, modules_count, status, submitted_at FROM course_submissions WHERE tutor_id = ? ORDER BY submitted_at DESC');
    $stmt2->execute([$user['id']]);
    $submissions = $stmt2->fetchAll();

    debug_log("tutor-courses.php GET: assigned=" . count($assignedCourses) . " submissions=" . count($submissions) . " | user_id={$user['id']}");
    json_out(['success' => true, 'assignedCourses' => $assignedCourses, 'submissions' => $submissions]);
}

// POST — submit a new course proposal
if ($method === 'POST') {
    verify_csrf();
    $data        = get_input();
    $title       = substr(trim($data['title']       ?? ''), 0, 200);
    $category    = substr(trim($data['category']    ?? ''), 0, 100);
    $description = substr(trim($data['description'] ?? ''), 0, 2000);
    $pricing     = substr(trim($data['pricing']     ?? ''), 0, 50);
    $modules_count = max(0, (int)($data['modules_count'] ?? 0));

    if (!$title) {
        debug_log("tutor-courses.php POST: missing title | user_id={$user['id']}");
        json_out(['error' => 'Course title is required.'], 400);
    }

    $tutor_name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));

    debug_log("tutor-courses.php POST: submitting | user_id={$user['id']} | title=$title");

    $stmt = $pdo->prepare('INSERT INTO course_submissions (tutor_id, tutor_name, title, category, description, pricing, modules_count) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$user['id'], $tutor_name, $title, $category, $description, $pricing, $modules_count]);

    debug_log("tutor-courses.php POST: submitted | user_id={$user['id']} | insert_id=" . $pdo->lastInsertId());
    json_out(['success' => true]);
}

// DELETE — remove an assigned course OR withdraw a pending submission
if ($method === 'DELETE') {
    verify_csrf();

    // Withdraw pending submission
    $submissionId = (int)($_GET['submission_id'] ?? 0);
    if ($submissionId > 0) {
        debug_log("tutor-courses.php DELETE submission: id=$submissionId | user_id={$user['id']}");
        $stmt = $pdo->prepare("DELETE FROM course_submissions WHERE id = ? AND tutor_id = ? AND status = 'pending'");
        $stmt->execute([$submissionId, $user['id']]);
        if ($stmt->rowCount() === 0) {
            debug_log("tutor-courses.php DELETE submission: not found or not pending | id=$submissionId | user_id={$user['id']}");
            json_out(['error' => 'Submission not found or cannot be withdrawn.'], 404);
        }
        debug_log("tutor-courses.php DELETE submission: withdrawn | id=$submissionId | user_id={$user['id']}");
        json_out(['success' => true]);
    }

    // Remove an assigned course from tutor_courses
    $slug = trim($_GET['course_slug'] ?? '');
    if (!$slug) {
        debug_log("tutor-courses.php DELETE: missing slug or submission_id | user_id={$user['id']}");
        json_out(['error' => 'Course slug or submission_id required.'], 400);
    }
    if (!in_array($slug, VALID_COURSE_SLUGS, true)) {
        debug_log("tutor-courses.php DELETE: invalid slug | user_id={$user['id']} | slug=$slug");
        json_out(['error' => 'Invalid course.'], 400);
    }

    debug_log("tutor-courses.php DELETE: removing | user_id={$user['id']} | slug=$slug");

    $stmt = $pdo->prepare('DELETE FROM tutor_courses WHERE tutor_id = ? AND course_slug = ?');
    $stmt->execute([$user['id'], $slug]);

    debug_log("tutor-courses.php DELETE: removed | user_id={$user['id']} | slug=$slug | rows=" . $stmt->rowCount());
    json_out(['success' => true]);
}

debug_log("tutor-courses.php: method not allowed | method=$method | user_id={$user['id']}");
json_out(['error' => 'Method not allowed'], 405);

<?php
require_once __DIR__ . '/db.php';
cors();
// Never cache enrollment/progress data
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

$user = current_user();
if (!$user) {
    debug_log("enroll.php: not authenticated | method=" . ($_SERVER['REQUEST_METHOD'] ?? '?') . " | session_id=" . session_id());
    json_out(['error' => 'Not authenticated'], 401);
}

$method = $_SERVER['REQUEST_METHOD'];
debug_log("REQUEST enroll.php | method=$method | user_id={$user['id']}");

// GET — list enrolled course slugs for current user
if ($method === 'GET') {
    $pdo  = getDB();
    $stmt = $pdo->prepare('SELECT course_slug FROM enrollments WHERE user_id = ?');
    $stmt->execute([$user['id']]);
    $slugs = $stmt->fetchAll(PDO::FETCH_COLUMN);
    debug_log("enroll.php GET: enrollments | user_id={$user['id']} | count=" . count($slugs) . " | slugs=" . implode(',', $slugs));

    // Per-course lesson completion counts (for backwards compat)
    $stmt2 = $pdo->prepare('SELECT course_slug, COUNT(*) as cnt FROM progress WHERE user_id = ? GROUP BY course_slug');
    $stmt2->execute([$user['id']]);
    $progressMap = [];
    while ($row = $stmt2->fetch()) {
        $progressMap[$row['course_slug']] = (int)$row['cnt'];
    }
    debug_log("enroll.php GET: progress counts | user_id={$user['id']} | data=" . json_encode($progressMap));

    // Per-course lesson IDs — lets the dashboard calculate exact % without guessing module counts
    $stmt3 = $pdo->prepare('SELECT course_slug, lesson_id FROM progress WHERE user_id = ?');
    $stmt3->execute([$user['id']]);
    $progressDetails = [];
    while ($row = $stmt3->fetch()) {
        $progressDetails[$row['course_slug']][] = $row['lesson_id'];
    }
    debug_log("enroll.php GET: progress details | user_id={$user['id']} | courses_with_progress=" . count($progressDetails) . " | detail=" . json_encode($progressDetails));

    json_out(['success' => true, 'enrollments' => $slugs, 'progress' => $progressMap, 'progressDetails' => $progressDetails]);
}

// POST — enroll in a course
if ($method === 'POST') {
    verify_csrf();
    $data = get_input();
    $slug = trim($data['course'] ?? '');
    if (!$slug) {
        debug_log("enroll.php POST: missing course slug | user_id={$user['id']}");
        json_out(['error' => 'Course slug required.'], 400);
    }
    if (!in_array($slug, VALID_COURSE_SLUGS)) {
        debug_log("enroll.php POST: invalid slug | user_id={$user['id']} | slug=$slug");
        json_out(['error' => 'Invalid course.'], 400);
    }

    debug_log("enroll.php POST: attempting enroll | user_id={$user['id']} | course=$slug");
    $pdo = getDB();

    // Already enrolled?
    $stmt = $pdo->prepare('SELECT id FROM enrollments WHERE user_id = ? AND course_slug = ?');
    $stmt->execute([$user['id'], $slug]);
    if ($stmt->fetch()) {
        debug_log("enroll.php POST: already enrolled | user_id={$user['id']} | course=$slug");
        json_out(['success' => true, 'already' => true]);
    }

    $stmt = $pdo->prepare('INSERT INTO enrollments (user_id, course_slug, enrolled_at) VALUES (?, ?, NOW())');
    $stmt->execute([$user['id'], $slug]);
    debug_log("enroll.php POST: enrolled | user_id={$user['id']} | course=$slug | insert_id=" . $pdo->lastInsertId());

    json_out(['success' => true, 'already' => false]);
}

// DELETE — unenroll from a course
if ($method === 'DELETE') {
    verify_csrf();
    $data = get_input();
    $slug = trim($data['course'] ?? '');
    if (!$slug) {
        debug_log("enroll.php DELETE: missing course slug | user_id={$user['id']}");
        json_out(['error' => 'Course slug required.'], 400);
    }
    if (!in_array($slug, VALID_COURSE_SLUGS)) {
        debug_log("enroll.php DELETE: invalid slug | user_id={$user['id']} | slug=$slug");
        json_out(['error' => 'Invalid course.'], 400);
    }

    debug_log("enroll.php DELETE: attempting unenroll | user_id={$user['id']} | course=$slug");
    $pdo = getDB();
    $stmt = $pdo->prepare('DELETE FROM enrollments WHERE user_id = ? AND course_slug = ?');
    $stmt->execute([$user['id'], $slug]);
    debug_log("enroll.php DELETE: unenrolled | user_id={$user['id']} | course=$slug | rows_deleted=" . $stmt->rowCount());

    json_out(['success' => true]);
}

debug_log("enroll.php: method not allowed | method=$method | user_id={$user['id']}");
json_out(['error' => 'Method not allowed'], 405);

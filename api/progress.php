<?php
require_once __DIR__ . '/db.php';
cors();
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

$user = current_user();
if (!$user) {
    debug_log("progress.php: not authenticated | method=" . ($_SERVER['REQUEST_METHOD'] ?? '?') . " | session_id=" . session_id());
    json_out(['error' => 'Not authenticated'], 401);
}

$method = $_SERVER['REQUEST_METHOD'];
debug_log("REQUEST progress.php | method=$method | user_id={$user['id']}");

// GET /api/progress.php?course=baking  — get completed lessons for a course
if ($method === 'GET') {
    $slug = trim($_GET['course'] ?? '');
    if (!$slug) {
        debug_log("progress.php GET: missing course slug | user_id={$user['id']}");
        json_out(['error' => 'Course slug required.'], 400);
    }

    debug_log("progress.php GET: fetching | user_id={$user['id']} | course=$slug");
    $pdo  = getDB();
    $stmt = $pdo->prepare('SELECT lesson_id FROM progress WHERE user_id = ? AND course_slug = ?');
    $stmt->execute([$user['id'], $slug]);
    $lessons = $stmt->fetchAll(PDO::FETCH_COLUMN);
    debug_log("progress.php GET: results | user_id={$user['id']} | course=$slug | count=" . count($lessons) . " | lessons=" . implode(',', $lessons));

    // Also check if a certificate exists for this course
    $certStmt = $pdo->prepare('SELECT 1 FROM certificates WHERE user_id = ? AND course_slug = ?');
    $certStmt->execute([$user['id'], $slug]);
    $certified = (bool) $certStmt->fetch();
    debug_log("progress.php GET: cert check | user_id={$user['id']} | course=$slug | certified=" . ($certified ? 'yes' : 'no'));

    json_out(['success' => true, 'completed' => $lessons, 'certified' => $certified]);
}

// POST — mark a lesson complete
if ($method === 'POST') {
    $data     = get_input();
    $slug     = trim($data['course']  ?? '');
    $lessonId = trim($data['lesson']  ?? '');
    if (!$slug || !$lessonId) {
        debug_log("progress.php POST: missing params | user_id={$user['id']} | course='$slug' | lesson='$lessonId'");
        json_out(['error' => 'Course and lesson required.'], 400);
    }

    debug_log("progress.php POST: marking complete | user_id={$user['id']} | course=$slug | lesson=$lessonId");
    $pdo = getDB();
    // Upsert — ignore if already exists
    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO progress (user_id, course_slug, lesson_id, completed_at) VALUES (?, ?, ?, NOW())'
    );
    $stmt->execute([$user['id'], $slug, $lessonId]);
    debug_log("progress.php POST: done | user_id={$user['id']} | course=$slug | lesson=$lessonId | rows_inserted=" . $stmt->rowCount());

    // Check if all lessons done → award certificate
    // (certificate logic can be expanded later)
    json_out(['success' => true]);
}

debug_log("progress.php: method not allowed | method=$method | user_id={$user['id']}");
json_out(['error' => 'Method not allowed'], 405);

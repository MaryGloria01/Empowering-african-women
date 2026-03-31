<?php
require_once __DIR__ . '/db.php';
cors();

$user = current_user();
if (!$user) json_out(['error' => 'Not authenticated'], 401);

$method = $_SERVER['REQUEST_METHOD'];

// GET /api/progress.php?course=baking  — get completed lessons for a course
if ($method === 'GET') {
    $slug = trim($_GET['course'] ?? '');
    if (!$slug) json_out(['error' => 'Course slug required.'], 400);

    $pdo  = getDB();
    $stmt = $pdo->prepare('SELECT lesson_id FROM progress WHERE user_id = ? AND course_slug = ?');
    $stmt->execute([$user['id'], $slug]);
    $lessons = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Also check if a certificate exists for this course
    $certStmt = $pdo->prepare('SELECT 1 FROM certificates WHERE user_id = ? AND course_slug = ?');
    $certStmt->execute([$user['id'], $slug]);
    $certified = (bool) $certStmt->fetch();

    json_out(['success' => true, 'completed' => $lessons, 'certified' => $certified]);
}

// POST — mark a lesson complete
if ($method === 'POST') {
    $data     = get_input();
    $slug     = trim($data['course']  ?? '');
    $lessonId = trim($data['lesson']  ?? '');
    if (!$slug || !$lessonId) json_out(['error' => 'Course and lesson required.'], 400);

    $pdo = getDB();
    // Upsert — ignore if already exists
    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO progress (user_id, course_slug, lesson_id, completed_at) VALUES (?, ?, ?, NOW())'
    );
    $stmt->execute([$user['id'], $slug, $lessonId]);

    // Check if all lessons done → award certificate
    // (certificate logic can be expanded later)
    json_out(['success' => true]);
}

json_out(['error' => 'Method not allowed'], 405);

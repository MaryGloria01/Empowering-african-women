<?php
require_once __DIR__ . '/db.php';
cors();

$user = current_user();
if (!$user) json_out(['error' => 'Not authenticated'], 401);

$method = $_SERVER['REQUEST_METHOD'];

// GET — list enrolled course slugs for current user
if ($method === 'GET') {
    $pdo  = getDB();
    $stmt = $pdo->prepare('SELECT course_slug FROM enrollments WHERE user_id = ?');
    $stmt->execute([$user['id']]);
    $slugs = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Also fetch per-course lesson completion counts
    $stmt2 = $pdo->prepare('SELECT course_slug, COUNT(*) as cnt FROM progress WHERE user_id = ? GROUP BY course_slug');
    $stmt2->execute([$user['id']]);
    $progressMap = [];
    while ($row = $stmt2->fetch()) {
        $progressMap[$row['course_slug']] = (int)$row['cnt'];
    }

    json_out(['success' => true, 'enrollments' => $slugs, 'progress' => $progressMap]);
}

// POST — enroll in a course
if ($method === 'POST') {
    $data = get_input();
    $slug = trim($data['course'] ?? '');
    if (!$slug) json_out(['error' => 'Course slug required.'], 400);

    $pdo = getDB();

    // Already enrolled?
    $stmt = $pdo->prepare('SELECT id FROM enrollments WHERE user_id = ? AND course_slug = ?');
    $stmt->execute([$user['id'], $slug]);
    if ($stmt->fetch()) json_out(['success' => true, 'already' => true]);

    $stmt = $pdo->prepare('INSERT INTO enrollments (user_id, course_slug, enrolled_at) VALUES (?, ?, NOW())');
    $stmt->execute([$user['id'], $slug]);

    json_out(['success' => true, 'already' => false]);
}

// DELETE — unenroll from a course
if ($method === 'DELETE') {
    $data = get_input();
    $slug = trim($data['course'] ?? '');
    if (!$slug) json_out(['error' => 'Course slug required.'], 400);

    $pdo = getDB();
    $stmt = $pdo->prepare('DELETE FROM enrollments WHERE user_id = ? AND course_slug = ?');
    $stmt->execute([$user['id'], $slug]);

    json_out(['success' => true]);
}

json_out(['error' => 'Method not allowed'], 405);

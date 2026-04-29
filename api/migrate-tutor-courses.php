<?php
// ONE-TIME MIGRATION — run once as admin, then delete this file from the server.
require_once __DIR__ . '/db.php';
cors();

$user = current_user();
if (!$user || $user['role'] !== 'admin') {
    json_out(['error' => 'Admin only.'], 403);
}

$pdo     = getDB();
$results = [];

// 1. Add proposed_courses column to tutor_applications if not present
$col = $pdo->query(
    "SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'tutor_applications'
       AND COLUMN_NAME  = 'proposed_courses'"
)->fetchColumn();
if (!$col) {
    $pdo->exec("ALTER TABLE tutor_applications ADD COLUMN proposed_courses TEXT NULL AFTER bio");
    $results[] = 'Added proposed_courses column to tutor_applications';
} else {
    $results[] = 'proposed_courses column already exists — skipped';
}

// 2. Create tutor_courses table
$pdo->exec("
    CREATE TABLE IF NOT EXISTS tutor_courses (
        tutor_id    INT NOT NULL,
        course_slug VARCHAR(60) NOT NULL,
        PRIMARY KEY (tutor_id, course_slug),
        INDEX idx_tutor_id   (tutor_id),
        INDEX idx_course_slug (course_slug)
    )
");
$results[] = 'tutor_courses table ready';

json_out(['success' => true, 'results' => $results]);

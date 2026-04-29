<?php
require_once __DIR__ . '/db.php';
cors();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') json_out(['error' => 'Method not allowed'], 405);

$user = current_user();
if (!$user) json_out(['error' => 'Not authenticated'], 401);
if (!in_array($user['role'], ['tutor', 'admin'], true)) json_out(['error' => 'Tutors only.'], 403);

$pdo = getDB();

// Get this tutor's assigned course slugs
$stmt = $pdo->prepare('SELECT course_slug FROM tutor_courses WHERE tutor_id = ?');
$stmt->execute([$user['id']]);
$myCourses = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (empty($myCourses)) {
    json_out(['success' => true, 'courses' => [], 'students' => [],
              'stats' => ['total_enrolled' => 0, 'total_certificates' => 0]]);
}

$placeholders = implode(',', array_fill(0, count($myCourses), '?'));

$stmt = $pdo->prepare("
    SELECT
        u.id,
        u.first_name,
        u.last_name,
        e.course_slug,
        e.enrolled_at,
        COALESCE(pr.lessons_done, 0) AS lessons_done,
        CASE WHEN ct.user_id IS NOT NULL THEN 1 ELSE 0 END AS has_cert
    FROM enrollments e
    JOIN users u ON u.id = e.user_id
    LEFT JOIN (
        SELECT user_id, course_slug, COUNT(*) AS lessons_done
        FROM progress
        WHERE course_slug IN ($placeholders)
        GROUP BY user_id, course_slug
    ) pr ON pr.user_id = e.user_id AND pr.course_slug = e.course_slug
    LEFT JOIN certificates ct ON ct.user_id = e.user_id AND ct.course_slug = e.course_slug
    WHERE e.course_slug IN ($placeholders)
    ORDER BY e.enrolled_at DESC
");
$stmt->execute(array_merge($myCourses, $myCourses));
$students = $stmt->fetchAll();

// Cast numeric fields
foreach ($students as &$s) {
    $s['lessons_done'] = (int)$s['lessons_done'];
    $s['has_cert']     = (bool)$s['has_cert'];
}
unset($s);

$totalCerts = array_sum(array_column($students, 'has_cert'));

debug_log("tutor-students.php: tutor_id={$user['id']} | courses=" . implode(',', $myCourses) . " | students=" . count($students));

json_out([
    'success'  => true,
    'courses'  => $myCourses,
    'students' => $students,
    'stats'    => [
        'total_enrolled'    => count($students),
        'total_certificates' => (int)$totalCerts,
    ],
]);

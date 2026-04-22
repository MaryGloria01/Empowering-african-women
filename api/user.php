<?php
require_once __DIR__ . '/db.php';
cors();

$method = $_SERVER['REQUEST_METHOD'];
debug_log("REQUEST user.php | method=$method");

// GET — return current session user + enrollments + issue CSRF token
if ($method === 'GET') {
    $user = current_user();
    if (!$user) {
        debug_log("user.php GET: session not authenticated | session_id=" . session_id());
        json_out(['error' => 'Not authenticated'], 401);
    }
    debug_log("user.php GET: authenticated | user_id={$user['id']} | email={$user['email']} | role={$user['role']}");
    start_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    header('X-CSRF-Token: ' . $_SESSION['csrf_token']);

    $pdo = getDB();

    $enrStmt = $pdo->prepare('SELECT course_slug FROM enrollments WHERE user_id = ?');
    $enrStmt->execute([$user['id']]);
    $enrollmentSlugs = $enrStmt->fetchAll(PDO::FETCH_COLUMN);
    debug_log("user.php GET: enrollments | user_id={$user['id']} | count=" . count($enrollmentSlugs) . " | slugs=" . implode(',', $enrollmentSlugs));

    $progStmt = $pdo->prepare('SELECT course_slug, COUNT(*) as cnt FROM progress WHERE user_id = ? GROUP BY course_slug');
    $progStmt->execute([$user['id']]);
    $progressMap = [];
    while ($row = $progStmt->fetch()) { $progressMap[$row['course_slug']] = (int)$row['cnt']; }
    debug_log("user.php GET: progress counts | user_id={$user['id']} | data=" . json_encode($progressMap));

    // Per-course lesson IDs — exact source of truth for progress %
    $progDetailStmt = $pdo->prepare('SELECT course_slug, lesson_id FROM progress WHERE user_id = ?');
    $progDetailStmt->execute([$user['id']]);
    $progressDetails = [];
    while ($row = $progDetailStmt->fetch()) {
        $progressDetails[$row['course_slug']][] = $row['lesson_id'];
    }
    debug_log("user.php GET: progress details | user_id={$user['id']} | courses_with_progress=" . count($progressDetails));

    $certStmt = $pdo->prepare('SELECT course_slug FROM certificates WHERE user_id = ?');
    $certStmt->execute([$user['id']]);
    $certSlugs = $certStmt->fetchAll(PDO::FETCH_COLUMN);
    debug_log("user.php GET: certificates | user_id={$user['id']} | count=" . count($certSlugs) . " | slugs=" . implode(',', $certSlugs));

    json_out(['success' => true,
        'enrollments'     => $enrollmentSlugs,
        'progress'        => $progressMap,
        'progressDetails' => $progressDetails,
        'certificates'    => $certSlugs,
        'user' => [
            'id'         => (int)$user['id'],
            'firstName'  => $user['first_name'],
            'lastName'   => $user['last_name'],
            'email'      => $user['email'],
            'phone'      => $user['phone'],
            'role'       => $user['role'],
            'isVerified' => (bool)$user['is_verified'],
        ]]);
}

// POST — update profile (own data only, validated & length-capped)
if ($method === 'POST') {
    // Rate limiting: max 10 profile updates per IP per hour
    $ip      = preg_replace('/[^a-f0-9:.]/', '', explode(',', ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown'))[0]);
    $rl_file = sys_get_temp_dir() . '/eaw_user_' . md5($ip) . '.json';
    $rl      = file_exists($rl_file) ? json_decode(file_get_contents($rl_file), true) : ['count' => 0, 'window_start' => time()];
    if (time() - ($rl['window_start'] ?? 0) > 3600) { $rl = ['count' => 0, 'window_start' => time()]; }
    if (($rl['count'] ?? 0) >= 10) {
        debug_log("user.php POST: rate limit hit | ip=$ip");
        json_out(['error' => 'Too many requests. Please try again later.'], 429);
    }
    $user = current_user();
    if (!$user) {
        debug_log("user.php POST: not authenticated | session_id=" . session_id());
        json_out(['error' => 'Not authenticated'], 401);
    }

    // CSRF — always required on POST, not optional
    start_session();
    $csrf_header  = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $csrf_session = $_SESSION['csrf_token']       ?? '';
    if (!$csrf_header || !$csrf_session || !hash_equals($csrf_session, $csrf_header)) {
        debug_log("user.php POST: CSRF FAIL | user_id={$user['id']} | header_present=" . ($csrf_header ? 'yes' : 'no') . " | session_token_present=" . ($csrf_session ? 'yes' : 'no'));
        json_out(['error' => 'Invalid CSRF token.'], 403);
    }

    $data      = get_input();
    $firstName = substr(trim($data['firstName'] ?? $user['first_name']), 0, 60);
    $lastName  = substr(trim($data['lastName']  ?? $user['last_name']),  0, 60);
    $phone     = substr(preg_replace('/[^0-9+\-\s()]/', '', $data['phone'] ?? $user['phone'] ?? ''), 0, 20);

    if (!$firstName || !$lastName) {
        debug_log("user.php POST: validation fail | user_id={$user['id']} | firstName='$firstName' | lastName='$lastName'");
        json_out(['error' => 'Name fields are required.'], 400);
    }

    debug_log("user.php POST: updating profile | user_id={$user['id']} | firstName=$firstName | lastName=$lastName | phone=$phone");
    $pdo  = getDB();
    $stmt = $pdo->prepare('UPDATE users SET first_name=?, last_name=?, phone=? WHERE id=?');
    $stmt->execute([$firstName, $lastName, $phone, $user['id']]);
    debug_log("user.php POST: profile updated | user_id={$user['id']} | rows_affected=" . $stmt->rowCount());

    // Increment rate limit counter
    $rl['count'] = ($rl['count'] ?? 0) + 1;
    file_put_contents($rl_file, json_encode($rl));

    json_out(['success' => true]);
}

json_out(['error' => 'Method not allowed'], 405);

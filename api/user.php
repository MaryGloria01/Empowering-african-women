<?php
require_once __DIR__ . '/db.php';
cors();

$method = $_SERVER['REQUEST_METHOD'];

// GET — return current session user + issue CSRF token
if ($method === 'GET') {
    $user = current_user();
    if (!$user) json_out(['error' => 'Not authenticated'], 401);
    start_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    header('X-CSRF-Token: ' . $_SESSION['csrf_token']);
    json_out(['success' => true, 'user' => [
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
        json_out(['error' => 'Too many requests. Please try again later.'], 429);
    }
    $user = current_user();
    if (!$user) json_out(['error' => 'Not authenticated'], 401);

    // CSRF — always required on POST, not optional
    start_session();
    $csrf_header  = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $csrf_session = $_SESSION['csrf_token']       ?? '';
    if (!$csrf_header || !$csrf_session || !hash_equals($csrf_session, $csrf_header)) {
        json_out(['error' => 'Invalid CSRF token.'], 403);
    }

    $data      = get_input();
    $firstName = substr(trim($data['firstName'] ?? $user['first_name']), 0, 60);
    $lastName  = substr(trim($data['lastName']  ?? $user['last_name']),  0, 60);
    $phone     = substr(preg_replace('/[^0-9+\-\s()]/', '', $data['phone'] ?? $user['phone'] ?? ''), 0, 20);

    if (!$firstName || !$lastName) json_out(['error' => 'Name fields are required.'], 400);

    $pdo  = getDB();
    $stmt = $pdo->prepare('UPDATE users SET first_name=?, last_name=?, phone=? WHERE id=?');
    $stmt->execute([$firstName, $lastName, $phone, $user['id']]);

    // Increment rate limit counter
    $rl['count'] = ($rl['count'] ?? 0) + 1;
    file_put_contents($rl_file, json_encode($rl));

    json_out(['success' => true]);
}

json_out(['error' => 'Method not allowed'], 405);

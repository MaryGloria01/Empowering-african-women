<?php
require_once __DIR__ . '/db.php';
cors();
header('Cache-Control: no-store, no-cache, must-revalidate');

$method = $_SERVER['REQUEST_METHOD'];

// GET ?check=email — check if email exists (no auth, no user info leaked beyond existence)
if ($method === 'GET') {
    $email = strtolower(trim($_GET['check'] ?? ''));
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
        json_out(['error' => 'Invalid email.'], 400);
    }
    $pdo  = getDB();
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    json_out(['exists' => (bool)$stmt->fetch()]);
}

// POST — update password after OTP verified client-side
if ($method === 'POST') {
    // Rate limiting: max 5 reset attempts per IP per hour
    $ip      = preg_replace('/[^a-f0-9:.]/', '', explode(',', ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown'))[0]);
    $rl_file = rl_dir() . 'eaw_reset_' . md5($ip) . '.json';
    $rl      = file_exists($rl_file) ? json_decode(file_get_contents($rl_file), true) : ['count' => 0, 'window_start' => time()];
    if (time() - ($rl['window_start'] ?? 0) > 3600) { $rl = ['count' => 0, 'window_start' => time()]; }
    if (($rl['count'] ?? 0) >= 5) {
        json_out(['error' => 'Too many reset attempts. Please try again later.'], 429);
    }

    $data     = get_input();
    $email    = strtolower(trim($data['email'] ?? ''));
    $password = $data['new_password'] ?? '';

    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
        json_out(['error' => 'Invalid email address.'], 400);
    }

    if (strlen($password) < 8 || strlen($password) > 128) {
        json_out(['error' => 'Password must be 8–128 characters.'], 400);
    }
    if (!preg_match('/[A-Z]/', $password)) {
        json_out(['error' => 'Password must contain at least one uppercase letter.'], 400);
    }
    if (!preg_match('/[0-9]/', $password)) {
        json_out(['error' => 'Password must contain at least one number.'], 400);
    }
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        json_out(['error' => 'Password must contain at least one special character.'], 400);
    }

    $pdo  = getDB();
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    if (!$stmt->fetch()) {
        // Increment rate limit even on failure to prevent enumeration via timing
        $rl['count'] = ($rl['count'] ?? 0) + 1;
        file_put_contents($rl_file, json_encode($rl));
        json_out(['error' => 'No account found with that email address.'], 404);
    }

    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    $pdo->prepare('UPDATE users SET password_hash = ? WHERE email = ?')
        ->execute([$hash, $email]);

    $rl['count'] = ($rl['count'] ?? 0) + 1;
    file_put_contents($rl_file, json_encode($rl));

    debug_log("reset-password.php: password updated | email=$email | ip=$ip");
    json_out(['success' => true]);
}

json_out(['error' => 'Method not allowed'], 405);

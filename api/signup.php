<?php
require_once __DIR__ . '/db.php';
cors();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['error' => 'Method not allowed'], 405);

// ── Server-side rate limiting ─────────────────────────────────────────────────
$ip      = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$ip      = preg_replace('/[^a-f0-9:.]/', '', explode(',', $ip)[0]);
$rl_file = sys_get_temp_dir() . '/eaw_signup_' . md5($ip) . '.json';
$rl      = file_exists($rl_file) ? json_decode(file_get_contents($rl_file), true) : ['count' => 0, 'window_start' => time()];

// Allow max 5 signups per IP per hour
if (time() - ($rl['window_start'] ?? 0) > 3600) {
    $rl = ['count' => 0, 'window_start' => time()];
}
if (($rl['count'] ?? 0) >= 5) {
    json_out(['error' => 'Too many accounts created from this connection. Please try again later.'], 429);
}

$data      = get_input();
$firstName = substr(trim($data['firstName'] ?? ''), 0, 60);
$lastName  = substr(trim($data['lastName']  ?? ''), 0, 60);
$email     = strtolower(substr(trim($data['email'] ?? ''), 0, 254));
$phone     = substr(trim($data['phone'] ?? ''), 0, 20);
$password  = $data['password'] ?? '';

// Role is ALWAYS student — never trust client-supplied role
$role = 'student';

// Validate required fields
if (!$firstName || !$lastName || !$email || !$password)
    json_out(['error' => 'All fields are required.'], 400);

if (!filter_var($email, FILTER_VALIDATE_EMAIL))
    json_out(['error' => 'Invalid email address.'], 400);

// Password complexity: min 8 chars, 1 uppercase, 1 digit, 1 special char
if (strlen($password) < 8 || strlen($password) > 128)
    json_out(['error' => 'Password must be 8–128 characters.'], 400);
if (!preg_match('/[A-Z]/', $password))
    json_out(['error' => 'Password must contain at least one uppercase letter.'], 400);
if (!preg_match('/[0-9]/', $password))
    json_out(['error' => 'Password must contain at least one number.'], 400);
if (!preg_match('/[^A-Za-z0-9]/', $password))
    json_out(['error' => 'Password must contain at least one special character.'], 400);

$pdo = getDB();

// Check duplicate email
$stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
$stmt->execute([$email]);
if ($stmt->fetch()) json_out(['error' => 'Unable to create account with this email. If you already have an account, please log in.'], 409);

// Insert user
$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
$stmt = $pdo->prepare('INSERT INTO users (first_name, last_name, email, phone, password_hash, role, is_verified, created_at) VALUES (?, ?, ?, ?, ?, ?, 0, NOW())');
$stmt->execute([$firstName, $lastName, $email, $phone, $hash, $role]);
$userId = $pdo->lastInsertId();

// Track referral if provided (validate it's alphanumeric only)
if (!empty($data['ref']) && preg_match('/^[A-Z0-9]{6,10}$/', strtoupper($data['ref']))) {
    $pdo->prepare("UPDATE users SET referred_by = ? WHERE id = ?")->execute([strtoupper($data['ref']), $userId]);
}

// Update rate limit counter
$rl['count'] = ($rl['count'] ?? 0) + 1;
file_put_contents($rl_file, json_encode($rl));

// Start session with regenerated ID
start_session();
session_regenerate_id(true);
$_SESSION['user_id'] = $userId;

json_out(['success' => true, 'user' => [
    'id'        => (int)$userId,
    'firstName' => $firstName,
    'lastName'  => $lastName,
    'email'     => $email,
    'phone'     => $phone,
    'role'      => $role,
]]);

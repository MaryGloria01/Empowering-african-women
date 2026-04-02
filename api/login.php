<?php
require_once __DIR__ . '/db.php';
cors();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['error' => 'Method not allowed'], 405);

// ── Server-side rate limiting (file-based, no DB needed) ─────────────────────
$ip       = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$ip       = preg_replace('/[^a-f0-9:.]/', '', explode(',', $ip)[0]); // sanitise IP
$rl_file  = sys_get_temp_dir() . '/eaw_login_' . md5($ip) . '.json';
$rl       = file_exists($rl_file) ? json_decode(file_get_contents($rl_file), true) : ['attempts' => 0, 'lock_until' => 0];

if (time() < ($rl['lock_until'] ?? 0)) {
    $secs = $rl['lock_until'] - time();
    json_out(['error' => "Too many failed attempts. Try again in {$secs} seconds."], 429);
}

$data     = get_input();
$email    = strtolower(trim($data['email'] ?? ''));
$password = $data['password'] ?? '';

if (!$email || !$password) json_out(['error' => 'Email and password are required.'], 400);
if (strlen($email) > 254 || strlen($password) > 128) json_out(['error' => 'Invalid input.'], 400);

$pdo = getDB();

// ── Admin check (no hardcoded password — reads hash from DB settings) ─────────
$adminStmt = $pdo->prepare('SELECT value FROM settings WHERE key_name = ?');
$adminStmt->execute(['admin_email']);
$adminEmail = $adminStmt->fetchColumn() ?: 'ogechi@eaw.admin';
$adminStmt->execute(['admin_password_hash']);
$adminHash = $adminStmt->fetchColumn();

if ($email === strtolower($adminEmail)) {
    if (!$adminHash) {
        // No hash set yet — force admin to set password via portal first
        json_out(['error' => 'Admin account not initialised. Please contact system administrator.'], 403);
    }
    if (password_verify($password, $adminHash)) {
        $rl = ['attempts' => 0, 'lock_until' => 0];
        file_put_contents($rl_file, json_encode($rl));
        start_session();
        session_regenerate_id(true);
        $_SESSION['user_id'] = 0;
        $_SESSION['is_admin'] = true;
        json_out(['success' => true, 'token' => bin2hex(random_bytes(16)),
            'user' => ['id' => 0, 'firstName' => 'Admin', 'lastName' => '', 'email' => $email, 'role' => 'admin']]);
    }
    _fail_login($rl, $rl_file);
}

// ── Regular user login ────────────────────────────────────────────────────────
$stmt = $pdo->prepare('SELECT id, first_name, last_name, email, phone, role, password_hash, is_verified FROM users WHERE email = ?');
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    _fail_login($rl, $rl_file);
}

// Success — reset rate limit, regenerate session
$rl = ['attempts' => 0, 'lock_until' => 0];
file_put_contents($rl_file, json_encode($rl));
start_session();
session_regenerate_id(true);
$_SESSION['user_id']   = $user['id'];
$_SESSION['user_role'] = $user['role'];
unset($_SESSION['is_admin']);

// Fetch enrollments so every browser gets them immediately on login
$enrStmt = $pdo->prepare('SELECT course_slug FROM enrollments WHERE user_id = ?');
$enrStmt->execute([$user['id']]);
$enrollmentSlugs = $enrStmt->fetchAll(PDO::FETCH_COLUMN);

$progStmt = $pdo->prepare('SELECT course_slug, COUNT(*) as cnt FROM progress WHERE user_id = ? GROUP BY course_slug');
$progStmt->execute([$user['id']]);
$progressMap = [];
while ($row = $progStmt->fetch()) { $progressMap[$row['course_slug']] = (int)$row['cnt']; }

json_out(['success' => true,
    'enrollments' => $enrollmentSlugs,
    'progress'    => $progressMap,
    'user' => [
        'id'         => (int)$user['id'],
        'firstName'  => $user['first_name'],
        'lastName'   => $user['last_name'],
        'email'      => $user['email'],
        'phone'      => $user['phone'],
        'role'       => $user['role'],
        'isVerified' => (bool)$user['is_verified'],
    ]]);

function _fail_login(&$rl, $rl_file) {
    $rl['attempts'] = ($rl['attempts'] ?? 0) + 1;
    if ($rl['attempts'] >= 5) {
        $rl['lock_until'] = time() + 300; // 5 minute lockout
        $rl['attempts']   = 0;
        file_put_contents($rl_file, json_encode($rl));
        json_out(['error' => 'Too many failed attempts. Access locked for 5 minutes.'], 429);
    }
    file_put_contents($rl_file, json_encode($rl));
    $remaining = 5 - $rl['attempts'];
    json_out(['error' => "Invalid email or password. {$remaining} attempt(s) remaining."], 401);
}

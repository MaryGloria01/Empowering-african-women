<?php
require_once __DIR__ . '/db.php';
cors();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['error' => 'Method not allowed'], 405);

// ── Rate limiting: max 3 tutor applications per IP per hour ──────────────────
$ip      = preg_replace('/[^a-f0-9:.]/', '', explode(',', ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown'))[0]);
$rl_file = sys_get_temp_dir() . '/eaw_apply_' . md5($ip) . '.json';
$rl      = file_exists($rl_file) ? json_decode(file_get_contents($rl_file), true) : ['count' => 0, 'window_start' => time()];
if (time() - ($rl['window_start'] ?? 0) > 3600) { $rl = ['count' => 0, 'window_start' => time()]; }
if (($rl['count'] ?? 0) >= 3) {
    json_out(['error' => 'Too many applications from this connection. Please try again later.'], 429);
}

$data      = get_input();
$firstName = substr(trim($data['firstName'] ?? ''), 0, 60);
$lastName  = substr(trim($data['lastName']  ?? ''), 0, 60);
$email     = strtolower(substr(trim($data['email'] ?? ''), 0, 254));
$phone     = substr(preg_replace('/[^0-9+\-\s()]/', '', $data['phone'] ?? ''), 0, 20);
$expertise = substr(trim($data['expertise'] ?? ''), 0, 100);
$bio       = substr(trim($data['bio'] ?? ''), 0, 1000);

if (!$firstName || !$lastName || !$email)
    json_out(['error' => 'Name and email are required.'], 400);

if (!filter_var($email, FILTER_VALIDATE_EMAIL))
    json_out(['error' => 'Invalid email address.'], 400);

$pdo = getDB();

// Block reapplication if ANY prior application exists (pending, rejected, or approved)
$stmt = $pdo->prepare("SELECT status FROM tutor_applications WHERE email = ? ORDER BY submitted_at DESC LIMIT 1");
$stmt->execute([$email]);
$existing = $stmt->fetch();
if ($existing) {
    if ($existing['status'] === 'pending') {
        json_out(['error' => 'Your application is already under review.'], 409);
    }
    if ($existing['status'] === 'approved') {
        json_out(['error' => 'This email is already registered as a tutor.'], 409);
    }
    if ($existing['status'] === 'rejected') {
        json_out(['error' => 'A previous application from this email was not accepted. Please contact support.'], 409);
    }
}

// Insert application
$stmt = $pdo->prepare('INSERT INTO tutor_applications (first_name, last_name, email, phone, expertise, bio) VALUES (?, ?, ?, ?, ?, ?)');
$stmt->execute([$firstName, $lastName, $email, $phone, $expertise, $bio]);

// Increment rate limit counter
$rl['count'] = ($rl['count'] ?? 0) + 1;
file_put_contents($rl_file, json_encode($rl));

// Update user role to tutor-pending if they have an account
$pdo->prepare("UPDATE users SET role='tutor-pending' WHERE email=? AND role='student'")->execute([$email]);

json_out(['success' => true]);

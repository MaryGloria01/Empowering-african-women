<?php
require_once __DIR__ . '/db.php';
cors();

$user = current_user();
if (!$user) json_out(['error' => 'Not authenticated'], 401);

$pdo = getDB();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Get or generate referral code for this user
    $stmt = $pdo->prepare("SELECT referral_code FROM users WHERE id = ?");
    $stmt->execute([$user['id']]);
    $code = $stmt->fetchColumn();

    if (!$code) {
        // Generate unique code
        $code = strtoupper(substr(base_convert(sha1(uniqid($user['id'], true)), 16, 36), 0, 8));
        $pdo->prepare("UPDATE users SET referral_code = ? WHERE id = ?")->execute([$code, $user['id']]);
    }

    // Count referred users
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE referred_by = ?");
    $stmt->execute([$code]);
    $count = (int)$stmt->fetchColumn();

    // Get referred users (names only, no emails)
    $stmt = $pdo->prepare("SELECT first_name, last_name, created_at FROM users WHERE referred_by = ? ORDER BY created_at DESC LIMIT 20");
    $stmt->execute([$code]);
    $referred = $stmt->fetchAll();

    json_out(['success' => true, 'referral_code' => $code, 'referred_count' => $count, 'referred' => $referred]);
}

json_out(['error' => 'Method not allowed'], 405);

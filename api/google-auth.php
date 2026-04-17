<?php
require_once __DIR__ . '/db.php';
cors();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['error' => 'Method not allowed'], 405);

// ── Your Google OAuth Client ID ───────────────────────────────────────────────
define('GOOGLE_CLIENT_ID', 'YOUR_GOOGLE_CLIENT_ID_HERE.apps.googleusercontent.com');

$data    = get_input();
$idToken = trim($data['id_token'] ?? '');

if (!$idToken || strlen($idToken) > 4096) json_out(['error' => 'Missing or invalid token.'], 400);

// ── Verify token with Google's tokeninfo endpoint ─────────────────────────────
$payload = _google_tokeninfo($idToken);
if (!$payload) json_out(['error' => 'Could not reach Google. Please try again.'], 502);

// Validate required claims
if (empty($payload['sub']))                                                         json_out(['error' => 'Invalid Google token (no sub).'], 401);
if (($payload['aud'] ?? '') !== GOOGLE_CLIENT_ID)                                   json_out(['error' => 'Token audience mismatch.'], 401);
if (!in_array($payload['iss'] ?? '', ['accounts.google.com', 'https://accounts.google.com'])) json_out(['error' => 'Invalid token issuer.'], 401);
if (($payload['email_verified'] ?? '') !== 'true')                                  json_out(['error' => 'Google email is not verified.'], 401);

$googleId  = $payload['sub'];
$email     = strtolower(trim($payload['email'] ?? ''));
$firstName = substr(trim($payload['given_name']  ?? 'User'), 0, 60);
$lastName  = substr(trim($payload['family_name'] ?? ''),     0, 60);

if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) json_out(['error' => 'Invalid email from Google.'], 400);

$pdo = getDB();

// ── Find existing user by google_id OR email ──────────────────────────────────
$stmt = $pdo->prepare('SELECT id, first_name, last_name, email, phone, role, google_id, auth_provider FROM users WHERE google_id = ? OR email = ? LIMIT 1');
$stmt->execute([$googleId, $email]);
$user = $stmt->fetch();

if ($user) {
    // Link google_id to an existing email-based account if not already linked
    if (!$user['google_id']) {
        $pdo->prepare('UPDATE users SET google_id = ?, auth_provider = ? WHERE id = ?')
            ->execute([$googleId, 'google', $user['id']]);
    }
    $userId    = (int)$user['id'];
    $firstName = $user['first_name'];
    $lastName  = $user['last_name'];
    $role      = $user['role'];
    $phone     = $user['phone'] ?? '';
} else {
    // New user — create student account (role always 'student' for self-signup)
    $fakeHash = 'google_oauth_' . bin2hex(random_bytes(16));
    $ins = $pdo->prepare('INSERT INTO users (first_name, last_name, email, phone, password_hash, role, google_id, auth_provider, is_verified, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())');
    $ins->execute([$firstName, $lastName, $email, '', $fakeHash, 'student', $googleId, 'google']);
    $userId = (int)$pdo->lastInsertId();
    $role   = 'student';
    $phone  = '';
}

// ── Session ───────────────────────────────────────────────────────────────────
start_session();
session_regenerate_id(true);
$_SESSION['user_id']   = $userId;
$_SESSION['user_role'] = $role;
unset($_SESSION['is_admin']);

// ── Return same shape as login.php ────────────────────────────────────────────
$enrStmt = $pdo->prepare('SELECT course_slug FROM enrollments WHERE user_id = ?');
$enrStmt->execute([$userId]);
$enrollmentSlugs = $enrStmt->fetchAll(PDO::FETCH_COLUMN);

$progStmt = $pdo->prepare('SELECT course_slug, COUNT(*) as cnt FROM progress WHERE user_id = ? GROUP BY course_slug');
$progStmt->execute([$userId]);
$progressMap = [];
while ($row = $progStmt->fetch()) { $progressMap[$row['course_slug']] = (int)$row['cnt']; }

json_out(['success' => true,
    'enrollments' => $enrollmentSlugs,
    'progress'    => $progressMap,
    'user' => [
        'id'         => $userId,
        'firstName'  => $firstName,
        'lastName'   => $lastName,
        'email'      => $email,
        'phone'      => $phone,
        'role'       => $role,
        'isVerified' => true,
    ]]);

// ── HTTP helper (prefers cURL, falls back to file_get_contents) ───────────────
function _google_tokeninfo($token) {
    $url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($token);
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $body = curl_exec($ch);
        curl_close($ch);
    } else {
        $ctx  = stream_context_create(['http' => ['timeout' => 10]]);
        $body = @file_get_contents($url, false, $ctx);
    }
    if (!$body) return null;
    $p = json_decode($body, true);
    return (is_array($p) && isset($p['sub'])) ? $p : null;
}

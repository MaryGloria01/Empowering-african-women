<?php
require_once __DIR__ . '/db.php';
cors();
start_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['error' => 'Method not allowed'], 405);

debug_log('REQUEST google-auth.php | method=POST');

// ── Your Google OAuth Client ID ───────────────────────────────────────────────
define('GOOGLE_CLIENT_ID', '628340119385-bg1k4gkadmu7ngpha69o44us9fhvlefe.apps.googleusercontent.com');

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

// ── mode: 'login' (default) = never create; 'signup' = create if not found ────
$mode = ($data['mode'] ?? 'login') === 'signup' ? 'signup' : 'login';

$pdo = getDB();

// ── Look up by google_id first, then fall back to email ───────────────────────
$stmt = $pdo->prepare('SELECT id, first_name, last_name, email, phone, role, google_id FROM users WHERE google_id = ? LIMIT 1');
$stmt->execute([$googleId]);
$user = $stmt->fetch();

if (!$user) {
    // No match on google_id — look up by email (most important: same email = same person)
    $stmt = $pdo->prepare('SELECT id, first_name, last_name, email, phone, role, google_id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
}

if ($user) {
    // ── Existing account — link google_id if not already linked ───────────────
    if (!$user['google_id']) {
        $pdo->prepare('UPDATE users SET google_id = ? WHERE id = ?')
            ->execute([$googleId, $user['id']]);
        error_log('[EAW google-auth] email=' . $email . ' action=linked_google_id user_id=' . $user['id']);
    } else {
        error_log('[EAW google-auth] email=' . $email . ' action=login user_id=' . $user['id'] . ' mode=' . $mode);
    }
    $userId    = (int)$user['id'];
    $firstName = $user['first_name'];
    $lastName  = $user['last_name'];
    $role      = $user['role'];
    $phone     = $user['phone'] ?? '';
} elseif ($mode === 'signup') {
    // ── Sign-up flow: INSERT with try/catch to block any race-condition duplicate
    try {
        $fakeHash = 'google_oauth_' . bin2hex(random_bytes(16));
        $ins = $pdo->prepare('INSERT INTO users (first_name, last_name, email, phone, password_hash, role, google_id, auth_provider, is_verified, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())');
        $ins->execute([$firstName, $lastName, $email, '', $fakeHash, 'student', $googleId, 'google']);
        $userId = (int)$pdo->lastInsertId();
        $role   = 'student';
        $phone  = '';
        error_log('[EAW google-auth] email=' . $email . ' action=new_account_google user_id=' . $userId);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            // Race condition: another request inserted this email between our SELECT and INSERT
            // Recover by fetching the existing row and logging them in
            error_log('[EAW google-auth] email=' . $email . ' action=race_condition_recovery');
            $stmt = $pdo->prepare('SELECT id, first_name, last_name, email, phone, role, google_id FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $existing = $stmt->fetch();
            if (!$existing) json_out(['error' => 'Account creation failed. Please try again.'], 500);
            if (!$existing['google_id']) {
                $pdo->prepare('UPDATE users SET google_id = ? WHERE id = ?')->execute([$googleId, $existing['id']]);
            }
            $userId    = (int)$existing['id'];
            $firstName = $existing['first_name'];
            $lastName  = $existing['last_name'];
            $role      = $existing['role'];
            $phone     = $existing['phone'] ?? '';
        } else {
            throw $e;
        }
    }
} else {
    // ── Login flow — no account found, refuse ─────────────────────────────────
    error_log('[EAW google-auth] email=' . $email . ' action=rejected_no_account mode=login');
    json_out(['error' => 'No account found with this Google email. Please sign up first.'], 404);
}

// ── Session ───────────────────────────────────────────────────────────────────
session_regenerate_id(true);
$_SESSION['user_id']   = $userId;
$_SESSION['user_role'] = $role;
unset($_SESSION['is_admin']);
debug_log("GOOGLE AUTH SUCCESS | email=$email | user_id=$userId | mode=$mode");

// ── Return same shape as login.php ────────────────────────────────────────────
$enrStmt = $pdo->prepare('SELECT course_slug FROM enrollments WHERE user_id = ?');
$enrStmt->execute([$userId]);
$enrollmentSlugs = $enrStmt->fetchAll(PDO::FETCH_COLUMN);
debug_log("GOOGLE AUTH: enrollments | user_id=$userId | count=" . count($enrollmentSlugs) . " | slugs=" . implode(',', $enrollmentSlugs));

$progStmt = $pdo->prepare('SELECT course_slug, COUNT(*) as cnt FROM progress WHERE user_id = ? GROUP BY course_slug');
$progStmt->execute([$userId]);
$progressMap = [];
while ($row = $progStmt->fetch()) { $progressMap[$row['course_slug']] = (int)$row['cnt']; }

// Per-course lesson IDs for accurate % calculation (same as login.php)
$detailStmt = $pdo->prepare('SELECT course_slug, lesson_id FROM progress WHERE user_id = ?');
$detailStmt->execute([$userId]);
$progressDetails = [];
while ($row = $detailStmt->fetch()) { $progressDetails[$row['course_slug']][] = $row['lesson_id']; }

json_out(['success' => true,
    'sid'             => session_id(),
    'enrollments'     => $enrollmentSlugs,
    'progress'        => $progressMap,
    'progressDetails' => $progressDetails,
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

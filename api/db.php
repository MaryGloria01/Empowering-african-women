<?php
// Force off any host-level display_errors so PHP warnings never corrupt JSON output
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ERROR | E_PARSE);

require_once __DIR__ . '/config.php';

// Global error handler — never leak file paths or stack traces to client
set_exception_handler(function(Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    // Log full detail server-side, return generic message to client
    error_log('[EAW] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    echo json_encode(['error' => 'An internal error occurred. Please try again.']);
    exit;
});

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

function json_out($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function get_input() {
    return json_decode(file_get_contents('php://input'), true) ?? [];
}

// Fix #10: Security headers for all API endpoints
function security_headers(): void {
    header('Content-Security-Policy: default-src \'none\'', true);
    header('X-Content-Type-Options: nosniff', true);
    header('X-Frame-Options: DENY', true);
    header('Referrer-Policy: no-referrer', true);
}

function cors() {
    $allowed = ['https://empoweringafricanwomen.com', 'https://www.empoweringafricanwomen.com'];
    $origin  = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (in_array($origin, $allowed)) {
        header("Access-Control-Allow-Origin: $origin");
        header('Access-Control-Allow-Credentials: true');
    }
    header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, X-Session-Id');
    header('Access-Control-Expose-Headers: X-CSRF-Token');
    security_headers();
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
}

function start_session() {
    if (session_status() === PHP_SESSION_NONE) {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
                || (!empty($_SERVER['HTTP_X_FORWARDED_SSL'])   && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on');

        ini_set('session.use_trans_sid',    '0');
        ini_set('session.use_only_cookies', '0'); // allow header-based session ID
        ini_set('session.gc_maxlifetime',   (string)(86400 * 30));
        session_set_cookie_params([
            'lifetime' => 86400 * 30,
            'path'     => '/',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        // Token auth fallback: if browser has no session cookie (different tab/browser),
        // accept the session ID from the X-Session-Id header sent by JS from localStorage.
        // Fix #5: removed stray comma from character class (valid session IDs are hex + hyphen only)
        $cookieSid = $_COOKIE[session_name()] ?? '';
        $headerSid = trim($_SERVER['HTTP_X_SESSION_ID'] ?? '');
        if (!$cookieSid && $headerSid && preg_match('/^[a-zA-Z0-9\-]{20,128}$/', $headerSid)) {
            session_id($headerSid);
        }

        session_start();
        debug_log('start_session: started | isHttps=' . ($isHttps ? 'true' : 'false') . ' | session_id=' . session_id() . ' | via=' . ($cookieSid ? 'cookie' : ($headerSid ? 'header' : 'new')));
    }
}

// ── Debug Logging ─────────────────────────────────────────────────────────────
// Log file lives at public_html/debug.log — blocked from HTTP by .htaccess FilesMatch *.log rule.
// Format: [YYYY-MM-DD HH:MM:SS] [file.php] [uid=X] message
define('EAW_DEBUG_LOG', __DIR__ . '/../debug.log');

function debug_log(string $message, string $file = ''): void {
    $ts   = date('Y-m-d H:i:s');
    $page = $file ?: basename(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]['file'] ?? 'unknown');
    $uid  = (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['user_id']))
            ? 'uid=' . $_SESSION['user_id']
            : 'uid=NONE';
    $line = "[$ts] [$page] [$uid] $message" . PHP_EOL;
    @file_put_contents(EAW_DEBUG_LOG, $line, FILE_APPEND | LOCK_EX);
}

// Fix #7: CSRF token verification for state-changing requests
function verify_csrf(): void {
    start_session();
    $token = trim($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!$token || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        debug_log('CSRF verify failed | token_present=' . ($token ? 'yes' : 'no'));
        json_out(['error' => 'Invalid CSRF token. Please refresh the page and try again.'], 403);
    }
}

// Fix #8: Whitelist of valid course slugs
define('VALID_COURSE_SLUGS', [
    'mechanic', 'baking', 'etiquette', 'coding', 'digital-skills',
    'english', 'entrepreneurship', 'finance', 'health', 'sewing', 'soft-skills',
]);

// Fix #9: Rate limit file directory — one level above public_html (not web-accessible)
function rl_dir(): string {
    static $dir = null;
    if ($dir === null) {
        $candidate = dirname(__DIR__, 2) . '/eaw_storage/';
        if (!is_dir($candidate)) @mkdir($candidate, 0700, true);
        $dir = is_dir($candidate) ? $candidate : sys_get_temp_dir() . '/';
    }
    return $dir;
}

function current_user() {
    start_session();
    if (empty($_SESSION['user_id'])) return null;
    $pdo  = getDB();
    $stmt = $pdo->prepare('SELECT id, first_name, last_name, email, phone, role, is_verified FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch() ?: null;
    if ($user) {
        // If role changed since session was created, regenerate session to prevent fixation
        if (isset($_SESSION['user_role']) && $_SESSION['user_role'] !== $user['role']) {
            session_regenerate_id(true);
        }
        $_SESSION['user_role'] = $user['role'];
    }
    return $user;
}

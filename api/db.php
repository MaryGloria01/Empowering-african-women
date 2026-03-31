<?php
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

function cors() {
    $allowed = ['https://empoweringafricanwomen.com', 'https://www.empoweringafricanwomen.com'];
    $origin  = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (in_array($origin, $allowed)) {
        header("Access-Control-Allow-Origin: $origin");
        header('Access-Control-Allow-Credentials: true');
    }
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
}

function start_session() {
    if (session_status() === PHP_SESSION_NONE) {
        // Never put session ID in URLs — prevents URI-too-large (414) on slow connections
        ini_set('session.use_trans_sid',   '0');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_secure',    '1');
        ini_set('session.cookie_httponly',  '1');
        ini_set('session.cookie_samesite',  'Strict');
        session_set_cookie_params([
            'lifetime' => 86400 * 30,
            'path'     => '/',
            'secure'   => true,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }
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

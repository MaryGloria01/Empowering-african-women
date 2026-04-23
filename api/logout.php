<?php
require_once __DIR__ . '/db.php';
cors();

start_session();
debug_log("LOGOUT | session_id=" . session_id() . " | uid_was=" . ($_SESSION['user_id'] ?? 'NONE'));

// Wipe all session variables
$_SESSION = [];

// Expire the session cookie immediately in the browser
if (ini_get('session.use_cookies')) {
    $params   = session_get_cookie_params();
    $isHttps  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
             || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
             || (!empty($_SERVER['HTTP_X_FORWARDED_SSL'])   && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on');
    setcookie(
        session_name(), '', [
            'expires'  => time() - 3600,
            'path'     => $params['path'],
            'domain'   => $params['domain'],
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]
    );
}

session_destroy();

json_out(['success' => true]);

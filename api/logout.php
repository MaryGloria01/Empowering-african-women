<?php
require_once __DIR__ . '/db.php';
cors();

start_session();

// Wipe all session variables
$_SESSION = [];

// Expire the session cookie immediately in the browser
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), '', [
            'expires'  => time() - 3600,
            'path'     => $params['path'],
            'domain'   => $params['domain'],
            'secure'   => true,
            'httponly' => true,
            'samesite' => 'Strict',
        ]
    );
}

session_destroy();

json_out(['success' => true]);

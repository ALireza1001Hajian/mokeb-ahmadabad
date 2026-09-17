<?php
/**
 * خروج ادمین
 * POST /api/admin/logout.php
 */

require_once __DIR__ . '/_auth.php';
require_method('POST');

secure_session();
admin_action('logout', 'IP: ' . client_ip());

$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}
session_destroy();

json_out(['ok' => true]);
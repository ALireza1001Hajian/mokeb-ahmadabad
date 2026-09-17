<?php
/**
 * چک وضعیت لاگین ادمین (برای بارگذاری اولیه صفحه admin.php)
 * GET /api/admin/me.php
 */

require_once __DIR__ . '/_auth.php';
require_method('GET', 'POST');

secure_session();
if (empty($_SESSION['admin_logged_in'])) {
    json_out(['ok' => false, 'logged_in' => false]);
}

admin_require(); // چک انقضا

json_out([
    'ok'        => true,
    'logged_in' => true,
    'csrf'      => csrf_token(),
    'expires_in'=> ADMIN_SESSION_LIFETIME - (time() - (int) $_SESSION['admin_login_at']),
]);
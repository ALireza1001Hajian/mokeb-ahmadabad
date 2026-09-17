<?php
/**
 * ورود ادمین
 * POST /api/admin/login.php
 * Body: { password, csrf }
 */

require_once __DIR__ . '/_auth.php';
require_method('POST');

// ═══ چک لاک اوت ═══
$lock = admin_ip_locked();
if ($lock > 0) {
    json_err("تلاش‌های ناموفق زیاد. لطفاً {$lock} ثانیه صبر کنید.", 429);
}

// ═══ CSRF ═══
admin_csrf_require();

// ═══ ورودی ═══
$password = (string) input('password', '');
if ($password === '') {
    json_err('رمز عبور را وارد کنید');
}
if (strlen($password) > 200) {
    json_err('ورودی نامعتبر');
}

// ═══ بررسی رمز ═══
if (!password_verify($password, ADMIN_PASSWORD_HASH)) {
    admin_log_attempt(false);
    admin_action('login_fail', 'IP: ' . client_ip());
    error_log('[ADMIN-LOGIN-FAIL] ip=' . client_ip());
    json_err('رمز عبور اشتباه است', 401);
}

// ═══ موفق ═══
admin_log_attempt(true);

// ریست Session برای جلوگیری از Session Fixation
session_regenerate_id(true);
secure_session();
$_SESSION['admin_logged_in'] = true;
$_SESSION['admin_login_at']  = time();
$_SESSION['admin_ip']        = client_ip();

admin_action('login_success', 'IP: ' . client_ip());

json_out([
    'ok'   => true,
    'csrf' => csrf_token(), // توکن جدید برای درخواست‌های بعدی
]);
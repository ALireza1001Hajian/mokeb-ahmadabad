<?php
/**
 * ثبت‌نام شرکت‌کننده جدید
 * POST /api/register.php
 * Body: { name, page_load }
 */

require_once __DIR__ . '/_bootstrap.php';
require_method('POST');
if (quiz_is_running()) {
    json_err('مسابقه در حال اجراست. امکان ثبت‌نام جدید وجود ندارد.');
}

// ─── دریافت ورودی ───
$name      = clean((string) input('name', ''), 60);
$page_load = clean((string) input('page_load', ''), 64);

// ─── اعتبارسنجی ───
if ($name === '') {
    json_err('لطفاً نام و نام خانوادگی خود را وارد کنید');
}
if (!valid_persian_name($name)) {
    json_err('نام باید حداقل ۳ کاراکتر و فقط شامل حروف فارسی باشد');
}
if ($page_load === '' || strlen($page_load) < 8) {
    json_err('درخواست نامعتبر');
}

// ─── محدودیت نرم: حداکثر ۵ نفر فعال از یک IP ───
// (برای جلوگیری از سوءاستفاده، ولی اجازه‌ی WiFi مشترک)
$ip_count = db_count(
    "SELECT COUNT(*) FROM participants
     WHERE ip = ? AND day_date = ? AND status IN ('waiting','playing','winner')",
    [client_ip(), today()]
);
if ($ip_count >= 5) {
    json_err('از این شبکه تعداد زیادی وارد شده‌اند. لطفاً از شبکه دیگری استفاده کنید.');
}

// ─── تولید session_id یکتا ───
$attempts = 0;
do {
    $session_id = new_session_id();
    $dup = db_count("SELECT COUNT(*) FROM participants WHERE session_id = ?", [$session_id]);
    $attempts++;
} while ($dup && $attempts < 5);

if ($dup) {
    json_err('خطا در ایجاد جلسه، دوباره تلاش کنید', 500);
}

// ─── درج در دیتابیس ───
$pid = db_insert(
    "INSERT INTO participants
        (`name`, `ip`, `session_id`, `page_load`, `status`, `current_q`, `joined_at`, `day_date`)
     VALUES (?, ?, ?, ?, 'waiting', 0, NOW(), ?)",
    [$name, client_ip(), $session_id, $page_load, today()]
);

error_log("[REGISTER] pid={$pid} name={$name} ip=" . client_ip());

// ─── پاسخ ───
json_out([
    'ok'         => true,
    'session_id' => $session_id,
    'name'       => $name,
    'status'     => 'waiting',
    'message'    => 'با موفقیت ثبت‌نام شدید. منتظر شروع مسابقه باشید.',
]);
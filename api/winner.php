<?php
/**
 * دریافت کد برنده (۵ رقمی)
 * GET یا POST /api/winner.php
 * Params: session_id, page_load
 *
 * فقط کاربرانی که هر ۱۰ سوال را صحیح پاسخ داده‌اند می‌توانند کد بگیرند
 */

require_once __DIR__ . '/_bootstrap.php';
require_method('GET', 'POST');

$session_id = clean((string) input('session_id', ''), 64);
$page_load  = clean((string) input('page_load', ''), 64);

if (!valid_session_id($session_id)) json_err('جلسه نامعتبر', 401);

$p = participant_get($session_id, $page_load, true);
if (!$p) json_err('جلسه یافت نشد', 404);

if ($p['status'] !== 'winner') {
    json_err('شما برنده نیستید', 403);
}

if (empty($p['won_code'])) {
    // حالت امنیتی: اگر برنده است ولی کد ندارد، تولید کن
    try {
        $code = generate_winner_code();
        db_run(
            "UPDATE participants SET won_code = ?, won_at = NOW() WHERE id = ?",
            [$code, $p['id']]
        );
        $p['won_code'] = $code;
    } catch (Throwable $e) {
        json_err('خطا در تولید کد', 500);
    }
}

json_out([
    'ok'      => true,
    'name'    => $p['name'],
    'code'    => $p['won_code'],
    'won_at'  => $p['won_at'],
]);
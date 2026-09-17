<?php
/**
 * ریست کامل مسابقه
 * POST /api/admin/reset.php
 * Body: { confirm: "RESET" }
 *
 * پاک کردن همه‌ی شرکت‌کنندگان امروز + پاسخ‌ها + reset تنظیمات
 * سوالات حفظ می‌شوند
 */

require_once __DIR__ . '/_auth.php';
require_method('POST');
admin_require();
admin_csrf_require();

$confirm = clean((string) input('confirm', ''), 20);
if ($confirm !== 'RESET') {
    json_err('کد تأیید اشتباه است. کلمه RESET را دقیقاً وارد کنید.');
}

try {
    $pdo = db();
    $pdo->beginTransaction();

    // شمارش قبل از پاک کردن (برای لاگ)
    $before_p = db_count("SELECT COUNT(*) FROM participants WHERE day_date = ?", [today()]);
    $before_a = db_count(
        "SELECT COUNT(*) FROM answers a
         INNER JOIN participants p ON p.id = a.participant_id
         WHERE p.day_date = ?",
        [today()]
    );

    // پاک کردن پاسخ‌های امروز
    db_run(
        "DELETE a FROM answers a
         INNER JOIN participants p ON p.id = a.participant_id
         WHERE p.day_date = ?",
        [today()]
    );

    // پاک کردن شرکت‌کنندگان امروز
    db_run("DELETE FROM participants WHERE day_date = ?", [today()]);

    // ریست تنظیمات
    setting_set('quiz_running', '0');
    setting_set('current_question', '0');
    setting_set('quiz_started_at', '');
    setting_set('quiz_stopped_at', '');

    // پاک کردن آمار امروز
    db_run("DELETE FROM stats_daily WHERE date = ?", [today()]);

    // لاگ
    db_run(
        "INSERT INTO admin_logs (action, details, ip) VALUES (?, ?, ?)",
        ['quiz_reset', "participants={$before_p}, answers={$before_a}", client_ip()]
    );

    $pdo->commit();

    json_out([
        'ok'      => true,
        'message' => 'مسابقه به‌طور کامل ریست شد',
        'cleared' => [
            'participants' => $before_p,
            'answers'      => $before_a,
        ],
    ]);

} catch (Throwable $e) {
    if (db()->inTransaction()) db()->rollBack();
    error_log('[RESET-ERROR] ' . $e->getMessage());
    json_err('خطا در ریست مسابقه', 500);
}
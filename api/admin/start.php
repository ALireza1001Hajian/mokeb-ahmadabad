<?php
/**
 * شروع مسابقه — نسخه همگام با فایل استاتیک
 */
require_once __DIR__ . '/_auth.php';
require_method('POST');
admin_require();
admin_csrf_require();

if (quiz_is_running()) {
    json_err('مسابقه در حال اجراست');
}

$total = (int) setting_get('total_questions', DEFAULT_TOTAL_QUESTIONS);
$qCount = db_count(
    "SELECT COUNT(*) FROM questions WHERE day_date = ? AND order_num <= ?",
    [today(), $total]
);
if ($qCount < $total) {
    json_err("تعداد سوالات کافی نیست. {$qCount} از {$total} سوال آماده است.");
}

setting_set('quiz_running', '1');
setting_set('current_question', '1');
setting_set('quiz_started_at', date('Y-m-d H:i:s'));

db_run("UPDATE participants SET status = 'playing' WHERE status = 'waiting' AND day_date = ?", [today()]);
db_run("UPDATE participants SET q_started_at = NULL WHERE status = 'playing' AND day_date = ?", [today()]);

admin_action('quiz_start', "total_q={$total}, participants={$qCount}");

// همگام‌سازی فوری فایل متنی برای مطلع شدن ۳۰۰ کاربر هم‌زمان بدون فشار به دیتابیس
if (function_exists('sync_game_state_to_file')) {
    sync_game_state_to_file();
}

json_out([
    'ok'      => true,
    'message' => 'مسابقه با موفقیت شروع شد',
    'running' => true,
]);
if (function_exists('sync_game_state_to_file')) {
    sync_game_state_to_file();
}

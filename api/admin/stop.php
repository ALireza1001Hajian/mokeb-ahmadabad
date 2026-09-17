<?php
/**
 * توقف فوری مسابقه — نسخه همگام با فایل استاتیک
 */
require_once __DIR__ . '/_auth.php';
require_method('POST');
admin_require();
admin_csrf_require();

if (!quiz_is_running()) {
    json_err('مسابقه در حال اجرا نیست');
}

setting_set('quiz_running', '0');
setting_set('quiz_stopped_at', date('Y-m-d H:i:s'));

$affected = db()->exec(
    "UPDATE participants
     SET status = 'eliminated', eliminated_at = NOW()
     WHERE status IN ('playing','waiting') AND day_date = '" . today() . "'"
);

admin_action('quiz_stop', "affected={$affected}");

// تغییر فوری فاز در فایل متنی تا کاربران در ثانیه بعد متوقف شوند
if (function_exists('sync_game_state_to_file')) {
    sync_game_state_to_file();
}

json_out([
    'ok'      => true,
    'message' => 'مسابقه متوقف شد',
    'running' => false,
    'affected'=> (int) $affected,
]);
if (function_exists('sync_game_state_to_file')) {
    sync_game_state_to_file();
}

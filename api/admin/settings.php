<?php
/**
 * تنظیمات مسابقه
 */

require_once __DIR__ . '/_auth.php';
require_method('GET', 'POST');
admin_require();

$action = clean((string) input('action', 'get'), 10);

if ($action === 'get') {
    json_out([
        'ok' => true,
        'settings' => [
            'question_time'   => (int) setting_get('question_time', DEFAULT_QUESTION_TIME),
            'break_time'      => (int) setting_get('break_time', DEFAULT_BREAK_TIME),
            'total_questions' => (int) setting_get('total_questions', DEFAULT_TOTAL_QUESTIONS),
            'msg_waiting'     => setting_get('msg_waiting', 'منتظر تأیید اپراتور باشید'),
            'msg_winner'      => setting_get('msg_winner', 'تبریک! شما برنده شدید'),
            'msg_eliminated'  => setting_get('msg_eliminated', 'متأسفانه از مسابقه حذف شدید'),
            'estimated_start' => setting_get('estimated_start', '5:00'),
            'quiz_duration'   => (int) setting_get('quiz_duration', 0),
        ],
    ]);
}

admin_csrf_require();

if ($action === 'save') {
    $qt = (int) input('question_time', 30);
    $bt = (int) input('break_time', 5);
    $tq = (int) input('total_questions', 10);
    $mw = clean((string) input('msg_waiting', ''), 255);
    $mw_win = clean((string) input('msg_winner', ''), 255);
    $me = clean((string) input('msg_eliminated', ''), 255);
    $es = clean((string) input('estimated_start', ''), 9);
    $qd = (int) input('quiz_duration', 0);

    if ($qt < 5 || $qt > 300) json_err('زمان سوال باید بین ۵ تا ۳۰۰ ثانیه باشد');
    if ($bt < 1 || $bt > 60)  json_err('زمان وقفه باید بین ۱ تا ۶۰ ثانیه باشد');
    if ($tq < 1 || $tq > 50)  json_err('تعداد سوالات باید بین ۱ تا ۵۰ باشد');

    if ($es !== '' && !preg_match('/^(\d{1,3}:)?\d{1,3}:\d{1,2}$|^\d+$/', $es)) {
    json_err('زمان شروع نامعتبر. فرمت‌های مجاز: HH:MM:SS یا MM:SS یا ثانیه');
}

    if ($qd < 0 || $qd > 7200) json_err('مدت زمان مسابقه باید بین ۰ تا ۷۲۰۰ ثانیه باشد');

    setting_set('question_time', $qt);
    setting_set('break_time', $bt);
    setting_set('total_questions', $tq);

    if ($mw !== '')     setting_set('msg_waiting', $mw);
    if ($mw_win !== '') setting_set('msg_winner', $mw_win);
    if ($me !== '')     setting_set('msg_eliminated', $me);

    setting_set('estimated_start', $es);
    setting_set('quiz_duration', $qd);

    admin_action('settings_update', "qt={$qt} bt={$bt} tq={$tq} qd={$qd}");
    json_out(['ok' => true, 'message' => 'تنظیمات ذخیره شد']);
}

json_err('اکشن نامعتبر');
<?php
require_once __DIR__ . '/_bootstrap.php';
require_method('GET', 'POST');

$session_id = clean((string) input('session_id', ''), 64);
$page_load  = clean((string) input('page_load', ''), 64);
$order      = (int) input('order', 0);

if (!valid_session_id($session_id)) json_err('جلسه نامعتبر', 401);

$p = participant_get($session_id, $page_load, true);
if (!$p) json_err('جلسه یافت نشد', 404);
if ($p['status'] !== 'playing') json_err('شما در حال بازی نیستید', 403);
if (!quiz_is_running()) json_err('مسابقه در حال اجرا نیست', 403);

$g = quiz_global_state();
if (!$g || $g['state'] !== 'running') json_err('مسابقه فعال نیست', 403);
if ($g['phase'] !== 'question') json_err('الان فاز سوال نیست', 403);
if ($order !== (int) $g['q_num']) json_err('سوال نامعتبر', 409);

$total = (int) setting_get('total_questions', DEFAULT_TOTAL_QUESTIONS);

$q = db_one(
    "SELECT id, order_num, text, option_a, option_b, option_c, option_d
     FROM questions
     WHERE order_num = ? AND day_date = ? ORDER BY id DESC LIMIT 1",
    [$order, today()]
);
if (!$q) json_err('سوال یافت نشد');

$options = [];
foreach (['A'=>'option_a','B'=>'option_b','C'=>'option_c','D'=>'option_d'] as $k=>$f) {
    if (!empty($q[$f])) $options[$k] = $q[$f];
}

json_out([
    'ok'         => true,
    'order'      => (int) $q['order_num'],
    'total'      => $total,
    'text'       => $q['text'],
    'options'    => $options,
    'time_left'  => (int) $g['time_left'],
    'time_limit' => (int) setting_get('question_time', DEFAULT_QUESTION_TIME),
]);
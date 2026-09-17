<?php
/**
 * ثبت پاسخ کاربر — نسخه هماهنگ
 * پاسخ ثبت می‌شود ولی کاربر تا پایان تایمر کلی نمی‌رود
 */

require_once __DIR__ . '/_bootstrap.php';
require_method('POST');

$session_id = clean((string) input('session_id', ''), 64);
$page_load  = clean((string) input('page_load', ''), 64);
$order      = (int) input('order', 0);
$chosen     = strtoupper(clean((string) input('chosen', ''), 4));

if (!valid_session_id($session_id)) json_err('جلسه نامعتبر', 401);
if (!in_array($chosen, ['A','B','C','D','NONE'], true)) json_err('پاسخ نامعتبر');

$p = participant_get($session_id, $page_load, true);
if (!$p) json_err('جلسه یافت نشد', 404);
if ($p['status'] !== 'playing') json_err('شما در حال بازی نیستید', 403);
if (!quiz_is_running()) json_err('مسابقه در حال اجرا نیست', 403);

$g = quiz_global_state();
if (!$g || $g['state'] !== 'running') json_err('مسابقه فعال نیست', 400);
if ($order !== (int) $g['q_num']) json_err('این سوال دیگر فعال نیست', 409);
if ($g['phase'] !== 'question') json_err('زمان این سوال تمام شده', 409);
if ((int) $p['current_q'] >= $order) json_err('شما قبلاً پاسخ داده‌اید', 409);

$q = db_one(
    "SELECT id, order_num, correct FROM questions
     WHERE order_num = ? AND day_date = ? ORDER BY id DESC LIMIT 1",
    [$order, today()]
);
if (!$q) json_err('سوال یافت نشد');

$is_correct = ($chosen === $q['correct']);

db_insert(
    "INSERT INTO answers (participant_id, question_id, chosen, is_correct)
     VALUES (?, ?, ?, ?)",
    [$p['id'], $q['id'], $chosen, $is_correct ? 1 : 0]
);

$total = (int) setting_get('total_questions', DEFAULT_TOTAL_QUESTIONS);

if ($is_correct) {
    $is_winner = ($order >= $total);

    if ($is_winner) {
        try { $code = generate_winner_code(); }
        catch (Throwable $e) { json_err('خطا در تولید کد', 500); }

        db_run(
            "UPDATE participants
             SET current_q = ?, status = 'winner', won_code = ?, won_at = NOW()
             WHERE id = ?",
            [$order, $code, $p['id']]
        );
        update_daily_stats($order);
        admin_log('winner', "pid={$p['id']} name={$p['name']} code={$code}");

        json_out([
            'ok' => true, 'is_correct' => true, 'correct_answer' => $q['correct'],
            'is_winner' => true, 'won_code' => $code,
            'time_left' => (int) $g['time_left'],
        ]);
    }

    db_run("UPDATE participants SET current_q = ? WHERE id = ?", [$order, $p['id']]);
    update_daily_stats($order);

    json_out([
        'ok' => true, 'is_correct' => true, 'correct_answer' => $q['correct'],
        'is_winner' => false, 'time_left' => (int) $g['time_left'],
    ]);
}

db_run(
    "UPDATE participants SET status = 'eliminated', eliminated_at = NOW(), current_q = ?
     WHERE id = ?",
    [$order, $p['id']]
);

error_log("[ELIMINATED] pid={$p['id']} name={$p['name']} q={$order} chosen={$chosen}");

json_out([
    'ok' => true, 'is_correct' => false, 'correct_answer' => $q['correct'],
    'eliminated_at_q' => $order,
]);
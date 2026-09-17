<?php
/**
 * موکب احمدآباد — چک وضعیت کاربر و مسابقه (نسخه اصلاح‌شده فوق‌سبک)
 */
require_once __DIR__ . '/_bootstrap.php';
require_method('GET', 'POST');

$session_id = clean((string) input('session_id', ''), 64);
$page_load  = clean((string) input('page_load', ''), 64);

if (!valid_session_id($session_id)) json_err('جلسه نامعتبر', 401);

$stateFile = __DIR__ . '/../game_state.json';
$cached = file_exists($stateFile) ? json_decode(file_get_contents($stateFile), true) : [];

if (($cached['site_open'] ?? '0') !== '1') {
    json_out([
        'ok' => true,
        'site_open' => false,
        'phase' => 'site_closed',
    ]);
}

$p = participant_get($session_id, $page_load, true);
if (!$p) json_err('جلسه یافت نشد یا منقضی شده', 404);

if ($p['status'] === 'waiting' && ($cached['quiz_running'] ?? '0') === '1') {
    db_run("UPDATE participants SET status = 'playing' WHERE id = ? AND status = 'waiting'", [$p['id']]);
    $p['status'] = 'playing';
}

$total_q = (int)($cached['total_questions'] ?? DEFAULT_TOTAL_QUESTIONS);

$payload = [
    'ok'              => true,
    'name'            => $p['name'],
    'status'          => $p['status'],
    'current_q'       => (int) $p['current_q'],
    'quiz_running'    => ($cached['quiz_running'] ?? '0') === '1',
    'won_code'        => $p['won_code'],
    'total_q'         => $total_q,
    'question_time'   => (int)($cached['question_time'] ?? DEFAULT_QUESTION_TIME),
    'quiz_duration'   => quiz_total_seconds(),
    'players_left'    => (int)($cached['players_left'] ?? 0),
    'players_total'   => (int)($cached['players_total'] ?? 0),
    'waiting_count'   => (int)($cached['waiting_count'] ?? 0),
    'site_open'       => true,
    'time_to_start'   => time_to_start(),
    'time_to_end'     => time_to_end(),
];

if ($p['status'] === 'eliminated') {
    $payload['phase'] = 'eliminated';
    $payload['eliminated_at_q'] = (int) $p['current_q'];
    json_out($payload);
}
if ($p['status'] === 'winner') {
    $payload['phase'] = 'winner';
    json_out($payload);
}
if ($p['status'] === 'waiting') {
    $payload['phase'] = 'waiting';
    json_out($payload);
}

$g = quiz_global_state();
if (!$g || $g['state'] !== 'running') {
    $payload['phase'] = 'finished';
    json_out($payload);
}

$global_q     = (int) $g['q_num'];
$global_phase = $g['phase'];
$time_left    = (int) $g['time_left'];
$user_q       = (int) $p['current_q'];

$payload['global_q']     = $global_q;
$payload['global_phase'] = $global_phase;
$payload['time_left']    = $time_left;

if ($global_phase === 'break') {
    $payload['phase'] = 'break';
    $payload['next_q'] = $global_q + 1;
    json_out($payload);
}

if ($user_q >= $global_q) {
    $payload['phase'] = 'answered_waiting';
    json_out($payload);
}

$payload['phase'] = 'question';
$payload['q_num'] = $global_q;
json_out($payload);

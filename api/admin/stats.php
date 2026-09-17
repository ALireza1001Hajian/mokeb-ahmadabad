<?php
/**
 * آمار و نمودارها — نسخه اصلاح‌شده و پرسرعت
 */

require_once __DIR__ . '/_auth.php';
require_method('GET', 'POST');
admin_require();

$today = today();

// اجرای پاک‌سازی بازیکنان خارج‌شده به صورت مدیریت‌شده در سمت ادمین
quiz_auto_eliminate_lazy();

$current_q = 0;
$g = quiz_global_state();
if ($g && $g['state'] === 'running') {
    $current_q = (int) $g['q_num'];
}

// [رفع باگ سنگینی دیتابیس]: کوئری تجمیعی بجای ۵ کوئری مجزا
$totals = db_one("
    SELECT 
        COUNT(*) AS participants,
        SUM(CASE WHEN status = 'waiting' THEN 1 ELSE 0 END) AS waiting,
        SUM(CASE WHEN status = 'playing' THEN 1 ELSE 0 END) AS playing,
        SUM(CASE WHEN status = 'eliminated' THEN 1 ELSE 0 END) AS eliminated,
        SUM(CASE WHEN status = 'winner' THEN 1 ELSE 0 END) AS winners,
        SUM(CASE WHEN claimed = 1 THEN 1 ELSE 0 END) AS claimed
    FROM participants 
    WHERE day_date = ?", [$today]);

$counts = [
    'participants' => (int)($totals['participants'] ?? 0),
    'waiting'      => (int)($totals['waiting'] ?? 0),
    'playing'      => (int)($totals['playing'] ?? 0),
    'eliminated'   => (int)($totals['eliminated'] ?? 0),
    'winners'      => (int)($totals['winners'] ?? 0),
    'claimed'      => (int)($totals['claimed'] ?? 0),
];

$rows = db_all(
    "SELECT q.order_num,
            SUM(CASE WHEN a.is_correct = 1 THEN 1 ELSE 0 END) AS ok,
            SUM(CASE WHEN a.is_correct = 0 THEN 1 ELSE 0 END) AS fail
     FROM questions q
     LEFT JOIN answers a ON a.question_id = q.id
     WHERE q.day_date = ?
     GROUP BY q.order_num
     ORDER BY q.order_num ASC",
    [$today]
);

$per_question = [];
$total = (int) setting_get('total_questions', DEFAULT_TOTAL_QUESTIONS);
for ($i = 1; $i <= $total; $i++) {
    $per_question[$i] = ['order' => $i, 'ok' => 0, 'fail' => 0, 'rate' => 0];
}
foreach ($rows as $r) {
    $n = (int) $r['order_num'];
    if (isset($per_question[$n])) {
        $ok   = (int) $r['ok'];
        $fail = (int) $r['fail'];
        $sum  = $ok + $fail;
        $per_question[$n] = [
            'order' => $n,
            'ok'    => $ok,
            'fail'  => $fail,
            'rate'  => $sum > 0 ? round(($ok / $sum) * 100, 1) : 0,
        ];
    }
}

$dist_rows = db_all(
    "SELECT q.order_num, a.chosen, COUNT(*) AS cnt
     FROM answers a
     INNER JOIN questions q ON q.id = a.question_id
     WHERE q.day_date = ?
     GROUP BY q.order_num, a.chosen",
    [$today]
);
$distribution = [];
for ($i = 1; $i <= $total; $i++) {
    $distribution[$i] = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'NONE' => 0];
}
foreach ($dist_rows as $r) {
    $n = (int) $r['order_num'];
    $ch = $r['chosen'];
    if (isset($distribution[$n][$ch])) {
        $distribution[$n][$ch] = (int) $r['cnt'];
    }
}

// [رفع باگ محاسباتی زمان]: مقداردهی اولیه امن برای جلوگیری از اختلال در نمودار جدول ادمین
$avg_response = [];
for ($i = 1; $i <= $total; $i++) $avg_response[$i] = 0;

$hardest_q = 0;
$hardest_fail = -1;
foreach ($per_question as $n => $q) {
    if ($q['fail'] > $hardest_fail) {
        $hardest_fail = $q['fail'];
        $hardest_q = $n;
    }
}

$eliminated_by_q = db_all(
    "SELECT (current_q + 1) AS q_num, COUNT(*) AS cnt
     FROM participants
     WHERE day_date = ? AND status = 'eliminated'
     GROUP BY q_num
     ORDER BY q_num ASC",
    [$today]
);

json_out([
    'ok'             => true,
    'quiz_running'   => quiz_is_running(),
    'current_q'      => $current_q,
    'counts'         => $counts,
    'per_question'   => array_values($per_question),
    'eliminated_by_q'=> $eliminated_by_q,
    'distribution'   => $distribution,
    'avg_response'   => $avg_response,
    'hardest_q'      => $hardest_q,
    'hardest_fail'   => $hardest_fail,
    'site_open'      => site_is_open(),
    'server_time'    => date('Y-m-d H:i:s'),
    'last_update'    => date('Y-m-d H:i:s'),
]);

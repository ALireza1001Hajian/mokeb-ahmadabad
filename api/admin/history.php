<?php
/**
 * تاریخچه مسابقات — آمار روزهای گذشته
 * POST /api/admin/history.php
 * Body: { action: list|details, date? }
 */

require_once __DIR__ . '/_auth.php';
require_method('GET', 'POST');
admin_require();

$action = clean((string) input('action', 'list'), 20);

// ═══════════════════════════════════════════
// LIST — لیست روزها با آمار خلاصه
// ═══════════════════════════════════════════
if ($action === 'list') {
    $rows = db_all(
        "SELECT
            day_date,
            COUNT(*) AS participants,
            SUM(CASE WHEN status = 'winner' THEN 1 ELSE 0 END) AS winners,
            SUM(CASE WHEN status = 'eliminated' THEN 1 ELSE 0 END) AS eliminated,
            MAX(current_q) AS max_q,
            MIN(joined_at) AS first_join,
            MAX(joined_at) AS last_join
         FROM participants
         GROUP BY day_date
         ORDER BY day_date DESC
         LIMIT 60"
    );

    $history = [];
    foreach ($rows as $r) {
        // مدت زمان مسابقه
        $duration = 0;
        if ($r['first_join'] && $r['last_join']) {
            $duration = strtotime($r['last_join']) - strtotime($r['first_join']);
        }

        $history[] = [
            'date'         => $r['day_date'],
            'participants' => (int) $r['participants'],
            'winners'      => (int) $r['winners'],
            'eliminated'   => (int) $r['eliminated'],
            'max_q'        => (int) $r['max_q'],
            'duration'     => $duration,
        ];
    }

    json_out(['ok' => true, 'history' => $history]);
}

// ═══════════════════════════════════════════
// DETAILS — جزئیات یک روز
// ═══════════════════════════════════════════
if ($action === 'details') {
    $date = clean((string) input('date', ''), 10);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        json_err('تاریخ نامعتبر');
    }

    // خلاصه
    $summary = db_one(
        "SELECT
            COUNT(*) AS participants,
            SUM(CASE WHEN status = 'winner' THEN 1 ELSE 0 END) AS winners,
            SUM(CASE WHEN status = 'eliminated' THEN 1 ELSE 0 END) AS eliminated
         FROM participants
         WHERE day_date = ?",
        [$date]
    );

    // آمار هر سوال
    $qs = db_all(
        "SELECT q.order_num,
                SUM(CASE WHEN a.is_correct = 1 THEN 1 ELSE 0 END) AS ok,
                SUM(CASE WHEN a.is_correct = 0 THEN 1 ELSE 0 END) AS fail
         FROM questions q
         LEFT JOIN answers a ON a.question_id = q.id
         WHERE q.day_date = ?
         GROUP BY q.order_num
         ORDER BY q.order_num ASC",
        [$date]
    );

    $questions = [];
    foreach ($qs as $r) {
        $ok   = (int) $r['ok'];
        $fail = (int) $r['fail'];
        $tot  = $ok + $fail;
        $questions[] = [
            'order' => (int) $r['order_num'],
            'ok'    => $ok,
            'fail'  => $fail,
            'rate'  => $tot > 0 ? round(($ok / $tot) * 100, 1) : 0,
        ];
    }

    // برندگان
    $winners = db_all(
        "SELECT name, won_code, won_at
         FROM participants
         WHERE day_date = ? AND status = 'winner'
         ORDER BY won_at ASC",
        [$date]
    );

    json_out([
        'ok'   => true,
        'date' => $date,
        'summary' => [
            'participants' => (int) $summary['participants'],
            'winners'      => (int) $summary['winners'],
            'eliminated'   => (int) $summary['eliminated'],
        ],
        'questions' => $questions,
        'winners'   => $winners,
    ]);
}

json_err('اکشن نامعتبر');
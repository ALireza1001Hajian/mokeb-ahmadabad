<?php
/**
 * حذف آمار بر اساس تاریخ
 * POST /api/admin/clear_stats.php
 * Body: { date, scope }  scope: full|stats_only
 */

require_once __DIR__ . '/_auth.php';
require_method('POST');
admin_require();
admin_csrf_require();

$date  = clean((string) input('date', ''), 10);
$scope = clean((string) input('scope', 'full'), 15);

// اعتبارسنجی تاریخ
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    json_err('تاریخ نامعتبر');
}
if (strtotime($date) === false) json_err('تاریخ نامعتبر');
if ($date > today()) json_err('نمی‌توانید برای آینده آماری حذف کنید');

// فقط ۷ روز اخیر قابل حذف (برای امنیت)
$daysAgo = (time() - strtotime($date)) / 86400;
if ($daysAgo > 30) json_err('فقط آمار ۳۰ روز اخیر قابل حذف است');

try {
    db()->beginTransaction();

    $countP = db_count("SELECT COUNT(*) FROM participants WHERE day_date = ?", [$date]);
    $countA = db_count(
        "SELECT COUNT(*) FROM answers a
         INNER JOIN participants p ON p.id = a.participant_id
         WHERE p.day_date = ?",
        [$date]
    );

    // همیشه: پاسخ‌ها + شرکت‌کنندگان + آمار روزانه
    db_run(
        "DELETE a FROM answers a
         INNER JOIN participants p ON p.id = a.participant_id
         WHERE p.day_date = ?",
        [$date]
    );
    db_run("DELETE FROM participants WHERE day_date = ?", [$date]);
    db_run("DELETE FROM stats_daily WHERE date = ?", [$date]);

    // اگر scope=stats_only پس سوالات حفظ می‌شوند
    // اگر scope=full → سوالات آن روز هم حذف شوند
    $countQ = 0;
    if ($scope === 'full') {
        $countQ = db_count("SELECT COUNT(*) FROM questions WHERE day_date = ?", [$date]);
        // فقط اگر امروز نباشد سوالات را حذف کن (تا سوالات امروز حفظ شوند)
        if ($date !== today()) {
            db_run("DELETE FROM questions WHERE day_date = ?", [$date]);
        } else {
            $countQ = 0; // امروز سوالات حذف نمی‌شوند
        }
    }

    db_run(
        "INSERT INTO admin_logs (action, details, ip) VALUES (?, ?, ?)",
        [
            'clear_stats',
            "date={$date} scope={$scope} p={$countP} a={$countA} q={$countQ}",
            client_ip()
        ]
    );

    db()->commit();

    json_out([
        'ok'      => true,
        'message' => "آمار تاریخ {$date} حذف شد",
        'cleared' => [
            'participants' => $countP,
            'answers'      => $countA,
            'questions'    => $countQ,
        ],
    ]);

} catch (Throwable $e) {
    if (db()->inTransaction()) db()->rollBack();
    error_log('[CLEAR-STATS] ' . $e->getMessage());
    json_err('خطا در حذف آمار', 500);
}
<?php
/**
 * لیست برندگان و خروجی اکسل — نسخه رفع باگ شده
 */

require_once __DIR__ . '/_auth.php';
require_method('GET', 'POST');
admin_require();

$action = clean((string) input('action', 'list'), 20);
$today  = today();

if ($action === 'list') {
    $search = clean((string) input('search', ''), 60);
    $params = [$today];
    $where  = "day_date = ? AND status = 'winner'";
    if ($search !== '') {
        $where .= " AND (name LIKE ? OR won_code LIKE ?)";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }
    $rows = db_all("SELECT id, name, won_code, won_at, claimed, claimed_at FROM participants WHERE {$where} ORDER BY won_at DESC", $params);
    json_out(['ok' => true, 'winners' => $rows]);
}

admin_csrf_require();

if ($action === 'claim' || $action === 'unclaim') {
    $id = (int) input('id', 0);
    if ($id <= 0) json_err('شناسه نامعتبر');

    $p = db_one("SELECT id, name, status, won_code FROM participants WHERE id = ? LIMIT 1", [$id]);
    if (!$p) json_err('شرکت‌کننده یافت نشد');
    if ($p['status'] !== 'winner') json_err('این کاربر برنده نیست');

    if ($action === 'claim') {
        db_run("UPDATE participants SET claimed = 1, claimed_at = NOW() WHERE id = ?", [$id]);
        admin_action('winner_claim', "id={$id} code={$p['won_code']}");
        json_out(['ok' => true, 'message' => 'جایزه علامت‌گذاری شد']);
    } else {
        db_run("UPDATE participants SET claimed = 0, claimed_at = NULL WHERE id = ?", [$id]);
        admin_action('winner_unclaim', "id={$id}");
        json_out(['ok' => true, 'message' => 'علامت برداشته شد']);
    }
}

if ($action === 'export_csv') {
    $rows = db_all("SELECT name, won_code, won_at, CASE WHEN claimed = 1 THEN 'دریافت شد' ELSE 'دریافت نشده' END AS claim_status FROM participants WHERE day_date = ? AND status = 'winner' ORDER BY won_at DESC", [$today]);

    $csv = "\xEF\xBB\xBF";
    $csv .= "نام,کد 5 رقمی,زمان برنده شدن,وضعیت دریافت\n";
    foreach ($rows as $r) {
        // [رفع باگ ویرگول]: جایگزین کردن ویرگول‌های احتمالی نام کاربر با خط تیره جهت جلوگیری از شکستن ستون‌های اکسل
        $safe_name = str_replace(',', ' - ', $r['name']);
        $csv .= sprintf(
            "\"%s\",\"%s\",\"%s\",\"%s\"\n",
            str_replace('"', '""', $safe_name),
            $r['won_code'],
            $r['won_at'],
            $r['claim_status']
        );
    }

    if (!headers_sent()) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="winners_' . $today . '.csv"');
        header('Content-Length: ' . strlen($csv));
    }
    echo $csv;
    exit;
}

json_err('اکشن نامعتبر');

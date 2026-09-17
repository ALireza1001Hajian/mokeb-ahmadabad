<?php
/**
 * لیست شرکت‌کنندگان + فیلتر + جستجو + حذف
 * GET یا POST /api/admin/participants.php
 * Body: { action: list|delete, ... }
 */

require_once __DIR__ . '/_auth.php';
require_method('GET', 'POST');
admin_require();

$action = clean((string) input('action', 'list'), 20);
$today  = today();

if ($action === 'list') {
    $status = clean((string) input('status', ''), 20);
    $search = clean((string) input('search', ''), 60);

    $where  = "day_date = ?";
    $params = [$today];

    $allowed_status = ['waiting', 'playing', 'eliminated', 'winner'];
    if (in_array($status, $allowed_status, true)) {
        $where .= " AND status = ?";
        $params[] = $status;
    }
    if ($search !== '') {
        $where .= " AND name LIKE ?";
        $params[] = "%{$search}%";
    }

    $rows = db_all(
        "SELECT id, name, ip, status, current_q, won_code, claimed,
                joined_at, eliminated_at
         FROM participants
         WHERE {$where}
         ORDER BY id ASC",
        $params
    );

    // نرمال‌سازی خروجی
    foreach ($rows as &$r) {
        $r['current_q']   = (int) $r['current_q'];
        $r['claimed']     = (int) $r['claimed'];
        $r['status_fa']   = [
            'waiting'    => 'در انتظار',
            'playing'    => 'در حال بازی',
            'eliminated' => 'حذف شده',
            'winner'     => 'برنده',
        ][$r['status']] ?? $r['status'];
    }
    unset($r);

    json_out(['ok' => true, 'participants' => $rows]);
}

// CSRF برای حذف
admin_csrf_require();

if ($action === 'delete') {
    $id = (int) input('id', 0);
    if ($id <= 0) json_err('شناسه نامعتبر');

    $p = db_one("SELECT name, status FROM participants WHERE id = ? LIMIT 1", [$id]);
    if (!$p) json_err('شرکت‌کننده یافت نشد');

    // پاک کردن پاسخ‌های وابسته
    db_run("DELETE FROM answers WHERE participant_id = ?", [$id]);
    db_run("DELETE FROM participants WHERE id = ?", [$id]);

    admin_action('participant_delete', "id={$id} name={$p['name']}");
    json_out(['ok' => true, 'message' => 'شرکت‌کننده حذف شد']);
}

json_err('اکشن نامعتبر');
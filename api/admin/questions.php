<?php
/**
 * مدیریت سوالات
 * POST /api/admin/questions.php
 * Body: { action: list|save|delete|reorder, ... }
 */

require_once __DIR__ . '/_auth.php';
require_method('POST', 'GET');
admin_require();

$action = clean((string) input('action', 'list'), 20);

// ═══════════════════════════════════════════
// LIST — لیست سوالات امروز
// ═══════════════════════════════════════════
if ($action === 'list') {
    $questions = db_all(
        "SELECT id, order_num, text, option_a, option_b, option_c, option_d, correct
         FROM questions
         WHERE day_date = ?
         ORDER BY order_num ASC",
        [today()]
    );
    json_out(['ok' => true, 'questions' => $questions]);
}

// ═══════════════════════════════════════════
// بقیه اکشن‌ها نیاز به CSRF دارند
// ═══════════════════════════════════════════
admin_csrf_require();

// ═══════════════════════════════════════════
// BULK_IMPORT — افزودن گروهی
// ═══════════════════════════════════════════
if ($action === 'bulk_import') {
    $raw = (string) input('text', '');
    if (trim($raw) === '') json_err('متن سوالات خالی است');

    $lines = preg_split('/\r?\n/', $raw);
    $imported = 0;
    $errors = [];

    // شماره سوال شروع
    $start_order = (int) input('start_order', 0);
    if ($start_order <= 0) {
        $max = (int) db_count("SELECT COALESCE(MAX(order_num), 0) FROM questions WHERE day_date = ?", [today()]);
        $start_order = $max + 1;
    }

    foreach ($lines as $idx => $line) {
        $line = trim($line);
        if ($line === '' || mb_substr($line, 0, 1) === '#') continue;

        $parts = array_map('trim', explode('|', $line));
        if (count($parts) < 6) {
            $errors[] = 'خط ' . ($idx + 1) . ': باید ۶ فیلد با | جدا شود';
            continue;
        }

        [$text, $optA, $optB, $optC, $optD, $correct] = $parts;

        // اعتبارسنجی
        if (mb_strlen($text, 'UTF-8') < 3) {
            $errors[] = 'خط ' . ($idx + 1) . ': متن سوال کوتاه است';
            continue;
        }
        if ($optA === '' || $optB === '') {
            $errors[] = 'خط ' . ($idx + 1) . ': گزینه الف و ب الزامی است';
            continue;
        }

        $optC = ($optC === '-' || $optC === '—') ? '' : $optC;
        $optD = ($optD === '-' || $optD === '—') ? '' : $optD;

        $correct = strtoupper(trim($correct));
        if (!in_array($correct, ['A', 'B', 'C', 'D'], true)) {
            $errors[] = 'خط ' . ($idx + 1) . ': پاسخ صحیح نامعتبر (A/B/C/D)';
            continue;
        }
        if ($correct === 'C' && $optC === '') {
            $errors[] = 'خط ' . ($idx + 1) . ': گزینه ج خالی است';
            continue;
        }
        if ($correct === 'D' && $optD === '') {
            $errors[] = 'خط ' . ($idx + 1) . ': گزینه د خالی است';
            continue;
        }

        try {
            db_insert(
                "INSERT INTO questions
                    (order_num, text, option_a, option_b, option_c, option_d, correct, day_date)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $start_order + $imported,
                    $text, $optA, $optB,
                    $optC ?: null, $optD ?: null,
                    $correct, today()
                ]
            );
            $imported++;
        } catch (Throwable $e) {
            $errors[] = 'خط ' . ($idx + 1) . ': خطا در درج';
        }
    }

    admin_action('question_bulk_import', "imported={$imported} errors=" . count($errors));

    json_out([
        'ok'       => true,
        'imported' => $imported,
        'errors'   => $errors,
        'message'  => "{$imported} سوال اضافه شد",
    ]);
}

// ═══════════════════════════════════════════
// SAVE — درج یا بروزرسانی
// ═══════════════════════════════════════════
if ($action === 'save') {
    $id       = (int) input('id', 0);
    $order    = (int) input('order_num', 0);
    $text     = clean((string) input('text', ''), 500);
    $opt_a    = clean((string) input('option_a', ''), 200);
    $opt_b    = clean((string) input('option_b', ''), 200);
    $opt_c    = clean((string) input('option_c', ''), 200);
    $opt_d    = clean((string) input('option_d', ''), 200);
    $correct  = strtoupper(clean((string) input('correct', ''), 1));

    // ─── اعتبارسنجی ───
    if ($order < 1 || $order > 50) json_err('شماره سوال نامعتبر');
    if ($text === '' || mb_strlen($text, 'UTF-8') < 3) json_err('متن سوال خیلی کوتاه است');
    if ($opt_a === '' || $opt_b === '') json_err('حداقل دو گزینه A و B الزامی است');
    if (!in_array($correct, ['A', 'B', 'C', 'D'], true)) json_err('گزینه صحیح را انتخاب کنید');

    // گزینه صحیح باید پر باشد
    $chosen_field = 'option_' . strtolower($correct);
    if ($$chosen_field === '') json_err('گزینه صحیح انتخاب‌شده خالی است');

    // ─── جلوگیری از تکرار order_num ───
    $exists = db_one(
        "SELECT id FROM questions WHERE day_date = ? AND order_num = ? AND id != ?",
        [today(), $order, $id]
    );
    if ($exists) json_err('سوالی با این شماره قبلاً وجود دارد');

    if ($id > 0) {
        // ─── بروزرسانی ───
        db_run(
            "UPDATE questions
             SET order_num = ?, text = ?, option_a = ?, option_b = ?, option_c = ?, option_d = ?, correct = ?
             WHERE id = ? AND day_date = ?",
            [$order, $text, $opt_a, $opt_b, $opt_c ?: null, $opt_d ?: null, $correct, $id, today()]
        );
        admin_action('question_update', "id={$id}");
        json_out(['ok' => true, 'id' => $id, 'message' => 'سوال بروزرسانی شد']);
    } else {
        // ─── درج ───
        $newId = db_insert(
            "INSERT INTO questions
                (order_num, text, option_a, option_b, option_c, option_d, correct, day_date)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [$order, $text, $opt_a, $opt_b, $opt_c ?: null, $opt_d ?: null, $correct, today()]
        );
        admin_action('question_create', "id={$newId}");
        json_out(['ok' => true, 'id' => $newId, 'message' => 'سوال افزوده شد']);
    }
}

// ═══════════════════════════════════════════
// DELETE
// ═══════════════════════════════════════════
if ($action === 'delete') {
    $id = (int) input('id', 0);
    if ($id <= 0) json_err('شناسه سوال نامعتبر');

    // چک: اگر مسابقه در حال اجراست، اجازه حذف نده
    if (quiz_is_running()) {
        json_err('در حین اجرای مسابقه امکان حذف سوال نیست');
    }

    db_run("DELETE FROM questions WHERE id = ? AND day_date = ?", [$id, today()]);
    admin_action('question_delete', "id={$id}");
    json_out(['ok' => true, 'message' => 'سوال حذف شد']);
}

// ═══════════════════════════════════════════
// REORDER — جابجایی ترتیب
// ═══════════════════════════════════════════
if ($action === 'reorder') {
    $orders = input('orders', []);
    if (!is_array($orders) || empty($orders)) json_err('داده ترتیب نامعتبر');

    try {
        db()->beginTransaction();
        foreach ($orders as $item) {
            $id  = (int) ($item['id'] ?? 0);
            $num = (int) ($item['order'] ?? 0);
            if ($id > 0 && $num > 0 && $num <= 50) {
                // موقتاً به -id ببر تا unique تعارض نکنه
                db_run("UPDATE questions SET order_num = ? WHERE id = ? AND day_date = ?", [-$id, $id, today()]);
            }
        }
        foreach ($orders as $item) {
            $id  = (int) ($item['id'] ?? 0);
            $num = (int) ($item['order'] ?? 0);
            if ($id > 0 && $num > 0 && $num <= 50) {
                db_run("UPDATE questions SET order_num = ? WHERE id = ? AND day_date = ?", [$num, $id, today()]);
            }
        }
        db()->commit();
        admin_action('question_reorder', count($orders) . ' items');
        json_out(['ok' => true, 'message' => 'ترتیب سوالات بروزرسانی شد']);
    } catch (Throwable $e) {
        if (db()->inTransaction()) db()->rollBack();
        error_log('[REORDER] ' . $e->getMessage());
        json_err('خطا در تغییر ترتیب', 500);
    }
}

json_err('اکشن نامعتبر');
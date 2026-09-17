<?php
/**
 * موکب احمدآباد — Bootstrap API
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
}

(function () {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($origin) {
        $oh = parse_url($origin, PHP_URL_HOST);
        if ($oh && $oh !== $host) json_err('منبع نامعتبر', 403);
    } elseif ($referer) {
        $rh = parse_url($referer, PHP_URL_HOST);
        if ($rh && $rh !== $host) json_err('منبع نامعتبر', 403);
    }
})();

function require_method(string ...$methods): void
{
    $m = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if (!in_array($m, array_map('strtoupper', $methods), true)) {
        json_err('متد نامعتبر', 405);
    }
}

function input(string $key, $default = null)
{
    static $jsonBody = null;
    if ($jsonBody === null) {
        $raw = file_get_contents('php://input');
        $jsonBody = $raw ? (json_decode($raw, true) ?: []) : [];
    }
    return $_POST[$key] ?? $_GET[$key] ?? $jsonBody[$key] ?? $default;
}

function valid_persian_name(string $name): bool
{
    $name = trim($name);
    $len  = mb_strlen($name, 'UTF-8');
    if ($len < 3 || $len > 60) return false;
    return (bool) preg_match('/^[\x{0600}-\x{06FF}\x{200C}\s]+$/u', $name);
}

function new_session_id(): string
{
    return bin2hex(random_bytes(16));
}

function valid_session_id(?string $sid): bool
{
    return is_string($sid) && preg_match('/^[a-f0-9]{32}$/', $sid) === 1;
}

/**
 * گرفتن participant + تشخیص هر لود جدید به‌عنوان حذف
 */
function participant_get(string $session_id, ?string $page_load = null, bool $detect_refresh = true, bool $is_reload = false): ?array
{
    if (!valid_session_id($session_id)) return null;

    $p = db_one("SELECT * FROM participants WHERE session_id = ? LIMIT 1", [$session_id]);
    if (!$p) return null;

    if ($detect_refresh && $page_load && in_array($p['status'], ['waiting', 'playing'], true)) {
        $prev = $p['page_load'] ?? '';

        if ($prev !== '' && $prev !== $page_load) {
            db_run(
                "UPDATE participants
                 SET status = 'eliminated', eliminated_at = NOW()
                 WHERE id = ? AND status IN ('waiting','playing')",
                [$p['id']]
            );
            error_log("[RELOAD-DETECT] pid={$p['id']} name={$p['name']} eliminated (token changed)");
            $p['status'] = 'eliminated';
            $p['eliminated_at'] = date('Y-m-d H:i:s');
        } else {
            if ($prev !== $page_load) {
                db_run("UPDATE participants SET page_load = ? WHERE id = ?", [$page_load, $p['id']]);
            }
            $p['page_load'] = $page_load;
        }
    }

    return $p;
}

function generate_winner_code(): string
{
    for ($i = 0; $i < 30; $i++) {
        $code = str_pad((string) mt_rand(10000, 99999), 5, '0', STR_PAD_LEFT);
        $exists = db_count("SELECT COUNT(*) FROM participants WHERE won_code = ?", [$code]);
        if (!$exists) return $code;
    }
    error_log('[CODE-GEN] unable to generate unique 5-digit code');
    throw new RuntimeException('تولید کد یکتا ناموفق بود');
}

function update_daily_stats(int $q_num): void
{
    if ($q_num < 1 || $q_num > 10) return;
    $col = "q{$q_num}_ok";
    db_run(
        "INSERT INTO stats_daily (`date`, `{$col}`) VALUES (?, 1)
         ON DUPLICATE KEY UPDATE `{$col}` = `{$col}` + 1",
        [today()]
    );
}

function admin_log(string $action, string $details = ''): void
{
    try {
        db_run(
            "INSERT INTO admin_logs (`action`, `details`, `ip`) VALUES (?, ?, ?)",
            [$action, $details, client_ip()]
        );
    } catch (Throwable $e) {
        error_log('[ADMIN-LOG] ' . $e->getMessage());
    }
}

function quiz_global_state(): ?array
{
    $started = setting_get('quiz_started_at', '');
    if (!$started) return null;

    $question_time = (int) setting_get('question_time', DEFAULT_QUESTION_TIME);
    $break_time    = (int) setting_get('break_time', DEFAULT_BREAK_TIME);
    $total         = (int) setting_get('total_questions', DEFAULT_TOTAL_QUESTIONS);

    $elapsed = time() - strtotime($started);
    if ($elapsed < 0) $elapsed = 0;

    $cycle   = $question_time + $break_time;
    $q_index = (int) floor($elapsed / $cycle);
    $q_num   = $q_index + 1;

    if ($q_num > $total) {
        return ['state' => 'finished', 'q_num' => $total, 'phase' => 'done', 'time_left' => 0];
    }

    $phase_offset = $elapsed % $cycle;
    if ($phase_offset < $question_time) {
        $phase = 'question';
        $time_left = $question_time - $phase_offset;
    } else {
        $phase = 'break';
        $time_left = $cycle - $phase_offset;
    }

    return [
        'state'     => 'running',
        'q_num'     => $q_num,
        'phase'     => $phase,
        'time_left' => $time_left,
    ];
}

function quiz_auto_eliminate_lazy(): void
{
    $g = quiz_global_state();
    if (!$g || $g['state'] !== 'running') return;

    $today = today();

    if ($g['phase'] === 'break') {
        db_run(
            "UPDATE participants
             SET status = 'eliminated', eliminated_at = NOW()
             WHERE day_date = ? AND status = 'playing' AND current_q < ?",
            [$today, $g['q_num']]
        );
    } elseif ($g['phase'] === 'question' && $g['q_num'] > 1) {
        db_run(
            "UPDATE participants
             SET status = 'eliminated', eliminated_at = NOW()
             WHERE day_date = ? AND status = 'playing' AND current_q < ?",
            [$today, $g['q_num'] - 1]
        );
    }
}

function count_players_remaining(): int
{
    return db_count(
        "SELECT COUNT(*) FROM participants
         WHERE day_date = ? AND status IN ('playing','winner')",
        [today()]
    );
}

function count_players_total(): int
{
    return db_count(
        "SELECT COUNT(*) FROM participants WHERE day_date = ?",
        [today()]
    );
}

function count_waiting_players(): int
{
    return db_count(
        "SELECT COUNT(*) FROM participants
         WHERE day_date = ? AND status = 'waiting'",
        [today()]
    );
}

/* ══════════════════════════════════════════
   SITE OPEN/CLOSE
   ══════════════════════════════════════════ */

function site_is_open(): bool
{
    return setting_get('site_open', '0') === '1';
}

function site_opened_at(): string
{
    return setting_get('site_opened_at', '');
}

/**
 * تبدیل estimated_start به ثانیه
 * پشتیبانی از فرمت‌ها:
 *   - HH:MM:SS  (مثال: 2:00:00 = ۲ ساعت)
 *   - MM:SS     (مثال: 5:00 = ۵ دقیقه)
 *   - SS        (مثال: 300 = ۵ دقیقه)
 */
function estimated_start_seconds(): int
{
    $es = trim((string) setting_get('estimated_start', '5:00'));

    if ($es === '') return 300;

    // فرمت HH:MM:SS
    if (preg_match('/^(\d{1,3}):(\d{1,2}):(\d{1,2})$/', $es, $m)) {
        return ((int) $m[1]) * 3600 + ((int) $m[2]) * 60 + (int) $m[3];
    }

    // فرمت MM:SS
    if (preg_match('/^(\d{1,3}):(\d{1,2})$/', $es, $m)) {
        return ((int) $m[1]) * 60 + (int) $m[2];
    }

    // فقط ثانیه
    if (is_numeric($es)) return (int) $es;

    return 300;
}

function time_to_start(): ?int
{
    if (!site_is_open()) return null;

    $opened = site_opened_at();
    if (!$opened) return null;

    if (quiz_is_running()) return null;

    $total = estimated_start_seconds();
    $elapsed = time() - strtotime($opened);

    return max(0, $total - $elapsed);
}

function quiz_total_seconds(): int
{
    $custom = (int) setting_get('quiz_duration', 0);
    if ($custom > 0) return $custom;

    $qTime = (int) setting_get('question_time', DEFAULT_QUESTION_TIME);
    $tq    = (int) setting_get('total_questions', DEFAULT_TOTAL_QUESTIONS);

    return ($tq * $qTime) + 30;
}

function time_to_end(): ?int
{
    if (!quiz_is_running()) return null;

    $started = setting_get('quiz_started_at', '');
    if (!$started) return null;

    $total = quiz_total_seconds();
    $elapsed = time() - strtotime($started);

    return max(0, $total - $elapsed);
}

set_exception_handler(function (Throwable $e) {
    error_log('[API-EXCEPTION] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    json_err('خطای سرور، لطفاً دوباره تلاش کنید', 500);
});
<?php
/**
 * موکب احمدآباد — هسته احراز هویت و امنیت ادمین
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../_bootstrap.php';

function admin_require(): void
{
    secure_session();
    if (!empty($_SESSION['admin_login_at'])) {
        $age = time() - (int) $_SESSION['admin_login_at'];
        if ($age > ADMIN_SESSION_LIFETIME) {
            $_SESSION = [];
            session_destroy();
            json_err('جلسه منقضی شده، دوباره وارد شوید', 401);
        }
    }
    if (empty($_SESSION['admin_logged_in'])) {
        json_err('دسترسی غیرمجاز', 401);
    }
    if (!empty($_SESSION['admin_ip']) && $_SESSION['admin_ip'] !== client_ip()) {
        $_SESSION = [];
        session_destroy();
        json_err('جلسه نامعتبر، دوباره وارد شوید', 401);
    }
    $_SESSION['admin_login_at'] = time();
}

function admin_csrf_require(): void
{
    $token = input('csrf', '');
    if (!csrf_check($token)) {
        json_err('توکن امنیتی نامعتبر، صفحه را رفرش کنید', 403);
    }
}

function admin_ip_locked(): int
{
    $cutoff = date('Y-m-d H:i:s', time() - ADMIN_LOCKOUT_TIME);
    $fails = db_count("SELECT COUNT(*) FROM admin_login_attempts WHERE ip = ? AND success = 0 AND created_at >= ?", [client_ip(), $cutoff]);
    if ($fails < ADMIN_MAX_ATTEMPTS) return 0;
    $last = db_one("SELECT created_at FROM admin_login_attempts WHERE ip = ? AND success = 0 ORDER BY id DESC LIMIT 1", [client_ip()]);
    if (!$last) return 0;
    $elapsed = time() - strtotime($last['created_at']);
    $remaining = ADMIN_LOCKOUT_TIME - $elapsed;
    return $remaining > 0 ? $remaining : 0;
}

function admin_log_attempt(bool $success): void
{
    db_run("INSERT INTO admin_login_attempts (ip, success) VALUES (?, ?)", [client_ip(), $success ? 1 : 0]);
}

function admin_action(string $action, string $details = ''): void
{
    try {
        db_run("INSERT INTO admin_logs (action, details, ip) VALUES (?, ?, ?)", [$action, $details, client_ip()]);
    } catch (Throwable $e) {
        error_log('[ADMIN-ACTION-LOG] ' . $e->getMessage());
    }
}

/**
 * [رفع باگ مصرف و پایداری لایو] همگام‌سازی وضعیت روی فایل متنی استاتیک
 */
function sync_game_state_to_file(): void
{
    // استفاده از کوئری تجمیعی پرسرعت به جای ۳ کوئری مجزای COUNT برای به صفر رساندن بار MySQL
    $totals = db_one("
        SELECT 
            SUM(CASE WHEN status = 'waiting' THEN 1 ELSE 0 END) AS waiting,
            SUM(CASE WHEN status IN ('playing','winner') THEN 1 ELSE 0 END) AS left_p,
            COUNT(*) AS total_p
        FROM participants 
        WHERE day_date = ?", [today()]);

    $state = [
        'quiz_running' => setting_get('quiz_running', '0'),
        'site_open' => setting_get('site_open', '0'),
        'site_opened_at' => setting_get('site_opened_at', ''),
        'quiz_started_at' => setting_get('quiz_started_at', ''),
        'question_time' => (int)setting_get('question_time', DEFAULT_QUESTION_TIME),
        'break_time' => (int)setting_get('break_time', DEFAULT_BREAK_TIME),
        'total_questions' => (int)setting_get('total_questions', DEFAULT_TOTAL_QUESTIONS),
        'players_left' => (int)($totals['left_p'] ?? 0),
        'players_total' => (int)($totals['total_p'] ?? 0),
        'waiting_count' => (int)($totals['waiting'] ?? 0)
    ];
    file_put_contents(__DIR__ . '/../../game_state.json', json_encode($state, JSON_UNESCAPED_UNICODE));
}

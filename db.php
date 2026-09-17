<?php
/**
 * موکب احمدآباد — لایه اتصال به دیتابیس
 * PDO + Prepared Statements + توابع کمکی
 */

require_once __DIR__ . '/config.php';

/**
 * گرفتن اتصال PDO (Singleton)
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        DB_HOST, DB_NAME, DB_CHARSET
    );

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        $pdo->exec("SET time_zone = '+03:30'");
    } catch (PDOException $e) {
        error_log('[DB ERROR] ' . $e->getMessage());
        http_response_code(500);
        die(json_encode(['ok' => false, 'error' => 'خطای اتصال به دیتابیس']));
    }

    return $pdo;
}

/**
 * اجرای کوئری با پارامتر
 */
function db_run(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

/**
 * گرفتن یک ردیف
 */
function db_one(string $sql, array $params = []): ?array
{
    $row = db_run($sql, $params)->fetch();
    return $row === false ? null : $row;
}

/**
 * گرفتن چند ردیف
 */
function db_all(string $sql, array $params = []): array
{
    return db_run($sql, $params)->fetchAll();
}

/**
 * درج و گرفتن ID
 */
function db_insert(string $sql, array $params = []): int
{
    db_run($sql, $params);
    return (int) db()->lastInsertId();
}

/**
 * شمارش
 */
function db_count(string $sql, array $params = []): int
{
    return (int) db_run($sql, $params)->fetchColumn();
}

/* ══════════════════════════════════════════
   توابع کمکی عمومی
   ══════════════════════════════════════════ */

/**
 * پاک‌سازی ورودی متنی
 */
function clean(?string $str, int $max = 500): string
{
    $str = trim((string) $str);
    $str = strip_tags($str);
    $str = htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if (mb_strlen($str, 'UTF-8') > $max) {
        $str = mb_substr($str, 0, $max, 'UTF-8');
    }
    return $str;
}

/**
 * خروجی JSON استاندارد
 */
function json_out($data, int $code = 200): void
{
    if (!headers_sent()) {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * خطای JSON
 */
function json_err(string $msg, int $code = 400): void
{
    json_out(['ok' => false, 'error' => $msg], $code);
}

/**
 * تاریخ امروز به شمسی نمی‌خواهیم؛ میلادی برای DB کافی است
 */
function today(): string
{
    return date('Y-m-d');
}

/**
 * IP کاربر
 */
function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * شروع Session امن
 */
function secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/**
 * تولید CSRF Token
 */
function csrf_token(): string
{
    secure_session();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

/**
 * بررسی CSRF
 */
function csrf_check(?string $token): bool
{
    secure_session();
    return !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string)$token);
}

/* ══════════════════════════════════════════
   تنظیمات قابل ویرایش (settings table)
   ══════════════════════════════════════════ */

function setting_get(string $key, $default = null)
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            foreach (db_all("SELECT `key`, `value` FROM settings") as $row) {
                $cache[$row['key']] = $row['value'];
            }
        } catch (Throwable $e) { $cache = []; }
    }
    return $cache[$key] ?? $default;
}

function setting_set(string $key, $value): void
{
    db_run(
        "INSERT INTO settings (`key`, `value`) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)",
        [$key, (string)$value]
    );
}

/**
 * وضعیت کلی مسابقه (started / stopped)
 */
function quiz_is_running(): bool
{
    return setting_get('quiz_running', '0') === '1';
}

function quiz_current_question(): int
{
    return (int) setting_get('current_question', 0);
}
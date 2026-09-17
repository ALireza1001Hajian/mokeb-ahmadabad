<?php
/**
 * موکب احمدآباد — فایل تنظیمات اصلی
 * نسخه: 1.0.0
 * سازگار با PHP 7.4+
 */

// جلوگیری از دسترسی مستقیم
if (!defined('MOKEB_APP')) {
    define('MOKEB_APP', true);
}

// ===== تنظیمات دیتابیس =====
define('DB_HOST', 'mysql-1ec6cbac-mokeb-ahmadababd.g.aivencloud.com');   // هاست دیتابیس
define('DB_NAME', 'defaultdb');         // نام دیتابیس
define('DB_USER', 'avnadmin');               // یوزر
define('DB_PASS', 'AVNS_LMuWDMI3X2lG_r2csLZ');          // پسورد
define('DB_CHARSET', 'utf8mb4');

// ===== تنظیمات ادمین =====
// رمز پیش‌فرض: mokeb1234
// برای تغییر، این دستور را اجرا کن: php -r "echo password_hash('رمز_جدید', PASSWORD_DEFAULT);"
define('ADMIN_PASSWORD_HASH', '$2y$10$vQfEBBui34y5Hvkzr6dyXuZgPXuzeS1zJ32U9GyepDhe79pIU0Jw6');
define('ADMIN_SESSION_LIFETIME', 3600);   // یک ساعت
define('ADMIN_MAX_ATTEMPTS', 3);          // حداکثر تلاش ورود
define('ADMIN_LOCKOUT_TIME', 60);         // ثانیه قفل بعد از تلاش‌های ناموفق

// ===== تنظیمات مسابقه (مقادیر پیش‌فرض - قابل ویرایش از پنل) =====
define('DEFAULT_QUESTION_TIME', 30);
define('DEFAULT_BREAK_TIME', 5);
define('DEFAULT_TOTAL_QUESTIONS', 10);

// ===== تنظیمات اپلیکیشن =====
define('APP_NAME', 'موکب احمدآباد');
define('APP_VERSION', '1.0.0');
define('APP_TIMEZONE', 'Asia/Tehran');

date_default_timezone_set(APP_TIMEZONE);

// ===== مدیریت خطا =====
define('ERROR_LOG_FILE', __DIR__ . '/error.log');
ini_set('log_errors', 1);
ini_set('error_log', ERROR_LOG_FILE);
error_reporting(E_ALL);
ini_set('display_errors', 0); // در پروداکشن خاموش

// ===== هدرهای امنیتی پایه =====
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

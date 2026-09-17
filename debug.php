<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

echo "✅ config و db لود شدن<br>";

secure_session();
$csrf = csrf_token();
echo "✅ Session و CSRF ساخته شدن<br>";

$quiz_running = quiz_is_running();
echo "quiz_running: " . ($quiz_running ? 'true' : 'false') . "<br>";

$total_q = (int) setting_get('total_questions', DEFAULT_TOTAL_QUESTIONS);
echo "total_q: {$total_q}<br>";

echo "✅ همه چیز سالمه!";
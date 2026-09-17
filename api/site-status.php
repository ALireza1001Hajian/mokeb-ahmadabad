<?php
/**
 * وضعیت سایت — برای چک کردن وضعیت باز/بسته بودن سایت
 */

require_once __DIR__ . '/_bootstrap.php';
require_method('GET', 'POST');

json_out([
    'ok'             => true,
    'site_open'      => site_is_open(),
    'time_to_start'  => time_to_start(),
    'time_to_end'    => time_to_end(),
    'quiz_running'   => quiz_is_running(),
]);
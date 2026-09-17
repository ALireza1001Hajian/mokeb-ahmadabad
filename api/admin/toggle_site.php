<?php
/**
 * باز/بسته کردن سایت — نسخه همگام با فایل استاتیک
 */
require_once __DIR__ . '/_auth.php';
require_method('POST');
admin_require();
admin_csrf_require();

$currentlyOpen = site_is_open();

if ($currentlyOpen) {
    setting_set('site_open', '0');
    setting_set('site_opened_at', '');
    admin_action('site_close', 'IP: ' . client_ip());
    
    if (function_exists('sync_game_state_to_file')) {
        sync_game_state_to_file();
    }
    
    json_out([
        'ok' => true,
        'site_open' => false,
        'message' => 'سایت بسته شد',
    ]);
} else {
    setting_set('site_open', '1');
    setting_set('site_opened_at', date('Y-m-d H:i:s'));
    admin_action('site_open', 'IP: ' . client_ip());
    
    if (function_exists('sync_game_state_to_file')) {
        sync_game_state_to_file();
    }
    
    json_out([
        'ok' => true,
        'site_open' => true,
        'message' => 'سایت باز شد',
        'estimated_seconds' => estimated_start_seconds(),
    ]);
}
if (function_exists('sync_game_state_to_file')) {
    sync_game_state_to_file();
}

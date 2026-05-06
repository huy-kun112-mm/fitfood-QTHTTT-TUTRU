<?php
/**
 * Bước 1 của OAuth: redirect user sang trang đăng nhập Google.
 * Sinh `state` ngẫu nhiên lưu vào session để chống CSRF — sẽ verify
 * lại trong google_callback.php.
 */

require_once __DIR__ . '/../vendor/autoload.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$config_file = __DIR__ . '/google_config.php';
if (!file_exists($config_file)) {
    http_response_code(500);
    exit('Thiếu file auth/google_config.php — copy từ google_config.example.php và điền credentials.');
}
$cfg = require $config_file;

$client = new Google\Client();
$client->setClientId($cfg['client_id']);
$client->setClientSecret($cfg['client_secret']);
$client->setRedirectUri($cfg['redirect_uri']);
$client->addScope('email');
$client->addScope('profile');
$client->setAccessType('online');
$client->setPrompt('select_account');

// CSRF protection
$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state'] = $state;
$client->setState($state);

header('Location: ' . $client->createAuthUrl());
exit;

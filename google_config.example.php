<?php
/**
 * TEMPLATE — copy file này thành `google_config.php` rồi điền credentials.
 * `google_config.php` đã được .gitignore, KHÔNG commit lên git.
 *
 * Lấy credentials tại: https://console.cloud.google.com/apis/credentials
 *   → Create Credentials → OAuth client ID → Web application
 *
 * Authorized redirect URI phải khớp 100% với 'redirect_uri' bên dưới.
 */

return [
    'client_id'     => 'YOUR_CLIENT_ID.apps.googleusercontent.com',
    'client_secret' => 'YOUR_CLIENT_SECRET',

    // Local dev (Docker map cổng 8080):
    'redirect_uri'  => 'http://localhost:8080/auth/google_callback.php',

    // Khi deploy production, đổi thành:
    // 'redirect_uri' => 'https://fitfood.vn/auth/google_callback.php',
];

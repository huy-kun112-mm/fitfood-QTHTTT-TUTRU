<?php
/**
 * Xử lý đăng xuất — huỷ session và quay về trang chủ
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Xoá tất cả biến session
$_SESSION = [];

// Xoá session cookie nếu có
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}

// Huỷ session
session_destroy();

// Quay về trang chủ
header('Location: index.php');
exit;

<?php
/**
 * Cấu hình database cho FitFood
 * - Khi chạy trong Docker: host = tên service MySQL (ví dụ 'db')
 * - Khi chạy local (XAMPP/MAMP): đổi DB_HOST về 'localhost'
 *
 * Dùng biến môi trường để linh hoạt giữa 2 môi trường.
 */

define('DB_HOST',    getenv('DB_HOST')    ?: 'localhost');
define('DB_NAME',    getenv('DB_NAME')    ?: 'fitfood');
define('DB_USER',    getenv('DB_USER')    ?: 'fitfood_user');
define('DB_PASS',    getenv('DB_PASS')    ?: 'fitfood_pass');
define('DB_CHARSET', 'utf8mb4');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Soft-fail: nếu kết nối thất bại thì $pdo = null + log lỗi (không die),
// để page/API có thể render UI/error message tử tế thay vì trang trắng.
$pdo      = null;
$db_error = null;
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => true,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    $db_error = $e->getMessage();
    error_log('[FitFood] DB connection failed: ' . $db_error);
}

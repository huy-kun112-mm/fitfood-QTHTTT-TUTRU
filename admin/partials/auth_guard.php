<?php
/**
 * Admin auth guard
 * Chặn truy cập admin nếu chưa đăng nhập hoặc role != admin.
 * Cách dùng: require_once __DIR__ . '/partials/auth_guard.php';
 *           ở đầu mỗi trang admin (NGAY SAU require database.php — vì
 *           database.php gọi session_start()).
 */

require_once __DIR__ . '/../../config/database.php';

// Chưa đăng nhập, hoặc đăng nhập rồi nhưng không phải admin → đẩy về trang chủ
// kèm flag mở sẵn modal login. Dùng đường dẫn tuyệt đối từ root web server
// để đúng dù admin/ nằm dưới sub-path.
if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    $reason = empty($_SESSION['user_id']) ? 'required' : 'forbidden';
    header('Location: ../index.php?login=' . $reason);
    exit;
}

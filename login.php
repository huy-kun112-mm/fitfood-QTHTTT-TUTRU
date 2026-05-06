<?php
/**
 * Xử lý đăng nhập người dùng cho FitFood
 * Nhận dữ liệu từ form (AJAX POST) → validate → kiểm tra DB → tạo session → trả về JSON
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config/database.php';

if ($pdo === null) {
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'message' => 'Dịch vụ tạm thời không khả dụng. Vui lòng thử lại sau.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Chỉ chấp nhận POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Phương thức không hợp lệ.']);
    exit;
}

// Lấy và làm sạch dữ liệu
$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

$errors = [];

// ===== Validate =====
if ($email === '') {
    $errors['email'] = 'Vui lòng nhập email.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Email không đúng định dạng.';
}

if ($password === '') {
    $errors['password'] = 'Vui lòng nhập mật khẩu.';
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

try {
    // Tìm user theo email — load thêm avatar + role để cache vào session
    $stmt = $pdo->prepare("SELECT id, full_name, email, password, avatar, role FROM users WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    // Kiểm tra user tồn tại và mật khẩu đúng
    if (!$user || !password_verify($password, $user['password'])) {
        echo json_encode([
            'success' => false,
            'errors'  => ['password' => 'Email hoặc mật khẩu không đúng.']
        ]);
        exit;
    }

    // Tạo session đăng nhập (avatar có thể null nếu user chưa upload)
    $_SESSION['user_id']     = $user['id'];
    $_SESSION['user_name']   = $user['full_name'];
    $_SESSION['user_email']  = $user['email'];
    $_SESSION['user_avatar'] = $user['avatar'];
    $_SESSION['user_role']   = $user['role'];

    // Admin → đẩy thẳng vào dashboard. User thường → reload trang client.
    $is_admin     = ($user['role'] === 'admin');
    $redirect_url = $is_admin ? 'admin/index.php' : null;

    echo json_encode([
        'success'      => true,
        'message'      => $is_admin
            ? 'Đăng nhập admin thành công! Đang chuyển tới dashboard...'
            : 'Đăng nhập thành công! Chào mừng ' . htmlspecialchars($user['full_name']) . '.',
        'redirect_url' => $redirect_url,
        'user'         => [
            'id'        => $user['id'],
            'full_name' => $user['full_name'],
            'email'     => $user['email'],
            'role'      => $user['role'],
        ]
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi hệ thống, vui lòng thử lại. (' . $e->getMessage() . ')'
    ]);
}

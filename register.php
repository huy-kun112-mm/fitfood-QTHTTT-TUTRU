<?php
/**
 * Xử lý đăng ký người dùng cho FitFood
 * Nhận AJAX POST → validate → lưu DB → trả JSON
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Phương thức không hợp lệ.']);
    exit;
}

$full_name        = trim($_POST['full_name'] ?? '');
$email            = trim($_POST['email'] ?? '');
$password         = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

$errors = [];

if ($full_name === '') {
    $errors['full_name'] = 'Vui lòng nhập họ và tên.';
} elseif (mb_strlen($full_name) < 2) {
    $errors['full_name'] = 'Họ và tên phải có ít nhất 2 ký tự.';
} elseif (mb_strlen($full_name) > 100) {
    $errors['full_name'] = 'Họ và tên không được vượt quá 100 ký tự.';
}

if ($email === '') {
    $errors['email'] = 'Vui lòng nhập email.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Email không đúng định dạng.';
}

if ($password === '') {
    $errors['password'] = 'Vui lòng nhập mật khẩu.';
} elseif (strlen($password) < 6) {
    $errors['password'] = 'Mật khẩu phải có ít nhất 6 ký tự.';
}

if ($confirm_password === '') {
    $errors['confirm_password'] = 'Vui lòng xác nhận mật khẩu.';
} elseif ($password !== $confirm_password) {
    $errors['confirm_password'] = 'Xác nhận mật khẩu không khớp.';
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $email]);
    if ($stmt->fetch()) {
        echo json_encode([
            'success' => false,
            'errors'  => ['email' => 'Email này đã được đăng ký. Vui lòng dùng email khác.']
        ]);
        exit;
    }

    $password_hash = password_hash($password, PASSWORD_BCRYPT);

    $sql = "INSERT INTO users (full_name, email, password, provider, status)
            VALUES (:full_name, :email, :password, 'local', 1)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':full_name' => $full_name,
        ':email'     => $email,
        ':password'  => $password_hash,
    ]);

    $user_id = $pdo->lastInsertId();

    $_SESSION['user_id']    = $user_id;
    $_SESSION['user_name']  = $full_name;
    $_SESSION['user_email'] = $email;

    echo json_encode([
        'success' => true,
        'message' => 'Đăng ký thành công! Chào mừng bạn đến với FitFood.',
        'user'    => [
            'id'        => $user_id,
            'full_name' => $full_name,
            'email'     => $email,
        ],
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Có lỗi xảy ra, vui lòng thử lại. (' . $e->getMessage() . ')'
    ]);
}

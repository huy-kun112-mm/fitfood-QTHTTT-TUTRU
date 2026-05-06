<?php
/**
 * Bước 2 của OAuth: Google redirect về đây với ?code=... &state=...
 *
 * Luồng:
 *   1. Verify `state` (chống CSRF)
 *   2. Đổi `code` → `access_token` (gọi Google qua thư viện)
 *   3. Dùng token gọi userinfo → lấy email/name/avatar
 *   4. Upsert vào bảng users:
 *      - Match google_id → user cũ đã liên kết
 *      - Match email → user local cũ → liên kết google_id
 *      - Không match → tạo user mới (provider='google', password=NULL)
 *   5. Tạo session (regenerate id) → redirect index.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';   // tự khởi động session + tạo $pdo

function normalize_google_avatar(?string $url): string {
    $url = trim((string)$url);
    if ($url === '') return '';
    if (strpos($url, '//') === 0) return 'https:' . $url;
    if (preg_match('#^https?://#i', $url)) return $url;
    // Một số nguồn trả host/path không có scheme.
    if (strpos($url, 'googleusercontent.com') !== false) return 'https://' . ltrim($url, '/');
    return $url;
}

$config_file = __DIR__ . '/google_config.php';
if (!file_exists($config_file)) {
    http_response_code(500);
    exit('Thiếu file auth/google_config.php.');
}
$cfg = require $config_file;

// 1. Verify state
if (
    empty($_GET['state']) ||
    empty($_SESSION['oauth_state']) ||
    !hash_equals($_SESSION['oauth_state'], $_GET['state'])
) {
    http_response_code(400);
    exit('State không hợp lệ — có thể là CSRF attempt.');
}
unset($_SESSION['oauth_state']);

if (empty($_GET['code'])) {
    http_response_code(400);
    exit('Thiếu authorization code.');
}

if ($pdo === null) {
    http_response_code(503);
    exit('Database tạm thời không khả dụng.');
}

try {
    // 2. Đổi code → access token
    $client = new Google\Client();
    $client->setClientId($cfg['client_id']);
    $client->setClientSecret($cfg['client_secret']);
    $client->setRedirectUri($cfg['redirect_uri']);

    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    if (isset($token['error'])) {
        throw new RuntimeException($token['error_description'] ?? $token['error']);
    }
    $client->setAccessToken($token);

    // 3. Lấy userinfo
    $oauth = new Google\Service\Oauth2($client);
    $info  = $oauth->userinfo->get();

    $google_id = $info->id;
    $email     = $info->email;
    $name      = $info->name ?: $email;
    $picture   = normalize_google_avatar($info->picture);

    if (!$google_id || !$email) {
        throw new RuntimeException('Google không trả về đủ thông tin (id/email).');
    }

    // 4. Upsert
    // 4a. Tìm theo google_id
    $stmt = $pdo->prepare("SELECT id, full_name, avatar FROM users WHERE google_id = :gid LIMIT 1");
    $stmt->execute([':gid' => $google_id]);
    $user = $stmt->fetch();

    if ($user) {
        // Giữ avatar đồng bộ với Google cho tài khoản đã liên kết.
        $stmt = $pdo->prepare(
            "UPDATE users
             SET provider = 'google',
                 full_name = :name,
                 avatar = :pic
             WHERE id = :id"
        );
        $stmt->execute([
            ':name' => $name,
            ':pic'  => $picture !== '' ? $picture : null,
            ':id'   => $user['id'],
        ]);
        $user['full_name'] = $name;
        $user['avatar'] = $picture !== '' ? $picture : ($user['avatar'] ?? '');
    } else {
        // 4b. Tìm theo email — nếu có user local cũ thì liên kết
        $stmt = $pdo->prepare("SELECT id, full_name, avatar FROM users WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if ($user) {
            $stmt = $pdo->prepare(
                "UPDATE users SET google_id = :gid, provider = 'google',
                                  full_name = :name,
                                  avatar = COALESCE(NULLIF(avatar,''), :pic)
                 WHERE id = :id"
            );
            $stmt->execute([
                ':gid' => $google_id,
                ':name' => $name,
                ':pic' => $picture !== '' ? $picture : null,
                ':id' => $user['id']
            ]);
            if (empty($user['avatar']) && $picture !== '') {
                $user['avatar'] = $picture;
            }
            $user['full_name'] = $name;
        } else {
            // 4c. Tạo user mới
            $stmt = $pdo->prepare(
                "INSERT INTO users (full_name, email, password, avatar, provider, google_id, status)
                 VALUES (:n, :e, NULL, :a, 'google', :gid, 1)"
            );
            $stmt->execute([
                ':n'   => $name,
                ':e'   => $email,
                ':a'   => $picture !== '' ? $picture : null,
                ':gid' => $google_id,
            ]);
            $user = [
                'id'        => $pdo->lastInsertId(),
                'full_name' => $name,
                'avatar'    => $picture,
            ];
        }
    }

    // 5. Tạo session, regenerate id để chống session fixation
    session_regenerate_id(true);
    $_SESSION['user_id']     = $user['id'];
    $_SESSION['user_name']   = $user['full_name'];
    $_SESSION['user_email']  = $email;
    $_SESSION['user_avatar'] = $user['avatar'] ?: $picture;

    header('Location: /index.php');
    exit;

} catch (Throwable $e) {
    error_log('[Google OAuth] ' . $e->getMessage());
    http_response_code(500);
    exit('Đăng nhập Google thất bại. Vui lòng thử lại sau.');
}

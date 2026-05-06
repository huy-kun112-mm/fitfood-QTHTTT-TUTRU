<?php
/**
 * POST /api/review-submit.php
 *
 * Body: product_id (int), content (string, ≤ 2000 ký tự)
 * Yêu cầu: user đã đăng nhập + có ít nhất 1 đơn `completed` chứa product này.
 * Trả về JSON: { ok: bool, error?: string }.
 */

if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/reviews.php';

header('Content-Type: application/json; charset=utf-8');

function json_fail(int $code, string $msg): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_fail(405, 'Method not allowed');
}

if (!$pdo) {
    json_fail(500, 'Hệ thống tạm thời gián đoạn, vui lòng thử lại sau.');
}

$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
if ($user_id <= 0) {
    json_fail(401, 'Vui lòng đăng nhập để bình luận.');
}

$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$content    = isset($_POST['content']) ? trim((string)$_POST['content']) : '';

if ($product_id <= 0) {
    json_fail(400, 'Sản phẩm không hợp lệ.');
}
if ($content === '') {
    json_fail(400, 'Nội dung bình luận không được để trống.');
}
if (mb_strlen($content) > 2000) {
    json_fail(400, 'Bình luận tối đa 2000 ký tự.');
}

if (!can_user_review($pdo, $user_id, $product_id)) {
    json_fail(403, 'Bạn cần mua và hoàn tất đơn hàng có sản phẩm này để bình luận.');
}

try {
    upsert_review($pdo, $user_id, $product_id, $content);
} catch (PDOException $e) {
    error_log('[review-submit] ' . $e->getMessage());
    json_fail(500, 'Không lưu được bình luận. Vui lòng thử lại.');
}

echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);

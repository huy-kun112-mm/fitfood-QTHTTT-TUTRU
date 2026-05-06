<?php
/**
 * POST /api/review-delete.php
 *
 * Body: review_id (int)
 * Quyền: user xóa được review của mình; admin (`role = 'admin'`) xóa được mọi review.
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
    json_fail(500, 'Hệ thống tạm thời gián đoạn.');
}

$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
if ($user_id <= 0) {
    json_fail(401, 'Vui lòng đăng nhập.');
}

$review_id = isset($_POST['review_id']) ? (int)$_POST['review_id'] : 0;
if ($review_id <= 0) {
    json_fail(400, 'Bình luận không hợp lệ.');
}

$is_admin = (($_SESSION['user_role'] ?? '') === 'admin');

try {
    // Admin: xóa không điều kiện. User thường: chỉ xóa review của chính mình.
    $deleted = delete_review($pdo, $review_id, $is_admin ? null : $user_id);
} catch (PDOException $e) {
    error_log('[review-delete] ' . $e->getMessage());
    json_fail(500, 'Không xóa được bình luận.');
}

if (!$deleted) {
    json_fail(403, 'Không có quyền xóa bình luận này.');
}

echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);

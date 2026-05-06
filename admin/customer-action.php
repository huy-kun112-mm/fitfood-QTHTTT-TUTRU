<?php
/**
 * FitFood — Admin / Customer AJAX endpoint
 * GET ?action=detail&id=...  → JSON chi tiết khách hàng (info + stats + addresses + recent orders)
 *
 * Read-only. Auth check inline (không dùng auth_guard.php vì nó redirect 302
 * không phù hợp cho AJAX).
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';

if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Bạn cần đăng nhập admin để thực hiện thao tác này.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($pdo === null) {
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'message' => 'Dịch vụ tạm thời không khả dụng. Kiểm tra DB.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$action = $_GET['action'] ?? '';

try {
    if ($action === 'detail') {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID khách hàng không hợp lệ.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmt = $pdo->prepare("
            SELECT id, full_name, email, phone, avatar, provider, status, created_at
            FROM users
            WHERE id = :id AND role <> 'admin'
        ");
        $stmt->execute([':id' => $id]);
        $user = $stmt->fetch();
        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy khách hàng.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmt = $pdo->prepare("
            SELECT
                COUNT(*) AS total_orders,
                COALESCE(SUM(CASE WHEN status = 'completed' THEN total_amount ELSE 0 END), 0) AS total_spent
            FROM orders
            WHERE user_id = :id
        ");
        $stmt->execute([':id' => $id]);
        $stats = $stmt->fetch() ?: ['total_orders' => 0, 'total_spent' => 0];

        $stmt = $pdo->prepare("
            SELECT id, total_amount, status, created_at
            FROM orders
            WHERE user_id = :id
            ORDER BY created_at DESC, id DESC
            LIMIT 5
        ");
        $stmt->execute([':id' => $id]);
        $recent_orders = $stmt->fetchAll();

        $stmt = $pdo->prepare("
            SELECT id, recipient_name, phone, address, is_default
            FROM user_addresses
            WHERE user_id = :id
            ORDER BY is_default DESC, id ASC
        ");
        $stmt->execute([':id' => $id]);
        $addresses = $stmt->fetchAll();

        echo json_encode([
            'success' => true,
            'data' => [
                'user'          => $user,
                'stats'         => $stats,
                'recent_orders' => $recent_orders,
                'addresses'     => $addresses,
            ],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Hành động không hỗ trợ.'], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    error_log('[admin/customer-action] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Lỗi truy vấn DB.'], JSON_UNESCAPED_UNICODE);
}

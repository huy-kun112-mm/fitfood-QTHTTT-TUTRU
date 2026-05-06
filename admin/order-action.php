<?php
/**
 * FitFood — Admin / Order AJAX action endpoint
 * Endpoint chung cho 2 thao tác từ orders.php:
 *   GET  ?action=detail&id=...           → trả JSON full chi tiết đơn (header + items)
 *   POST action=update_status            → cập nhật status; nếu status='cancelled' đồng nghĩa "huỷ đơn"
 *
 * Tất cả response là JSON, có guard auth_guard để đảm bảo chỉ admin gọi được.
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';

// Auth check inline (không dùng auth_guard.php vì nó redirect 302 — không
// phù hợp cho AJAX endpoint trả JSON).
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

$action = $_REQUEST['action'] ?? '';

try {
    if ($action === 'detail') {
        // ----- GET: Lấy chi tiết đơn -----
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Mã đơn không hợp lệ.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Header (kèm fallback user nếu không có recipient_name)
        $stmt = $pdo->prepare("
            SELECT o.id, o.recipient_name, o.phone, o.email, o.address,
                   o.delivery_time, o.pay_method, o.ship_fee, o.discount,
                   o.note_order, o.note, o.total_amount, o.status, o.created_at,
                   u.full_name AS user_name, u.email AS user_email
            FROM orders o
            LEFT JOIN users u ON u.id = o.user_id
            WHERE o.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $order = $stmt->fetch();
        if (!$order) {
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy đơn hàng.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Items
        $items_stmt = $pdo->prepare("
            SELECT oi.id, oi.quantity, oi.unit_price,
                   p.name AS product_name, p.image_url, p.unit
            FROM order_items oi
            INNER JOIN products p ON p.id = oi.product_id
            WHERE oi.order_id = :id
            ORDER BY oi.id ASC
        ");
        $items_stmt->execute([':id' => $id]);
        $items = $items_stmt->fetchAll();

        echo json_encode([
            'success' => true,
            'order'   => $order,
            'items'   => $items,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'update_status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        // ----- POST: Cập nhật status -----
        $id     = (int)($_POST['id']     ?? 0);
        $status = trim((string)($_POST['status'] ?? ''));

        $allowed = ['pending', 'processing', 'completed', 'cancelled'];
        if ($id <= 0 || !in_array($status, $allowed, true)) {
            echo json_encode([
                'success' => false,
                'message' => 'Tham số không hợp lệ.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmt = $pdo->prepare("SELECT status FROM orders WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $current = $stmt->fetchColumn();
        if ($current === false) {
            echo json_encode([
                'success' => false,
                'message' => 'Không tìm thấy đơn hàng.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ($current === 'cancelled' && $status !== 'cancelled') {
            echo json_encode([
                'success' => false,
                'message' => 'Đơn đã huỷ không thể chuyển lại trạng thái khác.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($current === $status) {
            $upd = $pdo->prepare("UPDATE orders SET status = :s WHERE id = :id");
            $upd->execute([':s' => $status, ':id' => $id]);
            echo json_encode([
                'success' => true,
                'message' => 'Đã cập nhật trạng thái.',
                'status'  => $status,
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $old_counts_as_sold = in_array($current, ['processing', 'completed'], true);
        $new_counts_as_sold = in_array($status, ['processing', 'completed'], true);

        $items_stmt = $pdo->prepare(
            "SELECT product_id, quantity FROM order_items WHERE order_id = :id"
        );
        $items_stmt->execute([':id' => $id]);
        $items = $items_stmt->fetchAll();

        $product_changes = [];
        foreach ($items as $item) {
            $pid = (int)$item['product_id'];
            $qty = (int)$item['quantity'];
            if ($pid <= 0 || $qty === 0) {
                continue;
            }
            $product_changes[$pid] = ($product_changes[$pid] ?? 0) + $qty;
        }

        $pdo->beginTransaction();
        try {
            foreach ($product_changes as $pid => $qty) {
                if ($current !== 'cancelled' && $status === 'cancelled') {
                    $restore_stock = $pdo->prepare(
                        "UPDATE products
                         SET stock = stock + :qty
                         WHERE id = :pid"
                    );
                    $restore_stock->execute([
                        ':qty' => $qty,
                        ':pid' => $pid,
                    ]);
                }

                if (!$old_counts_as_sold && $new_counts_as_sold) {
                    $update_sold = $pdo->prepare(
                        "UPDATE products
                         SET sold_count = sold_count + :qty
                         WHERE id = :pid"
                    );
                    $update_sold->execute([
                        ':qty' => $qty,
                        ':pid' => $pid,
                    ]);
                } elseif ($old_counts_as_sold && !$new_counts_as_sold) {
                    $update_sold = $pdo->prepare(
                        "UPDATE products
                         SET sold_count = GREATEST(sold_count - :qty, 0)
                         WHERE id = :pid"
                    );
                    $update_sold->execute([
                        ':qty' => $qty,
                        ':pid' => $pid,
                    ]);
                }
            }

            $upd = $pdo->prepare("UPDATE orders SET status = :s WHERE id = :id");
            $upd->execute([':s' => $status, ':id' => $id]);

            $pdo->commit();
            echo json_encode([
                'success' => true,
                'message' => 'Đã cập nhật trạng thái và thống kê tồn kho.',
                'status'  => $status,
            ], JSON_UNESCAPED_UNICODE);
            exit;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $err = $e->getMessage();
            error_log('[admin/order-action] stock update failed: ' . $err);
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Không thể cập nhật trạng thái đơn do lỗi hệ thống: ' . $err,
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    // Action không khớp gì cả
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Hành động không hợp lệ.',
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log('[admin/order-action] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi hệ thống, vui lòng thử lại.',
    ], JSON_UNESCAPED_UNICODE);
}

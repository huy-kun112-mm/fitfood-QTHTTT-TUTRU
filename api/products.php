<?php
/**
 * GET /api/products.php             → tất cả category + products active
 * GET /api/products.php?type=package
 * GET /api/products.php?type=product
 * GET /api/products.php?category=goi-an   (lọc theo slug category)
 *
 * Response shape:
 * {
 *   "success": true,
 *   "data": {
 *     "categories": [
 *       { "id": 1, "name": "Gói ăn", "slug": "goi-an", ...,
 *         "products": [ { "id": 1, "name": "Gói FIT 3", ... }, ... ] },
 *       ...
 *     ]
 *   }
 * }
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../lib/products.php';

if ($pdo === null) {
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'message' => 'Dịch vụ tạm thời không khả dụng. Vui lòng thử lại sau.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Chỉ chấp nhận GET.']);
    exit;
}

$filters = [];

if (isset($_GET['type'])) {
    $type = trim((string)$_GET['type']);
    if (!in_array($type, ['package', 'product'], true)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => "Tham số type không hợp lệ. Chỉ chấp nhận: package, product."
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $filters['type'] = $type;
}

if (isset($_GET['category'])) {
    $cat = trim((string)$_GET['category']);
    if ($cat !== '') {
        $filters['category'] = $cat;
    }
}

try {
    $categories = get_products_grouped($pdo, $filters);

    // Cast types để JSON sạch (không có "650000.00" string)
    foreach ($categories as &$c) {
        $c['id']         = (int)$c['id'];
        $c['sort_order'] = (int)$c['sort_order'];
        foreach ($c['products'] as &$p) {
            $p['id']          = (int)$p['id'];
            $p['category_id'] = $p['category_id'] !== null ? (int)$p['category_id'] : null;
            $p['price']       = (float)$p['price'];
            $p['sale_price']  = $p['sale_price'] !== null ? (float)$p['sale_price'] : null;
            $p['calories']    = $p['calories']  !== null ? (int)$p['calories']    : null;
            $p['stock']       = (int)$p['stock'];
            $p['is_featured'] = (bool)$p['is_featured'];
            $p['sort_order']  = (int)$p['sort_order'];
        }
        unset($p);
    }
    unset($c);

    echo json_encode([
        'success' => true,
        'data'    => ['categories' => $categories],
    ], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi truy vấn database.',
    ], JSON_UNESCAPED_UNICODE);
}

<?php
/**
 * Trang xác nhận đặt hàng — hiển thị thông tin đơn vừa đặt cho cả guest & user.
 *
 * Quyền truy cập:
 *   - Vừa đặt xong:           $_SESSION['last_order_id'] khớp order id.
 *   - User logged-in xem lại: orders.user_id khớp $_SESSION['user_id'].
 * Truy cập trái phép → redirect index.php.
 */
require_once __DIR__ . '/config/database.php';

$session_oid   = (int)($_SESSION['last_order_id']   ?? 0);
$url_oid       = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$order_id      = $session_oid ?: $url_oid;
$user_id       = $_SESSION['user_id'] ?? null;
$do_clear_cart = !empty($_SESSION['clear_cart_once']);

unset($_SESSION['clear_cart_once']);
$flash_success = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_success']);

if (!$order_id || !$pdo) {
    header('Location: index.php');
    exit;
}

try {
    $stmt = $pdo->prepare(
        "SELECT id, user_id, recipient_name, phone, email, address,
                delivery_time, pay_method, ship_fee, discount, note_order, note,
                total_amount, status, created_at
         FROM orders WHERE id = :id LIMIT 1"
    );
    $stmt->execute([':id' => $order_id]);
    $order = $stmt->fetch();
} catch (PDOException $e) {
    error_log('[order-success] ' . $e->getMessage());
    $order = null;
}

if (!$order) {
    header('Location: index.php');
    exit;
}

$can_view = false;
if ($session_oid === (int)$order['id'])                                $can_view = true;
elseif ($user_id && (int)$order['user_id'] === (int)$user_id)          $can_view = true;
if (!$can_view) {
    header('Location: index.php');
    exit;
}

// Items
try {
    $stmt = $pdo->prepare(
        "SELECT oi.quantity, oi.unit_price,
                p.name, p.image_url, p.unit
         FROM order_items oi
         INNER JOIN products p ON p.id = oi.product_id
         WHERE oi.order_id = :oid
         ORDER BY oi.id ASC"
    );
    $stmt->execute([':oid' => $order_id]);
    $items = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('[order-success] items: ' . $e->getMessage());
    $items = [];
}

function vnd($n): string {
    return number_format((float)$n, 0, ',', '.') . '₫';
}

function status_label_vn(string $s): array {
    $map = [
        'completed'  => ['Đã giao',     'success'],
        'processing' => ['Đang xử lý',  'primary'],
        'pending'    => ['Chờ xử lý',   'warning'],
        'cancelled'  => ['Đã huỷ',      'danger'],
    ];
    return $map[$s] ?? [$s, 'secondary'];
}

$pay_method_label = [
    'api_ocb' => 'OCB - QR',
    'api_acb' => 'ACB - QR',
    'CASH'    => 'Tiền mặt khi nhận hàng',
];
[$status_text, $status_color] = status_label_vn($order['status']);

$subtotal = 0.0;
foreach ($items as $it) $subtotal += (float)$it['unit_price'] * (int)$it['quantity'];
$created_str = $order['created_at']
    ? date('d/m/Y H:i', strtotime($order['created_at']))
    : '';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Đặt hàng thành công - Mã đơn #<?= (int)$order['id'] ?></title>
    <link rel="icon" href="/favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fitfood.vn/css/css.css?v=2026033101" rel="stylesheet">
    <style>
        body { font-family: 'Montserrat', sans-serif; background: #f7f7f7; }
        .os-wrap { max-width: 880px; margin: 40px auto; padding: 0 16px; }
        .os-card { background: #fff; border-radius: 8px; padding: 28px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,.06); }
        .os-hero { text-align: center; padding: 36px 28px; }
        .os-check { width: 72px; height: 72px; border-radius: 50%; background: #28a745;
            color: #fff; line-height: 72px; font-size: 40px; margin: 0 auto 14px; display: inline-block; }
        .os-hero h1 { color: #28a745; font-weight: 700; font-size: 28px; margin: 8px 0; }
        .os-hero .order-no { font-size: 18px; color: #555; }
        .os-hero .order-no strong { color: #e7100b; font-size: 22px; letter-spacing: 1px; }
        .os-card h3 { color: #e7100b; font-weight: 700; font-size: 16px; margin-bottom: 16px;
            text-transform: uppercase; letter-spacing: .5px; }
        .os-row { display: flex; padding: 6px 0; }
        .os-row .label { width: 160px; color: #888; }
        .os-row .value { flex: 1; color: #222; }
        .os-table { width: 100%; border-collapse: collapse; }
        .os-table th, .os-table td { padding: 12px 8px; text-align: left; border-bottom: 1px solid #eee; }
        .os-table th { background: #fafafa; font-weight: 600; color: #666; }
        .os-table .text-right { text-align: right; }
        .os-table img { width: 56px; height: 56px; object-fit: cover; border-radius: 4px; }
        .os-summary { margin-top: 16px; }
        .os-summary .row-line { display: flex; justify-content: space-between; padding: 6px 0; }
        .os-summary .total { font-size: 20px; font-weight: 700; color: #e7100b;
            border-top: 2px solid #eee; padding-top: 12px; margin-top: 8px; }
        .os-status { display: inline-block; padding: 4px 14px; border-radius: 20px; font-size: 13px; font-weight: 600; }
        .os-status.bg-warning { background: #fff3cd; color: #856404; }
        .os-status.bg-primary { background: #cce5ff; color: #004085; }
        .os-status.bg-success { background: #d4edda; color: #155724; }
        .os-status.bg-danger  { background: #f8d7da; color: #721c24; }
        .os-status.bg-secondary { background: #e2e3e5; color: #383d41; }
        .os-notice { background: #fff8e1; border-left: 4px solid #ffc107;
            padding: 12px 16px; border-radius: 4px; color: #6c5400; margin-top: 16px; }
        .os-actions { text-align: center; margin-top: 24px; }
        .os-actions .btn { margin: 4px; padding: 10px 22px; }
        @media print {
            .os-actions, .os-print-hide { display: none !important; }
            body { background: #fff; }
            .os-card { box-shadow: none; border: 1px solid #ddd; }
        }
    </style>
</head>
<body>
<header class="os-print-hide" style="background:#fff;padding:16px 0;border-bottom:1px solid #eee;">
    <div class="os-wrap" style="margin:0 auto;display:flex;align-items:center;justify-content:space-between;">
        <a href="index.php"><img src="/images/logo-healthyfood.png" alt="Fitfood" style="height:36px;"></a>
        <a href="index.php" style="color:#555;text-decoration:none;font-size:14px;">← Trang chủ</a>
    </div>
</header>

<main class="os-wrap">
    <?php if ($flash_success): ?>
    <div class="alert alert-success" style="margin-bottom:16px;">
        <?= htmlspecialchars($flash_success) ?>
    </div>
    <?php endif; ?>

    <div class="os-card os-hero">
        <span class="os-check">✓</span>
        <h1>Đặt hàng thành công!</h1>
        <p class="order-no">Mã đơn: <strong>#<?= (int)$order['id'] ?></strong></p>
        <p style="color:#888;font-size:13px;margin:6px 0 0;">Đặt lúc: <?= htmlspecialchars($created_str) ?></p>
        <div style="margin-top:14px;">
            <span class="os-status bg-<?= $status_color ?>">
                <?= htmlspecialchars($status_text) ?>
            </span>
        </div>
    </div>

    <div class="os-card">
        <h3>Thông tin nhận hàng</h3>
        <div class="os-row"><div class="label">Người nhận:</div><div class="value"><?= htmlspecialchars($order['recipient_name'] ?? '') ?></div></div>
        <div class="os-row"><div class="label">Số điện thoại:</div><div class="value"><?= htmlspecialchars($order['phone'] ?? '') ?></div></div>
        <?php if (!empty($order['email'])): ?>
        <div class="os-row"><div class="label">Email:</div><div class="value"><?= htmlspecialchars($order['email']) ?></div></div>
        <?php endif; ?>
        <div class="os-row"><div class="label">Địa chỉ:</div><div class="value"><?= htmlspecialchars($order['address'] ?? '') ?></div></div>
        <?php if (!empty($order['delivery_time'])): ?>
        <div class="os-row"><div class="label">Thời gian giao:</div><div class="value"><?= htmlspecialchars($order['delivery_time']) ?></div></div>
        <?php endif; ?>
        <div class="os-row"><div class="label">Thanh toán:</div>
            <div class="value"><?= htmlspecialchars($pay_method_label[$order['pay_method']] ?? $order['pay_method'] ?? '') ?></div>
        </div>
        <?php if (!empty($order['note_order'])): ?>
        <div class="os-row"><div class="label">Ghi chú món:</div><div class="value"><?= htmlspecialchars($order['note_order']) ?></div></div>
        <?php endif; ?>
        <?php if (!empty($order['note'])): ?>
        <div class="os-row"><div class="label">Ghi chú giao:</div><div class="value"><?= htmlspecialchars($order['note']) ?></div></div>
        <?php endif; ?>
    </div>

    <div class="os-card">
        <h3>Sản phẩm</h3>
        <table class="os-table">
            <thead>
                <tr>
                    <th colspan="2">Tên</th>
                    <th class="text-right">Đơn giá</th>
                    <th class="text-right">SL</th>
                    <th class="text-right">Thành tiền</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $it):
                $line = (float)$it['unit_price'] * (int)$it['quantity']; ?>
                <tr>
                    <td style="width:72px;"><img src="<?= htmlspecialchars($it['image_url'] ?? '') ?>" alt=""></td>
                    <td>
                        <div style="font-weight:600;"><?= htmlspecialchars($it['name']) ?></div>
                        <?php if (!empty($it['unit'])): ?>
                        <div style="color:#888;font-size:13px;"><?= htmlspecialchars($it['unit']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="text-right"><?= vnd($it['unit_price']) ?></td>
                    <td class="text-right"><?= (int)$it['quantity'] ?></td>
                    <td class="text-right"><strong><?= vnd($line) ?></strong></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <div class="os-summary">
            <div class="row-line"><span>Tạm tính:</span><span><?= vnd($subtotal) ?></span></div>
            <?php if ((float)$order['ship_fee'] > 0): ?>
            <div class="row-line"><span>Phí ship:</span><span><?= vnd($order['ship_fee']) ?></span></div>
            <?php endif; ?>
            <?php if ((float)$order['discount'] > 0): ?>
            <div class="row-line"><span>Giảm giá:</span><span>-<?= vnd($order['discount']) ?></span></div>
            <?php endif; ?>
            <div class="row-line total"><span>Tổng cộng:</span><span><?= vnd($order['total_amount']) ?></span></div>
        </div>
    </div>

    <div class="os-card os-notice">
        <strong>Lưu ý:</strong> Vui lòng <strong>lưu lại mã đơn #<?= (int)$order['id'] ?></strong> hoặc chụp ảnh trang này
        để tra cứu sau. Chúng tôi sẽ liên hệ qua SĐT <strong><?= htmlspecialchars($order['phone'] ?? '') ?></strong>
        trong vòng 1 giờ để xác nhận đơn hàng.
        <?php if (empty($user_id)): ?>
        <br><br>Bạn đang đặt với tư cách <strong>khách vãng lai</strong>. Nếu muốn theo dõi tất cả đơn của mình,
        vui lòng <a href="register.php">tạo tài khoản</a> trước khi đặt lần sau.
        <?php endif; ?>
    </div>

    <div class="os-actions">
        <a href="index.php" class="btn btn-primary">Tiếp tục mua sắm</a>
        <?php if ($user_id): ?>
        <a href="tracking-order.php" class="btn btn-outline-primary">Xem tất cả đơn của tôi</a>
        <?php endif; ?>
        <button type="button" class="btn btn-outline-dark" onclick="window.print()">In hoá đơn</button>
    </div>
</main>

<?php if ($do_clear_cart): ?>
<script>
(function(){ try { localStorage.removeItem('fitfood_cart_v1'); } catch(e){} })();
</script>
<?php endif; ?>
</body>
</html>

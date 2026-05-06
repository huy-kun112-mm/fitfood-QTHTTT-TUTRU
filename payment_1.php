<?php
require_once __DIR__ . '/config/database.php';

function payment_wants_json(): bool
{
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $xhr = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    return stripos($accept, 'application/json') !== false
        || strcasecmp($xhr, 'XMLHttpRequest') === 0;
}

function fail_place_order(string $message, int $status = 400): void
{
    if (payment_wants_json()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => $message,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $_SESSION['flash_error'] = $message;
    header('Location: payment.php');
    exit;
}

// ---------- Load user + addresses ----------
$user_id         = $_SESSION['user_id'] ?? null;
$user            = null;
$default_address = null;
$all_addresses   = [];
$flash_success   = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_success']);

if ($user_id && $pdo) {
    try {
        $stmt = $pdo->prepare(
            "SELECT id, full_name, email, phone FROM users WHERE id = :id LIMIT 1"
        );
        $stmt->execute([':id' => $user_id]);
        $user = $stmt->fetch();

        $stmt = $pdo->prepare(
            "SELECT id, recipient_name, phone, address, is_default
             FROM user_addresses
             WHERE user_id = :uid
             ORDER BY is_default DESC, id DESC"
        );
        $stmt->execute([':uid' => $user_id]);
        $all_addresses = $stmt->fetchAll();

        foreach ($all_addresses as $a) {
            if ((int)$a['is_default'] === 1) { $default_address = $a; break; }
        }
        if (!$default_address && !empty($all_addresses)) {
            $default_address = $all_addresses[0];
        }
    } catch (PDOException $e) {
        error_log('[payment] load: ' . $e->getMessage());
    }
}

// ---------- Handle: lưu địa chỉ mới (cho user đã login chưa có address) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'save_address'
    && $user_id && $pdo) {
    $name    = trim((string)($_POST['name']    ?? ''));
    $phone   = trim((string)($_POST['phone']   ?? ''));
    $street  = trim((string)($_POST['street']  ?? ''));
    $address = trim((string)($_POST['address'] ?? '')); // text full đã gộp từ JS

    $errs = [];
    if ($name === '')                                 $errs[] = 'Vui lòng nhập họ tên.';
    if (!preg_match('/^[0-9]{8,15}$/', $phone))       $errs[] = 'SĐT phải là 8-15 chữ số.';
    if ($address === '')                              $errs[] = 'Vui lòng nhập đầy đủ địa chỉ.';

    if (empty($errs)) {
        try {
            $pdo->beginTransaction();
            // Đặt default = 0 cho các địa chỉ cũ
            $pdo->prepare("UPDATE user_addresses SET is_default = 0 WHERE user_id = :uid")
                ->execute([':uid' => $user_id]);
            // Insert địa chỉ mới làm default
            $pdo->prepare(
                "INSERT INTO user_addresses (user_id, recipient_name, phone, address, is_default)
                 VALUES (:uid, :name, :phone, :address, 1)"
            )->execute([
                ':uid'     => $user_id,
                ':name'    => $name,
                ':phone'   => $phone,
                ':address' => $address,
            ]);
            $pdo->commit();
            $_SESSION['flash_success'] = 'Đã lưu địa chỉ mặc định.';
            header('Location: payment.php');
            exit;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('[payment] save_address: ' . $e->getMessage());
            $_SESSION['flash_error'] = 'Không thể lưu địa chỉ: ' . $e->getMessage();
            header('Location: payment.php');
            exit;
        }
    } else {
        $_SESSION['flash_error'] = implode(' ', $errs);
        header('Location: payment.php');
        exit;
    }
}
// ---------- Handle: đặt hàng (place_order) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'place_order'
    && $pdo) {

    $name          = trim((string)($_POST['name']          ?? ''));
    $phone         = trim((string)($_POST['phone']         ?? ''));
    $email         = trim((string)($_POST['email']         ?? ''));
    $address       = trim((string)($_POST['address']       ?? ''));
    $delivery_time = trim((string)($_POST['delivery_time'] ?? ''));
    $pay_method    = trim((string)($_POST['pay_method']    ?? ''));
    $note_order    = trim((string)($_POST['note_order']    ?? ''));
    $note          = trim((string)($_POST['note']          ?? ''));
    $ship_fee      = (float)($_POST['ship_fee']            ?? 0);
    $discount      = (float)($_POST['discount']            ?? 0);
    $cart_raw      = (string)($_POST['cart_items']         ?? '[]');

    $items = json_decode($cart_raw, true);
    if (!is_array($items)) $items = [];

    $errs = [];
    if ($name === '')                              $errs[] = 'Thiếu họ tên người nhận.';
    if (!preg_match('/^[0-9]{8,15}$/', $phone))    $errs[] = 'SĐT không hợp lệ.';
    if ($address === '')                           $errs[] = 'Thiếu địa chỉ giao hàng.';
    if ($delivery_time === '')                     $errs[] = 'Vui lòng chọn thời gian giao hàng.';
    if (empty($items))                             $errs[] = 'Giỏ hàng trống.';

    if (empty($errs)) {
        try {
            $ids = [];
            foreach ($items as $it) {
                $pid = (int)($it['id'] ?? 0);
                if ($pid > 0) $ids[$pid] = true;
            }
            $ids = array_keys($ids);
            if (empty($ids)) {
                throw new RuntimeException('Sản phẩm trong giỏ không còn tồn tại.');
            }

            $pdo->beginTransaction();

            $valid_products = [];
            $place = implode(',', array_fill(0, count($ids), '?'));
            $stmt  = $pdo->prepare(
                "SELECT id, name, type, price, sale_price, stock
                 FROM products
                 WHERE id IN ($place) AND is_active = 1
                 FOR UPDATE"
            );
            $stmt->execute($ids);
            foreach ($stmt->fetchAll() as $p) {
                $valid_products[(int)$p['id']] = [
                    'name' => (string)$p['name'],
                    'type' => (string)$p['type'],
                    'stock' => max(0, (int)$p['stock']),
                    'price' => $p['sale_price'] !== null
                        ? (float)$p['sale_price']
                        : (float)$p['price'],
                ];
            }

            $package_variant_prices = [
                1 => ['week' => 650000.0, 'month' => 2340000.0],
                2 => ['week' => 825000.0, 'month' => 2970000.0],
                3 => ['week' => 650000.0, 'month' => 2340000.0],
                4 => ['week' => 950000.0, 'month' => 3420000.0],
                5 => ['week' => 600000.0, 'month' => 2160000.0],
                6 => ['week' => 349000.0],
            ];

            // Gom theo product_id + unit_price để giữ riêng biến thể tuần/tháng.
            $line_items = [];
            $requested_by_product = [];
            foreach ($items as $it) {
                $pid = (int)($it['id'] ?? 0);
                if (!isset($valid_products[$pid])) continue;
                $qty = max(1, (int)($it['quantity'] ?? 1));
                $requested_by_product[$pid] = ($requested_by_product[$pid] ?? 0) + $qty;
                $unit_price = $valid_products[$pid]['price'];
                $variant_type = trim((string)($it['type'] ?? ''));
                if ($valid_products[$pid]['type'] === 'package'
                    && isset($package_variant_prices[$pid][$variant_type])) {
                    $unit_price = $package_variant_prices[$pid][$variant_type];
                }
                $key = $pid . '|' . $unit_price;
                if (!isset($line_items[$key])) {
                    $line_items[$key] = ['pid' => $pid, 'qty' => 0, 'unit_price' => $unit_price];
                }
                $line_items[$key]['qty'] += $qty;
            }

            if (empty($line_items)) {
                throw new RuntimeException('Sản phẩm trong giỏ không còn tồn tại.');
            }

            foreach ($requested_by_product as $pid => $requested_qty) {
                $stock = $valid_products[$pid]['stock'];
                if ($requested_qty > $stock) {
                    throw new RuntimeException('Chỉ còn ' . $stock . ' sản phẩm trong kho');
                }
            }

            $subtotal = 0.0;
            foreach ($line_items as $li) {
                $subtotal += $li['unit_price'] * $li['qty'];
            }
            $total_amount = max(0, $subtotal + $ship_fee - $discount);

            $pdo->prepare(
                "INSERT INTO orders
                   (user_id, recipient_name, phone, email, address,
                    delivery_time, pay_method, ship_fee, discount,
                    note_order, note, total_amount, status)
                 VALUES
                   (:uid, :name, :phone, :email, :address,
                    :dtime, :pay, :ship, :disc,
                    :note_order, :note, :total, 'pending')"
            )->execute([
                ':uid'        => $user_id ?: null,
                ':name'       => $name,
                ':phone'      => $phone,
                ':email'      => $email !== '' ? $email : null,
                ':address'    => $address,
                ':dtime'      => $delivery_time,
                ':pay'        => $pay_method,
                ':ship'       => $ship_fee,
                ':disc'       => $discount,
                ':note_order' => $note_order !== '' ? $note_order : null,
                ':note'       => $note !== '' ? $note : null,
                ':total'      => $total_amount,
            ]);
            $order_id = (int)$pdo->lastInsertId();

            $ins = $pdo->prepare(
                "INSERT INTO order_items (order_id, product_id, quantity, unit_price)
                 VALUES (:oid, :pid, :qty, :price)"
            );
            foreach ($line_items as $li) {
                $ins->execute([
                    ':oid'   => $order_id,
                    ':pid'   => $li['pid'],
                    ':qty'   => $li['qty'],
                    ':price' => $li['unit_price'],
                ]);
            }

            $dec = $pdo->prepare(
                "UPDATE products
                 SET stock = stock - :dec_qty
                 WHERE id = :pid AND stock >= :min_qty"
            );
            foreach ($requested_by_product as $pid => $requested_qty) {
                $dec->execute([
                    ':dec_qty' => $requested_qty,
                    ':pid' => $pid,
                    ':min_qty' => $requested_qty,
                ]);
                if ($dec->rowCount() !== 1) {
                    $stock_stmt = $pdo->prepare("SELECT stock FROM products WHERE id = :pid");
                    $stock_stmt->execute([':pid' => $pid]);
                    $current_stock = max(0, (int)$stock_stmt->fetchColumn());
                    throw new RuntimeException('Chỉ còn ' . $current_stock . ' sản phẩm trong kho');
                }
            }

            $pdo->commit();

            $_SESSION['flash_success']    = 'Đặt hàng thành công! Mã đơn: #' . $order_id;
            $_SESSION['last_order_id']    = $order_id;
            $_SESSION['clear_cart_once']  = 1; // order-success.php sẽ xoá localStorage
            header('Location: order-success.php');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('[payment] place_order: ' . $e->getMessage());
            $message = $e->getMessage();
            if (strpos($message, 'Chỉ còn ') !== 0) {
                $message = 'Không thể đặt hàng: ' . $message;
            }
            fail_place_order($message);
        }
    } else {
        $_SESSION['flash_error'] = implode(' ', $errs);
        header('Location: payment.php');
        exit;
    }
}

$flash_error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_error']);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="M0ZWj7dgiFlxa2Hzr9rfzEhmG4K8QrJKj7hgotBT">
    <meta name="lang" content="vi">

    <title>Thanh toán an toàn 100%</title>
                    <meta name="description" content="Thanh toán online an toàn 100% với momo, thẻ tín dụng hoặc chuyển khoản">
                            <meta name="keywords" content="thanh toan an toan">
            
    <link rel="icon" href="/favicon.ico">
    <link rel="canonical" href="https://fitfood.vn/payment" />
    <link rel="alternate" hreflang="vi" href="https://fitfood.vn/payment" />


    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">

    <link href="https://fitfood.vn/css/fonts.css?v=2026033101" rel="stylesheet">
    <link href="https://fitfood.vn/css/vendor.css?v=2026033101" rel="stylesheet">
    <link href="https://fitfood.vn/css/css.css?v=2026033101" rel="stylesheet">

    <!-- CSS popup đăng ký/đăng nhập -->
    <link href="assets/css/register.css" rel="stylesheet">

    <meta property="og:site_name" content="Fitfood.vn" />
    <meta property="og:type" content="article" />

            <meta name="google-site-verification" content="AbTvSYDeKcRTKgJ4dNUNDKf4mt2ZO_Rz8z4W9R4Zb_A" />
    
    
    <meta property="fb:app_id" content="1499077570417040" />

    <meta property="og:image:width" content="2048" />
    <meta property="og:image:height" content="1365" />

                <script type="application/ld+json">{"@context":"https:\/\/schema.org","@type":"LocalBusiness","name":"Fitfood","email":"info@fitfood.vn","telephone":"(+84) 932 788 120","address":{"@type":"PostalAddress","streetAddress":"33 Đường 14, KDC Bình Hưng, Ấp 2"}}</script>
            <script type="application/ld+json">{"@context":"https:\/\/schema.org","@type":"Organization","name":"Fitfood","url":"https:\/\/fitfood.vn","logo":"https:\/\/fitfood.vn\/images\/logo.png"}</script>
        
                    <!-- Google Tag Manager -->
        <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
                    new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
                j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
                'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
            })(window,document,'script','dataLayer','GTM-K4CMNLV');</script>
        <!-- End Google Tag Manager -->
    
    

    <style type="text/css">
        .doimk {display: none;}

        .hienmatkhau {display: inline-block !important;}

        .matkhaugiainen .nhanvao {display: none !important;}

        .new-links{
            display:block !important;
            color:red;
        }
    </style>
</head>

<body data-page="" data-device="desktop"
    class="" data-url="https://fitfood.vn/payment"
    data-lang="vi">
    <!-- Google Tag Manager (noscript) -->
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-K4CMNLV"
                      height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    <!-- End Google Tag Manager (noscript) -->
<!--
<script async defer src="https://connect.facebook.net/vi_VN/sdk.js#xfbml=1&version=v3.2"></script>
-->

<nav id="main-menu" class="navbar navbar-expand-xl navbar-dark bg-dark fixed-top">
    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#main-navigation"
            aria-controls="main-navigation" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
    </button>

    <a class="navbar-brand mr-auto logo-header" href="https://fitfood.vn">
        <img src="/images/logo-healthyfood.png" alt="" border="0"/>
        <img class="mobile-invisible" src="/images/logo-healthyfood-mobile.png" alt="" border="0"/>
    </a>

    <div class="collapse navbar-collapse d-flex flex-column flex-xl-row" id="main-navigation">
        <div class="d-block d-sm-none" style="padding-left: 20px; padding-right: 20px">
            <div class="row">
    <div class="col-12 form-group">
        <form action="https://fitfood.vn/search" class="form-search">
            <div class="search-control s-menu">
                <input type="text" class="form-control s-search" name="s" placeholder="Tìm kiếm" value="">
            </div>
        </form>
    </div>
</div>
        </div>
        <ul class="nav navbar-nav mr-auto ml-xl-auto order-2 order-xl-0">
    <li  class="nav-item">
     <a  class="nav-link" href="https://fitfood.vn">
      Trang chủ
          </a>
          </li>
    
  <li  class="nav-item">
     <a  class="nav-link" href="https://fitfood.vn/menu">
      Thực đơn
          </a>
          </li>
    
  <li  class="nav-item premium">
     <a  class="nav-link" href="https://fitfood.vn/b2b">
      Đặt tiệc
          </a>
          </li>
    
  <li  class="nav-item">
     <a  class="nav-link" href="order.php">
      Đặt hàng
          </a>
          </li>
    
  <li  class="nav-item">
     <a  class="nav-link" href="https://fitfood.vn/tin-tuc/su-kien">
      Hình ảnh
          </a>
          </li>
    
  <li  class="nav-item">
     <a  class="nav-link" href="https://fitfood.vn/faqs">
      FAQs
          </a>
          </li>
  </ul>
        <ul class="nav navbar-nav navbar-sub flex-row order-1 order-xl-0">
           <?php if (isset($_SESSION['user_id'])): ?>
                <!-- Đã đăng nhập: avatar + tên (click → account.php) + thoát -->
                <?php
                  $av = $_SESSION['user_avatar'] ?? '';
                  $avatar_url = $av !== ''
                      ? (preg_match('#^https?://#', $av) ? $av : 'uploads/avatars/' . htmlspecialchars($av))
                      : 'https://fitfood.vn/img/128/avatars/default.png';
                  // Lấy tên ngắn (chữ cuối) cho hiển thị giống template
                  $parts = preg_split('/\s+/', trim((string)$_SESSION['user_name']));
                  $short_name = end($parts) ?: $_SESSION['user_name'];
                ?>
                <li class="user-nav d-flex">
                    <a href="account.php">
                        <div class="avatar" style="background-image:url('<?= $avatar_url ?>')"></div>
                    </a>
                    <div>
                        Xin Chào
                        <a href="account.php" class="name"><strong><?= htmlspecialchars($short_name) ?></strong></a>
                        <br/><a href="logout.php">Thoát</a>
                    </div>
                </li>
            <?php else: ?>
                <!-- Chưa đăng nhập: hiện đăng ký + đăng nhập -->
                <li class="nav-item"><a id="btnOpenRegister" href="javascript:void(0)" class="nav-link">Đăng ký</a></li>
                <li class="nav-item"><a id="btnOpenLogin" href="javascript:void(0)" class="nav-link">Đăng nhập</a></li>
            <?php endif; ?>
        </ul>
        <div class="language mobile-invisible order-3 d-lg-none">
            <span class="lang-switch active" data-lang="en">
                <span>EN</span>
            </span>
            <span class="arrow">/</span>
            <span class="lang-switch  " data-lang="vi">
                <span>VI</span>
            </span>
        </div>
        <div class="mobile-invisible-overlap"></div>
    </div>

    <div id="cart-list" class="shopping-cart order-2 order-xl-0 " data-url-list="https://fitfood.vn/cart/list"
    data-url-remove="https://fitfood.vn/cart/package/remove" data-popup-sale="1">
    <a href="javascript:void(0)" id="cart" data-total="0">
        <span class="badge hide">0</span>
    </a>
    <div class="list-order-cart">
        <div class="cart-header">
            <a href="javascript:void(0)" class="close"><i class="fa fa-times" aria-hidden="true"></i></a>
            <div class="title">Giỏ hàng</div>
        </div>

        <div id="cart-list-content" class="cart-list-content">
        </div>
    </div>
    <div class="overlay"></div>
</div>


    <div class="language desktop-visible order-3 order-sm-0">
        <span class="lang-switch active" data-lang="en">
            <span class="d-sm-none">English</span>
            <span class="d-none d-sm-block">EN</span>
        </span>
        <span class="arrow">/</span>
        <span class="lang-switch  " data-lang="vi">
            <span class="d-sm-none">Vietnamese</span>
            <span class="d-none d-sm-block">VI</span>
        </span>
    </div>
    <input type="hidden" id="route-lang" value="https://fitfood.vn/lang">
</nav>

<main class="template_edit_package">
    <section class="top-inner">
        <div class="container">
            <nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item completed">
            <a href="javascript:void(0);">
                <span><i class="fa fa-order" aria-hidden="true"></i></span>Đặt hàng
            </a>
        </li>
        <li class="breadcrumb-item completed">
            <a href="javascript:void(0);">
                <span><i class="fa fa-ellipsis-h" aria-hidden="true"></i></span>Chỉnh sửa
            </a>
        </li>
        <li class="breadcrumb-item active">
            <a href="javascript:void(0);">
                <span><i class="fa fa-usd" aria-hidden="true"></i></span>Thanh toán
            </a>
        </li>
        <li class="breadcrumb-item ">
            <a href="javascript:void(0);">
                <span><i class="fa fa-check" aria-hidden="true"></i></span>Xác nhận
            </a>
        </li>
    </ol>
</nav>
            <h3 class="title">Thanh toán</h3>

                        
                        <form id="payment-form" action="payment.php" method="post">
<input type="hidden" name="action" value="place_order">
<input type="hidden" name="cart_items" id="cart-items-json" value="[]">
<input type="hidden" name="ship_fee" id="ship-fee-input" value="0">
<input type="hidden" name="discount" id="discount-input" value="0">

<?php if ($flash_success): ?>
  <div class="alert alert-success mb-3"><?= htmlspecialchars($flash_success) ?></div>
<?php endif; ?>
<?php if ($flash_error): ?>
  <div class="alert alert-danger mb-3"><?= htmlspecialchars($flash_error) ?></div>
<?php endif; ?>

<?php if ($default_address): /* === STATE 1: đã đăng nhập + có địa chỉ default === */ ?>
  <div class="box-payment-info" style="background:#fff;padding:24px;border-radius:8px;margin-bottom:20px;">
    <h3 style="color:#e7100b;font-weight:700;font-size:18px;margin-bottom:20px;letter-spacing:0.3px;">Địa chỉ giao hàng</h3>
    <div id="payment-address-card">
      <div class="row mb-3">
        <div class="col-4 col-md-3"><span style="color:#888;">Tên tài khoản:</span></div>
        <div class="col-8 col-md-9"><span class="addr-name"><?= htmlspecialchars($default_address['recipient_name']) ?></span></div>
      </div>
      <div class="row mb-3">
        <div class="col-4 col-md-3"><span style="color:#888;">Mobile:</span></div>
        <div class="col-8 col-md-9"><span class="addr-phone"><?= htmlspecialchars($default_address['phone']) ?></span></div>
      </div>
      <div class="row mb-3">
        <div class="col-4 col-md-3"><span style="color:#888;">Email:</span></div>
        <div class="col-8 col-md-9"><span class="addr-email"><?= htmlspecialchars($user['email'] ?? '') ?></span></div>
      </div>
      <div class="row align-items-center">
        <div class="col-4 col-md-3"><span style="color:#888;">Địa chỉ:</span></div>
        <div class="col-8 col-md-9 d-flex flex-wrap align-items-center" style="gap:12px;">
          <span class="addr-text" style="flex:1;min-width:200px;"><?= htmlspecialchars($default_address['address']) ?></span>
          <span id="addr-default-badge"
                style="background:#dcdcdc;color:#555;padding:6px 18px;border-radius:30px;font-size:13px;
                       <?= (int)$default_address['is_default'] === 1 ? '' : 'display:none;' ?>">Default</span>
          <button type="button" class="btn btn-outline-danger" data-toggle="modal" data-target="#selectAddressModal"
                  style="border-radius:6px;padding:6px 22px;">Đổi</button>
        </div>
      </div>
    </div>

    <input type="hidden" name="address_id" id="addr_id"      value="<?= (int)$default_address['id'] ?>">
    <input type="hidden" name="name"       id="addr_name_h"  value="<?= htmlspecialchars($default_address['recipient_name']) ?>">
    <input type="hidden" name="phone"      id="addr_phone_h" value="<?= htmlspecialchars($default_address['phone']) ?>">
    <input type="hidden" name="email"      id="addr_email_h" value="<?= htmlspecialchars($user['email'] ?? '') ?>">
    <input type="hidden" name="address"    id="addr_addr_h"  value="<?= htmlspecialchars($default_address['address']) ?>">
  </div>

<?php elseif ($user_id): /* === STATE 2: đã đăng nhập NHƯNG chưa có địa chỉ nào === */ ?>
  <div class="box-payment-info" style="background:#fff;padding:24px;border-radius:8px;margin-bottom:20px;">
    <h3 style="color:#e7100b;font-weight:700;font-size:18px;margin-bottom:8px;">Địa chỉ giao hàng</h3>
    <p class="text-muted mb-4">Bạn chưa có địa chỉ. Vui lòng nhập để lưu lại, lần sau khỏi nhập lại.</p>
    <div class="row">
      <div class="col-12 form-group">
        <label for="cus_name">Họ &amp; Tên <span class="req">*</span></label>
        <input type="text" id="cus_name" name="name" class="form-control" maxlength="90"
               value="<?= htmlspecialchars($user['full_name'] ?? '') ?>">
      </div>
      <div class="col-12 col-md-6 form-group">
        <label for="cus_phone">SĐT <span class="req">*</span></label>
        <input type="text" id="cus_phone" name="phone" class="form-control" maxlength="15"
               value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
      </div>
      <div class="col-12 col-md-6 form-group">
        <label for="cus_district">Quận <span class="req">*</span></label>
        <select class="form-control" id="cus_district" name="district_id">
          <option value="">Chọn quận</option>
          <option value="1">Huyện Bình Chánh</option><option value="26">Huyện Nhà Bè</option>
          <option value="6">Quận 01</option><option value="7">Quận 02</option>
          <option value="31">Quận 02 (Thạnh Mỹ Lợi)</option><option value="8">Quận 03</option>
          <option value="9">Quận 04</option><option value="10">Quận 05</option>
          <option value="11">Quận 06</option><option value="12">Quận 07</option>
          <option value="13">Quận 08 (P.1-14)</option><option value="33">Quận 08 (P.15-16)</option>
          <option value="30">Quận 09</option><option value="15">Quận 10</option>
          <option value="16">Quận 11</option><option value="18">Quận Bình Tân</option>
          <option value="19">Quận Bình Thạnh</option><option value="20">Quận Gò Vấp</option>
          <option value="21">Quận Phú Nhuận</option><option value="22">Quận Tân Bình</option>
          <option value="23">Quận Tân Phú</option><option value="29">Quận Thủ Đức</option>
        </select>
      </div>
      <div class="col-12 form-group">
        <label for="cus_address">Số nhà, tên đường, phường <span class="req">*</span></label>
        <input type="text" id="cus_address" name="address" maxlength="400" class="form-control"
               placeholder="VD: 115/5 Lê Quang Định, Phường 14">
        <small class="text-muted">Bao gồm phường để tài xế dễ tìm.</small>
      </div>
    </div>
    <button type="button" class="btn btn-primary" id="btn-save-address">
      <i class="fa fa-save mr-1"></i>Lưu địa chỉ
    </button>
    <p class="small text-muted mt-2 mb-0">* Sau khi lưu, trang sẽ refresh và hiện thông tin địa chỉ phía trên.</p>
  </div>

  <script>
  // AJAX: gộp street + ward + district → POST save_address → reload
  (function () {
    var btn = document.getElementById('btn-save-address');
    if (!btn) return;
    btn.addEventListener('click', function () {
      var name   = (document.getElementById('cus_name')    || {}).value || '';
      var phone  = (document.getElementById('cus_phone')   || {}).value || '';
      var street = (document.getElementById('cus_address') || {}).value || '';
      var ds = document.getElementById('cus_district');
      var distTxt = ds && ds.options[ds.selectedIndex] ? ds.options[ds.selectedIndex].text : '';
      var address = [street, distTxt]
        .map(function (s) { return (s || '').trim(); })
        .filter(function (s) { return s && s.indexOf('Chọn') !== 0; })
        .join(', ');

      if (!name.trim() || !phone.trim() || !street.trim()) {
        alert('Vui lòng nhập đầy đủ Họ tên, SĐT và Số nhà & tên đường.');
        return;
      }

      var fd = new FormData();
      fd.append('action',  'save_address');
      fd.append('name',    name);
      fd.append('phone',   phone);
      fd.append('address', address);

      btn.disabled = true;
      btn.textContent = 'Đang lưu…';

      fetch('payment.php', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function () { window.location.reload(); })
        .catch(function () {
          alert('Không thể lưu địa chỉ. Vui lòng thử lại.');
          btn.disabled = false;
          btn.textContent = 'Lưu địa chỉ';
        });
    });
  })();
  </script>

<?php else: /* === STATE 3: chưa đăng nhập — giữ nguyên intro + guest form === */ ?>
                                    <div class="box-payment-info box-guest-address">
    <div class="row">
        <div class="col-12">
            <div class="intro"><p>* Bạn có tài khoản Fitfood? Xin đăng nhập để nhận ưu đãi</p><p>* Nếu bạn không có tài khoản, bạn có thể nhấn nút mua hàng Khách Vãng Lai</p></div>
            <div class="buttons">
                <button type="button" class="btn btn-blue" onclick="document.getElementById('loginModal').classList.add('active'); document.body.style.overflow='hidden';">Đăng nhập Google</button>
                <button type="button" class="btn btn-gray btn-buy-guest">Khách vãng lai</button>
            </div>
        </div>

    </div>
    <div class="row guest-address">
        <div class="col-12 col-md-7">
            <div class="row">
                <div class="col-12 form-group">
                    <label for="cus_name">Họ &amp; Tên <span class="req">*</span></label>
                    <input type="text" id="cus_name" name="name" class="form-control" maxlength="90" placeholder="" data-validation="Vui lòng nhập họ tên">
                </div>
                <div class="col-12 col-md-6 form-group">
                    <label for="cus_phone">SĐT <span class="req">*</span></label>
                    <input type="number" id="cus_phone" name="phone" class="form-control" maxlength="10" placeholder="" data-validation="Vui lòng nhập số điện thoại">
                </div>
                <div class="col-12 col-md-6 form-group">
                    <label>Email <span class="req">*</span></label>
                    <input type="text" id="cus_email" name="email" class="form-control" placeholder="" data-validation="Vui lòng nhập email" data-regex="Email chưa đúng định dạng">
                </div>
            </div>
            <div class="row">
                <div class="col-6 form-group">
                    <label for="cus_district">Quận <span class="req">*</span></label>
                    <div>
                        <select class="form-control iselect2" id="cus_district" name="district_id" data-placeholder="Chọn quận" data-validation="Vui lòng nhập quận">
                            <option></option>
                                                            <option value="1">Huyện Bình Chánh</option>
                                                            <option value="26">Huyện Nhà Bè</option>
                                                            <option value="6">Quận 01</option>
                                                            <option value="7">Quận 02</option>
                                                            <option value="31">Quận 02 (Thạnh Mỹ Lợi)</option>
                                                            <option value="8">Quận 03</option>
                                                            <option value="9">Quận 04</option>
                                                            <option value="10">Quận 05</option>
                                                            <option value="11">Quận 06</option>
                                                            <option value="12">Quận 07</option>
                                                            <option value="13">Quận 08 (Phường 1-&gt;14)</option>
                                                            <option value="33">Quận 08 (Phường 15,16)</option>
                                                            <option value="30">Quận 09</option>
                                                            <option value="15">Quận 10</option>
                                                            <option value="16">Quận 11</option>
                                                            <option value="18">Quận Bình Tân</option>
                                                            <option value="19">Quận Bình Thạnh</option>
                                                            <option value="20">Quận Gò Vấp</option>
                                                            <option value="21">Quận Phú Nhuận</option>
                                                            <option value="22">Quận Tân Bình</option>
                                                            <option value="23">Quận Tân Phú</option>
                                                            <option value="29">Quận Thủ Đức</option>
                                                    </select>
                    </div>
                </div>
                <div class="col-6 form-group">
                    <label for="ward_id">Phường <span class="req">*</span></label>
                    <div>
                        <select class="form-control iselect2" id="cus_ward" name="ward_id" data-validation="Vui lòng nhập phường" data-placeholder="Chọn phường">
                            <option value=""></option>
                        </select>
                    </div>
                </div>
                <div class="col-12">
                    <label for="cus_street">Số nhà &amp; tên đường <span class="req">*</span></label>
                    <input type="text" id="cus_address" name="address" maxlength="400" class="form-control" placeholder="" data-validation="Vui lòng nhập địa chỉ" >
                </div>
            </div>
        </div>
    </div>
    <div style="display: none">
        <textarea id="data_ward">{"1":[{"id":4,"text":"X\u00e3 B\u00ecnh H\u01b0ng"}],"2":[{"id":17,"text":"Th\u1ecb tr\u1ea5n C\u1ea7n Th\u1ea1nh"},{"id":18,"text":"X\u00e3 An Th\u1edbi \u0110\u00f4ng"},{"id":19,"text":"X\u00e3 B\u00ecnh Kh\u00e1nh"},{"id":20,"text":"X\u00e3 Long H\u00f2a"},{"id":21,"text":"X\u00e3 L\u00fd Nh\u01a1n"},{"id":22,"text":"X\u00e3 Tam Th\u00f4n Hi\u1ec7p"},{"id":23,"text":"X\u00e3 Th\u1ea1nh An"}],"3":[{"id":24,"text":"Th\u1ecb tr\u1ea5n C\u1ee7 Chi"},{"id":25,"text":"X\u00e3 An Nh\u01a1n T\u00e2y"},{"id":26,"text":"X\u00e3 An Ph\u00fa"},{"id":27,"text":"X\u00e3 B\u00ecnh M\u1ef9"},{"id":28,"text":"X\u00e3 H\u00f2a Ph\u00fa"},{"id":29,"text":"X\u00e3 Nhu\u1eadn \u0110\u1ee9c"},{"id":30,"text":"X\u00e3 Ph\u1ea1m V\u0103n C\u1ed9i"},{"id":31,"text":"X\u00e3 Ph\u00fa H\u00f2a \u0110\u00f4ng"},{"id":32,"text":"X\u00e3 Ph\u00fa M\u1ef9 H\u01b0ng"},{"id":33,"text":"X\u00e3 Ph\u01b0\u1edbc Hi\u1ec7p"},{"id":34,"text":"X\u00e3 Ph\u01b0\u1edbc Th\u1ea1nh"},{"id":35,"text":"X\u00e3 Ph\u01b0\u1edbc V\u0129nh An"},{"id":36,"text":"X\u00e3 T\u00e2n An H\u1ed9i"},{"id":37,"text":"X\u00e3 T\u00e2n Ph\u00fa Trung"},{"id":39,"text":"X\u00e3 T\u00e2n Th\u1ea1nh \u0110\u00f4ng"},{"id":38,"text":"X\u00e3 T\u00e2n Th\u1ea1nh T\u00e2y"},{"id":40,"text":"X\u00e3 T\u00e2n Th\u00f4ng H\u1ed9i"},{"id":41,"text":"X\u00e3 Th\u00e1i M\u1ef9"},{"id":42,"text":"X\u00e3 Trung An"},{"id":43,"text":"X\u00e3 Trung L\u1eadp H\u1ea1"},{"id":44,"text":"X\u00e3 Trung L\u1eadp Th\u01b0\u1ee3ng"}],"4":[{"id":45,"text":"Th\u1ecb tr\u1ea5n H\u00f3c M\u00f4n"},{"id":46,"text":"X\u00e3 B\u00e0 \u0110i\u1ec3m"},{"id":56,"text":"X\u00e3 \u0110\u00f4ng Th\u1ea1nh"},{"id":47,"text":"X\u00e3 Nh\u1ecb B\u00ecnh"},{"id":48,"text":"X\u00e3 T\u00e2n Hi\u1ec7p"},{"id":49,"text":"X\u00e3 T\u00e2n Th\u1edbi Nh\u00ec"},{"id":50,"text":"X\u00e3 T\u00e2n Xu\u00e2n"},{"id":51,"text":"X\u00e3 Th\u1edbi Tam Th\u00f4n"},{"id":52,"text":"X\u00e3 Trung Ch\u00e1nh"},{"id":55,"text":"X\u00e3 Xu\u00e2n Th\u1edbi \u0110\u00f4ng"},{"id":53,"text":"X\u00e3 Xu\u00e2n Th\u1edbi S\u01a1n"},{"id":54,"text":"X\u00e3 Xu\u00e2n Th\u1edbi Th\u01b0\u1ee3ng"}],"5":[{"id":57,"text":"Th\u1ecb tr\u1ea5n Nh\u00e0 B\u00e8"},{"id":58,"text":"X\u00e3 Hi\u1ec7p Ph\u01b0\u1edbc"},{"id":59,"text":"X\u00e3 Long Th\u1edbi"},{"id":60,"text":"X\u00e3 Nh\u01a1n \u0110\u1ee9c"},{"id":61,"text":"X\u00e3 Ph\u00fa Xu\u00e2n"},{"id":62,"text":"X\u00e3 Ph\u01b0\u1edbc Ki\u1ec3n"},{"id":63,"text":"X\u00e3 Ph\u01b0\u1edbc L\u1ed9c"}],"6":[{"id":64,"text":"Ph\u01b0\u1eddng B\u1ebfn Ngh\u00e9"},{"id":65,"text":"Ph\u01b0\u1eddng B\u1ebfn Th\u00e0nh"},{"id":66,"text":"Ph\u01b0\u1eddng C\u1ea7u Kho"},{"id":67,"text":"Ph\u01b0\u1eddng C\u1ea7u \u00d4ng L\u00e3nh"},{"id":68,"text":"Ph\u01b0\u1eddng C\u00f4 Giang"},{"id":73,"text":"Ph\u01b0\u1eddng \u0110a Kao"},{"id":69,"text":"Ph\u01b0\u1eddng Nguy\u1ec5n C\u01b0 Trinh"},{"id":70,"text":"Ph\u01b0\u1eddng Nguy\u1ec5n Th\u00e1i B\u00ecnh"},{"id":71,"text":"Ph\u01b0\u1eddng Ph\u1ea1m Ng\u0169 L\u00e3o"},{"id":72,"text":"Ph\u01b0\u1eddng T\u00e2n \u0110\u1ecbnh"}],"7":[{"id":74,"text":"Ph\u01b0\u1eddng An Kh\u00e1nh"},{"id":75,"text":"Ph\u01b0\u1eddng An L\u1ee3i \u0110\u00f4ng"},{"id":76,"text":"Ph\u01b0\u1eddng An Ph\u00fa"},{"id":77,"text":"Ph\u01b0\u1eddng B\u00ecnh An"},{"id":341,"text":"Ph\u01b0\u1eddng B\u00ecnh Kh\u00e1nh"},{"id":80,"text":"Ph\u01b0\u1eddng B\u00ecnh Tr\u01b0ng \u0110\u00f4ng"},{"id":79,"text":"Ph\u01b0\u1eddng B\u00ecnh Tr\u01b0ng T\u00e2y"},{"id":83,"text":"Ph\u01b0\u1eddng Th\u1ea3o \u0110i\u1ec1n"},{"id":84,"text":"Ph\u01b0\u1eddng Th\u1ee7 Thi\u00eam"}],"8":[{"id":85,"text":"Ph\u01b0\u1eddng 01"},{"id":86,"text":"Ph\u01b0\u1eddng 02"},{"id":87,"text":"Ph\u01b0\u1eddng 03"},{"id":88,"text":"Ph\u01b0\u1eddng 04"},{"id":89,"text":"Ph\u01b0\u1eddng 05"},{"id":90,"text":"Ph\u01b0\u1eddng 06"},{"id":91,"text":"Ph\u01b0\u1eddng 07"},{"id":92,"text":"Ph\u01b0\u1eddng 08"},{"id":93,"text":"Ph\u01b0\u1eddng 09"},{"id":94,"text":"Ph\u01b0\u1eddng 10"},{"id":95,"text":"Ph\u01b0\u1eddng 11"},{"id":96,"text":"Ph\u01b0\u1eddng 12"},{"id":97,"text":"Ph\u01b0\u1eddng 13"},{"id":98,"text":"Ph\u01b0\u1eddng 14"},{"id":352,"text":"Ph\u01b0\u1eddng V\u00f5 Th\u1ecb S\u00e1u"}],"9":[{"id":99,"text":"Ph\u01b0\u1eddng 01"},{"id":100,"text":"Ph\u01b0\u1eddng 02"},{"id":101,"text":"Ph\u01b0\u1eddng 03"},{"id":102,"text":"Ph\u01b0\u1eddng 04"},{"id":103,"text":"Ph\u01b0\u1eddng 05"},{"id":104,"text":"Ph\u01b0\u1eddng 06"},{"id":105,"text":"Ph\u01b0\u1eddng 08"},{"id":106,"text":"Ph\u01b0\u1eddng 09"},{"id":107,"text":"Ph\u01b0\u1eddng 10"},{"id":108,"text":"Ph\u01b0\u1eddng 12"},{"id":109,"text":"Ph\u01b0\u1eddng 13"},{"id":110,"text":"Ph\u01b0\u1eddng 14"},{"id":111,"text":"Ph\u01b0\u1eddng 15"},{"id":112,"text":"Ph\u01b0\u1eddng 16"},{"id":113,"text":"Ph\u01b0\u1eddng 18"}],"10":[{"id":114,"text":"Ph\u01b0\u1eddng 01"},{"id":115,"text":"Ph\u01b0\u1eddng 02"},{"id":116,"text":"Ph\u01b0\u1eddng 03"},{"id":117,"text":"Ph\u01b0\u1eddng 04"},{"id":118,"text":"Ph\u01b0\u1eddng 05"},{"id":119,"text":"Ph\u01b0\u1eddng 06"},{"id":120,"text":"Ph\u01b0\u1eddng 07"},{"id":121,"text":"Ph\u01b0\u1eddng 08"},{"id":122,"text":"Ph\u01b0\u1eddng 09"},{"id":123,"text":"Ph\u01b0\u1eddng 10"},{"id":124,"text":"Ph\u01b0\u1eddng 11"},{"id":125,"text":"Ph\u01b0\u1eddng 12"},{"id":126,"text":"Ph\u01b0\u1eddng 13"},{"id":127,"text":"Ph\u01b0\u1eddng 14"},{"id":128,"text":"Ph\u01b0\u1eddng 15"}],"11":[{"id":129,"text":"Ph\u01b0\u1eddng 01"},{"id":130,"text":"Ph\u01b0\u1eddng 02"},{"id":131,"text":"Ph\u01b0\u1eddng 03"},{"id":132,"text":"Ph\u01b0\u1eddng 04"},{"id":133,"text":"Ph\u01b0\u1eddng 05"},{"id":134,"text":"Ph\u01b0\u1eddng 06"},{"id":135,"text":"Ph\u01b0\u1eddng 07"},{"id":136,"text":"Ph\u01b0\u1eddng 08"},{"id":137,"text":"Ph\u01b0\u1eddng 09"},{"id":138,"text":"Ph\u01b0\u1eddng 10"},{"id":139,"text":"Ph\u01b0\u1eddng 11"},{"id":140,"text":"Ph\u01b0\u1eddng 12"},{"id":141,"text":"Ph\u01b0\u1eddng 13"},{"id":142,"text":"Ph\u01b0\u1eddng 14"}],"12":[{"id":143,"text":"Ph\u01b0\u1eddng B\u00ecnh Thu\u1eadn"},{"id":144,"text":"Ph\u01b0\u1eddng Ph\u00fa M\u1ef9"},{"id":145,"text":"Ph\u01b0\u1eddng Ph\u00fa Thu\u1eadn"},{"id":146,"text":"Ph\u01b0\u1eddng T\u00e2n H\u01b0ng"},{"id":147,"text":"Ph\u01b0\u1eddng T\u00e2n Ki\u1ec3ng"},{"id":148,"text":"Ph\u01b0\u1eddng T\u00e2n Phong"},{"id":149,"text":"Ph\u01b0\u1eddng T\u00e2n Ph\u00fa"},{"id":150,"text":"Ph\u01b0\u1eddng T\u00e2n Quy"},{"id":152,"text":"Ph\u01b0\u1eddng T\u00e2n Thu\u1eadn \u0110\u00f4ng"},{"id":151,"text":"Ph\u01b0\u1eddng T\u00e2n Thu\u1eadn T\u00e2y"}],"13":[{"id":153,"text":"Ph\u01b0\u1eddng 01"},{"id":154,"text":"Ph\u01b0\u1eddng 02"},{"id":155,"text":"Ph\u01b0\u1eddng 03"},{"id":156,"text":"Ph\u01b0\u1eddng 04"},{"id":157,"text":"Ph\u01b0\u1eddng 05"},{"id":158,"text":"Ph\u01b0\u1eddng 06"},{"id":159,"text":"Ph\u01b0\u1eddng 07"},{"id":160,"text":"Ph\u01b0\u1eddng 08"},{"id":161,"text":"Ph\u01b0\u1eddng 09"},{"id":162,"text":"Ph\u01b0\u1eddng 10"},{"id":163,"text":"Ph\u01b0\u1eddng 11"},{"id":164,"text":"Ph\u01b0\u1eddng 12"},{"id":165,"text":"Ph\u01b0\u1eddng 13"},{"id":166,"text":"Ph\u01b0\u1eddng 14"}],"14":[{"id":169,"text":"Ph\u01b0\u1eddng Hi\u1ec7p Ph\u00fa"},{"id":170,"text":"Ph\u01b0\u1eddng Long B\u00ecnh"},{"id":171,"text":"Ph\u01b0\u1eddng Long Ph\u01b0\u1edbc"},{"id":172,"text":"Ph\u01b0\u1eddng Long Th\u1ea1nh M\u1ef9"},{"id":173,"text":"Ph\u01b0\u1eddng Long Tr\u01b0\u1eddng"},{"id":174,"text":"Ph\u01b0\u1eddng Ph\u00fa H\u1eefu"},{"id":175,"text":"Ph\u01b0\u1eddng Ph\u01b0\u1edbc B\u00ecnh"},{"id":176,"text":"Ph\u01b0\u1eddng Ph\u01b0\u1edbc Long A"},{"id":177,"text":"Ph\u01b0\u1eddng Ph\u01b0\u1edbc Long B"},{"id":178,"text":"Ph\u01b0\u1eddng T\u00e2n Ph\u00fa"},{"id":179,"text":"Ph\u01b0\u1eddng T\u0103ng Nh\u01a1n Ph\u00fa A"},{"id":180,"text":"Ph\u01b0\u1eddng T\u0103ng Nh\u01a1n Ph\u00fa B"},{"id":181,"text":"Ph\u01b0\u1eddng Tr\u01b0\u1eddng Th\u1ea1nh"}],"15":[{"id":182,"text":"Ph\u01b0\u1eddng 01"},{"id":183,"text":"Ph\u01b0\u1eddng 02"},{"id":184,"text":"Ph\u01b0\u1eddng 03"},{"id":185,"text":"Ph\u01b0\u1eddng 04"},{"id":186,"text":"Ph\u01b0\u1eddng 05"},{"id":187,"text":"Ph\u01b0\u1eddng 06"},{"id":188,"text":"Ph\u01b0\u1eddng 07"},{"id":189,"text":"Ph\u01b0\u1eddng 08"},{"id":190,"text":"Ph\u01b0\u1eddng 09"},{"id":191,"text":"Ph\u01b0\u1eddng 10"},{"id":192,"text":"Ph\u01b0\u1eddng 11"},{"id":193,"text":"Ph\u01b0\u1eddng 12"},{"id":194,"text":"Ph\u01b0\u1eddng 13"},{"id":195,"text":"Ph\u01b0\u1eddng 14"},{"id":196,"text":"Ph\u01b0\u1eddng 15"}],"16":[{"id":197,"text":"Ph\u01b0\u1eddng 01"},{"id":198,"text":"Ph\u01b0\u1eddng 02"},{"id":199,"text":"Ph\u01b0\u1eddng 03"},{"id":200,"text":"Ph\u01b0\u1eddng 04"},{"id":201,"text":"Ph\u01b0\u1eddng 05"},{"id":202,"text":"Ph\u01b0\u1eddng 06"},{"id":203,"text":"Ph\u01b0\u1eddng 07"},{"id":204,"text":"Ph\u01b0\u1eddng 08"},{"id":205,"text":"Ph\u01b0\u1eddng 09"},{"id":206,"text":"Ph\u01b0\u1eddng 10"},{"id":207,"text":"Ph\u01b0\u1eddng 11"},{"id":208,"text":"Ph\u01b0\u1eddng 12"},{"id":209,"text":"Ph\u01b0\u1eddng 13"},{"id":210,"text":"Ph\u01b0\u1eddng 14"},{"id":211,"text":"Ph\u01b0\u1eddng 15"},{"id":212,"text":"Ph\u01b0\u1eddng 16"}],"17":[{"id":213,"text":"Ph\u01b0\u1eddng An Ph\u00fa \u0110\u00f4ng"},{"id":223,"text":"Ph\u01b0\u1eddng \u0110\u00f4ng H\u01b0ng Thu\u1eadn"},{"id":214,"text":"Ph\u01b0\u1eddng Hi\u1ec7p Th\u00e0nh"},{"id":215,"text":"Ph\u01b0\u1eddng T\u00e2n Ch\u00e1nh Hi\u1ec7p"},{"id":216,"text":"Ph\u01b0\u1eddng T\u00e2n H\u01b0ng Thu\u1eadn"},{"id":217,"text":"Ph\u01b0\u1eddng T\u00e2n Th\u1edbi Hi\u1ec7p"},{"id":218,"text":"Ph\u01b0\u1eddng T\u00e2n Th\u1edbi Nh\u1ea5t"},{"id":219,"text":"Ph\u01b0\u1eddng Th\u1ea1nh L\u1ed9c"},{"id":220,"text":"Ph\u01b0\u1eddng Th\u1ea1nh Xu\u00e2n"},{"id":221,"text":"Ph\u01b0\u1eddng Th\u1edbi An"},{"id":222,"text":"Ph\u01b0\u1eddng Trung M\u1ef9 T\u00e2y"}],"18":[{"id":224,"text":"Ph\u01b0\u1eddng An L\u1ea1c"},{"id":225,"text":"Ph\u01b0\u1eddng An L\u1ea1c A"},{"id":227,"text":"Ph\u01b0\u1eddng B\u00ecnh H\u01b0ng Ho\u00e0 A"},{"id":229,"text":"Ph\u01b0\u1eddng B\u00ecnh Tr\u1ecb \u0110\u00f4ng"},{"id":231,"text":"Ph\u01b0\u1eddng B\u00ecnh Tr\u1ecb \u0110\u00f4ng B"},{"id":232,"text":"Ph\u01b0\u1eddng T\u00e2n T\u1ea1o"},{"id":233,"text":"Ph\u01b0\u1eddng T\u00e2n T\u1ea1o A"}],"19":[{"id":234,"text":"Ph\u01b0\u1eddng 01"},{"id":235,"text":"Ph\u01b0\u1eddng 02"},{"id":236,"text":"Ph\u01b0\u1eddng 03"},{"id":237,"text":"Ph\u01b0\u1eddng 05"},{"id":238,"text":"Ph\u01b0\u1eddng 06"},{"id":239,"text":"Ph\u01b0\u1eddng 07"},{"id":240,"text":"Ph\u01b0\u1eddng 11"},{"id":241,"text":"Ph\u01b0\u1eddng 12"},{"id":242,"text":"Ph\u01b0\u1eddng 13"},{"id":243,"text":"Ph\u01b0\u1eddng 14"},{"id":244,"text":"Ph\u01b0\u1eddng 15"},{"id":245,"text":"Ph\u01b0\u1eddng 17"},{"id":246,"text":"Ph\u01b0\u1eddng 19"},{"id":247,"text":"Ph\u01b0\u1eddng 21"},{"id":248,"text":"Ph\u01b0\u1eddng 22"},{"id":249,"text":"Ph\u01b0\u1eddng 24"},{"id":250,"text":"Ph\u01b0\u1eddng 25"},{"id":251,"text":"Ph\u01b0\u1eddng 26"},{"id":252,"text":"Ph\u01b0\u1eddng 27"},{"id":253,"text":"Ph\u01b0\u1eddng 28"}],"20":[{"id":254,"text":"Ph\u01b0\u1eddng 01"},{"id":255,"text":"Ph\u01b0\u1eddng 03"},{"id":256,"text":"Ph\u01b0\u1eddng 04"},{"id":257,"text":"Ph\u01b0\u1eddng 05"},{"id":258,"text":"Ph\u01b0\u1eddng 06"},{"id":259,"text":"Ph\u01b0\u1eddng 07"},{"id":260,"text":"Ph\u01b0\u1eddng 08"},{"id":261,"text":"Ph\u01b0\u1eddng 09"},{"id":262,"text":"Ph\u01b0\u1eddng 10"},{"id":263,"text":"Ph\u01b0\u1eddng 11"},{"id":264,"text":"Ph\u01b0\u1eddng 12"},{"id":265,"text":"Ph\u01b0\u1eddng 13"},{"id":266,"text":"Ph\u01b0\u1eddng 14"},{"id":267,"text":"Ph\u01b0\u1eddng 15"},{"id":268,"text":"Ph\u01b0\u1eddng 16"},{"id":269,"text":"Ph\u01b0\u1eddng 17"}],"21":[{"id":270,"text":"Ph\u01b0\u1eddng 01"},{"id":271,"text":"Ph\u01b0\u1eddng 02"},{"id":272,"text":"Ph\u01b0\u1eddng 03"},{"id":273,"text":"Ph\u01b0\u1eddng 04"},{"id":274,"text":"Ph\u01b0\u1eddng 05"},{"id":275,"text":"Ph\u01b0\u1eddng 07"},{"id":276,"text":"Ph\u01b0\u1eddng 08"},{"id":277,"text":"Ph\u01b0\u1eddng 09"},{"id":278,"text":"Ph\u01b0\u1eddng 10"},{"id":279,"text":"Ph\u01b0\u1eddng 11"},{"id":280,"text":"Ph\u01b0\u1eddng 12"},{"id":281,"text":"Ph\u01b0\u1eddng 13"},{"id":282,"text":"Ph\u01b0\u1eddng 14"},{"id":283,"text":"Ph\u01b0\u1eddng 15"},{"id":284,"text":"Ph\u01b0\u1eddng 17"}],"22":[{"id":285,"text":"Ph\u01b0\u1eddng 01"},{"id":286,"text":"Ph\u01b0\u1eddng 02"},{"id":287,"text":"Ph\u01b0\u1eddng 03"},{"id":288,"text":"Ph\u01b0\u1eddng 04"},{"id":289,"text":"Ph\u01b0\u1eddng 05"},{"id":290,"text":"Ph\u01b0\u1eddng 06"},{"id":291,"text":"Ph\u01b0\u1eddng 07"},{"id":292,"text":"Ph\u01b0\u1eddng 08"},{"id":293,"text":"Ph\u01b0\u1eddng 09"},{"id":294,"text":"Ph\u01b0\u1eddng 10"},{"id":295,"text":"Ph\u01b0\u1eddng 11"},{"id":296,"text":"Ph\u01b0\u1eddng 12"},{"id":297,"text":"Ph\u01b0\u1eddng 13"},{"id":298,"text":"Ph\u01b0\u1eddng 14"},{"id":299,"text":"Ph\u01b0\u1eddng 15"}],"23":[{"id":300,"text":"Ph\u01b0\u1eddng Hi\u1ec7p T\u00e2n"},{"id":301,"text":"Ph\u01b0\u1eddng H\u00f2a Th\u1ea1nh"},{"id":302,"text":"Ph\u01b0\u1eddng Ph\u00fa Th\u1ea1nh"},{"id":303,"text":"Ph\u01b0\u1eddng Ph\u00fa Th\u1ecd H\u00f2a"},{"id":304,"text":"Ph\u01b0\u1eddng Ph\u00fa Trung"},{"id":305,"text":"Ph\u01b0\u1eddng S\u01a1n K\u1ef3"},{"id":306,"text":"Ph\u01b0\u1eddng T\u00e2n Qu\u00fd"},{"id":307,"text":"Ph\u01b0\u1eddng T\u00e2n S\u01a1n Nh\u00ec"},{"id":308,"text":"Ph\u01b0\u1eddng T\u00e2n Th\u00e0nh"},{"id":309,"text":"Ph\u01b0\u1eddng T\u00e2n Th\u1edbi H\u00f2a"},{"id":310,"text":"Ph\u01b0\u1eddng T\u00e2y Th\u1ea1nh"}],"24":[{"id":311,"text":"Ph\u01b0\u1eddng B\u00ecnh Chi\u1ec3u"},{"id":312,"text":"Ph\u01b0\u1eddng B\u00ecnh Th\u1ecd"},{"id":313,"text":"Ph\u01b0\u1eddng Hi\u1ec7p B\u00ecnh Ch\u00e1nh"},{"id":314,"text":"Ph\u01b0\u1eddng Hi\u1ec7p B\u00ecnh Ph\u01b0\u1edbc"},{"id":315,"text":"Ph\u01b0\u1eddng Linh Chi\u1ec3u"},{"id":319,"text":"Ph\u01b0\u1eddng Linh \u0110\u00f4ng"},{"id":316,"text":"Ph\u01b0\u1eddng Linh T\u00e2y"},{"id":317,"text":"Ph\u01b0\u1eddng Linh Trung"},{"id":318,"text":"Ph\u01b0\u1eddng Linh Xu\u00e2n"},{"id":320,"text":"Ph\u01b0\u1eddng Tam B\u00ecnh"},{"id":321,"text":"Ph\u01b0\u1eddng Tam Ph\u00fa"},{"id":322,"text":"Ph\u01b0\u1eddng Tr\u01b0\u1eddng Th\u1ecd"}],"25":[{"id":323,"text":"T\u00e2n Ki\u1ec3ng"}],"26":[{"id":324,"text":"Ph\u01b0\u1eddng Ph\u01b0\u1edbc Ki\u1ec3n"}],"28":[{"id":327,"text":"Ph\u01b0\u1eddng B\u00ccnh Th\u1ecd"},{"id":330,"text":"Ph\u01b0\u1eddng Hi\u1ec7p B\u00ecnh Ch\u00e1nh"},{"id":331,"text":"Ph\u01b0\u1eddng Hi\u1ec7p B\u00ecnh Ph\u01b0\u1edbc"},{"id":332,"text":"Ph\u01b0\u1eddng Linh Chi\u1ec3u"},{"id":325,"text":"Ph\u01b0\u1eddng Linh \u0110\u00f4ng"},{"id":326,"text":"Ph\u01b0\u1eddng Linh T\u00e2y"},{"id":329,"text":"Ph\u01b0\u1eddng Tam Ph\u00fa"},{"id":328,"text":"Ph\u01b0\u1eddng Tr\u01b0\u1eddng Th\u1ecd"}],"29":[{"id":333,"text":"ph\u01b0\u1eddng B\u00ecnh Th\u1ecd"},{"id":334,"text":"Ph\u01b0\u1eddng Hi\u1ec7p B\u00ecnh Ch\u00e1nh"},{"id":335,"text":"Ph\u01b0\u1eddng Hi\u1ec7p B\u00ecnh Ph\u01b0\u1edbc"},{"id":338,"text":"Ph\u01b0\u1eddng Linh Chi\u1ec3u"},{"id":336,"text":"Ph\u01b0\u1eddng Linh \u0110\u00f4ng"},{"id":337,"text":"Ph\u01b0\u1eddng Linh T\u00e2y"},{"id":340,"text":"Ph\u01b0\u1eddng Tam Ph\u00fa"},{"id":339,"text":"Ph\u01b0\u1eddng Tr\u01b0\u1eddng Th\u1ecd"}],"30":[{"id":347,"text":"Ph\u01b0\u1eddng Hi\u1ec7p Ph\u00fa"},{"id":344,"text":"Ph\u01b0\u1eddng Ph\u01b0\u1edbc B\u00ecnh"},{"id":342,"text":"Ph\u01b0\u1eddng Ph\u01b0\u1edbc Long A"},{"id":343,"text":"Ph\u01b0\u1eddng Ph\u01b0\u1edbc Long B"},{"id":345,"text":"Ph\u01b0\u1eddng T\u0103ng Nh\u01a1n Ph\u00fa A"},{"id":346,"text":"Ph\u01b0\u1eddng T\u0103ng Nh\u01a1n Ph\u00fa B"}],"31":[{"id":348,"text":"Th\u1ea1nh M\u1ef9 L\u1ee3i"}],"32":[{"id":349,"text":"ph\u01b0\u1eddng Ph\u00fa M\u1ef9"}],"33":[{"id":351,"text":"Ph\u01b0\u1eddng 15"},{"id":350,"text":"Ph\u01b0\u1eddng 16"}]}</textarea>
        <textarea id="data_guest_info">[]</textarea>
    </div>
</div>
<?php endif; /* end of address state if/elseif/else */ ?>
<div class="box-payment"></div>
                                                <div class="box-product my-4">
    <div class="box-title d-none d-md-block mb-4">
        <div class="row">
            <div class="col-6 name">Tên sản phẩm</div>
            <div class="col-2">Giá</div>
                        <div class="col-2">Số lượng</div>
            <div class="col-2 price">Tổng đơn hàng</div>
                    </div>
    </div>
    <div class="box-content">
        <div class="list-items"></div>
        <div class="total d-flex justify-content-between d-md-block text-right">
            <span>Tạm tính:</span>
            <span class="text-price"><strong>0</strong>đ</span>
        </div>
    </div>
</div>
<div class="box-payment-info">
        <input type="hidden" id="url-discount" value="https://fitfood.vn/payment/discount">
    <div class="row">
        <div class="col-md-6 payment-method">
            <div class="payment">
                
                <div class="form-group row">
                    <label for="" class="col-md-5">Thời gian giao hàng <span class="req">*</span></label>
                    <div class="col-md-7">
                        <select class="form-control" id="delivery_time" name="delivery_time" data-empty="Thời gian giao hàng">
                                                    </select>
                    </div>
                </div>
                <div class="form-group row">
                    <label for="" class="col-md-5">Phương thức thanh toán</label>
                    <div class="col-md-7">
                        <select class="form-control" name="pay_method" id="pay_method">
                                                            <option value="api_ocb">OCB - Thanh toán QR</option>
                                                            <option value="api_acb">ACB - Thanh toán QR</option>
                                                            <option value="CASH">Thanh toán tiền mặt</option>
                                                    </select>
                    </div>
                </div>
                                <div class="form-group mt-md-4">
                    <label for="">Note</label>
                    <textarea class="form-control" name="note_order" placeholder="Ghi chú về các thành phần bị dị ứng (nếu có)"></textarea>
                </div>
                
                <div class="form-group mt-md-4">
                    <label for="">Note</label>
                    <textarea class="form-control" name="note" placeholder="Ghi chú về địa chỉ giao hàng (block chung cư, tên tòa nhà văn phòng...)"></textarea>
                </div>
            </div>
        </div>
        <div class="col-md-6 offset-lg-2 col-lg-4 mb-3 payment-summary">
                        <div class="row mb-md-2">
                <div class="col-6 col-md-7">Phí ship</div>
                <div id="cart-ship_fee" class="col-6 col-md-5 text-right price "><span>0</span>đ</div>
            </div>
            <div id="cart-discount" class="row mb-md-2" style="display: none">
                <div class="col-6 col-md-7">Giảm giá</div>
                <div class="col-6 col-md-5 text-right price text-price"><span>0</span>đ</div>
            </div>
                        <div id="wrap-fee" class="row mb-md-2" style="display: none">
                <div class="col-6 col-md-7">Phí thanh toán</div>
                <div class="col-6 col-md-5 text-right price"><span></span>đ</div>
            </div>
            <div id="wrap-total-final" class="row total" data-url="https://fitfood.vn/payment/summary">
                <div class="col-6 col-md-7">Tổng đơn hàng:</div>
                <div id="cart-total_bill" class="col-6 col-md-5 text-right text-price"><strong><span>0</span></strong>đ</div>
            </div>
            <input type="hidden" id="p-total-final" value="0">
            <textarea id="p-pay-method" style="display: none">{"api_ocb":0,"api_acb":0,"CASH":0}</textarea>
        </div>
        <textarea id="d-times" style="display: none">{"31":[{"id":1,"text":"8:00 - 8:30am"},{"id":4,"text":"9:30 - 10:00am"},{"id":3,"text":"T\u1ed1i h\u00f4m tr\u01b0\u1edbc ( 19:00 - 22:00 ) "}],"15":[{"id":1,"text":"8:00 - 8:30am"},{"id":2,"text":"8:30 - 9:00am"},{"id":7,"text":"9:00 - 9:30am"},{"id":4,"text":"9:30 - 10:00am"},{"id":8,"text":"10:00 - 10:30am"},{"id":6,"text":"10:30 - 11:00am"},{"id":3,"text":"T\u1ed1i h\u00f4m tr\u01b0\u1edbc ( 19:00 - 22:00 ) "}],"16":[{"id":1,"text":"8:00 - 8:30am"},{"id":2,"text":"8:30 - 9:00am"},{"id":7,"text":"9:00 - 9:30am"},{"id":4,"text":"9:30 - 10:00am"},{"id":8,"text":"10:00 - 10:30am"},{"id":6,"text":"10:30 - 11:00am"},{"id":3,"text":"T\u1ed1i h\u00f4m tr\u01b0\u1edbc ( 19:00 - 22:00 ) "}],"1":[{"id":1,"text":"8:00 - 8:30am"},{"id":8,"text":"10:00 - 10:30am"},{"id":3,"text":"T\u1ed1i h\u00f4m tr\u01b0\u1edbc ( 19:00 - 22:00 ) "}],"12":[{"id":1,"text":"8:00 - 8:30am"},{"id":2,"text":"8:30 - 9:00am"},{"id":7,"text":"9:00 - 9:30am"},{"id":4,"text":"9:30 - 10:00am"},{"id":8,"text":"10:00 - 10:30am"},{"id":6,"text":"10:30 - 11:00am"},{"id":3,"text":"T\u1ed1i h\u00f4m tr\u01b0\u1edbc ( 19:00 - 22:00 ) "}],"8":[{"id":1,"text":"8:00 - 8:30am"},{"id":2,"text":"8:30 - 9:00am"},{"id":7,"text":"9:00 - 9:30am"},{"id":4,"text":"9:30 - 10:00am"},{"id":8,"text":"10:00 - 10:30am"},{"id":6,"text":"10:30 - 11:00am"},{"id":3,"text":"T\u1ed1i h\u00f4m tr\u01b0\u1edbc ( 19:00 - 22:00 ) "}],"9":[{"id":1,"text":"8:00 - 8:30am"},{"id":2,"text":"8:30 - 9:00am"},{"id":7,"text":"9:00 - 9:30am"},{"id":4,"text":"9:30 - 10:00am"},{"id":8,"text":"10:00 - 10:30am"},{"id":6,"text":"10:30 - 11:00am"},{"id":3,"text":"T\u1ed1i h\u00f4m tr\u01b0\u1edbc ( 19:00 - 22:00 ) "}],"21":[{"id":1,"text":"8:00 - 8:30am"},{"id":2,"text":"8:30 - 9:00am"},{"id":7,"text":"9:00 - 9:30am"},{"id":4,"text":"9:30 - 10:00am"},{"id":8,"text":"10:00 - 10:30am"},{"id":6,"text":"10:30 - 11:00am"},{"id":3,"text":"T\u1ed1i h\u00f4m tr\u01b0\u1edbc ( 19:00 - 22:00 ) "}],"10":[{"id":1,"text":"8:00 - 8:30am"},{"id":2,"text":"8:30 - 9:00am"},{"id":7,"text":"9:00 - 9:30am"},{"id":4,"text":"9:30 - 10:00am"},{"id":8,"text":"10:00 - 10:30am"},{"id":6,"text":"10:30 - 11:00am"},{"id":3,"text":"T\u1ed1i h\u00f4m tr\u01b0\u1edbc ( 19:00 - 22:00 ) "}],"6":[{"id":1,"text":"8:00 - 8:30am"},{"id":2,"text":"8:30 - 9:00am"},{"id":7,"text":"9:00 - 9:30am"},{"id":4,"text":"9:30 - 10:00am"},{"id":8,"text":"10:00 - 10:30am"},{"id":6,"text":"10:30 - 11:00am"},{"id":3,"text":"T\u1ed1i h\u00f4m tr\u01b0\u1edbc ( 19:00 - 22:00 ) "}],"26":[{"id":1,"text":"8:00 - 8:30am"},{"id":6,"text":"10:30 - 11:00am"},{"id":3,"text":"T\u1ed1i h\u00f4m tr\u01b0\u1edbc ( 19:00 - 22:00 ) "}],"18":[{"id":2,"text":"8:30 - 9:00am"},{"id":6,"text":"10:30 - 11:00am"},{"id":3,"text":"T\u1ed1i h\u00f4m tr\u01b0\u1edbc ( 19:00 - 22:00 ) "}],"23":[{"id":2,"text":"8:30 - 9:00am"},{"id":7,"text":"9:00 - 9:30am"},{"id":4,"text":"9:30 - 10:00am"},{"id":8,"text":"10:00 - 10:30am"},{"id":6,"text":"10:30 - 11:00am"},{"id":3,"text":"T\u1ed1i h\u00f4m tr\u01b0\u1edbc ( 19:00 - 22:00 ) "}],"13":[{"id":2,"text":"8:30 - 9:00am"},{"id":7,"text":"9:00 - 9:30am"},{"id":4,"text":"9:30 - 10:00am"},{"id":8,"text":"10:00 - 10:30am"},{"id":6,"text":"10:30 - 11:00am"},{"id":3,"text":"T\u1ed1i h\u00f4m tr\u01b0\u1edbc ( 19:00 - 22:00 ) "}],"19":[{"id":2,"text":"8:30 - 9:00am"},{"id":7,"text":"9:00 - 9:30am"},{"id":4,"text":"9:30 - 10:00am"},{"id":8,"text":"10:00 - 10:30am"},{"id":6,"text":"10:30 - 11:00am"},{"id":3,"text":"T\u1ed1i h\u00f4m tr\u01b0\u1edbc ( 19:00 - 22:00 ) "}],"22":[{"id":2,"text":"8:30 - 9:00am"},{"id":7,"text":"9:00 - 9:30am"},{"id":4,"text":"9:30 - 10:00am"},{"id":8,"text":"10:00 - 10:30am"},{"id":6,"text":"10:30 - 11:00am"},{"id":3,"text":"T\u1ed1i h\u00f4m tr\u01b0\u1edbc ( 19:00 - 22:00 ) "}],"11":[{"id":2,"text":"8:30 - 9:00am"},{"id":7,"text":"9:00 - 9:30am"},{"id":4,"text":"9:30 - 10:00am"},{"id":3,"text":"T\u1ed1i h\u00f4m tr\u01b0\u1edbc ( 19:00 - 22:00 ) "}],"32":[{"id":7,"text":"9:00 - 9:30am"},{"id":4,"text":"9:30 - 10:00am"},{"id":8,"text":"10:00 - 10:30am"},{"id":6,"text":"10:30 - 11:00am"},{"id":3,"text":"T\u1ed1i h\u00f4m tr\u01b0\u1edbc ( 19:00 - 22:00 ) "}],"7":[{"id":7,"text":"9:00 - 9:30am"},{"id":4,"text":"9:30 - 10:00am"},{"id":8,"text":"10:00 - 10:30am"},{"id":3,"text":"T\u1ed1i h\u00f4m tr\u01b0\u1edbc ( 19:00 - 22:00 ) "}],"20":[{"id":3,"text":"T\u1ed1i h\u00f4m tr\u01b0\u1edbc ( 19:00 - 22:00 ) "}],"29":[{"id":3,"text":"T\u1ed1i h\u00f4m tr\u01b0\u1edbc ( 19:00 - 22:00 ) "}],"27":[{"id":3,"text":"T\u1ed1i h\u00f4m tr\u01b0\u1edbc ( 19:00 - 22:00 ) "}],"33":[{"id":3,"text":"T\u1ed1i h\u00f4m tr\u01b0\u1edbc ( 19:00 - 22:00 ) "}],"30":[{"id":3,"text":"T\u1ed1i h\u00f4m tr\u01b0\u1edbc ( 19:00 - 22:00 ) "}],"28":[{"id":3,"text":"T\u1ed1i h\u00f4m tr\u01b0\u1edbc ( 19:00 - 22:00 ) "}],"25":[{"id":3,"text":"T\u1ed1i h\u00f4m tr\u01b0\u1edbc ( 19:00 - 22:00 ) "}]}</textarea>
    </div>
</div>
<div class="box-payment">
    <div class="row align-items-center justify-content-between">
        <div class="col-6 col-md-3 col-lg-2 order-md-1">
            <a href="index.php" class="btn btn-outline-dark btn-block">Trở về</a>
        </div>
        <div class="col-6 col-md-4 col-lg-3 order-md-3 text-right">
            <span id="cart-error" style="display: none;"></span>
            <span id="cart-validation" data-delivery="Vui lòng chọn thời gian giao hàng"
                data-address="Chưa nhập địa chỉ giao hàng"></span>
            <button type="button" id="btn-place-order" class="btn btn-primary btn-block submit-payment submit">Hoàn thành</button>
        </div>
    </div>
</div>

<script type="text/template" id="temp-discount-input">
    <input class="form-control" id="discount_code" name="discount_code" placeholder="Nhập mã">
    <button type="button" id="discount-apply" class="btn btn-primary btn-block">Áp dụng</button>
</script>
<script type="text/template" id="temp-discount-added">
    <input class="form-control disabled" disabled value="<%- code %>">
    <button type="button" id="discount-remove" class="btn btn-primary btn-block">Xóa</button>
</script>
            </form>
            <form id="voucher-form" action="#" method="post">
    <div class="modal fade modal-voucher" id="modal-voucher" tabindex="-1" role="dialog" aria-labelledby="modal-voucher">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="m-top"></div>
            <div class="modal-content">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <div class="modal-body voucher-tickets">

                    <h3>Chọn Fitfood Voucher</h3>
                    <div class="voucher-code">
                        <div class="voucher-code-input">
                            <input class="form-control" id="voucher_code" name="voucher_code" placeholder="Nhập mã voucher">
                            <button type="button" id="voucher-code-apply" class="btn btn-primary" disabled>Áp dụng</button>
                        </div>
                    </div>
                    <div id="voucher-list" class="voucher-list"></div>
                    <div class="voucher-confirm">
                        <button type="button" id="voucher-confirm" class="btn btn-primary">Xác nhận</button>
                    </div>
                </div>
            </div>
            <div class="m-bottom"></div>
        </div>
    </div>
</form>
<div class="modal fade modal-voucher modal-voucher-detail" id="modal-voucher-detail" tabindex="-1" role="dialog" aria-labelledby="modal-voucher">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <button type="button" class="close" data-dismiss="modal">&times;</button>
            <div class="modal-body">
                <h3>Chi tiết voucher</h3>
                <div class="voucher-detail">
                </div>
                <div class="voucher-confirm" style="text-align: center">
                    <button type="button" id="voucher-close" class="btn btn-primary" data-dismiss="modal">Xác nhận</button>
                </div>
            </div>
        </div>
    </div>
</div>
<input type="hidden" id="url-voucher" value="https://fitfood.vn/payment/vouchers">
<input type="hidden" id="url-voucher-add" value="https://fitfood.vn/payment/add-voucher">
<input type="hidden" id="url-voucher-detail" value="https://fitfood.vn/payment/voucher-detail">
                    </div>
    </section>
</main>
<footer class="footer-main">
    <div class="container">
        <a href="/" class="mb-4 d-block">
            <img src="/images/logo-healthyfood.png" alt="fitfoodvn" border="0" />
        </a>
        <div class="widget-footer mb-4">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <h4>Công ty TNHH Fitfood</h4>
                    <p>
                        <strong>Địa chỉ</strong> 33 Đường 14, KDC Bình Hưng, Ấp 2, Huyện Bình Chánh, TPHCM<br/>
                        <strong>Điện thoại</strong> (+84) 932 788 120 [hotline]<br/>
                        <strong>Email</strong> info@fitfood.vn. For business inquiries: business@fitfood.vn<br/>
                        <strong>MST</strong> 0313272749 do Sở kế hoạch và đầu tư TPHCM cấp ngày 26/05/2015
                    </p>

                    <div class="row">
    <div class="col-12 form-group">
        <form action="https://fitfood.vn/search" class="form-search">
            <div class="search-control s-home">
                <input type="text" class="form-control s-search" name="s" placeholder="Tìm kiếm" value="">
            </div>
        </form>
    </div>
</div>
                </div>
                <div class="col-md-3 mb-3">
                    <h4>Điều khoản chung</h4>
                    <ul>
                                            <li><a href="https://fitfood.vn/page/chinh-sach-quy-dinh-chung">Chính Sách Quy Định Chung</a></li>
                                            <li><a href="https://fitfood.vn/page/quy-dinh-hinh-thuc-thanh-toan">Quy Định Hình Thức Thanh Toán</a></li>
                                            <li><a href="https://fitfood.vn/page/chinh-sach-van-chuyen-giao-hang">Chính Sách Vận Chuyển Giao Hàng</a></li>
                                            <li><a href="https://fitfood.vn/page/chinh-sach-bao-mat-thong-tin">Chính Sách Bảo Mật Thông Tin</a></li>
                                        </ul>
                </div>
                <div class="col-md-3 mb-3">
                    <h4>Theo dõi chúng tôi tại</h4>
                    <div class="social mb-3">
                        <a href="https://www.facebook.com/fitfoodvietnam" target="_blank">
                            <img src="/images/ic-fb.png" alt="fitfoodvietnam" border="0"/>
                        </a>
                        <a href="https://www.instagram.com/fitfoodvn" target="_blank">
                            <img src="/images/ic-instagram.png" alt="fitfoodvn" border="0"/>
                        </a>
                        <a href="https://www.youtube.com/watch?v=CJ6eTsFdd1I" target="_blank">
                            <img src="/images/ic-youtube.png" alt="fitfoodvn" border="0"/>
                        </a>
                    </div>
                    <div>
                        <a href="http://online.gov.vn/Home/WebDetails/34281" target="_blank">
                            <img src="/images/logo-bocongthuong.png?t=123" alt="fitfoodvn" border="0" width="120px"/>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <p class="copyright mb-0">© Copyright 2026 Fitfood. All rights reserved.</p>
    </div>
</footer>
<div style="display: none" id="ads-popup" data-url="https://fitfood.vn/promotion-utm"></div>

<!-- ===== POPUP ĐĂNG KÝ & ĐĂNG NHẬP ===== -->
<?php require_once __DIR__ . '/includes/register_modal.php'; ?>
<?php require_once __DIR__ . '/includes/login_modal.php'; ?>
<script src="assets/js/register.js"></script>
<script src="assets/js/login.js"></script>

<div class="modal fade" id="message-modal" tabindex="-1" role="dialog" aria-labelledby="" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-body">
                <p id="title"></p>
                <p></p>
            </div>
        </div>
    </div>
</div>

<?php if ($user_id && !empty($all_addresses)): ?>
<!-- Modal chọn địa chỉ giao hàng (mở khi bấm "Đổi" ở card địa chỉ) -->
<div class="modal fade" id="selectAddressModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Chọn địa chỉ giao hàng</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span>&times;</span></button>
      </div>
      <div class="modal-body" style="max-height:60vh;overflow-y:auto;">
        <?php foreach ($all_addresses as $a): ?>
          <label class="d-block p-3 mb-2 address-option"
                 style="border:1px solid #e5e5e5;border-radius:6px;cursor:pointer;">
            <div class="d-flex align-items-start">
              <input type="radio" name="select_address_id"
                     value="<?= (int)$a['id'] ?>"
                     data-name="<?= htmlspecialchars($a['recipient_name']) ?>"
                     data-phone="<?= htmlspecialchars($a['phone']) ?>"
                     data-address="<?= htmlspecialchars($a['address']) ?>"
                     data-default="<?= (int)$a['is_default'] ?>"
                     class="mt-1 mr-3"
                     <?= (int)$a['id'] === (int)$default_address['id'] ? 'checked' : '' ?>>
              <div style="flex:1;">
                <div>
                  <strong><?= htmlspecialchars($a['recipient_name']) ?></strong>
                  · <?= htmlspecialchars($a['phone']) ?>
                  <?php if ((int)$a['is_default'] === 1): ?>
                    <span class="ml-2" style="background:#dcdcdc;color:#555;padding:2px 12px;border-radius:20px;font-size:12px;">Default</span>
                  <?php endif; ?>
                </div>
                <p class="mb-0 mt-1 text-muted small"><?= htmlspecialchars($a['address']) ?></p>
              </div>
            </div>
          </label>
        <?php endforeach; ?>
        <a href="address.php" class="btn btn-link mt-2 px-0">
          <i class="fa fa-plus mr-1"></i>Thêm địa chỉ mới (mở trang quản lý)
        </a>
      </div>
      <div class="modal-footer">
        <button type="button" id="btnConfirmAddress" class="btn btn-primary">Xác nhận</button>
        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Huỷ</button>
      </div>
    </div>
  </div>
</div>

<script>
// JS: chọn địa chỉ trong modal → cập nhật card hiển thị + hidden inputs
(function () {
  var btn = document.getElementById('btnConfirmAddress');
  if (!btn) return;
  btn.addEventListener('click', function () {
    var checked = document.querySelector('input[name="select_address_id"]:checked');
    if (!checked) return;

    // Cập nhật hidden inputs (gửi lên server khi đặt đơn)
    var setVal = function (id, v) {
      var el = document.getElementById(id);
      if (el) el.value = v;
    };
    setVal('addr_id',      checked.value);
    setVal('addr_name_h',  checked.dataset.name);
    setVal('addr_phone_h', checked.dataset.phone);
    setVal('addr_addr_h',  checked.dataset.address);

    // Cập nhật text hiển thị
    var setText = function (sel, t) {
      var el = document.querySelector(sel);
      if (el) el.textContent = t;
    };
    setText('.addr-name',  checked.dataset.name);
    setText('.addr-phone', checked.dataset.phone);
    setText('.addr-text',  checked.dataset.address);

    // Toggle "Default" badge
    var badge = document.getElementById('addr-default-badge');
    if (badge) badge.style.display = checked.dataset.default === '1' ? 'inline-block' : 'none';

    // Đóng modal (Bootstrap 4 dùng jQuery)
    if (window.jQuery) {
      window.jQuery('#selectAddressModal').modal('hide');
    } else {
      // Fallback: bỏ class show + backdrop
      document.getElementById('selectAddressModal').classList.remove('show');
      document.body.classList.remove('modal-open');
      var bd = document.querySelector('.modal-backdrop');
      if (bd) bd.parentNode.removeChild(bd);
    }
  });
})();
</script>
<?php endif; ?>



        <div class="modal fade modal-popup-sale" id="modal-product" tabindex="-1" role="dialog" aria-labelledby="" aria-hidden="true" data-backdrop="static">
        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <p>Ưu đãi cho bạn</p>
                    Chúc mừng bạn, bạn được mua kèm một sản phẩm bên dưới với giá ưu đãi. Click vào sản phẩm để thêm vào giỏ hàng bạn nhé!
                </div>
                <div class="modal-body">
                    <div class="products">
<?php
require_once __DIR__ . '/lib/products.php';
if ($pdo) render_upsell_items($pdo, 4);
?>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="https://fitfood.vn/payment" class="btn btn-primary btn-block float-right">Tôi không muốn</a>
                </div>
            </div>
        </div>
    </div>

<script src="https://code.jquery.com/jquery-2.2.4.min.js" integrity="sha256-BbhdlvQf/xTY9gja0Dq3HiwQF8LaCRTXxZKRutelT44=" crossorigin="anonymous"></script>
<script src="https://fitfood.vn/js/bootstrap.min.js?v=2026033101"></script>
<script src="https://fitfood.vn/js/init.js?v=2026033101"></script>

<script src="https://fitfood.vn/js/plugins/select2/select2.min.js?v=2026033101"></script>
<script src="https://fitfood.vn/js/plugins/jquery.validation/jquery.validate.min.js?v=2026033101"></script>
<script src="https://fitfood.vn/js/plugins/bootbox/bootbox.min.js?v=2026033101"></script>
<script src="https://fitfood.vn/js/plugins/bootbox/bootbox.locales.js?v=2026033101"></script>
<script src="https://fitfood.vn/js/plugins/jquery.parseParams.js?v=2026033101"></script>
<script src="https://fitfood.vn/js/underscore-min.js?v=2026033101"></script>
<script src="https://fitfood.vn/js/plugins.js?v=2026033101"></script>
<script src="https://fitfood.vn/js/modules/tracking.js"></script>
    <script type="text/javascript" src="https://fitfood.vn/js/modules/payment/payment.js?v=2026033101"></script>
    <script type="text/javascript" src="https://fitfood.vn/js/modules/order/cart-order.js?v=2026033101"></script>
        <script type="text/javascript">
        var promoPopup = '#ads-popup'
        function copyCode()
        {
            navigator.clipboard.writeText($(promoPopup).data('code'));
        }

        function setPosPromoCode()
        {
            const _h = 680;
            const _b = 120;
            const height = $('.promo-adv .mfp-content').height();
            $('.promo-adv .mfp-bottom-bar').css('bottom', ((height * _b / _h) + 5) + 'px');
        }

        function showPromotionPopup()
        {
            ajax.get($(promoPopup).data('url'), {name_promo: ''}, function (res) {
                if (res.data.code != '') {
                    $(promoPopup).data('code', res.data.code);
                    $.magnificPopup.open({
                        items: {
                            src: res.data.url
                        },
                        type: 'image',
                        mainClass: 'promo-adv',
                        title: res.data.code,
                        image: {
                            titleSrc: function (item) {
                                return "<span class='pro-code'>Code : "+res.data.code+" <span class='pro-cp' data-code='"+res.data.code+"' onclick='copyCode()'><img src='https://fitfood.vn/images/ico-copy-code.svg' width='24'/></span></span>";
                            }
                        },
                        callbacks: {
                            imageLoadComplete: function () {
                                setPosPromoCode();
                            },
                            resize: function() {
                                setPosPromoCode();
                            }
                        }
                    });

                }
            })
        }

        $(function () {
            setTimeout(function () {
                showPromotionPopup();

            }, 3000)


        });
    </script>


<input type="hidden" id="url-add-cart" value="https://fitfood.vn/cart/package/add"/>
<div class="widget-social">
    <span class="widget-ico widget-btn-social"><a href="javascript:void(0)"><img src="https://fitfood.vn/images/ico-chat-bubble.svg?v=2026033101" alt="Fitfood.vn"></a></span>
    <div class="widget-btn-list">
        <div class="widget-btn widget-btn-facebook">
            <span class="widget-ico"><a target="_blank" href="https://www.messenger.com/t/765002226901320"><img src="https://fitfood.vn/images/ico-fb-messenger.svg?v=2026033101" alt="Fitfood.vn"></a></span>
        </div>
        <div class="widget-btn widget-btn-zalo">
            <span class="widget-ico"><a target="_blank" href="https://zalo.me/2248754524670622528"><img src="https://fitfood.vn/images/ico-zalo.svg?v=2026033101" alt="Fitfood.vn"></a></span>
        </div>
    </div>
</div>

<script>
(function () {
    var STORAGE_KEY = 'fitfood_cart_v1';

    function readCart() {
        try {
            var raw = localStorage.getItem(STORAGE_KEY);
            return raw ? JSON.parse(raw) : [];
        } catch (e) { return []; }
    }
    function writeCart(items) {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(items));
    }
    var LEGACY_PACKAGE_IDS = {
        fit3: 1,
        full: 2,
        fit1: 3,
        meat: 4,
        slim: 5,
        'lunch-eatclean': 6,
        lunch: 6
    };
    var PACKAGE_VARIANT_PRICES = {
        1: { week: 650000, month: 2340000 },
        2: { week: 825000, month: 2970000 },
        3: { week: 650000, month: 2340000 },
        4: { week: 950000, month: 3420000 },
        5: { week: 600000, month: 2160000 },
        6: { week: 349000 }
    };
    function normalizeId(id) {
        var key = String(id == null ? '' : id).trim();
        if (LEGACY_PACKAGE_IDS[key]) return LEGACY_PACKAGE_IDS[key];
        var parsed = parseInt(key, 10);
        return parsed > 0 ? parsed : '';
    }
    function normalizeType(type, name) {
        var rawType = String(type || '').trim();
        var rawName = String(name || '').toLowerCase();
        if (rawType === 'month' || rawName.indexOf('gói tháng') !== -1) return 'month';
        if (rawType === 'week' || rawName.indexOf('gói tuần') !== -1) return 'week';
        return rawType;
    }
    function normalizePrice(id, type, price) {
        var prices = PACKAGE_VARIANT_PRICES[id];
        return prices && prices[type] ? prices[type] : price;
    }
    function normalizeCart(items) {
        var grouped = {};
        (Array.isArray(items) ? items : []).forEach(function (raw) {
            var it = raw || {};
            var id = normalizeId(it.id);
            if (!id) return;
            var name = String(it.name || '').trim();
            var type = normalizeType(it.type, name);
            var price = normalizePrice(id, type, Number(it.price) || 0);
            var image = String(it.image || '').trim();
            var hasStock = it.stock !== undefined && it.stock !== null && it.stock !== '';
            var stock = hasStock ? Math.max(0, Number(it.stock) || 0) : null;
            if (stock === 0) return;
            var quantity = Math.max(1, Number(it.quantity) || 1);
            if (stock !== null && quantity > stock) quantity = stock;
            var key = [id, type, price, name].join('|');
            if (!grouped[key]) {
                grouped[key] = {
                    id: id,
                    type: type,
                    name: name,
                    image: image,
                    price: price,
                    calo: Number(it.calo) || 0,
                    stock: stock,
                    quantity: 0
                };
            }
            grouped[key].quantity += quantity;
            if (grouped[key].stock !== null && grouped[key].quantity > grouped[key].stock) {
                grouped[key].quantity = grouped[key].stock;
            }
            if (!grouped[key].image && image) grouped[key].image = image;
            if (grouped[key].stock === null && stock !== null) grouped[key].stock = stock;
        });
        return Object.keys(grouped).map(function (k) { return grouped[k]; });
    }
    function formatVN(n) {
        return (Number(n) || 0).toLocaleString('vi-VN');
    }
    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function renderItems() {
        var originalItems = readCart();
        var items = normalizeCart(originalItems);
        if (JSON.stringify(items) !== JSON.stringify(originalItems)) {
            writeCart(items);
        }
        var listEl = document.querySelector('.box-product .list-items');
        if (!listEl) return;

        if (items.length === 0) {
            listEl.innerHTML = '<div class="item"><div class="row"><div class="col-12 text-center py-4" style="color:#999">Giỏ hàng trống</div></div></div>';
        } else {
            listEl.innerHTML = items.map(function (it) {
                var price = Number(it.price) || 0;
                var qty = Math.max(1, Number(it.quantity) || 1);
                var lineTotal = price * qty;
                return '' +
                    '<div class="item">' +
                        '<div class="row d-flex">' +
                            '<div class="col-3 col-md-6 name d-flex">' +
                                '<img src="' + escapeHtml(it.image) + '" class="img-fluid">' +
                                '<span class="d-none d-md-block">' + escapeHtml(it.name) + '</span>' +
                            '</div>' +
                            '<div class="col-9 col-md-6">' +
                                '<div class="row">' +
                                    '<div class="col-6 d-md-none px-0">' + escapeHtml(it.name) + '</div>' +
                                    '<div class="col-6 col-md-4 text-right">' + formatVN(price) + '<u>đ</u></div>' +
                                    '<div class="col-6 col-md-4 text-left text-md-center px-0"><span class="d-md-none">x</span>' + qty + '</div>' +
                                    '<div class="col-6 col-md-4 price text-right">' + formatVN(lineTotal) + 'đ</div>' +
                                '</div>' +
                            '</div>' +
                        '</div>' +
                    '</div>';
            }).join('');
        }

        var subtotal = items.reduce(function (s, it) {
            var qty = Math.max(1, Number(it.quantity) || 1);
            return s + (Number(it.price) || 0) * qty;
        }, 0);
        var formatted = formatVN(subtotal);

        var subtotalEl = document.querySelector('.box-product .total .text-price strong');
        if (subtotalEl) subtotalEl.textContent = formatted;

        function readNumber(el) {
            if (!el) return 0;
            var v = (el.value !== undefined ? el.value : el.textContent) || '';
            v = String(v).replace(/[^\d.-]/g, '');
            return Number(v) || 0;
        }

        function updateFinalTotal(subtotalValue) {
            var shipValue = readNumber(document.querySelector('#cart-ship_fee span'));
            var discountValue = readNumber(document.querySelector('#cart-discount .text-price span'));
            var totalFinal = Math.max(0, subtotalValue + shipValue - discountValue);
            var totalBillEl = document.querySelector('#cart-total_bill strong span');
            if (totalBillEl) totalBillEl.textContent = formatVN(totalFinal);
            var hiddenTotal = document.getElementById('p-total-final');
            if (hiddenTotal) hiddenTotal.value = String(totalFinal);
        }

        updateFinalTotal(subtotal);

        var observeTotalChanges = function () {
            if (!window.MutationObserver) return;
            var shipNode = document.querySelector('#cart-ship_fee span');
            var discountNode = document.querySelector('#cart-discount .text-price span');
            var observer = new MutationObserver(function () {
                updateFinalTotal(subtotal);
            });
            [shipNode, discountNode].forEach(function (node) {
                if (node) {
                    observer.observe(node, { characterData: true, childList: true, subtree: true });
                }
            });
        };
        observeTotalChanges();
    }
    function syncStockFromApi() {
        if (!window.fetch) return;
        fetch('api/products.php', { credentials: 'same-origin', cache: 'no-store' })
            .then(function (res) { return res.ok ? res.json() : null; })
            .then(function (json) {
                if (!json || !json.success || !json.data || !json.data.categories) return;
                var stocks = {};
                json.data.categories.forEach(function (cat) {
                    (cat.products || []).forEach(function (p) {
                        stocks[String(p.id)] = Math.max(0, Number(p.stock) || 0);
                    });
                });
                var changed = false;
                var items = readCart().map(function (it) {
                    var id = String(normalizeId(it.id));
                    if (Object.prototype.hasOwnProperty.call(stocks, id) && it.stock !== stocks[id]) {
                        it.stock = stocks[id];
                        changed = true;
                    }
                    return it;
                });
                if (changed) {
                    writeCart(normalizeCart(items));
                    renderItems();
                }
            })
            .catch(function () {});
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { renderItems(); syncStockFromApi(); });
    } else {
        renderItems(); syncStockFromApi();
    }
})();
</script>

<script>
(function () {
    var STORAGE_KEY = 'fitfood_cart_v1';

    function readCart() {
        try {
            var raw = localStorage.getItem(STORAGE_KEY);
            return raw ? JSON.parse(raw) : [];
        } catch (e) { return []; }
    }
    function writeCart(items) {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(items));
    }
    var LEGACY_PACKAGE_IDS = {
        fit3: 1,
        full: 2,
        fit1: 3,
        meat: 4,
        slim: 5,
        'lunch-eatclean': 6,
        lunch: 6
    };
    var PACKAGE_VARIANT_PRICES = {
        1: { week: 650000, month: 2340000 },
        2: { week: 825000, month: 2970000 },
        3: { week: 650000, month: 2340000 },
        4: { week: 950000, month: 3420000 },
        5: { week: 600000, month: 2160000 },
        6: { week: 349000 }
    };
    function normalizeId(id) {
        var key = String(id == null ? '' : id).trim();
        if (LEGACY_PACKAGE_IDS[key]) return LEGACY_PACKAGE_IDS[key];
        var parsed = parseInt(key, 10);
        return parsed > 0 ? parsed : '';
    }
    function normalizeType(type, name) {
        var rawType = String(type || '').trim();
        var rawName = String(name || '').toLowerCase();
        if (rawType === 'month' || rawName.indexOf('gói tháng') !== -1) return 'month';
        if (rawType === 'week' || rawName.indexOf('gói tuần') !== -1) return 'week';
        return rawType;
    }
    function normalizePrice(id, type, price) {
        var prices = PACKAGE_VARIANT_PRICES[id];
        return prices && prices[type] ? prices[type] : price;
    }
    function normalizeCart(items) {
        var grouped = {};
        (Array.isArray(items) ? items : []).forEach(function (raw) {
            var it = raw || {};
            var id = normalizeId(it.id);
            if (!id) return;
            var name = String(it.name || '').trim();
            var type = normalizeType(it.type, name);
            var price = normalizePrice(id, type, Number(it.price) || 0);
            var image = String(it.image || '').trim();
            var hasStock = it.stock !== undefined && it.stock !== null && it.stock !== '';
            var stock = hasStock ? Math.max(0, Number(it.stock) || 0) : null;
            if (stock === 0) return;
            var quantity = Math.max(1, Number(it.quantity) || 1);
            if (stock !== null && quantity > stock) quantity = stock;
            var key = [id, type, price, name].join('|');
            if (!grouped[key]) {
                grouped[key] = {
                    id: id,
                    type: type,
                    name: name,
                    image: image,
                    price: price,
                    calo: Number(it.calo) || 0,
                    stock: stock,
                    quantity: 0
                };
            }
            grouped[key].quantity += quantity;
            if (grouped[key].stock !== null && grouped[key].quantity > grouped[key].stock) {
                grouped[key].quantity = grouped[key].stock;
            }
            if (!grouped[key].image && image) grouped[key].image = image;
            if (grouped[key].stock === null && stock !== null) grouped[key].stock = stock;
        });
        return Object.keys(grouped).map(function (k) { return grouped[k]; });
    }

    function readNumber(el) {
        if (!el) return 0;
        var v = (el.value !== undefined ? el.value : el.textContent) || '';
        v = String(v).replace(/[^\d.-]/g, '');
        return Number(v) || 0;
    }

    var btn  = document.getElementById('btn-place-order');
    var form = document.getElementById('payment-form');
    if (!btn || !form) return;

    var submitting = false;

    // Capture-phase + stopImmediatePropagation: chặn handler CDN khác bind cùng button
    btn.addEventListener('click', function (e) {
        if (submitting) {
            e.preventDefault();
            e.stopImmediatePropagation();
            return;
        }
        e.stopImmediatePropagation();

        var originalCart = readCart();
        var cart = normalizeCart(originalCart);
        if (JSON.stringify(cart) !== JSON.stringify(originalCart)) {
            writeCart(cart);
        }
        if (!cart.length) {
            alert('Giỏ hàng đang trống.');
            return;
        }

        // Giữ riêng biến thể tuần/tháng bằng unit_price, chỉ submit item đã có ID hợp lệ.
        cart.forEach(function (it) {
            it.id = normalizeId(it.id);
            it.quantity = Math.max(1, Number(it.quantity) || 1);
            it.price = Number(it.price) || 0;
        });
        var payload = cart.filter(function (it) { return it.id; });
        if (!payload.length) {
            alert('Giỏ hàng không hợp lệ. Vui lòng xoá giỏ hàng và thêm lại từ trang sản phẩm.');
            return;
        }

        // Validate địa chỉ + thời gian giao hàng
        var addrInput = form.querySelector('[name="address"]');
        var nameInput = form.querySelector('[name="name"]');
        var phoneInput = form.querySelector('[name="phone"]');
        var dtSelect  = form.querySelector('[name="delivery_time"]');

        if (!nameInput || !nameInput.value.trim()) { alert('Vui lòng nhập họ tên.'); return; }
        if (!phoneInput || !/^[0-9]{8,15}$/.test(phoneInput.value.trim())) {
            alert('Số điện thoại không hợp lệ.'); return;
        }
        if (!addrInput || !addrInput.value.trim()) { alert('Vui lòng nhập địa chỉ giao hàng.'); return; }
        if (!dtSelect || !dtSelect.value)          { alert('Vui lòng chọn thời gian giao hàng.'); return; }

        // Đẩy cart + ship_fee + discount vào hidden input rồi submit thật
        document.getElementById('cart-items-json').value = JSON.stringify(payload);
        document.getElementById('ship-fee-input').value =
            readNumber(document.querySelector('#cart-ship_fee span'));
        document.getElementById('discount-input').value =
            readNumber(document.querySelector('#cart-discount .text-price span'));

        // Disable + flag TRƯỚC khi submit để chặn click lần 2
        submitting = true;
        btn.disabled = true;
        btn.textContent = 'Đang xử lý…';
        // Chặn form bị submit 2 lần (nếu CDN script gọi form.submit() nữa)
        form.addEventListener('submit', function (ev) {
            if (form.dataset.submitted === '1') ev.preventDefault();
            form.dataset.submitted = '1';
        }, true);
        form.submit();
    }, true); // useCapture=true để chạy trước handler bubble từ CDN
})();
</script>
<script>
(function () {
    var dataEl = document.getElementById('d-times');
    var select = document.getElementById('delivery_time');
    if (!dataEl || !select) return;

    var timesData = {};
    try {
        timesData = JSON.parse(dataEl.textContent || dataEl.value || '{}');
    } catch (e) {
        timesData = {};
    }

    function buildOptions(items) {
        select.innerHTML = '';
        var placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = 'Chọn thời gian giao hàng';
        placeholder.disabled = true;
        placeholder.selected = true;
        select.appendChild(placeholder);

        items.forEach(function (item) {
            var opt = document.createElement('option');
            opt.value = item.text;
            opt.textContent = item.text;
            select.appendChild(opt);
        });
    }

    function getAllTimes() {
        var seen = {};
        var result = [];
        Object.values(timesData).forEach(function (arr) {
            arr.forEach(function (item) {
                if (!seen[item.text]) {
                    seen[item.text] = true;
                    result.push(item);
                }
            });
        });
        return result;
    }

    function updateDeliveryTimes() {
        var district = (document.getElementById('cus_district') || {}).value;
        var items = [];
        if (district && timesData[district]) {
            items = timesData[district];
        }
        if (items.length === 0) {
            items = getAllTimes();
        }
        buildOptions(items);
    }

    function initDeliveryTimes() {
        updateDeliveryTimes();
        var districtEl = document.getElementById('cus_district');
        if (districtEl) {
            districtEl.addEventListener('change', updateDeliveryTimes);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDeliveryTimes);
    } else {
        initDeliveryTimes();
    }
})();
</script>
</body>
</html>

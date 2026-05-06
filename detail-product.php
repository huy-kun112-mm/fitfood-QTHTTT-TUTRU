<?php
/**
 * FitFood — Trang detail sản phẩm động.
 * Truy cập: detail-product.php?slug=ten-san-pham
 *
 * Tự load dữ liệu từ DB theo slug, render cùng layout với các file
 * detail-product-*.php tĩnh (fit1, fit3, full, lunch, meat, slim).
 * Dùng cho mọi sản phẩm admin tạo qua /admin/create-product.php.
 */

if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/lib/products.php';
require_once __DIR__ . '/lib/reviews.php';

$slug    = isset($_GET['slug']) ? trim((string)$_GET['slug']) : '';
$product = null;

if ($slug !== '' && $pdo) {
    $stmt = $pdo->prepare(
        "SELECT p.*, c.slug AS category_slug, c.name AS category_name
           FROM products p
           LEFT JOIN categories c ON c.id = p.category_id
          WHERE p.slug = :slug AND p.is_active = 1
          LIMIT 1"
    );
    $stmt->execute([':slug' => $slug]);
    $product = $stmt->fetch() ?: null;
}

if (!$product) {
    http_response_code(404);
    ?>
    <!DOCTYPE html>
    <html lang="vi"><head><meta charset="utf-8"><title>Không tìm thấy sản phẩm</title>
    <link href="https://fitfood.vn/css/css.css?v=2026033101" rel="stylesheet"></head>
    <body style="text-align:center;padding:80px 20px;font-family:Montserrat,sans-serif">
        <h1>404 — Sản phẩm không tồn tại</h1>
        <p>Sản phẩm bạn tìm không có trong hệ thống hoặc đã bị tạm ẩn.</p>
        <p><a href="menu.php">Quay lại thực đơn</a></p>
    </body></html>
    <?php
    return;
}

$is_package    = ($product['type'] === 'package');
$base_price    = (float)$product['price'];
$selling_price = effective_selling_price($product);
$has_sale      = $selling_price < $base_price;

$name_html = htmlspecialchars($product['name']);
$short_desc_html = htmlspecialchars($product['short_description'] ?? '');
$image_url = $product['image_url'] ?: 'https://fitfood.vn/images/logo-healthyfood.png';
$image_url_html = htmlspecialchars($image_url);

$week_price  = (int)round($selling_price);
$month_price = (int)round($selling_price * 4 * 0.9);

$page_url = 'detail-product.php?slug=' . urlencode($product['slug']);

$ld_json = json_encode([
    '@context' => 'https://schema.org',
    '@type'    => 'Product',
    'name'     => $product['name'],
    'description' => $product['short_description'] ?: strip_tags((string)$product['description']),
    'image'    => $image_url,
    'offers'   => [
        '@type'         => 'Offer',
        'priceCurrency' => 'VND',
        'price'         => $week_price,
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="lang" content="vi">

    <title><?= $name_html ?></title>
    <meta name="description" content="<?= $short_desc_html ?>">

    <link rel="icon" href="/favicon.ico">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">

    <link href="https://fitfood.vn/css/fonts.css?v=2026033101" rel="stylesheet">
    <link href="https://fitfood.vn/css/vendor.css?v=2026033101" rel="stylesheet">
    <link href="https://fitfood.vn/css/css.css?v=2026033101" rel="stylesheet">

    <meta property="og:site_name" content="Fitfood.vn" />
    <meta property="og:type" content="article" />
    <meta property="og:title" content="<?= $name_html ?>" />
    <meta property="og:description" content="<?= $short_desc_html ?>" />
    <meta property="og:image" content="<?= $image_url_html ?>" />

    <script type="application/ld+json"><?= $ld_json ?></script>

    <link rel="stylesheet" href="https://fitfood.vn/js/plugins/simplelightbox/simple-lightbox.css?v=2026033101">

    <style type="text/css">
        .doimk { display: none; }
        .hienmatkhau { display: inline-block !important; }
        .matkhaugiainen .nhanvao { display: none !important; }
        .new-links { display: block !important; color: red; }
    </style>
</head>

<body data-page="<?= $is_package ? 'package' : 'product' ?>" data-device="desktop"
    class="" data-url="<?= htmlspecialchars($page_url) ?>"
    data-lang="vi">

<nav id="main-menu" class="navbar navbar-expand-xl navbar-dark bg-dark fixed-top">
    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#main-navigation"
            aria-controls="main-navigation" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
    </button>

    <a class="navbar-brand mr-auto logo-header" href="index.php">
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
            <li class="nav-item"><a class="nav-link" href="index.php">Trang chủ</a></li>
            <li class="nav-item"><a class="nav-link" href="menu.php">Thực đơn</a></li>
            <li class="nav-item premium"><a class="nav-link" href="b2b.php">Đặt tiệc</a></li>
            <li class="nav-item"><a class="nav-link" href="order.php">Đặt hàng</a></li>
            <li class="nav-item"><a class="nav-link" href="look.php">Hình ảnh</a></li>
            <li class="nav-item"><a class="nav-link" href="faqs.php">FAQs</a></li>
        </ul>
        <ul class="nav navbar-nav navbar-sub flex-row order-1 order-xl-0">
            <?php if (isset($_SESSION['user_id'])): ?>
                <?php
                  $av = $_SESSION['user_avatar'] ?? '';
                  $avatar_url = $av !== ''
                      ? (preg_match('#^https?://#', $av) ? $av : 'uploads/avatars/' . htmlspecialchars($av))
                      : 'https://fitfood.vn/img/128/avatars/default.png';
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
                <li class="nav-item"><a id="btnOpenRegister" href="javascript:void(0)" class="nav-link">Đăng ký</a></li>
                <li class="nav-item"><a id="btnOpenLogin" href="javascript:void(0)" class="nav-link">Đăng nhập</a></li>
            <?php endif; ?>
        </ul>
        <div class="language mobile-invisible order-3 d-lg-none">
            <span class="lang-switch active" data-lang="en"><span>EN</span></span>
            <span class="arrow">/</span>
            <span class="lang-switch" data-lang="vi"><span>VI</span></span>
        </div>
        <div class="mobile-invisible-overlap"></div>
    </div>

    <?php include 'cart.php'; ?>

    <div class="language desktop-visible order-3 order-sm-0">
        <span class="lang-switch active" data-lang="en">
            <span class="d-sm-none">English</span>
            <span class="d-none d-sm-block">EN</span>
        </span>
        <span class="arrow">/</span>
        <span class="lang-switch" data-lang="vi">
            <span class="d-sm-none">Vietnamese</span>
            <span class="d-none d-sm-block">VI</span>
        </span>
    </div>
    <input type="hidden" id="route-lang" value="https://fitfood.vn/lang">
</nav>

<main class="">
    <section class="top-inner">
        <div class="container">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item active">
                        <a href="javascript:void(0);"><span><i class="fa fa-order" aria-hidden="true"></i></span>Đặt hàng</a>
                    </li>
                    <li class="breadcrumb-item">
                        <a href="javascript:void(0);"><span><i class="fa fa-ellipsis-h" aria-hidden="true"></i></span>Chỉnh sửa</a>
                    </li>
                    <li class="breadcrumb-item">
                        <a href="javascript:void(0);"><span><i class="fa fa-usd" aria-hidden="true"></i></span>Thanh toán</a>
                    </li>
                    <li class="breadcrumb-item">
                        <a href="javascript:void(0);"><span><i class="fa fa-check" aria-hidden="true"></i></span>Xác nhận</a>
                    </li>
                </ol>
            </nav>
            <div class="box-info fix-span order mb-5">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <div class="slides">
                            <div><img src="<?= $image_url_html ?>" alt="<?= $name_html ?>" /></div>
                        </div>
                    </div>
                    <div class="col-md-8 mb-3">
                        <div class="d-flex flex-column flex-lg-row justify-content-between">
                            <h1 class="title"><?= $name_html ?></h1>
                            <p id="p-price" class="price">
                                <?= format_vnd($selling_price) ?>
                                <?php if ($has_sale): ?>
                                    <small class="text-muted text-decoration-line-through" style="margin-left:8px">
                                        <?= format_vnd($base_price) ?>
                                    </small>
                                <?php endif; ?>
                            </p>
                        </div>
                        <p class="note note-ex"></p>

                        <?php if (!empty($product['short_description'])): ?>
                            <p><strong><?= $short_desc_html ?></strong></p>
                        <?php endif; ?>

                        <div class="product-description">
                            <?= $product['description'] ?: '<p>Chưa có mô tả chi tiết.</p>' ?>
                        </div>

                        <?php if (!empty($product['ingredients'])): ?>
                            <div class="product-ingredients mt-3">
                                <h4>Thành phần</h4>
                                <p><?= nl2br(htmlspecialchars($product['ingredients'])) ?></p>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($product['calories'])): ?>
                            <p class="mt-2"><strong>Calories:</strong> <?= (int)$product['calories'] ?> Kcal</p>
                        <?php endif; ?>

                        <?php if ($is_package): ?>
                            <form id="form-package" action="javascript:void(0)" method="post" data-show-cart="">
                                <div class="form-group">
                                    <div class="form-check input-radio">
                                        <input class="form-check-input" type="radio" name="order_type" id="orderRadios1"
                                               value="week" checked
                                               data-price="<?= number_format($week_price, 0, '.', ',') ?>">
                                        <label class="form-check-label" for="orderRadios1">Gói Tuần - 5 ngày</label>
                                    </div>
                                    <div class="form-check input-radio">
                                        <input class="form-check-input" type="radio" name="order_type" id="orderRadios2"
                                               value="month"
                                               data-price="<?= number_format($month_price, 0, '.', ',') ?>">
                                        <label class="form-check-label" for="orderRadios2">Gói Tháng (4 tuần)</label>
                                    </div>
                                </div>
                                <input type="hidden" name="package" value="<?= htmlspecialchars($product['slug']) ?>">
                                <button type="button" id="add-to-cart" class="btn btn-primary submit">Thêm vào giỏ</button>
                                <a target="_blank" href="https://www.messenger.com/t/765002226901320" class="btn btn-primary btn-blue">Tư Vấn</a>
                            </form>
                        <?php else: ?>
                            <?php
                                $stock = (int)($product['stock'] ?? 0);
                                $out_of_stock = $stock <= 0;
                            ?>
                            <div class="product-stock mb-3">
                                <?php if ($out_of_stock): ?>
                                    <span class="stock-status stock-status-out">Hết hàng</span>
                                <?php elseif ($stock < 5): ?>
                                    <span class="stock-status stock-status-low">Sắp hết hàng (còn <?= $stock ?>)</span>
                                <?php else: ?>
                                    <span class="stock-status stock-status-in">Còn hàng</span>
                                <?php endif; ?>
                            </div>
                            <button type="button"
                                    id="add-product-to-cart"
                                    class="btn btn-primary submit <?= $out_of_stock ? 'is-disabled' : '' ?>"
                                    data-id="<?= (int)$product['id'] ?>"
                                    data-name="<?= $name_html ?>"
                                    data-price="<?= $week_price ?>"
                                    data-image="<?= $image_url_html ?>"
                                    data-calo="<?= (int)($product['calories'] ?? 0) ?>"
                                    <?= $out_of_stock ? 'disabled' : '' ?>>
                                Thêm vào giỏ
                            </button>
                            <a target="_blank" href="https://www.messenger.com/t/765002226901320" class="btn btn-primary btn-blue">Tư Vấn</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php render_reviews_section($pdo, $product); ?>

        <?php include __DIR__ . "/includes/detail-product-shared.php"; ?>

<script>
// Handler riêng cho product lẻ (id="add-product-to-cart").
// Package vẫn dùng handler của detail-product-shared.php (id="add-to-cart").
(function () {
    var btn = document.getElementById('add-product-to-cart');
    if (!btn) return;

    function parseInteger(s) {
        return parseInt(String(s == null ? '' : s).replace(/[^\d]/g, ''), 10) || 0;
    }

    btn.addEventListener('click', function (e) {
        e.preventDefault();
        if (btn.classList.contains('is-disabled') || btn.disabled) return;

        if (window.FitfoodCart && typeof window.FitfoodCart.add === 'function') {
            window.FitfoodCart.add({
                id:    btn.getAttribute('data-id') || '',
                type:  'product',
                name:  btn.getAttribute('data-name') || '',
                image: btn.getAttribute('data-image') || '',
                price: parseInteger(btn.getAttribute('data-price')),
                calo:  parseInteger(btn.getAttribute('data-calo'))
            });
        }
    });
})();
</script>

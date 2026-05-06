<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/lib/reviews.php';
$reviews_product = null;
if ($pdo) {
    $stmt = $pdo->prepare("SELECT id FROM products WHERE slug = ? AND is_active = 1");
    $stmt->execute(['goi-full']);
    $reviews_product = $stmt->fetch() ?: null;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="M0ZWj7dgiFlxa2Hzr9rfzEhmG4K8QrJKj7hgotBT">
    <meta name="lang" content="vi">

    <title>Gói FULL</title>
        <meta name="description" content="Gói SÁNG - TRƯA - TỐI. Ăn cả ngày phù hợp cho người bận rộn">
    
    <link rel="icon" href="/favicon.ico">
    <link rel="canonical" href="https://fitfood.vn/san-pham/full" />
    <link rel="alternate" hreflang="vi" href="https://fitfood.vn/san-pham/full" />


    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">

    <link href="https://fitfood.vn/css/fonts.css?v=2026033101" rel="stylesheet">
    <link href="https://fitfood.vn/css/vendor.css?v=2026033101" rel="stylesheet">
    <link href="https://fitfood.vn/css/css.css?v=2026033101" rel="stylesheet">

    <meta property="og:site_name" content="Fitfood.vn" />
    <meta property="og:type" content="article" />

            <meta name="google-site-verification" content="AbTvSYDeKcRTKgJ4dNUNDKf4mt2ZO_Rz8z4W9R4Zb_A" />
    
    
    <meta property="fb:app_id" content="1499077570417040" />

    <meta property="og:image:width" content="2048" />
    <meta property="og:image:height" content="1365" />

                <script type="application/ld+json">{"@context":"https:\/\/schema.org","@type":"LocalBusiness","name":"Fitfood","email":"info@fitfood.vn","telephone":"(+84) 932 788 120","address":{"@type":"PostalAddress","streetAddress":"33 Đường 14, KDC Bình Hưng, Ấp 2"}}</script>
            <script type="application/ld+json">{"@context":"https:\/\/schema.org","@type":"Organization","name":"Fitfood","url":"https:\/\/fitfood.vn","logo":"https:\/\/fitfood.vn\/images\/logo.png"}</script>
            <script type="application/ld+json">{"@context":"https:\/\/schema.org","@type":"Product","name":"Gói FULL","description":"Gói SÁNG - TRƯA - TỐI. Ăn cả ngày phù hợp cho người bận rộn","image":"https:\/\/fitfood.vn\/img\/original\/images\/full-15815879566674.jpg","offers":{"@type":"Offer","priceCurrency":"VND","price":825000}}</script>
        
            <link rel="stylesheet" href="https://fitfood.vn/js/plugins/simplelightbox/simple-lightbox.css?v=2026033101">
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

<body data-page="package" data-device="desktop"
    class="" data-url="https://fitfood.vn/san-pham/full"
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
    <li  class="nav-item">
     <a  class="nav-link" href="index.php">
      Trang chủ
          </a>
          </li>
    
  <li  class="nav-item">
     <a  class="nav-link" href="menu.php">
      Thực đơn
          </a>
          </li>
    
  <li  class="nav-item premium">
     <a  class="nav-link" href="b2b.php">
      Đặt tiệc
          </a>
          </li>
    
  <li  class="nav-item">
     <a  class="nav-link" href="order.php">
      Đặt hàng
          </a>
          </li>
    
  <li  class="nav-item">
     <a  class="nav-link" href="look.php">
      Hình ảnh
          </a>
          </li>
    
  <li  class="nav-item">
     <a  class="nav-link" href="faqs.php">
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

    <?php include 'cart.php'; ?>


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

<main class="">
    <section class="top-inner">
        <div class="container">
                        <nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item active">
            <a href="javascript:void(0);">
                <span><i class="fa fa-order" aria-hidden="true"></i></span>Đặt hàng
            </a>
        </li>
        <li class="breadcrumb-item ">
            <a href="javascript:void(0);">
                <span><i class="fa fa-ellipsis-h" aria-hidden="true"></i></span>Chỉnh sửa
            </a>
        </li>
        <li class="breadcrumb-item ">
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
            <div class="box-info fix-span order mb-5">
                <div class="row">
                    <div class="col-md-4 mb-3">
                                                    <div class="slides">
            <div><img src="https://fitfood.vn/static/sizes/800x600-fitfood-goi-full-healthy-1-17521259281132.jpg" alt="Gói FULL 0" /></div>
            <div><img src="https://fitfood.vn/static/sizes/800x600-fitfood-goi-full-healthy-5-17521259281867.jpg" alt="Gói FULL 1" /></div>
            <div><img src="https://fitfood.vn/static/sizes/800x600-fitfood-goi-full-healthy-6-17521259281542.jpg" alt="Gói FULL 2" /></div>
            <div><img src="https://fitfood.vn/static/sizes/800x600-fitfood-goi-full-healthy-4-17521259282325.jpg" alt="Gói FULL 3" /></div>
            <div><img src="https://fitfood.vn/static/sizes/800x600-fitfood-goi-full-healthy-2-17521259280763.jpg" alt="Gói FULL 4" /></div>
            <div><img src="https://fitfood.vn/static/sizes/800x600-fitfood-goi-full-healthy-3-17521259279898.jpg" alt="Gói FULL 5" /></div>
            <div><img src="https://fitfood.vn/static/sizes/800x600-fitfood-goi-full-healthy-17521259280425.jpg" alt="Gói FULL 6" /></div>
    </div>
                                            </div>
                    <div class="col-md-8 mb-3">
                        <div class="d-flex flex-column flex-lg-row justify-content-between">
                            <h1 class="title">Gói FULL</h1>
                            <p id="p-price" class="price">825,000đ</p>
                        </div>
                        <p class="note note-ex"></p>
                        <p><p>G&oacute;i 3 bữa S&Aacute;NG - TRƯA - TỐI</p>

<p>- Sử dụng thực đơn 3 bữa/ng&agrave;y tại trang fitfood.vn/menu.</p>

<p>- Giao 03 phần ăn tận nơi mỗi ng&agrave;y, từ thứ 2 đến thứ 6.</p>

<p>- Calories dao động từ 1300 - 1500 Kcal ph&ugrave; hợp với thể trạng người Ch&acirc;u &Aacute;</p>

<p>- K&egrave;m tinh bột phức, &iacute;t đường, đảm bảo ko bột ngọt, nhiều rau củ v&agrave; chất đạm</p>

<p><em>* Th&iacute;ch hợp cho người ăn ki&ecirc;ng bận rộn hoặc theo đuổi chế độ ăn l&acirc;u d&agrave;i</em></p></p>
                                                <form id="form-package" action="https://fitfood.vn/cart/package/add" method="post" data-show-cart="">
                                                        <div class="form-group">
                                                                    <div class="form-check input-radio">
                                        <input class="form-check-input" type="radio" name="order_type" id="orderRadios1" value="week" checked data-price="825,000">
                                        <label class="form-check-label" for="orderRadios1">Gói Tuần - 5 ngày</label>
                                    </div>
                                                                                                <div class="form-check input-radio">
                                    <input class="form-check-input" type="radio" name="order_type" id="orderRadios2" value="month" data-price="2,970,000">
                                    <label class="form-check-label" for="orderRadios2">Gói Tháng (4 tuần)</label>
                                </div>
                                                            </div>
                            
                            <input type="hidden" name="package" value="full">
                                                        <button type="button" href="javascript:void(0)" id="add-to-cart" class="btn btn-primary submit">Thêm vào giỏ</button>
                                                        <a target="_blank" href="https://www.messenger.com/t/765002226901320" class="btn btn-primary btn-blue">Tư Vấn</a>
                        </form>
                                            </div>
                </div>
            </div>
                    </div>

<?php render_reviews_section($pdo, $reviews_product); ?>
<?php include __DIR__ . "/includes/detail-product-shared.php"; ?>

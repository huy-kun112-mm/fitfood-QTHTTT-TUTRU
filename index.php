<?php
// ===== Khởi động session cho hệ thống đăng ký =====
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// ===== Lấy các gói ăn từ DB cho section "Sản phẩm tiêu biểu" =====
require_once __DIR__ . '/lib/products.php';
$packages_groups = [];
$db_down = ($pdo === null);
if ($pdo !== null) {
    try {
        $packages_groups = get_products_grouped($pdo, ['type' => 'package']);
    } catch (PDOException $e) {
        $db_down = true;
        error_log('[index.php] DB query failed: ' . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="qLPipbYOfF5v8Crd8ny6oywAxuKQ6ZFTpEcE03Jy">
    <meta name="lang" content="vi">

    <title>Fitfood VN - Nhà cung cấp gói ăn healthy lớn nhất Saigon</title>
                    <meta name="description" content="Giúp bạn ăn kiêng không bao giờ ngán với thực đơn được Fitfood lên kế hoạch kỹ lưỡng. Siêu ngon, giảm cân, healthy, ăn là ghiền - Nhanh tay order ngay!">
                    
    <link rel="icon" href="/favicon.ico">
    <link rel="canonical" href="https://fitfood.vn" />
    <link rel="alternate" hreflang="vi" href="https://fitfood.vn" />


    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">

    <link href="https://fitfood.vn/css/fonts.css?v=2026033101" rel="stylesheet">
    <link href="https://fitfood.vn/css/vendor.css?v=2026033101" rel="stylesheet">
    <link href="https://fitfood.vn/css/css.css?v=2026033101" rel="stylesheet">

    <!-- CSS popup đăng ký -->
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

<body data-page="home" data-device="desktop"
    class="" data-url="https://fitfood.vn"
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
     <a  class="active nav-link" href="index.php">
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
    <!-- main slider -->
<section class="main-banner">
        <ul class="bxslider">
                <li>
            <div class="featured-img" style="background-image: url(https://fitfood.vn/img/1920x800/images/nen-final-1-1695095846415.webp?f=jpg)" >
                                <p class="title">
                    <small>Kế hoạch bữa ăn hàng tuần cho </small> một lối sống lành mạnh
                </p>
                                                <div class="slide-btn">
                <a href="order.php" class="btn btn-primary">Đặt Ngay</a>
                                    <a target="_blank" href="https://www.messenger.com/t/765002226901320" class="btn btn-primary btn-blue">Tư Vấn</a>
                                </div>
                            </div>
        </li>
                <li>
            <div class="featured-img" style="background-image: url(https://fitfood.vn/img/1920x800/images/nen-final-3-16947527108499.webp?f=jpg)" >
                                <p class="title">
                    Trải nghiệm bữa ăn sạch <small>tươi ngon giàu dinh dưỡng</small>
                </p>
                                                <div class="slide-btn">
                <a href="order.php" class="btn btn-primary">Đặt Ngay</a>
                                    <a target="_blank" href="https://www.messenger.com/t/765002226901320" class="btn btn-primary btn-blue">Tư Vấn</a>
                                </div>
                            </div>
        </li>
                <li>
            <div class="featured-img" style="background-image: url(https://fitfood.vn/img/1920x800/images/nen-web-16950958311174.webp?f=jpg)" >
                                <p class="title">
                    <small>Nhà cung cấp bữa ăn sạch</small>lớn nhất Sài Gòn
                </p>
                                                <div class="slide-btn">
                <a href="https://fitfood.vn/order" class="btn btn-primary"></a>
                                </div>
                            </div>
        </li>
                <li>
            <div class="featured-img" style="background-image: url(https://fitfood.vn/img/1920x800/images/nen-final-2-16947528537416.webp?f=jpg)" >
                                <p class="title">
                    Giải pháp HEALTHY FOOD <small>giao tận nơi</small>
                </p>
                                                <div class="slide-btn">
                <a href="https://fitfood.vn/order" class="btn btn-primary"></a>
                                </div>
                            </div>
        </li>
            </ul>
    
</section>
    <!-- main about -->
    <section class="main-about">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-7 px-0 order-2 order-md-0">
                    <img style="width: 100%" src="/images/img-about-mobile.png?t=456" alt="FITFOOD VIETNAM" border="0" class="img-fluid d-md-none"/>
                </div>
                <div class="col-md-5 col-lg-4 order-1 order-md-0">
                    <h1 class="title pb-4 mb-4">FITFOOD VIETNAM</h1>
                    <p>Fitfood VN cung cấp các phần ăn lành mạnh hàng tuần giúp bạn duy trì một lối sống khỏe. Chúng tôi tập trung vào chế độ ăn cân bằng được thiết kế chuyên biệt để hỗ trợ bạn kiểm soát cân nặng một cách hiệu quả nhất.
</p>
Nếu bạn đang tìm kiếm những bữa ăn ngon và tốt cho sức khỏe được chuẩn bị sẵn ở Saigon thì Fitfood là một lựa chọn tối ưu. Thực đơn đa dạng với hơn 100 món của chúng tôi có thể giúp bạn thưởng thức mà không ngán trong hơn 1 tháng.
</p>
                </div>
            </div>
        </div>
    </section>
    <!-- how it work -->
    <section class="how-it-work">
        <div class="container-fluid">
            <h2 class="title title-center pb-4 mb-5">Cách đặt hàng</h2>
            <div class="row">
                <div class="offset-xl-1 col-xl-10">
                    <div class="row slider">
                        <div class="col-md-6 col-lg-3 mb-3">
                            <div class="item">
                                <i class="icon icon-history"></i>
                                <p class="title">Chọn Gói Ăn</p>
                                <p>Chọn gói ăn phù hợp với nhu cầu của bạn và điền đầy đủ thông tin giao hàng</p>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-3 mb-3">
                            <div class="item">
                                <i class="icon icon-prep"></i>
                                <p class="title">Fitfood nấu</p>
                                <p>Chúng tôi lựa chọn những nguyên liệu tốt nhất và nấu trong bếp công nghiệp hiện đại</p>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-3 mb-3">
                            <div class="item">
                                <i class="icon icon-deli"></i>
                                <p class="title">Giao hàng</p>
                                <p>Đội ngũ giao hàng của Fitfood sẽ giao tận nơi các phần ăn cho bạn mỗi ngày</p>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-3 mb-3">
                            <div class="item">
                                <i class="icon icon-enjoy"></i>
                                <p class="title">Thưởng thức</p>
                                <p>Không cần suy nghĩ, shopping hay nấu nướng dầu mỡ, chỉ cần hâm và thưởng thức!</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- featured product -->
    <section class="featured-product">
        <div class="container">
            <h2 class="title title-center pb-4 mb-4">Sản phẩm tiêu biểu</h2>
            <p class="des mb-5">Fitfood cung cấp nhiều gói ăn và thực phẩm dùng kèm đa dạng phù hợp với mọi nhu cầu của bạn</p>
<?php if ($db_down): ?>
            <div role="alert" style="background:#fff3cd;border:1px solid #ffc107;color:#856404;padding:14px 18px;border-radius:6px;text-align:center;font-weight:500;margin-bottom:20px;">
                Hiện chưa tải được danh sách sản phẩm. Vui lòng thử lại sau ít phút.
            </div>
<?php endif; ?>
            <div class="products">
                <div class="row product-listing">
<?php
foreach ($packages_groups as $cat) {
    foreach ($cat['products'] as $p) render_product_card($p);
}
?>
                </div>
            </div>
        </div>
    </section>
    <!-- environmental friendly -->
    <section class="environmental">
                <div class="container">
            <h2 class="title title-center pb-4 mb-5">Chung tay bảo vệ<strong>Môi trường</strong></h2>
            <div class="row slider">
                                    <div class="col-md-4 item">
                        <img alt="Nhà cung cấp duy nhất sử dung túi Nylon sinh học tự hủy thân thiện với môi trường" class="img-fluid" src="https://fitfood.vn/img/346x288/uploads/dsc04248-15668117116574.webp?f=JPG" />                        <p>Nhà cung cấp duy nhất sử dung túi Nylon sinh học tự hủy thân thiện với môi trường</p>
                    </div>
                                    <div class="col-md-4 item">
                        <img alt="Rửa sạch lại hộp nhựa đen để nhận hoàn tiền 5,000 vnd cho mỗi 10 hộp" class="img-fluid" src="https://fitfood.vn/img/346x288/uploads/dsc04268-15668122623444.webp?f=JPG" />                        <p>Rửa sạch lại hộp nhựa đen để nhận hoàn tiền 5,000 vnd cho mỗi 10 hộp</p>
                    </div>
                                    <div class="col-md-4 item">
                        <img alt="Fitfood chỉ cung cấp 01 bộ muỗng nĩa mỗi ngày để giảm thiểu rác thải nhựa" class="img-fluid" src="https://fitfood.vn/img/346x288/uploads/dsc04263-15668117777881.webp?f=JPG" />                        <p>Fitfood chỉ cung cấp 01 bộ muỗng nĩa mỗi ngày để giảm thiểu rác thải nhựa</p>
                    </div>
                            </div>
        </div>
    </section>
    <!-- charity -->
            <section class="charity">
        <div class="container">
            <h2 class="title title-center pb-4 mb-5">Hoạt động thiện nguyện</h2>
            <div class="row slider">
                                <div class="col-md-4 item">
                                        <a href="https://fitfood.vn/tin-tuc/thien-nguyen-fitfood-mang-tet-ve-lop-hoc-tinh-thuong-long-buu" target="_blank">
                        <img alt="Mang Tết về Lớp học tình thương Long Bửu" class="img-fluid" src="https://fitfood.vn/img/346x288/uploads/yuu09832-edit-1-170918191934.webp?f=jpg" />                    </a>
                                        <p>Mang Tết về Lớp học tình thương Long Bửu</p>
                </div>
                                <div class="col-md-4 item">
                                        <a href="https://fitfood.vn/tin-tuc/thien-nguyen-vui-hoi-trang-ram-cung-fitfood-tai-mai-am-tam-binh" target="_blank">
                        <img alt="Vui Hội Trăng Rằm cùng 150 bé nhỏ tại Mái Ấm Tam Bình" class="img-fluid" src="https://fitfood.vn/img/346x288/uploads/thien-nguyen-vui-hoi-trang-ram-banner-17605177654803.webp?f=jpg" />                    </a>
                                        <p>Vui Hội Trăng Rằm cùng 150 bé nhỏ tại Mái Ấm Tam Bình</p>
                </div>
                                <div class="col-md-4 item">
                                        <a href="https://fitfood.vn/tin-tuc/fitfood-cung-san-se-yeu-thuong-tai-benh-vien-nhiet-doi" target="_blank">
                        <img alt="Lan toả tình yêu thương đến bà con tại Bệnh Viện Nhiệt Đới, Quận 5" class="img-fluid" src="https://fitfood.vn/img/346x288/uploads/fitfood-san-se-yeu-thuong-3-16926979919731.webp?f=jpg" />                    </a>
                                        <p>Lan toả tình yêu thương đến bà con tại Bệnh Viện Nhiệt Đới, Quận 5</p>
                </div>
                            </div>
        </div>
    </section>
        <!-- testimonial -->
    <section class="testimonial">
        <div class="container">
            <h2 class="title title-center pb-4 mb-4">Câu chuyện khách hàng</h2>
            <p class="text-center">Những câu chuyện thành công từ khách hàng thân yêu của Fitfood, khi chúng tôi đồng hành trên con đường thúc đẩy lối sống hành mạnh cùng họ.</p>
            <div class="testimonial-gallery">
                                                <div class="testimonial-item testimonial-first">
                    <a href="https://fitfood.vn/img/0x1080/images/fitfood-x-vadp-2-17373539671764.gif" data-lightbox="testimonial">
                        <img alt="Câu chuyện khách hàng-0" src="https://fitfood.vn/img/205x205/images/fitfood-x-vadp-17373539672113.webp?f=gif" />                    </a>
                </div>
                                <div class="testimonial-item ">
                    <a href="https://fitfood.vn/img/0x1080/images/fitfood-x-viac-2-17373542135143.gif" data-lightbox="testimonial">
                        <img alt="Câu chuyện khách hàng-1" src="https://fitfood.vn/img/205x205/images/fitfood-x-viac-17373542135483.webp?f=gif" />                    </a>
                </div>
                                <div class="testimonial-item ">
                    <a href="https://fitfood.vn/img/0x1080/images/fitfood-x-chi-dep-dap-gio-2024-2-17373556118089.gif" data-lightbox="testimonial">
                        <img alt="Câu chuyện khách hàng-2" src="https://fitfood.vn/img/205x205/images/fitfood-x-chi-dep-dap-gio-2024-17373556118548.webp?f=gif" />                    </a>
                </div>
                                <div class="testimonial-item ">
                    <a href="https://fitfood.vn/img/0x1080/images/fitfood-x-rich-products-2-17373560327699.gif" data-lightbox="testimonial">
                        <img alt="Câu chuyện khách hàng-3" src="https://fitfood.vn/img/205x205/images/fitfood-x-rich-products-17373560328209.webp?f=gif" />                    </a>
                </div>
                                <div class="testimonial-item ">
                    <a href="https://fitfood.vn/img/0x1080/images/fitfood-x-nestle-2-17373565178539.gif" data-lightbox="testimonial">
                        <img alt="Câu chuyện khách hàng-4" src="https://fitfood.vn/img/205x205/images/fitfood-x-nestle-17373565178898.webp?f=gif" />                    </a>
                </div>
                                <div class="testimonial-item ">
                    <a href="https://fitfood.vn/img/0x1080/images/fitfood-x-benh-vien-nhi-dong-1-1737357007828.gif" data-lightbox="testimonial">
                        <img alt="Câu chuyện khách hàng-5" src="https://fitfood.vn/img/205x205/images/fitfood-x-benh-vien-nhi-dong-1-2-1737357007867.webp?f=gif" />                    </a>
                </div>
                                <div class="testimonial-item ">
                    <a href="https://fitfood.vn/img/0x1080/images/fitfood-x-manpower-group-viet-nam-1737357363057.gif" data-lightbox="testimonial">
                        <img alt="Câu chuyện khách hàng-6" src="https://fitfood.vn/img/205x205/images/fitfood-x-manpower-group-viet-nam-2-17373573630934.webp?f=gif" />                    </a>
                </div>
                                <div class="testimonial-item ">
                    <a href="https://fitfood.vn/img/0x1080/images/fitfood-x-2m-solutions-2-17373577656607.gif" data-lightbox="testimonial">
                        <img alt="Câu chuyện khách hàng-7" src="https://fitfood.vn/img/205x205/images/fitfood-x-2m-solutions-17373577656999.webp?f=gif" />                    </a>
                </div>
                                <div class="testimonial-item ">
                    <a href="https://fitfood.vn/img/0x1080/images/fitfood-x-philip-morris-2-1737358482315.gif" data-lightbox="testimonial">
                        <img alt="Câu chuyện khách hàng-8" src="https://fitfood.vn/img/205x205/images/fitfood-x-philip-morris-17373584823615.webp?f=gif" />                    </a>
                </div>
                                <div class="testimonial-item ">
                    <a href="https://fitfood.vn/img/0x1080/images/fitfood-x-anh-trai-vuot-ngan-chong-gai-2-17373588794727.gif" data-lightbox="testimonial">
                        <img alt="Câu chuyện khách hàng-9" src="https://fitfood.vn/img/205x205/images/untitled-2-17373588795446.webp?f=gif" />                    </a>
                </div>
                                <div class="testimonial-item ">
                    <a href="https://fitfood.vn/img/0x1080/images/feedback-event-buffet-healthy-fitfood-danone-bia-17515228256081.jpg" data-lightbox="testimonial">
                        <img alt="Câu chuyện khách hàng-10" src="https://fitfood.vn/img/205x205/images/fitfood-event-buffet-healthy-feedback-17515228256402.webp?f=jpg" />                    </a>
                </div>
                                <div class="testimonial-item ">
                    <a href="https://fitfood.vn/img/0x1080/images/feedback-event-com-van-phong-fitfood-naver-1751523284102.jpg" data-lightbox="testimonial">
                        <img alt="Câu chuyện khách hàng-11" src="https://fitfood.vn/img/205x205/images/event-com-van-phong-fitfood-naver-17515232841328.webp?f=jpg" />                    </a>
                </div>
                                <div class="testimonial-item ">
                    <a href="https://fitfood.vn/img/0x1080/images/untitled-design-7-16788545928454.png" data-lightbox="testimonial">
                        <img alt="Câu chuyện khách hàng-12" src="https://fitfood.vn/img/205x205/images/recipe-detox-11-16788545928511.webp?f=png" />                    </a>
                </div>
                                <div class="testimonial-item ">
                    <a href="https://fitfood.vn/img/0x1080/images/feedback-fitfood-babihulk-2-17373660428433.jpg" data-lightbox="testimonial">
                        <img alt="Câu chuyện khách hàng-13" src="https://fitfood.vn/img/205x205/images/feedback-fitfood-babihulk-17373660429678.webp?f=jpg" />                    </a>
                </div>
                                <div class="testimonial-item ">
                    <a href="https://fitfood.vn/img/0x1080/images/untitled-design-10-16788561895022.png" data-lightbox="testimonial">
                        <img alt="Câu chuyện khách hàng-14" src="https://fitfood.vn/img/205x205/images/recipe-detox-2-16788500351865.webp?f=png" />                    </a>
                </div>
                                <div class="testimonial-item ">
                    <a href="https://fitfood.vn/img/0x1080/images/screen-shot-2023-11-18-at-174912-17003045808427.png" data-lightbox="testimonial">
                        <img alt="Câu chuyện khách hàng-15" src="https://fitfood.vn/img/205x205/images/385883239-3503294706604199-6436277285410019929-n-17003045808644.webp?f=png" />                    </a>
                </div>
                                <div class="testimonial-item ">
                    <a href="https://fitfood.vn/img/0x1080/images/untitled-design-8-16788547608348.png" data-lightbox="testimonial">
                        <img alt="Câu chuyện khách hàng-16" src="https://fitfood.vn/img/205x205/images/recipe-detox-10-16788541906051.webp?f=png" />                    </a>
                </div>
                                <div class="testimonial-item ">
                    <a href="https://fitfood.vn/img/0x1080/images/review-fitfood-giam-can-2-17514654454489.jpg" data-lightbox="testimonial">
                        <img alt="Câu chuyện khách hàng-17" src="https://fitfood.vn/img/205x205/images/review-fitfood-giam-can-21-17514654454792.webp?f=jpg" />                    </a>
                </div>
                                <div class="testimonial-item ">
                    <a href="https://fitfood.vn/img/0x1080/images/aug-11paulo-15737984935492.png" data-lightbox="testimonial">
                        <img alt="Câu chuyện khách hàng-18" src="https://fitfood.vn/img/205x205/images/recipe-detox-4-16788506023179.webp?f=png" />                    </a>
                </div>
                                <div class="testimonial-item ">
                    <a href="https://fitfood.vn/img/0x1080/images/screen-shot-2024-02-29-at-1236-17091856655829.png" data-lightbox="testimonial">
                        <img alt="Câu chuyện khách hàng-19" src="https://fitfood.vn/img/205x205/images/screen-shot-2024-02-29-at-1236-1-1709185665801.webp?f=png" />                    </a>
                </div>
                                <div class="testimonial-item ">
                    <a href="https://fitfood.vn/img/0x1080/images/screen-shot-2024-02-29-at-1232-17091856875811.png" data-lightbox="testimonial">
                        <img alt="Câu chuyện khách hàng-20" src="https://fitfood.vn/img/205x205/images/screen-shot-2024-02-29-at-1232-1-17091856876037.webp?f=png" />                    </a>
                </div>
                                <div class="testimonial-item ">
                    <a href="https://fitfood.vn/img/0x1080/images/453729735-879038670936490-5685655124717519534-n-17265049910595.jpg" data-lightbox="testimonial">
                        <img alt="Câu chuyện khách hàng-21" src="https://fitfood.vn/img/205x205/images/z5837866009382-8931b29999653f7e8fe03b5d83f787fd-17265049910887.webp?f=jpg" />                    </a>
                </div>
                                <div class="testimonial-item ">
                    <a href="https://fitfood.vn/img/0x1080/images/z6246030642367-a36a576e69f126976eefecd1d8c020b8-17373495766414.gif" data-lightbox="testimonial">
                        <img alt="Câu chuyện khách hàng-22" src="https://fitfood.vn/img/205x205/images/untitled-3-17373495766867.webp?f=gif" />                    </a>
                </div>
                                <div class="testimonial-item ">
                    <a href="https://fitfood.vn/img/0x1080/images/review-fitfood-giam-can-1-17514654217773.jpg" data-lightbox="testimonial">
                        <img alt="Câu chuyện khách hàng-23" src="https://fitfood.vn/img/205x205/images/review-fitfood-giam-can1-17514654218128.webp?f=jpg" />                    </a>
                </div>
                                <div class="testimonial-item ">
                    <a href="https://fitfood.vn/img/0x1080/images/july-27le-van-tien-157379866175.png" data-lightbox="testimonial">
                        <img alt="Câu chuyện khách hàng-24" src="https://fitfood.vn/img/205x205/images/recipe-detox-6-16788511450995.webp?f=png" />                    </a>
                </div>
                                <div class="testimonial-item ">
                    <a href="https://fitfood.vn/img/0x1080/images/untitled-design-3-1678849725503.png" data-lightbox="testimonial">
                        <img alt="Câu chuyện khách hàng-25" src="https://fitfood.vn/img/205x205/images/recipe-detox-8-16788532069444.webp?f=png" />                    </a>
                </div>
                                <div class="testimonial-item ">
                    <a href="https://fitfood.vn/img/0x1080/images/z6246035475918-4e8bd4bf024e95031ef506d9e3ca22ed-17373498065096.gif" data-lightbox="testimonial">
                        <img alt="Câu chuyện khách hàng-26" src="https://fitfood.vn/img/205x205/images/untitled-3-17373498065614.webp?f=gif" />                    </a>
                </div>
                                <div class="testimonial-item ">
                    <a href="https://fitfood.vn/img/0x1080/images/feedback-fitfood-17514448757374.jpg" data-lightbox="testimonial">
                        <img alt="Câu chuyện khách hàng-27" src="https://fitfood.vn/img/205x205/images/feedback-fitfood-1751447496746.webp?f=jpg" />                    </a>
                </div>
                                <div class="testimonial-item ">
                    <a href="https://fitfood.vn/img/0x1080/images/untitled-design-4-16788531049964.png" data-lightbox="testimonial">
                        <img alt="Câu chuyện khách hàng-28" src="https://fitfood.vn/img/205x205/images/recipe-detox-9-16788535240581.webp?f=png" />                    </a>
                </div>
                                <div class="testimonial-item ">
                    <a href="https://fitfood.vn/img/0x1080/images/356212643-645914697582223-8858909892311272615-n-16922426387753.jpeg" data-lightbox="testimonial">
                        <img alt="Câu chuyện khách hàng-29" src="https://fitfood.vn/img/205x205/images/untitled-design-5-16922426387863.webp?f=png" />                    </a>
                </div>
                                <div class="testimonial-item ">
                    <a href="https://fitfood.vn/img/0x1080/images/review-fitfood-giam-can-3-1751465483401.jpg" data-lightbox="testimonial">
                        <img alt="Câu chuyện khách hàng-30" src="https://fitfood.vn/img/205x205/images/review-fitfood-giam-can-31-17514654834271.webp?f=jpg" />                    </a>
                </div>
                                <div class="testimonial-item ">
                    <a href="https://fitfood.vn/img/0x1080/images/z6246039625755-be94a207a729c21e2bd4f6647e7334c0-17373500524374.gif" data-lightbox="testimonial">
                        <img alt="Câu chuyện khách hàng-31" src="https://fitfood.vn/img/205x205/images/untitled-3-1737350052474.webp?f=gif" />                    </a>
                </div>
                                <div class="testimonial-item ">
                    <a href="https://fitfood.vn/img/0x1080/images/364707834-665966525577040-2335237567905602921-n-16922423064952.jpeg" data-lightbox="testimonial">
                        <img alt="Câu chuyện khách hàng-32" src="https://fitfood.vn/img/205x205/images/untitled-design-3-16922423065066.webp?f=png" />                    </a>
                </div>
                                <div class="testimonial-item ">
                    <a href="https://fitfood.vn/img/0x1080/images/screen-shot-2023-11-18-at-173423-17003037228107.png" data-lightbox="testimonial">
                        <img alt="Câu chuyện khách hàng-33" src="https://fitfood.vn/img/205x205/images/385883239-3503294706604199-6436277285410019929-n-17003037228334.webp?f=jpeg" />                    </a>
                </div>
                                <div class="testimonial-item ">
                    <a href="https://fitfood.vn/img/0x1080/images/img-1194-16922432123783.png" data-lightbox="testimonial">
                        <img alt="Câu chuyện khách hàng-34" src="https://fitfood.vn/img/205x205/images/untitled-design-7-16922430280091.webp?f=png" />                    </a>
                </div>
                                <div class="testimonial-item ">
                    <a href="https://fitfood.vn/img/0x1080/images/screen-shot-2023-12-02-at-124314-17014958061663.png" data-lightbox="testimonial">
                        <img alt="Câu chuyện khách hàng-35" src="https://fitfood.vn/img/205x205/images/fitfood-citigym-yoga-1-17014958061823.webp?f=jpg" />                    </a>
                </div>
                                <div class="testimonial-item ">
                    <a href="https://fitfood.vn/img/0x1080/images/screen-shot-2024-02-29-at-115414-17091824660075.png" data-lightbox="testimonial">
                        <img alt="Câu chuyện khách hàng-36" src="https://fitfood.vn/img/205x205/images/yuu09832-edit-1-17091824660311.webp?f=png" />                    </a>
                </div>
                                <div class="testimonial-item ">
                    <a href="https://fitfood.vn/img/0x1080/images/review-fitfood-31-17514653402372.jpg" data-lightbox="testimonial">
                        <img alt="Câu chuyện khách hàng-37" src="https://fitfood.vn/img/205x205/images/review-fitfood-3-17514653402674.webp?f=jpg" />                    </a>
                </div>
                                <div class="testimonial-item ">
                    <a href="https://fitfood.vn/img/0x1080/images/screenshot-20201030-215530-facebook-16040699181733.jpg" data-lightbox="testimonial">
                        <img alt="Câu chuyện khách hàng-38" src="https://fitfood.vn/img/205x205/images/recipe-detox-3-16788502338455.webp?f=png" />                    </a>
                </div>
                                <div class="testimonial-item ">
                    <a href="https://fitfood.vn/img/0x1080/images/untitled-design-9-16788559734405.png" data-lightbox="testimonial">
                        <img alt="Câu chuyện khách hàng-39" src="https://fitfood.vn/img/205x205/images/recipe-detox-12-16788559734466.webp?f=png" />                    </a>
                </div>
                                <div class="testimonial-item ">
                    <a href="https://fitfood.vn/img/0x1080/images/351483765-1012144213528072-8104367635288915713-n-16922428272551.png" data-lightbox="testimonial">
                        <img alt="Câu chuyện khách hàng-40" src="https://fitfood.vn/img/205x205/images/untitled-design-6-16922428272681.webp?f=png" />                    </a>
                </div>
                                <div class="testimonial-item ">
                    <a href="https://fitfood.vn/img/0x1080/images/fitfood-post-1-16922576406384.png" data-lightbox="testimonial">
                        <img alt="Câu chuyện khách hàng-41" src="https://fitfood.vn/img/205x205/images/untitled-design-8-16922568787806.webp?f=png" />                    </a>
                </div>
                                <div class="testimonial-item ">
                    <a href="https://fitfood.vn/img/0x1080/images/5-17003043491009.png" data-lightbox="testimonial">
                        <img alt="Câu chuyện khách hàng-42" src="https://fitfood.vn/img/205x205/images/feedback-17003043491161.webp?f=png" />                    </a>
                </div>
                                <div class="testimonial-item ">
                    <a href="https://fitfood.vn/img/0x1080/images/review-fitfood-giam-can-1751465363662.jpg" data-lightbox="testimonial">
                        <img alt="Câu chuyện khách hàng-43" src="https://fitfood.vn/img/205x205/images/review-fitfood-giam-can-11-17514653636954.webp?f=jpg" />                    </a>
                </div>
                                <div class="testimonial-item ">
                    <a href="https://fitfood.vn/img/0x1080/images/5-17373520593086.gif" data-lightbox="testimonial">
                        <img alt="Câu chuyện khách hàng-44" src="https://fitfood.vn/img/205x205/images/untitled-5-1737352059336.webp?f=gif" />                    </a>
                </div>
                                <div class="testimonial-item ">
                    <a href="https://fitfood.vn/img/0x1080/images/393257680-707564021417290-8784056869319395349-n-1700305399772.jpeg" data-lightbox="testimonial">
                        <img alt="Câu chuyện khách hàng-45" src="https://fitfood.vn/img/205x205/images/wer-17003053997881.webp?f=png" />                    </a>
                </div>
                                <div class="testimonial-item ">
                    <a href="https://fitfood.vn/img/0x1080/images/357497989-650192427154450-6680058188453543747-n-16922424873813.jpeg" data-lightbox="testimonial">
                        <img alt="Câu chuyện khách hàng-46" src="https://fitfood.vn/img/205x205/images/untitled-design-4-16922424873944.webp?f=png" />                    </a>
                </div>
                                <div class="testimonial-item ">
                    <a href="https://fitfood.vn/img/0x1080/images/screen-shot-2023-11-18-at-173828-17003039405904.png" data-lightbox="testimonial">
                        <img alt="Câu chuyện khách hàng-47" src="https://fitfood.vn/img/205x205/images/avatar-17003039406127.webp?f=png" />                    </a>
                </div>
                                <div class="testimonial-item ">
                    <a href="https://fitfood.vn/img/0x1080/images/z6246002729724-2ff95eb3a42300076514811851a88fdc-17373487805507.gif" data-lightbox="testimonial">
                        <img alt="Câu chuyện khách hàng-48" src="https://fitfood.vn/img/205x205/images/untitled-4-17373487806009.webp?f=gif" />                    </a>
                </div>
                                <div class="testimonial-item ">
                    <a href="https://fitfood.vn/img/0x1080/images/66-17373520749542.gif" data-lightbox="testimonial">
                        <img alt="Câu chuyện khách hàng-49" src="https://fitfood.vn/img/205x205/images/6-17373520749933.webp?f=gif" />                    </a>
                </div>
                            </div>
        </div>
    </section>
    <!-- partners -->
        <section class="partners">
        <div class="container">
            <h2 class="title title-center pb-4 mb-4">Đối tác</h2>
            <p class="text-center">Chúng tôi hợp tác với các nhà cung cấp hàng đầu để đảm bảo chất lượng trải nghiệm tốt nhất</p>
            <div class="text-center">
                <div class="row">
                                    <div class="col-2">
                        <img alt="Đối tác-0" border="0" class="img-fluid" src="https://fitfood.vn/img/160x0/images/aha-169528796639.png" />                    </div>
                                    <div class="col-2">
                        <img alt="Đối tác-1" border="0" class="img-fluid" src="https://fitfood.vn/img/160x0/images/lacaph-16952879752401.png" />                    </div>
                                    <div class="col-2">
                        <img alt="Đối tác-2" border="0" class="img-fluid" src="https://fitfood.vn/img/160x0/images/fitpack-16952879844178.png" />                    </div>
                                    <div class="col-2">
                        <img alt="Đối tác-3" border="0" class="img-fluid" src="https://fitfood.vn/img/160x0/images/logo-star-kombucha-16014620887392.png" />                    </div>
                                    <div class="col-2">
                        <img alt="Đối tác-4" border="0" class="img-fluid" src="https://fitfood.vn/img/160x0/images/megamarket-15625154298117.png" />                    </div>
                                    <div class="col-2">
                        <img alt="Đối tác-5" border="0" class="img-fluid" src="https://fitfood.vn/img/160x0/images/payoo-15625154518087.png" />                    </div>
                                    <div class="col-2">
                        <img alt="Đối tác-6" border="0" class="img-fluid" src="https://fitfood.vn/img/160x0/images/nakayama-15625155077656.png" />                    </div>
                                    <div class="col-2">
                        <img alt="Đối tác-7" border="0" class="img-fluid" src="https://fitfood.vn/img/160x0/images/jafpa-15694083848488.png" />                    </div>
                                    <div class="col-2">
                        <img alt="Đối tác-8" border="0" class="img-fluid" src="https://fitfood.vn/img/160x0/images/vineco-15685206922536.png" />                    </div>
                                    <div class="col-2">
                        <img alt="Đối tác-9" border="0" class="img-fluid" src="https://fitfood.vn/img/160x0/images/logo-15686011892226.png" />                    </div>
                                </div>
            </div>
        </div>
    </section>
        <!-- clients -->
        <section class="clients">
        <div class="container">
            <h2 class="title title-center pb-4 mb-4">Khách hàng</h2>
            <p class="text-center">Fitfood tự hào là lựa chọn ưu tiên hàng đầu của các doanh nghiệp lớn trong và ngoài nước<br>(Click vào logo để xem hình ảnh thực tế sự kiện)<br>Liên hệ business@fitfood.vn để đặt tiệc ngay</p>
            <div class="text-center">
                <div class="row">
                                            <div class="col-2">
                            <img alt="Khách hàng-0" border="0" class="img-fluid" data-link="https://fitfood.vn/tin-tuc/nap-lai-nang-luong-voi-chuoi-recharge-day-cung-loreal" src="https://fitfood.vn/img/160x0/images/logo-loreal-viet-nam-den-transparent-1-17185159878491.png" />                        </div>
                                            <div class="col-2">
                            <img alt="Khách hàng-1" border="0" class="img-fluid" data-link="https://fitfood.vn/tin-tuc/fitfood-x-astrazeneca-hoi-thao-khoa-hoc-tai-dai-hoc-y-duoc" src="https://fitfood.vn/img/160x0/images/logo-astra-17157549891201.png" />                        </div>
                                            <div class="col-2">
                            <img alt="Khách hàng-2" border="0" class="img-fluid" data-link="https://fitfood.vn/tin-tuc/fitfood-nang-luong-cho-cac-chien-binh-pubg-mobile-tai-pmnc-2023" src="https://fitfood.vn/img/160x0/images/tagline-lock-up-1-orangevng-master-17091812782907.png" />                        </div>
                                            <div class="col-2">
                            <img alt="Khách hàng-3" border="0" class="img-fluid" data-link="https://fitfood.vn/tin-tuc/fitfood-x-tdic-buoi-trua-tran-day-nang-luong-cung-tdic" src="https://fitfood.vn/img/160x0/images/logo-17091801011997.webp" />                        </div>
                                            <div class="col-2">
                            <img alt="Khách hàng-4" border="0" class="img-fluid" data-link="https://fitfood.vn/tin-tuc/fitfood-x-citigym-hoat-dong-yoga-buffet-finger-food" src="https://fitfood.vn/img/160x0/images/logocitigym-1709180160773.png" />                        </div>
                                            <div class="col-2">
                            <img alt="Khách hàng-5" border="0" class="img-fluid" data-link="https://fitfood.vn/tin-tuc/fitfood-x-tiki-mung-ngay-phu-nu-viet-nam-2010" src="https://fitfood.vn/img/160x0/images/logo-tiki-png-16039561972419.png" />                        </div>
                                            <div class="col-2">
                            <img alt="Khách hàng-6" border="0" class="img-fluid" data-link="https://www.facebook.com/fitfoodvietnam/posts/2011782072223323" src="https://fitfood.vn/img/160x0/images/coop-15694086865358.png" />                        </div>
                                            <div class="col-2">
                            <img alt="Khách hàng-7" border="0" class="img-fluid" data-link="https://fitfood.vn/tin-tuc/bua-trua-fitfood-mon-qua-dinh-duong-gui-den-nestle" src="https://fitfood.vn/img/160x0/images/nestlelogo-with-wordmark-black-244x250mm-cmyk-17165185082292.png" />                        </div>
                                            <div class="col-2">
                            <img alt="Khách hàng-8" border="0" class="img-fluid" data-link="https://fitfood.vn/tin-tuc/fitfood-vn-dong-hanh-cung-chi-dep-dap-gio-2024-bua-tiec-dinh-duong-day-mau-sac" src="https://fitfood.vn/img/160x0/images/chi-dep-dap-gio-2024-logo-17367456477472.png" />                        </div>
                                            <div class="col-2">
                            <img alt="Khách hàng-9" border="0" class="img-fluid" data-link="https://www.facebook.com/fitfoodvietnam/posts/pfbid02K11bQn69ueZ5tyZqNdhR7XBCoUGRnKiBJ3AY3EvGuHsY2cYDf8hFycAmfdYWkW2Jl" src="https://fitfood.vn/img/160x0/images/salonpassvg-16720344297781.png" />                        </div>
                                            <div class="col-2">
                            <img alt="Khách hàng-10" border="0" class="img-fluid" data-link="https://fitfood.vn/tin-tuc/fitfood-dong-hanh-cung-anh-trai-vuot-ngan-chong-gai-nang-luong-doi-dao-cho-hanh-trinh-chinh-phuc" src="https://fitfood.vn/img/160x0/images/anh-trai-logo-17367457924108.webp" />                        </div>
                                            <div class="col-2">
                            <img alt="Khách hàng-11" border="0" class="img-fluid" data-link="https://www.facebook.com/fitfoodvietnam/posts/2219584664776395?__tn__=-R" src="https://fitfood.vn/img/160x0/images/saigon-heat-15685204405616.png" />                        </div>
                                            <div class="col-2">
                            <img alt="Khách hàng-12" border="0" class="img-fluid" src="https://fitfood.vn/img/160x0/images/14511723-gigamalllogo-01-15685392974799.png" />                        </div>
                                            <div class="col-2">
                            <img alt="Khách hàng-13" border="0" class="img-fluid" data-link="https://fitfood.vn/tin-tuc/fitfood-x-shinhan-bank-bua-trua-dinh-duong-thang-hang-tinh-than" src="https://fitfood.vn/img/160x0/images/logo-shinhan-bank-17266454712133.png" />                        </div>
                                            <div class="col-2">
                            <img alt="Khách hàng-14" border="0" class="img-fluid" data-link="https://www.facebook.com/media/set/?set=a.2797625236972332&amp;type=3" src="https://fitfood.vn/img/160x0/images/techcomrun-small-15753651582381.png" />                        </div>
                                            <div class="col-2">
                            <img alt="Khách hàng-15" border="0" class="img-fluid" data-link="https://www.facebook.com/fitfoodvietnam/posts/1615840338484167" src="https://fitfood.vn/img/160x0/images/aia-logo-15685202507269.png" />                        </div>
                                            <div class="col-2">
                            <img alt="Khách hàng-16" border="0" class="img-fluid" data-link="https://www.facebook.com/fitfoodvietnam/posts/2319944984740362?__tn__=-R" src="https://fitfood.vn/img/160x0/images/vba-1-15685395342121.png" />                        </div>
                                            <div class="col-2">
                            <img alt="Khách hàng-17" border="0" class="img-fluid" data-link="https://fitfood.vn/tin-tuc/fitfood-va-joy-foundation-hoa-am-tron-ven-tai-tron-concert" src="https://fitfood.vn/img/160x0/images/unnamed-17266460433216.png" />                        </div>
                                            <div class="col-2">
                            <img alt="Khách hàng-18" border="0" class="img-fluid" data-link="https://fitfood.vn/tin-tuc/fitfood-x-benh-vien-nhi-dong-1-mot-ngay-tran-day-nang-luong-va-suc-khoe" src="https://fitfood.vn/img/160x0/images/logo-benh-vien-nhi-dong-1-17367459219188.webp" />                        </div>
                                            <div class="col-2">
                            <img alt="Khách hàng-19" border="0" class="img-fluid" data-link="https://fitfood.vn/tin-tuc/tiec-tea-break-ky-niem-10-nam-cung-nutrition-depot-vietnam" src="https://fitfood.vn/img/160x0/images/img-9928-17266445643703.PNG" />                        </div>
                                    </div>
            </div>
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

<script src="https://fitfood.vn/js/jquery.bxslider.min.js?v=2026033101"></script>
<script src="https://fitfood.vn/js/slick.min.js?v=2026033101"></script>
<script src="https://fitfood.vn/js/plugins/magnific-popup.min.js?v=2026033101"></script>
<script src="https://fitfood.vn/js/plugins/lightbox/lightbox.js?v=2026033101"></script>
<script src="https://fitfood.vn/js/plugins/gallery-thumb/gallery-thumb.js?v=2026033101"></script>
<script src="https://fitfood.vn/js/plugins/jquery.validation/jquery.validate.min.js?v=2026033101"></script>
<script src="https://fitfood.vn/js/underscore-min.js?v=2026033101"></script>
<script src="https://fitfood.vn/js/plugins.js?v=2026033101"></script>
<script src="https://fitfood.vn/js/modules/tracking.js"></script>
    <script type="text/javascript" src="https://fitfood.vn/js/modules/home.js?v=2026033101"></script>
    <!-- package.js đã gỡ -- sẽ thay bằng cart local -->
    <script>
        lightbox.option({
            disableScrolling: true,
            alwaysShowNavOnTouchDevices: true
        })

        const onImgLoad = function(selector, callback){
            $(selector).each(function(){
                if (this.complete || /*for IE 10-*/ $(this).height() > 0) {
                    callback.apply(this);
                }
                else {
                    $(this).on('load', function(){
                        callback.apply(this);
                    });
                }
            });
        };

        var timer = null;
        var totalImgLoad = 0;
        $(function() {
            function reloadSize()
            {
                if (timer !== null) {
                    clearTimeout(timer);
                    timer = null;
                }
                timer = setTimeout(function () {
                    reSize();
                }, 600)
            }

            function reSize()
            {
                const view = viewport();
                const height = $('.testimonial-first img').height();
                if (view.width >= 767) {
                    $('.testimonial-gallery').height(height * 3 + 40 + 4);
                } else {
                    $('.testimonial-gallery').height(height * 3 + 30 + 4);
                }
            }

            function viewport() {
                var e = window, a = 'inner';
                if (!('innerWidth' in window )) {
                    a = 'client';
                    e = document.documentElement || document.body;
                }
                return { width : e[ a+'Width' ] , height : e[ a+'Height' ] };
            }

            onImgLoad('.testimonial-item img', function(){
                totalImgLoad++;
                if ($('.testimonial-item img').length == totalImgLoad) {
                    reloadSize();
                }
            });

            $(window).resize(function () {
                reloadSize()
            })
        })
    </script>
    <!-- cart-order.js đã gỡ -- sẽ thay bằng cart local -->
    <!-- Auth scripts đã được thay bằng hệ thống local -->
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
            ajax.get($(promoPopup).data('url'), {name_promo: 'sem-gdn'}, function (res) {
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


<!-- url-add-cart đã gỡ -- sẽ thay bằng cart local -->
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
<!-- ===== POPUP ĐĂNG KÝ & ĐĂNG NHẬP ===== -->
<?php require_once __DIR__ . '/includes/register_modal.php'; ?>
<?php require_once __DIR__ . '/includes/login_modal.php'; ?>
<script src="assets/js/register.js"></script>
<script src="assets/js/login.js"></script>

</body>
</html>

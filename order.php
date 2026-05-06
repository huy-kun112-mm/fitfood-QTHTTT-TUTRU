<?php
// ===== Khởi động session để hiển thị trạng thái đăng nhập =====
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// ===== Lấy dữ liệu sản phẩm từ DB để render =====
require_once __DIR__ . '/lib/products.php';
$packages_groups = $products_groups = [];
$db_down = ($pdo === null);
if ($pdo !== null) {
    try {
        $packages_groups = get_products_grouped($pdo, ['type' => 'package']);
        $products_groups = get_products_grouped($pdo, ['type' => 'product']);
    } catch (PDOException $e) {
        $db_down = true;
        error_log('[order.php] DB query failed: ' . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="JJFoKhWcYUadbQ3eLOwBtWkeIzKbpzmjMV9Kk91T">
    <meta name="lang" content="vi">

    <title>Đặt hàng đơn giản - Fitfood VN</title>
                    <meta name="description" content="Chỉ cần 1 lần đặt hàng, Fitfood sẽ giao tận nơi các bữa ăn Healthy mỗi ngày">
                            <meta name="keywords" content="dat hang fitfood">
            
    <link rel="icon" href="/favicon.ico">


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

<body data-page="order" data-device="desktop"
    class="" data-url="order.php"
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
     <a  class="active nav-link" href="order.php">
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
    <input type="hidden" id="route-lang" value="#">
</nav>

<main class="">
<?php if ($db_down): ?>
    <div class="container" style="margin-top: 80px;">
        <div role="alert" style="background:#fff3cd;border:1px solid #ffc107;color:#856404;padding:14px 18px;border-radius:6px;text-align:center;font-weight:500;">
            Hiện chưa tải được danh sách sản phẩm. Vui lòng thử lại sau ít phút.
        </div>
    </div>
<?php endif; ?>
    <section class="order-weekly">
        <div class="container">
            <h1 class="title title-center pb-4 mb-4">CHỌN <strong>GÓI ĂN</strong></h1>
            <p>Fitfood cung cấp nhiều gói ăn và thực phẩm dùng kèm đa dạng phù hợp với mọi nhu cầu của bạn</p>

            <div class="row mb-4">
                <div class="col-md-6 col-lg-5 mx-auto">
                    <div class="search-control" style="position:relative;">
                        <input type="text"
                               id="product-search"
                               class="form-control s-search"
                               placeholder="Tìm sản phẩm theo tên..."
                               autocomplete="off">
                    </div>
                </div>
            </div>
            <div id="search-no-results" class="text-center py-4"
                 style="display:none;font-size:18px;color:#666;font-weight:500;">
                Không tìm thấy sản phẩm phù hợp
            </div>

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
    <section class="top-inner order-additional">
        <div class="container">
<?php foreach ($products_groups as $cat): ?>
            <h2 class="title title-center pb-4 mb-4"><?= htmlspecialchars(mb_strtoupper($cat['name'], 'UTF-8')) ?></h2>
            <div class="products">
                <div class="row product-listing">
<?php foreach ($cat['products'] as $p) render_product_card($p); ?>
                </div>
            </div>
<?php endforeach; ?>
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
                                            <li><a href="#">Chính Sách Quy Định Chung</a></li>
                                            <li><a href="#">Quy Định Hình Thức Thanh Toán</a></li>
                                            <li><a href="#">Chính Sách Vận Chuyển Giao Hàng</a></li>
                                            <li><a href="#">Chính Sách Bảo Mật Thông Tin</a></li>
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
<div style="display: none" id="ads-popup" data-url="#"></div>

<!-- Các modal sign-in/sign-up/forgot-password cũ đã được thay bằng popup mới
     ở includes/login_modal.php và includes/register_modal.php (xem cuối trang). -->

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
                    <a href="#" class="btn btn-primary btn-block float-right">Tôi không muốn</a>
                </div>
            </div>
        </div>
    </div>

<script src="https://code.jquery.com/jquery-2.2.4.min.js" integrity="sha256-BbhdlvQf/xTY9gja0Dq3HiwQF8LaCRTXxZKRutelT44=" crossorigin="anonymous"></script>
<script src="https://fitfood.vn/js/bootstrap.min.js?v=2026033101"></script>
<script src="https://fitfood.vn/js/init.js?v=2026033101"></script>

<script src="https://fitfood.vn/js/plugins/gallery-thumb/gallery-thumb.js?v=2026033101"></script>
<script src="https://fitfood.vn/js/jquery.cookie.js?v=2026033101"></script>
<script src="https://fitfood.vn/js/plugins/jquery.validation/jquery.validate.min.js?v=2026033101"></script>
<script src="https://fitfood.vn/js/underscore-min.js?v=2026033101"></script>
<script src="https://fitfood.vn/js/plugins.js?v=2026033101"></script>
<script src="https://fitfood.vn/js/modules/tracking.js"></script>
    <script type="text/javascript" src="https://fitfood.vn/js/modules/order/package.js?v=2026033101"></script>
    <script type="text/javascript" src="https://fitfood.vn/js/modules/menu/menu_packages.js?v=2026033101"></script>
    <script type="text/javascript" src="https://fitfood.vn/js/modules/order/cart-order.js?v=2026033101"></script>
        <script src="https://fitfood.vn/js/modules/auth/sign-up.js"></script>
    <script src="https://fitfood.vn/js/modules/auth/sign-in.js"></script>
    <script src="https://fitfood.vn/js/modules/auth/forgot-password.js"></script>
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

<!-- ===== POPUP ĐĂNG KÝ & ĐĂNG NHẬP ===== -->
<?php require_once __DIR__ . '/includes/register_modal.php'; ?>
<?php require_once __DIR__ . '/includes/login_modal.php'; ?>
<script src="assets/js/register.js"></script>
<script src="assets/js/login.js"></script>

<script>
// Live filter sản phẩm trên trang đặt hàng. Tìm theo tên (data-name trên .listing-item),
// bỏ dấu tiếng Việt để gõ "goi fit" cũng tìm được "Gói FIT".
(function () {
    var input = document.getElementById('product-search');
    if (!input) return;

    var noResults    = document.getElementById('search-no-results');
    var packageBlock = document.querySelector('.order-weekly .products');
    var categoryGroups = [];
    document.querySelectorAll('.order-additional > .container > h2').forEach(function (h2) {
        var next = h2.nextElementSibling;
        if (next && next.classList.contains('products')) {
            categoryGroups.push({ h2: h2, block: next });
        }
    });
    var allCols = document.querySelectorAll('.product-listing > .col-md-3');

    function normalize(s) {
        return (s || '').toString()
            .toLowerCase()
            .normalize('NFD')
            .replace(/[̀-ͯ]/g, '')
            .replace(/đ/g, 'd')
            .trim();
    }

    function blockHasVisibleCard(block) {
        var cols = block.querySelectorAll('.col-md-3');
        for (var i = 0; i < cols.length; i++) {
            if (cols[i].style.display !== 'none') return true;
        }
        return false;
    }

    function applyFilter() {
        var q = normalize(input.value);
        var totalVisible = 0;

        allCols.forEach(function (col) {
            var item = col.querySelector('.listing-item');
            var name = item ? normalize(item.getAttribute('data-name')) : '';
            var match = q === '' || name.indexOf(q) !== -1;
            col.style.display = match ? '' : 'none';
            if (match) totalVisible++;
        });

        // Ẩn h2 + block của category nếu không có card nào hiện
        categoryGroups.forEach(function (g) {
            var visible = blockHasVisibleCard(g.block);
            g.h2.style.display    = visible ? '' : 'none';
            g.block.style.display = visible ? '' : 'none';
        });

        // Ẩn block packages nếu không có card nào (tiêu đề "CHỌN GÓI ĂN" giữ nguyên)
        if (packageBlock) {
            packageBlock.style.display = blockHasVisibleCard(packageBlock) ? '' : 'none';
        }

        if (noResults) {
            noResults.style.display = (q !== '' && totalVisible === 0) ? '' : 'none';
        }
    }

    input.addEventListener('input', applyFilter);
})();
</script>

</body>
</html>

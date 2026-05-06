<?php
// ===== Khởi động session để hiển thị trạng thái đăng nhập =====
if (session_status() === PHP_SESSION_NONE) { session_start(); }
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="JJFoKhWcYUadbQ3eLOwBtWkeIzKbpzmjMV9Kk91T">
    <meta name="lang" content="vi">

    <title>Hình ảnh</title>
    
    <link rel="icon" href="/favicon.ico">
    <link rel="canonical" href="#" />
    <link rel="alternate" hreflang="vi" href="#" />


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
    class="" data-url="look.php"
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
        <form action="" class="form-search">
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
     <a  class="active nav-link" href="look.php">
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
    <input type="hidden" id="route-lang" value="lang">
</nav>

<main class="">
    <section class="top-inner pt-0 news-page">
        <div class="container">
                        <div class="row">
                <div class="col-12">
                    <h1 class="title cate-title">Sự kiện</h1>
                </div>
            </div>
            <div class="row mt-4 row-v2">
    <div class="col-lg-8 col-md-12">
        <div class="row news-category">
                    <div class="col-lg-3 col-md-6 "><a href="#" title="Nấu Ăn">Nấu Ăn</a></div>
                    <div class="col-lg-3 col-md-6 active"><a href="#" title="Sự Kiện">Sự Kiện</a></div>
                    <div class="col-lg-3 col-md-6 "><a href="#" title="Khuyến Mãi">Khuyến Mãi</a></div>
                    <div class="col-lg-3 col-md-6 "><a href="#" title="Thông Báo">Thông Báo</a></div>
                    <div class="col-lg-3 col-md-6 "><a href="#" title="Thực Đơn">Thực Đơn</a></div>
                    <div class="col-lg-3 col-md-6 "><a href="#" title="Bài Tập">Bài Tập</a></div>
                    <div class="col-lg-3 col-md-6 "><a href="#" title="Giảm Cân">Giảm Cân</a></div>
                    <div class="col-lg-3 col-md-6 "><a href="#" title="Chế Độ Ăn">Chế Độ Ăn</a></div>
                </div>
    </div>
    <div class="col-lg-4 col-md-12">
        <div class="row">
    <div class="col-12 form-group">
        <form action="" class="form-search">
            <div class="search-control s-news">
                <input type="text" class="form-control s-search" name="s" placeholder="Tìm kiếm" value="">
            </div>
        </form>
    </div>
</div>
    </div>
</div>

            <div class="news-list">
                <div class="row">
                                        <div class="col-lg-4 col-md-6 news-item-wrap">
                        <div class="news-item">
                            <div class="thumb">
                                <a href="#" class="thumb">
                                    <img src="https://fitfood.vn/img/358x268/images/fitfood-miss-world-2025-1-thumb-1776423310534.jpg" alt="Fitfood x Miss World Việt Nam 2025 — Đồng Hành Dinh Dưỡng Cho Hành Trình Tôn Vinh Nhan Sắc" loading="lazy">
                                </a>
                            </div>

                            <div class="news-content">
                                <h2 class="title"><a href="#">Fitfood x Miss World Việt Nam 2025 — Đồng Hành Dinh Dưỡng Cho Hành Trình Tôn Vinh Nhan Sắc</a></h2>
                                <p class="date">30/03/2026</p>
                                <p class="intro">Fitfood chính thức là đối tác dinh dưỡng của Miss World Việt Nam 2025, chăm sóc từng bữa ăn lành mạnh cho các thí sinh. Khám phá hành trình đồng hành đặc biệt này.</p>
                            </div>
                        </div>
                    </div>
                                        <div class="col-lg-4 col-md-6 news-item-wrap">
                        <div class="news-item">
                            <div class="thumb">
                                <a href="#" class="thumb">
                                    <img src="https://fitfood.vn/img/358x268/images/thumb-event-fitfood-x-faraday-4-1773213566369.jpg" alt="Một góc bàn Happy Hour đầy màu sắc: Fitfood mang gì đến event văn phòng của Faraday?" loading="lazy">
                                </a>
                            </div>

                            <div class="news-content">
                                <h2 class="title"><a href="#">Một góc bàn Happy Hour đầy màu sắc: Fitfood mang gì đến event văn phòng của Faraday?</a></h2>
                                <p class="date">28/02/2026</p>
                                <p class="intro">Happy Hour của Faraday tuần qua có một góc bàn nhỏ toàn màu xanh, cam, đỏ – nơi Fitfood chuẩn bị hơn 300 phần salad cùng các Happy Box cho sự kiện. Một ví dụ nhỏ về cách catering cho event văn phòng có thể vừa gọn gàng, vừa chỉn chu.</p>
                            </div>
                        </div>
                    </div>
                                        <div class="col-lg-4 col-md-6 news-item-wrap">
                        <div class="news-item">
                            <div class="thumb">
                                <a href="#" class="thumb">
                                    <img src="https://fitfood.vn/img/358x268/images/thumbevent-fitfood-fireapps-17732160486817.jpg" alt="Một buổi sáng 7h tại FireApps: gần 100 phần hủ tiếu Nam Vang nóng hổi được chuẩn bị như thế nào?" loading="lazy">
                                </a>
                            </div>

                            <div class="news-content">
                                <h2 class="title"><a href="#">Một buổi sáng 7h tại FireApps: gần 100 phần hủ tiếu Nam Vang nóng hổi được chuẩn bị như thế nào?</a></h2>
                                <p class="date">23/02/2026</p>
                                <p class="intro">Từ sáng sớm, Fitfood đã chuẩn bị gần 100 phần hủ tiếu Nam Vang nóng hổi và giao đến văn phòng của FireApps lúc 7h, đóng gói gọn gàng để mọi người có thể thưởng thức ngay tại bàn.</p>
                            </div>
                        </div>
                    </div>
                                        <div class="col-lg-4 col-md-6 news-item-wrap">
                        <div class="news-item">
                            <div class="thumb">
                                <a href="#" class="thumb">
                                    <img src="https://fitfood.vn/img/358x268/images/thumb-event-fitfood-fas-3-17732151636933.jpg" alt="Bữa trưa văn phòng premium: Fitfood chuẩn bị gì cho đội ngũ của Money Forward Vietnam?" loading="lazy">
                                </a>
                            </div>

                            <div class="news-content">
                                <h2 class="title"><a href="#">Bữa trưa văn phòng premium: Fitfood chuẩn bị gì cho đội ngũ của Money Forward Vietnam?</a></h2>
                                <p class="date">17/12/2025</p>
                                <p class="intro">Fitfood đồng hành cùng Money Forward Vietnam tổ chức bữa trưa văn phòng premium với menu dinh dưỡng. Giải pháp catering doanh nghiệp gọn gàng và hiệu quả.</p>
                            </div>
                        </div>
                    </div>
                                        <div class="col-lg-4 col-md-6 news-item-wrap">
                        <div class="news-item">
                            <div class="thumb">
                                <a href="#" class="thumb">
                                    <img src="https://fitfood.vn/img/358x268/images/thien-nguyen-vui-hoi-trang-ram-thumb-17605174568099.jpg" alt="Thiện nguyện: Vui Hội Trăng Rằm cùng Fitfood tại Mái ấm Tam Bình" loading="lazy">
                                </a>
                            </div>

                            <div class="news-content">
                                <h2 class="title"><a href="#">Thiện nguyện: Vui Hội Trăng Rằm cùng Fitfood tại Mái ấm Tam Bình</a></h2>
                                <p class="date">05/10/2025</p>
                                <p class="intro">Với Fitfood, Trung thu năm nay không chỉ là dịp sum vầy, mà còn là cơ hội để cùng JCI Wonder Woman mang niềm vui và sự sẻ chia đến với các em nhỏ tại Cơ sở Bảo Trợ Trẻ Em Mái Ấm Tam Bình.</p>
                            </div>
                        </div>
                    </div>
                                        <div class="col-lg-4 col-md-6 news-item-wrap">
                        <div class="news-item">
                            <div class="thumb">
                                <a href="#" class="thumb">
                                    <img src="https://fitfood.vn/img/358x268/images/banner-event-fitfood-ahamove-tiec-sinh-nhat-17549751606059.jpg" alt="|Fitfood x Ahamove| Fitfood Đồng Hành Cùng Ahamove Trong Tiệc Sinh Nhật 10 Năm" loading="lazy">
                                </a>
                            </div>

                            <div class="news-content">
                                <h2 class="title"><a href="#">|Fitfood x Ahamove| Fitfood Đồng Hành Cùng Ahamove Trong Tiệc Sinh Nhật 10 Năm</a></h2>
                                <p class="date">08/08/2025</p>
                                <p class="intro">Trong dịp kỷ niệm sinh nhật 10 tuổi, Ahamove đã “chọn mặt gửi bụng” cho Fitfood là nơi cung cấp bữa ăn tiệc sinh nhật</p>
                            </div>
                        </div>
                    </div>
                                        <div class="col-lg-4 col-md-6 news-item-wrap">
                        <div class="news-item">
                            <div class="thumb">
                                <a href="#" class="thumb">
                                    <img src="https://fitfood.vn/img/358x268/images/thien-nguyen-moi-truong-fitfood-x-sai-gon-xanh-3-17532459090525.jpg" alt="Fitfood x Sài Gòn Xanh: Lan toả hành trình “sống lành” từ bữa ăn đến môi trường" loading="lazy">
                                </a>
                            </div>

                            <div class="news-content">
                                <h2 class="title"><a href="#">Fitfood x Sài Gòn Xanh: Lan toả hành trình “sống lành” từ bữa ăn đến môi trường</a></h2>
                                <p class="date">21/07/2025</p>
                                <p class="intro">Cuối tuần rồi, chúng mình đã có mặt tại Bình Thạnh cùng tổ chức Sài Gòn Xanh – nhóm thiện nguyện trẻ đang ngày ngày cặm cụi dọn rác, trả lại vẻ trong lành cho những dòng kênh tưởng chừng bị lãng quên.</p>
                            </div>
                        </div>
                    </div>
                                        <div class="col-lg-4 col-md-6 news-item-wrap">
                        <div class="news-item">
                            <div class="thumb">
                                <a href="#" class="thumb">
                                    <img src="https://fitfood.vn/img/358x268/images/banner-fitfood-event-17503105021989.jpg" alt="Bữa trưa đầy sắc màu cùng buffet healthy tại văn phòng Danone" loading="lazy">
                                </a>
                            </div>

                            <div class="news-content">
                                <h2 class="title"><a href="#">Bữa trưa đầy sắc màu cùng buffet healthy tại văn phòng Danone</a></h2>
                                <p class="date">18/06/2025</p>
                                <p class="intro">Danone – thương hiệu toàn cầu về dinh dưỡng đến từ Pháp – đã chọn Fitfood để mang đến một bữa buffet văn phòng trọn vẹn, vừa đầy đủ dinh dưỡng, vừa tiết kiệm thời gian tổ chức.</p>
                            </div>
                        </div>
                    </div>
                                        <div class="col-lg-4 col-md-6 news-item-wrap">
                        <div class="news-item">
                            <div class="thumb">
                                <a href="#" class="thumb">
                                    <img src="https://fitfood.vn/img/358x268/images/banner-event-fitfood-truong-arthome-17518822838397.jpg" alt="Fitfood x Arthome: Hành trình “đóng gói yêu thương” trong từng phần ăn cho các bé mỗi ngày" loading="lazy">
                                </a>
                            </div>

                            <div class="news-content">
                                <h2 class="title"><a href="#">Fitfood x Arthome: Hành trình “đóng gói yêu thương” trong từng phần ăn cho các bé mỗi ngày</a></h2>
                                <p class="date">02/06/2025</p>
                                <p class="intro">Giữa hàng trăm bữa ăn được chuẩn bị mỗi ngày, có những phần ăn đặc biệt hơn cả – vì chúng dành cho những “khách hàng nhỏ tuổi” rất đặc biệt: các bé trường Arthome - Ngôi nhà ươm mầm và phát triển năng khiếu về nghệ thuật dành cho các bé.</p>
                            </div>
                        </div>
                    </div>
                                        <div class="col-lg-4 col-md-6 news-item-wrap">
                        <div class="news-item">
                            <div class="thumb">
                                <a href="#" class="thumb">
                                    <img src="https://fitfood.vn/img/358x268/images/event-fitfood-naver-4-17515165901179.jpg" alt="FITFOOD x NAVER: 200 BỮA ĂN DINH DƯỠNG - ĐẸP MẮT TẠI VĂN PHÒNG" loading="lazy">
                                </a>
                            </div>

                            <div class="news-content">
                                <h2 class="title"><a href="#">FITFOOD x NAVER: 200 BỮA ĂN DINH DƯỠNG - ĐẸP MẮT TẠI VĂN PHÒNG</a></h2>
                                <p class="date">21/05/2025</p>
                                <p class="intro">Fitfood được đồng hành cùng Naver Việt Nam trong việc cung cấp hơn 200 phần ăn trưa văn phòng cho toàn bộ nhân sự công ty.</p>
                            </div>
                        </div>
                    </div>
                                        <div class="col-lg-4 col-md-6 news-item-wrap">
                        <div class="news-item">
                            <div class="thumb">
                                <a href="#" class="thumb">
                                    <img src="https://fitfood.vn/img/358x268/images/banner-17468496002912.jpg" alt="Tổ chức sự kiện cho 300+ khách? Đây là cách Fitfood đã giúp Thallo xử lý trọn vẹn bữa trưa và hình ảnh thương hiệu" loading="lazy">
                                </a>
                            </div>

                            <div class="news-content">
                                <h2 class="title"><a href="#">Tổ chức sự kiện cho 300+ khách? Đây là cách Fitfood đã giúp Thallo xử lý trọn vẹn bữa trưa và hình ảnh thương hiệu</a></h2>
                                <p class="date">10/05/2025</p>
                                <p class="intro">Fitfood x Thallo: 350 suất ăn healthy trong hội thảo y khoa – Giải pháp ẩm thực dành cho doanh nghiệp hiện đại</p>
                            </div>
                        </div>
                    </div>
                                        <div class="col-lg-4 col-md-6 news-item-wrap">
                        <div class="news-item">
                            <div class="thumb">
                                <a href="#" class="thumb">
                                    <img src="https://fitfood.vn/img/358x268/images/event-fitfood-faraday-sinble-thumb-17452282203893.jpg" alt="Fitfood x Faraday - Sinble: Làm thế nào để một buổi chiều xế cuối tuần biến thành thời gian yêu thích nhất của nhân viên?" loading="lazy">
                                </a>
                            </div>

                            <div class="news-content">
                                <h2 class="title"><a href="#">Fitfood x Faraday - Sinble: Làm thế nào để một buổi chiều xế cuối tuần biến thành thời gian yêu thích nhất của nhân viên?</a></h2>
                                <p class="date">20/04/2025</p>
                                <p class="intro">Cuối tuần, sau những giờ làm việc căng thẳng, một bữa xế nhẹ nhàng chính là món quà tinh thần thiết thực nhất mà doanh nghiệp có thể gửi đến nhân viên.</p>
                            </div>
                        </div>
                    </div>
                                        <div class="col-lg-4 col-md-6 news-item-wrap">
                        <div class="news-item">
                            <div class="thumb">
                                <a href="#" class="thumb">
                                    <img src="https://fitfood.vn/img/358x268/images/event-fitfood-nestle-thumb-17452282943576.jpg" alt="Fitfood x Nestlé: Nếu bạn đang tìm suất ăn healthy cho văn phòng, đây là cách Nestlé đã chọn" loading="lazy">
                                </a>
                            </div>

                            <div class="news-content">
                                <h2 class="title"><a href="#">Fitfood x Nestlé: Nếu bạn đang tìm suất ăn healthy cho văn phòng, đây là cách Nestlé đã chọn</a></h2>
                                <p class="date">16/04/2025</p>
                                <p class="intro">Nestlé, một trong những tập đoàn hàng đầu về dinh dưỡng và sức khỏe, đã tin tưởng lựa chọn Fitfood là đối tác cung cấp bữa ăn văn phòng healthy cho nhân viên.</p>
                            </div>
                        </div>
                    </div>
                                        <div class="col-lg-4 col-md-6 news-item-wrap">
                        <div class="news-item">
                            <div class="thumb">
                                <a href="#" class="thumb">
                                    <img src="https://fitfood.vn/img/358x268/images/event-fitfood-vadp-thumb-17452286000894.jpg" alt="Fitfood x VADP – Tiếp Sức Ngày Thi Bằng Những Bữa Ăn Healthy Chuẩn Vị" loading="lazy">
                                </a>
                            </div>

                            <div class="news-content">
                                <h2 class="title"><a href="#">Fitfood x VADP – Tiếp Sức Ngày Thi Bằng Những Bữa Ăn Healthy Chuẩn Vị</a></h2>
                                <p class="date">31/03/2025</p>
                                <p class="intro">Trong ngày thi quan trọng của các bạn thí sinh thuộc Vietnam Academy of Debate and Public Speaking (VADP), Fitfood rất hân hạnh được tiếp tục trở thành đơn vị cung cấp suất ăn healthy, giúp tiếp thêm năng lượng và tinh thần vững vàng cho các bạn trẻ.</p>
                            </div>
                        </div>
                    </div>
                                        <div class="col-lg-4 col-md-6 news-item-wrap">
                        <div class="news-item">
                            <div class="thumb">
                                <a href="#" class="thumb">
                                    <img src="https://fitfood.vn/img/358x268/images/fitfood-sai-gon-xanh-don-rac-bao-ve-moi-truong-thumb-17452283695534.jpg" alt="Fitfood x Sài Gòn Xanh – Lan Tỏa Năng Lượng Tích Cực Vì Môi Trường" loading="lazy">
                                </a>
                            </div>

                            <div class="news-content">
                                <h2 class="title"><a href="#">Fitfood x Sài Gòn Xanh – Lan Tỏa Năng Lượng Tích Cực Vì Môi Trường</a></h2>
                                <p class="date">23/03/2025</p>
                                <p class="intro">Một ngày Chủ nhật thật đặc biệt khi Fitfood VN có cơ hội đồng hành cùng Sài Gòn Xanh và các bạn sinh viên trẻ nhiệt huyết trong hoạt động dọn dẹp kênh sông Bình Thạnh. Đây không chỉ là một buổi làm sạch môi trường mà còn là dịp để mọi người cùng nhau hành động, lan tỏa ý thức bảo vệ thiên nhiên.</p>
                            </div>
                        </div>
                    </div>
                                    </div>
                <ul class="pagination"><li class="page-item disabled">
        <a class="page-link" href="#" aria-label="Previous">
            <span aria-hidden="true">&laquo;</span>
            <span class="sr-only">Previous</span>
        </a>
    </li><li class="page-item disabled">
        <a class="page-link" href="#">1</a>
    </li><li class="page-item ">
        <a class="page-link" href="#">2</a>
    </li><li class="page-item ">
        <a class="page-link" href="#">3</a>
    </li><li class="page-item ">
        <a class="page-link" href="#">4</a>
    </li><li class="page-item ">
        <a class="page-link" href="#">5</a>
    </li><li class="page-item ">
        <a class="page-link" href="#" aria-label="Next">
            <span aria-hidden="true">&raquo;</span>
            <span class="sr-only">Next</span>
        </a>
    </li></ul>
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
        <form action="" class="form-search">
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
<div style="display: none" id="ads-popup" data-url=""></div>

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
                    <a href="payment.php" class="btn btn-primary btn-block float-right">Tôi không muốn</a>
                </div>
            </div>
        </div>
    </div>

<script src="https://code.jquery.com/jquery-2.2.4.min.js" integrity="sha256-BbhdlvQf/xTY9gja0Dq3HiwQF8LaCRTXxZKRutelT44=" crossorigin="anonymous"></script>
<script src="https://fitfood.vn/js/bootstrap.min.js?v=2026033101"></script>
<script src="https://fitfood.vn/js/init.js?v=2026033101"></script>

<script src="https://fitfood.vn/js/plugins/jquery.validation/jquery.validate.min.js?v=2026033101"></script>
<script src="https://fitfood.vn/js/underscore-min.js?v=2026033101"></script>
<script src="https://fitfood.vn/js/plugins.js?v=2026033101"></script>
<script src="https://fitfood.vn/js/modules/tracking.js"></script>
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


<input type="hidden" id="url-add-cart" value="cart/package/add"/>
<div class="widget-social">
    <span class="widget-ico widget-btn-social"><a href="javascript:void(0)"><img src="https://fitfood.vn/images/ico-chat-bubble.svg?v=2026033101" alt="Fitfood.vn"></a></span>
    <div class="widget-btn-list">
        <div class="widget-btn widget-btn-facebook">
            <span class="widget-ico"><a target="_blank" href="#"><img src="https://fitfood.vn/images/ico-fb-messenger.svg?v=2026033101" alt="Fitfood.vn"></a></span>
        </div>
        <div class="widget-btn widget-btn-zalo">
            <span class="widget-ico"><a target="_blank" href="#"><img src="https://fitfood.vn/images/ico-zalo.svg?v=2026033101" alt="Fitfood.vn"></a></span>
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

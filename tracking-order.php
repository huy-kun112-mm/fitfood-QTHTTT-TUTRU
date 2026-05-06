<?php
/**
 * Trang đơn hàng của user — wire DB.
 * Hiển thị tất cả orders của user, mới nhất trên cùng,
 * kèm danh sách item của từng đơn.
 */
require_once __DIR__ . '/config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$flash_success = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_success']);
$user_id = (int)$_SESSION['user_id'];

function vnd($n): string {
    return number_format((float)$n, 0, ',', '.') . '₫';
}

function status_label(string $s): array {
    $map = [
        'completed'  => ['Đã giao',     'success'],
        'processing' => ['Đang xử lý',  'primary'],
        'pending'    => ['Chờ duyệt',   'warning'],
        'cancelled'  => ['Đã huỷ',      'danger'],
    ];
    return $map[$s] ?? [$s, 'secondary'];
}

$orders = [];
$db_error = null;

if ($pdo) {
    try {
        // Lấy đơn của user
        $stmt = $pdo->prepare(
            "SELECT id, total_amount, status, note, created_at
             FROM orders
             WHERE user_id = :uid
             ORDER BY created_at DESC, id DESC"
        );
        $stmt->execute([':uid' => $user_id]);
        $orders = $stmt->fetchAll();

        // Load items cho mỗi đơn (1 query gộp)
        if (!empty($orders)) {
            $ids = array_column($orders, 'id');
            $place = implode(',', array_fill(0, count($ids), '?'));
            $items_stmt = $pdo->prepare(
                "SELECT oi.order_id, oi.quantity, oi.unit_price,
                        p.name, p.image_url, p.unit
                 FROM order_items oi
                 INNER JOIN products p ON p.id = oi.product_id
                 WHERE oi.order_id IN ($place)
                 ORDER BY oi.id ASC"
            );
            $items_stmt->execute($ids);
            $items_by_order = [];
            foreach ($items_stmt->fetchAll() as $r) {
                $items_by_order[$r['order_id']][] = $r;
            }
            foreach ($orders as &$o) {
                $o['items'] = $items_by_order[$o['id']] ?? [];
            }
            unset($o);
        }
    } catch (PDOException $e) {
        $db_error = $e->getMessage();
        error_log('[tracking-order] ' . $db_error);
    }
}

// Avatar/name cho navbar + sidebar
$av = $_SESSION['user_avatar'] ?? '';
if ($av === '' || $av === null) {
    $avatar_url = 'https://fitfood.vn/img/128/avatars/default.png';
} elseif (preg_match('#^https?://#', $av)) {
    $avatar_url = $av;
} else {
    $avatar_url = 'uploads/avatars/' . htmlspecialchars($av);
}
$display_name = $_SESSION['user_name'] ?? '';
$_name_parts  = preg_split('/\s+/', trim($display_name));
$short_name   = ($_name_parts && !empty($_name_parts)) ? end($_name_parts) : $display_name;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="jYreCEmKbMGqsMlbqArxYuLnK1QNyL15GyqO9wel">
    <meta name="lang" content="vi">

    <title>Fitfood VN - Nhà cung cấp gói ăn healthy lớn nhất Saigon</title>
                    <meta name="description" content="Giúp bạn ăn kiêng không bao giờ ngán với thực đơn được Fitfood lên kế hoạch kỹ lưỡng. Siêu ngon, giảm cân, healthy, ăn là ghiền - Nhanh tay order ngay!">
                    
    <link rel="icon" href="/favicon.ico">
    <link rel="canonical" href="https://fitfood.vn/profile/order" />
    <link rel="alternate" hreflang="vi" href="https://fitfood.vn/profile/order" />


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
    class="" data-url="https://fitfood.vn/profile/order"
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

<main class="">
<main>
    <section class="top-inner">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 col-xl-3 mb-4">
                    <div class="sidebar">
                        <div class="profile d-flex align-items-center">
                            <div class="avatar">
                                <img src="<?= $avatar_url ?>" alt="" border="0" class="img-fluid" />
                            </div>
                            <div class="info d-flex flex-column">
                                <span class="name"><?= htmlspecialchars($short_name) ?></span>
                            </div>
                        </div>
                        <ul class="nav flex-column">
    <li  class="nav-item">
     <a  class="nav-link" href="account.php">
      <i class='icon icon-account'></i><span>Thông tin tài khoản</span>
          </a>
          </li>

  <li  class="nav-item">
     <a  class="nav-link" href="#">
      <i class='icon icon-gift'></i><span>Point</span>
          </a>
          </li>

  <li  class="nav-item">
     <a  class="nav-link" href="address.php">
      <i class='icon icon-location'></i><span>Địa chỉ giao hàng</span>
          </a>
          </li>

  <li  class="nav-item">
     <a  class="active nav-link" href="tracking-order.php">
      <i class='icon icon-deli'></i><span>Đơn hàng hiện tại</span>
          </a>
          </li>
  </ul>
                    </div>
                </div>
                <div class="col-lg-8 col-xl-9 mb-4">
                    <div class="main-content">
                        <div class="top-main d-lg-flex justify-content-lg-between">
                            <div class="title ">
                                <h5>Đơn hàng hiện tại</h5>
                                <p>Tra cứu thông tin đặt hàng hiện tại</p>
                            </div>
                                                    </div>
                            <div class="list-order pt-3">
        <?php if (!empty($flash_success)): ?>
          <div class="alert alert-success"><?= htmlspecialchars($flash_success) ?></div>
        <?php endif; ?>
        <?php if ($db_error): ?>
          <div class="alert alert-danger">Lỗi tải đơn hàng: <?= htmlspecialchars($db_error) ?></div>
        <?php elseif (empty($orders)): ?>
          <div class="text-center py-5">
            <p class="text-muted">Bạn chưa có đơn hàng nào.</p>
            <a href="menu.php" class="btn btn-primary">Đặt hàng ngay</a>
          </div>
        <?php else: ?>
          <?php foreach ($orders as $o):
            [$status_text, $status_color] = status_label($o['status']);
            $created = $o['created_at'] ? date('d/m/Y H:i', strtotime($o['created_at'])) : '';
          ?>
            <div class="card mb-3" style="border:1px solid #eee;border-radius:8px;">
              <div class="card-header d-flex justify-content-between align-items-center"
                   style="background:#fafafa;border-bottom:1px solid #eee;padding:12px 16px;">
                <div>
                  <strong>Đơn #<?= str_pad((string)$o['id'], 6, '0', STR_PAD_LEFT) ?></strong>
                  <small class="text-muted ml-2"><?= htmlspecialchars($created) ?></small>
                </div>
                <span class="badge badge-<?= $status_color ?>"
                      style="padding:6px 10px;font-weight:500;font-size:13px;"><?= htmlspecialchars($status_text) ?></span>
              </div>
              <div class="card-body" style="padding:12px 16px;">
                <?php if (empty($o['items'])): ?>
                  <p class="text-muted small mb-0">Không có sản phẩm trong đơn này.</p>
                <?php else: ?>
                  <?php foreach ($o['items'] as $it): ?>
                    <div class="d-flex align-items-center mb-2">
                      <img src="<?= htmlspecialchars($it['image_url'] ?: '/images/logo-healthyfood.png') ?>"
                           alt="" width="48" height="48"
                           style="object-fit:cover;border-radius:6px;border:1px solid #eee;">
                      <div class="flex-grow-1 ml-3">
                        <div><?= htmlspecialchars($it['name']) ?></div>
                        <small class="text-muted">
                          <?= htmlspecialchars(vnd($it['unit_price'])) ?>
                          <?php if (!empty($it['unit'])): ?> • <?= htmlspecialchars($it['unit']) ?><?php endif; ?>
                        </small>
                      </div>
                      <div class="text-right">
                        <span class="text-muted">x<?= (int)$it['quantity'] ?></span>
                      </div>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
              <div class="card-footer d-flex justify-content-between align-items-center"
                   style="background:#fff;border-top:1px solid #eee;padding:10px 16px;">
                <small class="text-muted">Tổng cộng</small>
                <strong style="color:#E66239;font-size:16px;"><?= htmlspecialchars(vnd($o['total_amount'])) ?></strong>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>
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

<!-- modal sign up -->
<div class="modal fade" id="forgot-password" tabindex="-1" role="dialog" aria-labelledby="" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-body">
                <form id="forgot-password-form" action="https://fitfood.vn/forgot-password" method="post">
                    <p class="title">Quên mật khẩu?</p>
                    <div class="form-group">
                        <input type="text" class="form-control" name="email" placeholder="Email" required>
                    </div>
                    <button type="button" class="btn btn-primary btn-block submit">Hoàn thành</button>
                </form>
            </div>
        </div>
    </div>
</div>

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

<script src="https://fitfood.vn/js/underscore-min.js?v=2026033101"></script>
<script src="https://fitfood.vn/js/plugins.js?v=2026033101"></script>
<script src="https://fitfood.vn/js/modules/tracking.js"></script>
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
</body>
</html>

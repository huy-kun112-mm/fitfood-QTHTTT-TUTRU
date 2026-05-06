<?php
/**
 * Trang địa chỉ giao hàng — wire DB.
 * - GET: list addresses của user hiện tại
 * - POST action=create:      thêm địa chỉ mới (từ modal popup)
 * - POST action=delete:      xoá 1 địa chỉ
 * - POST action=set_default: đặt làm mặc định
 */
require_once __DIR__ . '/config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
$user_id = (int)$_SESSION['user_id'];

$errors = [];
$flash_success = $_SESSION['flash_success'] ?? null;
$flash_error   = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// ---------- Handle POST ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create') {
            $recipient = trim((string)($_POST['name']    ?? ''));
            $phone     = trim((string)($_POST['phone']   ?? ''));
            $gender    = (string)($_POST['gender']  ?? '');
            $address   = trim((string)($_POST['address'] ?? ''));
            $is_default = isset($_POST['default']) ? 1 : 0;

            if ($recipient === '')                                 $errors['name']    = 'Vui lòng nhập họ tên.';
            if (!preg_match('/^[0-9]{8,15}$/', $phone))            $errors['phone']   = 'SĐT phải là 8-15 chữ số.';
            if ($address === '' || mb_strlen($address) > 500)      $errors['address'] = 'Vui lòng nhập đầy đủ địa chỉ.';
            $gender_db = $gender === 'M' ? 'M' : ($gender === 'F' ? 'F' : null);

            if (empty($errors)) {
                $pdo->beginTransaction();
                if ($is_default) {
                    $pdo->prepare("UPDATE user_addresses SET is_default = 0 WHERE user_id = ?")
                        ->execute([$user_id]);
                }
                $pdo->prepare(
                    "INSERT INTO user_addresses (user_id, recipient_name, phone, gender, address, is_default)
                     VALUES (:uid, :name, :phone, :gender, :address, :default)"
                )->execute([
                    ':uid'     => $user_id,
                    ':name'    => $recipient,
                    ':phone'   => $phone,
                    ':gender'  => $gender_db,
                    ':address' => $address,
                    ':default' => $is_default,
                ]);
                $pdo->commit();
                $_SESSION['flash_success'] = 'Đã thêm địa chỉ mới.';
                header('Location: address.php');
                exit;
            }
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $pdo->prepare("DELETE FROM user_addresses WHERE id = :id AND user_id = :uid")
                    ->execute([':id' => $id, ':uid' => $user_id]);
                $_SESSION['flash_success'] = 'Đã xoá địa chỉ.';
                header('Location: address.php');
                exit;
            }
        } elseif ($action === 'set_default') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $pdo->beginTransaction();
                $pdo->prepare("UPDATE user_addresses SET is_default = 0 WHERE user_id = :uid")
                    ->execute([':uid' => $user_id]);
                $pdo->prepare(
                    "UPDATE user_addresses SET is_default = 1 WHERE id = :id AND user_id = :uid"
                )->execute([':id' => $id, ':uid' => $user_id]);
                $pdo->commit();
                $_SESSION['flash_success'] = 'Đã đặt địa chỉ mặc định.';
                header('Location: address.php');
                exit;
            }
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $errors['_db'] = 'Lỗi DB: ' . $e->getMessage();
        error_log('[address] ' . $e->getMessage());
    }
}

// ---------- Load addresses ----------
$addresses = [];
if ($pdo) {
    try {
        $stmt = $pdo->prepare(
            "SELECT id, recipient_name, phone, gender, address, is_default, created_at
             FROM user_addresses
             WHERE user_id = :uid
             ORDER BY is_default DESC, created_at DESC"
        );
        $stmt->execute([':uid' => $user_id]);
        $addresses = $stmt->fetchAll();
    } catch (PDOException $e) {
        $errors['_db'] = 'Lỗi load: ' . $e->getMessage();
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
    <link rel="canonical" href="https://fitfood.vn/profile/address" />
    <link rel="alternate" hreflang="vi" href="https://fitfood.vn/profile/address" />


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
    class="" data-url="https://fitfood.vn/profile/address"
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
     <a  class="active nav-link" href="address.php">
      <i class='icon icon-location'></i><span>Địa chỉ giao hàng</span>
          </a>
          </li>

  <li  class="nav-item">
     <a  class="nav-link" href="tracking-order.php">
      <i class='icon icon-deli'></i><span>Đơn hàng hiện tại</span>
          </a>
          </li>
  </ul>
                    </div>
                </div>
                <div class="col-lg-8 col-xl-9 mb-4">
                    <div class="main-content">
                        <div class="top-main d-lg-flex justify-content-lg-between">
                            <div class="title mb-2 mb-md-0 ">
                                <h5>Địa chỉ giao hàng</h5>
                                <p>Quản lý các địa chỉ giao hàng của bạn</p>
                            </div>
                                <button type="button" class="btn btn-primary btn-lg" data-toggle="modal" data-target="#addressModal">
        <i class="fa fa-plus mr-2" aria-hidden="true"></i>Thêm địa chỉ mới
    </button>
                        </div>

                            <?php if ($flash_success): ?>
                              <div class="alert alert-success mt-3"><?= htmlspecialchars($flash_success) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($errors['_db'])): ?>
                              <div class="alert alert-danger mt-3"><?= htmlspecialchars($errors['_db']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($errors)): ?>
                              <?php foreach (['name','phone','address'] as $f): ?>
                                <?php if (!empty($errors[$f])): ?>
                                  <div class="alert alert-warning mt-3"><?= htmlspecialchars($errors[$f]) ?></div>
                                <?php endif; ?>
                              <?php endforeach; ?>
                            <?php endif; ?>

                            <div class="list-address">
                              <?php if (empty($addresses)): ?>
                                <p class="text-muted py-4">Chưa có địa chỉ giao hàng nào. Bấm "Thêm địa chỉ mới" để bắt đầu.</p>
                              <?php else: ?>
                                <?php foreach ($addresses as $a): ?>
                                  <div class="address-item border-bottom pb-3 mb-3">
                                    <div class="row">
                                      <div class="col-md-9">
                                        <div class="row mb-2">
                                          <div class="col-3 col-md-2"><strong>Tên</strong></div>
                                          <div class="col-9 col-md-10"><?= htmlspecialchars($a['recipient_name']) ?></div>
                                        </div>
                                        <div class="row mb-2">
                                          <div class="col-3 col-md-2"><strong>SĐT</strong></div>
                                          <div class="col-9 col-md-10"><?= htmlspecialchars($a['phone']) ?></div>
                                        </div>
                                        <div class="row">
                                          <div class="col-3 col-md-2"><strong>Địa chỉ</strong></div>
                                          <div class="col-9 col-md-10"><?= htmlspecialchars($a['address']) ?></div>
                                        </div>
                                      </div>
                                      <div class="col-md-3 text-md-right mt-2 mt-md-0">
                                        <div class="mb-2">
                                          <a href="javascript:;" class="text-danger small">Sửa</a> |
                                          <form method="POST" action="address.php" class="d-inline"
                                                onsubmit="return confirm('Xoá địa chỉ này?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                                            <button type="submit" class="btn btn-link p-0 text-danger small">Xoá</button>
                                          </form>
                                        </div>
                                        <?php if ((int)$a['is_default'] === 1): ?>
                                          <span class="badge badge-dark px-3 py-2" style="background:#000;color:#fff;border-radius:4px;">Mặc định</span>
                                        <?php else: ?>
                                          <form method="POST" action="address.php" class="d-inline">
                                            <input type="hidden" name="action" value="set_default">
                                            <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                                            <button type="submit" class="btn btn-outline-secondary btn-sm">Đặt mặc định</button>
                                          </form>
                                        <?php endif; ?>
                                      </div>
                                    </div>
                                  </div>
                                <?php endforeach; ?>
                              <?php endif; ?>
                            </div>
    <!-- modal new address -->
<div class="modal fade" id="addressModal" tabindex="-1" role="dialog" aria-labelledby="addressModalLabel" data-backdrop="static"
     aria-hidden="true">
    <div class="modal-dialog customer-address modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-body">
                <div class="row">
                    <div class="col-12">
                        <form id="customer-address-form" method="POST" action="address.php">
                            <input type="hidden" name="action" value="create">
                            <input type="hidden" name="address" id="cus_address_full">
                            <h2>Thêm địa chỉ mới</h2>
                            <div class="form-group">
                                <label for=""><strong>Họ &amp; Tên</strong></label>
                                <input type="text" id="cus_name" name="name" class="form-control" maxlength="90" placeholder="" data-validation="Họ &amp; Tên">
                            </div>
                            <div class="row">
                                <div class="col-6 form-group">
                                    <label for=""><strong>SĐT</strong></label>
                                    <input type="text" id="cus_phone" name="phone" class="form-control" maxlength="10" placeholder="" data-validation="Nhập SĐT">
                                </div>
                                <div class="col-6">
                                    <label for=""><strong>Giới tính</strong></label>
                                    <div class="form-group">
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="gender" id="inlineRadio1" value="M" checked>
                                            <label class="form-check-label" for="inlineRadio1">Nam</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="gender" id="inlineRadio2" value="F">
                                            <label class="form-check-label" for="inlineRadio2">Nữ</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label for=""><strong>Quận</strong></label>
                                        <div>
                                            <select class="form-control iselect2" id="cus_district" name="district_id" data-validation="Nhập quận" data-placeholder="Chọn quận">
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
                                </div>
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label for=""><strong>Phường</strong></label>
                                        <div>
                                            <select class="form-control iselect2" id="cus_ward" name="ward_id" data-validation="Nhập phường" data-placeholder="Chọn phường">
                                                <option value=""></option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for=""><strong>Số nhà &amp; tên đường</strong></label>
                                <input type="text" id="cus_street" name="street" maxlength="400" class="form-control" placeholder="" data-validation="Số nhà &amp; tên đường" >
                            </div>
                            
                            <div class="form-group">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="default" id="is_default" value="1">
                                    <label class="form-check-label" for="is_default">Làm địa chỉ mặc định</label>
                                </div>
                            </div>
                            <div class="d-flex justify-content-center">
                                <button type="submit" class="btn btn-primary">Hoàn thành</button>
                                <button type="button" class="btn btn-outline-danger" data-dismiss="modal">Trở về</button>
                            </div>
                        </form>
                        <script>
                        // Gộp số nhà + phường + quận thành 1 chuỗi địa chỉ trước khi submit
                        (function () {
                          var form = document.getElementById('customer-address-form');
                          if (!form) return;
                          form.addEventListener('submit', function (e) {
                            var street   = (document.getElementById('cus_street') || {}).value || '';
                            var wardSel  = document.getElementById('cus_ward');
                            var distSel  = document.getElementById('cus_district');
                            var wardTxt  = wardSel && wardSel.options[wardSel.selectedIndex] ? wardSel.options[wardSel.selectedIndex].text : '';
                            var distTxt  = distSel && distSel.options[distSel.selectedIndex] ? distSel.options[distSel.selectedIndex].text : '';
                            var parts = [street, wardTxt, distTxt].map(function (s) { return (s || '').trim(); }).filter(Boolean);
                            document.getElementById('cus_address_full').value = parts.join(', ');
                          });
                        })();
                        </script>
                    </div>
                    <!--
                    <div class="col-7">

                    </div>
                    -->
                </div>

            </div>
        </div>
    </div>
</div>
<div style="display: none">
    <textarea id="data_ward">{"1":[{"id":4,"text":"X\u00e3 B\u00ecnh H\u01b0ng"}],"2":[{"id":17,"text":"Th\u1ecb tr\u1ea5n C\u1ea7n Th\u1ea1nh"},{"id":18,"text":"X\u00e3 An Th\u1edbi \u0110\u00f4ng"},{"id":19,"text":"X\u00e3 B\u00ecnh Kh\u00e1nh"},{"id":20,"text":"X\u00e3 Long H\u00f2a"},{"id":21,"text":"X\u00e3 L\u00fd Nh\u01a1n"},{"id":22,"text":"X\u00e3 Tam Th\u00f4n Hi\u1ec7p"},{"id":23,"text":"X\u00e3 Th\u1ea1nh An"}],"3":[{"id":24,"text":"Th\u1ecb tr\u1ea5n C\u1ee7 Chi"},{"id":25,"text":"X\u00e3 An Nh\u01a1n T\u00e2y"},{"id":26,"text":"X\u00e3 An Ph\u00fa"},{"id":27,"text":"X\u00e3 B\u00ecnh M\u1ef9"},{"id":28,"text":"X\u00e3 H\u00f2a Ph\u00fa"},{"id":29,"text":"X\u00e3 Nhu\u1eadn \u0110\u1ee9c"},{"id":30,"text":"X\u00e3 Ph\u1ea1m V\u0103n C\u1ed9i"},{"id":31,"text":"X\u00e3 Ph\u00fa H\u00f2a \u0110\u00f4ng"},{"id":32,"text":"X\u00e3 Ph\u00fa M\u1ef9 H\u01b0ng"},{"id":33,"text":"X\u00e3 Ph\u01b0\u1edbc Hi\u1ec7p"},{"id":34,"text":"X\u00e3 Ph\u01b0\u1edbc Th\u1ea1nh"},{"id":35,"text":"X\u00e3 Ph\u01b0\u1edbc V\u0129nh An"},{"id":36,"text":"X\u00e3 T\u00e2n An H\u1ed9i"},{"id":37,"text":"X\u00e3 T\u00e2n Ph\u00fa Trung"},{"id":39,"text":"X\u00e3 T\u00e2n Th\u1ea1nh \u0110\u00f4ng"},{"id":38,"text":"X\u00e3 T\u00e2n Th\u1ea1nh T\u00e2y"},{"id":40,"text":"X\u00e3 T\u00e2n Th\u00f4ng H\u1ed9i"},{"id":41,"text":"X\u00e3 Th\u00e1i M\u1ef9"},{"id":42,"text":"X\u00e3 Trung An"},{"id":43,"text":"X\u00e3 Trung L\u1eadp H\u1ea1"},{"id":44,"text":"X\u00e3 Trung L\u1eadp Th\u01b0\u1ee3ng"}],"4":[{"id":45,"text":"Th\u1ecb tr\u1ea5n H\u00f3c M\u00f4n"},{"id":46,"text":"X\u00e3 B\u00e0 \u0110i\u1ec3m"},{"id":56,"text":"X\u00e3 \u0110\u00f4ng Th\u1ea1nh"},{"id":47,"text":"X\u00e3 Nh\u1ecb B\u00ecnh"},{"id":48,"text":"X\u00e3 T\u00e2n Hi\u1ec7p"},{"id":49,"text":"X\u00e3 T\u00e2n Th\u1edbi Nh\u00ec"},{"id":50,"text":"X\u00e3 T\u00e2n Xu\u00e2n"},{"id":51,"text":"X\u00e3 Th\u1edbi Tam Th\u00f4n"},{"id":52,"text":"X\u00e3 Trung Ch\u00e1nh"},{"id":55,"text":"X\u00e3 Xu\u00e2n Th\u1edbi \u0110\u00f4ng"},{"id":53,"text":"X\u00e3 Xu\u00e2n Th\u1edbi S\u01a1n"},{"id":54,"text":"X\u00e3 Xu\u00e2n Th\u1edbi Th\u01b0\u1ee3ng"}],"5":[{"id":57,"text":"Th\u1ecb tr\u1ea5n Nh\u00e0 B\u00e8"},{"id":58,"text":"X\u00e3 Hi\u1ec7p Ph\u01b0\u1edbc"},{"id":59,"text":"X\u00e3 Long Th\u1edbi"},{"id":60,"text":"X\u00e3 Nh\u01a1n \u0110\u1ee9c"},{"id":61,"text":"X\u00e3 Ph\u00fa Xu\u00e2n"},{"id":62,"text":"X\u00e3 Ph\u01b0\u1edbc Ki\u1ec3n"},{"id":63,"text":"X\u00e3 Ph\u01b0\u1edbc L\u1ed9c"}],"6":[{"id":64,"text":"Ph\u01b0\u1eddng B\u1ebfn Ngh\u00e9"},{"id":65,"text":"Ph\u01b0\u1eddng B\u1ebfn Th\u00e0nh"},{"id":66,"text":"Ph\u01b0\u1eddng C\u1ea7u Kho"},{"id":67,"text":"Ph\u01b0\u1eddng C\u1ea7u \u00d4ng L\u00e3nh"},{"id":68,"text":"Ph\u01b0\u1eddng C\u00f4 Giang"},{"id":73,"text":"Ph\u01b0\u1eddng \u0110a Kao"},{"id":69,"text":"Ph\u01b0\u1eddng Nguy\u1ec5n C\u01b0 Trinh"},{"id":70,"text":"Ph\u01b0\u1eddng Nguy\u1ec5n Th\u00e1i B\u00ecnh"},{"id":71,"text":"Ph\u01b0\u1eddng Ph\u1ea1m Ng\u0169 L\u00e3o"},{"id":72,"text":"Ph\u01b0\u1eddng T\u00e2n \u0110\u1ecbnh"}],"7":[{"id":74,"text":"Ph\u01b0\u1eddng An Kh\u00e1nh"},{"id":75,"text":"Ph\u01b0\u1eddng An L\u1ee3i \u0110\u00f4ng"},{"id":76,"text":"Ph\u01b0\u1eddng An Ph\u00fa"},{"id":77,"text":"Ph\u01b0\u1eddng B\u00ecnh An"},{"id":341,"text":"Ph\u01b0\u1eddng B\u00ecnh Kh\u00e1nh"},{"id":80,"text":"Ph\u01b0\u1eddng B\u00ecnh Tr\u01b0ng \u0110\u00f4ng"},{"id":79,"text":"Ph\u01b0\u1eddng B\u00ecnh Tr\u01b0ng T\u00e2y"},{"id":83,"text":"Ph\u01b0\u1eddng Th\u1ea3o \u0110i\u1ec1n"},{"id":84,"text":"Ph\u01b0\u1eddng Th\u1ee7 Thi\u00eam"}],"8":[{"id":85,"text":"Ph\u01b0\u1eddng 01"},{"id":86,"text":"Ph\u01b0\u1eddng 02"},{"id":87,"text":"Ph\u01b0\u1eddng 03"},{"id":88,"text":"Ph\u01b0\u1eddng 04"},{"id":89,"text":"Ph\u01b0\u1eddng 05"},{"id":90,"text":"Ph\u01b0\u1eddng 06"},{"id":91,"text":"Ph\u01b0\u1eddng 07"},{"id":92,"text":"Ph\u01b0\u1eddng 08"},{"id":93,"text":"Ph\u01b0\u1eddng 09"},{"id":94,"text":"Ph\u01b0\u1eddng 10"},{"id":95,"text":"Ph\u01b0\u1eddng 11"},{"id":96,"text":"Ph\u01b0\u1eddng 12"},{"id":97,"text":"Ph\u01b0\u1eddng 13"},{"id":98,"text":"Ph\u01b0\u1eddng 14"},{"id":352,"text":"Ph\u01b0\u1eddng V\u00f5 Th\u1ecb S\u00e1u"}],"9":[{"id":99,"text":"Ph\u01b0\u1eddng 01"},{"id":100,"text":"Ph\u01b0\u1eddng 02"},{"id":101,"text":"Ph\u01b0\u1eddng 03"},{"id":102,"text":"Ph\u01b0\u1eddng 04"},{"id":103,"text":"Ph\u01b0\u1eddng 05"},{"id":104,"text":"Ph\u01b0\u1eddng 06"},{"id":105,"text":"Ph\u01b0\u1eddng 08"},{"id":106,"text":"Ph\u01b0\u1eddng 09"},{"id":107,"text":"Ph\u01b0\u1eddng 10"},{"id":108,"text":"Ph\u01b0\u1eddng 12"},{"id":109,"text":"Ph\u01b0\u1eddng 13"},{"id":110,"text":"Ph\u01b0\u1eddng 14"},{"id":111,"text":"Ph\u01b0\u1eddng 15"},{"id":112,"text":"Ph\u01b0\u1eddng 16"},{"id":113,"text":"Ph\u01b0\u1eddng 18"}],"10":[{"id":114,"text":"Ph\u01b0\u1eddng 01"},{"id":115,"text":"Ph\u01b0\u1eddng 02"},{"id":116,"text":"Ph\u01b0\u1eddng 03"},{"id":117,"text":"Ph\u01b0\u1eddng 04"},{"id":118,"text":"Ph\u01b0\u1eddng 05"},{"id":119,"text":"Ph\u01b0\u1eddng 06"},{"id":120,"text":"Ph\u01b0\u1eddng 07"},{"id":121,"text":"Ph\u01b0\u1eddng 08"},{"id":122,"text":"Ph\u01b0\u1eddng 09"},{"id":123,"text":"Ph\u01b0\u1eddng 10"},{"id":124,"text":"Ph\u01b0\u1eddng 11"},{"id":125,"text":"Ph\u01b0\u1eddng 12"},{"id":126,"text":"Ph\u01b0\u1eddng 13"},{"id":127,"text":"Ph\u01b0\u1eddng 14"},{"id":128,"text":"Ph\u01b0\u1eddng 15"}],"11":[{"id":129,"text":"Ph\u01b0\u1eddng 01"},{"id":130,"text":"Ph\u01b0\u1eddng 02"},{"id":131,"text":"Ph\u01b0\u1eddng 03"},{"id":132,"text":"Ph\u01b0\u1eddng 04"},{"id":133,"text":"Ph\u01b0\u1eddng 05"},{"id":134,"text":"Ph\u01b0\u1eddng 06"},{"id":135,"text":"Ph\u01b0\u1eddng 07"},{"id":136,"text":"Ph\u01b0\u1eddng 08"},{"id":137,"text":"Ph\u01b0\u1eddng 09"},{"id":138,"text":"Ph\u01b0\u1eddng 10"},{"id":139,"text":"Ph\u01b0\u1eddng 11"},{"id":140,"text":"Ph\u01b0\u1eddng 12"},{"id":141,"text":"Ph\u01b0\u1eddng 13"},{"id":142,"text":"Ph\u01b0\u1eddng 14"}],"12":[{"id":143,"text":"Ph\u01b0\u1eddng B\u00ecnh Thu\u1eadn"},{"id":144,"text":"Ph\u01b0\u1eddng Ph\u00fa M\u1ef9"},{"id":145,"text":"Ph\u01b0\u1eddng Ph\u00fa Thu\u1eadn"},{"id":146,"text":"Ph\u01b0\u1eddng T\u00e2n H\u01b0ng"},{"id":147,"text":"Ph\u01b0\u1eddng T\u00e2n Ki\u1ec3ng"},{"id":148,"text":"Ph\u01b0\u1eddng T\u00e2n Phong"},{"id":149,"text":"Ph\u01b0\u1eddng T\u00e2n Ph\u00fa"},{"id":150,"text":"Ph\u01b0\u1eddng T\u00e2n Quy"},{"id":152,"text":"Ph\u01b0\u1eddng T\u00e2n Thu\u1eadn \u0110\u00f4ng"},{"id":151,"text":"Ph\u01b0\u1eddng T\u00e2n Thu\u1eadn T\u00e2y"}],"13":[{"id":153,"text":"Ph\u01b0\u1eddng 01"},{"id":154,"text":"Ph\u01b0\u1eddng 02"},{"id":155,"text":"Ph\u01b0\u1eddng 03"},{"id":156,"text":"Ph\u01b0\u1eddng 04"},{"id":157,"text":"Ph\u01b0\u1eddng 05"},{"id":158,"text":"Ph\u01b0\u1eddng 06"},{"id":159,"text":"Ph\u01b0\u1eddng 07"},{"id":160,"text":"Ph\u01b0\u1eddng 08"},{"id":161,"text":"Ph\u01b0\u1eddng 09"},{"id":162,"text":"Ph\u01b0\u1eddng 10"},{"id":163,"text":"Ph\u01b0\u1eddng 11"},{"id":164,"text":"Ph\u01b0\u1eddng 12"},{"id":165,"text":"Ph\u01b0\u1eddng 13"},{"id":166,"text":"Ph\u01b0\u1eddng 14"}],"14":[{"id":169,"text":"Ph\u01b0\u1eddng Hi\u1ec7p Ph\u00fa"},{"id":170,"text":"Ph\u01b0\u1eddng Long B\u00ecnh"},{"id":171,"text":"Ph\u01b0\u1eddng Long Ph\u01b0\u1edbc"},{"id":172,"text":"Ph\u01b0\u1eddng Long Th\u1ea1nh M\u1ef9"},{"id":173,"text":"Ph\u01b0\u1eddng Long Tr\u01b0\u1eddng"},{"id":174,"text":"Ph\u01b0\u1eddng Ph\u00fa H\u1eefu"},{"id":175,"text":"Ph\u01b0\u1eddng Ph\u01b0\u1edbc B\u00ecnh"},{"id":176,"text":"Ph\u01b0\u1eddng Ph\u01b0\u1edbc Long A"},{"id":177,"text":"Ph\u01b0\u1eddng Ph\u01b0\u1edbc Long B"},{"id":178,"text":"Ph\u01b0\u1eddng T\u00e2n Ph\u00fa"},{"id":179,"text":"Ph\u01b0\u1eddng T\u0103ng Nh\u01a1n Ph\u00fa A"},{"id":180,"text":"Ph\u01b0\u1eddng T\u0103ng Nh\u01a1n Ph\u00fa B"},{"id":181,"text":"Ph\u01b0\u1eddng Tr\u01b0\u1eddng Th\u1ea1nh"}],"15":[{"id":182,"text":"Ph\u01b0\u1eddng 01"},{"id":183,"text":"Ph\u01b0\u1eddng 02"},{"id":184,"text":"Ph\u01b0\u1eddng 03"},{"id":185,"text":"Ph\u01b0\u1eddng 04"},{"id":186,"text":"Ph\u01b0\u1eddng 05"},{"id":187,"text":"Ph\u01b0\u1eddng 06"},{"id":188,"text":"Ph\u01b0\u1eddng 07"},{"id":189,"text":"Ph\u01b0\u1eddng 08"},{"id":190,"text":"Ph\u01b0\u1eddng 09"},{"id":191,"text":"Ph\u01b0\u1eddng 10"},{"id":192,"text":"Ph\u01b0\u1eddng 11"},{"id":193,"text":"Ph\u01b0\u1eddng 12"},{"id":194,"text":"Ph\u01b0\u1eddng 13"},{"id":195,"text":"Ph\u01b0\u1eddng 14"},{"id":196,"text":"Ph\u01b0\u1eddng 15"}],"16":[{"id":197,"text":"Ph\u01b0\u1eddng 01"},{"id":198,"text":"Ph\u01b0\u1eddng 02"},{"id":199,"text":"Ph\u01b0\u1eddng 03"},{"id":200,"text":"Ph\u01b0\u1eddng 04"},{"id":201,"text":"Ph\u01b0\u1eddng 05"},{"id":202,"text":"Ph\u01b0\u1eddng 06"},{"id":203,"text":"Ph\u01b0\u1eddng 07"},{"id":204,"text":"Ph\u01b0\u1eddng 08"},{"id":205,"text":"Ph\u01b0\u1eddng 09"},{"id":206,"text":"Ph\u01b0\u1eddng 10"},{"id":207,"text":"Ph\u01b0\u1eddng 11"},{"id":208,"text":"Ph\u01b0\u1eddng 12"},{"id":209,"text":"Ph\u01b0\u1eddng 13"},{"id":210,"text":"Ph\u01b0\u1eddng 14"},{"id":211,"text":"Ph\u01b0\u1eddng 15"},{"id":212,"text":"Ph\u01b0\u1eddng 16"}],"17":[{"id":213,"text":"Ph\u01b0\u1eddng An Ph\u00fa \u0110\u00f4ng"},{"id":223,"text":"Ph\u01b0\u1eddng \u0110\u00f4ng H\u01b0ng Thu\u1eadn"},{"id":214,"text":"Ph\u01b0\u1eddng Hi\u1ec7p Th\u00e0nh"},{"id":215,"text":"Ph\u01b0\u1eddng T\u00e2n Ch\u00e1nh Hi\u1ec7p"},{"id":216,"text":"Ph\u01b0\u1eddng T\u00e2n H\u01b0ng Thu\u1eadn"},{"id":217,"text":"Ph\u01b0\u1eddng T\u00e2n Th\u1edbi Hi\u1ec7p"},{"id":218,"text":"Ph\u01b0\u1eddng T\u00e2n Th\u1edbi Nh\u1ea5t"},{"id":219,"text":"Ph\u01b0\u1eddng Th\u1ea1nh L\u1ed9c"},{"id":220,"text":"Ph\u01b0\u1eddng Th\u1ea1nh Xu\u00e2n"},{"id":221,"text":"Ph\u01b0\u1eddng Th\u1edbi An"},{"id":222,"text":"Ph\u01b0\u1eddng Trung M\u1ef9 T\u00e2y"}],"18":[{"id":224,"text":"Ph\u01b0\u1eddng An L\u1ea1c"},{"id":225,"text":"Ph\u01b0\u1eddng An L\u1ea1c A"},{"id":227,"text":"Ph\u01b0\u1eddng B\u00ecnh H\u01b0ng Ho\u00e0 A"},{"id":229,"text":"Ph\u01b0\u1eddng B\u00ecnh Tr\u1ecb \u0110\u00f4ng"},{"id":231,"text":"Ph\u01b0\u1eddng B\u00ecnh Tr\u1ecb \u0110\u00f4ng B"},{"id":232,"text":"Ph\u01b0\u1eddng T\u00e2n T\u1ea1o"},{"id":233,"text":"Ph\u01b0\u1eddng T\u00e2n T\u1ea1o A"}],"19":[{"id":234,"text":"Ph\u01b0\u1eddng 01"},{"id":235,"text":"Ph\u01b0\u1eddng 02"},{"id":236,"text":"Ph\u01b0\u1eddng 03"},{"id":237,"text":"Ph\u01b0\u1eddng 05"},{"id":238,"text":"Ph\u01b0\u1eddng 06"},{"id":239,"text":"Ph\u01b0\u1eddng 07"},{"id":240,"text":"Ph\u01b0\u1eddng 11"},{"id":241,"text":"Ph\u01b0\u1eddng 12"},{"id":242,"text":"Ph\u01b0\u1eddng 13"},{"id":243,"text":"Ph\u01b0\u1eddng 14"},{"id":244,"text":"Ph\u01b0\u1eddng 15"},{"id":245,"text":"Ph\u01b0\u1eddng 17"},{"id":246,"text":"Ph\u01b0\u1eddng 19"},{"id":247,"text":"Ph\u01b0\u1eddng 21"},{"id":248,"text":"Ph\u01b0\u1eddng 22"},{"id":249,"text":"Ph\u01b0\u1eddng 24"},{"id":250,"text":"Ph\u01b0\u1eddng 25"},{"id":251,"text":"Ph\u01b0\u1eddng 26"},{"id":252,"text":"Ph\u01b0\u1eddng 27"},{"id":253,"text":"Ph\u01b0\u1eddng 28"}],"20":[{"id":254,"text":"Ph\u01b0\u1eddng 01"},{"id":255,"text":"Ph\u01b0\u1eddng 03"},{"id":256,"text":"Ph\u01b0\u1eddng 04"},{"id":257,"text":"Ph\u01b0\u1eddng 05"},{"id":258,"text":"Ph\u01b0\u1eddng 06"},{"id":259,"text":"Ph\u01b0\u1eddng 07"},{"id":260,"text":"Ph\u01b0\u1eddng 08"},{"id":261,"text":"Ph\u01b0\u1eddng 09"},{"id":262,"text":"Ph\u01b0\u1eddng 10"},{"id":263,"text":"Ph\u01b0\u1eddng 11"},{"id":264,"text":"Ph\u01b0\u1eddng 12"},{"id":265,"text":"Ph\u01b0\u1eddng 13"},{"id":266,"text":"Ph\u01b0\u1eddng 14"},{"id":267,"text":"Ph\u01b0\u1eddng 15"},{"id":268,"text":"Ph\u01b0\u1eddng 16"},{"id":269,"text":"Ph\u01b0\u1eddng 17"}],"21":[{"id":270,"text":"Ph\u01b0\u1eddng 01"},{"id":271,"text":"Ph\u01b0\u1eddng 02"},{"id":272,"text":"Ph\u01b0\u1eddng 03"},{"id":273,"text":"Ph\u01b0\u1eddng 04"},{"id":274,"text":"Ph\u01b0\u1eddng 05"},{"id":275,"text":"Ph\u01b0\u1eddng 07"},{"id":276,"text":"Ph\u01b0\u1eddng 08"},{"id":277,"text":"Ph\u01b0\u1eddng 09"},{"id":278,"text":"Ph\u01b0\u1eddng 10"},{"id":279,"text":"Ph\u01b0\u1eddng 11"},{"id":280,"text":"Ph\u01b0\u1eddng 12"},{"id":281,"text":"Ph\u01b0\u1eddng 13"},{"id":282,"text":"Ph\u01b0\u1eddng 14"},{"id":283,"text":"Ph\u01b0\u1eddng 15"},{"id":284,"text":"Ph\u01b0\u1eddng 17"}],"22":[{"id":285,"text":"Ph\u01b0\u1eddng 01"},{"id":286,"text":"Ph\u01b0\u1eddng 02"},{"id":287,"text":"Ph\u01b0\u1eddng 03"},{"id":288,"text":"Ph\u01b0\u1eddng 04"},{"id":289,"text":"Ph\u01b0\u1eddng 05"},{"id":290,"text":"Ph\u01b0\u1eddng 06"},{"id":291,"text":"Ph\u01b0\u1eddng 07"},{"id":292,"text":"Ph\u01b0\u1eddng 08"},{"id":293,"text":"Ph\u01b0\u1eddng 09"},{"id":294,"text":"Ph\u01b0\u1eddng 10"},{"id":295,"text":"Ph\u01b0\u1eddng 11"},{"id":296,"text":"Ph\u01b0\u1eddng 12"},{"id":297,"text":"Ph\u01b0\u1eddng 13"},{"id":298,"text":"Ph\u01b0\u1eddng 14"},{"id":299,"text":"Ph\u01b0\u1eddng 15"}],"23":[{"id":300,"text":"Ph\u01b0\u1eddng Hi\u1ec7p T\u00e2n"},{"id":301,"text":"Ph\u01b0\u1eddng H\u00f2a Th\u1ea1nh"},{"id":302,"text":"Ph\u01b0\u1eddng Ph\u00fa Th\u1ea1nh"},{"id":303,"text":"Ph\u01b0\u1eddng Ph\u00fa Th\u1ecd H\u00f2a"},{"id":304,"text":"Ph\u01b0\u1eddng Ph\u00fa Trung"},{"id":305,"text":"Ph\u01b0\u1eddng S\u01a1n K\u1ef3"},{"id":306,"text":"Ph\u01b0\u1eddng T\u00e2n Qu\u00fd"},{"id":307,"text":"Ph\u01b0\u1eddng T\u00e2n S\u01a1n Nh\u00ec"},{"id":308,"text":"Ph\u01b0\u1eddng T\u00e2n Th\u00e0nh"},{"id":309,"text":"Ph\u01b0\u1eddng T\u00e2n Th\u1edbi H\u00f2a"},{"id":310,"text":"Ph\u01b0\u1eddng T\u00e2y Th\u1ea1nh"}],"24":[{"id":311,"text":"Ph\u01b0\u1eddng B\u00ecnh Chi\u1ec3u"},{"id":312,"text":"Ph\u01b0\u1eddng B\u00ecnh Th\u1ecd"},{"id":313,"text":"Ph\u01b0\u1eddng Hi\u1ec7p B\u00ecnh Ch\u00e1nh"},{"id":314,"text":"Ph\u01b0\u1eddng Hi\u1ec7p B\u00ecnh Ph\u01b0\u1edbc"},{"id":315,"text":"Ph\u01b0\u1eddng Linh Chi\u1ec3u"},{"id":319,"text":"Ph\u01b0\u1eddng Linh \u0110\u00f4ng"},{"id":316,"text":"Ph\u01b0\u1eddng Linh T\u00e2y"},{"id":317,"text":"Ph\u01b0\u1eddng Linh Trung"},{"id":318,"text":"Ph\u01b0\u1eddng Linh Xu\u00e2n"},{"id":320,"text":"Ph\u01b0\u1eddng Tam B\u00ecnh"},{"id":321,"text":"Ph\u01b0\u1eddng Tam Ph\u00fa"},{"id":322,"text":"Ph\u01b0\u1eddng Tr\u01b0\u1eddng Th\u1ecd"}],"25":[{"id":323,"text":"T\u00e2n Ki\u1ec3ng"}],"26":[{"id":324,"text":"Ph\u01b0\u1eddng Ph\u01b0\u1edbc Ki\u1ec3n"}],"28":[{"id":327,"text":"Ph\u01b0\u1eddng B\u00ccnh Th\u1ecd"},{"id":330,"text":"Ph\u01b0\u1eddng Hi\u1ec7p B\u00ecnh Ch\u00e1nh"},{"id":331,"text":"Ph\u01b0\u1eddng Hi\u1ec7p B\u00ecnh Ph\u01b0\u1edbc"},{"id":332,"text":"Ph\u01b0\u1eddng Linh Chi\u1ec3u"},{"id":325,"text":"Ph\u01b0\u1eddng Linh \u0110\u00f4ng"},{"id":326,"text":"Ph\u01b0\u1eddng Linh T\u00e2y"},{"id":329,"text":"Ph\u01b0\u1eddng Tam Ph\u00fa"},{"id":328,"text":"Ph\u01b0\u1eddng Tr\u01b0\u1eddng Th\u1ecd"}],"29":[{"id":333,"text":"ph\u01b0\u1eddng B\u00ecnh Th\u1ecd"},{"id":334,"text":"Ph\u01b0\u1eddng Hi\u1ec7p B\u00ecnh Ch\u00e1nh"},{"id":335,"text":"Ph\u01b0\u1eddng Hi\u1ec7p B\u00ecnh Ph\u01b0\u1edbc"},{"id":338,"text":"Ph\u01b0\u1eddng Linh Chi\u1ec3u"},{"id":336,"text":"Ph\u01b0\u1eddng Linh \u0110\u00f4ng"},{"id":337,"text":"Ph\u01b0\u1eddng Linh T\u00e2y"},{"id":340,"text":"Ph\u01b0\u1eddng Tam Ph\u00fa"},{"id":339,"text":"Ph\u01b0\u1eddng Tr\u01b0\u1eddng Th\u1ecd"}],"30":[{"id":347,"text":"Ph\u01b0\u1eddng Hi\u1ec7p Ph\u00fa"},{"id":344,"text":"Ph\u01b0\u1eddng Ph\u01b0\u1edbc B\u00ecnh"},{"id":342,"text":"Ph\u01b0\u1eddng Ph\u01b0\u1edbc Long A"},{"id":343,"text":"Ph\u01b0\u1eddng Ph\u01b0\u1edbc Long B"},{"id":345,"text":"Ph\u01b0\u1eddng T\u0103ng Nh\u01a1n Ph\u00fa A"},{"id":346,"text":"Ph\u01b0\u1eddng T\u0103ng Nh\u01a1n Ph\u00fa B"}],"31":[{"id":348,"text":"Th\u1ea1nh M\u1ef9 L\u1ee3i"}],"32":[{"id":349,"text":"ph\u01b0\u1eddng Ph\u00fa M\u1ef9"}],"33":[{"id":351,"text":"Ph\u01b0\u1eddng 15"},{"id":350,"text":"Ph\u01b0\u1eddng 16"}]}</textarea>
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

<script src="https://fitfood.vn/js/plugins/select2/select2.min.js?v=2026033101"></script>
<script src="https://fitfood.vn/js/plugins/jquery.validation/jquery.validate.min.js?v=2026033101"></script>
<script src="https://fitfood.vn/js/underscore-min.js?v=2026033101"></script>
<script src="https://fitfood.vn/js/plugins.js?v=2026033101"></script>
<script src="https://fitfood.vn/js/modules/tracking.js"></script>
    <script type="text/javascript" src="https://fitfood.vn/js/modules/address_popup.js?v=2026033101"></script>
    <script type="text/javascript" src="https://fitfood.vn/js/modules/profile/address.js?v=2026033101"></script>
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

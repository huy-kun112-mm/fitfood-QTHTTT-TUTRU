<?php
/**
 * Trang tài khoản — wire với DB.
 * - GET: load user từ DB, render form pre-filled
 * - POST: validate + UPDATE users (+ upload avatar nếu có file)
 */
require_once __DIR__ . '/config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
$user_id = (int)$_SESSION['user_id'];

// ---------- Helpers ----------
function options_range(int $from, int $to, ?int $selected, string $placeholder, bool $reverse = false): string {
    $html = '<option value="">' . htmlspecialchars($placeholder) . '</option>';
    $range = $reverse ? range($to, $from) : range($from, $to);
    foreach ($range as $v) {
        $sel = ($selected !== null && $selected === $v) ? ' selected' : '';
        $html .= sprintf('<option value="%d"%s>%02d</option>', $v, $sel, $v);
    }
    return $html;
}

// ---------- State ----------
$errors = [];
$flash_success = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_success']);

// ---------- Load user ----------
$user = null;
if ($pdo) {
    try {
        $stmt = $pdo->prepare(
            "SELECT id, full_name, email, phone, gender, dob, avatar
             FROM users WHERE id = :id LIMIT 1"
        );
        $stmt->execute([':id' => $user_id]);
        $user = $stmt->fetch();
    } catch (PDOException $e) {
        $errors['_db'] = 'Lỗi tải dữ liệu user: ' . $e->getMessage();
    }
}

// ---------- Handle POST ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user && $pdo) {
    $name   = trim((string)($_POST['name']   ?? ''));
    $phone  = trim((string)($_POST['phone']  ?? ''));
    $email  = trim((string)($_POST['email']  ?? ''));
    $gender = (string)($_POST['gender'] ?? '');
    $day    = (int)($_POST['day']    ?? 0);
    $month  = (int)($_POST['month']  ?? 0);
    $year   = (int)($_POST['year']   ?? 0);

    // Validate
    if ($name === '')                           $errors['name']  = 'Vui lòng nhập họ tên.';
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Email không hợp lệ.';
    }
    if ($phone !== '' && !preg_match('/^[0-9]{8,15}$/', $phone)) {
        $errors['phone'] = 'SĐT phải là 8-15 chữ số.';
    }

    // Map gender từ form (m/fm) sang DB (M/F)
    $gender_db = null;
    if     ($gender === 'm')  $gender_db = 'M';
    elseif ($gender === 'fm') $gender_db = 'F';

    // Build DOB nếu có đủ 3 phần
    $dob_db = null;
    if ($day && $month && $year && checkdate($month, $day, $year)) {
        $dob_db = sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    // Avatar upload (1MB max, chỉ JPEG/PNG)
    $new_avatar_filename = null;
    if (!empty($_FILES['avatar']['name']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $size = (int)$_FILES['avatar']['size'];
        if ($size > 1024 * 1024) {
            $errors['avatar'] = 'Kích thước ảnh tối đa 1MB.';
        } else {
            $mime = function_exists('mime_content_type')
                ? mime_content_type($_FILES['avatar']['tmp_name'])
                : null;
            $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
            if (!isset($allowed[$mime])) {
                $errors['avatar'] = 'Chỉ chấp nhận JPEG hoặc PNG.';
            } else {
                $ext = $allowed[$mime];
                $fn  = $user_id . '-' . time() . '.' . $ext;
                $dir = __DIR__ . '/uploads/avatars/';
                if (!is_dir($dir)) @mkdir($dir, 0755, true);
                if (move_uploaded_file($_FILES['avatar']['tmp_name'], $dir . $fn)) {
                    $new_avatar_filename = $fn;
                } else {
                    $errors['avatar'] = 'Không thể lưu file ảnh.';
                }
            }
        }
    } elseif (!empty($_FILES['avatar']['name']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
        $errors['avatar'] = 'Lỗi upload ảnh (mã ' . $_FILES['avatar']['error'] . ').';
    }

    if (empty($errors)) {
        try {
            $set    = ['full_name = :name', 'phone = :phone', 'gender = :gender', 'dob = :dob'];
            $params = [
                ':name'   => $name,
                ':phone'  => $phone !== '' ? $phone : null,
                ':gender' => $gender_db,
                ':dob'    => $dob_db,
                ':id'     => $user_id,
            ];
            if ($new_avatar_filename !== null) {
                $set[] = 'avatar = :avatar';
                $params[':avatar'] = $new_avatar_filename;
            }
            $sql = 'UPDATE users SET ' . implode(', ', $set) . ' WHERE id = :id';
            $pdo->prepare($sql)->execute($params);

            // Cập nhật session
            $_SESSION['user_name'] = $name;
            if ($new_avatar_filename !== null) {
                $_SESSION['user_avatar'] = $new_avatar_filename;
            }
            $_SESSION['flash_success'] = 'Đã cập nhật thông tin tài khoản.';
            header('Location: account.php');
            exit;
        } catch (PDOException $e) {
            $errors['_db'] = 'Lỗi lưu DB: ' . $e->getMessage();
        }
    }

    // Reload user data sau khi POST có lỗi (để form giữ giá trị mới user vừa nhập)
    $user['full_name'] = $name;
    $user['email']     = $email;
    $user['phone']     = $phone;
    $user['gender']    = $gender_db;
    $user['dob']       = $dob_db;
}

// ---------- Helpers cho view ----------
$display_name  = $user['full_name']        ?? ($_SESSION['user_name']  ?? '');
$display_email = $user['email']            ?? ($_SESSION['user_email'] ?? '');
$display_phone = $user['phone']            ?? '';
$display_gender = $user['gender']         ?? '';

$dob_day = $dob_month = $dob_year = null;
if (!empty($user['dob'])) {
    [$dob_year, $dob_month, $dob_day] = array_map('intval', explode('-', $user['dob']));
}

// Avatar URL: nếu là filename → uploads/, nếu http(s):// → external, nếu rỗng → fallback
$av = $user['avatar'] ?? '';
if ($av === '' || $av === null) {
    $avatar_url = 'https://fitfood.vn/img/128/avatars/default.png';
} elseif (preg_match('#^https?://#', $av)) {
    $avatar_url = $av;
} else {
    $avatar_url = 'uploads/avatars/' . htmlspecialchars($av);
}

// Tên ngắn cho navbar (chữ cuối)
$_name_parts = preg_split('/\s+/', trim($display_name));
$short_name  = ($_name_parts && !empty($_name_parts)) ? end($_name_parts) : $display_name;
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
    <link rel="canonical" href="https://fitfood.vn/profile/account" />
    <link rel="alternate" hreflang="vi" href="https://fitfood.vn/profile/account" />


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
    class="" data-url="https://fitfood.vn/profile/account"
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
     <a  class="active nav-link" href="account.php">
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
                            <div class="title ">
                                <h5>Thông tin tài khoản</h5>
                                <p>Quản lý thông tin tài khoản cá nhân</p>
                            </div>
                                                    </div>
                            <div class="frm-account">
        <?php if ($flash_success): ?>
          <div class="alert alert-success" role="alert"><?= htmlspecialchars($flash_success) ?></div>
        <?php endif; ?>
        <?php if (!empty($errors['_db'])): ?>
          <div class="alert alert-danger" role="alert"><?= htmlspecialchars($errors['_db']) ?></div>
        <?php endif; ?>
        <form class="mx-lg-4" method="post" enctype="multipart/form-data">
            <div class="form-group row info">
                <label for="staticEmail" class="col-md-3 col-lg-4 col-xl-3 col-form-label"><strong>Email</strong></label>
                <div class="col-8 col-md-3 col-lg-5 hidden-text">
                    <input type="text" class="form-control" id="staticEmail" name="email"
                           value="<?= htmlspecialchars($display_email) ?>" readonly>
                    <span>******.com</span>
                </div>
            </div>
            <div class="form-group row info">
                <label for="staticPhone" class="col-md-3 col-lg-4 col-xl-3 col-form-label"><strong>SĐT</strong></label>
                <div class="col-8 col-md-3 col-lg-5 hidden-text">
                    <input type="text" class="form-control <?= !empty($errors['phone']) ? 'is-invalid' : '' ?>"
                           id="staticPhone" name="phone" value="<?= htmlspecialchars($display_phone) ?>">
                    <span>**********</span>
                    <?php if (!empty($errors['phone'])): ?>
                      <div class="invalid-feedback d-block"><?= htmlspecialchars($errors['phone']) ?></div>
                    <?php endif; ?>
                </div>
                <div class="col-2 text-right">
                    <a href="javascript:;" class="btn btn-link change-hidden">Thay đổi</a>
                </div>
            </div>
            <div class="form-group row">
                <label for="inputName" class="col-md-3 col-lg-4 col-xl-3 col-form-label"><strong>Họ &amp; Tên</strong></label>
                <div class="col-md-9 col-lg-8 col-xl-9">
                    <input type="text" class="form-control <?= !empty($errors['name']) ? 'is-invalid' : '' ?>"
                           id="inputName" name="name" value="<?= htmlspecialchars($display_name) ?>">
                    <?php if (!empty($errors['name'])): ?>
                      <div class="invalid-feedback d-block"><?= htmlspecialchars($errors['name']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="form-group row">
                <label for="inputSex" class="col-md-3 col-lg-4 col-xl-3 col-form-label"><strong>Giới tính</strong></label>
                <div class="col-md-9 col-lg-8 col-xl-9">
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="gender" id="inlineRadio1" value="m"
                               <?= $display_gender === 'M' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="inlineRadio1">Nam</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="gender" id="inlineRadio2" value="fm"
                               <?= $display_gender === 'F' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="inlineRadio2">Nữ</label>
                    </div>
                </div>
            </div>
            <div class="form-group row">
                <label for="inputBirth" class="col-md-3 col-lg-4 col-xl-3 col-form-label"><strong>Ngày sinh</strong></label>
                <div class="col-md-9 col-lg-8 col-xl-9">
                    <div class="form-row">
                        <div class="form-group col-4">
                            <select id="inputDate" class="form-control" name="day">
                                <?= options_range(1, 31, $dob_day, 'Ngày') ?>
                            </select>
                        </div>
                        <div class="form-group col-4">
                            <select id="inputMonth" name="month" class="form-control">
                                <?= options_range(1, 12, $dob_month, 'Tháng') ?>
                            </select>
                        </div>
                        <div class="form-group col-4">
                            <select id="inputYear" name="year" class="form-control">
                                <?= options_range(1900, (int)date('Y'), $dob_year, 'Năm', true) ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            <?php // === Static options gốc đã bị xoá khi wire DB === ?>
            <?php /* DEAD_CODE_START
                                                                <option value="14" >14</option>
                                                                <option value="15" >15</option>
                                                                <option value="16" >16</option>
                                                                <option value="17" >17</option>
                                                                <option value="18" >18</option>
                                                                <option value="19" >19</option>
                                                                <option value="20" >20</option>
                                                                <option value="21" >21</option>
                                                                <option value="22" >22</option>
                                                                <option value="23" >23</option>
                                                                <option value="24" >24</option>
                                                                <option value="25" >25</option>
                                                                <option value="26" >26</option>
                                                                <option value="27" >27</option>
                                                                <option value="28" >28</option>
                                                                <option value="29" >29</option>
                                                                <option value="30" >30</option>
                                                                <option value="31" >31</option>
                                                            </select>
                        </div>
                        <div class="form-group col-4">
                            <select id="inputMonth" name="month" class="form-control">
                                <option value="">Tháng</option>
                                                                    <option value="1" >01</option>
                                                                    <option value="2" >02</option>
                                                                    <option value="3" >03</option>
                                                                    <option value="4" >04</option>
                                                                    <option value="5" >05</option>
                                                                    <option value="6" >06</option>
                                                                    <option value="7" >07</option>
                                                                    <option value="8" >08</option>
                                                                    <option value="9" >09</option>
                                                                    <option value="10" >10</option>
                                                                    <option value="11" >11</option>
                                                                    <option value="12" >12</option>
                                                            </select>
                        </div>
                        <div class="form-group col-4">
                            <select id="inputYear" name="year" class="form-control">
                                <option value="">Năm</option>
                                                                    <option value="2016" >2016</option>
                                                                    <option value="2015" >2015</option>
                                                                    <option value="2014" >2014</option>
                                                                    <option value="2013" >2013</option>
                                                                    <option value="2012" >2012</option>
                                                                    <option value="2011" >2011</option>
                                                                    <option value="2010" >2010</option>
                                                                    <option value="2009" >2009</option>
                                                                    <option value="2008" >2008</option>
                                                                    <option value="2007" >2007</option>
                                                                    <option value="2006" >2006</option>
                                                                    <option value="2005" >2005</option>
                                                                    <option value="2004" >2004</option>
                                                                    <option value="2003" >2003</option>
                                                                    <option value="2002" >2002</option>
                                                                    <option value="2001" >2001</option>
                                                                    <option value="2000" >2000</option>
                                                                    <option value="1999" >1999</option>
                                                                    <option value="1998" >1998</option>
                                                                    <option value="1997" >1997</option>
                                                                    <option value="1996" >1996</option>
                                                                    <option value="1995" >1995</option>
                                                                    <option value="1994" >1994</option>
                                                                    <option value="1993" >1993</option>
                                                                    <option value="1992" >1992</option>
                                                                    <option value="1991" >1991</option>
                                                                    <option value="1990" >1990</option>
                                                                    <option value="1989" >1989</option>
                                                                    <option value="1988" >1988</option>
                                                                    <option value="1987" >1987</option>
                                                                    <option value="1986" >1986</option>
                                                                    <option value="1985" >1985</option>
                                                                    <option value="1984" >1984</option>
                                                                    <option value="1983" >1983</option>
                                                                    <option value="1982" >1982</option>
                                                                    <option value="1981" >1981</option>
                                                                    <option value="1980" >1980</option>
                                                                    <option value="1979" >1979</option>
                                                                    <option value="1978" >1978</option>
                                                                    <option value="1977" >1977</option>
                                                                    <option value="1976" >1976</option>
                                                                    <option value="1975" >1975</option>
                                                                    <option value="1974" >1974</option>
                                                                    <option value="1973" >1973</option>
                                                                    <option value="1972" >1972</option>
                                                                    <option value="1971" >1971</option>
                                                                    <option value="1970" >1970</option>
                                                                    <option value="1969" >1969</option>
                                                                    <option value="1968" >1968</option>
                                                                    <option value="1967" >1967</option>
                                                                    <option value="1966" >1966</option>
                                                                    <option value="1965" >1965</option>
                                                                    <option value="1964" >1964</option>
                                                                    <option value="1963" >1963</option>
                                                                    <option value="1962" >1962</option>
                                                                    <option value="1961" >1961</option>
                                                                    <option value="1960" >1960</option>
                                                                    <option value="1959" >1959</option>
                                                                    <option value="1958" >1958</option>
                                                                    <option value="1957" >1957</option>
                                                                    <option value="1956" >1956</option>
                                                                    <option value="1955" >1955</option>
                                                                    <option value="1954" >1954</option>
                                                                    <option value="1953" >1953</option>
                                                                    <option value="1952" >1952</option>
                                                                    <option value="1951" >1951</option>
                                                                    <option value="1950" >1950</option>
                                                                    <option value="1949" >1949</option>
                                                                    <option value="1948" >1948</option>
                                                                    <option value="1947" >1947</option>
                                                                    <option value="1946" >1946</option>
                                                                    <option value="1945" >1945</option>
                                                                    <option value="1944" >1944</option>
                                                                    <option value="1943" >1943</option>
                                                                    <option value="1942" >1942</option>
                                                                    <option value="1941" >1941</option>
                                                                    <option value="1940" >1940</option>
                                                                    <option value="1939" >1939</option>
                                                                    <option value="1938" >1938</option>
                                                                    <option value="1937" >1937</option>
                                                                    <option value="1936" >1936</option>
                                                                    <option value="1935" >1935</option>
                                                                    <option value="1934" >1934</option>
                                                                    <option value="1933" >1933</option>
                                                                    <option value="1932" >1932</option>
                                                                    <option value="1931" >1931</option>
                                                                    <option value="1930" >1930</option>
                                                                    <option value="1929" >1929</option>
                                                                    <option value="1928" >1928</option>
                                                                    <option value="1927" >1927</option>
                                                                    <option value="1926" >1926</option>
                                                                    <option value="1925" >1925</option>
                                                                    <option value="1924" >1924</option>
                                                                    <option value="1923" >1923</option>
                                                                    <option value="1922" >1922</option>
                                                                    <option value="1921" >1921</option>
                                                                    <option value="1920" >1920</option>
                                                                    <option value="1919" >1919</option>
                                                                    <option value="1918" >1918</option>
                                                                    <option value="1917" >1917</option>
                                                                    <option value="1916" >1916</option>
                                                                    <option value="1915" >1915</option>
                                                                    <option value="1914" >1914</option>
                                                                    <option value="1913" >1913</option>
                                                                    <option value="1912" >1912</option>
                                                                    <option value="1911" >1911</option>
                                                                    <option value="1910" >1910</option>
                                                                    <option value="1909" >1909</option>
                                                                    <option value="1908" >1908</option>
                                                                    <option value="1907" >1907</option>
                                                                    <option value="1906" >1906</option>
                                                                    <option value="1905" >1905</option>
                                                                    <option value="1904" >1904</option>
                                                                    <option value="1903" >1903</option>
                                                                    <option value="1902" >1902</option>
                                                                    <option value="1901" >1901</option>
                                                                    <option value="1900" >1900</option>
                                                            </select>
                        </div>
                    </div>
                                    </div>
            </div>
            DEAD_CODE_END */ ?>
            <div class="form-group row">
                <label for="inputAvatar" class="col-md-3 col-lg-4 col-xl-3 col-form-label"><strong>Avatar</strong></label>
                <div class="col-md-9 col-lg-8 col-xl-9">
                    <div class="d-flex upload-preview">
                        <div class="avatar mr-4 image-preview" style="background-image: url('<?= $avatar_url ?>')">
                        </div>
                        <div class="upload">
                            <input type="file" name="avatar" id="image-upload" class="inputfile" accept="image/jpeg,image/png" />
                            <label for="image-upload">Hình</label>
                            <p>Kích thước tối đa là 1 MB<br/>Format: .JPEG, .PNG</p>
                            <?php if (!empty($errors['avatar'])): ?>
                              <p class="text-danger small"><?= htmlspecialchars($errors['avatar']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                                    </div>
            </div>
            <div class="form-group row">
                <div class="offset-md-3 col-md-9 offset-lg-4 col-lg-8 offset-xl-3 col-xl-9">
                    <button class="btn btn-primary" type="submit">Lưu</button>
                </div>
            </div>
        </form>
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
    <script type="text/javascript" src="https://fitfood.vn/js/modules/profile/account.js?v=2026033101"></script>
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

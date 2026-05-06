<?php
/**
 * Shared sidebar cho admin pages.
 * Set $active_page trước khi include để highlight đúng menu item.
 *   include __DIR__ . '/partials/sidebar.php';   với  $active_page = 'inventory';
 *
 * Giá trị $active_page hợp lệ: dashboard, inventory, orders, customers
 */
$active_page = $active_page ?? '';

$nav_items = [
    ['key' => 'dashboard', 'href' => 'index.php',     'icon' => 'ti-home',          'label' => 'Dashboard'],
    ['key' => 'inventory', 'href' => 'inventory.php', 'icon' => 'ti-box-seam',      'label' => 'Sản phẩm'],
    ['key' => 'orders',    'href' => 'orders.php',    'icon' => 'ti-shopping-cart', 'label' => 'Đơn hàng'],
    ['key' => 'reviews',   'href' => 'reviews.php',   'icon' => 'ti-message-circle','label' => 'Bình luận'],
    ['key' => 'finance',   'href' => 'finance.php',   'icon' => 'ti-cash',          'label' => 'Thu chi'],
    ['key' => 'customers', 'href' => 'customers.php', 'icon' => 'ti-users',         'label' => 'Khách hàng'],
];
?>
<aside id="sidebar" class="sidebar">
  <div class="logo-area">
    <a href="index.php" class="d-inline-flex align-items-center text-decoration-none">
      <img src="./assets/images/logo-icon.svg" alt="" width="24">
      <span class="logo-text ms-2"><img src="./assets/images/logo.svg" alt=""></span>
    </a>
  </div>

  <ul class="nav flex-column">
    <li class="px-4 py-2"><small class="nav-text text-muted">Quản lý</small></li>
    <?php foreach ($nav_items as $item): ?>
      <li>
        <a class="nav-link <?= $active_page === $item['key'] ? 'active' : '' ?>"
           href="<?= htmlspecialchars($item['href']) ?>">
          <i class="ti <?= htmlspecialchars($item['icon']) ?>"></i>
          <span class="nav-text"><?= htmlspecialchars($item['label']) ?></span>
        </a>
      </li>
    <?php endforeach; ?>

    <li class="px-4 pt-4 pb-2"><small class="nav-text text-muted">Tài khoản</small></li>
    <li>
      <a class="nav-link" href="../logout.php">
        <i class="ti ti-logout"></i><span class="nav-text">Đăng xuất</span>
      </a>
    </li>
  </ul>
</aside>

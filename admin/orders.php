<?php
/**
 * FitFood — Admin / Đơn hàng
 * URL: http://localhost:8080/admin/orders.php
 *
 * Hỗ trợ: search theo #id, tên khách, SĐT; filter theo status;
 *         pagination 10/trang. Update status & xoá (soft delete = set
 *         status = cancelled) qua AJAX → admin/order-action.php.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/partials/auth_guard.php';

// ---------- Helpers ----------
function vnd($n): string {
    return number_format((float)$n, 0, ',', '.') . '₫';
}

function status_label(string $s): array {
    $map = [
        'pending'    => ['Chờ duyệt',  'warning'],
        'processing' => ['Đang xử lý', 'primary'],
        'completed'  => ['Hoàn tất',   'success'],
        'cancelled'  => ['Đã huỷ',     'danger'],
    ];
    return $map[$s] ?? [$s, 'secondary'];
}

// ---------- Inputs ----------
$q       = trim((string)($_GET['q']      ?? ''));
$status  = trim((string)($_GET['status'] ?? ''));
$page    = max(1, (int)($_GET['page']    ?? 1));
$per_page = 10;
$offset  = ($page - 1) * $per_page;

// Whitelist status để tránh SQL injection (dù đã prepared)
$allowed_status = ['pending', 'processing', 'completed', 'cancelled'];
if (!in_array($status, $allowed_status, true)) $status = '';

// ---------- Queries ----------
$orders      = [];
$total_rows  = 0;
$query_error = null;

if ($pdo === null) {
    $query_error = 'Không thể kết nối database. Kiểm tra docker-compose hoặc config/database.php.';
} else {
    try {
        // Build WHERE động
        $where  = [];
        $params = [];

        if ($q !== '') {
            // Cho phép tìm theo "#123" → 123, hoặc theo tên/phone
            $clean_id = ltrim($q, '#');
            if (ctype_digit($clean_id)) {
                $where[] = '(o.id = :oid OR o.recipient_name LIKE :q OR o.phone LIKE :q OR u.full_name LIKE :q)';
                $params[':oid'] = (int)$clean_id;
                $params[':q']   = '%' . $q . '%';
            } else {
                $where[] = '(o.recipient_name LIKE :q OR o.phone LIKE :q OR u.full_name LIKE :q)';
                $params[':q']   = '%' . $q . '%';
            }
        }
        if ($status !== '') {
            $where[] = 'o.status = :status';
            $params[':status'] = $status;
        }

        $where_sql = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);

        // Count total
        $count_sql = "
            SELECT COUNT(*)
            FROM orders o
            LEFT JOIN users u ON u.id = o.user_id
            $where_sql
        ";
        $count_stmt = $pdo->prepare($count_sql);
        $count_stmt->execute($params);
        $total_rows = (int)$count_stmt->fetchColumn();

        // Main SELECT — kèm sản phẩm đầu tiên + đếm tổng số item của từng đơn
        // (LIMIT inline với integer đã sanitize qua (int))
        $sql = "
            SELECT
                o.id,
                o.recipient_name,
                o.phone,
                o.total_amount,
                o.status,
                o.created_at,
                u.full_name           AS user_name,
                first_item.product_name,
                items.item_count
            FROM orders o
            LEFT JOIN users u ON u.id = o.user_id
            LEFT JOIN (
                SELECT oi.order_id, COUNT(*) AS item_count
                FROM order_items oi
                GROUP BY oi.order_id
            ) items ON items.order_id = o.id
            LEFT JOIN (
                SELECT f.order_id, p.name AS product_name
                FROM (
                    SELECT order_id, MIN(id) AS first_id
                    FROM order_items
                    GROUP BY order_id
                ) f
                INNER JOIN order_items oi ON oi.id = f.first_id
                INNER JOIN products    p  ON p.id  = oi.product_id
            ) first_item ON first_item.order_id = o.id
            $where_sql
            ORDER BY o.created_at DESC, o.id DESC
            LIMIT $per_page OFFSET $offset
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $orders = $stmt->fetchAll();
    } catch (PDOException $e) {
        $query_error = 'Lỗi truy vấn DB: ' . $e->getMessage();
        error_log('[admin/orders] ' . $e->getMessage());
    }
}

$total_pages     = max(1, (int)ceil($total_rows / $per_page));
$placeholder_img = './assets/images/logo-icon.svg';
$active_page     = 'orders';

// One-shot flash message (nếu sau này muốn redirect kèm flash)
$flash_success = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_success']);

function page_url(int $p): string {
    $params = $_GET;
    $params['page'] = $p;
    return '?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8" />
  <title>Đơn hàng - FitFood Admin</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link rel="apple-touch-icon" sizes="180x180" href="./assets/images/favicon_io/apple-touch-icon.png">
  <link rel="icon" type="image/png" sizes="32x32"  href="./assets/images/favicon_io/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16"  href="./assets/images/favicon_io/favicon-16x16.png">
  <link rel="manifest" href="./assets/images/favicon_io/site.webmanifest">

  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.35.0/dist/tabler-icons.min.css" rel="stylesheet">

  <link rel="stylesheet" href="./assets/css/style.css">
</head>
<body>

<div id="overlay" class="overlay"></div>

<!-- TOPBAR -->
<nav id="topbar" class="navbar bg-white border-bottom fixed-top topbar px-3">
  <button id="toggleBtn" class="d-none d-lg-inline-flex btn btn-light btn-icon btn-sm">
    <i class="ti ti-layout-sidebar-left-expand"></i>
  </button>
  <button id="mobileBtn" class="btn btn-light btn-icon btn-sm d-lg-none me-2">
    <i class="ti ti-layout-sidebar-left-expand"></i>
  </button>
  <div>
    <ul class="list-unstyled d-flex align-items-center mb-0 gap-1">
      <li>
        <a class="position-relative btn-icon btn-sm btn-light btn rounded-circle" href="#" role="button">
          <i class="ti ti-bell"></i>
        </a>
      </li>
      <li class="ms-3 dropdown">
        <a href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
          <img src="./assets/images/avatar/avatar-1.jpg" alt="" class="avatar avatar-sm rounded-circle">
        </a>
        <div class="dropdown-menu dropdown-menu-end p-0" style="min-width: 200px;">
          <div class="d-flex gap-3 align-items-center border-bottom px-3 py-3">
            <img src="./assets/images/avatar/avatar-1.jpg" alt="" class="avatar avatar-md rounded-circle">
            <div>
              <h4 class="mb-0 small">Admin FitFood</h4>
              <p class="mb-0 small text-muted">@admin</p>
            </div>
          </div>
          <div class="p-3 d-flex flex-column gap-1 small lh-lg">
            <a href="../index.php" class="text-body">Về trang chủ</a>
            <a href="../logout.php" class="text-body">Đăng xuất</a>
          </div>
        </div>
      </li>
    </ul>
  </div>
</nav>

<!-- SIDEBAR -->
<?php include __DIR__ . '/partials/sidebar.php'; ?>

<!-- MAIN CONTENT -->
<main id="content" class="content py-10">
  <div class="container-fluid pt-5">

    <div class="row mt-4">
      <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
          <div>
            <h1 class="fs-3 mb-1">Đơn hàng</h1>
            <p class="text-muted mb-0">Danh sách đơn hàng và cập nhật trạng thái.</p>
          </div>
        </div>
      </div>
    </div>

    <?php if ($flash_success): ?>
      <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="ti ti-circle-check me-2"></i>
        <?= htmlspecialchars($flash_success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php endif; ?>

    <?php if ($query_error): ?>
      <div class="alert alert-danger" role="alert">
        <i class="ti ti-alert-triangle me-2"></i>
        <?= htmlspecialchars($query_error, ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php endif; ?>

    <!-- Toast container cho thông báo AJAX -->
    <div id="ordersToast" class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1080;"></div>

    <!-- TOOLBAR: search + filter -->
    <form method="GET" class="card p-3 mb-3">
      <div class="row g-2 align-items-center">
        <div class="col-md-5">
          <div class="input-group">
            <span class="input-group-text bg-white"><i class="ti ti-search"></i></span>
            <input type="text" name="q" class="form-control"
                   placeholder="Tìm theo mã đơn (#123), tên khách, hoặc SĐT…"
                   value="<?= htmlspecialchars($q) ?>">
          </div>
        </div>
        <div class="col-md-3">
          <select name="status" class="form-select">
            <option value="">Tất cả trạng thái</option>
            <option value="pending"    <?= $status === 'pending'    ? 'selected' : '' ?>>Chờ duyệt</option>
            <option value="processing" <?= $status === 'processing' ? 'selected' : '' ?>>Đang xử lý</option>
            <option value="completed"  <?= $status === 'completed'  ? 'selected' : '' ?>>Hoàn tất</option>
            <option value="cancelled"  <?= $status === 'cancelled'  ? 'selected' : '' ?>>Đã huỷ</option>
          </select>
        </div>
        <div class="col-md-4 d-flex gap-2">
          <button class="btn btn-primary flex-grow-1" type="submit">
            <i class="ti ti-filter me-1"></i>Lọc
          </button>
          <?php if ($q !== '' || $status !== ''): ?>
            <a href="orders.php" class="btn btn-outline-secondary" title="Xoá bộ lọc">
              <i class="ti ti-x"></i>
            </a>
          <?php endif; ?>
        </div>
      </div>
    </form>

    <!-- TABLE -->
    <div class="card table-responsive">
      <table class="table mb-0 text-nowrap table-hover align-middle">
        <thead class="table-light border-light">
          <tr>
            <th>Mã đơn</th>
            <th>Khách hàng</th>
            <th>Sản phẩm</th>
            <th class="text-end">Tổng tiền</th>
            <th class="text-center">Trạng thái</th>
            <th>Thời gian đặt</th>
            <th class="text-center">Hành động</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($orders)): ?>
            <tr>
              <td colspan="7" class="text-center text-muted py-4">
                Không tìm thấy đơn hàng phù hợp.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($orders as $o):
              $oid          = (int)$o['id'];
              $cust_name    = $o['recipient_name'] ?: ($o['user_name'] ?: 'Khách vãng lai');
              $phone        = $o['phone'] ?: '—';
              $first_prod   = $o['product_name'] ?? '—';
              $item_count   = (int)($o['item_count'] ?? 0);
              $more_count   = max(0, $item_count - 1);
              [$st_label, $st_color] = status_label($o['status']);
              $is_cancelled = ($o['status'] === 'cancelled');
            ?>
              <tr data-order-id="<?= $oid ?>">
                <td>
                  <a href="#" class="js-open-detail fw-semibold text-body text-decoration-none"
                     data-order-id="<?= $oid ?>">
                    #<?= str_pad((string)$oid, 6, '0', STR_PAD_LEFT) ?>
                  </a>
                </td>
                <td>
                  <div><?= htmlspecialchars($cust_name) ?></div>
                  <small class="text-muted"><?= htmlspecialchars($phone) ?></small>
                </td>
                <td>
                  <div><?= htmlspecialchars($first_prod) ?></div>
                  <?php if ($more_count > 0): ?>
                    <small class="text-muted">(+<?= $more_count ?> sản phẩm nữa)</small>
                  <?php endif; ?>
                </td>
                <td class="text-end fw-semibold">
                  <?= htmlspecialchars(vnd($o['total_amount'])) ?>
                </td>
                <td class="text-center">
                  <select class="form-select form-select-sm js-status-select w-auto d-inline-block"
                          data-order-id="<?= $oid ?>"
                          data-current="<?= htmlspecialchars($o['status']) ?>"
                          <?= $is_cancelled ? 'disabled' : '' ?>>
                    <?php foreach ($allowed_status as $s):
                      [$lbl,] = status_label($s);
                    ?>
                      <option value="<?= $s ?>" <?= $o['status'] === $s ? 'selected' : '' ?>>
                        <?= htmlspecialchars($lbl) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </td>
                <td>
                  <small><?= htmlspecialchars(date('d/m/Y H:i', strtotime($o['created_at']))) ?></small>
                </td>
                <td class="text-center">
                  <a href="#" class="js-open-detail" title="Xem chi tiết" data-order-id="<?= $oid ?>">
                    <i class="ti ti-eye"></i>
                  </a>
                  <?php if (!$is_cancelled): ?>
                    <a href="#" class="js-cancel-order link-danger ms-2" title="Huỷ đơn"
                       data-order-id="<?= $oid ?>">
                      <i class="ti ti-trash"></i>
                    </a>
                  <?php else: ?>
                    <span class="text-muted ms-2" title="Đã huỷ">
                      <i class="ti ti-trash-off"></i>
                    </span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- PAGINATION -->
    <?php if ($total_rows > 0): ?>
      <div class="d-flex justify-content-between align-items-center mt-3">
        <div class="text-muted small">
          Hiển thị <strong><?= $offset + 1 ?>–<?= min($offset + $per_page, $total_rows) ?></strong>
          / <strong><?= $total_rows ?></strong> đơn hàng
        </div>

        <?php if ($total_pages > 1): ?>
          <nav aria-label="Phân trang">
            <ul class="pagination mb-0">
              <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= $page <= 1 ? '#' : page_url($page - 1) ?>">Previous</a>
              </li>
              <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                  <a class="page-link" href="<?= page_url($i) ?>"><?= $i ?></a>
                </li>
              <?php endfor; ?>
              <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= $page >= $total_pages ? '#' : page_url($page + 1) ?>">Next</a>
              </li>
            </ul>
          </nav>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <div class="row mt-3">
      <div class="col-12">
        <footer class="text-center py-2 mt-6 text-secondary">
          <p class="mb-0 small">© 2026 FitFood Admin.</p>
        </footer>
      </div>
    </div>

  </div>
</main>

<!-- ========== MODAL CHI TIẾT ĐƠN HÀNG ========== -->
<div class="modal fade" id="orderDetailModal" tabindex="-1" aria-labelledby="orderDetailLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="orderDetailLabel">Chi tiết đơn hàng</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="orderDetailBody">
        <!-- JS sẽ inject nội dung vào đây -->
        <div class="text-center text-muted py-5">
          <div class="spinner-border spinner-border-sm me-2"></div>Đang tải…
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="./assets/js/main.js"></script>
<script src="./assets/js/orders.js"></script>
</body>
</html>

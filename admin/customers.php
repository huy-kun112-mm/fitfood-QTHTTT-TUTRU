<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/partials/auth_guard.php';

function fmt_date(?string $ts): string {
    if (!$ts) return '—';
    $t = strtotime($ts);
    return $t ? date('d/m/Y H:i', $t) : '—';
}

$q = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

$customers = [];
$total_rows = 0;
$query_error = null;

if ($pdo === null) {
    $query_error = 'Không thể kết nối database. Kiểm tra docker-compose hoặc config/database.php.';
} else {
    try {
        $where = "WHERE u.role <> 'admin'";
        $params = [];

        if ($q !== '') {
            $where .= " AND (u.full_name LIKE :q OR u.email LIKE :q OR u.phone LIKE :q)";
            $params[':q'] = '%' . $q . '%';
        }

        $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM users u $where");
        $count_stmt->execute($params);
        $total_rows = (int)$count_stmt->fetchColumn();

        $sql = "
            SELECT
                u.id,
                u.full_name,
                u.email,
                u.phone,
                u.provider,
                u.status,
                u.created_at,
                COUNT(DISTINCT o.id) AS total_orders,
                COALESCE(SUM(CASE WHEN o.status = 'completed' THEN o.total_amount ELSE 0 END), 0) AS total_spent
            FROM users u
            LEFT JOIN orders o ON o.user_id = u.id
            $where
            GROUP BY u.id, u.full_name, u.email, u.phone, u.provider, u.status, u.created_at
            ORDER BY u.created_at DESC, u.id DESC
            LIMIT $per_page OFFSET $offset
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $customers = $stmt->fetchAll();
    } catch (PDOException $e) {
        $query_error = 'Lỗi truy vấn DB: ' . $e->getMessage();
        error_log('[admin/customers] ' . $e->getMessage());
    }
}

$total_pages = max(1, (int)ceil($total_rows / $per_page));
$active_page = 'customers';

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
  <title>Khách hàng - FitFood Admin</title>
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
<nav id="topbar" class="navbar bg-white border-bottom fixed-top topbar px-3">
  <button id="toggleBtn" class="d-none d-lg-inline-flex btn btn-light btn-icon btn-sm"><i class="ti ti-layout-sidebar-left-expand"></i></button>
  <button id="mobileBtn" class="btn btn-light btn-icon btn-sm d-lg-none me-2"><i class="ti ti-layout-sidebar-left-expand"></i></button>
</nav>
<?php include __DIR__ . '/partials/sidebar.php'; ?>
<main id="content" class="content py-10">
  <div class="container-fluid pt-5">
    <div class="row mt-4">
      <div class="col-12">
        <div class="mb-4">
          <h1 class="fs-3 mb-1">Khách hàng</h1>
          <p class="text-muted mb-0">Danh sách tài khoản khách hàng và thống kê mua hàng.</p>
        </div>
      </div>
    </div>

    <?php if ($query_error): ?>
      <div class="alert alert-danger" role="alert">
        <i class="ti ti-alert-triangle me-2"></i><?= htmlspecialchars($query_error, ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php endif; ?>

    <form method="GET" class="card p-3 mb-3">
      <div class="row g-2 align-items-center">
        <div class="col-md-10">
          <div class="input-group">
            <span class="input-group-text bg-white"><i class="ti ti-search"></i></span>
            <input type="text" name="q" class="form-control" placeholder="Tìm theo tên, email hoặc SĐT..."
                   value="<?= htmlspecialchars($q) ?>">
          </div>
        </div>
        <div class="col-md-2 d-flex gap-2">
          <button class="btn btn-primary flex-grow-1" type="submit">Lọc</button>
          <?php if ($q !== ''): ?>
            <a href="customers.php" class="btn btn-outline-secondary"><i class="ti ti-x"></i></a>
          <?php endif; ?>
        </div>
      </div>
    </form>

    <div class="card table-responsive">
      <table class="table mb-0 text-nowrap table-hover align-middle">
        <thead class="table-light border-light">
          <tr>
            <th>Khách hàng</th>
            <th>SĐT</th>
            <th>Nguồn đăng nhập</th>
            <th class="text-center">Đơn hàng</th>
            <th class="text-end">Đã chi</th>
            <th>Ngày tạo</th>
            <th class="text-center">Trạng thái</th>
            <th class="text-center">Chi tiết</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($customers)): ?>
            <tr><td colspan="8" class="text-center text-muted py-4">Không tìm thấy khách hàng phù hợp.</td></tr>
          <?php else: ?>
            <?php foreach ($customers as $c): ?>
              <tr>
                <td>
                  <div class="fw-semibold"><?= htmlspecialchars($c['full_name']) ?></div>
                  <small class="text-muted"><?= htmlspecialchars($c['email']) ?></small>
                </td>
                <td><?= htmlspecialchars($c['phone'] ?: '—') ?></td>
                <td><span class="badge bg-secondary-subtle text-secondary"><?= htmlspecialchars($c['provider']) ?></span></td>
                <td class="text-center"><?= (int)$c['total_orders'] ?></td>
                <td class="text-end fw-semibold"><?= number_format((float)$c['total_spent'], 0, ',', '.') ?>₫</td>
                <td><?= htmlspecialchars(fmt_date($c['created_at'])) ?></td>
                <td class="text-center">
                  <?php if ((int)$c['status'] === 1): ?>
                    <span class="badge bg-success-subtle text-success">Hoạt động</span>
                  <?php else: ?>
                    <span class="badge bg-secondary-subtle text-secondary">Tạm khoá</span>
                  <?php endif; ?>
                </td>
                <td class="text-center">
                  <button type="button" class="btn btn-sm btn-outline-primary js-view-customer"
                          data-id="<?= (int)$c['id'] ?>" title="Xem chi tiết">
                    <i class="ti ti-eye"></i>
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php if ($total_rows > 0): ?>
      <div class="d-flex justify-content-between align-items-center mt-3">
        <div class="text-muted small">
          Hiển thị <strong><?= $offset + 1 ?>–<?= min($offset + $per_page, $total_rows) ?></strong> / <strong><?= $total_rows ?></strong> khách hàng
        </div>
        <?php if ($total_pages > 1): ?>
          <nav aria-label="Phân trang">
            <ul class="pagination mb-0">
              <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= $page <= 1 ? '#' : page_url($page - 1) ?>">Previous</a></li>
              <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>"><a class="page-link" href="<?= page_url($i) ?>"><?= $i ?></a></li>
              <?php endfor; ?>
              <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>"><a class="page-link" href="<?= $page >= $total_pages ? '#' : page_url($page + 1) ?>">Next</a></li>
            </ul>
          </nav>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</main>
<div class="modal fade" id="customerDetailModal" tabindex="-1" aria-labelledby="customerDetailLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="customerDetailLabel">Chi tiết khách hàng</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="customerDetailBody">
        <div class="text-center text-muted py-5">
          <div class="spinner-border spinner-border-sm me-2"></div>Đang tải…
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="./assets/js/main.js"></script>
<script src="./assets/js/customers.js"></script>
</body>
</html>

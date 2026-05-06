<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/partials/auth_guard.php';

function money($n): string {
    return number_format((float)$n, 0, ',', '.') . '₫';
}

$stats = [
    'customers' => 0,
    'orders' => 0,
    'completed_orders' => 0,
    'revenue' => 0,
];
$top_customers = [];
$status_breakdown = [];
$query_error = null;

if ($pdo === null) {
    $query_error = 'Không thể kết nối database. Kiểm tra docker-compose hoặc config/database.php.';
} else {
    try {
        $stats['customers'] = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role <> 'admin'")->fetchColumn();
        $stats['orders'] = (int)$pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
        $stats['completed_orders'] = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'completed'")->fetchColumn();
        $stats['revenue'] = (float)$pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE status = 'completed'")->fetchColumn();

        $top_customers = $pdo->query("
            SELECT
                u.full_name,
                u.email,
                COUNT(o.id) AS total_orders,
                COALESCE(SUM(CASE WHEN o.status = 'completed' THEN o.total_amount ELSE 0 END), 0) AS total_spent
            FROM users u
            LEFT JOIN orders o ON o.user_id = u.id
            WHERE u.role <> 'admin'
            GROUP BY u.id, u.full_name, u.email
            ORDER BY total_spent DESC, total_orders DESC, u.id ASC
            LIMIT 5
        ")->fetchAll();

        $status_breakdown = $pdo->query("
            SELECT status, COUNT(*) AS total, COALESCE(SUM(total_amount), 0) AS amount
            FROM orders
            GROUP BY status
            ORDER BY FIELD(status, 'pending', 'processing', 'completed', 'cancelled')
        ")->fetchAll();
    } catch (PDOException $e) {
        $query_error = 'Lỗi truy vấn DB: ' . $e->getMessage();
        error_log('[admin/reports] ' . $e->getMessage());
    }
}

$active_page = 'reports';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8" />
  <title>Báo cáo - FitFood Admin</title>
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
          <h1 class="fs-3 mb-1">Báo cáo</h1>
          <p class="text-muted mb-0">Tổng hợp số liệu khách hàng và đơn hàng từ dữ liệu hiện tại.</p>
        </div>
      </div>
    </div>

    <?php if ($query_error): ?>
      <div class="alert alert-danger" role="alert">
        <i class="ti ti-alert-triangle me-2"></i><?= htmlspecialchars($query_error, ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php endif; ?>

    <div class="row g-3 mb-3">
      <div class="col-lg-3 col-md-6">
        <div class="card p-4 h-100"><div class="text-muted small mb-1">Tổng khách hàng</div><div class="fs-3 fw-bold"><?= number_format($stats['customers']) ?></div></div>
      </div>
      <div class="col-lg-3 col-md-6">
        <div class="card p-4 h-100"><div class="text-muted small mb-1">Tổng đơn hàng</div><div class="fs-3 fw-bold"><?= number_format($stats['orders']) ?></div></div>
      </div>
      <div class="col-lg-3 col-md-6">
        <div class="card p-4 h-100"><div class="text-muted small mb-1">Đơn hoàn tất</div><div class="fs-3 fw-bold"><?= number_format($stats['completed_orders']) ?></div></div>
      </div>
      <div class="col-lg-3 col-md-6">
        <div class="card p-4 h-100"><div class="text-muted small mb-1">Doanh thu hoàn tất</div><div class="fs-3 fw-bold"><?= htmlspecialchars(money($stats['revenue'])) ?></div></div>
      </div>
    </div>

    <div class="row g-3">
      <div class="col-lg-6">
        <div class="card h-100">
          <div class="card-header bg-white"><h2 class="h5 mb-0">Khách hàng chi tiêu cao</h2></div>
          <div class="table-responsive">
            <table class="table mb-0 align-middle">
              <thead class="table-light">
                <tr><th>Khách hàng</th><th class="text-center">Đơn</th><th class="text-end">Đã chi</th></tr>
              </thead>
              <tbody>
              <?php if (empty($top_customers)): ?>
                <tr><td colspan="3" class="text-center text-muted py-4">Chưa có dữ liệu.</td></tr>
              <?php else: foreach ($top_customers as $c): ?>
                <tr>
                  <td><div class="fw-semibold"><?= htmlspecialchars($c['full_name']) ?></div><small class="text-muted"><?= htmlspecialchars($c['email']) ?></small></td>
                  <td class="text-center"><?= (int)$c['total_orders'] ?></td>
                  <td class="text-end fw-semibold"><?= htmlspecialchars(money($c['total_spent'])) ?></td>
                </tr>
              <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="col-lg-6">
        <div class="card h-100">
          <div class="card-header bg-white"><h2 class="h5 mb-0">Phân bố trạng thái đơn</h2></div>
          <div class="table-responsive">
            <table class="table mb-0 align-middle">
              <thead class="table-light">
                <tr><th>Trạng thái</th><th class="text-center">Số đơn</th><th class="text-end">Tổng tiền</th></tr>
              </thead>
              <tbody>
              <?php if (empty($status_breakdown)): ?>
                <tr><td colspan="3" class="text-center text-muted py-4">Chưa có dữ liệu.</td></tr>
              <?php else: foreach ($status_breakdown as $row): ?>
                <tr>
                  <td><?= htmlspecialchars($row['status']) ?></td>
                  <td class="text-center"><?= (int)$row['total'] ?></td>
                  <td class="text-end fw-semibold"><?= htmlspecialchars(money($row['amount'])) ?></td>
                </tr>
              <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="./assets/js/main.js"></script>
</body>
</html>

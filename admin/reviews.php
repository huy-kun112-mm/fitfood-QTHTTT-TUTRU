<?php
/**
 * FitFood — Admin / Bình luận sản phẩm
 * URL: http://localhost:8080/admin/reviews.php
 *
 * Liệt kê toàn bộ review, filter theo sản phẩm + email user.
 * Admin có quyền xóa bất kỳ review nào.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/reviews.php';
require_once __DIR__ . '/partials/auth_guard.php';

$active_page = 'reviews';

// ---------- POST: xóa review ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $del_id = (int)($_POST['id'] ?? 0);
    if ($del_id <= 0) {
        $_SESSION['flash_error'] = 'Tham số không hợp lệ.';
    } elseif ($pdo === null) {
        $_SESSION['flash_error'] = 'Không kết nối được database.';
    } else {
        try {
            $deleted = delete_review($pdo, $del_id, null);
            if ($deleted) {
                $_SESSION['flash_success'] = 'Đã xóa bình luận.';
            } else {
                $_SESSION['flash_error'] = 'Không tìm thấy bình luận để xóa.';
            }
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = 'Lỗi: ' . $e->getMessage();
            error_log('[admin/reviews delete] ' . $e->getMessage());
        }
    }
    $qs = http_build_query($_GET);
    header('Location: reviews.php' . ($qs !== '' ? '?' . $qs : ''));
    exit;
}

// ---------- Inputs ----------
$product_id_filter = (int)($_GET['product'] ?? 0);
$email_filter      = trim((string)($_GET['email'] ?? ''));

$flash_success = $_SESSION['flash_success'] ?? null;
$flash_error   = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// ---------- Queries ----------
$reviews     = [];
$products    = [];
$query_error = null;

if ($pdo === null) {
    $query_error = 'Không kết nối được database.';
} else {
    try {
        $products = $pdo->query(
            "SELECT id, name FROM products WHERE is_active = 1 ORDER BY name ASC"
        )->fetchAll();

        $where = [];
        $params = [];
        if ($product_id_filter > 0) {
            $where[] = 'r.product_id = :pid';
            $params[':pid'] = $product_id_filter;
        }
        if ($email_filter !== '') {
            $where[] = 'u.email LIKE :email';
            $params[':email'] = '%' . $email_filter . '%';
        }
        $where_sql = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);

        $sql = "SELECT r.id, r.content, r.created_at, r.updated_at,
                       p.id AS product_id, p.name AS product_name, p.slug AS product_slug,
                       u.id AS user_id, u.full_name, u.email
                  FROM product_reviews r
                  JOIN products p ON p.id = r.product_id
                  JOIN users    u ON u.id = r.user_id
                  $where_sql
              ORDER BY r.created_at DESC
                 LIMIT 200";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $reviews = $stmt->fetchAll();
    } catch (PDOException $e) {
        $query_error = 'Lỗi truy vấn: ' . $e->getMessage();
        error_log('[admin/reviews] ' . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>Bình luận - FitFood Admin</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="apple-touch-icon" sizes="180x180" href="./assets/images/favicon_io/apple-touch-icon.png">
  <link rel="icon" type="image/png" sizes="32x32" href="./assets/images/favicon_io/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="./assets/images/favicon_io/favicon-16x16.png">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.35.0/dist/tabler-icons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="./assets/css/style.css">
</head>
<body>

<div id="overlay" class="overlay"></div>

<nav id="topbar" class="navbar bg-white border-bottom fixed-top topbar px-3">
  <button id="toggleBtn" class="d-none d-lg-inline-flex btn btn-light btn-icon btn-sm">
    <i class="ti ti-layout-sidebar-left-expand"></i>
  </button>
  <button id="mobileBtn" class="btn btn-light btn-icon btn-sm d-lg-none me-2">
    <i class="ti ti-layout-sidebar-left-expand"></i>
  </button>
  <div class="ms-auto">
    <a href="../logout.php" class="btn btn-light btn-sm">
      <i class="ti ti-logout me-1"></i>Đăng xuất
    </a>
  </div>
</nav>

<?php include __DIR__ . '/partials/sidebar.php'; ?>

<main id="content" class="content py-10">
  <div class="container-fluid pt-5">

    <div class="row mt-4">
      <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
          <div>
            <h1 class="fs-3 mb-1">Bình luận sản phẩm</h1>
            <p class="text-muted mb-0">
              Tổng: <strong><?= count($reviews) ?></strong> bình luận
            </p>
          </div>
        </div>
      </div>
    </div>

    <?php if ($flash_success): ?>
      <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="ti ti-circle-check me-2"></i>
        <?= htmlspecialchars($flash_success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>
    <?php if ($flash_error): ?>
      <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="ti ti-alert-triangle me-2"></i>
        <?= htmlspecialchars($flash_error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>
    <?php if ($query_error): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($query_error) ?></div>
    <?php endif; ?>

    <form method="GET" class="card p-3 mb-3">
      <div class="row g-2 align-items-center">
        <div class="col-md-5">
          <select name="product" class="form-select">
            <option value="0">— Tất cả sản phẩm —</option>
            <?php foreach ($products as $p): ?>
              <option value="<?= (int)$p['id'] ?>"
                      <?= $product_id_filter === (int)$p['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($p['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <div class="input-group">
            <span class="input-group-text bg-white"><i class="ti ti-mail"></i></span>
            <input type="text" name="email" class="form-control"
                   placeholder="Email user…"
                   value="<?= htmlspecialchars($email_filter) ?>">
          </div>
        </div>
        <div class="col-md-3 d-flex gap-2">
          <button type="submit" class="btn btn-primary">Lọc</button>
          <a href="reviews.php" class="btn btn-light">Xóa lọc</a>
        </div>
      </div>
    </form>

    <div class="card">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="width:60px">ID</th>
              <th>Sản phẩm</th>
              <th>Người bình luận</th>
              <th>Nội dung</th>
              <th style="width:140px">Ngày</th>
              <th style="width:100px">Hành động</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($reviews)): ?>
              <tr><td colspan="6" class="text-center text-muted py-4">Chưa có bình luận nào.</td></tr>
            <?php else: foreach ($reviews as $r):
                $excerpt = mb_substr($r['content'], 0, 80, 'UTF-8');
                if (mb_strlen($r['content'], 'UTF-8') > 80) $excerpt .= '…';
            ?>
              <tr>
                <td>#<?= (int)$r['id'] ?></td>
                <td>
                  <a href="../detail-product.php?slug=<?= urlencode($r['product_slug']) ?>"
                     target="_blank">
                    <?= htmlspecialchars($r['product_name']) ?>
                  </a>
                </td>
                <td>
                  <div><?= htmlspecialchars($r['full_name']) ?></div>
                  <small class="text-muted"><?= htmlspecialchars($r['email']) ?></small>
                </td>
                <td title="<?= htmlspecialchars($r['content']) ?>">
                  <?= htmlspecialchars($excerpt) ?>
                </td>
                <td>
                  <small><?= htmlspecialchars(date('d/m/Y H:i', strtotime($r['created_at']))) ?></small>
                </td>
                <td>
                  <form method="POST" style="display:inline"
                        onsubmit="return confirm('Xóa bình luận #<?= (int)$r['id'] ?>?');">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger">
                      <i class="ti ti-trash"></i>
                    </button>
                  </form>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="./assets/js/main.js"></script>
</body>
</html>

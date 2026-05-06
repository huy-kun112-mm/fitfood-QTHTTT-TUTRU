<?php
/**
 * FitFood — Admin / Sản phẩm (Inventory)
 * URL: http://localhost:8080/admin/inventory.php
 *
 * Hỗ trợ: search theo name/sku, filter theo category + type, pagination 10/trang.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/partials/auth_guard.php';

// ---------- POST: Soft delete (ẩn sản phẩm — set is_active = 0) ----------
// Pattern PRG: nhận POST → UPDATE → redirect lại inventory.php giữ nguyên filter.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $del_id = (int)($_POST['id'] ?? 0);

    if ($del_id <= 0) {
        $_SESSION['flash_error'] = 'Tham số xoá không hợp lệ.';
    } elseif ($pdo === null) {
        $_SESSION['flash_error'] = 'Không kết nối được database.';
    } else {
        try {
            $name_stmt = $pdo->prepare("SELECT name, is_active FROM products WHERE id = ?");
            $name_stmt->execute([$del_id]);
            $row = $name_stmt->fetch();

            if (!$row) {
                $_SESSION['flash_error'] = 'Không tìm thấy sản phẩm cần xoá.';
            } elseif ((int)$row['is_active'] === 0) {
                $_SESSION['flash_error'] = 'Sản phẩm "' . $row['name'] . '" đã ở trạng thái Tạm ngừng.';
            } else {
                $upd = $pdo->prepare("UPDATE products SET is_active = 0 WHERE id = ?");
                $upd->execute([$del_id]);
                $_SESSION['flash_success'] = 'Đã xoá sản phẩm "' . $row['name'] . '" (chuyển sang Tạm ngừng). Lịch sử đơn hàng vẫn được giữ.';
            }
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = 'Lỗi khi xoá sản phẩm: ' . $e->getMessage();
            error_log('[admin/inventory delete] ' . $e->getMessage());
        }
    }

    $qs_redirect = http_build_query($_GET);
    header('Location: inventory.php' . ($qs_redirect !== '' ? '?' . $qs_redirect : ''));
    exit;
}

// ---------- Helpers ----------
function vnd($n): string {
    return number_format((float)$n, 0, ',', '.') . '₫';
}

function effective_price(array $p): float {
    return $p['sale_price'] !== null ? (float)$p['sale_price'] : (float)$p['price'];
}

// ---------- Inputs ----------
$q       = trim((string)($_GET['q']    ?? ''));
$cat     = trim((string)($_GET['cat']  ?? ''));
$type    = trim((string)($_GET['type'] ?? ''));
$page    = max(1, (int)($_GET['page']  ?? 1));
$per_page = 10;
$offset  = ($page - 1) * $per_page;

// Whitelist filter values để tránh SQL injection (dù đã prepared)
if (!in_array($type, ['', 'package', 'product'], true)) $type = '';
if ($cat !== '' && !ctype_digit($cat)) $cat = '';

// ---------- Queries ----------
$products      = [];
$total_rows    = 0;
$categories    = [];
$query_error   = null;

if ($pdo === null) {
    $query_error = 'Không thể kết nối database. Kiểm tra docker-compose hoặc config/database.php.';
} else {
    try {
        // Categories cho dropdown filter
        $categories = $pdo->query(
            "SELECT id, name FROM categories WHERE is_active = 1 ORDER BY sort_order ASC, name ASC"
        )->fetchAll();

        // Build WHERE clause động
        $where  = [];
        $params = [];

        if ($q !== '') {
            $where[] = '(p.name LIKE :q OR p.sku LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }
        if ($cat !== '') {
            $where[] = 'p.category_id = :cat';
            $params[':cat'] = (int)$cat;
        }
        if ($type !== '') {
            $where[] = 'p.type = :type';
            $params[':type'] = $type;
        }

        $where_sql = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);

                // Nếu yêu cầu JSON refresh tồn kho / đã bán realtime
        if (isset($_GET['action']) && $_GET['action'] === 'refresh_stats') {
            $refresh_sql = "
                SELECT p.id, p.stock, p.sold_count
                FROM products p
                $where_sql
                ORDER BY p.id DESC
                LIMIT $per_page OFFSET $offset
            ";
            $refresh_stmt = $pdo->prepare($refresh_sql);
            $refresh_stmt->execute($params);
            $refresh_rows = $refresh_stmt->fetchAll(PDO::FETCH_ASSOC);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'status' => 'ok',
                'products' => $refresh_rows,
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        // Count total cho pagination
        $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM products p $where_sql");
        $count_stmt->execute($params);
        $total_rows = (int)$count_stmt->fetchColumn();

        // SELECT với LIMIT (inline integers, đã được sanitize từ (int) cast)
        $sql = "
            SELECT p.id, p.sku, p.name, p.image_url, p.type,
                   p.price, p.sale_price, p.unit, p.stock,
                   p.sold_count, p.is_active,
                   c.name AS category_name
            FROM products p
            LEFT JOIN categories c ON c.id = p.category_id
            $where_sql
            ORDER BY p.id DESC
            LIMIT $per_page OFFSET $offset
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $products = $stmt->fetchAll();
    } catch (PDOException $e) {
        $query_error = 'Lỗi truy vấn DB: ' . $e->getMessage();
        error_log('[admin/inventory] ' . $e->getMessage());
    }
}

$total_pages = max(1, (int)ceil($total_rows / $per_page));
$placeholder_img = './assets/images/logo-icon.svg';
$active_page     = 'inventory';

// One-shot flash message từ create-product.php / edit-product.php / delete handler
$flash_success = $_SESSION['flash_success'] ?? null;
$flash_error   = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Hàm build URL giữ filter, đổi page
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
  <title>Sản phẩm - FitFood Admin</title>
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
            <h1 class="fs-3 mb-1">Sản phẩm</h1>
            <p class="text-muted mb-0">Quản lý danh sách sản phẩm và tồn kho.</p>
          </div>
          <div>
            <a href="create-product.php" class="btn btn-primary">
              <i class="ti ti-plus me-1"></i>Thêm sản phẩm
            </a>
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

    <?php if ($flash_error): ?>
      <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="ti ti-alert-triangle me-2"></i>
        <?= htmlspecialchars($flash_error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php endif; ?>

    <?php if ($query_error): ?>
      <div class="alert alert-danger" role="alert">
        <i class="ti ti-alert-triangle me-2"></i>
        <?= htmlspecialchars($query_error, ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php endif; ?>

    <!-- TOOLBAR: search + filter -->
    <form method="GET" class="card p-3 mb-3">
      <div class="row g-2 align-items-center">
        <div class="col-md-4">
          <div class="input-group">
            <span class="input-group-text bg-white"><i class="ti ti-search"></i></span>
            <input type="text" name="q" class="form-control" placeholder="Tìm theo tên hoặc SKU…"
                   value="<?= htmlspecialchars($q) ?>">
          </div>
        </div>
        <div class="col-md-3">
          <select name="cat" class="form-select">
            <option value="">Tất cả danh mục</option>
            <?php foreach ($categories as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= $cat === (string)$c['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($c['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <select name="type" class="form-select">
            <option value="">Tất cả loại</option>
            <option value="package" <?= $type === 'package' ? 'selected' : '' ?>>Gói ăn</option>
            <option value="product" <?= $type === 'product' ? 'selected' : '' ?>>Sản phẩm lẻ</option>
          </select>
        </div>
        <div class="col-md-2 d-flex gap-2">
          <button class="btn btn-primary flex-grow-1" type="submit">
            <i class="ti ti-filter me-1"></i>Lọc
          </button>
          <?php if ($q !== '' || $cat !== '' || $type !== ''): ?>
            <a href="inventory.php" class="btn btn-outline-secondary" title="Xoá bộ lọc">
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
            <th>Sản phẩm</th>
            <th>SKU</th>
            <th>Danh mục</th>
            <th>Loại</th>
            <th class="text-end">Giá</th>
            <th>Đơn vị</th>
            <th class="text-center">Tồn kho</th>
            <th class="text-center">Đã bán</th>
            <th class="text-center">Trạng thái</th>
            <th class="text-center">Hành động</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($products)): ?>
            <tr>
              <td colspan="10" class="text-center text-muted py-4">
                Không tìm thấy sản phẩm phù hợp.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($products as $p):
              $img         = $p['image_url'] ?: $placeholder_img;
              $price       = effective_price($p);
              $has_sale    = $p['sale_price'] !== null;
              $stock       = (int)$p['stock'];
              $stock_class = $stock <= 5 ? 'text-danger fw-semibold'
                           : ($stock <= 15 ? 'text-warning fw-semibold' : '');
              $type_label  = $p['type'] === 'package' ? 'Gói ăn' : 'Sản phẩm lẻ';
              $type_color  = $p['type'] === 'package' ? 'primary' : 'secondary';
              $is_active   = (int)$p['is_active'] === 1;
            ?>
             <tr data-product-id="<?= (int)$p['id'] ?>">
                <td>
                  <a href="#" class="d-inline-flex align-items-center text-body text-decoration-none">
                    <img src="<?= htmlspecialchars($img) ?>"
                         alt="" class="avatar avatar-md rounded" style="object-fit:cover;">
                    <span class="ms-3"><?= htmlspecialchars($p['name']) ?></span>
                  </a>
                </td>
                <td><code class="text-body"><?= htmlspecialchars($p['sku'] ?? '—') ?></code></td>
                <td><?= htmlspecialchars($p['category_name'] ?? '—') ?></td>
                <td>
                  <span class="badge bg-<?= $type_color ?>-subtle text-<?= $type_color ?>">
                    <?= htmlspecialchars($type_label) ?>
                  </span>
                </td>
                <td class="text-end">
                  <?php if ($has_sale): ?>
                    <div class="fw-semibold"><?= htmlspecialchars(vnd($price)) ?></div>
                    <small class="text-muted text-decoration-line-through">
                      <?= htmlspecialchars(vnd($p['price'])) ?>
                    </small>
                  <?php else: ?>
                    <span class="fw-semibold"><?= htmlspecialchars(vnd($price)) ?></span>
                  <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($p['unit'] ?? '—') ?></td>
                <td class="text-center stock-cell <?= $stock_class ?>"><?= $stock ?></td>
                <td class="text-center sold-cell"><?= number_format((int)$p['sold_count']) ?></td>
                <td class="text-center <?= $stock_class ?>"><?= $stock ?></td>
                <td class="text-center"><?= number_format((int)$p['sold_count']) ?></td>
                <td class="text-center">
                  <?php if ($is_active): ?>
                    <span class="badge bg-success-subtle text-success">Hoạt động</span>
                  <?php else: ?>
                    <span class="badge bg-secondary-subtle text-secondary">Tạm ngừng</span>
                  <?php endif; ?>
                </td>
                <td class="text-center">
                  <a href="edit-product.php?id=<?= (int)$p['id'] ?>" title="Sửa">
                    <i class="ti ti-edit"></i>
                  </a>
                  <button type="button" class="btn btn-link link-danger p-0 ms-2 align-baseline"
                          title="Xoá"
                          data-bs-toggle="modal" data-bs-target="#deleteModal"
                          data-id="<?= (int)$p['id'] ?>"
                          data-name="<?= htmlspecialchars($p['name'], ENT_QUOTES) ?>"
                          data-sku="<?= htmlspecialchars($p['sku'] ?? '', ENT_QUOTES) ?>"
                          data-img="<?= htmlspecialchars($img, ENT_QUOTES) ?>">
                    <i class="ti ti-trash"></i>
                  </button>
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
          / <strong><?= $total_rows ?></strong> sản phẩm
        </div>

        <?php if ($total_pages > 1): ?>
          <nav aria-label="Phân trang">
            <ul class="pagination mb-0">
              <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= $page <= 1 ? '#' : page_url($page - 1) ?>">
                  Previous
                </a>
              </li>
              <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                  <a class="page-link" href="<?= page_url($i) ?>"><?= $i ?></a>
                </li>
              <?php endfor; ?>
              <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= $page >= $total_pages ? '#' : page_url($page + 1) ?>">
                  Next
                </a>
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

<!-- MODAL: xác nhận xoá sản phẩm (soft delete → set is_active = 0) -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" action="">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="delete_product_id" value="">

        <div class="modal-header">
          <h5 class="modal-title" id="deleteModalLabel">
            <i class="ti ti-alert-triangle text-warning me-2"></i>Xác nhận xoá sản phẩm
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">
          <div class="d-flex align-items-center mb-3 p-3 bg-light rounded">
            <img id="delete_product_img" src="<?= htmlspecialchars($placeholder_img) ?>"
                 alt="" class="avatar avatar-md rounded me-3" style="object-fit:cover;">
            <div class="flex-grow-1">
              <div class="fw-semibold" id="delete_product_name">—</div>
              <small class="text-muted">SKU: <code id="delete_product_sku">—</code></small>
            </div>
          </div>
          <p class="mb-0 small">
            Sản phẩm sẽ chuyển sang trạng thái <span class="badge bg-secondary-subtle text-secondary">Tạm ngừng</span>
            và không hiển thị trên cửa hàng. Lịch sử đơn hàng vẫn được giữ nguyên.
            Bạn có thể khôi phục sau bằng cách chỉnh sửa và bật lại "Hoạt động".
          </p>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Huỷ</button>
          <button type="submit" class="btn btn-danger">
            <i class="ti ti-trash me-1"></i>Xoá
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="./assets/js/main.js"></script>
<script>
  // Populate modal xoá với data-* attributes từ button vừa click
  (function () {
    const modal = document.getElementById('deleteModal');
    if (!modal) return;
    const placeholder = <?= json_encode($placeholder_img) ?>;

    modal.addEventListener('show.bs.modal', function (event) {
      const btn = event.relatedTarget;
      if (!btn) return;
      document.getElementById('delete_product_id').value         = btn.dataset.id   || '';
      document.getElementById('delete_product_name').textContent = btn.dataset.name || '—';
      document.getElementById('delete_product_sku').textContent  = btn.dataset.sku  || '—';
      const img = document.getElementById('delete_product_img');
      img.src = btn.dataset.img || placeholder;
      img.onerror = () => { img.src = placeholder; };
    });
          

    if (document.querySelector('table')) {
      refreshInventoryStats();
      setInterval(refreshInventoryStats, REFRESH_INTERVAL_MS);
    }
  })();
</script>
</body>
</html>

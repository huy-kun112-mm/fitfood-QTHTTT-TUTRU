<?php
/**
 * FitFood — Admin / Thu chi (Finance)
 * URL: http://localhost:8080/admin/finance.php
 *
 * Tabs:
 *   ?tab=purchase  — nhập nguyên vật liệu (Total Purchase)
 *   ?tab=expenses  — chi phí vận hành    (Total Expenses)
 *
 * POST actions: create_purchase | create_expense | delete_purchase | delete_expense
 * Soft delete: set is_deleted = 1 (không xoá hẳn — vẫn audit được).
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/partials/auth_guard.php';

// ---------- Helpers ----------
function vnd($n): string {
    return number_format((float)$n, 0, ',', '.') . '₫';
}

function fmt_qty($n): string {
    // Bỏ trailing zeros: "2.500" → "2.5", "10.000" → "10"
    return rtrim(rtrim(number_format((float)$n, 3, '.', ''), '0'), '.');
}

function fmt_date(?string $d): string {
    if (!$d) return '—';
    $ts = strtotime($d);
    return $ts ? date('d/m/Y', $ts) : '—';
}

/** Validate format YYYY-MM-DD và date thật sự tồn tại */
function is_valid_date(string $d): bool {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) return false;
    [$y, $m, $day] = array_map('intval', explode('-', $d));
    return checkdate($m, $day, $y);
}

// ---------- Inputs (giữ qua redirect) ----------
$tab      = $_GET['tab']  ?? $_POST['tab'] ?? 'purchase';
if (!in_array($tab, ['purchase', 'expenses'], true)) $tab = 'purchase';
$q        = trim((string)($_GET['q']   ?? ''));
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;
$offset   = ($page - 1) * $per_page;

/** Build URL giữ filter, đổi page hoặc tab */
function build_url(array $overrides = []): string {
    $params = array_merge($_GET, $overrides);
    return 'finance.php?' . http_build_query($params);
}

// ---------- POST handlers ----------
$form_errors  = [];   // lỗi validation form create
$form_old     = [];   // dữ liệu form khi validation fail (giữ lại để re-render)

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($pdo === null) {
        $_SESSION['flash_error'] = 'Không kết nối được database.';
        header('Location: finance.php?tab=' . $tab);
        exit;
    }

    // ---- Soft delete: purchases ----
    if ($action === 'delete_purchase') {
        $del_id = (int)($_POST['id'] ?? 0);
        if ($del_id <= 0) {
            $_SESSION['flash_error'] = 'Tham số xoá không hợp lệ.';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT material_name, is_deleted FROM purchases WHERE id = ?");
                $stmt->execute([$del_id]);
                $row = $stmt->fetch();
                if (!$row) {
                    $_SESSION['flash_error'] = 'Không tìm thấy phiếu nhập cần xoá.';
                } elseif ((int)$row['is_deleted'] === 1) {
                    $_SESSION['flash_error'] = 'Phiếu nhập này đã bị xoá trước đó.';
                } else {
                    $upd = $pdo->prepare("UPDATE purchases SET is_deleted = 1 WHERE id = ?");
                    $upd->execute([$del_id]);
                    $_SESSION['flash_success'] = 'Đã xoá phiếu nhập "' . $row['material_name'] . '" (soft delete — dữ liệu vẫn được giữ).';
                }
            } catch (PDOException $e) {
                $_SESSION['flash_error'] = 'Lỗi khi xoá: ' . $e->getMessage();
                error_log('[admin/finance delete_purchase] ' . $e->getMessage());
            }
        }
        header('Location: ' . build_url(['tab' => 'purchase']));
        exit;
    }

    // ---- Soft delete: expenses ----
    if ($action === 'delete_expense') {
        $del_id = (int)($_POST['id'] ?? 0);
        if ($del_id <= 0) {
            $_SESSION['flash_error'] = 'Tham số xoá không hợp lệ.';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT bill_name, is_deleted FROM expenses WHERE id = ?");
                $stmt->execute([$del_id]);
                $row = $stmt->fetch();
                if (!$row) {
                    $_SESSION['flash_error'] = 'Không tìm thấy hoá đơn cần xoá.';
                } elseif ((int)$row['is_deleted'] === 1) {
                    $_SESSION['flash_error'] = 'Hoá đơn này đã bị xoá trước đó.';
                } else {
                    $upd = $pdo->prepare("UPDATE expenses SET is_deleted = 1 WHERE id = ?");
                    $upd->execute([$del_id]);
                    $_SESSION['flash_success'] = 'Đã xoá hoá đơn "' . $row['bill_name'] . '" (soft delete — dữ liệu vẫn được giữ).';
                }
            } catch (PDOException $e) {
                $_SESSION['flash_error'] = 'Lỗi khi xoá: ' . $e->getMessage();
                error_log('[admin/finance delete_expense] ' . $e->getMessage());
            }
        }
        header('Location: ' . build_url(['tab' => 'expenses']));
        exit;
    }

    // ---- Create: purchase ----
    if ($action === 'create_purchase') {
        $tab = 'purchase';
        $form_old = [
            'material_name' => trim((string)($_POST['material_name'] ?? '')),
            'quantity'      => trim((string)($_POST['quantity']      ?? '')),
            'unit_price'    => trim((string)($_POST['unit_price']    ?? '')),
            'expiry_date'   => trim((string)($_POST['expiry_date']   ?? '')),
            'import_date'   => trim((string)($_POST['import_date']   ?? '')),
        ];

        if ($form_old['material_name'] === '') {
            $form_errors['material_name'] = 'Tên nguyên vật liệu không được để trống.';
        } elseif (mb_strlen($form_old['material_name']) > 255) {
            $form_errors['material_name'] = 'Tên tối đa 255 ký tự.';
        }

        if ($form_old['quantity'] === '' || !is_numeric($form_old['quantity']) || (float)$form_old['quantity'] <= 0) {
            $form_errors['quantity'] = 'Số lượng phải là số dương.';
        }

        if ($form_old['unit_price'] === '' || !is_numeric($form_old['unit_price']) || (float)$form_old['unit_price'] < 0) {
            $form_errors['unit_price'] = 'Đơn giá phải là số không âm.';
        }

        if ($form_old['import_date'] === '' || !is_valid_date($form_old['import_date'])) {
            $form_errors['import_date'] = 'Ngày nhập không hợp lệ (YYYY-MM-DD).';
        }

        if ($form_old['expiry_date'] !== '' && !is_valid_date($form_old['expiry_date'])) {
            $form_errors['expiry_date'] = 'Hạn sử dụng không hợp lệ.';
        }

        if (empty($form_errors)) {
            try {
                $stmt = $pdo->prepare(
                    "INSERT INTO purchases (material_name, quantity, unit_price, expiry_date, import_date)
                     VALUES (:material_name, :quantity, :unit_price, :expiry_date, :import_date)"
                );
                $stmt->execute([
                    ':material_name' => $form_old['material_name'],
                    ':quantity'      => (float)$form_old['quantity'],
                    ':unit_price'    => (float)$form_old['unit_price'],
                    ':expiry_date'   => $form_old['expiry_date'] !== '' ? $form_old['expiry_date'] : null,
                    ':import_date'   => $form_old['import_date'],
                ]);
                $_SESSION['flash_success'] = 'Đã thêm phiếu nhập "' . $form_old['material_name'] . '" thành công.';
                header('Location: ' . build_url(['tab' => 'purchase', 'page' => 1]));
                exit;
            } catch (PDOException $e) {
                $form_errors['_db'] = 'Lỗi lưu dữ liệu: ' . $e->getMessage();
                error_log('[admin/finance create_purchase] ' . $e->getMessage());
            }
        }
    }

    // ---- Create: expense ----
    if ($action === 'create_expense') {
        $tab = 'expenses';
        $form_old = [
            'bill_name'     => trim((string)($_POST['bill_name']     ?? '')),
            'amount'        => trim((string)($_POST['amount']        ?? '')),
            'bill_code'     => trim((string)($_POST['bill_code']     ?? '')),
            'received_date' => trim((string)($_POST['received_date'] ?? '')),
            'due_date'      => trim((string)($_POST['due_date']      ?? '')),
        ];

        if ($form_old['bill_name'] === '') {
            $form_errors['bill_name'] = 'Tên hoá đơn không được để trống.';
        } elseif (mb_strlen($form_old['bill_name']) > 255) {
            $form_errors['bill_name'] = 'Tên hoá đơn tối đa 255 ký tự.';
        }

        if ($form_old['amount'] === '' || !is_numeric($form_old['amount']) || (float)$form_old['amount'] < 0) {
            $form_errors['amount'] = 'Số tiền phải là số không âm.';
        }

        if ($form_old['bill_code'] !== '' && mb_strlen($form_old['bill_code']) > 100) {
            $form_errors['bill_code'] = 'Mã hoá đơn tối đa 100 ký tự.';
        }

        if ($form_old['received_date'] === '' || !is_valid_date($form_old['received_date'])) {
            $form_errors['received_date'] = 'Ngày nhận không hợp lệ.';
        }

        if ($form_old['due_date'] !== '' && !is_valid_date($form_old['due_date'])) {
            $form_errors['due_date'] = 'Ngày đến hạn không hợp lệ.';
        }

        if (empty($form_errors)) {
            try {
                $stmt = $pdo->prepare(
                    "INSERT INTO expenses (bill_name, amount, bill_code, received_date, due_date)
                     VALUES (:bill_name, :amount, :bill_code, :received_date, :due_date)"
                );
                $stmt->execute([
                    ':bill_name'     => $form_old['bill_name'],
                    ':amount'        => (float)$form_old['amount'],
                    ':bill_code'     => $form_old['bill_code'] !== '' ? $form_old['bill_code'] : null,
                    ':received_date' => $form_old['received_date'],
                    ':due_date'      => $form_old['due_date'] !== '' ? $form_old['due_date'] : null,
                ]);
                $_SESSION['flash_success'] = 'Đã thêm hoá đơn "' . $form_old['bill_name'] . '" thành công.';
                header('Location: ' . build_url(['tab' => 'expenses', 'page' => 1]));
                exit;
            } catch (PDOException $e) {
                $form_errors['_db'] = 'Lỗi lưu dữ liệu: ' . $e->getMessage();
                error_log('[admin/finance create_expense] ' . $e->getMessage());
            }
        }
    }
}

// ---------- Queries cho list hiện tại ----------
$rows         = [];
$total_rows   = 0;
$summary_total = 0.0;
$query_error  = null;

if ($pdo === null) {
    $query_error = 'Không thể kết nối database. Kiểm tra docker-compose hoặc config/database.php.';
} else {
    try {
        if ($tab === 'purchase') {
            $where  = ['is_deleted = 0'];
            $params = [];
            if ($q !== '') {
                $where[] = 'material_name LIKE :q';
                $params[':q'] = '%' . $q . '%';
            }
            $where_sql = 'WHERE ' . implode(' AND ', $where);

            $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM purchases $where_sql");
            $count_stmt->execute($params);
            $total_rows = (int)$count_stmt->fetchColumn();

            $sum_stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM purchases $where_sql");
            $sum_stmt->execute($params);
            $summary_total = (float)$sum_stmt->fetchColumn();

            $sql = "SELECT id, material_name, quantity, unit_price, total_amount, expiry_date, import_date, created_at
                    FROM purchases
                    $where_sql
                    ORDER BY import_date DESC, id DESC
                    LIMIT $per_page OFFSET $offset";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll();
        } else { // expenses
            $where  = ['is_deleted = 0'];
            $params = [];
            if ($q !== '') {
                $where[] = '(bill_name LIKE :q OR bill_code LIKE :q)';
                $params[':q'] = '%' . $q . '%';
            }
            $where_sql = 'WHERE ' . implode(' AND ', $where);

            $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM expenses $where_sql");
            $count_stmt->execute($params);
            $total_rows = (int)$count_stmt->fetchColumn();

            $sum_stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses $where_sql");
            $sum_stmt->execute($params);
            $summary_total = (float)$sum_stmt->fetchColumn();

            $sql = "SELECT id, bill_name, amount, bill_code, received_date, due_date, created_at
                    FROM expenses
                    $where_sql
                    ORDER BY received_date DESC, id DESC
                    LIMIT $per_page OFFSET $offset";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll();
        }
    } catch (PDOException $e) {
        $query_error = 'Lỗi truy vấn DB: ' . $e->getMessage();
        error_log('[admin/finance] ' . $e->getMessage());
    }
}

$total_pages = max(1, (int)ceil($total_rows / $per_page));
$active_page = 'finance';

$flash_success = $_SESSION['flash_success'] ?? null;
$flash_error   = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

/** Helper render error nhỏ phía dưới input (đồng bộ với edit-product.php) */
function err(string $field, array $errors): string {
    if (empty($errors[$field])) return '';
    return '<div class="invalid-feedback d-block">' . htmlspecialchars($errors[$field]) . '</div>';
}
function inv(string $field, array $errors): string {
    return empty($errors[$field]) ? '' : 'is-invalid';
}

/** Lấy giá trị form_old cho field, hoặc default rỗng */
function old_val(string $field, array $form_old): string {
    return htmlspecialchars((string)($form_old[$field] ?? ''));
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8" />
  <title>Thu chi - FitFood Admin</title>
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

<?php include __DIR__ . '/partials/sidebar.php'; ?>

<main id="content" class="content py-10">
  <div class="container-fluid pt-5">

    <div class="row mt-4">
      <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
          <div>
            <h1 class="fs-3 mb-1">Quản lý Thu chi</h1>
            <p class="text-muted mb-0">Nhập và theo dõi chi phí nguyên vật liệu (Purchase) và chi phí vận hành (Expenses).</p>
          </div>
          <div class="text-end">
            <small class="text-muted d-block">Tổng (<?= $tab === 'purchase' ? 'Purchase' : 'Expenses' ?>)</small>
            <h3 class="mb-0 fw-bold text-<?= $tab === 'purchase' ? 'success' : 'info' ?>">
              <?= htmlspecialchars(vnd($summary_total)) ?>
            </h3>
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
        <?= htmlspecialchars($query_error) ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($form_errors['_db'])): ?>
      <div class="alert alert-danger" role="alert">
        <i class="ti ti-alert-triangle me-2"></i>
        <?= htmlspecialchars($form_errors['_db']) ?>
      </div>
    <?php endif; ?>

    <!-- TABS -->
    <ul class="nav nav-tabs mb-3" role="tablist">
      <li class="nav-item">
        <a class="nav-link <?= $tab === 'purchase' ? 'active' : '' ?>"
           href="<?= htmlspecialchars(build_url(['tab' => 'purchase', 'q' => '', 'page' => 1])) ?>">
          <i class="ti ti-shopping-cart me-1"></i>Total Purchase
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= $tab === 'expenses' ? 'active' : '' ?>"
           href="<?= htmlspecialchars(build_url(['tab' => 'expenses', 'q' => '', 'page' => 1])) ?>">
          <i class="ti ti-receipt-2 me-1"></i>Total Expenses
        </a>
      </li>
    </ul>

    <?php if ($tab === 'purchase'): ?>
      <!-- ============================================ -->
      <!--   FORM: NHẬP NGUYÊN VẬT LIỆU                 -->
      <!-- ============================================ -->
      <div class="card mb-3">
        <div class="card-header bg-white px-4 py-3">
          <h5 class="mb-0"><i class="ti ti-plus me-2"></i>Thêm phiếu nhập nguyên vật liệu</h5>
        </div>
        <div class="card-body p-4">
          <form method="POST" action="finance.php?tab=purchase" novalidate>
            <input type="hidden" name="action" value="create_purchase">
            <input type="hidden" name="tab"    value="purchase">

            <div class="row">
              <div class="col-md-6 mb-3">
                <label for="material_name" class="form-label">Tên nguyên vật liệu <span class="text-danger">*</span></label>
                <input type="text" name="material_name" id="material_name"
                       class="form-control <?= inv('material_name', $form_errors) ?>"
                       value="<?= old_val('material_name', $form_old) ?>"
                       placeholder="Ví dụ: Ức gà, Gạo lứt, Dầu olive..." maxlength="255">
                <?= err('material_name', $form_errors) ?>
              </div>
              <div class="col-md-3 mb-3">
                <label for="quantity" class="form-label">Số lượng <span class="text-danger">*</span></label>
                <input type="number" name="quantity" id="quantity"
                       class="form-control <?= inv('quantity', $form_errors) ?>"
                       value="<?= old_val('quantity', $form_old) ?>"
                       min="0" step="0.001" placeholder="10">
                <?= err('quantity', $form_errors) ?>
                <div class="form-text">Có thể nhập số thập phân (vd: 2.5 kg).</div>
              </div>
              <div class="col-md-3 mb-3">
                <label for="unit_price" class="form-label">Giá thành (đơn giá VND) <span class="text-danger">*</span></label>
                <input type="number" name="unit_price" id="unit_price"
                       class="form-control <?= inv('unit_price', $form_errors) ?>"
                       value="<?= old_val('unit_price', $form_old) ?>"
                       min="0" step="1000" placeholder="120000">
                <?= err('unit_price', $form_errors) ?>
              </div>
            </div>

            <div class="row">
              <div class="col-md-4 mb-3">
                <label for="import_date" class="form-label">Ngày nhập <span class="text-danger">*</span></label>
                <input type="date" name="import_date" id="import_date"
                       class="form-control <?= inv('import_date', $form_errors) ?>"
                       value="<?= old_val('import_date', $form_old) ?: date('Y-m-d') ?>">
                <?= err('import_date', $form_errors) ?>
              </div>
              <div class="col-md-4 mb-3">
                <label for="expiry_date" class="form-label">Hạn sử dụng</label>
                <input type="date" name="expiry_date" id="expiry_date"
                       class="form-control <?= inv('expiry_date', $form_errors) ?>"
                       value="<?= old_val('expiry_date', $form_old) ?>">
                <?= err('expiry_date', $form_errors) ?>
                <div class="form-text">Bỏ trống nếu không có hạn rõ ràng.</div>
              </div>
              <div class="col-md-4 mb-3">
                <label class="form-label">Thành tiền (tự tính)</label>
                <div class="form-control bg-light d-flex align-items-center">
                  <strong id="purchaseTotal" class="text-success">—</strong>
                </div>
                <div class="form-text">= Số lượng × Giá thành.</div>
              </div>
            </div>

            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-success">
                <i class="ti ti-device-floppy me-1"></i>Lưu phiếu nhập
              </button>
              <button type="reset" class="btn btn-light">Reset</button>
            </div>
          </form>
        </div>
      </div>

      <!-- TOOLBAR: search -->
      <form method="GET" class="card p-3 mb-3">
        <input type="hidden" name="tab" value="purchase">
        <div class="row g-2 align-items-center">
          <div class="col-md-6">
            <div class="input-group">
              <span class="input-group-text bg-white"><i class="ti ti-search"></i></span>
              <input type="text" name="q" class="form-control"
                     placeholder="Tìm theo tên nguyên vật liệu…"
                     value="<?= htmlspecialchars($q) ?>">
            </div>
          </div>
          <div class="col-md-6 d-flex gap-2">
            <button class="btn btn-primary" type="submit">
              <i class="ti ti-filter me-1"></i>Tìm
            </button>
            <?php if ($q !== ''): ?>
              <a href="finance.php?tab=purchase" class="btn btn-outline-secondary">
                <i class="ti ti-x me-1"></i>Xoá lọc
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
              <th>#</th>
              <th>Tên nguyên vật liệu</th>
              <th class="text-end">Số lượng</th>
              <th class="text-end">Đơn giá</th>
              <th class="text-end">Thành tiền</th>
              <th>Ngày nhập</th>
              <th>Hạn sử dụng</th>
              <th class="text-center">Thao tác</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($rows)): ?>
              <tr><td colspan="8" class="text-center text-muted py-4">
                <?= $q !== '' ? 'Không tìm thấy kết quả phù hợp.' : 'Chưa có phiếu nhập nào. Hãy thêm ở form bên trên.' ?>
              </td></tr>
            <?php else: ?>
              <?php foreach ($rows as $r): ?>
                <tr>
                  <td><code class="text-body">#<?= str_pad((string)$r['id'], 4, '0', STR_PAD_LEFT) ?></code></td>
                  <td class="fw-semibold"><?= htmlspecialchars($r['material_name']) ?></td>
                  <td class="text-end"><?= htmlspecialchars(fmt_qty($r['quantity'])) ?></td>
                  <td class="text-end"><?= htmlspecialchars(vnd($r['unit_price'])) ?></td>
                  <td class="text-end fw-bold text-success"><?= htmlspecialchars(vnd($r['total_amount'])) ?></td>
                  <td><?= htmlspecialchars(fmt_date($r['import_date'])) ?></td>
                  <td><?= htmlspecialchars(fmt_date($r['expiry_date'])) ?></td>
                  <td class="text-center">
                    <a href="edit-purchase.php?id=<?= (int)$r['id'] ?>" title="Sửa">
                      <i class="ti ti-edit"></i>
                    </a>
                    <button type="button" class="btn btn-link link-danger p-0 ms-2 align-baseline"
                            title="Xoá"
                            data-bs-toggle="modal" data-bs-target="#deleteModal"
                            data-action="delete_purchase"
                            data-id="<?= (int)$r['id'] ?>"
                            data-name="<?= htmlspecialchars($r['material_name'], ENT_QUOTES) ?>">
                      <i class="ti ti-trash"></i>
                    </button>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

    <?php else: // tab === 'expenses' ?>

      <!-- ============================================ -->
      <!--   FORM: NHẬP HOÁ ĐƠN CHI PHÍ                 -->
      <!-- ============================================ -->
      <div class="card mb-3">
        <div class="card-header bg-white px-4 py-3">
          <h5 class="mb-0"><i class="ti ti-plus me-2"></i>Thêm hoá đơn chi phí vận hành</h5>
        </div>
        <div class="card-body p-4">
          <form method="POST" action="finance.php?tab=expenses" novalidate>
            <input type="hidden" name="action" value="create_expense">
            <input type="hidden" name="tab"    value="expenses">

            <div class="row">
              <div class="col-md-6 mb-3">
                <label for="bill_name" class="form-label">Tên hoá đơn <span class="text-danger">*</span></label>
                <input type="text" name="bill_name" id="bill_name"
                       class="form-control <?= inv('bill_name', $form_errors) ?>"
                       value="<?= old_val('bill_name', $form_old) ?>"
                       placeholder="Ví dụ: Tiền điện T05, Lương nhân viên T05..." maxlength="255">
                <?= err('bill_name', $form_errors) ?>
              </div>
              <div class="col-md-3 mb-3">
                <label for="amount" class="form-label">Số tiền (VND) <span class="text-danger">*</span></label>
                <input type="number" name="amount" id="amount"
                       class="form-control <?= inv('amount', $form_errors) ?>"
                       value="<?= old_val('amount', $form_old) ?>"
                       min="0" step="1000" placeholder="2500000">
                <?= err('amount', $form_errors) ?>
              </div>
              <div class="col-md-3 mb-3">
                <label for="bill_code" class="form-label">Mã số hoá đơn</label>
                <input type="text" name="bill_code" id="bill_code"
                       class="form-control <?= inv('bill_code', $form_errors) ?>"
                       value="<?= old_val('bill_code', $form_old) ?>"
                       placeholder="(tuỳ chọn)" maxlength="100">
                <?= err('bill_code', $form_errors) ?>
              </div>
            </div>

            <div class="row">
              <div class="col-md-4 mb-3">
                <label for="received_date" class="form-label">Ngày nhận <span class="text-danger">*</span></label>
                <input type="date" name="received_date" id="received_date"
                       class="form-control <?= inv('received_date', $form_errors) ?>"
                       value="<?= old_val('received_date', $form_old) ?: date('Y-m-d') ?>">
                <?= err('received_date', $form_errors) ?>
              </div>
              <div class="col-md-4 mb-3">
                <label for="due_date" class="form-label">Ngày đến hạn</label>
                <input type="date" name="due_date" id="due_date"
                       class="form-control <?= inv('due_date', $form_errors) ?>"
                       value="<?= old_val('due_date', $form_old) ?>">
                <?= err('due_date', $form_errors) ?>
                <div class="form-text">Bỏ trống nếu không có hạn thanh toán.</div>
              </div>
            </div>

            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-info text-white">
                <i class="ti ti-device-floppy me-1"></i>Lưu hoá đơn
              </button>
              <button type="reset" class="btn btn-light">Reset</button>
            </div>
          </form>
        </div>
      </div>

      <!-- TOOLBAR: search -->
      <form method="GET" class="card p-3 mb-3">
        <input type="hidden" name="tab" value="expenses">
        <div class="row g-2 align-items-center">
          <div class="col-md-6">
            <div class="input-group">
              <span class="input-group-text bg-white"><i class="ti ti-search"></i></span>
              <input type="text" name="q" class="form-control"
                     placeholder="Tìm theo tên hoá đơn hoặc mã số…"
                     value="<?= htmlspecialchars($q) ?>">
            </div>
          </div>
          <div class="col-md-6 d-flex gap-2">
            <button class="btn btn-primary" type="submit">
              <i class="ti ti-filter me-1"></i>Tìm
            </button>
            <?php if ($q !== ''): ?>
              <a href="finance.php?tab=expenses" class="btn btn-outline-secondary">
                <i class="ti ti-x me-1"></i>Xoá lọc
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
              <th>#</th>
              <th>Tên hoá đơn</th>
              <th class="text-end">Số tiền</th>
              <th>Mã số</th>
              <th>Ngày nhận</th>
              <th>Ngày đến hạn</th>
              <th class="text-center">Thao tác</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($rows)): ?>
              <tr><td colspan="7" class="text-center text-muted py-4">
                <?= $q !== '' ? 'Không tìm thấy kết quả phù hợp.' : 'Chưa có hoá đơn nào. Hãy thêm ở form bên trên.' ?>
              </td></tr>
            <?php else: ?>
              <?php
                $today = date('Y-m-d');
                foreach ($rows as $r):
                    $overdue = $r['due_date'] !== null && $r['due_date'] < $today;
              ?>
                <tr>
                  <td><code class="text-body">#<?= str_pad((string)$r['id'], 4, '0', STR_PAD_LEFT) ?></code></td>
                  <td class="fw-semibold"><?= htmlspecialchars($r['bill_name']) ?></td>
                  <td class="text-end fw-bold text-info"><?= htmlspecialchars(vnd($r['amount'])) ?></td>
                  <td><code class="text-body"><?= htmlspecialchars($r['bill_code'] ?? '—') ?></code></td>
                  <td><?= htmlspecialchars(fmt_date($r['received_date'])) ?></td>
                  <td>
                    <?= htmlspecialchars(fmt_date($r['due_date'])) ?>
                    <?php if ($overdue): ?>
                      <span class="badge bg-danger-subtle text-danger ms-1">Quá hạn</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-center">
                    <a href="edit-expense.php?id=<?= (int)$r['id'] ?>" title="Sửa">
                      <i class="ti ti-edit"></i>
                    </a>
                    <button type="button" class="btn btn-link link-danger p-0 ms-2 align-baseline"
                            title="Xoá"
                            data-bs-toggle="modal" data-bs-target="#deleteModal"
                            data-action="delete_expense"
                            data-id="<?= (int)$r['id'] ?>"
                            data-name="<?= htmlspecialchars($r['bill_name'], ENT_QUOTES) ?>">
                      <i class="ti ti-trash"></i>
                    </button>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

    <!-- PAGINATION (chung cho cả 2 tab) -->
    <?php if ($total_rows > 0): ?>
      <div class="d-flex justify-content-between align-items-center mt-3">
        <div class="text-muted small">
          Hiển thị <strong><?= $offset + 1 ?>–<?= min($offset + $per_page, $total_rows) ?></strong>
          / <strong><?= $total_rows ?></strong> bản ghi
        </div>
        <?php if ($total_pages > 1): ?>
          <nav aria-label="Phân trang">
            <ul class="pagination mb-0">
              <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= $page <= 1 ? '#' : htmlspecialchars(build_url(['page' => $page - 1])) ?>">Previous</a>
              </li>
              <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                  <a class="page-link" href="<?= htmlspecialchars(build_url(['page' => $i])) ?>"><?= $i ?></a>
                </li>
              <?php endfor; ?>
              <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= $page >= $total_pages ? '#' : htmlspecialchars(build_url(['page' => $page + 1])) ?>">Next</a>
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

<!-- MODAL: xác nhận xoá (dùng chung cho cả purchase + expense) -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" action="finance.php?tab=<?= htmlspecialchars($tab) ?>">
        <input type="hidden" name="action" id="delete_action" value="">
        <input type="hidden" name="id"     id="delete_id"     value="">
        <input type="hidden" name="tab"    value="<?= htmlspecialchars($tab) ?>">

        <div class="modal-header">
          <h5 class="modal-title" id="deleteModalLabel">
            <i class="ti ti-alert-triangle text-warning me-2"></i>Xác nhận xoá
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p>Bạn chắc chắn muốn xoá: <strong id="delete_name">—</strong>?</p>
          <p class="mb-0 small text-muted">
            Đây là <em>soft delete</em> — bản ghi sẽ ẩn khỏi danh sách và không tính vào tổng,
            nhưng dữ liệu vẫn được giữ trong DB để kiểm tra lại sau.
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
  // Live preview "Thành tiền" khi nhập purchase
  (function () {
    const qty = document.getElementById('quantity');
    const up  = document.getElementById('unit_price');
    const out = document.getElementById('purchaseTotal');
    if (!qty || !up || !out) return;

    const fmt = new Intl.NumberFormat('vi-VN');
    function update() {
      const q = parseFloat(qty.value);
      const p = parseFloat(up.value);
      if (isFinite(q) && isFinite(p) && q >= 0 && p >= 0) {
        out.textContent = fmt.format(q * p) + '₫';
      } else {
        out.textContent = '—';
      }
    }
    qty.addEventListener('input', update);
    up.addEventListener('input',  update);
    update();
  })();

  // Populate modal xoá với data-* attributes từ button vừa click
  (function () {
    const modal = document.getElementById('deleteModal');
    if (!modal) return;
    modal.addEventListener('show.bs.modal', function (event) {
      const btn = event.relatedTarget;
      if (!btn) return;
      document.getElementById('delete_action').value     = btn.dataset.action || '';
      document.getElementById('delete_id').value         = btn.dataset.id     || '';
      document.getElementById('delete_name').textContent = btn.dataset.name   || '—';
    });
  })();
</script>
</body>
</html>

<?php
/**
 * FitFood — Admin / Chỉnh sửa phiếu nhập nguyên vật liệu
 * URL: http://localhost:8080/admin/edit-purchase.php?id=<id>
 *
 * Flow: GET ?id=... → load record → render form với data cũ.
 *       POST → validate → UPDATE → redirect về finance.php?tab=purchase với flash.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/partials/auth_guard.php';

function is_valid_date(string $d): bool {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) return false;
    [$y, $m, $day] = array_map('intval', explode('-', $d));
    return checkdate($m, $day, $y);
}

function fmt_qty($n): string {
    return rtrim(rtrim(number_format((float)$n, 3, '.', ''), '0'), '.');
}

// ---------- Load record ----------
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    $_SESSION['flash_error'] = 'Không tìm thấy phiếu nhập cần sửa.';
    header('Location: finance.php?tab=purchase');
    exit;
}
if ($pdo === null) {
    $_SESSION['flash_error'] = 'Không kết nối được database.';
    header('Location: finance.php?tab=purchase');
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM purchases WHERE id = ? AND is_deleted = 0 LIMIT 1");
    $stmt->execute([$id]);
    $record = $stmt->fetch();
} catch (PDOException $e) {
    $_SESSION['flash_error'] = 'Lỗi truy vấn: ' . $e->getMessage();
    header('Location: finance.php?tab=purchase');
    exit;
}

if (!$record) {
    $_SESSION['flash_error'] = 'Không tìm thấy phiếu nhập với id = ' . $id . '.';
    header('Location: finance.php?tab=purchase');
    exit;
}

$errors = [];
$old = [
    'material_name' => (string)$record['material_name'],
    'quantity'      => fmt_qty($record['quantity']),
    'unit_price'    => (string)(int)round((float)$record['unit_price']),
    'expiry_date'   => (string)($record['expiry_date'] ?? ''),
    'import_date'   => (string)$record['import_date'],
];

// ---------- Handle POST ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($old as $k => $_) {
        $old[$k] = trim((string)($_POST[$k] ?? ''));
    }

    if ($old['material_name'] === '') {
        $errors['material_name'] = 'Tên nguyên vật liệu không được để trống.';
    } elseif (mb_strlen($old['material_name']) > 255) {
        $errors['material_name'] = 'Tên tối đa 255 ký tự.';
    }

    if ($old['quantity'] === '' || !is_numeric($old['quantity']) || (float)$old['quantity'] <= 0) {
        $errors['quantity'] = 'Số lượng phải là số dương.';
    }

    if ($old['unit_price'] === '' || !is_numeric($old['unit_price']) || (float)$old['unit_price'] < 0) {
        $errors['unit_price'] = 'Đơn giá phải là số không âm.';
    }

    if ($old['import_date'] === '' || !is_valid_date($old['import_date'])) {
        $errors['import_date'] = 'Ngày nhập không hợp lệ.';
    }

    if ($old['expiry_date'] !== '' && !is_valid_date($old['expiry_date'])) {
        $errors['expiry_date'] = 'Hạn sử dụng không hợp lệ.';
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare(
                "UPDATE purchases SET
                    material_name = :material_name,
                    quantity      = :quantity,
                    unit_price    = :unit_price,
                    expiry_date   = :expiry_date,
                    import_date   = :import_date
                 WHERE id = :id"
            );
            $stmt->execute([
                ':material_name' => $old['material_name'],
                ':quantity'      => (float)$old['quantity'],
                ':unit_price'    => (float)$old['unit_price'],
                ':expiry_date'   => $old['expiry_date'] !== '' ? $old['expiry_date'] : null,
                ':import_date'   => $old['import_date'],
                ':id'            => $id,
            ]);
            $_SESSION['flash_success'] = 'Đã cập nhật phiếu nhập "' . $old['material_name'] . '".';
            header('Location: finance.php?tab=purchase');
            exit;
        } catch (PDOException $e) {
            $errors['_db'] = 'Lỗi khi lưu: ' . $e->getMessage();
            error_log('[admin/edit-purchase] ' . $e->getMessage());
        }
    }
}

$active_page = 'finance';

function err(string $field, array $errors): string {
    if (empty($errors[$field])) return '';
    return '<div class="invalid-feedback d-block">' . htmlspecialchars($errors[$field]) . '</div>';
}
function inv(string $field, array $errors): string {
    return empty($errors[$field]) ? '' : 'is-invalid';
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8" />
  <title>Sửa phiếu nhập - FitFood Admin</title>
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
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
          <div>
            <h1 class="fs-3 mb-1">Sửa phiếu nhập</h1>
            <p class="mb-0 text-muted">ID: <code>#<?= str_pad((string)$id, 4, '0', STR_PAD_LEFT) ?></code></p>
          </div>
          <div>
            <a href="finance.php?tab=purchase" class="btn btn-outline-secondary">
              <i class="ti ti-arrow-left me-1"></i>Về danh sách
            </a>
          </div>
        </div>
      </div>
    </div>

    <?php if (!empty($errors['_db'])): ?>
      <div class="alert alert-danger" role="alert">
        <i class="ti ti-alert-triangle me-2"></i>
        <?= htmlspecialchars($errors['_db']) ?>
      </div>
    <?php endif; ?>

    <div class="row">
      <div class="col-12">
        <div class="card">
          <div class="card-body p-4">
            <form method="POST" action="edit-purchase.php?id=<?= (int)$id ?>" novalidate>
              <input type="hidden" name="id" value="<?= (int)$id ?>">

              <div class="row">
                <div class="col-md-6 mb-3">
                  <label for="material_name" class="form-label">Tên nguyên vật liệu <span class="text-danger">*</span></label>
                  <input type="text" name="material_name" id="material_name"
                         class="form-control <?= inv('material_name', $errors) ?>"
                         value="<?= htmlspecialchars($old['material_name']) ?>"
                         maxlength="255">
                  <?= err('material_name', $errors) ?>
                </div>
                <div class="col-md-3 mb-3">
                  <label for="quantity" class="form-label">Số lượng <span class="text-danger">*</span></label>
                  <input type="number" name="quantity" id="quantity"
                         class="form-control <?= inv('quantity', $errors) ?>"
                         value="<?= htmlspecialchars($old['quantity']) ?>"
                         min="0" step="0.001">
                  <?= err('quantity', $errors) ?>
                </div>
                <div class="col-md-3 mb-3">
                  <label for="unit_price" class="form-label">Đơn giá (VND) <span class="text-danger">*</span></label>
                  <input type="number" name="unit_price" id="unit_price"
                         class="form-control <?= inv('unit_price', $errors) ?>"
                         value="<?= htmlspecialchars($old['unit_price']) ?>"
                         min="0" step="1000">
                  <?= err('unit_price', $errors) ?>
                </div>
              </div>

              <div class="row">
                <div class="col-md-4 mb-3">
                  <label for="import_date" class="form-label">Ngày nhập <span class="text-danger">*</span></label>
                  <input type="date" name="import_date" id="import_date"
                         class="form-control <?= inv('import_date', $errors) ?>"
                         value="<?= htmlspecialchars($old['import_date']) ?>">
                  <?= err('import_date', $errors) ?>
                </div>
                <div class="col-md-4 mb-3">
                  <label for="expiry_date" class="form-label">Hạn sử dụng</label>
                  <input type="date" name="expiry_date" id="expiry_date"
                         class="form-control <?= inv('expiry_date', $errors) ?>"
                         value="<?= htmlspecialchars($old['expiry_date']) ?>">
                  <?= err('expiry_date', $errors) ?>
                </div>
                <div class="col-md-4 mb-3">
                  <label class="form-label">Thành tiền (tự tính)</label>
                  <div class="form-control bg-light d-flex align-items-center">
                    <strong id="purchaseTotal" class="text-success">—</strong>
                  </div>
                </div>
              </div>

              <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                  <i class="ti ti-device-floppy me-1"></i>Lưu thay đổi
                </button>
                <a href="finance.php?tab=purchase" class="btn btn-light">Huỷ</a>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>

    <div class="row mt-3">
      <div class="col-12">
        <footer class="text-center py-2 mt-6 text-secondary">
          <p class="mb-0 small">© 2026 FitFood Admin.</p>
        </footer>
      </div>
    </div>

  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="./assets/js/main.js"></script>
<script>
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
</script>
</body>
</html>

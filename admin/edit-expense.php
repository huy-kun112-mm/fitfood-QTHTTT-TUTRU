<?php
/**
 * FitFood — Admin / Chỉnh sửa hoá đơn chi phí vận hành
 * URL: http://localhost:8080/admin/edit-expense.php?id=<id>
 *
 * Flow: GET ?id=... → load record → render form với data cũ.
 *       POST → validate → UPDATE → redirect về finance.php?tab=expenses với flash.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/partials/auth_guard.php';

function is_valid_date(string $d): bool {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) return false;
    [$y, $m, $day] = array_map('intval', explode('-', $d));
    return checkdate($m, $day, $y);
}

// ---------- Load record ----------
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    $_SESSION['flash_error'] = 'Không tìm thấy hoá đơn cần sửa.';
    header('Location: finance.php?tab=expenses');
    exit;
}
if ($pdo === null) {
    $_SESSION['flash_error'] = 'Không kết nối được database.';
    header('Location: finance.php?tab=expenses');
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM expenses WHERE id = ? AND is_deleted = 0 LIMIT 1");
    $stmt->execute([$id]);
    $record = $stmt->fetch();
} catch (PDOException $e) {
    $_SESSION['flash_error'] = 'Lỗi truy vấn: ' . $e->getMessage();
    header('Location: finance.php?tab=expenses');
    exit;
}

if (!$record) {
    $_SESSION['flash_error'] = 'Không tìm thấy hoá đơn với id = ' . $id . '.';
    header('Location: finance.php?tab=expenses');
    exit;
}

$errors = [];
$old = [
    'bill_name'     => (string)$record['bill_name'],
    'amount'        => (string)(int)round((float)$record['amount']),
    'bill_code'     => (string)($record['bill_code'] ?? ''),
    'received_date' => (string)$record['received_date'],
    'due_date'      => (string)($record['due_date'] ?? ''),
];

// ---------- Handle POST ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($old as $k => $_) {
        $old[$k] = trim((string)($_POST[$k] ?? ''));
    }

    if ($old['bill_name'] === '') {
        $errors['bill_name'] = 'Tên hoá đơn không được để trống.';
    } elseif (mb_strlen($old['bill_name']) > 255) {
        $errors['bill_name'] = 'Tên hoá đơn tối đa 255 ký tự.';
    }

    if ($old['amount'] === '' || !is_numeric($old['amount']) || (float)$old['amount'] < 0) {
        $errors['amount'] = 'Số tiền phải là số không âm.';
    }

    if ($old['bill_code'] !== '' && mb_strlen($old['bill_code']) > 100) {
        $errors['bill_code'] = 'Mã hoá đơn tối đa 100 ký tự.';
    }

    if ($old['received_date'] === '' || !is_valid_date($old['received_date'])) {
        $errors['received_date'] = 'Ngày nhận không hợp lệ.';
    }

    if ($old['due_date'] !== '' && !is_valid_date($old['due_date'])) {
        $errors['due_date'] = 'Ngày đến hạn không hợp lệ.';
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare(
                "UPDATE expenses SET
                    bill_name     = :bill_name,
                    amount        = :amount,
                    bill_code     = :bill_code,
                    received_date = :received_date,
                    due_date      = :due_date
                 WHERE id = :id"
            );
            $stmt->execute([
                ':bill_name'     => $old['bill_name'],
                ':amount'        => (float)$old['amount'],
                ':bill_code'     => $old['bill_code'] !== '' ? $old['bill_code'] : null,
                ':received_date' => $old['received_date'],
                ':due_date'      => $old['due_date'] !== '' ? $old['due_date'] : null,
                ':id'            => $id,
            ]);
            $_SESSION['flash_success'] = 'Đã cập nhật hoá đơn "' . $old['bill_name'] . '".';
            header('Location: finance.php?tab=expenses');
            exit;
        } catch (PDOException $e) {
            $errors['_db'] = 'Lỗi khi lưu: ' . $e->getMessage();
            error_log('[admin/edit-expense] ' . $e->getMessage());
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
  <title>Sửa hoá đơn - FitFood Admin</title>
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
            <h1 class="fs-3 mb-1">Sửa hoá đơn</h1>
            <p class="mb-0 text-muted">ID: <code>#<?= str_pad((string)$id, 4, '0', STR_PAD_LEFT) ?></code></p>
          </div>
          <div>
            <a href="finance.php?tab=expenses" class="btn btn-outline-secondary">
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
            <form method="POST" action="edit-expense.php?id=<?= (int)$id ?>" novalidate>
              <input type="hidden" name="id" value="<?= (int)$id ?>">

              <div class="row">
                <div class="col-md-6 mb-3">
                  <label for="bill_name" class="form-label">Tên hoá đơn <span class="text-danger">*</span></label>
                  <input type="text" name="bill_name" id="bill_name"
                         class="form-control <?= inv('bill_name', $errors) ?>"
                         value="<?= htmlspecialchars($old['bill_name']) ?>"
                         maxlength="255">
                  <?= err('bill_name', $errors) ?>
                </div>
                <div class="col-md-3 mb-3">
                  <label for="amount" class="form-label">Số tiền (VND) <span class="text-danger">*</span></label>
                  <input type="number" name="amount" id="amount"
                         class="form-control <?= inv('amount', $errors) ?>"
                         value="<?= htmlspecialchars($old['amount']) ?>"
                         min="0" step="1000">
                  <?= err('amount', $errors) ?>
                </div>
                <div class="col-md-3 mb-3">
                  <label for="bill_code" class="form-label">Mã số hoá đơn</label>
                  <input type="text" name="bill_code" id="bill_code"
                         class="form-control <?= inv('bill_code', $errors) ?>"
                         value="<?= htmlspecialchars($old['bill_code']) ?>"
                         maxlength="100" placeholder="(tuỳ chọn)">
                  <?= err('bill_code', $errors) ?>
                </div>
              </div>

              <div class="row">
                <div class="col-md-6 mb-3">
                  <label for="received_date" class="form-label">Ngày nhận <span class="text-danger">*</span></label>
                  <input type="date" name="received_date" id="received_date"
                         class="form-control <?= inv('received_date', $errors) ?>"
                         value="<?= htmlspecialchars($old['received_date']) ?>">
                  <?= err('received_date', $errors) ?>
                </div>
                <div class="col-md-6 mb-3">
                  <label for="due_date" class="form-label">Ngày đến hạn</label>
                  <input type="date" name="due_date" id="due_date"
                         class="form-control <?= inv('due_date', $errors) ?>"
                         value="<?= htmlspecialchars($old['due_date']) ?>">
                  <?= err('due_date', $errors) ?>
                </div>
              </div>

              <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                  <i class="ti ti-device-floppy me-1"></i>Lưu thay đổi
                </button>
                <a href="finance.php?tab=expenses" class="btn btn-light">Huỷ</a>
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
</body>
</html>

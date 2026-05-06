<?php
/**
 * FitFood — Admin / Chỉnh sửa sản phẩm
 * URL: http://localhost:8080/admin/edit-product.php?id=<id>
 *
 * Flow: GET ?id=... → load record → render form với data cũ.
 *       POST → validate → UPDATE → redirect về inventory.php với flash message.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/partials/auth_guard.php';

// ---------- Helpers (giữ đồng bộ với create-product.php) ----------

/** Bỏ dấu tiếng Việt + slugify. "Gói FIT 4" → "goi-fit-4" */
function vi_to_slug(string $name): string {
    $name = mb_strtolower($name, 'UTF-8');
    $repl = [
        '/[àáảãạăằắẳẵặâầấẩẫậ]/u' => 'a',
        '/[èéẻẽẹêềếểễệ]/u'        => 'e',
        '/[ìíỉĩị]/u'              => 'i',
        '/[òóỏõọôồốổỗộơờớởỡợ]/u' => 'o',
        '/[ùúủũụưừứửữự]/u'        => 'u',
        '/[ỳýỷỹỵ]/u'              => 'y',
        '/đ/u'                    => 'd',
    ];
    foreach ($repl as $pattern => $replacement) {
        $name = preg_replace($pattern, $replacement, $name);
    }
    $name = preg_replace('/[^a-z0-9]+/', '-', $name);
    return trim($name, '-');
}

/**
 * Trả về slug khả dụng, EXCLUDING current product id (cho phép giữ slug cũ).
 * Auto append -2, -3 nếu trùng với product khác.
 */
function unique_slug_excluding(PDO $pdo, string $base, int $exclude_id): string {
    $stmt = $pdo->prepare("SELECT 1 FROM products WHERE slug = ? AND id <> ?");
    $slug = $base ?: 'product';
    $i = 2;
    while (true) {
        $stmt->execute([$slug, $exclude_id]);
        if (!$stmt->fetch()) return $slug;
        $slug = $base . '-' . $i++;
    }
}

// ---------- Load product theo id ----------
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    $_SESSION['flash_error'] = 'Không tìm thấy sản phẩm cần sửa.';
    header('Location: inventory.php');
    exit;
}

if ($pdo === null) {
    $_SESSION['flash_error'] = 'Không kết nối được database.';
    header('Location: inventory.php');
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $product = $stmt->fetch();
} catch (PDOException $e) {
    $_SESSION['flash_error'] = 'Lỗi truy vấn sản phẩm: ' . $e->getMessage();
    header('Location: inventory.php');
    exit;
}

if (!$product) {
    $_SESSION['flash_error'] = 'Không tìm thấy sản phẩm với id = ' . $id . '.';
    header('Location: inventory.php');
    exit;
}

// ---------- State ----------
$errors = [];

// $old: dữ liệu hiện đang hiển thị / sẽ submit. Khởi tạo từ DB.
$old = [
    'name'              => (string)$product['name'],
    'sku'               => (string)$product['sku'],
    'category_id'       => $product['category_id'] !== null ? (string)$product['category_id'] : '',
    'type'              => (string)$product['type'],
    // PDO trả DECIMAL(12,2) dạng string "650000.00" → cast int để hiện gọn trong <input type=number step=1000>
    'price'             => $product['price']      !== null ? (string)(int)round((float)$product['price'])      : '',
    'sale_price'        => $product['sale_price'] !== null ? (string)(int)round((float)$product['sale_price']) : '',
    'stock'             => (string)$product['stock'],
    'unit'              => (string)($product['unit'] ?? ''),
    'calories'          => $product['calories'] !== null ? (string)$product['calories'] : '',
    'is_featured'       => (int)$product['is_featured'],
    'is_active'         => (int)$product['is_active'],
    'image_url'         => (string)($product['image_url'] ?? ''),
    'short_description' => (string)($product['short_description'] ?? ''),
    'description'       => (string)($product['description'] ?? ''),
];

$categories = [];
try {
    $categories = $pdo->query(
        "SELECT id, name FROM categories WHERE is_active = 1 ORDER BY sort_order ASC, name ASC"
    )->fetchAll();
} catch (PDOException $e) {
    error_log('[admin/edit-product] ' . $e->getMessage());
}

// ---------- Handle POST ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($old as $k => $_) {
        if ($k === 'is_featured' || $k === 'is_active') continue;
        $old[$k] = is_array($_POST[$k] ?? null) ? '' : trim((string)($_POST[$k] ?? ''));
    }
    $old['is_featured'] = isset($_POST['is_featured']) ? 1 : 0;
    $old['is_active']   = isset($_POST['is_active'])   ? 1 : 0;

    // --- Validation (đồng bộ với create-product.php) ---
    if ($old['name'] === '') {
        $errors['name'] = 'Tên sản phẩm không được để trống.';
    } elseif (mb_strlen($old['name']) > 200) {
        $errors['name'] = 'Tên sản phẩm tối đa 200 ký tự.';
    }

    if ($old['sku'] === '') {
        $errors['sku'] = 'SKU không được để trống.';
    } elseif (mb_strlen($old['sku']) > 50) {
        $errors['sku'] = 'SKU tối đa 50 ký tự.';
    }

    if ($old['category_id'] === '' || !ctype_digit($old['category_id'])) {
        $errors['category_id'] = 'Vui lòng chọn danh mục.';
    }

    if (!in_array($old['type'], ['package', 'product'], true)) {
        $errors['type'] = 'Loại sản phẩm không hợp lệ.';
    }

    if ($old['price'] === '' || !is_numeric($old['price']) || (float)$old['price'] < 0) {
        $errors['price'] = 'Giá phải là số không âm.';
    }

    if ($old['sale_price'] !== '') {
        if (!is_numeric($old['sale_price']) || (float)$old['sale_price'] < 0) {
            $errors['sale_price'] = 'Giá khuyến mãi phải là số không âm.';
        } elseif (!isset($errors['price']) && (float)$old['sale_price'] >= (float)$old['price']) {
            $errors['sale_price'] = 'Giá khuyến mãi phải nhỏ hơn giá gốc.';
        }
    }

    if ($old['stock'] === '' || !ctype_digit($old['stock'])) {
        $errors['stock'] = 'Tồn kho phải là số nguyên không âm.';
    }

    if ($old['calories'] !== '' && !ctype_digit($old['calories'])) {
        $errors['calories'] = 'Calo phải là số nguyên không âm.';
    }

    if ($old['image_url'] === '') {
        $errors['image_url'] = 'URL ảnh không được để trống.';
    } elseif (!filter_var($old['image_url'], FILTER_VALIDATE_URL)) {
        $errors['image_url'] = 'URL ảnh không hợp lệ.';
    }

    if (mb_strlen($old['short_description']) > 255) {
        $errors['short_description'] = 'Mô tả ngắn tối đa 255 ký tự.';
    }

    // --- Check SKU uniqueness EXCLUDING self ---
    if (empty($errors['sku'])) {
        try {
            $check = $pdo->prepare("SELECT 1 FROM products WHERE sku = ? AND id <> ?");
            $check->execute([$old['sku'], $id]);
            if ($check->fetch()) {
                $errors['sku'] = 'SKU "' . $old['sku'] . '" đã được dùng cho sản phẩm khác.';
            }
        } catch (PDOException $e) {
            $errors['_db'] = 'Lỗi kiểm tra SKU: ' . $e->getMessage();
        }
    }

    // --- UPDATE ---
    if (empty($errors)) {
        try {
            // Chỉ regenerate slug khi name thay đổi (giữ SEO/bookmark nếu name không đổi)
            if ($old['name'] !== (string)$product['name']) {
                $slug = unique_slug_excluding($pdo, vi_to_slug($old['name']), $id);
            } else {
                $slug = (string)$product['slug'];
            }

            $stmt = $pdo->prepare(
                "UPDATE products SET
                    sku               = :sku,
                    category_id       = :category_id,
                    name              = :name,
                    slug              = :slug,
                    type              = :type,
                    short_description = :short_description,
                    description       = :description,
                    price             = :price,
                    sale_price        = :sale_price,
                    calories          = :calories,
                    unit              = :unit,
                    stock             = :stock,
                    image_url         = :image_url,
                    is_featured       = :is_featured,
                    is_active         = :is_active
                 WHERE id = :id"
            );

            $stmt->execute([
                ':sku'               => $old['sku'],
                ':category_id'       => (int)$old['category_id'],
                ':name'              => $old['name'],
                ':slug'              => $slug,
                ':type'              => $old['type'],
                ':short_description' => $old['short_description'] !== '' ? $old['short_description'] : null,
                ':description'       => $old['description']       !== '' ? $old['description']       : null,
                ':price'             => (float)$old['price'],
                ':sale_price'        => $old['sale_price'] !== '' ? (float)$old['sale_price'] : null,
                ':calories'          => $old['calories']   !== '' ? (int)$old['calories']     : null,
                ':unit'              => $old['unit']       !== '' ? $old['unit']              : null,
                ':stock'             => (int)$old['stock'],
                ':image_url'         => $old['image_url'],
                ':is_featured'       => $old['is_featured'],
                ':is_active'         => $old['is_active'],
                ':id'                => $id,
            ]);

            $_SESSION['flash_success'] = 'Đã cập nhật sản phẩm "' . $old['name'] . '" thành công.';
            header('Location: inventory.php');
            exit;
        } catch (PDOException $e) {
            $errors['_db'] = 'Lỗi khi lưu sản phẩm: ' . $e->getMessage();
            error_log('[admin/edit-product] update: ' . $e->getMessage());
        }
    }
}

$active_page = 'inventory';

/** Helper render error nhỏ phía dưới input */
function err(string $field, array $errors): string {
    if (empty($errors[$field])) return '';
    return '<div class="invalid-feedback d-block">' . htmlspecialchars($errors[$field]) . '</div>';
}

/** Helper thêm class is-invalid nếu field có lỗi */
function inv(string $field, array $errors): string {
    return empty($errors[$field]) ? '' : 'is-invalid';
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8" />
  <title>Chỉnh sửa sản phẩm - FitFood Admin</title>
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
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
          <div>
            <h1 class="fs-3 mb-1">Chỉnh sửa sản phẩm</h1>
            <p class="mb-0 text-muted">
              ID: <code>#<?= (int)$id ?></code> · Slug: <code><?= htmlspecialchars($product['slug']) ?></code>
            </p>
          </div>
          <div>
            <a href="inventory.php" class="btn btn-outline-secondary">
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
            <form method="POST" action="edit-product.php?id=<?= (int)$id ?>" novalidate>
              <input type="hidden" name="id" value="<?= (int)$id ?>">

              <div class="row">
                <div class="col-md-6 mb-3">
                  <label for="name" class="form-label">Tên sản phẩm <span class="text-danger">*</span></label>
                  <input type="text" name="name" id="name"
                         class="form-control <?= inv('name', $errors) ?>"
                         value="<?= htmlspecialchars($old['name']) ?>"
                         placeholder="Ví dụ: Gói FIT 4 — Trưa ăn nhẹ">
                  <?= err('name', $errors) ?>
                  <div class="form-text">Slug sẽ được sinh lại tự động nếu bạn đổi tên.</div>
                </div>
                <div class="col-md-6 mb-3">
                  <label for="sku" class="form-label">SKU <span class="text-danger">*</span></label>
                  <input type="text" name="sku" id="sku"
                         class="form-control <?= inv('sku', $errors) ?>"
                         value="<?= htmlspecialchars($old['sku']) ?>"
                         placeholder="Ví dụ: FF-PKG-016">
                  <?= err('sku', $errors) ?>
                </div>
              </div>

              <div class="row">
                <div class="col-md-6 mb-3">
                  <label for="type" class="form-label">Loại <span class="text-danger">*</span></label>
                  <select name="type" id="type" class="form-select <?= inv('type', $errors) ?>">
                    <option value="package" <?= $old['type'] === 'package' ? 'selected' : '' ?>>Gói ăn (package)</option>
                    <option value="product" <?= $old['type'] === 'product' ? 'selected' : '' ?>>Sản phẩm lẻ (product)</option>
                  </select>
                  <?= err('type', $errors) ?>
                </div>
                <div class="col-md-6 mb-3">
                  <label for="category_id" class="form-label">Danh mục <span class="text-danger">*</span></label>
                  <select name="category_id" id="category_id"
                          class="form-select <?= inv('category_id', $errors) ?>">
                    <option value="">— Chọn danh mục —</option>
                    <?php foreach ($categories as $c): ?>
                      <option value="<?= (int)$c['id'] ?>"
                        <?= $old['category_id'] === (string)$c['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['name']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <?= err('category_id', $errors) ?>
                </div>
              </div>

              <div class="row">
                <div class="col-md-6 mb-3">
                  <label for="price" class="form-label">Giá gốc (VND) <span class="text-danger">*</span></label>
                  <input type="number" name="price" id="price"
                         class="form-control <?= inv('price', $errors) ?>"
                         value="<?= htmlspecialchars($old['price']) ?>"
                         min="0" step="1000" placeholder="650000">
                  <?= err('price', $errors) ?>
                  <div class="form-text">Giá niêm yết. Sẽ bị gạch ngang khi có giá khuyến mãi.</div>
                </div>
                <div class="col-md-6 mb-3">
                  <label for="sale_price" class="form-label">Giá khuyến mãi (VND)</label>
                  <input type="number" name="sale_price" id="sale_price"
                         class="form-control <?= inv('sale_price', $errors) ?>"
                         value="<?= htmlspecialchars($old['sale_price']) ?>"
                         min="0" step="1000" placeholder="(để trống nếu không có)">
                  <?= err('sale_price', $errors) ?>
                  <div class="form-text">Bỏ trống nếu không có khuyến mãi. Bắt buộc <strong>nhỏ hơn</strong> giá gốc.</div>
                </div>
              </div>

              <!-- Preview giá bán thực tế (effective price) — giúp tránh nhầm "Giá gốc" với "Giá đang bán" -->
              <div class="alert alert-info py-2 px-3 mb-3 d-flex align-items-center gap-2 small" id="effectiveBox">
                <i class="ti ti-tag"></i>
                <span>Giá đang bán (hiển thị ngoài store):</span>
                <strong id="effectivePrice" class="ms-1">—</strong>
                <span id="effectiveOriginal" class="text-muted text-decoration-line-through ms-2"></span>
              </div>

              <div class="row">
                <div class="col-md-4 mb-3">
                  <label for="stock" class="form-label">Tồn kho <span class="text-danger">*</span></label>
                  <input type="number" name="stock" id="stock"
                         class="form-control <?= inv('stock', $errors) ?>"
                         value="<?= htmlspecialchars($old['stock']) ?>"
                         min="0" step="1" placeholder="100">
                  <?= err('stock', $errors) ?>
                </div>
                <div class="col-md-4 mb-3">
                  <label for="unit" class="form-label">Đơn vị</label>
                  <input type="text" name="unit" id="unit"
                         class="form-control <?= inv('unit', $errors) ?>"
                         value="<?= htmlspecialchars($old['unit']) ?>"
                         placeholder="6 bữa/tuần, 200 Gram/Hộp…">
                  <?= err('unit', $errors) ?>
                </div>
                <div class="col-md-4 mb-3">
                  <label for="calories" class="form-label">Calo (kcal)</label>
                  <input type="number" name="calories" id="calories"
                         class="form-control <?= inv('calories', $errors) ?>"
                         value="<?= htmlspecialchars($old['calories']) ?>"
                         min="0" step="1" placeholder="(tuỳ chọn)">
                  <?= err('calories', $errors) ?>
                </div>
              </div>

              <div class="mb-3">
                <label for="image_url" class="form-label">URL ảnh sản phẩm <span class="text-danger">*</span></label>
                <input type="url" name="image_url" id="image_url"
                       class="form-control <?= inv('image_url', $errors) ?>"
                       value="<?= htmlspecialchars($old['image_url']) ?>"
                       placeholder="https://fitfood.vn/static/sizes/...">
                <?= err('image_url', $errors) ?>
                <div class="mt-2">
                  <img id="imgPreview" src="<?= htmlspecialchars($old['image_url'] ?: '') ?>"
                       alt="Preview" style="max-height: 120px; <?= $old['image_url'] === '' ? 'display:none;' : '' ?>"
                       class="rounded border">
                </div>
              </div>

              <div class="mb-3">
                <label for="short_description" class="form-label">Mô tả ngắn</label>
                <input type="text" name="short_description" id="short_description"
                       class="form-control <?= inv('short_description', $errors) ?>"
                       value="<?= htmlspecialchars($old['short_description']) ?>"
                       maxlength="255" placeholder="Tối đa 255 ký tự, hiển thị ở danh sách">
                <?= err('short_description', $errors) ?>
              </div>

              <div class="mb-3">
                <label for="description" class="form-label">Mô tả chi tiết</label>
                <textarea name="description" id="description" rows="4"
                          class="form-control <?= inv('description', $errors) ?>"
                          placeholder="Mô tả chi tiết về sản phẩm…"><?= htmlspecialchars($old['description']) ?></textarea>
                <?= err('description', $errors) ?>
              </div>

              <div class="mb-2 form-check">
                <input type="checkbox" name="is_featured" id="is_featured" value="1"
                       class="form-check-input"
                       <?= $old['is_featured'] ? 'checked' : '' ?>>
                <label for="is_featured" class="form-check-label">
                  Sản phẩm nổi bật (hiển thị ưu tiên ở trang chủ)
                </label>
              </div>

              <div class="mb-4 form-check form-switch">
                <input type="checkbox" name="is_active" id="is_active" value="1"
                       class="form-check-input"
                       <?= $old['is_active'] ? 'checked' : '' ?>>
                <label for="is_active" class="form-check-label">
                  Đang hoạt động
                  <small class="text-muted">— tắt = ẩn khỏi cửa hàng (Tạm ngừng).</small>
                </label>
              </div>

              <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                  <i class="ti ti-device-floppy me-1"></i>Lưu thay đổi
                </button>
                <a href="inventory.php" class="btn btn-light">Huỷ</a>
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
  // Image preview khi user nhập URL (giống create-product.php)
  (function () {
    const url = document.getElementById('image_url');
    const img = document.getElementById('imgPreview');
    if (!url || !img) return;
    url.addEventListener('input', () => {
      const v = url.value.trim();
      if (v) { img.src = v; img.style.display = ''; }
      else   { img.style.display = 'none'; }
    });
    img.addEventListener('error', () => { img.style.display = 'none'; });
  })();

  // Live preview "Giá đang bán" = sale_price nếu có và < price, ngược lại = price
  (function () {
    const priceEl    = document.getElementById('price');
    const saleEl     = document.getElementById('sale_price');
    const effEl      = document.getElementById('effectivePrice');
    const origEl     = document.getElementById('effectiveOriginal');
    if (!priceEl || !saleEl || !effEl) return;

    const fmt = new Intl.NumberFormat('vi-VN');
    const toNum = v => {
      const n = parseFloat(String(v).replace(/[^\d.-]/g, ''));
      return isFinite(n) ? n : NaN;
    };

    function update() {
      const p  = toNum(priceEl.value);
      const sp = toNum(saleEl.value);
      const hasSale = !isNaN(sp) && sp > 0 && !isNaN(p) && sp < p;
      const effective = hasSale ? sp : (isNaN(p) ? null : p);

      effEl.textContent  = effective !== null ? fmt.format(effective) + '₫' : '—';
      origEl.textContent = hasSale ? fmt.format(p) + '₫' : '';
    }
    priceEl.addEventListener('input', update);
    saleEl.addEventListener('input',  update);
    update();
  })();
</script>
</body>
</html>

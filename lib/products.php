<?php
/**
 * Lib chia sẻ giữa server-render (order.php, menu.php, …)
 * và endpoint JSON (api/products.php).
 *
 * Sử dụng:
 *   require_once __DIR__ . '/lib/products.php';
 *   $grouped = get_products_grouped($pdo, ['type' => 'package']);
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Lấy tất cả category đang active, sắp xếp theo sort_order.
 */
function get_categories(PDO $pdo): array
{
    $sql = "SELECT id, name, slug, description, image_url, sort_order
            FROM categories
            WHERE is_active = 1
            ORDER BY sort_order, id";
    return $pdo->query($sql)->fetchAll();
}

/**
 * Lấy products theo filter (type, category slug). Chỉ trả product active.
 */
function get_products(PDO $pdo, array $filters = []): array
{
    $where  = ['p.is_active = 1'];
    $params = [];

    if (!empty($filters['type']) && in_array($filters['type'], ['package', 'product'], true)) {
        $where[]          = 'p.type = :type';
        $params[':type']  = $filters['type'];
    }

    if (!empty($filters['category'])) {
        $where[]            = 'c.slug = :cat_slug';
        $params[':cat_slug'] = $filters['category'];
    }

    $sql = "SELECT
              p.id, p.category_id, p.name, p.slug, p.type,
              p.short_description, p.description, p.ingredients,
              p.price, p.sale_price, p.calories, p.unit, p.stock,
              p.image_url, p.is_featured, p.sort_order,
              c.slug AS category_slug, c.name AS category_name
            FROM products p
            LEFT JOIN categories c ON c.id = p.category_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY c.sort_order, p.sort_order, p.id";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Lấy products gom nhóm theo category.
 * Trả về: [ {id, name, slug, ..., products: [...]} , ... ]
 * Khi có filter, các category không có sản phẩm nào sẽ bị loại bỏ.
 */
function get_products_grouped(PDO $pdo, array $filters = []): array
{
    $cats     = get_categories($pdo);
    $products = get_products($pdo, $filters);

    $by_id = [];
    foreach ($cats as $c) {
        $c['products']  = [];
        $by_id[$c['id']] = $c;
    }

    foreach ($products as $p) {
        if ($p['category_id'] !== null && isset($by_id[$p['category_id']])) {
            // Bỏ trường lookup khỏi product để JSON gọn hơn
            unset($p['category_slug'], $p['category_name']);
            $by_id[$p['category_id']]['products'][] = $p;
        }
    }

    // Nếu có filter thì chỉ giữ category có sản phẩm
    if (!empty($filters)) {
        $by_id = array_filter($by_id, fn($c) => !empty($c['products']));
    }

    return array_values($by_id);
}

/**
 * Định dạng tiền VND theo style hiện tại của UI: "650,000đ".
 */
function format_vnd($amount): string
{
    return number_format((float)$amount, 0, '.', ',') . 'đ';
}

/**
 * Tính giá đang bán (effective): nếu có sale_price hợp lệ và NHỎ HƠN price thì dùng sale_price.
 * (`sale_price` trong DB là giá KHUYẾN MÃI — luôn <= price.)
 */
function effective_selling_price(array $p): float
{
    $base = (float)$p['price'];
    $sale = $p['sale_price'] ?? null;
    if ($sale !== null && (float)$sale > 0 && (float)$sale < $base) {
        return (float)$sale;
    }
    return $base;
}

/**
 * Render 1 card sản phẩm. Dùng chung cho cả packages và products lẻ.
 * Match cấu trúc HTML cũ trong order.php để không phải đổi CSS/JS:
 *   .price      = giá đang bán (chính)
 *   .price-sale = giá gốc gạch ngang (phụ, chỉ hiện khi có khuyến mãi)
 */
function render_product_card(array $p): void
{
    $type = $p['type'];

    // Dòng phụ: package dùng <p class="desc">, product lẻ dùng <p class="card-text">
    $sub_text = '';
    if ($type === 'package' && !empty($p['short_description'])) {
        $sub_text = '<p class="desc">' . htmlspecialchars($p['short_description']) . '</p>';
    } elseif ($type === 'product' && !empty($p['unit'])) {
        $sub_text = '<p class="card-text">' . htmlspecialchars($p['unit']) . '</p>';
    }

    $base_price = (float)$p['price'];
    $selling    = effective_selling_price($p);
    $has_sale   = $selling < $base_price;

    // Khối giá
    if ($type === 'package') {
        $price_html = '<span>' . format_vnd($selling) . '</span>';
        if ($has_sale) {
            $price_html .= ' <small class="text-muted text-decoration-line-through">'
                         . format_vnd($base_price) . '</small>';
        }
    } else {
        $price_html = '<span class="price">' . format_vnd($selling) . '</span>';
        if ($has_sale) {
            $price_html .= '<span class="price-sale">' . format_vnd($base_price) . '</span>';
        }
    }

    $img        = htmlspecialchars($p['image_url'] ?? '');
    $name       = htmlspecialchars($p['name']);
    // data-price = giá đang bán (cart cộng đúng số khách trả).
    $price_attr = (int)round($selling);
    $calo_attr  = (int)($p['calories'] ?? 0);
    $id_attr    = (int)$p['id'];
    $stock_attr = max(0, (int)($p['stock'] ?? 0));

    // Badge tồn kho — chỉ áp dụng cho product lẻ (package không có giới hạn stock).
    $stock_html     = '';
    $add_cart_class = 'add-to-cart';
    if ($type === 'product') {
        if ($stock_attr <= 0) {
            $stock_html      = '<div class="stock-status stock-status-out">Hết hàng</div>';
            $add_cart_class .= ' is-disabled';
        } elseif ($stock_attr < 5) {
            $stock_html = '<div class="stock-status stock-status-low">Sắp hết hàng (còn ' . $stock_attr . ')</div>';
        } else {
            $stock_html = '<div class="stock-status stock-status-in">Còn hàng</div>';
        }
    }

    // Mapping cho 6 trang detail tĩnh có sẵn. Các sản phẩm khác (admin tự thêm)
    // sẽ trỏ về template động detail-product.php?slug=...
    $detail_pages = [
        'goi-fit-3' => 'detail-product-fit3.php',
        'goi-full'  => 'detail-product-full.php',
        'goi-fit-1' => 'detail-product-fit1.php',
        'goi-lunch' => 'detail-product-lunch.php',
        'goi-meat'  => 'detail-product-meat.php',
        'goi-slim'  => 'detail-product-slim.php',
    ];
    $detail_url = $detail_pages[$p['slug']] ?? ('detail-product.php?slug=' . urlencode($p['slug']));

    echo <<<HTML
            <div class="col-md-3">
                <div class="listing-item"
                     data-id="$id_attr"
                     data-type="$type"
                     data-name="$name"
                     data-price="$price_attr"
                     data-image="$img"
                     data-calo="$calo_attr"
                     data-stock="$stock_attr">
                    <div class="card">
                        <a href="$detail_url" class="link">
                            <img src="$img" class="card-img-top" alt="$name">
                        </a>
                        <div class="card-body">
                            <a href="$detail_url" class="link-text">
                                <h3 class="card-title d-flex justify-content-between">$name</h3>
                            </a>
                            $sub_text
                            $price_html
                            $stock_html
                            <span class="icon"><i class="icon icon-add-cart $add_cart_class" aria-hidden="true"></i></span>
                        </div>
                    </div>
                </div>
            </div>

HTML;
}

/**
 * Render listing 1 category (kèm tiêu đề h2). Bỏ qua nếu không có sản phẩm.
 */
function render_category_section(PDO $pdo, string $cat_slug, string $title): void
{
    $items = get_products($pdo, ['type' => 'product', 'category' => $cat_slug]);
    if (empty($items)) return;
    echo '<h2 class="title title-center pb-4 mb-4">' . htmlspecialchars($title) . '</h2>';
    echo '<div class="products"><div class="row product-listing">';
    foreach ($items as $p) render_product_card($p);
    echo '</div></div>';
}

/**
 * Render danh sách package (Gói ăn).
 */
function render_packages_section(PDO $pdo): void
{
    $items = get_products($pdo, ['type' => 'package']);
    if (empty($items)) return;
    echo '<div class="row product-listing">';
    foreach ($items as $p) render_product_card($p);
    echo '</div>';
}

/**
 * Render 1 card upsell modal (markup khác card listing — dùng `popup-add-cart`).
 */
function render_upsell_card(array $p): void
{
    $id    = (int)$p['id'];
    $name  = htmlspecialchars($p['name']);
    $img   = htmlspecialchars($p['image_url'] ?? '');

    $base_price = (float)$p['price'];
    $selling    = effective_selling_price($p);
    $has_sale   = $selling < $base_price;

    $price = format_vnd($selling);
    // .price-sale = giá gốc gạch ngang, chỉ hiện khi đang khuyến mãi.
    $sale  = $has_sale ? '<span class="price-sale">' . format_vnd($base_price) . '</span>' : '';

    $price_attr = (int)round($selling);
    $calo_attr  = (int)($p['calories'] ?? 0);
    $stock_attr = max(0, (int)($p['stock'] ?? 0));
    echo <<<HTML
            <div class="listing-item"
                 data-id="$id"
                 data-type="product"
                 data-name="$name"
                 data-price="$price_attr"
                 data-image="$img"
                 data-calo="$calo_attr"
                 data-stock="$stock_attr">
                <div class="card">
                    <a href="javascript:;" class="popup-add-cart link" data-id="$id">
                        <img src="$img" class="card-img-top" alt="$name">
                        <span><i class="icon icon-plus1"></i></span>
                    </a>
                    <div class="card-body">
                        <h3 class="card-title d-flex justify-content-between">P_$name </h3>
                        <div class="s_price">
                            $sale<span class="price">$price</span>
                        </div>
                    </div>
                </div>
            </div>
HTML;
}

/**
 * Render modal upsell với N sản phẩm featured (hoặc N đầu tiên nếu không có featured).
 */
function render_upsell_items(PDO $pdo, int $limit = 4): void
{
    $sql = "SELECT id, name, slug, image_url, price, sale_price, calories, stock
            FROM products
            WHERE is_active = 1 AND type = 'product'
            ORDER BY is_featured DESC, sold_count DESC, id ASC
            LIMIT :lim";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    foreach ($stmt->fetchAll() as $p) render_upsell_card($p);
}

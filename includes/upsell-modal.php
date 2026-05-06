<?php
// Modal "Ưu đãi cho bạn" (popup-add-cart) — render từ DB.
// Yêu cầu $pdo có sẵn (require_once lib/products.php trước khi include).
require_once __DIR__ . '/../lib/products.php';
?>
<div class="modal fade modal-popup-sale" id="modal-product" tabindex="-1" role="dialog" aria-labelledby="" aria-hidden="true" data-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <p>Ưu đãi cho bạn</p>
                Chúc mừng bạn, bạn được mua kèm một sản phẩm bên dưới với giá ưu đãi. Click vào sản phẩm để thêm vào giỏ hàng bạn nhé!
            </div>
            <div class="modal-body">
                <div class="products">
<?php render_upsell_items($pdo, 4); ?>
                </div>
            </div>
            <div class="modal-footer">
                <a href="payment.php" class="btn btn-primary btn-block float-right">Tôi không muốn</a>
            </div>
        </div>
    </div>
</div>

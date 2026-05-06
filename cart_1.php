<?php /* Giỏ hàng — include vào navbar. HTML + JS + CSS self-contained. */ ?>
<div id="cart-list" class="shopping-cart order-2 order-xl-0">
    <a href="javascript:void(0)" id="cart" data-total="0">
        <span class="badge hide">0</span>
    </a>
    <div class="list-order-cart">
        <div class="cart-header">
            <a href="javascript:void(0)" class="close"><i class="fa fa-times" aria-hidden="true"></i></a>
            <div class="title">GIỎ HÀNG</div>
        </div>
        <div id="cart-list-content" class="cart-list-content"></div>
        <div class="cart-footer">
            <div class="subtotal">
                <span class="subtotal-label">Tạm tính</span>
                <span class="subtotal-amount">0đ</span>
            </div>
            <a href="payment.php" class="btn-order">Đặt hàng</a>
        </div>
    </div>
    <div class="overlay"></div>
</div>

<style>
#cart-list { position: relative; }
#cart-list .list-order-cart {
    position: fixed; top: 0; right: 0;
    width: 380px; max-width: 100%; height: 100vh;
    background: #fff;
    box-shadow: -2px 0 14px rgba(0,0,0,.15);
    transform: translateX(100%);
    transition: transform .3s ease;
    z-index: 1050;
    display: flex; flex-direction: column;
}
#cart-list.active .list-order-cart { transform: translateX(0); }
#cart-list .overlay {
    position: fixed; inset: 0;
    background: rgba(0,0,0,.45);
    opacity: 0; visibility: hidden;
    transition: opacity .3s, visibility .3s;
    z-index: 1040;
}
#cart-list.active .overlay { opacity: 1; visibility: visible; }
#cart-list .cart-header {
    display: flex; align-items: center;
    padding: 20px 22px; border-bottom: 1px solid #eee;
}
#cart-list .cart-header .close { color: #555; font-size: 18px; margin-right: 16px; text-decoration: none; }
#cart-list .cart-header .title { flex: 1; text-align: center; font-weight: 700; letter-spacing: 1px; color: #111; }
#cart-list-content { flex: 1; overflow-y: auto; padding: 8px 22px; }
#cart-list .cart-item { display: flex; gap: 14px; padding: 14px 0; border-bottom: 1px solid #f1f1f1; }
#cart-list .cart-item-img { width: 92px; height: 72px; object-fit: cover; border-radius: 3px; flex-shrink: 0; }
#cart-list .cart-item-body { flex: 1; min-width: 0; }
#cart-list .cart-item-name { font-weight: 600; color: #111; font-size: 15px; }
#cart-list .cart-item-calo { font-size: 13px; color: #999; margin-top: 2px; }
#cart-list .cart-item-row { display: flex; justify-content: space-between; align-items: center; margin-top: 10px; }
#cart-list .cart-item-price { color: #e4022a; font-weight: 700; font-size: 15px; }
#cart-list .cart-item-controls { display: flex; justify-content: space-between; align-items: center; margin-top: 10px; gap: 10px; }
#cart-list .cart-item-qty { display: inline-flex; align-items: center; gap: 6px; border: 1px solid #ddd; border-radius: 4px; padding: 2px 6px; }
#cart-list .qty-control { width: 28px; height: 28px; border: none; background: #f5f5f5; color: #333; font-size: 18px; line-height: 1; cursor: pointer; border-radius: 4px; }
#cart-list .qty-control:hover { background: #eaeaea; }
#cart-list .qty-value { min-width: 24px; text-align: center; font-weight: 600; }
#cart-list .cart-item-remove { color: #3ba6c9; font-size: 14px; text-decoration: none; }
#cart-list .cart-item-remove:hover { text-decoration: underline; }
#cart-list .empty-cart { text-align: center; padding: 60px 0; color: #999; }
#cart-list .cart-footer { border-top: 1px solid #eee; padding: 18px 22px; }
#cart-list .subtotal { display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; }
#cart-list .subtotal-label { color: #555; font-size: 15px; }
#cart-list .subtotal-amount { font-weight: 700; font-size: 17px; color: #111; }
#cart-list .btn-order {
    display: block; background: #e4022a; color: #fff;
    text-align: center; padding: 12px; text-decoration: none;
    border-radius: 4px; font-weight: 600; letter-spacing: .5px;
}
#cart-list .btn-order:hover { background: #c4001e; color: #fff; }
#cart-list #cart .badge.hide { display: none; }
.stock-status { margin: 6px 0; font-size: 13px; font-weight: 600; }
.stock-status-in { color: #198754; }
.stock-status-low { color: #d97706; }
.stock-status-out { color: #dc3545; }
.add-to-cart.is-disabled,
.popup-add-cart.is-disabled {
    opacity: .45;
    pointer-events: none;
    cursor: not-allowed;
}
#cart-list .cart-stock-warning { color: #dc3545; font-size: 12px; margin-top: 6px; font-weight: 600; }
#cart-list .qty-control:disabled { opacity: .4; cursor: not-allowed; }
</style>

<script>
(function () {
    var STORAGE_KEY = 'fitfood_cart_v1';

    function readCart() {
        try {
            var raw = localStorage.getItem(STORAGE_KEY);
            return raw ? JSON.parse(raw) : [];
        } catch (e) { return []; }
    }
    function writeCart(items) {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(items));
    }
    var LEGACY_PACKAGE_IDS = {
        fit3: 1,
        full: 2,
        fit1: 3,
        meat: 4,
        slim: 5,
        'lunch-eatclean': 6,
        lunch: 6
    };
    var PACKAGE_VARIANT_PRICES = {
        1: { week: 650000, month: 2340000 },
        2: { week: 825000, month: 2970000 },
        3: { week: 650000, month: 2340000 },
        4: { week: 950000, month: 3420000 },
        5: { week: 600000, month: 2160000 },
        6: { week: 349000 }
    };
    function normalizeId(id) {
        var key = String(id == null ? '' : id).trim();
        if (LEGACY_PACKAGE_IDS[key]) return LEGACY_PACKAGE_IDS[key];
        var parsed = parseInt(key, 10);
        return parsed > 0 ? parsed : '';
    }
    function normalizeType(type, name) {
        var rawType = String(type || '').trim();
        var rawName = String(name || '').toLowerCase();
        if (rawType === 'month' || rawName.indexOf('gói tháng') !== -1) return 'month';
        if (rawType === 'week' || rawName.indexOf('gói tuần') !== -1) return 'week';
        return rawType;
    }
    function normalizePrice(id, type, price) {
        var prices = PACKAGE_VARIANT_PRICES[id];
        return prices && prices[type] ? prices[type] : price;
    }
    function normalizeCart(items) {
        var grouped = {};
        (Array.isArray(items) ? items : []).forEach(function (raw) {
            var it = raw || {};
            var id = normalizeId(it.id);
            if (!id) return;
            var name = String(it.name || '').trim();
            var type = normalizeType(it.type, name);
            var price = normalizePrice(id, type, Number(it.price) || 0);
            var image = String(it.image || '').trim();
            var calo = Number(it.calo) || 0;
            var hasStock = it.stock !== undefined && it.stock !== null && it.stock !== '';
            var stock = hasStock ? Math.max(0, Number(it.stock) || 0) : null;
            if (stock === 0) return;
            var quantity = Math.max(1, Number(it.quantity) || 1);
            if (stock !== null && quantity > stock) quantity = stock;
            var key = [id, type, price, name].join('|');
            if (!grouped[key]) {
                grouped[key] = {
                    id: id,
                    type: type,
                    name: name,
                    image: image,
                    price: price,
                    calo: calo,
                    stock: stock,
                    quantity: 0
                };
            }
            grouped[key].quantity += quantity;
            if (grouped[key].stock !== null && grouped[key].quantity > grouped[key].stock) {
                grouped[key].quantity = grouped[key].stock;
            }
            if (!grouped[key].image && image) grouped[key].image = image;
            if (!grouped[key].calo && calo) grouped[key].calo = calo;
            if (grouped[key].stock === null && stock !== null) grouped[key].stock = stock;
        });
        return Object.keys(grouped).map(function (k) { return grouped[k]; });
    }
    function formatVN(n) {
        return (Number(n) || 0).toLocaleString('vi-VN');
    }
    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }
    function getTotalQuantity(items) {
        return items.reduce(function (sum, it) { return sum + (Number(it.quantity) || 1); }, 0);
    }

    function render() {
        var originalItems = readCart();
        var items = normalizeCart(originalItems);
        if (JSON.stringify(items) !== JSON.stringify(originalItems)) {
            writeCart(items);
        }
        var listEl = document.getElementById('cart-list-content');
        var badgeEl = document.querySelector('#cart-list #cart .badge');
        var subtotalEl = document.querySelector('#cart-list .subtotal-amount');
        var cartAnchor = document.querySelector('#cart-list #cart');

        var totalQuantity = getTotalQuantity(items);
        if (badgeEl) {
            badgeEl.textContent = totalQuantity;
            if (totalQuantity > 0) badgeEl.classList.remove('hide');
            else badgeEl.classList.add('hide');
        }
        if (cartAnchor) cartAnchor.setAttribute('data-total', totalQuantity);

        if (listEl) {
            if (items.length === 0) {
                listEl.innerHTML = '<div class="empty-cart">Giỏ hàng trống</div>';
            } else {
                listEl.innerHTML = items.map(function (it, i) {
                    var qty = Number(it.quantity) || 1;
                    var caloLine = it.calo && Number(it.calo) > 0
                        ? '<div class="cart-item-calo">' + escapeHtml(it.calo) + ' Calo</div>'
                        : '';
                    var stock = it.stock === null || it.stock === undefined ? null : Number(it.stock);
                    var stockWarning = stock !== null && qty >= stock
                        ? '<div class="cart-stock-warning">Chỉ còn ' + stock + ' sản phẩm</div>'
                        : '';
                    var increaseDisabled = stock !== null && qty >= stock ? ' disabled' : '';
                    var itemTotal = qty * (Number(it.price) || 0);
                    return '' +
                        '<div class="cart-item" data-index="' + i + '">' +
                            '<img src="' + escapeHtml(it.image) + '" alt="" class="cart-item-img">' +
                            '<div class="cart-item-body">' +
                                '<div class="cart-item-name">' + escapeHtml(it.name) + '</div>' +
                                caloLine +
                                '<div class="cart-item-row cart-item-controls">' +
                                    '<div class="cart-item-qty">' +
                                        '<button type="button" class="qty-control qty-decrease" data-index="' + i + '" aria-label="Giảm">−</button>' +
                                        '<span class="qty-value">' + qty + '</span>' +
                                        '<button type="button" class="qty-control qty-increase" data-index="' + i + '" aria-label="Tăng"' + increaseDisabled + '>+</button>' +
                                    '</div>' +
                                    '<span class="cart-item-price">' + formatVN(itemTotal) + '</span>' +
                                '</div>' +
                                stockWarning +
                                '<div class="cart-item-row">' +
                                    '<a href="javascript:void(0)" class="cart-item-remove" data-index="' + i + '">Xóa</a>' +
                                '</div>' +
                            '</div>' +
                        '</div>';
                }).join('');
            }
        }

        if (subtotalEl) {
            var total = items.reduce(function (s, it) {
                return s + (Number(it.price) || 0) * (Number(it.quantity) || 1);
            }, 0);
            subtotalEl.textContent = formatVN(total) + 'đ';
        }
    }

    function addFromListingItem(itemEl) {
        if (!itemEl) return;
        var name = itemEl.getAttribute('data-name') || '';
        var image = itemEl.getAttribute('data-image') || '';
        var price = parseInt(itemEl.getAttribute('data-price') || '0', 10) || 0;
        var calo = parseInt(itemEl.getAttribute('data-calo') || '0', 10) || 0;
        var stock = parseInt(itemEl.getAttribute('data-stock') || '0', 10) || 0;
        var id = itemEl.getAttribute('data-id') || '';
        var type = itemEl.getAttribute('data-type') || '';
        if (stock <= 0) {
            alert('Sản phẩm đã hết hàng.');
            return;
        }

        // Fallback parse nếu thiếu data-*
        if (!id) {
            var linkWithId = itemEl.querySelector('[data-id]');
            if (linkWithId) id = linkWithId.getAttribute('data-id') || '';
        }
        if (!name) {
            var h3 = itemEl.querySelector('.card-title');
            if (h3) name = h3.textContent.trim();
        }
        if (!image) {
            var img = itemEl.querySelector('.card-img-top');
            if (img) image = img.getAttribute('src') || '';
        }
        if (!price) {
            // Lấy giá từ DOM: ưu tiên .price-sale, fallback .price (text VND có dấu , và đ)
            var pe = itemEl.querySelector('.price-sale') || itemEl.querySelector('.price');
            if (pe) price = parseInt((pe.textContent || '').replace(/[^\d]/g, ''), 10) || 0;
        }

        var items = readCart();
        var normalizedItem = normalizeCart([{ id: id, type: type, name: name, image: image, price: price, calo: calo, stock: stock, quantity: 1 }])[0];
        if (!normalizedItem) return;
        var foundIndex = items.findIndex(function (it) {
            return String(normalizeId(it.id)) === String(normalizedItem.id)
                && String(it.type || '') === String(normalizedItem.type || '')
                && Number(it.price) === Number(normalizedItem.price)
                && String(it.name || '') === String(normalizedItem.name || '');
        });

        if (foundIndex >= 0) {
            var nextQty = (Number(items[foundIndex].quantity) || 1) + 1;
            if (stock > 0 && nextQty > stock) {
                items[foundIndex].quantity = stock;
                writeCart(normalizeCart(items));
                render();
                alert('Chỉ còn ' + stock + ' sản phẩm');
                openPopup();
                return;
            }
            items[foundIndex].quantity = nextQty;
        } else {
            items.push(normalizedItem);
        }
        writeCart(normalizeCart(items));
        render();
        openPopup();
    }

    function removeAt(i) {
        var items = readCart();
        if (i >= 0 && i < items.length) {
            items.splice(i, 1);
            writeCart(items);
            render();
        }
    }

    function changeQuantity(i, delta) {
        var items = readCart();
        if (i < 0 || i >= items.length) return;
        var qty = (Number(items[i].quantity) || 1) + delta;
        var stock = items[i].stock === null || items[i].stock === undefined ? null : Number(items[i].stock);
        if (stock !== null && qty > stock) {
            items[i].quantity = stock;
            writeCart(normalizeCart(items));
            render();
            alert('Chỉ còn ' + stock + ' sản phẩm');
            return;
        }
        if (qty <= 0) {
            items.splice(i, 1);
        } else {
            items[i].quantity = qty;
        }
        writeCart(items);
        render();
    }

    function openPopup() {
        var w = document.getElementById('cart-list');
        if (w) w.classList.add('active');
    }
    function closePopup() {
        var w = document.getElementById('cart-list');
        if (w) w.classList.remove('active');
    }
    function syncStockFromApi() {
        if (!window.fetch) return;
        fetch('api/products.php', { credentials: 'same-origin', cache: 'no-store' })
            .then(function (res) { return res.ok ? res.json() : null; })
            .then(function (json) {
                if (!json || !json.success || !json.data || !json.data.categories) return;
                var stocks = {};
                json.data.categories.forEach(function (cat) {
                    (cat.products || []).forEach(function (p) {
                        stocks[String(p.id)] = Math.max(0, Number(p.stock) || 0);
                    });
                });
                var changed = false;
                var items = readCart().map(function (it) {
                    var id = String(normalizeId(it.id));
                    if (Object.prototype.hasOwnProperty.call(stocks, id)) {
                        if (it.stock !== stocks[id]) {
                            it.stock = stocks[id];
                            changed = true;
                        }
                    }
                    return it;
                });
                if (changed) {
                    writeCart(normalizeCart(items));
                    render();
                }
            })
            .catch(function () {});
    }

    function bind() {
        document.addEventListener('click', function (e) {
            if (e.target.closest('#cart-list #cart')) {
                e.preventDefault();
                var w = document.getElementById('cart-list');
                if (w.classList.contains('active')) closePopup(); else openPopup();
                return;
            }
            if (e.target.closest('#cart-list .cart-header .close')) {
                e.preventDefault();
                closePopup();
                return;
            }
            if (e.target.closest('#cart-list .overlay')) {
                closePopup();
                return;
            }
            var addBtn = e.target.closest('.add-to-cart, .popup-add-cart');
            if (addBtn) {
                e.preventDefault();
                e.stopPropagation();
                var li = addBtn.closest('.listing-item') || addBtn;
                addFromListingItem(li);
                return;
            }
            var qtyBtn = e.target.closest('#cart-list .qty-control');
            if (qtyBtn) {
                e.preventDefault();
                var idx = parseInt(qtyBtn.getAttribute('data-index'), 10);
                if (!isNaN(idx)) {
                    changeQuantity(idx, qtyBtn.classList.contains('qty-increase') ? 1 : -1);
                }
                return;
            }
            var removeBtn = e.target.closest('#cart-list .cart-item-remove');
            if (removeBtn) {
                e.preventDefault();
                var idx = parseInt(removeBtn.getAttribute('data-index'), 10);
                if (!isNaN(idx)) removeAt(idx);
                return;
            }
        });
    }

    window.FitfoodCart = {
        add: function (item) {
            var items = readCart();
            var normalizedItems = normalizeCart([item || {}]);
            if (!normalizedItems.length) return;
            var normalizedItem = normalizedItems[0];
            if (item && Object.prototype.hasOwnProperty.call(item, 'stock') && Number(normalizedItem.stock) <= 0) {
                alert('Sản phẩm đã hết hàng.');
                return;
            }
            var foundIndex = items.findIndex(function (it) {
                return String(normalizeId(it.id)) === String(normalizedItem.id)
                    && String(it.type || '') === String(normalizedItem.type || '')
                    && Number(it.price) === Number(normalizedItem.price)
                    && String(it.name || '') === String(normalizedItem.name || '');
            });
            if (foundIndex >= 0) {
                var nextQty = (Number(items[foundIndex].quantity) || 1) + normalizedItem.quantity;
                if (normalizedItem.stock !== null && nextQty > normalizedItem.stock) {
                    items[foundIndex].quantity = normalizedItem.stock;
                    writeCart(normalizeCart(items));
                    render();
                    alert('Chỉ còn ' + normalizedItem.stock + ' sản phẩm');
                    openPopup();
                    return;
                }
                items[foundIndex].quantity = nextQty;
            } else {
                items.push(normalizedItem);
            }
            writeCart(normalizeCart(items));
            render();
            openPopup();
        },
        open: openPopup,
        close: closePopup,
        render: render
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { render(); bind(); syncStockFromApi(); });
    } else {
        render(); bind(); syncStockFromApi();
    }
})();
</script>

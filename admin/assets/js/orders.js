/**
 * FitFood Admin / orders.php
 * - Đổi status qua dropdown inline → POST order-action.php?action=update_status
 * - Click mã đơn hoặc icon mắt → mở modal chi tiết (GET ?action=detail&id=)
 * - Click icon thùng rác → confirm rồi soft delete (set status='cancelled')
 */
(function () {
  'use strict';

  const ENDPOINT = './order-action.php';
  const STATUS_LABEL = {
    pending:    ['Chờ duyệt',  'warning'],
    processing: ['Đang xử lý', 'primary'],
    completed:  ['Hoàn tất',   'success'],
    cancelled:  ['Đã huỷ',     'danger'],
  };
  const PAY_LABEL = {
    cod:    'Thanh toán khi nhận hàng (COD)',
    bank:   'Chuyển khoản ngân hàng',
    momo:   'Ví MoMo',
    vnpay:  'VNPay',
    card:   'Thẻ tín dụng/ghi nợ',
  };

  // ---------- Toast helper ----------
  const toastBox = document.getElementById('ordersToast');
  function showToast(message, type = 'success') {
    if (!toastBox) return alert(message);
    const id = 't' + Date.now();
    const bg = type === 'success' ? 'bg-success' : 'bg-danger';
    toastBox.insertAdjacentHTML('beforeend', `
      <div id="${id}" class="toast align-items-center text-white ${bg} border-0" role="alert">
        <div class="d-flex">
          <div class="toast-body">${message}</div>
          <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
      </div>
    `);
    const el = document.getElementById(id);
    const t = new bootstrap.Toast(el, { delay: 3000 });
    t.show();
    el.addEventListener('hidden.bs.toast', () => el.remove());
  }

  // ---------- Format helpers ----------
  function vnd(n) {
    return Number(n || 0).toLocaleString('vi-VN') + '₫';
  }
  function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }
  function formatDateTime(ts) {
    if (!ts) return '';
    const d = new Date(ts.replace(' ', 'T'));
    if (isNaN(d)) return ts;
    const pad = (n) => String(n).padStart(2, '0');
    return `${pad(d.getDate())}/${pad(d.getMonth() + 1)}/${d.getFullYear()} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
  }

  // ---------- Update status ----------
  document.querySelectorAll('.js-status-select').forEach((sel) => {
    sel.addEventListener('change', async function () {
      const orderId   = this.dataset.orderId;
      const newStatus = this.value;
      const oldStatus = this.dataset.current;
      if (newStatus === oldStatus) return;

      this.disabled = true;
      try {
        const fd = new FormData();
        fd.append('action', 'update_status');
        fd.append('id', orderId);
        fd.append('status', newStatus);

        const res = await fetch(ENDPOINT, { method: 'POST', body: fd });
        const data = await res.json();

        if (!data.success) {
          this.value = oldStatus;
          showToast(data.message || 'Cập nhật thất bại.', 'error');
          return;
        }

        this.dataset.current = newStatus;
        showToast(`Đơn #${orderId}: ${data.message}`);

        // Nếu chuyển sang cancelled → disable nút xoá + select luôn (giống state khi load lại trang)
        if (newStatus === 'cancelled') {
          this.disabled = true;
          const row = this.closest('tr');
          if (row) {
            const trash = row.querySelector('.js-cancel-order');
            if (trash) trash.remove();
          }
        }
      } catch (err) {
        this.value = oldStatus;
        showToast('Không kết nối được server.', 'error');
      } finally {
        // Bật lại nếu chưa cancelled (cancelled thì để disable)
        if (this.dataset.current !== 'cancelled') this.disabled = false;
      }
    });
  });

  // ---------- Cancel order (soft delete) ----------
  document.querySelectorAll('.js-cancel-order').forEach((btn) => {
    btn.addEventListener('click', async function (e) {
      e.preventDefault();
      const orderId = this.dataset.orderId;
      if (!confirm(`Huỷ đơn hàng #${orderId}?\nThao tác này sẽ chuyển trạng thái sang "Đã huỷ" (vẫn lưu trong DB).`)) {
        return;
      }

      const fd = new FormData();
      fd.append('action', 'update_status');
      fd.append('id', orderId);
      fd.append('status', 'cancelled');

      try {
        const res = await fetch(ENDPOINT, { method: 'POST', body: fd });
        const data = await res.json();
        if (!data.success) {
          showToast(data.message || 'Huỷ đơn thất bại.', 'error');
          return;
        }
        showToast(`Đã huỷ đơn #${orderId}.`);

        // Cập nhật UI: đổi select sang cancelled + disable, xoá nút thùng rác
        const row = document.querySelector(`tr[data-order-id="${orderId}"]`);
        if (row) {
          const sel = row.querySelector('.js-status-select');
          if (sel) {
            sel.value = 'cancelled';
            sel.dataset.current = 'cancelled';
            sel.disabled = true;
          }
          this.remove();
        }
      } catch (err) {
        showToast('Không kết nối được server.', 'error');
      }
    });
  });

  // ---------- Open detail modal ----------
  const modalEl   = document.getElementById('orderDetailModal');
  const modalBody = document.getElementById('orderDetailBody');
  const modalLbl  = document.getElementById('orderDetailLabel');
  const modal     = modalEl ? new bootstrap.Modal(modalEl) : null;

  document.querySelectorAll('.js-open-detail').forEach((a) => {
    a.addEventListener('click', async function (e) {
      e.preventDefault();
      const orderId = this.dataset.orderId;
      if (!modal) return;

      modalLbl.textContent = `Chi tiết đơn hàng #${String(orderId).padStart(6, '0')}`;
      modalBody.innerHTML = `
        <div class="text-center text-muted py-5">
          <div class="spinner-border spinner-border-sm me-2"></div>Đang tải…
        </div>`;
      modal.show();

      try {
        const res = await fetch(`${ENDPOINT}?action=detail&id=${encodeURIComponent(orderId)}`);
        const data = await res.json();
        if (!data.success) {
          modalBody.innerHTML = `<div class="alert alert-danger mb-0">${escapeHtml(data.message || 'Lỗi tải chi tiết.')}</div>`;
          return;
        }
        modalBody.innerHTML = renderDetail(data.order, data.items);
      } catch (err) {
        modalBody.innerHTML = `<div class="alert alert-danger mb-0">Không kết nối được server.</div>`;
      }
    });
  });

  function renderDetail(order, items) {
    const [stLabel, stColor] = STATUS_LABEL[order.status] || [order.status, 'secondary'];

    // Items table
    let subTotal = 0;
    const itemRows = (items || []).map((it) => {
      const lineTotal = Number(it.unit_price) * Number(it.quantity);
      subTotal += lineTotal;
      const img = it.image_url || './assets/images/logo-icon.svg';
      return `
        <tr>
          <td>
            <div class="d-flex align-items-center gap-2">
              <img src="${escapeHtml(img)}" width="40" height="40" class="rounded" style="object-fit:cover;" alt="">
              <div>
                <div>${escapeHtml(it.product_name)}</div>
                <small class="text-muted">${escapeHtml(it.unit || '')}</small>
              </div>
            </div>
          </td>
          <td class="text-center">${Number(it.quantity)}</td>
          <td class="text-end">${vnd(it.unit_price)}</td>
          <td class="text-end fw-semibold">${vnd(lineTotal)}</td>
        </tr>`;
    }).join('');

    const shipFee  = Number(order.ship_fee  || 0);
    const discount = Number(order.discount  || 0);
    const customerName  = order.recipient_name || order.user_name || 'Khách vãng lai';
    const customerEmail = order.email || order.user_email || '';
    const payLabel = PAY_LABEL[order.pay_method] || (order.pay_method || '—');

    return `
      <div class="row g-3">
        <div class="col-md-6">
          <h6 class="text-muted small text-uppercase mb-2">Khách hàng</h6>
          <div><strong>${escapeHtml(customerName)}</strong></div>
          <div class="small text-muted">SĐT: ${escapeHtml(order.phone || '—')}</div>
          <div class="small text-muted">Email: ${escapeHtml(customerEmail || '—')}</div>
        </div>
        <div class="col-md-6">
          <h6 class="text-muted small text-uppercase mb-2">Giao hàng</h6>
          <div class="small">${escapeHtml(order.address || '—')}</div>
          <div class="small text-muted mt-1">Thời gian: ${escapeHtml(order.delivery_time || '—')}</div>
          <div class="small text-muted">Thanh toán: ${escapeHtml(payLabel)}</div>
        </div>

        <div class="col-12 d-flex align-items-center gap-3">
          <span class="text-muted small">Trạng thái:</span>
          <span class="badge bg-${stColor}-subtle text-${stColor}">${escapeHtml(stLabel)}</span>
          <span class="text-muted small ms-auto">Đặt lúc: ${escapeHtml(formatDateTime(order.created_at))}</span>
        </div>

        ${order.note_order || order.note ? `
          <div class="col-12">
            <h6 class="text-muted small text-uppercase mb-2">Ghi chú</h6>
            <div class="small">${escapeHtml(order.note_order || order.note)}</div>
          </div>` : ''}

        <div class="col-12">
          <h6 class="text-muted small text-uppercase mb-2">Sản phẩm</h6>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Sản phẩm</th>
                  <th class="text-center">SL</th>
                  <th class="text-end">Đơn giá</th>
                  <th class="text-end">Thành tiền</th>
                </tr>
              </thead>
              <tbody>${itemRows || '<tr><td colspan="4" class="text-center text-muted">Không có sản phẩm</td></tr>'}</tbody>
              <tfoot>
                <tr>
                  <td colspan="3" class="text-end text-muted">Tạm tính</td>
                  <td class="text-end">${vnd(subTotal)}</td>
                </tr>
                <tr>
                  <td colspan="3" class="text-end text-muted">Phí giao hàng</td>
                  <td class="text-end">${vnd(shipFee)}</td>
                </tr>
                ${discount > 0 ? `
                  <tr>
                    <td colspan="3" class="text-end text-muted">Giảm giá</td>
                    <td class="text-end text-danger">−${vnd(discount)}</td>
                  </tr>` : ''}
                <tr>
                  <td colspan="3" class="text-end fw-bold">Tổng cộng</td>
                  <td class="text-end fw-bold fs-5">${vnd(order.total_amount)}</td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
      </div>`;
  }
})();

/**
 * FitFood Admin / customers.php
 * Click nút mắt → fetch ./customer-action.php?action=detail&id=
 * → render vào #customerDetailBody → show modal.
 * Read-only: không có thao tác cập nhật / xoá.
 */
(function () {
  'use strict';

  const ENDPOINT = './customer-action.php';
  const STATUS_LABEL = {
    pending:    ['Chờ duyệt',  'warning'],
    processing: ['Đang xử lý', 'primary'],
    completed:  ['Hoàn tất',   'success'],
    cancelled:  ['Đã huỷ',     'danger'],
  };
  const PROVIDER_LABEL = {
    local:    'Tài khoản nội bộ',
    google:   'Google',
    facebook: 'Facebook',
  };

  const modalEl = document.getElementById('customerDetailModal');
  const bodyEl  = document.getElementById('customerDetailBody');
  const modal   = modalEl ? new bootstrap.Modal(modalEl) : null;

  function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }
  function vnd(n) {
    return Number(n || 0).toLocaleString('vi-VN') + '₫';
  }
  function formatDateTime(ts) {
    if (!ts) return '—';
    const d = new Date(ts.replace(' ', 'T'));
    if (isNaN(d)) return ts;
    const pad = (n) => String(n).padStart(2, '0');
    return `${pad(d.getDate())}/${pad(d.getMonth() + 1)}/${d.getFullYear()} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
  }
  function statusBadge(status) {
    const [label, color] = STATUS_LABEL[status] || [status || '—', 'secondary'];
    return `<span class="badge bg-${color}-subtle text-${color}">${escapeHtml(label)}</span>`;
  }

  function renderLoading() {
    bodyEl.innerHTML = `
      <div class="text-center text-muted py-5">
        <div class="spinner-border spinner-border-sm me-2"></div>Đang tải…
      </div>`;
  }
  function renderError(msg) {
    bodyEl.innerHTML = `
      <div class="alert alert-danger mb-0">
        <i class="ti ti-alert-triangle me-2"></i>${escapeHtml(msg)}
      </div>`;
  }

  function renderDetail(d) {
    const u = d.user || {};
    const s = d.stats || {};
    const orders = Array.isArray(d.recent_orders) ? d.recent_orders : [];
    const addresses = Array.isArray(d.addresses) ? d.addresses : [];

    const statusBadgeHtml = (Number(u.status) === 1)
      ? '<span class="badge bg-success-subtle text-success">Hoạt động</span>'
      : '<span class="badge bg-secondary-subtle text-secondary">Tạm khoá</span>';

    const providerLabel = PROVIDER_LABEL[u.provider] || u.provider || '—';

    let ordersHtml;
    if (orders.length === 0) {
      ordersHtml = '<div class="text-muted small">Khách hàng chưa có đơn hàng nào.</div>';
    } else {
      ordersHtml = `
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Mã đơn</th>
                <th>Ngày tạo</th>
                <th>Trạng thái</th>
                <th class="text-end">Giá trị</th>
              </tr>
            </thead>
            <tbody>
              ${orders.map(o => `
                <tr>
                  <td class="fw-semibold">#${String(o.id).padStart(6, '0')}</td>
                  <td>${escapeHtml(formatDateTime(o.created_at))}</td>
                  <td>${statusBadge(o.status)}</td>
                  <td class="text-end">${vnd(o.total_amount)}</td>
                </tr>`).join('')}
            </tbody>
          </table>
        </div>`;
    }

    let addressesHtml;
    if (addresses.length === 0) {
      addressesHtml = '<div class="text-muted small">Khách hàng chưa lưu địa chỉ giao hàng.</div>';
    } else {
      addressesHtml = `
        <ul class="list-group list-group-flush">
          ${addresses.map(a => `
            <li class="list-group-item px-0">
              <div class="d-flex justify-content-between align-items-start">
                <div>
                  <div class="fw-semibold">
                    ${escapeHtml(a.recipient_name)}
                    ${Number(a.is_default) === 1 ? '<span class="badge bg-primary-subtle text-primary ms-2">Mặc định</span>' : ''}
                  </div>
                  <div class="small text-muted">SĐT: ${escapeHtml(a.phone || '—')}</div>
                  <div class="small">${escapeHtml(a.address)}</div>
                </div>
              </div>
            </li>`).join('')}
        </ul>`;
    }

    bodyEl.innerHTML = `
      <div class="row g-3 mb-4">
        <div class="col-md-7">
          <h6 class="text-muted mb-2">Thông tin tài khoản</h6>
          <dl class="row mb-0 small">
            <dt class="col-4 text-muted">Họ tên</dt>
            <dd class="col-8 fw-semibold">${escapeHtml(u.full_name)}</dd>
            <dt class="col-4 text-muted">Email</dt>
            <dd class="col-8">${escapeHtml(u.email)}</dd>
            <dt class="col-4 text-muted">SĐT</dt>
            <dd class="col-8">${escapeHtml(u.phone || '—')}</dd>
            <dt class="col-4 text-muted">Nguồn đăng nhập</dt>
            <dd class="col-8">${escapeHtml(providerLabel)}</dd>
            <dt class="col-4 text-muted">Trạng thái</dt>
            <dd class="col-8">${statusBadgeHtml}</dd>
            <dt class="col-4 text-muted">Ngày đăng ký</dt>
            <dd class="col-8">${escapeHtml(formatDateTime(u.created_at))}</dd>
          </dl>
        </div>
        <div class="col-md-5">
          <h6 class="text-muted mb-2">Thống kê mua hàng</h6>
          <div class="card border bg-light-subtle">
            <div class="card-body py-3">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="text-muted">Số đơn đã mua</span>
                <span class="fs-5 fw-semibold">${Number(s.total_orders || 0)}</span>
              </div>
              <div class="d-flex justify-content-between align-items-center">
                <span class="text-muted">Tổng đã chi</span>
                <span class="fs-5 fw-semibold text-success">${vnd(s.total_spent)}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <h6 class="text-muted mb-2">5 đơn hàng gần nhất</h6>
      <div class="mb-4">${ordersHtml}</div>

      <h6 class="text-muted mb-2">Địa chỉ giao hàng</h6>
      <div>${addressesHtml}</div>
    `;
  }

  document.querySelectorAll('.js-view-customer').forEach((btn) => {
    btn.addEventListener('click', async function () {
      const id = this.dataset.id;
      if (!id || !modal) return;

      renderLoading();
      modal.show();

      try {
        const res = await fetch(`${ENDPOINT}?action=detail&id=${encodeURIComponent(id)}`, {
          headers: { 'Accept': 'application/json' },
        });
        const data = await res.json();
        if (!data.success) {
          renderError(data.message || 'Không tải được dữ liệu.');
          return;
        }
        renderDetail(data.data);
      } catch (err) {
        renderError('Lỗi kết nối: ' + err.message);
      }
    });
  });
})();

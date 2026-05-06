/**
 * Xử lý popup đăng nhập & gửi form qua AJAX
 */
document.addEventListener('DOMContentLoaded', function () {
    const openBtn   = document.getElementById('btnOpenLogin');
    const closeBtn  = document.getElementById('btnCloseLogin');
    const overlay   = document.getElementById('loginModal');
    const form      = document.getElementById('loginForm');
    const msgBox    = document.getElementById('loginMessage');
    const submitBtn = form.querySelector('.btn-submit-ff');
    const switchReg = document.getElementById('switchToRegister');

    // Mở popup đăng nhập
    openBtn.addEventListener('click', function (e) {
        e.preventDefault();
        overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    });

    // Đóng popup (nút X)
    closeBtn.addEventListener('click', closeModal);

    // Đóng khi click vùng tối bên ngoài
    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) closeModal();
    });

    // Đóng khi nhấn ESC
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && overlay.classList.contains('active')) closeModal();
    });

    // Auto-mở modal nếu bị redirect về kèm ?login=required (chưa đăng nhập
    // mà truy cập trang admin) hoặc ?login=forbidden (đã đăng nhập nhưng
    // không phải admin). Hiện thông báo phù hợp với từng trường hợp.
    const params = new URLSearchParams(window.location.search);
    const loginFlag = params.get('login');
    if (loginFlag === 'required' || loginFlag === 'forbidden') {
        overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
        msgBox.className = 'form-message error';
        msgBox.textContent = loginFlag === 'forbidden'
            ? 'Tài khoản của bạn không có quyền truy cập trang quản trị.'
            : 'Vui lòng đăng nhập tài khoản admin để tiếp tục.';
    }

    // Chuyển sang popup đăng ký
    if (switchReg) {
        switchReg.addEventListener('click', function () {
            closeModal();
            const regModal = document.getElementById('registerModal');
            if (regModal) {
                regModal.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
        });
    }

    function closeModal() {
        overlay.classList.remove('active');
        document.body.style.overflow = '';
        clearErrors();
        msgBox.className = 'form-message';
        msgBox.textContent = '';
        form.reset();
    }

    function clearErrors() {
        form.querySelectorAll('.form-error').forEach(el => {
            el.classList.remove('show');
            el.textContent = '';
        });
        form.querySelectorAll('input').forEach(el => el.classList.remove('error'));
    }

    function showFieldError(fieldName, message) {
        const input = form.querySelector(`[name="${fieldName}"]`);
        const errEl = form.querySelector(`.form-error[data-for="${fieldName}"]`);
        if (input) input.classList.add('error');
        if (errEl) {
            errEl.textContent = message;
            errEl.classList.add('show');
        }
    }

    // Submit form qua AJAX
    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        clearErrors();
        msgBox.className = 'form-message';
        msgBox.textContent = '';

        submitBtn.disabled = true;
        submitBtn.textContent = 'Đang xử lý...';

        try {
            const formData = new FormData(form);
            const res = await fetch('login.php', {
                method: 'POST',
                body: formData,
            });
            const data = await res.json();

            if (data.success) {
                msgBox.className = 'form-message success';
                msgBox.textContent = data.message || 'Đăng nhập thành công!';
                // Admin có redirect_url → chuyển sang dashboard. User thường → reload.
                setTimeout(() => {
                    closeModal();
                    if (data.redirect_url) {
                        window.location.href = data.redirect_url;
                    } else {
                        window.location.reload();
                    }
                }, 1500);
            } else {
                if (data.errors) {
                    Object.keys(data.errors).forEach(field => {
                        showFieldError(field, data.errors[field]);
                    });
                } else {
                    msgBox.className = 'form-message error';
                    msgBox.textContent = data.message || 'Có lỗi xảy ra.';
                }
            }
        } catch (err) {
            msgBox.className = 'form-message error';
            msgBox.textContent = 'Không kết nối được server. Vui lòng thử lại.';
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Đăng nhập';
        }
    });
});

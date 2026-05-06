/**
 * Xử lý popup đăng ký & gửi form qua AJAX
 * Viết bằng vanilla JS, không phụ thuộc jQuery
 */
(function () {
    function init() {
        const openBtn  = document.getElementById('btnOpenRegister');
        const closeBtn = document.getElementById('btnCloseRegister');
        const overlay  = document.getElementById('registerModal');
        const form     = document.getElementById('registerForm');
        const msgBox   = document.getElementById('formMessage');

        if (!openBtn || !overlay || !form) {
            console.warn('[register.js] Thiếu phần tử DOM. Kiểm tra nút #btnOpenRegister & popup #registerModal.');
            return;
        }
        const submitBtn = form.querySelector('.btn-submit-ff');

        // Mở popup
        openBtn.addEventListener('click', function (e) {
            e.preventDefault();
            overlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        });

        // Đóng popup (nút X)
        closeBtn.addEventListener('click', closeModal);

        // Đóng khi click ra ngoài
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) closeModal();
        });

        // Đóng bằng ESC
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && overlay.classList.contains('active')) closeModal();
        });

        function closeModal() {
            overlay.classList.remove('active');
            document.body.style.overflow = '';
            clearErrors();
            msgBox.className = 'form-message';
            msgBox.textContent = '';
            form.reset();
        }

        function clearErrors() {
            form.querySelectorAll('.form-error').forEach(function (el) {
                el.classList.remove('show');
                el.textContent = '';
            });
            form.querySelectorAll('input').forEach(function (el) {
                el.classList.remove('error');
            });
        }

        function showFieldError(fieldName, message) {
            const input = form.querySelector('[name="' + fieldName + '"]');
            const errEl = form.querySelector('.form-error[data-for="' + fieldName + '"]');
            if (input) input.classList.add('error');
            if (errEl) {
                errEl.textContent = message;
                errEl.classList.add('show');
            }
        }

        // Submit qua fetch
        form.addEventListener('submit', async function (e) {
            e.preventDefault();
            clearErrors();
            msgBox.className = 'form-message';
            msgBox.textContent = '';

            submitBtn.disabled = true;
            submitBtn.textContent = 'Đang xử lý...';

            try {
                const formData = new FormData(form);
                const res = await fetch('register.php', {
                    method: 'POST',
                    body: formData,
                });
                const data = await res.json();

                if (data.success) {
                    msgBox.className = 'form-message success';
                    msgBox.textContent = data.message || 'Đăng ký thành công!';
                    form.reset();
                    setTimeout(function () {
                        closeModal();
                        window.location.reload();
                    }, 1800);
                } else {
                    if (data.errors) {
                        Object.keys(data.errors).forEach(function (field) {
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
                submitBtn.textContent = 'Đăng ký';
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();

<!-- ========= POPUP ĐĂNG KÝ ========= -->
<div class="modal-overlay" id="registerModal">
    <div class="modal-box">
        <button class="modal-close" id="btnCloseRegister" type="button" aria-label="Đóng">&times;</button>

        <h2 class="modal-title">Đăng ký</h2>
        <p class="modal-subtitle">
            Bạn đã có tài khoản? Vui lòng <a href="#">Đăng nhập</a>
        </p>

        <div class="form-message" id="formMessage"></div>

        <form id="registerForm" novalidate>
            <div class="form-group-ff">
                <input type="text" name="full_name" placeholder="Họ &amp; tên" autocomplete="name">
                <div class="form-error" data-for="full_name"></div>
            </div>
            <div class="form-group-ff">
                <input type="email" name="email" placeholder="Email" autocomplete="email">
                <div class="form-error" data-for="email"></div>
            </div>
            <div class="form-group-ff">
                <input type="password" name="password" placeholder="Mật khẩu" autocomplete="new-password">
                <div class="form-error" data-for="password"></div>
            </div>
            <div class="form-group-ff">
                <input type="password" name="confirm_password" placeholder="Xác nhận mật khẩu" autocomplete="new-password">
                <div class="form-error" data-for="confirm_password"></div>
            </div>

            <button type="submit" class="btn-submit-ff">Đăng ký</button>
        </form>

        <div class="divider-ff">Hoặc</div>

        <a href="auth/google_login.php" class="btn-google-ff" style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center;">
            <span class="g-icon">G</span>
            Đăng ký với Google
        </a>
    </div>
</div>

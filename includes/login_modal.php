<!-- ========= POPUP ĐĂNG NHẬP ========= -->
<div class="modal-overlay" id="loginModal">
    <div class="modal-box">
        <button class="modal-close" id="btnCloseLogin" type="button" aria-label="Đóng">&times;</button>

        <h2 class="modal-title">Đăng nhập</h2>
        <p class="modal-subtitle">
            Bạn chưa có tài khoản? Vui lòng <a href="javascript:void(0)" id="switchToRegister">Đăng ký</a>
        </p>

        <div class="form-message" id="loginMessage"></div>

        <form id="loginForm" novalidate>
            <div class="form-group-ff">
                <input type="email" name="email" placeholder="Email" autocomplete="email">
                <div class="form-error" data-for="email"></div>
            </div>
            <div class="form-group-ff">
                <input type="password" name="password" placeholder="Mật khẩu" autocomplete="current-password">
                <div class="form-error" data-for="password"></div>
            </div>

            <button type="submit" class="btn-submit-ff">Đăng nhập</button>
        </form>

        <div class="divider-ff">Hoặc</div>

        <a href="auth/google_login.php" class="btn-google-ff" style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center;">
            <span class="g-icon">G</span>
            Đăng nhập với Google
        </a>
    </div>
</div>

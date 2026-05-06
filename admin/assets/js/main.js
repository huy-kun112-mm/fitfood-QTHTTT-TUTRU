// FitFood Admin — sidebar toggle + active link
// (Trích từ inapp-1.0.0/src/assets/js/sidebar.js, không dùng module
//  để chạy được trực tiếp trên Apache, không cần Vite/npm.)

(function () {
  const sidebar   = document.getElementById('sidebar');
  const content   = document.getElementById('content');
  const topbar    = document.getElementById('topbar');
  const toggleBtn = document.getElementById('toggleBtn');
  const mobileBtn = document.getElementById('mobileBtn');
  const overlay   = document.getElementById('overlay');

  if (toggleBtn) {
    toggleBtn.addEventListener('click', () => {
      if (sidebar) sidebar.classList.toggle('collapsed');
      if (content) content.classList.toggle('full');
      if (topbar)  topbar.classList.toggle('full');
    });
  }

  if (mobileBtn) {
    mobileBtn.addEventListener('click', () => {
      if (sidebar) sidebar.classList.add('mobile-show');
      if (overlay) overlay.classList.add('show');
    });
  }

  if (overlay) {
    overlay.addEventListener('click', () => {
      if (sidebar) sidebar.classList.remove('mobile-show');
      overlay.classList.remove('show');
    });
  }

  // Active link theo URL hiện tại
  const path = window.location.pathname;
  const currentPage = (path.split('/').filter(Boolean).pop() || 'index.php');
  document.querySelectorAll('.sidebar .nav-link').forEach((link) => {
    const href = link.getAttribute('href') || '';
    if (href === currentPage || href === path) {
      link.classList.add('active');
    } else {
      link.classList.remove('active');
    }
  });
})();

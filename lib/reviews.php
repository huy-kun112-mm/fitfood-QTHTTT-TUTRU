<?php
/**
 * Lib bình luận sản phẩm.
 *
 * Quy tắc:
 * - Chỉ user có ít nhất 1 đơn hàng `completed` chứa product_id mới được review.
 * - 1 user / 1 sản phẩm = 1 review duy nhất (unique key trong DB).
 * - User có thể sửa hoặc xóa review của mình. Admin xóa được mọi review.
 *
 * Sử dụng: require_once __DIR__ . '/lib/reviews.php';
 */

require_once __DIR__ . '/../config/database.php';

/** Kiểm tra user có đủ điều kiện bình luận sản phẩm này không. */
function can_user_review(PDO $pdo, int $user_id, int $product_id): bool
{
    if ($user_id <= 0 || $product_id <= 0) return false;
    $sql = "SELECT 1
              FROM order_items oi
              JOIN orders o ON o.id = oi.order_id
             WHERE oi.product_id = :pid
               AND o.user_id    = :uid
               AND o.status     = 'completed'
             LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':pid' => $product_id, ':uid' => $user_id]);
    return (bool)$stmt->fetchColumn();
}

/** Lấy review của 1 user cho 1 sản phẩm (null nếu chưa có). */
function get_user_review(PDO $pdo, int $user_id, int $product_id): ?array
{
    $stmt = $pdo->prepare(
        "SELECT id, content, created_at, updated_at
           FROM product_reviews
          WHERE user_id = :uid AND product_id = :pid
          LIMIT 1"
    );
    $stmt->execute([':uid' => $user_id, ':pid' => $product_id]);
    return $stmt->fetch() ?: null;
}

/** Lấy danh sách review của 1 sản phẩm (kèm thông tin user). */
function get_product_reviews(PDO $pdo, int $product_id, int $limit = 50): array
{
    $stmt = $pdo->prepare(
        "SELECT r.id, r.content, r.created_at, r.updated_at,
                r.user_id, u.full_name, u.avatar
           FROM product_reviews r
           JOIN users u ON u.id = r.user_id
          WHERE r.product_id = :pid
          ORDER BY r.created_at DESC
          LIMIT :lim"
    );
    $stmt->bindValue(':pid', $product_id, PDO::PARAM_INT);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/** Tạo mới hoặc cập nhật review của user cho sản phẩm. */
function upsert_review(PDO $pdo, int $user_id, int $product_id, string $content): void
{
    $stmt = $pdo->prepare(
        "INSERT INTO product_reviews (product_id, user_id, content)
         VALUES (:pid, :uid, :content)
         ON DUPLICATE KEY UPDATE content = VALUES(content)"
    );
    $stmt->execute([
        ':pid'     => $product_id,
        ':uid'     => $user_id,
        ':content' => $content,
    ]);
}

/**
 * Xóa review.
 * - $only_user_id != null  → chỉ xóa nếu review thuộc user đó (user tự xóa).
 * - $only_user_id == null  → xóa không điều kiện (admin xóa).
 * Trả về true nếu có dòng bị xóa.
 */
function delete_review(PDO $pdo, int $review_id, ?int $only_user_id = null): bool
{
    if ($only_user_id !== null) {
        $stmt = $pdo->prepare("DELETE FROM product_reviews WHERE id = :id AND user_id = :uid");
        $stmt->execute([':id' => $review_id, ':uid' => $only_user_id]);
    } else {
        $stmt = $pdo->prepare("DELETE FROM product_reviews WHERE id = :id");
        $stmt->execute([':id' => $review_id]);
    }
    return $stmt->rowCount() > 0;
}

/** Format avatar URL theo cùng pattern navbar đã dùng (uploads/avatars/ hoặc URL tuyệt đối). */
function review_avatar_url(?string $av): string
{
    if (!$av) return 'https://fitfood.vn/img/128/avatars/default.png';
    if (preg_match('#^https?://#', $av)) return $av;
    return 'uploads/avatars/' . $av;
}

/** "3 ngày trước", "vừa xong", "12/03/2026"… cho thời gian Việt. */
function review_time_ago(string $datetime): string
{
    $ts   = strtotime($datetime);
    $diff = time() - $ts;
    if ($diff < 60)        return 'vừa xong';
    if ($diff < 3600)      return floor($diff / 60)   . ' phút trước';
    if ($diff < 86400)     return floor($diff / 3600) . ' giờ trước';
    if ($diff < 86400 * 7) return floor($diff / 86400) . ' ngày trước';
    return date('d/m/Y', $ts);
}

/**
 * Render full UI section "Đánh giá sản phẩm" trên trang detail.
 * Tự động chia 4 nhánh dựa trên trạng thái user.
 * Nếu $pdo null hoặc $product không hợp lệ → ẩn nguyên section.
 */
function render_reviews_section(?PDO $pdo, ?array $product): void
{
    if (!$pdo || !$product || empty($product['id'])) return;

    $product_id = (int)$product['id'];
    $user_id    = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
    $logged_in  = $user_id > 0;
    $eligible   = $logged_in && can_user_review($pdo, $user_id, $product_id);
    $my_review  = $eligible ? get_user_review($pdo, $user_id, $product_id) : null;
    $reviews    = get_product_reviews($pdo, $product_id);
    $count      = count($reviews);

    ?>
    <section class="product-reviews pt-4 pb-4" id="product-reviews">
        <div class="container">
            <div class="row">
                <div class="col-md-10 mx-auto">
                    <h2 class="title pb-3 mb-3">
                        Đánh giá sản phẩm
                        <small class="text-muted" style="font-size:14px;font-weight:400">
                            (<?= $count ?> bình luận)
                        </small>
                    </h2>

                    <?php /* ===== Form khu vực ===== */ ?>
                    <div class="review-form-wrap mb-4 p-3"
                         style="background:#f7f7f7;border-radius:8px;border:1px solid #eee">
                        <?php if (!$logged_in): ?>
                            <p class="mb-0">
                                Vui lòng
                                <a href="javascript:void(0)" id="btnOpenLogin"
                                   style="color:#0066cc;font-weight:500">đăng nhập</a>
                                để bình luận.
                            </p>
                        <?php elseif (!$eligible): ?>
                            <p class="mb-0" style="color:#666">
                                Bạn cần mua và hoàn tất đơn hàng có sản phẩm này để bình luận.
                            </p>
                        <?php else: ?>
                            <form id="review-form" data-product-id="<?= $product_id ?>"
                                  data-mode="<?= $my_review ? 'edit' : 'new' ?>"
                                  <?= $my_review ? 'data-review-id="' . (int)$my_review['id'] . '"' : '' ?>>
                                <label for="review-content" class="form-label" style="font-weight:500">
                                    <?= $my_review ? 'Cập nhật bình luận của bạn' : 'Viết bình luận của bạn' ?>
                                </label>
                                <textarea id="review-content"
                                          name="content"
                                          class="form-control mb-2"
                                          rows="3"
                                          maxlength="2000"
                                          placeholder="Chia sẻ trải nghiệm của bạn về sản phẩm…"
                                          required><?= $my_review ? htmlspecialchars($my_review['content']) : '' ?></textarea>
                                <div class="d-flex" style="gap:8px">
                                    <button type="submit" class="btn btn-primary">
                                        <?= $my_review ? 'Cập nhật' : 'Gửi bình luận' ?>
                                    </button>
                                    <?php if ($my_review): ?>
                                        <button type="button" id="review-delete-btn"
                                                class="btn btn-outline-danger">
                                            Xóa bình luận của tôi
                                        </button>
                                    <?php endif; ?>
                                </div>
                                <p id="review-msg" class="mt-2 mb-0" style="font-size:13px;display:none"></p>
                            </form>
                        <?php endif; ?>
                    </div>

                    <?php /* ===== Danh sách bình luận ===== */ ?>
                    <div class="review-list">
                        <?php if ($count === 0): ?>
                            <p class="text-muted">Chưa có bình luận nào. Hãy là người đầu tiên!</p>
                        <?php else: foreach ($reviews as $r):
                            $av    = review_avatar_url($r['avatar']);
                            $name  = htmlspecialchars($r['full_name']);
                            $ago   = review_time_ago($r['updated_at'] ?: $r['created_at']);
                            $edited = $r['updated_at'] && $r['created_at']
                                      && $r['updated_at'] !== $r['created_at'];
                        ?>
                            <div class="review-item d-flex mb-3 pb-3"
                                 style="border-bottom:1px solid #eee">
                                <div class="me-3" style="flex-shrink:0">
                                    <div style="width:48px;height:48px;border-radius:50%;
                                                background:url('<?= htmlspecialchars($av) ?>') center/cover no-repeat;
                                                background-color:#ddd"></div>
                                </div>
                                <div style="flex:1">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <strong><?= $name ?></strong>
                                        <small class="text-muted">
                                            <?= htmlspecialchars($ago) ?>
                                            <?= $edited ? ' (đã sửa)' : '' ?>
                                        </small>
                                    </div>
                                    <div style="white-space:pre-wrap;color:#333">
                                        <?= nl2br(htmlspecialchars($r['content'])) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php if ($eligible): ?>
    <script>
    (function () {
        var form  = document.getElementById('review-form');
        if (!form) return;
        var msg   = document.getElementById('review-msg');
        var delBtn = document.getElementById('review-delete-btn');

        function showMsg(text, ok) {
            if (!msg) return;
            msg.textContent = text;
            msg.style.display = '';
            msg.style.color = ok ? '#28a745' : '#dc3545';
        }

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var content = document.getElementById('review-content').value.trim();
            if (!content) { showMsg('Vui lòng nhập nội dung.', false); return; }

            var fd = new FormData();
            fd.append('product_id', form.getAttribute('data-product-id'));
            fd.append('content', content);

            fetch('api/review-submit.php', { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json().catch(function(){ return { ok:false, error:'Lỗi server' }; }); })
                .then(function (res) {
                    if (res && res.ok) {
                        showMsg('Đã lưu bình luận.', true);
                        setTimeout(function () { location.reload(); }, 600);
                    } else {
                        showMsg((res && res.error) || 'Không gửi được bình luận.', false);
                    }
                })
                .catch(function () { showMsg('Lỗi kết nối.', false); });
        });

        if (delBtn) {
            delBtn.addEventListener('click', function () {
                if (!confirm('Xóa bình luận của bạn?')) return;
                var fd = new FormData();
                fd.append('review_id', form.getAttribute('data-review-id'));
                fetch('api/review-delete.php', { method: 'POST', body: fd, credentials: 'same-origin' })
                    .then(function (r) { return r.json().catch(function(){ return { ok:false }; }); })
                    .then(function (res) {
                        if (res && res.ok) location.reload();
                        else showMsg((res && res.error) || 'Không xóa được.', false);
                    })
                    .catch(function () { showMsg('Lỗi kết nối.', false); });
            });
        }
    })();
    </script>
    <?php endif; ?>
    <?php
}

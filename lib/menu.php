<?php
/**
 * Helper truy vấn weekly menu (regular / vegetarian).
 * Dùng bởi menu.php và (sau này có thể) api/menu.php.
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Lấy tuần active của 1 loại menu + items grouped theo ngày & bữa.
 *
 * Trả về:
 * [
 *   'week' => ['id'=>..., 'type'=>..., 'week_start_date'=>'2026-04-20', 'cover_image_url'=>...],
 *   'days' => [
 *     2 => [ 'breakfast' => {...item...}, 'lunch' => {...}, 'dinner' => {...} ],
 *     3 => [...], ..., 6 => [...]
 *   ]
 * ]
 * hoặc null nếu không có tuần active.
 */
function get_active_menu(PDO $pdo, string $type): ?array
{
    if (!in_array($type, ['regular', 'vegetarian'], true)) return null;

    $stmt = $pdo->prepare("
        SELECT id, type, week_start_date, cover_image_url
        FROM menu_weeks
        WHERE type = :type AND is_active = 1
        ORDER BY week_start_date DESC
        LIMIT 1
    ");
    $stmt->execute([':type' => $type]);
    $week = $stmt->fetch();
    if (!$week) return null;

    $stmt = $pdo->prepare("
        SELECT day_of_week, meal_slot, name_vi, name_en, nutrition_info, sticker_url
        FROM menu_items
        WHERE menu_week_id = :wid
        ORDER BY day_of_week, sort_order, id
    ");
    $stmt->execute([':wid' => $week['id']]);

    $days = [];
    foreach ($stmt->fetchAll() as $it) {
        $d = (int)$it['day_of_week'];
        $days[$d][$it['meal_slot']] = $it;
    }
    ksort($days);

    return ['week' => $week, 'days' => $days];
}

/**
 * Convert week_start_date + day_of_week (2..6) → "20.04", "21.04", v.v.
 * Tham số $week_start: 'YYYY-MM-DD' (Thứ 2).
 */
function date_for_day_num(string $week_start, int $day_num): string
{
    $offset = $day_num - 2; // T2 = 0, T3 = 1, ..., T6 = 4
    return date('d.m', strtotime($week_start . " +{$offset} days"));
}

/**
 * "2026-04-20" → "20.04 - 24.04" (Thứ 2 → Thứ 6)
 */
function format_week_range(string $week_start): string
{
    $start = date('d.m', strtotime($week_start));
    $end   = date('d.m', strtotime($week_start . ' +4 days'));
    return "{$start} - {$end}";
}

/**
 * Render bảng thực đơn cho 1 loại (regular / vegetarian).
 * Match cấu trúc HTML cũ của menu.php (Bootstrap nav-pills + tab-content).
 */
function render_menu_table(array $menu_data, string $type): void
{
    $week = $menu_data['week'];
    $days = $menu_data['days'];
    if (empty($days)) return;

    $day_labels = [2 => 'Thứ 2', 3 => 'Thứ 3', 4 => 'Thứ 4', 5 => 'Thứ 5', 6 => 'Thứ 6'];
    $day_short  = [2 => 'T2',    3 => 'T3',    4 => 'T4',    5 => 'T5',    6 => 'T6'];

    if ($type === 'vegetarian') {
        $slots         = ['meal1', 'meal2'];
        $slot_headers  = ['Meal1', 'Meal2'];
        $btn_class     = 'btn-success';
        $col_color     = 'text-success';
        $table_extra   = 'green';
        $tab_prefix    = 'veg-day';
    } else {
        $slots         = ['breakfast', 'lunch', 'dinner'];
        $slot_headers  = ['Sáng', 'Trưa', 'Tối'];
        $btn_class     = 'btn-primary';
        $col_color     = 'text-danger';
        $table_extra   = '';
        $tab_prefix    = 'reg-day';
    }

    $week_range = format_week_range($week['week_start_date']);
    $first_day  = array_key_first($days);
    $cover      = htmlspecialchars($week['cover_image_url'] ?? '');
    ?>
<div class="table-menu <?= $table_extra ?> pb-5 mb-5">
    <div class="d-md-flex align-items-center text-center justify-content-md-between mb-4">
        <div class="time-week mb-3 mb-md-0 d-flex">
            <p class="time active"><?= htmlspecialchars($week_range) ?></p>
        </div>
        <a href="order.php" class="btn <?= $btn_class ?> order-now">Đặt Ngay</a>
    </div>

    <div class="thead-menu d-none mb-3 d-md-flex flex-fill justify-content-between text-center">
        <div class="col-menu <?= $col_color ?>">Ngày</div>
        <?php foreach ($slot_headers as $h): ?>
            <div class="col-menu"><?= htmlspecialchars($h) ?></div>
        <?php endforeach; ?>
    </div>

    <div class="nav nav-pills d-md-none active" role="tablist">
        <?php foreach ($days as $day_num => $_): ?>
            <a class="nav-link <?= ($day_num === $first_day) ? 'active' : '' ?>"
               data-toggle="pill"
               href="#<?= $tab_prefix ?>-<?= $day_num ?>"
               role="tab"
               aria-selected="<?= ($day_num === $first_day) ? 'true' : 'false' ?>">
                <?= htmlspecialchars($day_labels[$day_num] ?? "T{$day_num}") ?><br>
                <?= htmlspecialchars(date_for_day_num($week['week_start_date'], $day_num)) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="tab-content">
        <div class="tab-item active" data-img="<?= $cover ?>">
            <?php $first = true; foreach ($days as $day_num => $meals_by_slot): ?>
                <div class="tab-pane fade <?= $first ? 'active show' : '' ?>"
                     id="<?= $tab_prefix ?>-<?= $day_num ?>" role="tabpanel">
                    <div class="day-menu mb-3">
                        <div class="col-menu d-none d-md-block">
                            <div class="date"><?= htmlspecialchars($day_short[$day_num] ?? "T{$day_num}") ?><br />
                                <?= htmlspecialchars(date_for_day_num($week['week_start_date'], $day_num)) ?></div>
                        </div>
                        <?php foreach ($slots as $slot): $m = $meals_by_slot[$slot] ?? null; ?>
                            <div class="col-menu">
                                <?php if (!$m): ?>
                                    <!-- (chưa có món cho bữa này) -->
                                <?php elseif ($type === 'vegetarian'): ?>
                                    <span class="h5 d-md-none"><?= htmlspecialchars($slot) ?></span>
                                    <strong><?= htmlspecialchars($m['name_vi']) ?></strong><br />
                                    <?= htmlspecialchars($m['name_en'] ?? '') ?>
                                <?php else: ?>
                                    <p class="d-md-none"><?= htmlspecialchars($slot) ?></p>
                                    <h3><?= htmlspecialchars($m['name_vi']) ?></h3>
                                    <?= htmlspecialchars($m['name_en'] ?? '') ?>
                                    <br>
                                    <?php if (!empty($m['nutrition_info'])): ?>
                                        <div class="group-line">
                                            <span class="text-danger"><?= htmlspecialchars($m['nutrition_info']) ?></span>
                                            <?php if (!empty($m['sticker_url'])): ?>
                                                <div class="stickers"><div><img src="<?= htmlspecialchars($m['sticker_url']) ?>"></div></div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php $first = false; ?>
            <?php endforeach; ?>
        </div>
    </div>
</div>
    <?php
}

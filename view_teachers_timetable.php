<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

require_once __DIR__ . '/includes/period_schema.php';

$page_title = 'View Teachers Timetable';

$user_id    = (int) ($_GET['user_id'] ?? 0);
$period_cat = (int) ($_GET['periodCat_id'] ?? 0);

$instructors = [];
$res = db_query("SELECT emp_id, CONCAT(first_name, ' ', COALESCE(last_name, '')) AS full_name, designation
                 FROM employees
                 WHERE status=1 AND (
                     designation LIKE '%teacher%' OR designation LIKE '%lecturer%'
                     OR designation LIKE '%instructor%' OR designation LIKE '%professor%'
                     OR designation LIKE '%subject specialist%'
                     OR emp_id IN (SELECT DISTINCT teacher_id FROM timetable WHERE teacher_id IS NOT NULL)
                 ) ORDER BY first_name");
while ($row = $res->fetch_assoc()) { $instructors[] = $row; }

$categories = [];
$res = db_query("SELECT * FROM period_categories ORDER BY name");
while ($row = $res->fetch_assoc()) { $categories[] = $row; }

$instructor = null;
foreach ($instructors as $t) {
    if ((int) $t['emp_id'] === $user_id) { $instructor = $t; break; }
}

$days = [
    'Monday' => 'Mon',
    'Tuesday' => 'Tue',
    'Wednesday' => 'Wed',
    'Thursday' => 'Thu',
    'Friday' => 'Fri',
    'Saturday' => 'Sat',
    'Sunday' => 'Sun',
];

$schedule = []; // day => list of period entries
if ($user_id > 0 && $instructor) {
    $sql = "SELECT t.day, t.period_id, t.class_id, t.subject_id, t.section_id,
                   c.class_name, p.period_name, p.start_time, p.end_time,
                   s.subject_name, sec.section_name
            FROM timetable t
            JOIN classes c ON t.class_id=c.class_id
            LEFT JOIN sections sec ON t.section_id=sec.section_id
            LEFT JOIN subjects s ON t.subject_id=s.subject_id
            LEFT JOIN periods p ON t.period_id=p.period_id
            LEFT JOIN class_periods cp ON cp.class_id=t.class_id AND IFNULL(cp.section_id,0)=IFNULL(t.section_id,0)
            LEFT JOIN period_categories pc ON pc.id=cp.period_cat_id
            WHERE t.teacher_id=?";
    if ($period_cat > 0) { $sql .= " AND (pc.id=? OR p.category_id=?)"; }
    $sql .= " ORDER BY FIELD(t.day, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'), t.period_id";
    $stmt = db_prepare($sql);
    if ($period_cat > 0) { $stmt->bind_param('iii', $user_id, $period_cat, $period_cat); }
    else { $stmt->bind_param('i', $user_id); }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) { $schedule[$row['day']][] = $row; }
}

include __DIR__ . '/includes/header.php';
?>
<style>
.search-bar-student { display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end; background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:16px; margin-bottom:16px; }
.slot { background:linear-gradient(135deg,#4F46E5,#7C3AED); color:#fff; border-radius:10px; padding:10px 12px; min-height:64px; }
.slot small { color:#E0E7FF; display:block; }
</style>

<div class="main-content">
    <div class="container-fluid">
        <div style="display:flex; align-items:center; justify-content:space-between; padding:14px 4px;">
            <h3 style="font-size:18px; font-weight:800; color:#111827; margin:0;"><i class="fa fa-calendar"></i> View Teachers Timetable</h3>
            <div style="display:flex; gap:8px;">
                <button onclick="window.print()" class="btn btn-success" style="color:#fff;"><i class="fa fa-print"></i> Print</button>
                <a href="<?php echo BASE_URL; ?>class_period_selection.php" class="btn btn-primary" style="color:#fff;"><i class="fa fa-plus"></i> Create Time Table</a>
            </div>
        </div>

        <form method="get" action="view_teachers_timetable.php" class="search-bar-student">
            <div class="form-group col-md-3" style="margin-bottom:0;">
                <label class="required">Select Instructor / Teacher</label>
                <select name="user_id" class="form-control">
                    <option value="">Select Instructor</option>
                    <?php foreach ($instructors as $t): ?>
                        <option value="<?php echo $t['emp_id']; ?>" <?php echo $user_id == $t['emp_id'] ? 'selected' : ''; ?>><?php echo e($t['full_name']); ?> <?php echo $t['designation'] ? '- ' . e($t['designation']) : ''; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group col-md-3" style="margin-bottom:0;">
                <label>Period Category</label>
                <select name="periodCat_id" class="form-control">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo $period_cat == $cat['id'] ? 'selected' : ''; ?>><?php echo e($cat['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group col-md-1" style="margin-bottom:0;">
                <button type="submit" class="btn btn-primary" style="width:100%; margin-top:27px;"><i class="fa fa-search"></i></button>
            </div>
            <div class="form-group col-md-2" style="margin-bottom:0;">
                <a href="https://www.youtube.com/watch?v=X94bfzfqUYI" target="_blank" class="btn btn-success" style="width:100%; color:#fff; margin-top:27px;"><i class="fa fa-play"></i> Watch Demo</a>
            </div>
        </form>

        <?php if ($user_id > 0 && $instructor): ?>
            <div style="overflow-x:auto; background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:14px;">
                <div style="padding:4px 2px 12px; border-bottom:1px solid #E5E7EB; margin-bottom:14px;">
                    <h4 style="font-size:17px; font-weight:800; margin:0;">
                        <i class="fa fa-user-circle" style="color:#4F46E5;"></i> Timetable of <?php echo e($instructor['full_name']); ?>
                        <?php if ($instructor['designation']): ?><span style="color:#6B7280; font-size:12px; margin-left:6px;">(<?php echo e($instructor['designation']); ?>)</span><?php endif; ?>
                    </h4>
                </div>

                <?php if (count($schedule) === 0): ?>
                    <div style="text-align:center; color:#6B7280; padding:40px;">No timetable entries found<?php echo $period_cat > 0 ? ' for the selected period category' : ''; ?>.</div>
                <?php else: ?>
                    <?php foreach ($days as $day => $short): ?>
                        <?php if (empty($schedule[$day])): ?>
                            <div style="border:1px dashed #E5E7EB; border-radius:10px; padding:8px 12px; margin-bottom:8px; color:#9CA3AF; font-size:13px;">
                                <strong style="color:#111827; margin-right:8px;"><?php echo $day; ?></strong> No periods assigned.
                            </div>
                        <?php else: ?>
                            <div style="margin-bottom:14px;">
                                <div style="font-weight:800; color:#111827; margin-bottom:6px;"><?php echo $day; ?></div>
                                <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(190px,1fr)); gap:8px;">
                                    <?php foreach ($schedule[$day] as $row): ?>
                                        <div class="slot">
                                            <strong><?php echo e($row['period_name'] ?: 'Period ' . $row['period_id']); ?></strong>
                                            <?php if ($row['start_time']): ?>
                                                <small><i class="fa fa-clock-o"></i> <?php echo date('h:i A', strtotime($row['start_time'])); ?><?php echo $row['end_time'] ? ' - ' . date('h:i A', strtotime($row['end_time'])) : ''; ?></small>
                                            <?php endif; ?>
                                            <small><i class="fa fa-graduation-cap"></i> <?php echo e($row['subject_name'] ?? 'Subject'); ?> — <?php echo e($row['class_name']); ?><?php echo $row['section_name'] ? ' (' . e($row['section_name']) . ')' : ''; ?></small>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info" style="margin-top:8px;">Select an instructor/teacher and click search to view their weekly timetable.</div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
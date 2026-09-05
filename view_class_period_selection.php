<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

require_once __DIR__ . '/includes/period_schema.php';

$page_title = 'View Time Table';

$class_id   = (int) ($_GET['class_id'] ?? 0);
$section_id = (int) ($_GET['section_id'] ?? 0);

$classes = [];
$res = db_query("Select Course/Class_id, class_name FROM classes WHERE status=1 ORDER BY class_name");
while ($row = $res->fetch_assoc()) { $classes[] = $row; }

$periods = [];
$res = db_query("SELECT * FROM periods ORDER BY start_time, period_id");
while ($row = $res->fetch_assoc()) { $periods[] = $row; }

$days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];

$class = null;
$section_name = '';
$grid = [];
if ($class_id > 0) {
    $stmt = db_prepare("Select Course/Class_name FROM classes WHERE class_id=?");
    $stmt->bind_param('i', $class_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $class = $res->fetch_assoc();

    if ($section_id > 0) {
        $stmt = db_prepare("SELECT section_name FROM sections WHERE section_id=?");
        $stmt->bind_param('i', $section_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($sn = $res->fetch_assoc()) { $section_name = $sn['section_name']; }
    }

    $sql = "SELECT t.*, s.subject_name, p.period_name, p.start_time, p.end_time,
                   e.first_name teacher_name, sec.section_name
            FROM timetable t
            LEFT JOIN subjects s ON t.subject_id=s.subject_id
            LEFT JOIN periods p ON t.period_id=p.period_id
            LEFT JOIN employees e ON t.teacher_id=e.emp_id
            LEFT JOIN sections sec ON t.section_id=sec.section_id
            WHERE t.class_id=?";
    if ($section_id > 0) { $sql .= " AND IFNULL(t.section_id,0)=?"; }
    $sql .= " ORDER BY t.day";
    $stmt = db_prepare($sql);
    if ($section_id > 0) { $stmt->bind_param('ii', $class_id, $section_id); }
    else { $stmt->bind_param('i', $class_id); }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) { $grid[$row['day']][$row['period_id']] = $row; }
}

include __DIR__ . '/includes/header.php';
?>
<style>
.search-bar-student { display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end; background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:16px; margin-bottom:16px; }
</style>

<div class="main-content">
    <div class="container-fluid">
        <div style="display:flex; align-items:center; justify-content:space-between; padding:14px 4px;">
            <h3 style="font-size:18px; font-weight:800; color:#111827; margin:0;"><i class="fa fa-calendar"></i> View Time Table</h3>
            <div style="display:flex; gap:8px;">
                <button onclick="window.print()" class="btn btn-success" style="color:#fff;"><i class="fa fa-print"></i> Print</button>
                <a href="<?php echo BASE_URL; ?>class_period_selection.php" class="btn btn-primary" style="color:#fff;"><i class="fa fa-plus"></i> Create Time Table</a>
            </div>
        </div>

        <form method="get" action="view_class_period_selection.php" class="search-bar-student">
            <div class="form-group col-md-3" style="margin-bottom:0;">
                <label class="required">Course/Class</label>
                <select name="class_id" id="class_id" class="form-control" onchange="loadSections(this.value)">
                    <option value="">Select Course/Class</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?php echo $c['class_id']; ?>" <?php echo $class_id == $c['class_id'] ? 'selected' : ''; ?>><?php echo e($c['class_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group col-md-3" style="margin-bottom:0;">
                <label>Section</label>
                <select name="section_id" id="txt_section" class="form-control">
                    <option value="">Select Section</option>
                </select>
            </div>
            <div class="form-group col-md-2" style="margin-bottom:0;">
                <button type="submit" class="btn btn-primary" style="width:100%; margin-top:27px;"><i class="fa fa-search"></i> Search</button>
            </div>
        </form>

        <?php if ($class_id > 0): ?>
            <div style="overflow-x:auto; background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:4px;">
                <div style="padding:12px 14px; font-weight:800; color:#111827; font-size:15px;">
                    <?php echo e($class['class_name'] ?? ''); ?> <?php echo $section_name ? '(' . e($section_name) . ')' : '(All Sections)'; ?> — Class Time Table
                </div>
                <?php if (count($periods) === 0): ?>
                    <div style="text-align:center; color:#6B7280; padding:40px;">No periods configured yet. Add periods first to build the grid.</div>
                <?php else: ?>
                <table class="table table-bordered" style="width:100%; background:#fff; margin-bottom:0; font-size:12.5px;">
                    <thead>
                        <tr>
                            <th>Day / Period</th>
                            <?php foreach ($periods as $p): ?>
                                <th><?php echo e($p['period_name']); ?><br><small style="color:#6B7280; font-weight:400;"><?php echo $p['start_time'] ? date('h:i A', strtotime($p['start_time'])) : ''; ?></small></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($days as $d): ?>
                            <tr>
                                <th style="background:#F9FAFB;"><?php echo $d; ?></th>
                                <?php foreach ($periods as $p): $cell = $grid[$d][$p['period_id']] ?? null; ?>
                                    <td style="text-align:center; color:#6B7280;">
                                        <?php if ($cell): ?>
                                            <strong style="color:#111827; display:block;"><?php echo e($cell['subject_name'] ?: '-'); ?></strong>
                                            <small><?php echo e($cell['teacher_name'] ?: ''); ?></small>
                                        <?php else: ?>—<?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info" style="margin-top:8px;">Select a class (and optional section) to view its timetable.</div>
        <?php endif; ?>
    </div>
</div>

<script>
function loadSections(cid){
    var sel = document.getElementById('txt_section');
    sel.innerHTML = '<option value="">Loading...</option>';
    fetch('<?php echo BASE_URL; ?>get_sections.php?class_id=' + cid)
        .then(function(r){ return r.json(); })
        .then(function(data){
            sel.innerHTML = '<option value="">All Sections</option>';
            data.forEach(function(s){
                var o = document.createElement('option');
                o.value = s.section_id; o.textContent = s.section_name;
                sel.appendChild(o);
            });
            <?php if ($section_id > 0): ?> sel.value = '<?php echo $section_id; ?>'; <?php endif; ?>
        });
}
window.addEventListener('DOMContentLoaded', function(){
    var cls = document.getElementById('class_id');
    if (cls && cls.value) { loadSections(cls.value); }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
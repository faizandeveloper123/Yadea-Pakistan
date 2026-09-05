<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

require_once __DIR__ . '/includes/period_schema.php';

$page_title = 'Create Time Table';

$message = '';
$error = '';

$classes = [];
$res = db_query("Select class_id, class_name FROM classes WHERE status=1 ORDER BY class_name");
while ($row = $res->fetch_assoc()) { $classes[] = $row; }

$periods = [];
$res = db_query("SELECT * FROM periods ORDER BY start_time, period_id");
while ($row = $res->fetch_assoc()) { $periods[] = $row; }

$days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];

$sel_class   = (int) ($_GET['class_id'] ?? 0);
$sel_section = (int) ($_GET['section_id'] ?? 0);

// Save period schedule entries for a class + section
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'SaveClassTimetable') {
    $class_id   = (int) ($_POST['class_id'] ?? 0);
    $section_raw = $_POST['section_id'] ?? '';
    $section_id = ($section_raw === '' || $section_raw === null) ? 0 : (int) $section_raw;

    if ($class_id <= 0 || $section_id <= 0) {
        $error = 'Please select both class and section.';
    } else {
        $items = $_POST['tt'] ?? []; // [day][period_id] => subject_id
        $sdel = db_prepare("DELETE FROM timetable WHERE class_id=? AND IFNULL(section_id,0)=?");
        $sdel->bind_param('ii', $class_id, $section_id);
        $sdel->execute();

        $inserted = 0;
        $tstmt = db_prepare("INSERT INTO timetable (class_id, section_id, day, period_id, subject_id) VALUES (?, ?, ?, ?, ?)");
        foreach ($items as $day => $periods_arr) {
            if (!in_array($day, $days)) continue;
            foreach ($periods_arr as $pid => $sub_id) {
                $pid = (int) $pid; $sub_id = (int) $sub_id;
                if ($pid <= 0 || $sub_id <= 0) continue;
                $tstmt->bind_param('iisii', $class_id, $section_id, $day, $pid, $sub_id);
                $tstmt->execute();
                $inserted++;
            }
        }
        $sel_class = $class_id; $sel_section = $section_id;
        $message = "Timetable saved with $inserted entries for the selected class + section!";
    }
}

$subjects = [];
$grid = [];
if ($sel_class > 0 && $sel_section > 0) {
    $stmt = db_prepare("SELECT * FROM subjects WHERE class_id=? ORDER BY subject_name");
    $stmt->bind_param('i', $sel_class);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) { $subjects[] = $row; }

    $stmt = db_prepare("SELECT * FROM timetable WHERE class_id=? AND IFNULL(section_id,0)=?");
    $stmt->bind_param('ii', $sel_class, $sel_section);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) { $grid[$row['day']][$row['period_id']] = $row['subject_id']; }
}

include __DIR__ . '/includes/header.php';
?>
<style>
.search-bar-student { display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end; background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:16px; margin-bottom:16px; }
</style>

<div class="main-content">
    <div class="container-fluid">
        <?php if ($message): ?><div class="alert alert-success"><?php echo e($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>

        <div style="display:flex; align-items:center; justify-content:space-between; padding:14px 4px;">
            <h3 style="font-size:18px; font-weight:800; color:#111827; margin:0;"><i class="fa fa-calendar"></i> Create Time Table</h3>
            <a href="<?php echo BASE_URL; ?>view_class_period_selection.php" class="btn btn-primary" style="color:#fff;"><i class="fa fa-eye"></i> View Timetable</a>
        </div>

        <form method="get" action="class_period_selection.php" class="search-bar-student">
            <div class="form-group col-md-3" style="margin-bottom:0;">
                <label class="required">Course/Class</label>
                <select name="class_id" id="class_id" class="form-control" onchange="loadSections(this.value)">
                    <option value="">Select Course/Class</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?php echo $c['class_id']; ?>" <?php echo $sel_class == $c['class_id'] ? 'selected' : ''; ?>><?php echo e($c['class_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group col-md-3" style="margin-bottom:0;">
                <label class="required">Section</label>
                <select name="section_id" id="txt_section" class="form-control">
                    <option value="">Select Section</option>
                </select>
            </div>
            <div class="form-group col-md-2" style="margin-bottom:0;">
                <button type="submit" class="btn btn-primary" style="width:100%; margin-top:27px;"><i class="fa fa-search"></i> Search</button>
            </div>
            <div class="form-group col-md-2" style="margin-bottom:0;">
                <a href="https://www.youtube.com/watch?v=X94bfzfqUYI" target="_blank" class="btn btn-success" style="width:100%; color:#fff; margin-top:27px;"><i class="fa fa-play"></i> Watch Demo</a>
            </div>
        </form>

        <?php if ($sel_class > 0 && $sel_section > 0): ?>
            <form method="post" action="class_period_selection.php" style="overflow-x:auto; background:#fff; border:1px solid #E5E7EB; border-radius:14px;">
                <input type="hidden" name="action" value="SaveClassTimetable">
                <input type="hidden" name="class_id" value="<?php echo $sel_class; ?>">
                <input type="hidden" name="section_id" value="<?php echo $sel_section; ?>">
                <div style="padding:12px 14px; font-weight:800; color:#111827; font-size:15px; border-bottom:1px solid #E5E7EB;">
                    <?php
                        $cn = '';
                        foreach ($classes as $c) { if ($c['class_id'] == $sel_class) { $cn = $c['class_name']; break; } }
                        $sn = '';
                        $st = db_prepare("SELECT section_name FROM sections WHERE section_id=?");
                        $st->bind_param('i', $sel_section); $st->execute();
                        $rr = $st->get_result();
                        if ($snr = $rr->fetch_assoc()) { $sn = $snr['section_name']; }
                        echo e($cn) . ($sn ? ' (' . e($sn) . ')' : '');
                    ?> — Assign Subjects Day-Wise
                </div>
                <table class="table table-bordered" style="width:100%; background:#fff; margin-bottom:0; font-size:12.5px;">
                    <thead>
                        <tr>
                            <th style="min-width:90px;">Days</th>
                            <?php foreach ($periods as $p): ?>
                                <th style="min-width:110px;"><?php echo e($p['period_name']); ?><br><small style="color:#6B7280; font-weight:400;"><?php echo $p['start_time'] ? date('h:i A', strtotime($p['start_time'])) : ''; ?></small></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($subjects) === 0): ?>
                            <tr><td colspan="<?php echo count($periods) + 1; ?>" style="text-align:center; color:#6B7280; padding:30px;">No subjects configured for this class yet. Add subjects first.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($days as $day): ?>
                            <tr>
                                <td><strong><?php echo $day; ?></strong></td>
                                <?php foreach ($periods as $p): ?>
                                    <td>
                                        <select class="form-control" name="tt[<?php echo $day; ?>][<?php echo $p['period_id']; ?>]" style="padding:4px; font-size:12px;">
                                            <option value="0">—</option>
                                            <?php foreach ($subjects as $sub): ?>
                                                <option value="<?php echo $sub['subject_id']; ?>" <?php echo ($grid[$day][$p['period_id']] ?? 0) == $sub['subject_id'] ? 'selected' : ''; ?>><?php echo e($sub['subject_name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div style="padding:14px; text-align:right;">
                    <button type="submit" name="submit" class="btn btn-success" style="padding:10px 34px; color:#fff;"><i class="fa fa-save"></i> Submit</button>
                </div>
            </form>
        <?php else: ?>
            <div class="alert alert-info" style="margin-top:8px;">Select a class and section, then click Search to fill the period schedule below.</div>
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
            sel.innerHTML = '<option value="">Select Section</option>';
            data.forEach(function(s){
                var o = document.createElement('option');
                o.value = s.section_id; o.textContent = s.section_name;
                sel.appendChild(o);
            });
            <?php if ($sel_section > 0): ?> sel.value = '<?php echo $sel_section; ?>'; <?php endif; ?>
        });
}
window.addEventListener('DOMContentLoaded', function(){
    var cls = document.getElementById('class_id');
    if (cls && cls.value) { loadSections(cls.value); }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
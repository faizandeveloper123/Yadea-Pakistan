<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

require_once __DIR__ . '/includes/period_schema.php';

$page_title = 'Assign Periods to Classes';

$message = '';
$error = '';

$classes = [];
$res = db_query("Select Course/Class_id, class_name FROM classes WHERE status=1 ORDER BY class_name");
while ($row = $res->fetch_assoc()) { $classes[] = $row; }

$categories = [];
$res = db_query("SELECT * FROM period_categories ORDER BY name");
while ($row = $res->fetch_assoc()) { $categories[] = $row; }

$periods = [];
$res = db_query("SELECT * FROM periods ORDER BY start_time, period_id");
while ($row = $res->fetch_assoc()) { $periods[] = $row; }

$sel_class = (int) ($_GET['class_id'] ?? 0);
$sel_cat   = (int) ($_GET['periodCat'] ?? ($_POST['periodCat'] ?? 0));

$subjects = [];
if ($sel_class > 0) {
    $stmt = db_prepare("SELECT * FROM subjects WHERE class_id=? ORDER BY subject_name");
    $stmt->bind_param('i', $sel_class);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) { $subjects[] = $row; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'SavePeriod') {
        $name = trim($_POST['period_name'] ?? '');
        $start = trim($_POST['start_time'] ?? '');
        $end = trim($_POST['end_time'] ?? '');
        if ($name === '') {
            $error = 'Period name is required.';
        } else {
            $st2 = db_prepare("INSERT INTO periods (period_name, start_time, end_time) VALUES (?, ?, ?)");
            $st2->bind_param('sss', $name, $start, $end);
            $st2->execute();
            $message = 'Period added successfully!';
        }
    }

    if ($action === 'DeletePeriod') {
        $pid = (int) ($_POST['period_id'] ?? 0);
        if ($pid > 0) {
            $st2 = db_prepare("DELETE FROM periods WHERE period_id=?");
            $st2->bind_param('i', $pid);
            $st2->execute();
            $message = 'Period deleted.';
        }
    }

    if ($action === 'SaveTimetable') {
        $class_id = (int) ($_POST['class_id'] ?? 0);
        if ($class_id <= 0) {
            $error = 'Please select a class.';
        } else {
            $items = $_POST['tt'] ?? []; // [day][period_id] => subject_id
            $st2 = db_prepare("DELETE FROM timetable WHERE class_id=?");
            $st2->bind_param('i', $class_id);
            $st2->execute();
            $days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
            $inserted = 0;
            $tstmt = db_prepare("INSERT INTO timetable (class_id, day, period_id, subject_id) VALUES (?, ?, ?, ?)");
            foreach ($items as $day => $periods_arr) {
                if (!in_array($day, $days)) continue;
                foreach ($periods_arr as $pid => $sub_id) {
                    $pid = (int) $pid; $sub_id = (int) $sub_id;
                    if ($pid <= 0 || $sub_id <= 0) continue;
                    $tstmt->bind_param('isii', $class_id, $day, $pid, $sub_id);
                    $tstmt->execute();
                    $inserted++;
                }
            }
            $message = "Timetable saved with $inserted entries!";
        }
    }

    // Bulk assign selected class/section rows to the searched period category
    if ($action === 'ClassPeriods') {
        $periodCatId = (int) ($_POST['periodCatId'] ?? 0);
        $assigned = $_POST['assign'] ?? [];
        $classMap  = $_POST['assign_class'] ?? [];
        $secMap    = $_POST['assign_section'] ?? [];
        if ($periodCatId <= 0) {
            $error = 'Please select a period category first.';
        } elseif (count($assigned) === 0) {
            $error = 'No classes selected.';
        } else {
            $count = 0;
            foreach ($assigned as $idx) {
                $cid = (int) ($classMap[$idx] ?? 0);
                $sid_raw = $secMap[$idx] ?? '';
                $sid = ($sid_raw === '' || $sid_raw === null) ? null : (int) $sid_raw;
                if ($cid <= 0) continue;
                if ($sid === null) {
                    $d = db_prepare("DELETE FROM class_periods WHERE class_id=? AND section_id IS NULL");
                    $d->bind_param('i', $cid);
                    $d->execute();
                    $st2 = db_prepare("INSERT INTO class_periods (class_id, section_id, period_cat_id) VALUES (?, NULL, ?)");
                    $st2->bind_param('ii', $cid, $periodCatId);
                    $st2->execute();
                } else {
                    $st2 = db_prepare("INSERT INTO class_periods (class_id, section_id, period_cat_id) VALUES (?, ?, ?)
                        ON DUPLICATE KEY UPDATE period_cat_id=VALUES(period_cat_id)");
                    $st2->bind_param('iii', $cid, $sid, $periodCatId);
                    $st2->execute();
                }
                $count++;
            }
            $message = "$count class(es) assigned to selected period category!";
        }
    }

    // Inline edit modal (class + section + period category)
    if ($action === 'EditClassPeriod') {
        $class_id = (int) ($_POST['class_id'] ?? 0);
        $section_raw = $_POST['section'] ?? '';
        $periodCatId = (int) ($_POST['periodCatId'] ?? 0);
        if ($class_id <= 0) {
            $error = 'Please choose a valid class.';
        } else {
            $sid = ($section_raw === '' || $section_raw === null) ? null : (int) $section_raw;
            if ($periodCatId > 0) {
                if ($sid === null) {
                    $d = db_prepare("DELETE FROM class_periods WHERE class_id=? AND section_id IS NULL");
                    $d->bind_param('i', $class_id);
                    $d->execute();
                    $st2 = db_prepare("INSERT INTO class_periods (class_id, section_id, period_cat_id) VALUES (?, NULL, ?)");
                    $st2->bind_param('ii', $class_id, $periodCatId);
                    $st2->execute();
                } else {
                    $st2 = db_prepare("INSERT INTO class_periods (class_id, section_id, period_cat_id) VALUES (?, ?, ?)
                        ON DUPLICATE KEY UPDATE period_cat_id=VALUES(period_cat_id)");
                    $st2->bind_param('iii', $class_id, $sid, $periodCatId);
                    $st2->execute();
                }
                $message = 'Class period updated successfully!';
            } else {
                $error = 'Please select a period category.';
            }
        }
    }

    if ($action === 'DeleteClassPeriod') {
        $cpid = (int) ($_POST['class_period_id'] ?? 0);
        if ($cpid > 0) {
            $st2 = db_prepare("DELETE FROM class_periods WHERE id=?");
            $st2->bind_param('i', $cpid);
            $st2->execute();
            $message = 'Class period assignment deleted.';
        }
    }
}

// Class list rows: every class/section combo (+ classes without sections) with assigned category
$cp_rows = [];
$res = db_query("SELECT s.section_id, s.section_name, s.class_id, c.class_name,
                        cp.id AS cp_id, cp.period_cat_id, pc.name AS cat_name
                 FROM sections s
                 JOIN classes c ON s.class_id=c.class_id AND c.status=1
                 LEFT JOIN class_periods cp ON cp.class_id=s.class_id AND cp.section_id=s.section_id
                 LEFT JOIN period_categories pc ON pc.id=cp.period_cat_id
                 ORDER BY c.class_name, s.section_name");
while ($row = $res->fetch_assoc()) { $cp_rows[] = $row; }

$res = db_query("SELECT c.class_id, c.class_name,
                        NULL AS section_id, NULL AS section_name,
                        cp.id AS cp_id, cp.period_cat_id, pc.name AS cat_name
                 FROM classes c
                 LEFT JOIN class_periods cp ON cp.class_id=c.class_id AND cp.section_id IS NULL
                 LEFT JOIN period_categories pc ON pc.id=cp.period_cat_id
                 WHERE c.status=1 AND NOT EXISTS (SELECT 1 FROM sections s2 WHERE s2.class_id=c.class_id)
                 ORDER BY c.class_name");
while ($row = $res->fetch_assoc()) { $cp_rows[] = $row; }

// Apply category filter
$filtered_rows = $cp_rows;
if ($sel_cat > 0) {
    $filtered_rows = array_values(array_filter($cp_rows, function ($r) use ($sel_cat) {
        return (int) $r['period_cat_id'] === $sel_cat;
    }));
}

// Build grid for selected class
$grid = [];
if ($sel_class > 0) {
    $stmt = db_prepare("SELECT * FROM timetable WHERE class_id=?");
    $stmt->bind_param('i', $sel_class);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) { $grid[$row['day']][$row['period_id']] = $row['subject_id']; }
}

$days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];

include __DIR__ . '/includes/header.php';
?>
<style>
.search-bar-student { display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end; background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:16px; margin-bottom:16px; }
.cp-check { height:18px; width:18px; }
</style>

<div class="main-content">
    <div class="container-fluid">
        <?php if ($message): ?><div class="alert alert-success"><?php echo e($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>

        <div style="display:flex; align-items:center; justify-content:space-between; padding:14px 4px;">
            <h3 style="font-size:18px; font-weight:800; color:#111827; margin:0;"><i class="fa fa-calendar-check-o"></i> Assign Periods to Classes</h3>
            <div style="display:flex; gap:8px;">
                <a href="<?php echo BASE_URL; ?>view_teachers_timetable.php" class="btn btn-primary" style="color:#fff;"><i class="fa fa-eye"></i> View Timetable</a>
                <a href="<?php echo BASE_URL; ?>period_categories.php" class="btn btn-default"><i class="fa fa-clock-o"></i> Period Categories</a>
            </div>
        </div>

        <form method="get" action="class_period.php" class="search-bar-student">
            <div class="form-group col-md-3" style="margin-bottom:0;">
                <label>Course/Class (for timetable grid)</label>
                <select name="class_id" class="form-control">
                    <option value="">Select Course/Class</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?php echo $c['class_id']; ?>" <?php echo $sel_class == $c['class_id'] ? 'selected' : ''; ?>><?php echo e($c['class_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group col-md-3" style="margin-bottom:0;">
                <label>Period Category</label>
                <select name="periodCat" class="form-control">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo $sel_cat == $cat['id'] ? 'selected' : ''; ?>><?php echo e($cat['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group col-md-2" style="margin-bottom:0;">
                <button type="submit" class="btn btn-primary" style="width:100%;"><i class="fa fa-search"></i> Search Categories</button>
            </div>
        </form>

        <form method="post" action="class_period.php" onsubmit="return checkAssign();" style="background:#fff; border:1px solid #E5E7EB; border-radius:14px; overflow-x:auto; margin-bottom:16px;">
            <input type="hidden" name="action" value="ClassPeriods">
            <input type="hidden" name="periodCatId" value="<?php echo $sel_cat; ?>">
            <input type="hidden" name="periodCat" value="<?php echo $sel_cat; ?>">
            <div style="padding:12px 14px; font-weight:800; color:#111827; font-size:15px; border-bottom:1px solid #E5E7EB; display:flex; justify-content:space-between; align-items:center;">
                <span><i class="fa fa-list"></i> Classes (<?php echo count($filtered_rows); ?> records)</span>
                <button type="submit" class="btn btn-success btn-sm" style="color:#fff;"><i class="fa fa-check-circle"></i> Assign Selected to Category</button>
            </div>
            <table class="table table-bordered" style="width:100%; background:#fff; margin-bottom:0; font-size:13px;">
                <thead>
                    <tr>
                        <th style="width:60px; text-align:center;">Check<br><input type="checkbox" id="checkAll" class="cp-check"></th>
                        <th>Course/Course/Class Name</th>
                        <th>Section</th>
                        <th>Period Category</th>
                        <th style="width:150px; text-align:center;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($filtered_rows) === 0): ?>
                        <tr><td colspan="5" style="text-align:center; color:#6B7280; padding:30px;">
                            <?php echo $sel_cat > 0 ? 'No classes assigned to this category yet. Search another category to manage assignments.' : 'No classes available. Please add classes + sections first.'; ?>
                        </td></tr>
                    <?php endif; ?>
                    <?php foreach ($filtered_rows as $i => $r): ?>
                        <tr>
                            <td style="text-align:center;">
                                <input type="checkbox" name="assign[]" value="<?php echo $i; ?>" class="cp-check cp-row">
                                <input type="hidden" name="assign_class[<?php echo $i; ?>]" value="<?php echo $r['class_id']; ?>">
                                <input type="hidden" name="assign_section[<?php echo $i; ?>]" value="<?php echo $r['section_id'] === null ? '' : $r['section_id']; ?>">
                            </td>
                            <td><strong>#<?php echo $r['class_id']; ?> — <?php echo e($r['class_name']); ?></strong></td>
                            <td><?php echo $r['section_id'] === null ? '—' : e($r['section_name']); ?></td>
                            <td>
                                <?php if ($r['cat_name']): ?>
                                    <span style="background:#FFF7E0; color:#B45309; padding:4px 10px; border-radius:8px; font-weight:700; font-size:12px; display:inline-block;"><?php echo e($r['cat_name']); ?></span>
                                <?php else: ?>
                                    <span style="color:#D1D5DB;">Not assigned</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:center; white-space:nowrap;">
                                <button type="button" class="btn btn-primary btn-xs edit-cp"
                                    data-class="<?php echo $r['class_id']; ?>"
                                    data-section="<?php echo $r['section_id'] === null ? '' : $r['section_id']; ?>"
                                    data-sectionname="<?php echo e($r['section_name'] ?? ''); ?>"
                                    data-cat="<?php echo $r['period_cat_id'] ? (int)$r['period_cat_id'] : ($sel_cat > 0 ? $sel_cat : ''); ?>"
                                    data-classname="<?php echo e($r['class_name']); ?>"><i class="fa fa-pencil"></i></button>
                                <?php if ($r['cp_id']): ?>
                                    <form method="post" action="class_period.php" style="display:inline;" onsubmit="return confirm('Delete this period assignment?');">
                                        <input type="hidden" name="action" value="DeleteClassPeriod">
                                        <input type="hidden" name="class_period_id" value="<?php echo (int)$r['cp_id']; ?>">
                                        <button class="btn btn-danger btn-xs"><i class="fa fa-trash"></i></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </form>

        <div class="row">
            <div class="col-md-4">
                <form method="post" action="class_period.php" style="background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:16px;">
                    <h4 style="font-size:15px; font-weight:800; margin:0 0 14px;">Add Period</h4>
                    <input type="hidden" name="action" value="SavePeriod">
                    <input type="hidden" name="class_id" value="<?php echo $sel_class; ?>">
                    <div class="form-group">
                        <label class="required">Period Name</label>
                        <input type="text" name="period_name" class="form-control" placeholder="e.g. Period 1" required>
                    </div>
                    <div class="form-group">
                        <label>Start Time</label>
                        <input type="time" name="start_time" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>End Time</label>
                        <input type="time" name="end_time" class="form-control">
                    </div>
                    <button type="submit" class="btn btn-success" style="width:100%;">Add Period</button>
                </form>

                <div style="overflow-x:auto; background:#fff; border:1px solid #E5E7EB; border-radius:14px; margin-top:14px;">
                    <table class="table table-striped table-bordered" style="width:100%; background:#fff; margin-bottom:0;">
                        <thead><tr><th>Period</th><th>Time</th><th></th></tr></thead>
                        <tbody>
                            <?php if (count($periods) === 0): ?><tr><td colspan="3" style="text-align:center; color:#6B7280;">No periods yet.</td></tr><?php endif; ?>
                            <?php foreach ($periods as $p): ?>
                                <tr>
                                    <td><strong><?php echo e($p['period_name']); ?></strong></td>
                                    <td><?php echo $p['start_time'] ? date('h:i A', strtotime($p['start_time'])) : '-'; ?> - <?php echo $p['end_time'] ? date('h:i A', strtotime($p['end_time'])) : '-'; ?></td>
                                    <td>
                                        <form method="post" action="class_period.php" style="display:inline;" onsubmit="return confirm('Delete period?');">
                                            <input type="hidden" name="action" value="DeletePeriod">
                                            <input type="hidden" name="period_id" value="<?php echo $p['period_id']; ?>">
                                            <button class="btn btn-danger btn-xs"><i class="fa fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="col-md-8">
                <?php if ($sel_class > 0): ?>
                <form method="post" action="class_period.php" style="overflow-x:auto; background:#fff; border:1px solid #E5E7EB; border-radius:14px;">
                    <input type="hidden" name="action" value="SaveTimetable">
                    <input type="hidden" name="class_id" value="<?php echo $sel_class; ?>">
                    <table class="table table-bordered" style="width:100%; background:#fff; margin-bottom:0; font-size:12.5px;">
                        <thead>
                            <tr>
                                <th style="min-width:90px;">Day</th>
                                <?php foreach ($periods as $p): ?>
                                    <th style="min-width:110px;"><?php echo e($p['period_name']); ?><br><small style="color:#6B7280; font-weight:400;"><?php echo $p['start_time'] ? date('h:iA', strtotime($p['start_time'])) : ''; ?></small></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($days as $day): ?>
                                <tr>
                                    <td><strong><?php echo $day; ?></strong></td>
                                    <?php foreach ($periods as $p): ?>
                                        <td>
                                            <Select Course/Class="form-control" name="tt[<?php echo $day; ?>][<?php echo $p['period_id']; ?>]" style="padding:4px; font-size:12px;">
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
                        <button type="submit" class="btn btn-success" style="padding:10px 30px;"><i class="fa fa-save"></i> Save Timetable</button>
                    </div>
                </form>
                <?php else: ?>
                    <div class="alert alert-info" style="margin-top:0;">Select a class to build / edit its timetable grid below.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Edit Class Period Modal -->
<div class="modal fade" id="editClassPeriodModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius:14px;">
            <div class="modal-header" style="border-bottom:1px solid #E5E7EB; padding:16px 20px;">
                <h4 class="modal-title" style="font-weight:800; font-size:16px;"><i class="fa fa-pencil"></i> Edit Class Period</h4>
            </div>
            <form method="post" action="class_period.php">
                <div class="modal-body" style="padding:20px;">
                    <input type="hidden" name="action" value="EditClassPeriod">
                    <input type="hidden" name="periodCat" value="<?php echo $sel_cat; ?>">
                    <div class="form-group">
                        <label class="required">Course/Course/Class Name</label>
                        <select name="class_id" id="ecpClass" class="form-control" required>
                            <option value="">Select Course/Class</option>
                            <?php foreach ($classes as $c): ?>
                                <option value="<?php echo $c['class_id']; ?>"><?php echo e($c['class_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Section Name</label>
                        <select name="section" id="ecpSection" class="form-control">
                            <option value="">—</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="required">Period Category</label>
                        <select name="periodCatId" id="ecpCat" class="form-control" required>
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>"><?php echo e($cat['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer" style="border-top:1px solid #E5E7EB;">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function fillSections(classId, selId, keepValue){
    var sel = document.getElementById('ecpSection');
    sel.innerHTML = '<option value="">Loading...</option>';
    fetch('<?php echo BASE_URL; ?>get_sections.php?class_id=' + classId)
        .then(function(r){ return r.json(); })
        .then(function(data){
            sel.innerHTML = '<option value="">—</option>';
            data.forEach(function(s){
                var o = document.createElement('option');
                o.value = s.section_id; o.textContent = s.section_name;
                sel.appendChild(o);
            });
            if (selId !== '' && selId !== null) { sel.value = String(selId); }
        });
}

document.querySelectorAll('.edit-cp').forEach(function(btn){
    btn.addEventListener('click', function(){
        var cid = this.dataset.class;
        var sec = this.dataset.section || '';
        var cat = this.dataset.cat || '';
        document.getElementById('ecpClass').value = cid;
        document.getElementById('ecpCat').value = cat;
        fillSections(cid, sec);
        jQuery('#editClassPeriodModal').modal('show');
    });
});

document.getElementById('ecpClass').addEventListener('change', function(){
    fillSections(this.value, '');
});

var checkAll = document.getElementById('checkAll');
if (checkAll) {
    checkAll.addEventListener('change', function(){
        document.querySelectorAll('.cp-row').forEach(function(c){ c.checked = checkAll.checked; });
    });
}

function checkAssign(){
    var checked = document.querySelectorAll('.cp-row:checked');
    if (checked.length === 0) { alert('Please select at least one class to assign.'); return false; }
    return true;
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
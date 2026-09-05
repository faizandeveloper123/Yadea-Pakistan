<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Students Cards';

$sel_class = (int) ($_GET['class_id'] ?? 0);
$auto_print = (int) ($_GET['print'] ?? 0);

$classes = [];
$res = db_query("Select Course/Class_id, class_name FROM classes WHERE status=1 ORDER BY class_name");
while ($row = $res->fetch_assoc()) { $classes[] = $row; }

$students = [];
if ($sel_class > 0) {
    $res = db_query("SELECT s.*, c.class_name, sec.section_name FROM students s
                     LEFT JOIN classes c ON s.class_id=c.class_id
                     LEFT JOIN sections sec ON s.section_id=sec.section_id
                     WHERE s.class_id=$sel_class AND s.status=1 ORDER BY s.first_name");
    while ($row = $res->fetch_assoc()) { $students[] = $row; }
}

$school_name = get_setting('school_name') ?: 'HIIFI';

include __DIR__ . '/includes/header.php';
?>
<style>
.search-bar-student { display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end; background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:16px; margin-bottom:16px; }
.card-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:14px; }
.id-card { background:#fff; border:1px solid #E5E7EB; border-radius:14px; overflow:hidden; break-inside: avoid; box-shadow:0 2px 8px rgba(0,0,0,.04); }
.id-card .band { background:linear-gradient(90deg,#FF7A1B,#ffa35c); color:#fff; padding:10px 14px; font-weight:800; display:flex; justify-content:space-between; align-items:center; }
.id-card .body { padding:14px; }
.id-card .avatar-big { width:64px; height:64px; border-radius:999px; background:#FFF3E9; border:2px solid #FF7A1B; color:#FF7A1B; display:flex; align-items:center; justify-content:center; font-size:22px; font-weight:800; }
.id-card table { width:100%; font-size:12px; }
.id-card td { padding:3px 0; }
.id-card .lbl { color:#6B7280; width:74px; }
@media print { .no-print{display:none!important;} .card-grid{grid-template-columns:repeat(3,1fr);} }
@media (max-width:900px){ .card-grid{grid-template-columns:1fr;} }
</style>

<div class="main-content">
    <div class="container-fluid">
        <div style="display:flex; align-items:center; justify-content:space-between; padding:14px 4px;">
            <h3 style="font-size:18px; font-weight:800; color:#111827; margin:0;"><i class="fa fa-id-card"></i> Students Cards</h3>
        </div>

        <form method="get" action="students_card.php" class="search-bar-student no-print">
            <div class="form-group col-md-4" style="margin-bottom:0;">
                <label>Course/Class</label>
                <select name="class_id" class="form-control" required onchange="this.form.submit()">
                    <option value="">Select Course/Class</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?php echo $c['class_id']; ?>" <?php echo $sel_class == $c['class_id'] ? 'selected' : ''; ?>><?php echo e($c['class_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>

        <?php if ($sel_class > 0): ?>
            <div class="no-print" style="margin-bottom:14px;">
                <button onclick="window.print()" class="btn btn-success"><i class="fa fa-print"></i> Print All Cards</button>
                <span style="color:#6B7280; margin-left:10px; font-size:13px;"><?php echo count($students); ?> students</span>
            </div>
            <div class="card-grid">
                <?php if (count($students) === 0): ?><div style="text-align:center; color:#6B7280; padding:40px;">No students in this class.</div><?php endif; ?>
                <?php foreach ($students as $st): ?>
                    <div class="id-card">
                        <div class="band">
                            <span><?php echo e($school_name); ?></span>
                            <span style="font-size:11px; font-weight:600;">STUDENT ID</span>
                        </div>
                        <div class="body">
                            <div style="display:flex; gap:12px; align-items:center; margin-bottom:10px;">
                            <?php if (!empty($st['photo'])): ?>
                                <img src="<?php echo BASE_URL; ?>uploads/students/<?php echo e($st['photo']); ?>" alt="" style="width:64px;height:64px;border-radius:999px;object-fit:cover;border:2px solid #FF7A1B;">
                            <?php else: ?>
                                <div class="avatar-big"><?php echo strtoupper(substr($st['first_name'], 0, 1)); ?></div>
                            <?php endif; ?>
                            <div>
                                <div style="font-weight:800; font-size:14px; color:#111827;"><?php echo e($st['first_name']); ?></div>
                                <div style="font-size:11.5px; color:#6B7280;"><?php echo e($st['father_name'] ?? ''); ?></div>
                            </div>
                        </div>
                        <table>
                            <tr><td class="lbl">GR No</td><td><strong><?php echo e($st['gr_no'] ?? ($st['roll_no'] ?? $st['student_id'])); ?></strong></td></tr>
                            <tr><td class="lbl">Course/Class</td><td><?php echo e($st['class_name'] ?? ''); ?></td></tr>
                            <tr><td class="lbl">Section</td><td><?php echo e($st['section_name'] ?? $st['section_id']); ?></td></tr>
                            <tr><td class="lbl">DOB</td><td><?php echo $st['dob'] ? date('d M Y', strtotime($st['dob'])) : ''; ?></td></tr>
                            <tr><td class="lbl">Phone</td><td><?php echo e($st['phone'] ?? ''); ?></td></tr>
                            <tr><td class="lbl">Session</td><td><?php echo e($st['session'] ?? get_setting('session_year') ?? ''); ?></td></tr>
                        </table>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($auto_print === 1): ?>
<script>
window.onload = function(){ setTimeout(function(){ window.print(); }, 400); };
</script>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
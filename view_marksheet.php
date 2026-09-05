<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'View Marksheet';

$sel_exam = (int) ($_GET['exam_id'] ?? 0);
$sel_student = (int) ($_GET['student_id'] ?? 0);

$exams = [];
$res = db_query("SELECT e.*, c.class_name FROM exams e LEFT JOIN classes c ON e.class_id=c.class_id ORDER BY e.exam_id DESC");
while ($row = $res->fetch_assoc()) { $exams[] = $row; }

$exams_by_id = [];
foreach ($exams as $x) { $exams_by_id[$x['exam_id']] = $x; }
$sel_exam_info = $exams_by_id[$sel_exam] ?? null;

$students = [];
if ($sel_exam_info) {
    $cls = $sel_exam_info['class_id'];
    $res = db_query("SELECT * FROM students WHERE class_id=$cls AND status=1 ORDER BY first_name");
    while ($row = $res->fetch_assoc()) { $students[] = $row; }
}

$rows = [];
if ($sel_exam > 0 && $sel_student > 0) {
    $res = db_query("SELECT m.*, s.subject_name FROM marks m LEFT JOIN subjects s ON m.subject_id=s.subject_id WHERE m.exam_id=$sel_exam AND m.student_id=$sel_student ORDER BY m.subject_id");
    while ($row = $res->fetch_assoc()) { $rows[] = $row; }
}

$total_obtained = 0;
$total_max = 0;
foreach ($rows as $r) { $total_obtained += (float) $r['obtained_marks']; $total_max += (float) $r['total_marks']; }
$pct = $total_max > 0 ? round(($total_obtained / $total_max) * 100, 1) : 0;
$grade = $pct >= 90 ? 'A+' : ($pct >= 80 ? 'A' : ($pct >= 70 ? 'B' : ($pct >= 60 ? 'C' : ($pct >= 50 ? 'D' : 'F'))));

$sel_student_info = null;
if ($sel_student > 0) { $sel_student_info = db_query("SELECT * FROM students WHERE student_id=$sel_student")->fetch_assoc(); }

include __DIR__ . '/includes/header.php';
?>
<style>
.search-bar-student { display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end; background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:16px; margin-bottom:16px; }
</style>

<div class="main-content">
    <div class="container-fluid">
        <div style="display:flex; align-items:center; justify-content:space-between; padding:14px 4px;">
            <h3 style="font-size:18px; font-weight:800; color:#111827; margin:0;"><i class="fa fa-file-text"></i> View Marksheet</h3>
        </div>

        <form method="get" action="view_marksheet.php" class="search-bar-student">
            <div class="form-group col-md-4" style="margin-bottom:0;">
                <label>Exam</label>
                <select name="exam_id" class="form-control" required onchange="this.form.submit()">
                    <option value="">Select Exam</option>
                    <?php foreach ($exams as $x): ?>
                        <option value="<?php echo $x['exam_id']; ?>" <?php echo $sel_exam == $x['exam_id'] ? 'selected' : ''; ?>><?php echo e($x['exam_name']); ?> — <?php echo e($x['class_name'] ?? ''); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group col-md-4" style="margin-bottom:0;">
                <label>Student</label>
                <select name="student_id" class="form-control" required>
                    <option value="">Select Student</option>
                    <?php foreach ($students as $st): ?>
                        <option value="<?php echo $st['student_id']; ?>" <?php echo $sel_student == $st['student_id'] ? 'selected' : ''; ?>><?php echo e($st['first_name']); ?> (<?php echo $st['student_id']; ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group col-md-2" style="margin-bottom:0;">
                <button type="submit" class="btn btn-primary" style="width:100%;">View</button>
            </div>
        </form>

        <?php if ($sel_exam > 0 && $sel_student > 0 && $sel_student_info): ?>
        <div style="max-width:640px; background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:22px;">
            <div style="text-align:center; border-bottom:2px solid #FF7A1B; padding-bottom:12px; margin-bottom:16px;">
                <h4 style="font-weight:800; margin:0; color:#111827;"><?php echo $sel_exam_info ? e($sel_exam_info['exam_name']) : 'Exam'; ?> Result Card</h4>
                <div style="color:#6B7280; font-size:13px; margin-top:4px;"><?php echo e($sel_student_info['first_name']); ?> — <?php echo $sel_exam_info ? e($sel_exam_info['class_name']) : ''; ?></div>
            </div>
            <table class="table table-bordered" style="margin-bottom:12px;">
                <thead>
                    <tr><th>Subject</th><th>Obtained</th><th>Total</th><th>%</th></tr>
                </thead>
                <tbody>
                    <?php if (count($rows) === 0): ?>
                        <tr><td colspan="4" style="text-align:center; color:#6B7280;">No marks entered yet.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td><strong><?php echo e($r['subject_name'] ?? 'Subject'); ?></strong></td>
                            <td><?php echo $r['obtained_marks']; ?></td>
                            <td><?php echo $r['total_marks']; ?></td>
                            <td><?php echo $r['total_marks'] > 0 ? round(($r['obtained_marks'] / $r['total_marks']) * 100, 1) : 0; ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background:#FFF7E0;">
                        <th>Total</th>
                        <th><?php echo $total_obtained; ?></th>
                        <th><?php echo $total_max; ?></th>
                        <th><?php echo $pct; ?>%</th>
                    </tr>
                    <tr>
                        <th colspan="2">Grade</th>
                        <td colspan="2"><span class="status-badge status-paid" style="font-size:14px;"><?php echo $grade; ?></span> <span style="font-size:12px; color:#6B7280; margin-left:8px;"><?php echo $pct; ?>% overall</span></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
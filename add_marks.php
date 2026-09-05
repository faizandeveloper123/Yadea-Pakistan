<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Enter Marks';

$message = '';
$error = '';

$sel_exam = (int) ($_GET['exam_id'] ?? 0);
$sel_class = (int) ($_GET['class_id'] ?? 0);

$exams = [];
$res = db_query("SELECT e.*, c.class_name FROM exams e LEFT JOIN classes c ON e.class_id=c.class_id ORDER BY e.exam_id DESC");
while ($row = $res->fetch_assoc()) { $exams[] = $row; }

$students = [];
$subjects = [];
if ($sel_exam > 0 || $sel_class > 0) {
    $cls = $sel_class;
    foreach ($exams as $x) { if ($x['exam_id'] === $sel_exam) { $cls = $x['class_id']; } }
    if ($cls > 0) {
        $students = [];
        $res = db_query("SELECT * FROM students WHERE class_id=$cls AND status=1 ORDER BY first_name");
        while ($row = $res->fetch_assoc()) { $students[] = $row; }

        $subjects = [];
        $res = db_query("SELECT * FROM subjects WHERE class_id=$cls ORDER BY subject_name");
        while ($row = $res->fetch_assoc()) { $subjects[] = $row; }
    }
}

// Load existing marks
$existing = [];
if ($sel_exam > 0) {
    $res = db_query("SELECT * FROM marks WHERE exam_id=$sel_exam");
    while ($row = $res->fetch_assoc()) { $existing[$row['student_id']][$row['subject_id']] = $row; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'SaveMarks') {
    $exam_id = (int) ($_POST['exam_id'] ?? 0);
    $total_marks = (float) ($_POST['total_marks'] ?? 100);
    $marks = $_POST['marks'] ?? []; // [student_id][subject_id] => obtained

    if ($exam_id <= 0) {
        $error = 'Invalid exam.';
    } else {
        $saved = 0;
        foreach ($marks as $sid => $subs) {
            $sid = (int) $sid;
            foreach ($subs as $sub_id => $obt) {
                $sub_id = (int) $sub_id;
                $obt = (float) $obt;
                if ($obt < 0) $obt = 0;
                if ($existing[$sid][$sub_id] ?? null) {
                    $st2 = db_prepare("UPDATE marks SET obtained_marks=?, total_marks=? WHERE mark_id=?");
                    $mk = $existing[$sid][$sub_id]['mark_id'];
                    $st2->bind_param('ddi', $obt, $total_marks, $mk);
                    $st2->execute();
                } else {
                    $st2 = db_prepare("INSERT INTO marks (student_id, exam_id, subject_id, obtained_marks, total_marks) VALUES (?, ?, ?, ?, ?)");
                    $st2->bind_param('iiidd', $sid, $exam_id, $sub_id, $obt, $total_marks);
                    $st2->execute();
                }
                $saved++;
            }
        }
        $message = "$saved marks records saved!";
    }
}

include __DIR__ . '/includes/header.php';
?>
<style>
.search-bar-student { display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end; background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:16px; margin-bottom:16px; }
.marks-input { width:70px; text-align:center; padding:5px!important; }
</style>

<div class="main-content">
    <div class="container-fluid">
        <?php if ($message): ?><div class="alert alert-success"><?php echo e($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>

        <div style="display:flex; align-items:center; justify-content:space-between; padding:14px 4px;">
            <h3 style="font-size:18px; font-weight:800; color:#111827; margin:0;"><i class="fa fa-edit"></i> Enter Marks</h3>
            <a href="<?php echo BASE_URL; ?>view_marksheet.php" class="btn btn-primary" style="color:#fff;"><i class="fa fa-file-text"></i> View Marksheet</a>
        </div>

        <form method="get" action="add_marks.php" class="search-bar-student">
            <div class="form-group col-md-4" style="margin-bottom:0;">
                <label>Exam</label>
                <select name="exam_id" class="form-control" required>
                    <option value="">Select Exam</option>
                    <?php foreach ($exams as $x): ?>
                        <option value="<?php echo $x['exam_id']; ?>" <?php echo $sel_exam == $x['exam_id'] ? 'selected' : ''; ?>><?php echo e($x['exam_name']); ?> — <?php echo e($x['class_name'] ?? ''); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group col-md-2" style="margin-bottom:0;">
                <label>Total Marks</label>
                <input type="number" name="total_marks" class="form-control" value="100" min="1">
            </div>
            <div class="form-group col-md-2" style="margin-bottom:0;">
                <button type="submit" class="btn btn-primary" style="width:100%;"><i class="fa fa-search"></i> Load</button>
            </div>
        </form>

        <?php if ($sel_exam > 0 && count($students) > 0): ?>
        <form method="post" action="add_marks.php" style="overflow-x:auto; background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:4px;">
            <input type="hidden" name="action" value="SaveMarks">
            <input type="hidden" name="exam_id" value="<?php echo $sel_exam; ?>">
            <input type="hidden" name="total_marks" value="<?php echo e($_GET['total_marks'] ?? 100); ?>">
            <table class="table table-bordered" style="width:100%; background:#fff; margin-bottom:0; font-size:12.5px;">
                <thead>
                    <tr>
                        <th style="min-width:40px;">GR</th>
                        <th style="min-width:160px;">Student</th>
                        <?php foreach ($subjects as $sub): ?>
                            <th style="min-width:80px;"><?php echo e($sub['subject_name']); ?><br><small style="color:#6B7280;">/<?php echo e($_GET['total_marks'] ?? 100); ?></small></th>
                        <?php endforeach; ?>
                        <?php if (count($subjects) === 0): ?><th>No Subjects</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $st): ?>
                        <tr>
                            <td><?php echo $st['student_id']; ?></td>
                            <td><strong><?php echo e($st['first_name']); ?></strong><br><small style="color:#6B7280;"><?php echo e($st['father_name']); ?></small></td>
                            <?php foreach ($subjects as $sub): $mk = $existing[$st['student_id']][$sub['subject_id']] ?? null; ?>
                                <td>
                                    <input type="number" step="0.01" class="form-control marks-input" name="marks[<?php echo $st['student_id']; ?>][<?php echo $sub['subject_id']; ?>]" value="<?php echo $mk ? $mk['obtained_marks'] : ''; ?>" min="0">
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div style="padding:14px; text-align:right;">
                <button type="submit" class="btn btn-success" style="padding:10px 30px;"><i class="fa fa-save"></i> Save All Marks</button>
            </div>
        </form>
        <?php elseif ($sel_exam > 0): ?>
            <div class="alert alert-warning">No students found for this exam's class.</div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
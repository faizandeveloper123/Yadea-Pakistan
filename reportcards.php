<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Marks Report';

$exam_id = (int) ($_GET['exam_id'] ?? 0);
$class_id = (int) ($_GET['class_id'] ?? 0);

$exams = [];
$res = db_query("SELECT * FROM exams ORDER BY exam_id DESC");
while ($row = $res->fetch_assoc()) { $exams[] = $row; }

$classes = [];
$res = db_query("Select class_id, class_name FROM classes ORDER BY class_name");
while ($row = $res->fetch_assoc()) { $classes[] = $row; }

$students = [];
if ($exam_id > 0 && $class_id > 0) {
    $res = db_query("SELECT DISTINCT m.student_id, s.first_name, s.father_name, s.roll_no, cl.class_name
        FROM marks m
        JOIN students s ON m.student_id=s.student_id
        LEFT JOIN classes cl ON s.class_id=cl.class_id
        WHERE m.exam_id=$exam_id AND s.class_id=$class_id
        ORDER BY s.first_name");
    while ($row = $res->fetch_assoc()) {
        $row['subjects'] = [];
        $res2 = db_query("SELECT m.obtained_marks, m.total_marks, m.subject_id, su.subject_name
            FROM marks m LEFT JOIN subjects su ON m.subject_id=su.subject_id
            WHERE m.exam_id=$exam_id AND m.student_id={$row['student_id']}");
        $tot = 0; $full = 0;
        while ($m = $res2->fetch_assoc()) { $row['subjects'][] = $m; $tot += (float)$m['obtained_marks']; $full += (float)($m['total_marks'] ?: 0); }
        $row['obtained'] = $tot; $row['max'] = $full;
        $row['pct'] = $full > 0 ? round(($tot / $full) * 100, 1) : 0;
        $students[] = $row;
    }
}

include __DIR__ . '/includes/header.php';
?>
<style>
.search-bar-student { display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end; background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:16px; margin-bottom:16px; }
</style>

<div class="main-content">
    <div class="container-fluid">
        <div style="display:flex; align-items:center; justify-content:space-between; padding:14px 4px;">
            <h3 style="font-size:18px; font-weight:800; color:#111827; margin:0;"><i class="fa fa-file-text-o"></i> Marks Report / Result Card</h3>
            <a href="<?php echo BASE_URL; ?>add_marks.php" class="btn btn-success" style="color:#fff;"><i class="fa fa-plus"></i> Add Marks</a>
        </div>

        <form method="get" action="reportcards.php" class="search-bar-student">
            <div class="form-group col-md-3" style="margin-bottom:0;">
                <label>Exam</label>
                <select name="exam_id" class="form-control" required>
                    <option value="">Select Exam</option>
                    <?php foreach ($exams as $ex): ?><option value="<?php echo $ex['exam_id']; ?>" <?php echo $exam_id == $ex['exam_id'] ? 'selected' : ''; ?>><?php echo e($ex['exam_name']); ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group col-md-3" style="margin-bottom:0;">
                <label>Course/Class</label>
                <select name="class_id" class="form-control" required>
                    <option value="">Select Course/Class</option>
                    <?php foreach ($classes as $cl): ?><option value="<?php echo $cl['class_id']; ?>" <?php echo $class_id == $cl['class_id'] ? 'selected' : ''; ?>><?php echo e($cl['class_name']); ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group col-md-2" style="margin-bottom:0;">
                <button type="submit" class="btn btn-primary" style="width:100%;"><i class="fa fa-search"></i> Load</button>
            </div>
        </form>

        <?php foreach ($students as $st): ?>
            <div style="background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:16px; margin-bottom:14px;">
                <div style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:8px; margin-bottom:10px;">
                    <div>
                        <strong style="font-size:15px; color:#111827;"><?php echo e($st['first_name']); ?></strong>
                        <span style="color:#6B7280; margin-left:8px;"><?php echo e($st['father_name'] ?? ''); ?></span>
                        <span class="status-badge" style="background:#E0E7FF; color:#4338CA; margin-left:8px;"><?php echo e($st['class_name'] ?? ''); ?></span>
                    </div>
                    <div style="font-size:13px;">
                        Obtained: <strong><?php echo number_format($st['obtained'], 1); ?></strong> /
                        <strong><?php echo number_format($st['max'], 1); ?></strong> |
                        Percentage: <strong style="color:#16A34A;"><?php echo $st['pct']; ?>%</strong>
                        <a href="<?php echo BASE_URL; ?>view_marksheet.php?student_id=<?php echo $st['student_id']; ?>&exam_id=<?php echo $exam_id; ?>&class_id=<?php echo $class_id; ?>" class="btn btn-info btn-xs" style="color:#fff; margin-left:10px;"><i class="fa fa-file"></i> Full Card</a>
                    </div>
                </div>
                <table class="table table-bordered table-striped" style="width:100%; background:#F9FAFB; margin-bottom:0; font-size:13px;">
                    <thead><tr><th>Subject</th><th style="width:120px;">Obtained</th><th style="width:120px;">Total</th></tr></thead>
                    <tbody>
                        <?php if (count($st['subjects']) === 0): ?><tr><td colspan="3" style="text-align:center; color:#6B7280;">No subjects recorded.</td></tr><?php endif; ?>
                        <?php foreach ($st['subjects'] as $sb): ?>
                            <tr>
                                <td><?php echo e($sb['subject_name'] ?: 'Subject #' . $sb['subject_id']); ?></td>
                                <td><?php echo number_format($sb['obtained_marks'], 1); ?></td>
                                <td><?php echo number_format($sb['total_marks'] ?: 0, 1); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
        <?php if ($exam_id > 0 && $class_id > 0 && count($students) === 0): ?>
            <div style="text-align:center; color:#6B7280; padding:40px;">Is exam/class ke liye koi marks record nahi mila.</div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
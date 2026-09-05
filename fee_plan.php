<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

// Ensure the per-student fee-plan table exists (auto-migration pattern used across the project)
try { db_query("CREATE TABLE IF NOT EXISTS student_fee_plan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    head_id INT DEFAULT NULL,
    head_name VARCHAR(191) DEFAULT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    discount DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY (student_id)
) ENGINE=InnoDB"); } catch (Throwable $ex) {}

$studentId = (int) ($_GET['student_id'] ?? $_GET['student'] ?? 0);

// --- POST: save the fee plan for this student ---
$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['save_fee_plan'] ?? '') === '1') {
    $sid = (int) ($_POST['student_id'] ?? 0);
    if ($sid > 0) {
        $st = db_prepare('DELETE FROM student_fee_plan WHERE student_id = ?');
        $st->bind_param('i', $sid);
        $st->execute();
        $st->close();
        $heads = isset($_POST['heads']) ? $_POST['heads'] : [];
        $done = 0;
        $ins = db_prepare('INSERT INTO student_fee_plan (student_id, head_id, head_name, amount, discount) VALUES (?, ?, ?, ?, ?)');
        foreach ((array) $heads as $head_id => $row) {
            $hname = trim($_POST['head_names'][$head_id] ?? '');
            $amt = (float) ($_POST['amounts'][$head_id] ?? 0);
            $disc = (float) ($_POST['discounts'][$head_id] ?? 0);
            $ins->bind_param('iisdd', $sid, (int) $head_id, $hname, $amt, $disc);
            $ins->execute();
            $done++;
        }
        $ins->close();
        $message = 'Fee plan saved with ' . $done . ' fee head(s).';
    } else {
        $error = 'Please select a valid student before saving the fee plan.';
    }
}

// --- Load the student ---
$student = null;
if ($studentId > 0) {
    $sq = db_prepare('SELECT student_id, first_name, last_name, gr_no, class_id, section_id, admission_date FROM students WHERE student_id = ?');
    $sq->bind_param('i', $studentId);
    $sq->execute();
    $student = $sq->get_result()->fetch_assoc();
    $sq->close();
}

// Existing fee plan for pre-fill
$savedPlan = [];
if ($studentId > 0) {
    $fp = db_prepare('SELECT head_id, head_name, amount, discount FROM student_fee_plan WHERE student_id = ? ORDER BY id');
    $fp->bind_param('i', $studentId);
    $fp->execute();
    $res = $fp->get_result();
    while ($r = $res->fetch_assoc()) { $savedPlan[(int) $r['head_id']] = $r; }
    $fp->close();
}

// Active fee heads (defaults)
$feeHeads = [];
$fr = db_query("SELECT head_id, head_name, amount FROM fee_heads WHERE status=1 ORDER BY head_id");
if ($fr) { while ($row = $fr->fetch_assoc()) { $feeHeads[] = $row; } }

// Class / section labels for the summary line
$classLabel = '';
if ($student && !empty($student['class_id'])) {
    $cr = db_prepare('Select class_name FROM classes WHERE class_id = ?');
    $cr->bind_param('i', $student['class_id']);
    $cr->execute();
    $classLabel = $cr->get_result()->fetch_row()[0] ?? '';
    $cr->close();
}
$sectionLabel = '';
if ($student && !empty($student['section_id'])) {
    $sr = db_prepare('SELECT section_name FROM sections WHERE section_id = ?');
    $sr->bind_param('i', $student['section_id']);
    $sr->execute();
    $sectionLabel = $sr->get_result()->fetch_row()[0] ?? '';
    $sr->close();
}

include __DIR__ . '/includes/header.php';
?>
<style>
.wizard-section-title { display: flex; align-items: center; gap: 10px; font-size: 14px; font-weight: 700; color: #111827; margin: 14px 0; border-left: 4px solid #FF7A1B; padding-left: 10px; line-height: 1.3; }
.wizard-actions-bar { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-top: 18px; padding-top: 16px; border-top: 1px solid #E5E7EB; }
.mandatory-note { font-size: 12px; color: #6B7280; }
.wizard-actions-buttons { display: flex; gap: 10px; }
.wizard-btn { padding: 9px 22px; border-radius: 10px; border: 1px solid #E5E7EB; background: #fff; color: #374151; font-weight: 600; font-size: 13px; cursor: pointer; }
.wizard-btn-primary { background: #FF7A1B; border-color: #FF7A1B; color: #fff; }
.wizard-btn-primary:hover { background: #e96a0c; color: #fff; }
.page-card { background: #fff; border: 1px solid #E5E7EB; border-radius: 14px; padding: 16px 20px; }
.fh-amount, .fh-discount { width: 130px; text-align: right; }
thead th { font-size: 12.5px; color: #6B7280; }
table tbody td { vertical-align: middle; }
</style>

<div class="main-content">
    <div class="container-fluid">
        <div class="container mt-4 page-card" style="width:100%;">

            <!-- Breadcrumb -->
            <div style="padding:6px 4px 10px; font-size:12.5px; color:#6B7280;">
                <a href="dashboard.php" style="color:#377dff;">Dashboard</a> <i class="fa fa-angle-double-right"></i>
                <a href="manage_students.php" style="color:#377dff;">Students</a> <i class="fa fa-angle-double-right"></i>
                Fee Plan
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success" style="margin-top:12px;"><?php echo e($message); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger" style="margin-top:12px;"><?php echo e($error); ?></div>
            <?php endif; ?>

            <?php if (!$student): ?>
                <div class="alert alert-warning">
                    No student selected. <a href="manage_students.php" class="alert-link">Go to Manage Students</a> to pick a student and open their fee plan.
                </div>
            <?php else: ?>

            <?php
            $fullName = trim(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? ''));
            $feeTotal = 0;
            foreach ($feeHeads as $fh) { $feeTotal += (float) $fh['amount']; }
            ?>

            <!-- Summary line -->
            <div class="wizard-section-title"><span>Fee Plan</span></div>
            <p style="font-size:13px; color:#6B7280; margin:0 0 16px;">
                Configure the monthly fee heads for
                <strong><?php echo e($fullName); ?></strong>
                <?php if ($student['gr_no']): ?>(GR No: <?php echo e($student['gr_no']); ?>)<?php endif; ?>
                <?php if ($classLabel): ?>&middot; Class: <?php echo e($classLabel); ?> <?php echo e($sectionLabel); ?><?php endif; ?>
            </p>

            <form id="feePlanForm" action="fee_plan.php?student_id=<?php echo (int) $studentId; ?>" method="post">
                <input type="hidden" name="save_fee_plan" value="1">
                <input type="hidden" name="student_id" value="<?php echo (int) $studentId; ?>">
                <div style="background:#fff; border:1px solid #E6E9ED; border-radius:14px; padding:22px 24px;">
                    <div class="wizard-section-title" style="border-left-color:#FF7A1B;"><span>Monthly Fee Heads <small style="font-size:12px; color:gray;">(tick the heads that apply to this student)</small></span></div>

                    <?php if (!$feeHeads): ?>
                        <div style="padding:30px; text-align:center; color:#8A99A8;">
                            <p>No active fee heads found.</p>
                            <a class="btn btn-default" href="<?php echo BASE_URL; ?>update_fee_settings.php"><i class="fa fa-cog"></i> Add Fee Heads</a>
                        </div>
                    <?php else: ?>
                    <table class="table table-hover" style="margin-bottom:0;">
                        <thead>
                            <tr>
                                <th style="width:40px;">#</th>
                                <th>Fee Head</th>
                                <th style="width:170px;">Amount</th>
                                <th style="width:170px;">Discount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($feeHeads as $i => $fh):
                                $hid = (int) $fh['head_id'];
                                $existing = $savedPlan[$hid] ?? null;
                                $checked = $existing ? 'checked' : '';
                                $amt = $existing ? (float) $existing['amount'] : (float) $fh['amount'];
                                $disc = $existing ? (float) $existing['discount'] : 0; ?>
                                <tr>
                                    <td><?php echo $i + 1; ?></td>
                                    <td>
                                        <label style="font-weight:500; margin:0; display:flex; align-items:center; gap:6px;">
                                            <input type="hidden" name="heads[<?php echo $hid; ?>]" value="0">
                                            <input type="checkbox" name="heads[<?php echo $hid; ?>]" value="1" <?php echo $checked; ?> class="fh-check" data-amount="<?php echo (float) $fh['amount']; ?>">
                                            <span>
                                                <?php echo e($fh['head_name']); ?>
                                                <input type="hidden" name="head_names[<?php echo $hid; ?>]" value="<?php echo e($fh['head_name']); ?>">
                                            </span>
                                        </label>
                                    </td>
                                    <td><input type="number" step="0.01" min="0" class="form-control input-sm fh-amount" name="amounts[<?php echo $hid; ?>]" value="<?php echo $amt; ?>"></td>
                                    <td><input type="number" step="0.01" min="0" class="form-control input-sm fh-discount" name="discounts[<?php echo $hid; ?>]" value="<?php echo $disc; ?>"></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="2" style="text-align:right; font-weight:700;">Monthly Total:</td>
                                <td><strong id="feeTotal"><?php echo number_format($feeTotal, 2); ?></strong></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                    <?php endif; ?>

                    <div class="wizard-actions-bar">
                        <span class="mandatory-note">The Monthly Total is calculated as Amount minus Discount for each selected head.</span>
                        <div class="wizard-actions-buttons">
                            <a href="<?php echo BASE_URL; ?>add_student.php" class="wizard-btn"><i class="fa fa-plus"></i> Add Another Student</a>
                            <button type="submit" class="wizard-btn wizard-btn-primary" id="btnSaveFeePlan"><i class="fa fa-save"></i> Save Fee Plan</button>
                            <a href="<?php echo BASE_URL; ?>student.php?student=<?php echo (int) $studentId; ?>" class="wizard-btn"><i class="fa fa-user"></i> View Student</a>
                        </div>
                    </div>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
(function () {
    var form = document.getElementById('feePlanForm');
    if (!form) return;
    function calc() {
        var total = 0;
        document.querySelectorAll('.fh-check').forEach(function (cb) {
            if (cb.checked) {
                var tr = cb.closest('tr');
                var amt = parseFloat((tr.querySelector('.fh-amount') || {}).value || 0) || 0;
                var disc = parseFloat((tr.querySelector('.fh-discount') || {}).value || 0) || 0;
                total += amt - disc;
            }
        });
        var t = document.getElementById('feeTotal');
        if (t) t.textContent = total.toFixed(2);
    }
    form.addEventListener('change', calc);
    form.addEventListener('input', calc);
    calc();
})();
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
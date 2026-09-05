<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Student Fee Payments';

$sel_student = (int) ($_GET['student_id'] ?? 0);

$students = [];
$res = db_query("SELECT student_id, first_name, father_name FROM students WHERE status=1 ORDER BY first_name");
while ($row = $res->fetch_assoc()) { $students[] = $row; }

$challans = [];
if ($sel_student > 0) {
    $res = db_query("SELECT c.*, s.first_name, s.father_name FROM fee_challans c JOIN students s ON c.student_id=s.student_id WHERE c.student_id=$sel_student ORDER BY c.created_at DESC");
    while ($row = $res->fetch_assoc()) {
        $row['payments'] = [];
        $res2 = db_query("SELECT * FROM fee_payments WHERE challan_id={$row['challan_id']} ORDER BY payment_id");
        while ($p = $res2->fetch_assoc()) { $row['payments'][] = $p; }
        $challans[] = $row;
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
            <h3 style="font-size:18px; font-weight:800; color:#111827; margin:0;"><i class="fa fa-money"></i> Student Fee Payments</h3>
            <a href="<?php echo BASE_URL; ?>parents_portal_dashboard.php" class="btn btn-primary" style="color:#fff;"><i class="fa fa-users"></i> Parents Portal</a>
        </div>

        <form method="get" action="student_fee_payments_view.php" class="search-bar-student">
            <div class="form-group col-md-4" style="margin-bottom:0;">
                <label>Student</label>
                <select name="student_id" class="form-control" required onchange="this.form.submit()">
                    <option value="">Select Student</option>
                    <?php foreach ($students as $s): ?>
                        <option value="<?php echo $s['student_id']; ?>" <?php echo $sel_student == $s['student_id'] ? 'selected' : ''; ?>><?php echo e($s['first_name']); ?> — <?php echo e($s['father_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>

        <?php foreach ($challans as $c): $due = (float)$c['total_amount'] - (float)$c['paid_amount']; ?>
            <div style="background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:16px; margin-bottom:12px;">
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
                    <div>
                        <strong><?php echo e($c['challan_no']); ?></strong>
                        <span style="color:#6B7280; margin-left:10px;"><?php echo e($c['month']); ?></span>
                        <span class="status-badge status-<?php echo $c['status']; ?>" style="margin-left:10px;"><?php echo ucfirst($c['status']); ?></span>
                    </div>
                    <div style="font-size:13px;">
                        Total: <strong><?php echo number_format($c['total_amount'], 2); ?></strong> &nbsp;
                        Paid: <span style="color:#16A34A;"><?php echo number_format($c['paid_amount'], 2); ?></span> &nbsp;
                        Due: <span style="color:#DC2626;"><?php echo number_format($due, 2); ?></span>
                    </div>
                </div>
                <?php if (count($c['payments']) > 0): ?>
                    <table class="table table-striped table-bordered" style="width:100%; background:#F9FAFB; margin:10px 0 0; font-size:12.5px;">
                        <thead><tr><th>#</th><th>Amount</th><th>Method</th><th>Date</th></tr></thead>
                        <tbody>
                            <?php foreach ($c['payments'] as $p): ?>
                                <tr>
                                    <td><?php echo $p['payment_id']; ?></td>
                                    <td style="color:#16A34A; font-weight:700;"><?php echo number_format($p['amount'], 2); ?></td>
                                    <td><?php echo e(ucfirst($p['payment_method'])); ?></td>
                                    <td><?php echo date('d M Y h:i A', strtotime($p['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
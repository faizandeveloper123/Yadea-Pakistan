<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$ids = $_POST['payroll_id'] ?? $_GET['payroll_id'] ?? [];
if (!is_array($ids)) { $ids = [$ids]; }
$ids = array_filter(array_map('intval', $ids));

$rows = [];
if (count($ids) > 0) {
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $st2 = db_prepare("SELECT p.*, e.first_name, e.last_name, e.designation, e.department FROM payroll p LEFT JOIN employees e ON p.emp_id=e.emp_id WHERE p.payroll_id IN ($ph) ORDER BY p.payroll_id");
    $st2->bind_param(str_repeat('i', count($ids)), ...$ids);
    $st2->execute();
    $res = $st2->get_result();
    while ($row = $res->fetch_assoc()) {
        $ded_sum = (float)$row['p_bal'] + (float)$row['adv_amt'] + (float)$row['adv_dec'] + (float)$row['security'] + (float)$row['other_ded'] + (float)$row['absent'];
        $row['ded_show'] = $ded_sum > 0 ? $ded_sum : (float)$row['deductions'];
        $allow_sum = (float)$row['traveling'] + (float)$row['reimb'] + (float)$row['other_allow'];
        $row['allow_show'] = $allow_sum > 0 ? $allow_sum : (float)$row['allowances'];
        $row['net'] = round((float)$row['basic_salary'] + $row['allow_show'] - $row['ded_show'], 2);
        $rows[] = $row;
    }
}

if (count($rows) === 0) { die('No payroll slips selected.'); }

$school = get_setting('school_name', 'HIIFI');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Salary Slips</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; color: #111827; margin: 20px; font-size: 12px; }
        .slip { border: 1px solid #d1d5db; border-radius: 8px; padding: 16px 18px; margin-bottom: 18px; page-break-after: always; }
        .slip:last-child { page-break-after: auto; }
        .head { text-align: center; margin-bottom: 8px; }
        .head .name { font-size: 18px; font-weight: 800; color: #ff7800; }
        .head .title { font-weight: 700; }
        table { width: 100%; border-collapse: collapse; }
        td, th { border: 1px solid #d1d5db; padding: 5px 8px; }
        th { background: #f3f4f6; text-transform: uppercase; font-size: 11px; }
        .right { text-align: right; }
        b.amt { font-weight: 800; }
        .paid { color: #16a34a; font-weight: 800; }
        .pending { color: #d97706; font-weight: 800; }
        .sign { display: flex; justify-content: space-between; margin-top: 18px; }
        .sign div { width: 40%; text-align: center; }
        .sign .line { border-top: 1px solid #9ca3af; margin-top: 22px; }
        @media print { body { margin: 6px; } .slip { border: none; padding: 0; margin-bottom: 10px; } }
    </style>
</head>
<body>
<?php foreach ($rows as $r):
    $monthName = date('F', mktime(0, 0, 0, (int)$r['month'], 1));
    $period = trim($monthName . ' ' . ($r['year'] ?? ''));
?>
<div class="slip">
    <div class="head">
        <div class="name"><?php echo e($school); ?></div>
        <div class="title">Salary Slip - <?php echo e($period); ?></div>
    </div>
    <table>
        <tr>
            <th>Employee</th><th>Designation</th><th>Basic</th><th>Allowances</th><th>Deductions</th><th>Net Pay</th><th>Status</th>
        </tr>
        <tr>
            <td><?php echo e($r['first_name'] . ' ' . $r['last_name']); ?></td>
            <td><?php echo e($r['designation'] ?: '-'); ?></td>
            <td class="right"><?php echo number_format((float)$r['basic_salary'], 2); ?></td>
            <td class="right"><?php echo number_format($r['allow_show'], 2); ?></td>
            <td class="right"><?php echo number_format($r['ded_show'], 2); ?></td>
            <td class="right"><b class="amt"><?php echo number_format($r['net'], 2); ?></b></td>
            <td><span class="<?php echo $r['status'] == 'paid' ? 'paid' : 'pending'; ?>"><?php echo strtoupper($r['status']); ?></span>
                <?php if (!empty($r['paid_date'])): ?>(<?php echo e($r['paid_date']); ?>)<?php endif; ?></td>
        </tr>
    </table>
    <div class="sign">
        <div><span class="line"></span><div>Prepared By</div></div>
        <div><span class="line"></span><div>Received By</div></div>
    </div>
</div>
<?php endforeach; ?>
<script>window.onload = function(){ window.print(); };</script>
</body>
</html>
<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Monthly Invoices';

$sel_year = (int) ($_GET['year'] ?? ((int) date('Y')));
$years = [];
for ($y = 2018; $y <= 2030; $y++) { $years[] = $y; }
if (!in_array($sel_year, $years)) { $sel_year = (int) date('Y'); }
if (!in_array($sel_year, $years)) { $sel_year = 2026; }

$months = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June',
    7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
];

$monthly = [];
$res = db_query("SELECT
                    CAST(c.month AS UNSIGNED) AS m,
                    SUM(c.total_amount) AS total_amt,
                    SUM(c.paid_amount) AS paid_amt,
                    MAX(CASE WHEN p.payment_id IS NOT NULL THEN p.created_at END) AS paid_date,
                    COUNT(*) AS challan_count
                 FROM fee_challans c
                 LEFT JOIN fee_payments p ON c.challan_id = p.challan_id
                 WHERE c.year = $sel_year
                 GROUP BY CAST(c.month AS UNSIGNED)
                 ORDER BY m DESC");
while ($row = $res->fetch_assoc()) { $monthly[$row['m']] = $row; }

$details = [];
$res = db_query("SELECT c.*, st.first_name, st.last_name, st.gr_no, cl.class_name
                 FROM fee_challans c
                 LEFT JOIN students st ON c.student_id = st.student_id
                 LEFT JOIN classes cl ON c.class_id = cl.class_id
                 WHERE c.year = $sel_year
                 ORDER BY CAST(c.month AS UNSIGNED) DESC, c.challan_id DESC");
while ($row = $res->fetch_assoc()) { $details[] = $row; }

include __DIR__ . '/includes/header.php';
?>
<style>
.invoice-filter-panel { background:#fff; border:1px solid #E5E7EB; border-radius:14px; margin-bottom:16px; overflow:hidden; }
.invoice-filter-panel > .panel-heading { background:#fff; border-bottom:1px solid #EEF0F3; padding:14px 16px; }
.invoice-filter-panel > .panel-heading h4 { margin:0; font-size:16px; font-weight:800; color:#111827; }
.invoice-filter-panel > .panel-body { padding:14px 16px; }
.invoice-filter-field label { font-size:12px; font-weight:700; color:#374151; margin-bottom:4px; display:block; }
.invoice-filter-field .form-control { height:40px; border-radius:9px; }
.month-stats-row { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:16px; }
.inv-stat-card { background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:16px; border-left:5px solid #6366F1; }
.inv-stat-card .label { font-size:12px; color:#6B7280; text-transform:uppercase; letter-spacing:.3px; }
.inv-stat-card .value { font-size:22px; font-weight:800; color:#111827; }
.inv-stat-green { border-left-color:#10B981; } .inv-stat-red { border-left-color:#EF4444; } .inv-stat-amber { border-left-color:#F59E0B; }
.status-badge { padding:4px 12px; border-radius:999px; font-size:12px; font-weight:700; }
@media (max-width:900px){ .month-stats-row { grid-template-columns:repeat(2,1fr); } }
</style>

<div class="main-content">
    <div class="container-fluid">
        <div style="display:flex; align-items:center; justify-content:space-between; padding:14px 4px;">
            <h3 style="font-size:18px; font-weight:800; color:#111827; margin:0;"><i class="fa fa-file-text"></i> Monthly Invoice List View</h3>
            <a href="<?php echo BASE_URL; ?>monthly_challan.php" class="btn btn-primary" style="color:#fff;"><i class="fa fa-plus"></i> Create Challan</a>
        </div>

        <form method="get" action="monthly_invoices.php" class="invoice-filter-panel">
            <div class="panel-heading"><h4><i class="fa fa-filter" style="color:#F59E0B;"></i> Select Year</h4></div>
            <div class="panel-body">
                <div style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end;">
                    <div class="invoice-filter-field" style="flex:0 0 220px;">
                        <div class="form-group" style="margin-bottom:0;">
                            <label>Year <span style="color:#DC2626;">*</span></label>
                            <select name="year" class="form-control">
                                <?php foreach ($years as $y): ?>
                                    <option value="<?php echo $y; ?>" <?php echo $sel_year === $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div>
                        <div class="form-group" style="margin-bottom:0;">
                            <button type="submit" class="btn btn-primary" style="height:40px;"><i class="fa fa-search"></i> Search</button>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <?php
        $year_total = 0.0; $year_paid = 0.0; $year_due = 0.0; $month_count = count($monthly);
        foreach ($monthly as $m => $r) {
            $year_total += (float) $r['total_amt'];
            $year_paid += (float) $r['paid_amt'];
            $year_due += (float) $r['total_amt'] - (float) $r['paid_amt'];
        }
        ?>
        <div class="month-stats-row">
            <div class="inv-stat-card"><div class="label">Total Challans</div><div class="value"><?php echo $month_count; ?> months</div></div>
            <div class="inv-stat-card"><div class="label">Total Amount</div><div class="value"><?php echo get_setting('currency_symbol', 'Rs.') . number_format($year_total, 2); ?></div></div>
            <div class="inv-stat-card inv-stat-green"><div class="label">Collected</div><div class="value"><?php echo get_setting('currency_symbol', 'Rs.') . number_format($year_paid, 2); ?></div></div>
            <div class="inv-stat-card inv-stat-red"><div class="label">Outstanding</div><div class="value"><?php echo get_setting('currency_symbol', 'Rs.') . number_format($year_due, 2); ?></div></div>
        </div>

        <div style="overflow-x:auto; background:#fff; border:1px solid #E5E7EB; border-radius:14px; margin-bottom:20px;">
            <div style="padding:14px 16px; border-bottom:1px solid #EEF0F3;">
                <h4 style="margin:0; font-size:15px; font-weight:800; color:#111827;"><i class="fa fa-calendar" style="color:#F59E0B;"></i> Monthly Summary — <?php echo $sel_year; ?></h4>
            </div>
            <table class="table table-hover table-bordered" style="width:100%; background:#fff; margin-bottom:0; font-size:13px;">
                <thead>
                    <tr style="background:#F9FAFB;">
                        <th>S.No</th><th>Month</th><th>Total Amount</th><th>Paid Amount</th><th>Remaining Amount</th><th>Paid Status</th><th>Paid Date</th><th>Print</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach ($monthly as $m => $r):
                        $amt = (float) $r['total_amt'];
                        $paid = (float) $r['paid_amt'];
                        $due = $amt - $paid;
                        $pdate = $r['paid_date'] ? date('d-M-Y', strtotime($r['paid_date'])) : '';
                    ?>
                        <tr>
                            <td><?php echo $i++; ?></td>
                            <td><strong><?php echo $months[(int) $m]; ?></strong> <small style="color:#6B7280;">(<?php echo (int)$r['challan_count']; ?> challans)</small></td>
                            <td style="font-weight:700;"><?php echo get_setting('currency_symbol', 'Rs.') . number_format($amt, 2); ?></td>
                            <td style="font-weight:700; color:#16A34A;"><?php echo number_format($paid, 2); ?></td>
                            <td style="font-weight:700; color:<?php echo $due > 0 ? '#DC2626' : '#16A34A'; ?>;"><?php echo number_format($due, 2); ?></td>
                            <td>
                                <?php if ($due > 0): ?>
                                    <a class="btn btn-danger" style="color:#fff; padding:2px 10px; font-size:12px;">Not Paid</a>
                                <?php else: ?>
                                    <a class="btn btn-success" style="color:#fff; padding:2px 10px; font-size:12px;">Paid <i class="fa fa-check"></i></a>
                                <?php endif; ?>
                            </td>
                            <td><?php echo e($pdate); ?></td>
                            <td>
                                <?php
                                $first_cid = 0;
                                $r2 = db_query("SELECT challan_id FROM fee_challans WHERE year=$sel_year AND CAST(month AS UNSIGNED)=" . (int)$m . " ORDER BY challan_id DESC LIMIT 1");
                                if ($r2 && $rr = $r2->fetch_assoc()) { $first_cid = (int) $rr['challan_id']; }
                                ?>
                                <?php if ($first_cid > 0): ?>
                                    <a href="<?php echo BASE_URL; ?>payment_slip.php?challan_id=<?php echo $first_cid; ?>" target="_blank" class="btn btn-primary" style="padding:2px 14px; font-size:12px;">Print</a>
                                <?php else: ?>
                                    <span style="color:#9CA3AF;">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($month_count === 0): ?>
                        <tr><td colspan="8" style="text-align:center; color:#6B7280; padding:36px;">No challans found for year <?php echo $sel_year; ?>.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div style="overflow-x:auto; background:#fff; border:1px solid #E5E7EB; border-radius:14px;">
            <div style="padding:14px 16px; border-bottom:1px solid #EEF0F3; display:flex; justify-content:space-between; flex-wrap:wrap; gap:8px;">
                <h4 style="margin:0; font-size:15px; font-weight:800; color:#111827;"><i class="fa fa-list" style="color:#F59E0B;"></i> Challan Details — <?php echo $sel_year; ?> <span style="font-size:12px; color:#6B7280; font-weight:600;"> (<?php echo count($details); ?> challans)</span></h4>
            </div>
            <table class="table table-hover table-bordered" id="challanDetailsTable" style="width:100%; background:#fff; margin-bottom:0; font-size:13px;">
                <thead>
                    <tr style="background:#F9FAFB;">
                        <th>S.No</th><th>Challan No</th><th>Student</th><th>GR.No</th><th>Course/Class</th><th>Month</th><th>Total</th><th>Paid</th><th>Due</th><th>Status</th><th>Print</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach ($details as $d):
                        $due = (float) $d['total_amount'] - (float) $d['paid_amount'];
                        $badge = 'background:#FEE2E2;color:#DC2626;';
                        if ($d['status'] === 'partial') $badge = 'background:#FFF7E0;color:#F59E0B;';
                        if ($d['status'] === 'paid') $badge = 'background:#DCFCE7;color:#16A34A;';
                    ?>
                        <tr>
                            <td><?php echo $i++; ?></td>
                            <td><strong><?php echo e($d['challan_no']); ?></strong></td>
                            <td><?php echo e(($d['first_name'] ?? '') . ' ' . ($d['last_name'] ?? '')); ?></td>
                            <td><?php echo e($d['gr_no'] ?? '-'); ?></td>
                            <td><?php echo e($d['class_name'] ?? '-'); ?></td>
                            <td><?php echo e($d['month']) . ' / ' . e($d['year']); ?></td>
                            <td style="font-weight:700;"><?php echo get_setting('currency_symbol', 'Rs.') . number_format($d['total_amount'], 2); ?></td>
                            <td style="color:#16A34A; font-weight:700;"><?php echo number_format($d['paid_amount'], 2); ?></td>
                            <td style="color:<?php echo $due > 0 ? '#DC2626' : '#16A34A'; ?>; font-weight:700;"><?php echo number_format($due, 2); ?></td>
                            <td><span class="status-badge" style="<?php echo $badge; ?>"><?php echo ucfirst($d['status']); ?></span></td>
                            <td>
                                <a href="<?php echo BASE_URL; ?>payment_slip.php?challan_id=<?php echo $d['challan_id']; ?>" target="_blank" class="btn btn-primary" style="padding:2px 14px; font-size:12px;"><i class="fa fa-print"></i> Print</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (count($details) === 0): ?>
                        <tr><td colspan="11" style="text-align:center; color:#6B7280; padding:36px;">No challans found for year <?php echo $sel_year; ?>.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
var table = document.getElementById('challanDetailsTable');
var filterInput = document.createElement('input');
filterInput.type = 'search';
filterInput.placeholder = 'Search challan no / student / gr no...';
filterInput.className = 'form-control';
filterInput.style.cssText = 'max-width:320px; height:36px; border-radius:8px; margin-left:auto;';
filterInput.addEventListener('input', function(){
    var q = this.value.toLowerCase();
    var rows = table.querySelectorAll('tbody tr');
    rows.forEach(function(tr){
        tr.style.display = tr.textContent.toLowerCase().indexOf(q) !== -1 ? '' : 'none';
    });
});
var head = document.querySelector('#challanDetailsTable').previousElementSibling;
if (head) head.appendChild(filterInput);
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
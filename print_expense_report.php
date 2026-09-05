<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Expense Report';

$fFrom = $_GET['from'] ?? '';
$fTo = $_GET['to'] ?? '';
$fPaidBy = $_GET['PiadBy'] ?? 'All';
$fCategory = $_GET['category'] ?? 'All';
$fSubCat = $_GET['subcatgory'] ?? 'All';
$showSub = (strtoupper($_GET['subCatgry'] ?? 'YES')) === 'YES';

$where = [];
$params = [];
$types = '';
if ($fFrom !== '') { $where[] = 'e.expense_date >= ?'; $params[] = $fFrom; $types .= 's'; }
if ($fTo !== '') { $where[] = 'e.expense_date <= ?'; $params[] = $fTo; $types .= 's'; }
if ($fPaidBy !== '' && $fPaidBy !== 'All') { $where[] = 'e.paid_by = ?'; $params[] = $fPaidBy; $types .= 's'; }
if ($fCategory !== '' && $fCategory !== 'All') { $where[] = 'e.category_id = ?'; $params[] = (int)$fCategory; $types .= 'i'; }
if ($fSubCat !== '' && $fSubCat !== 'All') { $where[] = 'e.sub_category_id = ?'; $params[] = (int)$fSubCat; $types .= 'i'; }

$sql = "SELECT e.*, c.name category_name, s.name sub_category_name
        FROM expenses e
        LEFT JOIN expense_categories c ON e.category_id = c.id
        LEFT JOIN expense_subs s ON e.sub_category_id = s.id";
if (count($where) > 0) { $sql .= ' WHERE ' . implode(' AND ', $where); }
$sql .= ' ORDER BY e.expense_date, e.expense_id';

$expenses = [];
if (count($params) > 0) {
    $st2 = db_prepare($sql);
    $st2->bind_param($types, ...$params);
    $st2->execute();
    $res = $st2->get_result();
} else {
    $res = db_query($sql);
}
while ($row = $res->fetch_assoc()) { $expenses[] = $row; }

$total = 0.0;
foreach ($expenses as $ex) { $total += (float) $ex['amount']; }

$schoolName = get_setting('school_name', 'School Name');
$schoolAddr = get_setting('school_address', '');

include __DIR__ . '/includes/header.php';
?>
<style>
@media print {
    .main-content { margin: 0; }
    .no-print { display: none !important; }
}
.report-header { text-align:center; margin-bottom:16px; }
.report-header h2 { font-size:20px; font-weight:800; color:#111827; margin:0 0 4px; }
.report-header p { margin:2px 0; color:#6B7280; font-size:13px; }
</style>

<div class="main-content">
    <div class="container-fluid">
        <div class="no-print" style="display:flex; align-items:center; justify-content:space-between; padding:14px 4px;">
            <h3 style="font-size:18px; font-weight:800; color:#111827; margin:0;"><i class="fa fa-print"></i> Expense Report</h3>
            <button onclick="window.print()" class="btn btn-success" style="color:#fff;"><i class="fa fa-print"></i> Print Report</button>
        </div>

        <div style="overflow-x:auto; background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:24px;">
            <div class="report-header">
                <h2><?php echo e($schoolName); ?></h2>
                <?php if ($schoolAddr !== ''): ?><p><?php echo e($schoolAddr); ?></p><?php endif; ?>
                <p><strong>EXPENSE REPORT</strong></p>
                <p>Period: <?php echo $fFrom !== '' ? e($fFrom) : 'All'; ?> To: <?php echo $fTo !== '' ? e($fTo) : 'Today'; ?> | Paid By: <?php echo e($fPaidBy === 'All' ? 'All' : $fPaidBy); ?></p>
            </div>

            <table class="table table-striped table-bordered" style="width:100%; background:#fff; margin-bottom:0;">
                <thead>
                    <tr>
                        <th width="5%">S.No</th>
                        <th width="20%">Expense Category</th>
                        <?php if ($showSub): ?><th width="18%">Sub Category</th><?php endif; ?>
                        <th width="27%">Expense Details</th>
                        <th width="12%">Date</th>
                        <th width="10%">Paid By</th>
                        <th width="10%" style="text-align:right;">Amount (Rs.)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($expenses) === 0): ?>
                        <tr><td colspan="7" style="text-align:center; color:#6B7280; padding:25px;">No record found.</td></tr>
                    <?php endif; ?>
                    <?php $i = 1; foreach ($expenses as $e): ?>
                        <tr>
                            <td><?php echo $i++; ?></td>
                            <td><?php echo e($e['category_name'] ?: '-'); ?></td>
                            <?php if ($showSub): ?><td><?php echo e($e['sub_category_name'] ?: '-'); ?></td><?php endif; ?>
                            <td><?php echo e($e['narration'] ?: $e['title']); ?></td>
                            <td><?php echo $e['expense_date'] ? date('d M Y', strtotime($e['expense_date'])) : '-'; ?></td>
                            <td><?php echo e($e['paid_by'] ?: '-'); ?></td>
                            <td style="text-align:right; color:#DC2626; font-weight:700;"><?php echo number_format($e['amount'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background:#F9FAFB;">
                        <th colspan="<?php echo $showSub ? 6 : 5; ?>" style="text-align:right;">Total Expenses</th>
                        <th style="color:#DC2626;"><?php echo number_format($total, 2); ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
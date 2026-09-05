<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Monthly Expenses Report';

// Filter defaults = current month
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-t');
$viewType = $_GET['view_type'] ?? 'expense';
$subCat = $_GET['sub_cat'] ?? 'All';
$userId = $_GET['user_id'] ?? 'All';

$subsList = [];
$res = db_query("SELECT expense_subs.*, expense_categories.name category_name
                 FROM expense_subs
                 LEFT JOIN expense_categories ON expense_subs.expense_id = expense_categories.id
                 WHERE expense_subs.status=1 ORDER BY expense_subs.name");
while ($row = $res->fetch_assoc()) { $subsList[] = $row; }

$users = [];
$res = db_query("SELECT user_id, full_name FROM users WHERE status=1 ORDER BY full_name");
while ($row = $res->fetch_assoc()) { $users[] = $row; }

$where = [];
$params = [];
$types = '';
if ($startDate !== '') { $where[] = 'DATE(e.expense_date) >= ?'; $params[] = $startDate; $types .= 's'; }
if ($endDate !== '') { $where[] = 'DATE(e.expense_date) <= ?'; $params[] = $endDate; $types .= 's'; }
if ($subCat !== '' && $subCat !== 'All') { $where[] = 'e.sub_category_id = ?'; $params[] = (int)$subCat; $types .= 'i'; }
if ($userId !== '' && $userId !== 'All') { $where[] = 'e.created_by = ?'; $params[] = (int)$userId; $types .= 'i'; }

$sql = "SELECT e.*, c.name category_name, s.name sub_category_name, u.full_name added_by
        FROM expenses e
        LEFT JOIN expense_categories c ON e.category_id = c.id
        LEFT JOIN expense_subs s ON e.sub_category_id = s.id
        LEFT JOIN users u ON e.created_by = u.user_id";
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

function isSalaryRow($row) {
    foreach (['%salary%', '%salaries%', '%wages%'] as $pat) {
        if (stripos($row['sub_category_name'] ?? '', trim($pat, '%')) !== false) return true;
        if (stripos($row['category_name'] ?? '', trim($pat, '%')) !== false) return true;
    }
    return false;
}

$excludeSalary = ($viewType !== 'expense_salary');
$visible = [];
foreach ($expenses as $e) {
    if ($excludeSalary && isSalaryRow($e)) { continue; }
    $visible[] = $e;
}

$total = 0.0;
foreach ($visible as $e) { $total += (float) $e['amount']; }

include __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="https://cdn.datatables.net/1.10.13/css/jquery.dataTables.min.css">
<style>
.page-header-section { margin-bottom:14px; }
.page-header-section h2 { font-size:18px; font-weight:800; color:#111827; margin:0; }
.record-count-badge { display:inline-block; font-size:11px; font-weight:700; color:#377DFF; background:#E9F2FF; border-radius:999px; padding:4px 10px; margin-left:8px; vertical-align:middle; }
.breadcrumb-modern { display:flex; align-items:center; gap:8px; font-size:12.5px; color:#6B7280; margin:6px 0 0; padding:0; list-style:none; }
.breadcrumb-modern a { color:#377DFF; text-decoration:none; }
.breadcrumb-modern i { font-size:11px; color:#9CA3AF; }
.filter-panel { background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:16px; margin-bottom:16px; }
.filter-panel h4 { font-size:14px; font-weight:800; color:#111827; margin:0 0 10px; }
.table-container { background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:0 0 16px; overflow-x:auto; }
.table-container h4 { font-size:15px; font-weight:800; color:#111827; margin:0; padding:14px 16px; border-bottom:2px solid #F3F4F6; }
.dataTables_wrapper { padding: 0 12px 12px; }
.total-amount-box { display:inline-flex; align-items:center; gap:8px; background:#FEF2F2; border:1px solid #FECACA; color:#DC2626; font-weight:800; font-size:14px; border-radius:12px; padding:10px 16px; margin:14px 16px 0; }
</style>

<div class="main-content">
    <div class="container-fluid">
        <div class="page-header-section">
            <h2><i class="fa fa-chart-line"></i> Monthly Expenses Report <span class="record-count-badge"><?php echo count($visible); ?> Records</span></h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb-modern">
                    <li><a href="<?php echo BASE_URL; ?>dashboard.php"><i class="fa fa-home"></i> Dashboard</a></li>
                    <li><i class="fa fa-angle-right"></i></li>
                    <li><a href="<?php echo BASE_URL; ?>manage_expenses.php">Financial Management</a></li>
                    <li><i class="fa fa-angle-right"></i></li>
                    <li><span>Monthly Expenses Report</span></li>
                </ol>
            </nav>
        </div>

        <div class="filter-panel">
            <h4><i class="fa fa-filter"></i> Filter Expenses</h4>
            <form action="monthly_expenses_report.php" method="get">
                <div class="row">
                    <div class="col-md-3 col-xs-12">
                        <div class="form-group">
                            <label class="required"><i class="fa fa-calendar"></i> From Date</label>
                            <input type="date" name="start_date" class="form-control" value="<?php echo e($startDate); ?>">
                        </div>
                    </div>
                    <div class="col-md-2 col-xs-12">
                        <div class="form-group">
                            <label class="required"><i class="fa fa-calendar"></i> To Date</label>
                            <input type="date" name="end_date" class="form-control" value="<?php echo e($endDate); ?>">
                        </div>
                    </div>
                    <div class="col-md-2 col-xs-12">
                        <div class="form-group">
                            <label><i class="fa fa-eye"></i> View</label>
                            <select name="view_type" class="form-control">
                                <option value="expense" <?php echo $viewType === 'expense' ? 'selected' : ''; ?>>Show Expense</option>
                                <option value="expense_salary" <?php echo $viewType === 'expense_salary' ? 'selected' : ''; ?>>Show Expense with Salary</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3 col-xs-12">
                        <div class="form-group">
                            <label><i class="fa fa-tag"></i> Sub Category</label>
                            <select name="sub_cat" class="form-control">
                                <option value="All">All Sub Categories</option>
                                <?php foreach ($subsList as $s): ?>
                                    <option value="<?php echo $s['id']; ?>" <?php echo $subCat === (string)$s['id'] ? 'selected' : ''; ?>><?php echo e($s['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2 col-xs-12">
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-primary btn-block"><i class="fa fa-search"></i> Search</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <div class="table-container">
            <h4><i class="fa fa-list"></i> Expense Records</h4>
            <table id="datatable" class="table table-striped table-bordered" style="width:100%; background:#fff; margin-bottom:0;">
                <thead>
                    <tr>
                        <th width="5%">S.No</th>
                        <th width="15%">Category</th>
                        <th width="15%">Sub Category</th>
                        <th width="28%">Details</th>
                        <th width="12%">Date</th>
                        <th width="12%" style="text-align:right;">Amount (Rs.)</th>
                        <th width="13%">Added By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($visible) === 0): ?>
                        <tr><td colspan="7" class="dataTables_empty">No data available in table</td></tr>
                    <?php endif; ?>
                    <?php $i = 1; foreach ($visible as $e): ?>
                        <tr>
                            <td><?php echo $i++; ?></td>
                            <td><strong><?php echo e($e['category_name'] ?: '-'); ?></strong></td>
                            <td><?php echo e($e['sub_category_name'] ?: '-'); ?></td>
                            <td><?php echo e($e['narration'] ?: $e['title']); ?></td>
                            <td><?php echo $e['expense_date'] ? date('d M Y', strtotime($e['expense_date'])) : '-'; ?></td>
                            <td style="text-align:right; color:#DC2626; font-weight:700;"><?php echo number_format($e['amount'], 2); ?></td>
                            <td><?php echo e($e['added_by'] ?: '-'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background-color:#f8f9fa; font-weight:bold;">
                        <td colspan="5" style="text-align:right; padding:15px;">Grand Total:</td>
                        <td style="text-align:right; padding:15px; font-size:16px; color:#e74c3c;"><?php echo get_setting('currency_symbol', 'Rs.') . ' ' . number_format($total, 2); ?></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
            <div class="total-amount-box">
                <i class="fa fa-calculator"></i>
                <span>Grand Total: <?php echo get_setting('currency_symbol', 'Rs.') . ' ' . number_format($total, 2); ?></span>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.datatables.net/1.10.13/js/jquery.dataTables.min.js"></script>
<script>
$(document).ready(function(){
    $('#datatable').DataTable({ order: [], pageLength: 10 });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
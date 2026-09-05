<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Monthly Attendance Report';

try {
    db_query("CREATE TABLE IF NOT EXISTS staff_attendance (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        att_date DATE NOT NULL,
        status VARCHAR(10) NOT NULL DEFAULT 'present',
        time_in TIME NULL, time_out TIME NULL,
        UNIQUE KEY uq_emp_attdate (employee_id, att_date)
    ) ENGINE=InnoDB");
} catch (\Throwable $e) { /* table exists */ }

// Sessions 2018-2019 .. 2030-2031
$currentSession = get_setting('session_year', '2026-2027');
$sessionOptions = [];
for ($s = 2018; $s <= 2030; $s++) { $sessionOptions[$s . '-' . ($s + 1)] = $s . '-' . ($s + 1); }

$sel_session = $_GET['session'] ?? $currentSession;
if (!isset($sessionOptions[$sel_session])) { $sel_session = $currentSession; }
$sel_year = (int) substr($sel_session, 0, 4);
if ($sel_year < 1900) { $sel_year = (int) date('Y'); }

$sel_month = (int) ($_GET['month'] ?? (int) date('m'));
if ($sel_month < 1 || $sel_month > 12) { $sel_month = (int) date('m'); }

$search = trim($_GET['search'] ?? '');

$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $sel_month, $sel_year);
$firstDay = sprintf('%04d-%02d-01', $sel_year, $sel_month);
$lastDay  = sprintf('%04d-%02d-%02d', $sel_year, $sel_month, $daysInMonth);

// ---- CSV export -----------------------------------------------------------
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $expEmps = get_emp_records($sel_year, $sel_month, $search);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="monthly_attendance_' . $sel_month . '_' . $sel_year . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, array_merge(['Employee'], range(1, $daysInMonth)));
    foreach ($expEmps as $emp) {
        $row = [];
        $row[] = $emp['name'];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $stt = $emp['days'][$d] ?? '';
            $row[] = ['present'=>'P','absent'=>'A','late'=>'L','leave'=>'Lv','short_leave'=>'SL'][$stt] ?? '';
        }
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}

function get_emp_records($year, $month, $searchStr) {
    $days = cal_days_in_month(CAL_GREGORIAN, $month, $year);
    $first = sprintf('%04d-%02d-01', $year, $month);
    $last  = sprintf('%04d-%02d-%02d', $year, $month, $days);

    $sql = "SELECT e.emp_id, e.first_name, e.last_name, e.department FROM employees e
            WHERE e.status = 1
              AND EXISTS (SELECT 1 FROM staff_attendance sa WHERE sa.employee_id = e.emp_id AND sa.att_date BETWEEN ? AND ?)";
    $params = [$first, $last]; $types = 'ss';
    if ($searchStr !== '') {
        $sql .= " AND (e.first_name LIKE ? OR e.last_name LIKE ? OR CONCAT(e.first_name, ' ', e.last_name) LIKE ?)";
        $like = '%' . $searchStr . '%';
        $params[] = $like; $params[] = $like; $params[] = $like;
        $types .= 'sss';
    }
    $sql .= " ORDER BY e.first_name, e.last_name";

    $stmt = db_prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    $emps = [];
    while ($row = $res->fetch_assoc()) {
        $emps[$row['emp_id']] = [
            'id'   => $row['emp_id'],
            'name' => trim($row['first_name'] . ' ' . $row['last_name']),
            'days' => [],
        ];
    }
    if (!$emps) return [];

    $ids = implode(',', array_keys($emps));
    $ra = db_query("SELECT employee_id, att_date, status FROM staff_attendance WHERE att_date BETWEEN '$first' AND '$last' AND employee_id IN ($ids)");
    while ($row = $ra->fetch_assoc()) {
        $emps[$row['employee_id']]['days'][(int) date('j', strtotime($row['att_date']))] = $row['status'];
    }
    return array_values($emps);
}

$empRecords = get_emp_records($sel_year, $sel_month, $search);

$isPrint = isset($_GET['print']) && $_GET['print'] === '1';

include __DIR__ . '/includes/header.php';
?>
<style>
.panel-emp { margin-top:10px; }
.box-filter { display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end; background:#fff; border:1px solid #E5E7EB; border-radius:10px; padding:12px; margin:12px 0; }
.att-cell { width:26px; height:26px; display:inline-flex; align-items:center; justify-content:center; font-size:10.5px; font-weight:700; border-radius:6px; }
.att-P { background:#DCFCE7; color:#16A34A; }
.att-A { background:#FEE2E2; color:#DC2626; }
.att-L { background:#DBEAFE; color:#377DFF; }
.att-Lv { background:#FFF7E0; color:#F59E0B; }
.att-SL { background:#EDE9FE; color:#7C3AED; }
.att-- { background:#F3F4F6; color:#D1D5DB; }
@media print {
    .no-print { display:none!important; }
    body { background:#fff; }
    .table { width:100% !important; }
}
</style>

<div class="main-content">
    <div class="container-fluid">
        <div style="padding:10px 0 8px 0; font-size:13px;" class="no-print">
            <a href="<?php echo BASE_URL; ?>dashboard.php">Dashboard</a> &nbsp; <i class="fa fa-angle-double-right"></i> &nbsp;
            Attendance Report
        </div>

        <div class="panel panel-default panel-emp">
            <div class="panel-heading">
                <b style="font-size:22px; color:gray;">Monthly Attendance: &nbsp;</b> (<?php echo count($empRecords); ?> Employee Records)
            </div>
            <div class="panel-body">
                <form class="box-filter no-print" action="<?php echo BASE_URL; ?>monthly_attendance.php" method="get">
                    <div class="col-md-3 col-sm-12 col-xs-12" style="padding: 8px;">
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="required">Year / Session</label>
                            <select name="session" class="form-control" style="height:40px;">
                                <?php foreach ($sessionOptions as $val => $label): ?>
                                    <option value="<?php echo e($val); ?>" <?php echo $sel_session === $val ? 'selected' : ''; ?>><?php echo e($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-12 col-xs-12" style="padding: 8px;">
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="required">Month</label>
                            <select name="month" id="month" class="form-control" style="height:40px;">
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?php echo $m; ?>" <?php echo $sel_month === $m ? 'selected' : ''; ?>><?php echo date('F', mktime(0,0,0,$m,1)); ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4 col-sm-12 col-xs-12" style="padding: 8px;">
                        <div class="form-group" style="margin-bottom:0;">
                            <label>Search Employee</label>
                            <input type="text" name="search" class="form-control" style="height:40px;" value="<?php echo e($search); ?>" placeholder="Type name...">
                        </div>
                    </div>
                    <div class="col-md-4 col-sm-12 col-xs-12" style="padding:8px;">
                        <div class="form-group" style="margin-bottom:0;">
                            <button type="submit" class="btn btn-primary" style="margin-top:23px;"><i class="fa fa-search"></i> Search Attendance</button>
                            <a target="_blank" href="<?php echo BASE_URL; ?>monthly_attendance.php?session=<?php echo e($sel_session); ?>&month=<?php echo $sel_month; ?>&print=1">
                                <button type="button" class="btn btn-success" style="margin-top:23px;"><i class="fa fa-print"></i> Print</button>
                            </a>
                            <a style="margin-top:23px;" href="<?php echo BASE_URL; ?>monthly_attendance.php?session=<?php echo e($sel_session); ?>&month=<?php echo $sel_month; ?>&export=csv" class="btn btn-success"><i class="fa fa-file-excel-o"></i> Export CSV</a>
                        </div>
                    </div>
                    <div class="clearfix"></div>
                </form>

                <?php if (count($empRecords) === 0): ?>
                    <div style="text-align:center; color:#6B7280; padding:40px; border:1px dashed #E5E7EB; border-radius:10px;">
                        No attendance records found for <?php echo date('F Y', mktime(0,0,0,$sel_month,1,$sel_year)); ?>.
                    </div>
                <?php else: ?>
                    <div style="overflow-x:auto;">
                        <table id="listofstudents3" class="table table-striped table-bordered" style="width:100%; font-size:11.5px;">
                            <thead>
                                <tr>
                                    <th style="min-width:150px;">Name</th>
                                    <th style="min-width:20px;">
                                        <table border="1" width="100%" class="responsive">
                                            <tbody><tr>
                                                <?php for ($d = 1; $d <= $daysInMonth; $d++): ?>
                                                    <th width="2%" class="thStyle" style="padding:0px; text-align:center;"><?php echo $d; ?></th>
                                                <?php endfor; ?>
                                            </tr></tbody>
                                        </table>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($empRecords as $emp): ?>
                                    <tr>
                                        <td><strong><?php echo e($emp['name']); ?></strong></td>
                                        <td>
                                            <table border="1" width="100%" class="responsive">
                                                <tbody><tr>
                                                    <?php for ($d = 1; $d <= $daysInMonth; $d++):
                                                        $stt = $emp['days'][$d] ?? '';
                                                        $cls = ['present'=>'att-P','absent'=>'att-A','late'=>'att-L','leave'=>'att-Lv','short_leave'=>'att-SL'][$stt] ?? 'att--';
                                                        $letter = ['present'=>'P','absent'=>'A','late'=>'L','leave'=>'Lv','short_leave'=>'SL'][$stt] ?? '--';
                                                    ?>
                                                        <th width="2%" style="padding:0px; text-align:center; background-color:white; color:black;">
                                                            <span class="att-cell <?php echo $cls; ?>"><?php echo $letter; ?></span>
                                                        </th>
                                                    <?php endfor; ?>
                                                </tr></tbody>
                                            </table>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
<?php if ($isPrint): ?>
window.addEventListener('load', function(){ window.print(); });
<?php endif; ?>
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
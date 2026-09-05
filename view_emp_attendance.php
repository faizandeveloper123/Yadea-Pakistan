<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'View Employee Attendance';

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

$message = '';
$infoMsg = '';

$date = trim($_GET['date'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) { $date = date('Y-m-d'); }

$sel_session = $_GET['session'] ?? $currentSession;
if (!isset($sessionOptions[$sel_session])) { $sel_session = $currentSession; }
$sel_year = (int) substr($sel_session, 0, 4);

$statusFilter = $_GET['attendance_status'] ?? '';
$statusMap = ['P' => 'present', 'A' => 'absent', 'LA' => 'late', 'L' => 'leave', 'SL' => 'short_leave'];
$sel_status_word = $statusMap[$statusFilter] ?? null;

$searchQ = trim($_GET['search'] ?? '');

// ---- POST handlers ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'MarkAttendance') {
        $d = trim($_POST['date'] ?? $date);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) { $d = date('Y-m-d'); }
        $marks = $_POST['att'] ?? [];
        $saved = 0;
        $st1 = db_prepare("INSERT INTO staff_attendance (employee_id, att_date, status) VALUES (?, ?, ?)
                           ON DUPLICATE KEY UPDATE status=VALUES(status)");
        $st2 = db_prepare("INSERT INTO employee_attendance (emp_id, date, status) VALUES (?, ?, ?)
                           ON DUPLICATE KEY UPDATE status=VALUES(status)");
        foreach ($marks as $eid => $status) {
            $eid = (int) $eid;
            if ($status === '' || $eid <= 0) continue;
            $st1->bind_param('iss', $eid, $d, $status);
            $st1->execute();
            $st2->bind_param('iss', $eid, $d, $status);
            $st2->execute();
            $saved++;
        }
        $message = "Attendance saved for $saved employees!";
    }

    if ($action === 'UpdateEmpAttendance') {
        header('Content-Type: application/json');
        $eid = (int) ($_POST['emp_id'] ?? 0);
        $d = $_POST['attendance_date'] ?? '';
        $raw = $_POST['attendance_status'] ?? '';
        $time_in = ($_POST['time_in'] ?? '') ?: null;
        $time_out = ($_POST['time_out'] ?? '') ?: null;
        $word = $statusMap[$raw] ?? null;
        if ($eid <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) || $word === null) {
            echo json_encode(['success' => false, 'message' => 'Invalid data provided.']);
            exit;
        }
        try {
            $st = db_prepare("INSERT INTO staff_attendance (employee_id, att_date, status, time_in, time_out)
                              VALUES (?, ?, ?, ?, ?)
                              ON DUPLICATE KEY UPDATE status=VALUES(status), time_in=VALUES(time_in), time_out=VALUES(time_out)");
            $st->bind_param('issss', $eid, $d, $word, $time_in, $time_out);
            $st->execute();
            db_prepare("INSERT INTO employee_attendance (emp_id, date, status) VALUES (?, ?, ?)
                        ON DUPLICATE KEY UPDATE status=VALUES(status)")->bind_param('iss', $eid, $d, $word)->execute();
            echo json_encode(['success' => true, 'message' => 'Attendance updated successfully!']);
        } catch (\Throwable $er) {
            echo json_encode(['success' => false, 'message' => $er->getMessage()]);
        }
        exit;
    }

    if ($action === 'DeleteEmp_Attendance') {
        $eid = (int) ($_POST['emp_id'] ?? 0);
        $d = $_POST['date'] ?? '';
        if ($eid > 0 && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
            db_prepare("DELETE FROM staff_attendance WHERE employee_id=? AND att_date=?")->bind_param('is', $eid, $d)->execute();
            db_prepare("DELETE FROM employee_attendance WHERE emp_id=? AND date=?")->bind_param('is', $eid, $d)->execute();
            $infoMsg = 'Attendance record deleted.';
        }
    }
}

// ---- Record list (prefers staff_attendance, falls back to employee_attendance)
$whereFilters = []; // shared WHERE fragments for employee join
$paramsSa = [$date, $sel_year]; $typesSa = 'si';
$paramsEa = [$date, $sel_year]; $typesEa = 'si';

if ($statusFilter !== '' && $sel_status_word !== null) {
    $paramsSa[] = $sel_status_word; $typesSa .= 's';
    $paramsEa[] = $sel_status_word; $typesEa .= 's';
}
$like = '';
if ($searchQ !== '') {
    $like = '%' . $searchQ . '%';
    $paramsSa[] = $like; $paramsSa[] = $like; $paramsSa[] = $like; $paramsSa[] = $like;
    $paramsEa[] = $like; $paramsEa[] = $like; $paramsEa[] = $like; $paramsEa[] = $like;
    $typesSa .= 'ssss';
    $typesEa .= 'ssss';
}

$searchSql = '';
if ($like !== '') {
    $searchSql = " AND (e.first_name LIKE ? OR e.last_name LIKE ? OR CONCAT(e.first_name,' ',e.last_name) LIKE ? OR e.department LIKE ?)";
}
$statusSql = ($statusFilter !== '' && $sel_status_word !== null) ? ' AND sa.status = ?' : '';
$sqlSa = "SELECT e.emp_id, e.first_name, e.last_name, e.department, e.designation,
                 sa.att_date, sa.status AS att_status, sa.time_in, sa.time_out
          FROM employees e
          INNER JOIN staff_attendance sa ON sa.employee_id = e.emp_id
          WHERE sa.att_date = ? AND YEAR(sa.att_date) = ? " . $statusSql . $searchSql . "
          ORDER BY sa.att_date DESC, e.first_name, e.last_name
          LIMIT 500";

$statusSqlEa = ($statusFilter !== '' && $sel_status_word !== null) ? ' AND ea.status = ?' : '';
$sqlEa = "SELECT e.emp_id, e.first_name, e.last_name, e.department, e.designation,
                 ea.date AS att_date, ea.status AS att_status, NULL AS time_in, NULL AS time_out
          FROM employees e
          INNER JOIN employee_attendance ea ON ea.emp_id = e.emp_id
          WHERE ea.date = ? AND YEAR(ea.date) = ? " . $statusSqlEa . $searchSql . "
          ORDER BY ea.date DESC, e.first_name, e.last_name
          LIMIT 500";

$records = [];
$seen = [];
$stmt = db_prepare($sqlSa);
$stmt->bind_param($typesSa, ...$paramsSa);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    if ($row['att_date'] === null) continue;
    $key = $row['emp_id'] . '|' . $row['att_date'];
    $seen[$key] = true;
    $records[] = $row;
}
$stmt = db_prepare($sqlEa);
$stmt->bind_param($typesEa, ...$paramsEa);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    if ($row['att_date'] === null) continue;
    $key = $row['emp_id'] . '|' . $row['att_date'];
    if (isset($seen[$key])) continue;
    $seen[$key] = true;
    $records[] = $row;
}
usort($records, function ($a, $b) { return strcmp($b['att_date'], $a['att_date']); });

$batchDate = date('Y-m-d');
$emps = [];
$res = db_query("SELECT emp_id, first_name, last_name, designation FROM employees WHERE status=1 ORDER BY first_name, last_name");
while ($row = $res->fetch_assoc()) {
    $sa = db_query("SELECT status FROM staff_attendance WHERE employee_id={$row['emp_id']} AND att_date='$batchDate'")->fetch_assoc();
    if (!$sa) { $sa = db_query("SELECT status FROM employee_attendance WHERE emp_id={$row['emp_id']} AND date='$batchDate'")->fetch_assoc(); }
    $row['att_status'] = $sa['status'] ?? '';
    $emps[] = $row;
}

include __DIR__ . '/includes/header.php';
?>
<style>
.search-bar-student { display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end; background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:16px; margin-bottom:16px; }
.att-radio label { margin-right:14px; font-weight:600; cursor:pointer; }
.att-badge { display:inline-block; padding:3px 10px; border-radius:20px; font-size:11.5px; font-weight:700; }
.att-P { background:#DCFCE7; color:#16A34A; }
.att-A { background:#FEE2E2; color:#DC2626; }
.att-L { background:#FFF7E0; color:#F59E0B; }
.att-LA { background:#DBEAFE; color:#377DFF; }
.att-SL { background:#EDE9FE; color:#7C3AED; }
</style>

<div class="main-content">
    <div class="container-fluid">
        <?php if ($message): ?><div class="alert alert-success"><?php echo e($message); ?></div><?php endif; ?>
        <?php if ($infoMsg): ?><div class="alert alert-warning"><?php echo e($infoMsg); ?></div><?php endif; ?>

        <div style="padding:10px 0 5px 0;">
            <a href="<?php echo BASE_URL; ?>dashboard.php" style="color:#337ab7; text-decoration:none;"><i class="fa fa-dashboard"></i> Dashboard</a>
            <i class="fa fa-angle-double-right" style="color:#999; margin:0 5px;"></i>
            <span style="color:#666;">View Staff Attendance</span>
        </div>

        <form class="" action="<?php echo BASE_URL; ?>view_emp_attendance.php" method="get">
            <div class="panel panel-default" style="margin-top:8px;">
                <div class="panel-heading" style="padding:12px 15px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap;">
                        <div style="margin-bottom:5px;">
                            <b style="font-size:20px; color:#333;">View Employee Attendance</b>
                            <span style="color:#666; font-size:14px; margin-left:10px;">(<?php echo count($records); ?> Employee Records)</span>
                        </div>
                        <div style="margin-bottom:5px;">
                            <input type="hidden" name="session" value="<?php echo e($sel_session); ?>">
                            <a onclick="$('#advanceSearch').slideToggle('fast')" class="btn btn-primary btn-sm" style="cursor:pointer; margin-right:5px;">
                                <i class="fa fa-search"></i> Advance Search
                            </a>
                            <a href="<?php echo BASE_URL; ?>send_emp_absnt_msgs.php" class="btn btn-info btn-sm" style="margin-right:5px;">
                                <i class="fa fa-envelope"></i> Staff Absent Report
                            </a>
                            <a href="<?php echo BASE_URL; ?>mark_emp_attendance.php" class="btn btn-success btn-sm">
                                <i class="fa fa-check"></i> Mark Attendance
                            </a>
                            <a href="<?php echo BASE_URL; ?>qr_attendance_scan.php" class="btn btn-sm" style="background:#8e44ad; color:#fff; margin-left:5px;">
                                <i class="fa fa-qrcode"></i> QR Attendance
                            </a>
                        </div>
                    </div>
                </div>
                <div class="panel-body" style="padding:15px;">

                    <div class="col-md-12" id="advanceSearch" style="display:none; padding:12px; background-color:#f5f5f5; border-radius:5px; margin-bottom:10px;">
                        <div class="row">
                            <div class="col-md-2 col-xs-12" style="padding:5px 10px;">
                                <div class="form-group">
                                    <label style="font-weight:600;">Session</label>
                                    <select name="session" class="form-control">
                                        <?php foreach ($sessionOptions as $val => $label): ?>
                                            <option value="<?php echo e($val); ?>" <?php echo $sel_session === $val ? 'selected' : ''; ?>><?php echo e($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-2 col-xs-12" style="padding:5px 10px;">
                                <div class="form-group">
                                    <label class="required" style="font-weight:600;">Date</label>
                                    <input class="form-control" type="date" name="date" value="<?php echo e($date); ?>">
                                </div>
                            </div>
                            <div class="col-md-2 col-xs-12" style="padding:5px 10px;">
                                <div class="form-group">
                                    <label style="font-weight:600;">Attendance Status</label>
                                    <select name="attendance_status" class="form-control">
                                        <option value="">All Status</option>
                                        <option value="P" <?php echo $statusFilter === 'P' ? 'selected' : ''; ?>>Present (P)</option>
                                        <option value="A" <?php echo $statusFilter === 'A' ? 'selected' : ''; ?>>Absent (A)</option>
                                        <option value="L" <?php echo $statusFilter === 'L' ? 'selected' : ''; ?>>Leave (L)</option>
                                        <option value="LA" <?php echo $statusFilter === 'LA' ? 'selected' : ''; ?>>Late Arrival (LA)</option>
                                        <option value="SL" <?php echo $statusFilter === 'SL' ? 'selected' : ''; ?>>Short Leave (SL)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-2 col-xs-12" style="padding:5px 10px;">
                                <div class="form-group">
                                    <label style="font-weight:600;">Search</label>
                                    <input type="text" name="search" class="form-control" placeholder="Name / dept..." value="<?php echo e($searchQ); ?>">
                                </div>
                            </div>
                            <div class="col-md-4 col-xs-12" style="padding:5px 10px;">
                                <div class="form-group">
                                    <label style="opacity:0;">Action</label>
                                    <div style="display:flex; gap:5px; flex-wrap:wrap;">
                                        <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> Search</button>
                                        <a href="<?php echo BASE_URL; ?>print_emp_attendance.php?session=<?php echo e($sel_session); ?>&date=<?php echo e($date); ?>&attendance_status=<?php echo e($statusFilter); ?>" target="_blank" class="btn btn-info"><i class="fa fa-print"></i> Print Report</a>
                                        <button type="button" class="btn btn-danger" onclick="deleteAllAttendance();"><i class="fa fa-trash"></i> Delete Attendance</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="clearfix"></div>
                </div>
            </div>
        </form>

        <form method="post" action="<?php echo BASE_URL; ?>view_emp_attendance.php" class="form-style-7">
            <input type="hidden" name="action" value="MarkAttendance">
            <input type="hidden" name="date" value="<?php echo e($batchDate); ?>">
            <div class="panel panel-default">
                <div class="panel-heading" style="padding:10px 15px;">
                    <b style="font-size:16px; color:#333;">Employee Attendance Records</b>
                </div>
                <div class="panel-body" style="padding:15px;">
                    <div style="margin:0 0 10px 0; display:flex; gap:8px; flex-wrap:wrap; align-items:center;" class="record-actions">
                        <input type="text" id="recordSearch" class="form-control" style="max-width:260px; display:inline-block;" placeholder="Search:">
                    </div>
                    <div style="overflow-x:auto;">
                        <table id="listofstudents" class="table table-striped table-bordered" style="width:100%; margin-top:5px;">
                            <thead style="background-color:#f8f9fa;">
                                <tr>
                                    <th width="4%" style="text-align:center;">S.No</th>
                                    <th width="13%">First Name</th>
                                    <th width="13%">Last Name</th>
                                    <th width="12%">Department</th>
                                    <th width="12%">Designation</th>
                                    <th width="11%" style="text-align:center;">Date</th>
                                    <th width="12%" style="text-align:center;">Time In / Out</th>
                                    <th width="11%" style="text-align:center;">Attendance</th>
                                    <th width="8%" style="text-align:center;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($records) === 0): ?>
                                    <tr><td colspan="9" style="text-align:center; color:#6B7280; padding:30px;" class="dataTables_empty">No data available in table</td></tr>
                                <?php endif; ?>
                                <?php $i = 1; foreach ($records as $r):
                                    $code = ['present'=>'P','absent'=>'A','leave'=>'L','late'=>'LA','short_leave'=>'SL'][$r['att_status']] ?? 'P';
                                    $cls = ['present'=>'att-P','absent'=>'att-A','leave'=>'att-L','late'=>'att-LA','short_leave'=>'att-SL'][$r['att_status']] ?? 'att-P';
                                    $badge = ['present'=>'Present','absent'=>'Absent','leave'=>'Leave','late'=>'Late Arrival','short_leave'=>'Short Leave'][$r['att_status']] ?? $r['att_status'];
                                ?>
                                <tr data-search="<?php echo e(mb_strtolower(($r['first_name'].' '.$r['last_name'].' '.($r['department']??'')), 'UTF-8')); ?>">
                                    <td style="text-align:center;"><?php echo $i++; ?></td>
                                    <td><?php echo e($r['first_name']); ?></td>
                                    <td><?php echo e($r['last_name']); ?></td>
                                    <td><?php echo e($r['department'] ?? '-'); ?></td>
                                    <td><?php echo e($r['designation'] ?? '-'); ?></td>
                                    <td style="text-align:center;"><?php echo $r['att_date'] ? date('d M Y', strtotime($r['att_date'])) : '-'; ?></td>
                                    <td style="text-align:center; font-size:12px;">
                                        <?php echo ($r['time_in'] ?? null) ? date('h:i A', strtotime($r['time_in'])) : '-'; ?>
                                        /
                                        <?php echo ($r['time_out'] ?? null) ? date('h:i A', strtotime($r['time_out'])) : '-'; ?>
                                    </td>
                                    <td style="text-align:center;"><span class="att-badge <?php echo $cls; ?>"><?php echo $code; ?> <?php echo e($badge); ?></span></td>
                                    <td style="text-align:center; white-space:nowrap;">
                                        <button type="button" class="btn btn-xs btn-info edit-attendance-btn"
                                            data-id="<?php echo $r['emp_id']; ?>" data-emp-id="<?php echo $r['emp_id']; ?>"
                                            data-emp-name="<?php echo e($r['first_name'] . ' ' . $r['last_name']); ?>"
                                            data-attendance="<?php echo e($badge); ?>"
                                            data-time-in="<?php echo e($r['time_in'] ?? ''); ?>"
                                            data-time-out="<?php echo e($r['time_out'] ?? ''); ?>"
                                            data-date="<?php echo e($r['att_date']); ?>"><i class="fa fa-edit"></i></button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <div style="font-size:12px; color:#6B7280; margin-top:6px;">
                            Showing <?php echo count($records); ?> to <?php echo count($records); ?> of <?php echo count($records); ?> entries
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <form method="post" action="<?php echo BASE_URL; ?>view_emp_attendance.php" class="search-bar-student" style="border-radius:0 0 14px 14px;">
            <input type="hidden" name="action" value="MarkAttendance">
            <input type="hidden" name="date" value="<?php echo e($batchDate); ?>">
            <div class="col-md-12" style="padding:0 0 6px 0;">
                <b style="font-size:16px; color:#333;"><i class="fa fa-calendar-check-o"></i> Quick Mark Attendance — <?php echo date('d M Y', strtotime($batchDate)); ?></b>
            </div>
            <table class="table table-striped table-bordered" style="width:100%; background:#fff; margin-bottom:0;">
                <thead>
                    <tr><th>#</th><th>Employee</th><th>Designation</th><th style="width:320px; text-align:center;">Attendance</th></tr>
                </thead>
                <tbody>
                    <?php if (count($emps) === 0): ?>
                        <tr><td colspan="4" style="text-align:center; color:#6B7280; padding:30px;">No employees found.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($emps as $em): $cur = $em['att_status']; ?>
                        <tr>
                            <td><?php echo $em['emp_id']; ?></td>
                            <td><strong><?php echo e($em['first_name']); ?> <?php echo e($em['last_name']); ?></strong></td>
                            <td><?php echo e($em['designation'] ?? '-'); ?></td>
                            <td class="att-radio" style="text-align:center;">
                                <?php foreach (['present' => 'P', 'absent' => 'A', 'late' => 'L', 'leave' => 'Lv'] as $key => $lbl): ?>
                                    <label><input type="radio" name="att[<?php echo $em['emp_id']; ?>]" value="<?php echo $key; ?>" <?php echo $cur === $key ? 'checked' : ''; ?>> <?php echo $lbl; ?></label>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div style="width:100%; padding:10px 0 0 0; text-align:right;">
                <button type="submit" class="btn btn-success" style="padding:10px 30px;"><i class="fa fa-save"></i> Save Attendance</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Attendance Modal -->
<div class="modal fade" id="editAttendanceModal" tabindex="-1" role="dialog" aria-labelledby="editAttendanceModalLabel">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title" id="editAttendanceModalLabel">Edit Employee Attendance</h4>
      </div>
      <div class="modal-body">
        <form id="editAttendanceForm">
          <input type="hidden" id="attendance_id" name="attendance_id">
          <input type="hidden" id="emp_id" name="emp_id">
          <input type="hidden" id="attendance_date" name="attendance_date">
          <input type="hidden" id="time_in" name="time_in">
          <input type="hidden" id="time_out" name="time_out">
          <div class="form-group">
            <label>Employee Name:</label>
            <input type="text" class="form-control" id="employee_name" readonly="">
          </div>
          <div class="form-group">
            <label>Date:</label>
            <input type="text" class="form-control" id="display_date" readonly="">
          </div>
          <div class="form-group">
            <label>Current Attendance Status:</label>
            <input type="text" class="form-control" id="current_attendance" readonly="">
          </div>
          <div class="form-group">
            <label>Time In / Time Out:</label>
            <div class="row">
              <div class="col-xs-6"><input type="time" class="form-control" id="time_in_edit" value=""></div>
              <div class="col-xs-6"><input type="time" class="form-control" id="time_out_edit" value=""></div>
            </div>
          </div>
          <div class="form-group">
            <label>Change Attendance Status:</label>
            <select class="form-control" id="attendance_status" name="attendance_status" required="">
              <option value="">Select Status</option>
              <option value="P">Present (P)</option>
              <option value="A">Absent (A)</option>
              <option value="L">Leave (L)</option>
              <option value="LA">Late Arrival (LA)</option>
              <option value="SL">Short Leave (SL)</option>
            </select>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" id="deleteThisBtn" onclick="deleteOneRecord();"><i class="fa fa-trash"></i> Delete</button>
        <button type="button" class="btn btn-primary" onclick="saveAttendance()">Save Changes</button>
      </div>
    </div>
  </div>
</div>

<script>
$(document).on('click', '.edit-attendance-btn', function () {
    var $btn = $(this);
    $('#attendance_id').val($btn.data('id'));
    $('#emp_id').val($btn.data('emp-id'));
    $('#employee_name').val($btn.data('emp-name'));
    $('#attendance_date').val($btn.data('date'));
    $('#display_date').val($btn.data('date'));
    $('#current_attendance').val($btn.data('attendance'));
    $('#attendance_status').val('');
    $('#time_in').val($btn.data('time-in') || '');
    $('#time_out').val($btn.data('time-out') || '');
    $('#time_in_edit').val($btn.data('time-in') ? $btn.data('time-in').slice(0, 5) : '');
    $('#time_out_edit').val($btn.data('time-out') ? $btn.data('time-out').slice(0, 5) : '');
    $('#editAttendanceModal').appendTo('body').modal('show');
});

function saveAttendance() {
    var attendanceStatus = $('#attendance_status').val();
    if (!attendanceStatus) { alert('Please select an attendance status.'); return; }
    $('#time_in').val($('#time_in_edit').val() + ':00');
    $('#time_out').val($('#time_out_edit').val() + ':00');
    fetch('<?php echo BASE_URL; ?>view_emp_attendance.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: $('#editAttendanceForm').serialize() + '&action=UpdateEmpAttendance'
    })
    .then(function(r){ return r.json(); })
    .then(function(resp){
        if (resp.success) { alert(resp.message || 'Attendance updated successfully!'); $('#editAttendanceModal').modal('hide'); location.reload(); }
        else { alert('Error: ' + resp.message); }
    })
    .catch(function(){ alert('Error occurred while updating attendance.'); });
}

function deleteOneRecord() {
    var id = $('#emp_id').val(); var d = $('#attendance_date').val();
    if (!confirm('Are you sure you want to delete this attendance?')) return;
    fetch('<?php echo BASE_URL; ?>view_emp_attendance.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=DeleteEmp_Attendance&emp_id=' + encodeURIComponent(id) + '&date=' + encodeURIComponent(d)
    }).then(function(){ $('#editAttendanceModal').modal('hide'); location.reload(); });
}

function deleteAllAttendance() {
    var d = '<?php echo e($date); ?>';
    if (!d || !confirm('Are you sure you want to delete all attendance for the selected date/session?')) return;
    window.location = '<?php echo BASE_URL; ?>view_emp_attendance.php?action=DeleteEmp_Attendance&date=' + encodeURIComponent(d);
}

// lightweight client-side search
document.addEventListener('DOMContentLoaded', function(){
    var box = document.getElementById('recordSearch');
    if (!box) return;
    box.addEventListener('keyup', function(){
        var q = this.value.toLowerCase().trim();
        document.querySelectorAll('#listofstudents tbody tr').forEach(function(tr){
            var key = (tr.getAttribute('data-search') || '').toLowerCase();
            tr.style.display = key.indexOf(q) >= 0 ? '' : 'none';
        });
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
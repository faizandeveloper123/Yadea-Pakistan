<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Issue & Return Book (Staff)';

function ire_col_exists($t, $c) {
    $r = db_query("SHOW COLUMNS FROM `$t` LIKE '" . str_replace("'", '', $c) . "'");
    return $r && $r->num_rows > 0;
}
try {
    if (!ire_col_exists('book_issues', 'employee_id')) { db_query("ALTER TABLE book_issues ADD COLUMN `employee_id` INT NULL DEFAULT NULL"); }
    foreach (['remarks' => 'TEXT NULL', 'reservation_date' => 'DATE NULL', 'reservation_expiry' => 'DATE NULL', 'notes' => 'TEXT NULL'] as $c => $d) {
        if (!ire_col_exists('book_issues', $c)) { db_query("ALTER TABLE book_issues ADD COLUMN `$c` $d"); }
    }
    $r = db_query("SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='book_issues' AND COLUMN_NAME='status'");
    $row = $r ? $r->fetch_assoc() : null;
    if ($row && ($row['DATA_TYPE'] ?? '') === 'enum') {
        db_query("ALTER TABLE book_issues MODIFY status VARCHAR(20) NOT NULL DEFAULT 'issued'");
    }
} catch (Throwable $ex) {}

function ire_migrate_legacy_settings() {
    try {
        $rows = [];
        $res = db_query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'staff_book_issue_%'");
        if (!$res) { return; }
        while ($row = $res->fetch_assoc()) { $rows[] = $row; }
        foreach ($rows as $r) {
            $parts = explode('|', $r['setting_value']);
            $book_id = (int) ($parts[0] ?? 0);
            $emp_id = (int) ($parts[1] ?? 0);
            $date = trim($parts[4] ?? '');
            $status = trim($parts[5] ?? 'issued');
            if ($book_id <= 0 || $emp_id <= 0 || $date === '') { continue; }
            $status = ($status === 'returned') ? 'returned' : 'issued';
            $return_date = null;
            if ($status === 'returned') { $return_date = $date; }
            $due_date = null;
            if ($status === 'issued') { $due_date = date('Y-m-d', strtotime($date . ' +14 days')); }
            $st = db_prepare("INSERT INTO book_issues (book_id, employee_id, issue_date, due_date, return_date, status, remarks) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $st->bind_param('iisssss', $book_id, $emp_id, $date, $due_date, $return_date, $status, $r['setting_value']);
            $st->execute();
            $st2 = db_prepare("DELETE FROM settings WHERE setting_key=?");
            $st2->bind_param('s', $r['setting_key']);
            $st2->execute();
        }
    } catch (Throwable $ex) {}
}
ire_migrate_legacy_settings();

$message = '';
$error = '';

$books = [];
$res = db_query("SELECT book_id, title, available FROM books WHERE status=1 ORDER BY title");
while ($row = $res->fetch_assoc()) { $books[] = $row; }

$employees = [];
$res = db_query("SELECT emp_id, first_name, last_name, department FROM employees WHERE status=1 ORDER BY first_name");
while ($row = $res->fetch_assoc()) { $employees[] = $row; }

$departments = [];
$res = db_query("SELECT DISTINCT department FROM employees WHERE status=1 AND department IS NOT NULL AND department <> '' ORDER BY department");
while ($row = $res->fetch_assoc()) { $departments[] = $row['department']; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'IssueBook') {
        $emp_id = (int) ($_POST['emp_id'] ?? 0);
        $book_id = (int) ($_POST['book_id'] ?? 0);
        $issue_date = trim($_POST['issue_date'] ?? date('Y-m-d'));
        $due_date = trim($_POST['due_date'] ?? date('Y-m-d', strtotime('+14 days')));
        $remarks = trim($_POST['remarks'] ?? '');

        $st = db_prepare("SELECT * FROM books WHERE book_id=? AND status=1");
        $st->bind_param('i', $book_id);
        $st->execute();
        $bk = $st->get_result()->fetch_assoc();

        if (!$bk) {
            $error = 'Book not found.';
        } elseif ((int) ($bk['available'] ?? 0) <= 0) {
            $error = 'No copies available to issue.';
        } elseif ($emp_id <= 0) {
            $error = 'Please select a staff member.';
        } else {
            $st2 = db_prepare("INSERT INTO book_issues (book_id, employee_id, issue_date, due_date, status, remarks) VALUES (?, ?, ?, ?, 'issued', ?)");
            $st2->bind_param('iisss', $book_id, $emp_id, $issue_date, $due_date, $remarks);
            $st2->execute();
            $st3 = db_prepare("UPDATE books SET available = available - 1 WHERE book_id=?");
            $st3->bind_param('i', $book_id);
            $st3->execute();
            $message = 'Book issued to staff successfully!';
        }
    }

    if ($action === 'ReturnBook') {
        $issue_id = (int) ($_POST['issue_id'] ?? 0);
        $remarks = trim($_POST['remarks'] ?? '');
        $st = db_prepare("SELECT * FROM book_issues WHERE issue_id=? AND status='issued'");
        $st->bind_param('i', $issue_id);
        $st->execute();
        $ret = $st->get_result()->fetch_assoc();
        if ($ret) {
            $rd = date('Y-m-d');
            $st2 = db_prepare("UPDATE book_issues SET return_date=?, status='returned', remarks=? WHERE issue_id=?");
            $st2->bind_param('ssi', $rd, $remarks, $issue_id);
            $st2->execute();
            $st3 = db_prepare("UPDATE books SET available = available + 1 WHERE book_id=?");
            $st3->bind_param('i', $ret['book_id']);
            $st3->execute();
            $message = 'Book returned successfully!';
        } else {
            $error = 'Record not found or already returned.';
        }
    }

    if ($action === 'ReserveBook') {
        $emp_id = (int) ($_POST['emp_id'] ?? 0);
        $book_id = (int) ($_POST['book_id'] ?? 0);
        $reservation_date = trim($_POST['reservation_date'] ?? date('Y-m-d'));
        $expiry_date = trim($_POST['expiry_date'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        if ($emp_id <= 0 || $book_id <= 0) {
            $error = 'Please select staff member and book.';
        } else {
            $st2 = db_prepare("INSERT INTO book_issues (book_id, employee_id, issue_date, reservation_date, reservation_expiry, status, notes) VALUES (?, ?, ?, ?, ?, 'Reserved', ?)");
            $st2->bind_param('iissss', $book_id, $emp_id, $reservation_date, $reservation_date, $expiry_date, $notes);
            $st2->execute();
            $message = 'Book reserved successfully!';
        }
    }

    if ($action === 'ReservationToIssue') {
        $issue_id = (int) ($_POST['issue_id'] ?? 0);
        $st = db_prepare("SELECT * FROM book_issues WHERE issue_id=? AND status='Reserved'");
        $st->bind_param('i', $issue_id);
        $st->execute();
        $ret = $st->get_result()->fetch_assoc();
        if ($ret) {
            $id = date('Y-m-d');
            $dd = date('Y-m-d', strtotime('+14 days'));
            $st2 = db_prepare("UPDATE book_issues SET status='issued', issue_date=?, due_date=? WHERE issue_id=?");
            $st2->bind_param('ssi', $id, $dd, $issue_id);
            $st2->execute();
            $st3 = db_prepare("UPDATE books SET available = available - 1 WHERE book_id=?");
            $st3->bind_param('i', $ret['book_id']);
            $st3->execute();
            $message = 'Reservation converted to issue!';
        } else {
            $error = 'Reservation not found.';
        }
    }

    if ($action === 'DeleteRecord') {
        $issue_id = (int) ($_POST['issue_id'] ?? 0);
        $st = db_prepare("SELECT * FROM book_issues WHERE issue_id=?");
        $st->bind_param('i', $issue_id);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        if ($row) {
            if (($row['status'] ?? '') === 'issued') {
                $st3 = db_prepare("UPDATE books SET available = available + 1 WHERE book_id=?");
                $st3->bind_param('i', $row['book_id']);
                $st3->execute();
            }
            $st2 = db_prepare("DELETE FROM book_issues WHERE issue_id=?");
            $st2->bind_param('i', $issue_id);
            $st2->execute();
            $message = 'Book record deleted!';
        }
    }
}

$dept = trim($_GET['dept'] ?? '');
$records = [];
$sql = "SELECT bi.*, b.title,
        e.first_name, e.last_name, e.department
    FROM book_issues bi
    JOIN books b ON bi.book_id=b.book_id
    LEFT JOIN employees e ON bi.employee_id=e.emp_id
    WHERE bi.employee_id IS NOT NULL";
if ($dept !== '') {
    $sql .= " AND e.department = ?";
}
$sql .= " ORDER BY bi.issue_date DESC, bi.issue_id DESC";
$st = db_prepare($sql);
if ($dept !== '') { $st->bind_param('s', $dept); }
$st->execute();
$res = $st->get_result();
while ($row = $res->fetch_assoc()) { $records[] = $row; }

$issuedRecords = [];
$st = db_prepare("SELECT bi.issue_id, bi.book_id, bi.employee_id, bi.due_date, bi.status, b.title,
        e.first_name, e.last_name
    FROM book_issues bi
    JOIN books b ON bi.book_id=b.book_id
    LEFT JOIN employees e ON bi.employee_id=e.emp_id
    WHERE bi.employee_id IS NOT NULL AND bi.status='issued'
    ORDER BY bi.issue_date DESC");
$st->execute();
$res = $st->get_result();
while ($row = $res->fetch_assoc()) { $issuedRecords[] = $row; }

include __DIR__ . '/includes/header.php';
?>
<style>
.hdr-row { display:flex; align-items:center; justify-content:space-between; padding:14px 4px; flex-wrap:wrap; gap:10px; }
.hdr-row h3 { font-size:18px; font-weight:800; color:#111827; margin:0; }
.form-card { background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:16px; margin-bottom:16px; }
.tab-pills { display:flex; background:#f1f3f6; border-radius:8px; padding:3px; gap:2px; }
.tab-pill { border:none; background:transparent; padding:6px 16px; font-size:12.5px; font-weight:600; color:#718096; border-radius:6px; cursor:pointer; }
.tab-pill.active { background:#fff; color:#2d3748; box-shadow:0 1px 3px rgba(0,0,0,.12); }
.pane { display:none; }
.pane.active { display:block; }
.chip { border-radius:12px; padding:4px 11px; font-size:11px; font-weight:700; white-space:nowrap; display:inline-block; }
.chip-issued { background:#feebc8; color:#9c4221; }
.chip-returned { background:#c6f6d5; color:#22543d; }
.chip-reserved { background:#e9d8fd; color:#553c9a; }
.tbl-card { background:#fff; border:1px solid #E5E7EB; border-radius:14px; overflow:hidden; margin-top:14px; }
.tbl-card thead th { background:#1a202c; color:#fff; font-size:11.5px; font-weight:600; padding:10px; white-space:nowrap; }
.tbl-card tbody td { font-size:12.5px; vertical-align:middle; padding:9px 10px; }
.empty { text-align:center; padding:40px 20px; color:#9CA3AF; }
.empty i { font-size:48px; display:block; margin-bottom:10px; }
.search-card { display:flex; gap:10px; align-items:center; background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:12px 16px; margin-bottom:4px; flex-wrap:wrap; }
.form-lbl { font-size:13px; font-weight:600; color:#4a5568; margin-bottom:4px; }
.btn-return-row { padding:5px 12px; font-size:11.5px; font-weight:600; background:#319795; border:none; color:#fff; border-radius:5px; white-space:nowrap; }
@media print { .no-print { display:none !important; } }
</style>

<div class="main-content">
    <div class="container-fluid">
        <?php if ($message): ?><div class="alert alert-success"><?php echo e($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>

        <div class="hdr-row">
            <h3><i class="fa fa-users"></i> Issue &amp; Return Book (Staff)</h3>
            <div>
                <button type="button" class="btn btn-default btn-sm no-print" onclick="window.print();"><i class="fa fa-print"></i> Print</button>
            </div>
        </div>

        <div class="tab-pills no-print" style="margin-bottom:14px; width:max-content;">
            <button type="button" class="tab-pill active" onclick="showPane('paneIssue', this);"><i class="fa fa-plus-circle"></i> Issue Book</button>
            <button type="button" class="tab-pill" onclick="showPane('paneReturn', this);"><i class="fa fa-reply"></i> Return Book</button>
            <button type="button" class="tab-pill" onclick="showPane('paneReserve', this);"><i class="fa fa-bookmark"></i> Reserve Book</button>
        </div>

        <div class="pane active" id="paneIssue">
            <div class="form-card" style="max-width:600px;">
                <h4 style="font-size:15px; font-weight:800; margin:0 0 14px;"><i class="fa fa-plus-circle"></i> Issue Book</h4>
                <form method="post" action="issue_return_employee.php">
                    <input type="hidden" name="action" value="IssueBook">
                    <div class="form-group">
                        <label class="form-lbl">Staff Member <span style="color:red;">*</span></label>
                        <select name="emp_id" class="form-control" required>
                            <option value="">Select Staff</option>
                            <?php foreach ($employees as $e): ?>
                                <option value="<?php echo $e['emp_id']; ?>"><?php echo e(trim($e['first_name'] . ' ' . $e['last_name'])); ?> <?php echo $e['department'] ? '(' . e($e['department']) . ')' : ''; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-lbl">Book Title <span style="color:red;">*</span></label>
                        <select name="book_id" class="form-control" required>
                            <option value="">Select Book (available)</option>
                            <?php foreach ($books as $b): if ($b['available'] > 0): ?>
                                <option value="<?php echo $b['book_id']; ?>"><?php echo e($b['title']); ?> (<?php echo $b['available']; ?>)</option>
                            <?php endif; endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-lbl">Issue Date <span style="color:red;">*</span></label>
                                <input type="date" name="issue_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-lbl">Return Due Date <span style="color:red;">*</span></label>
                                <input type="date" name="due_date" class="form-control" value="<?php echo date('Y-m-d', strtotime('+14 days')); ?>" required>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-lbl">Remarks</label>
                        <textarea name="remarks" class="form-control" rows="2" placeholder="Optional notes..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-warning" style="width:100%; color:#fff;"><i class="fa fa-check"></i> Issue Book</button>
                </form>
            </div>
        </div>

        <div class="pane" id="paneReturn">
            <div class="form-card" style="max-width:600px;">
                <h4 style="font-size:15px; font-weight:800; margin:0 0 14px;"><i class="fa fa-reply"></i> Return Book</h4>
                <form method="post" action="issue_return_employee.php">
                    <input type="hidden" name="action" value="ReturnBook">
                    <div class="form-group">
                        <label class="form-lbl">Staff Member</label>
                        <select name="emp_filter" id="returnEmpFilter" class="form-control">
                            <option value="">All Staff</option>
                            <?php foreach ($employees as $e): ?>
                                <option value="<?php echo $e['emp_id']; ?>"><?php echo e(trim($e['first_name'] . ' ' . $e['last_name'])); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-lbl">Select Issued Book <span style="color:red;">*</span></label>
                        <select name="issue_id" id="returnIssueSelect" class="form-control" required>
                            <option value="">Select a record</option>
                            <?php foreach ($issuedRecords as $ir): ?>
                                <option value="<?php echo $ir['issue_id']; ?>" data-emp="<?php echo $ir['employee_id']; ?>">
                                    <?php echo e(trim($ir['first_name'] . ' ' . $ir['last_name'])); ?> — <?php echo e($ir['title']); ?> (Due: <?php echo date('d M Y', strtotime($ir['due_date'])); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-lbl">Remarks</label>
                        <textarea name="remarks" class="form-control" rows="2" placeholder="Optional notes..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:100%; color:#fff;"><i class="fa fa-check"></i> Confirm Return</button>
                </form>
            </div>
        </div>

        <div class="pane" id="paneReserve">
            <div class="form-card" style="max-width:600px;">
                <h4 style="font-size:15px; font-weight:800; margin:0 0 14px;"><i class="fa fa-bookmark"></i> Reserve Book</h4>
                <form method="post" action="issue_return_employee.php">
                    <input type="hidden" name="action" value="ReserveBook">
                    <div class="form-group">
                        <label class="form-lbl">Staff Member <span style="color:red;">*</span></label>
                        <select name="emp_id" class="form-control" required>
                            <option value="">Select Staff</option>
                            <?php foreach ($employees as $e): ?>
                                <option value="<?php echo $e['emp_id']; ?>"><?php echo e(trim($e['first_name'] . ' ' . $e['last_name'])); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-lbl">Book Title <span style="color:red;">*</span></label>
                        <select name="book_id" class="form-control" required>
                            <option value="">Select Book</option>
                            <?php foreach ($books as $b): ?>
                                <option value="<?php echo $b['book_id']; ?>"><?php echo e($b['title']); ?> (<?php echo $b['available']; ?> available)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-lbl">Reservation Date <span style="color:red;">*</span></label>
                                <input type="date" name="reservation_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-lbl">Expiry Date</label>
                                <input type="date" name="expiry_date" class="form-control">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-lbl">Notes</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Optional notes..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:100%; color:#fff;"><i class="fa fa-check"></i> Reserve Book</button>
                </form>
            </div>
        </div>

        <div class="search-card no-print">
            <i class="fa fa-search" style="color:#319795;"></i>
            <input type="text" id="recordSearch" class="form-control" style="flex:1; min-width:220px;" placeholder="Search by staff name or book title...">
            <select id="deptFilter" class="form-control" style="max-width:220px;">
                <option value="">All Departments</option>
                <?php foreach ($departments as $dpt): ?>
                    <option value="<?php echo e($dpt); ?>" <?php echo $dept === $dpt ? 'selected' : ''; ?>><?php echo e($dpt); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="button" class="btn btn-default" onclick="location.href='issue_return_employee.php?dept=' + encodeURIComponent(document.getElementById('deptFilter').value);"><i class="fa fa-filter"></i> Filter</button>
            <button type="button" class="btn btn-default" onclick="location.href='issue_return_employee.php';"><i class="fa fa-list"></i> View All</button>
        </div>

        <div class="tbl-card">
            <div style="display:flex; justify-content:space-between; align-items:center; padding:12px 16px; border-bottom:1px solid #edf2f7; flex-wrap:wrap; gap:10px;">
                <h4 style="margin:0; color:#2d3748; font-size:15px; font-weight:600;"><i class="fa fa-book" style="color:#319795;"></i> &nbsp;Book Records (<?php echo count($records); ?>)</h4>
                <div class="tab-pills">
                    <button type="button" class="tab-pill active" onclick="filterStatus('', this);">All</button>
                    <button type="button" class="tab-pill" onclick="filterStatus('issued', this);">Issued</button>
                    <button type="button" class="tab-pill" onclick="filterStatus('returned', this);">Returned</button>
                    <button type="button" class="tab-pill" onclick="filterStatus('Reserved', this);">Reserved</button>
                </div>
            </div>

            <?php if (count($records) === 0): ?>
                <div class="empty"><i class="fa fa-book"></i> No book issue/return records found yet.</div>
            <?php else: ?>
                <div style="overflow-x:auto; padding:0 4px;">
                    <table class="table table-hover table-bordered" id="recordsTable" style="width:100%; margin:0;">
                        <thead>
                            <tr><th>#</th><th>Book</th><th>Staff</th><th>Department</th><th>Issue Date</th><th>Status</th><th>Action</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($records as $idx => $i): ?>
                                <tr data-key="<?php echo e(strtolower(trim(($i['first_name'] ?? '') . ' ' . ($i['last_name'] ?? '')) . ' ' . ($i['title'] ?? ''))); ?>" data-status="<?php echo e($i['status'] ?? ''); ?>">
                                    <td><?php echo $idx + 1; ?></td>
                                    <td><strong><?php echo e($i['title'] ?? '-'); ?></strong></td>
                                    <td><?php echo e(trim(($i['first_name'] ?? '') . ' ' . ($i['last_name'] ?? '')) !== '' ? trim(($i['first_name'] ?? '') . ' ' . ($i['last_name'] ?? '')) : '-'); ?></td>
                                    <td><?php echo e($i['department'] ?? '-'); ?></td>
                                    <td><?php echo $i['issue_date'] ? date('d M Y', strtotime($i['issue_date'])) : '-'; ?></td>
                                    <td>
                                        <?php if (($i['status'] ?? '') === 'returned'): ?>
                                            <span class="chip chip-returned">Returned</span>
                                        <?php elseif (($i['status'] ?? '') === 'Reserved'): ?>
                                            <span class="chip chip-reserved">Reserved</span>
                                        <?php else: ?>
                                            <span class="chip chip-issued">Issued</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="white-space:nowrap;">
                                        <?php if (($i['status'] ?? '') === 'issued'): ?>
                                            <form method="post" action="issue_return_employee.php" style="display:inline;" onsubmit="return confirm('Mark this book as returned?');">
                                                <input type="hidden" name="action" value="ReturnBook">
                                                <input type="hidden" name="issue_id" value="<?php echo $i['issue_id']; ?>">
                                                <button class="btn-return-row"><i class="fa fa-reply"></i> Return</button>
                                            </form>
                                        <?php elseif (($i['status'] ?? '') === 'Reserved'): ?>
                                            <form method="post" action="issue_return_employee.php" style="display:inline;" onsubmit="return confirm('Convert this reservation to issue?');">
                                                <input type="hidden" name="action" value="ReservationToIssue">
                                                <input type="hidden" name="issue_id" value="<?php echo $i['issue_id']; ?>">
                                                <button class="btn btn-success btn-xs"><i class="fa fa-share"></i> Issue</button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="post" action="issue_return_employee.php" style="display:inline;" onsubmit="return confirm('Delete this book record?');">
                                            <input type="hidden" name="action" value="DeleteRecord">
                                            <input type="hidden" name="issue_id" value="<?php echo $i['issue_id']; ?>">
                                            <button class="btn btn-danger btn-xs"><i class="fa fa-trash"></i></button>
                                        </form>
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

<script>
function showPane(id, btn){
    document.querySelectorAll('.pane').forEach(function(p){ p.classList.remove('active'); });
    document.getElementById(id).classList.add('active');
    if (btn) {
        btn.closest('.tab-pills').querySelectorAll('.tab-pill').forEach(function(b){ b.classList.remove('active'); });
        btn.classList.add('active');
    }
}
function filterStatus(status, btn){
    if (btn) {
        btn.closest('.tab-pills').querySelectorAll('.tab-pill').forEach(function(b){ b.classList.remove('active'); });
        btn.classList.add('active');
    }
    document.querySelectorAll('#recordsTable tbody tr').forEach(function(tr){
        tr.style.display = (status === '' || tr.getAttribute('data-status') === status) ? '' : 'none';
    });
}
document.getElementById('recordSearch').addEventListener('keyup', function(){
    var q = this.value.trim().toLowerCase();
    document.querySelectorAll('#recordsTable tbody tr').forEach(function(tr){
        tr.style.display = (q === '' || (tr.getAttribute('data-key') || '').indexOf(q) !== -1) ? '' : 'none';
    });
});
document.getElementById('returnEmpFilter').addEventListener('change', function(){
    var emp = this.value;
    var sel = document.getElementById('returnIssueSelect');
    Array.prototype.forEach.call(sel.options, function(opt){
        if (!opt.value) { opt.disabled = false; return; }
        opt.disabled = !(emp === '' || opt.getAttribute('data-emp') === emp);
        if (opt.disabled && opt.selected) { sel.value = ''; }
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
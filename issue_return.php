<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Issue / Return Books';

$conn = db_connect();
function ir_col_exists($t, $c) {
    $r = db_query("SHOW COLUMNS FROM `$t` LIKE '" . str_replace("'", '', $c) . "'");
    return $r && $r->num_rows > 0;
}
try {
    foreach (['employee_id' => 'INT NULL DEFAULT NULL', 'remarks' => 'TEXT NULL', 'reservation_date' => 'DATE NULL', 'reservation_expiry' => 'DATE NULL', 'notes' => 'TEXT NULL'] as $c => $d) {
        if (!ir_col_exists('book_issues', $c)) { db_query("ALTER TABLE book_issues ADD COLUMN `$c` $d"); }
    }
    $r = db_query("SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='book_issues' AND COLUMN_NAME='status'");
    $row = $r ? $r->fetch_assoc() : null;
    if ($row && ($row['DATA_TYPE'] ?? '') === 'enum') {
        db_query("ALTER TABLE book_issues MODIFY status VARCHAR(20) NOT NULL DEFAULT 'issued'");
    }
} catch (Throwable $ex) {}

function ir_find_col($r, $name) {
    return isset($r[$name]) ? $r[$name] : null;
}

$message = '';
$error = '';

$books = [];
$res = db_query("SELECT book_id, title, available FROM books WHERE status=1 ORDER BY title");
while ($row = $res->fetch_assoc()) { $books[] = $row; }

$students = [];
$res = db_query("SELECT student_id, first_name, last_name, father_name, gr_no, family_code, form_b_no FROM students WHERE status=1 ORDER BY first_name");
while ($row = $res->fetch_assoc()) { $students[] = $row; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'IssueBook') {
        $book_id = (int) ($_POST['book_id'] ?? 0);
        $student_id = (int) ($_POST['student_id'] ?? 0);
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
        } elseif ($student_id <= 0) {
            $error = 'Please select a student.';
        } else {
            $st2 = db_prepare("INSERT INTO book_issues (book_id, student_id, issue_date, due_date, status, remarks) VALUES (?, ?, ?, ?, 'issued', ?)");
            $st2->bind_param('iisss', $book_id, $student_id, $issue_date, $due_date, $remarks);
            $st2->execute();
            $st3 = db_prepare("UPDATE books SET available = available - 1 WHERE book_id=?");
            $st3->bind_param('i', $book_id);
            $st3->execute();
            $message = 'Book issued successfully!';
        }
    }

    if ($action === 'ReturnBook' || $action === 'ConfirmReturn') {
        $issue_id = (int) ($_POST['issue_id'] ?? 0);
        $st = db_prepare("SELECT * FROM book_issues WHERE issue_id=? AND status='issued'");
        $st->bind_param('i', $issue_id);
        $st->execute();
        $ret = $st->get_result()->fetch_assoc();
        if ($ret) {
            $rd = date('Y-m-d');
            $st2 = db_prepare("UPDATE book_issues SET return_date=?, status='returned' WHERE issue_id=?");
            $st2->bind_param('si', $rd, $issue_id);
            $st2->execute();
            $st3 = db_prepare("UPDATE books SET available = available + 1 WHERE book_id=?");
            $st3->bind_param('i', $ret['book_id']);
            $st3->execute();
            $message = 'Book returned successfully!';
        } else {
            $error = 'Record not found or already returned.';
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

$records = [];
$res = db_query("SELECT bi.*, b.title,
        s.first_name, s.last_name, s.father_name, s.gr_no, s.form_b_no, s.family_code
    FROM book_issues bi
    JOIN books b ON bi.book_id=b.book_id
    LEFT JOIN students s ON bi.student_id=s.student_id
    WHERE bi.student_id IS NOT NULL
    ORDER BY bi.issue_date DESC, bi.issue_id DESC");
while ($row = $res->fetch_assoc()) { $records[] = $row; }

$issuedOnly = [];
$st = db_prepare("SELECT bi.*, b.title, s.first_name, s.last_name, s.father_name
    FROM book_issues bi JOIN books b ON bi.book_id=b.book_id
    LEFT JOIN students s ON bi.student_id=s.student_id
    WHERE bi.student_id IS NOT NULL AND bi.status='issued'
    ORDER BY bi.issue_date DESC");
$st->execute();
while ($row = $st->get_result()->fetch_assoc()) { $issuedOnly[] = $row; }

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
.chip-overdue { background:#fed7d7; color:#822727; }
.tbl-card { background:#fff; border:1px solid #E5E7EB; border-radius:14px; overflow:hidden; margin-top:14px; }
.tbl-card thead th { background:#1a202c; color:#fff; font-size:11.5px; font-weight:600; padding:10px; white-space:nowrap; }
.tbl-card tbody td { font-size:12.5px; vertical-align:middle; padding:9px 10px; }
.row-overdue { background:#fff5f5 !important; }
.empty { text-align:center; padding:40px 20px; color:#9CA3AF; }
.empty i { font-size:48px; display:block; margin-bottom:10px; }
.search-card { display:flex; gap:10px; align-items:center; background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:12px 16px; margin-bottom:4px; flex-wrap:wrap; }
.form-lbl { font-size:13px; font-weight:600; color:#4a5568; margin-bottom:4px; }
.btn-return-row { padding:5px 12px; font-size:11.5px; font-weight:600; background:#ed8936; border:none; color:#fff; border-radius:5px; white-space:nowrap; }
.btn-returned-row { padding:5px 12px; font-size:11.5px; font-weight:600; background:#edf2f7; border:none; color:#a0aec0; border-radius:5px; white-space:nowrap; }
@media print { .no-print { display:none !important; } body * { color:#000 !important; } }
</style>

<div class="main-content">
    <div class="container-fluid">
        <?php if ($message): ?><div class="alert alert-success"><?php echo e($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>

        <div class="hdr-row">
            <h3><i class="fa fa-exchange"></i> Issue &amp; Return Book</h3>
            <div>
                <button type="button" class="btn btn-default btn-sm no-print" onclick="window.print();"><i class="fa fa-print"></i> Print</button>
                <a href="<?php echo BASE_URL; ?>list_books.php" class="btn btn-primary btn-sm" style="color:#fff;"><i class="fa fa-book"></i> Books</a>
            </div>
        </div>

        <div class="tab-pills no-print" style="margin-bottom:14px; width:max-content;">
            <button type="button" class="tab-pill active" onclick="showPane('paneIssue', this);"><i class="fa fa-plus-circle"></i> Issue Book</button>
            <button type="button" class="tab-pill" onclick="showPane('paneReturn', this);"><i class="fa fa-reply"></i> Return Book</button>
        </div>

        <div class="pane active" id="paneIssue">
            <div class="form-card" style="max-width:560px;">
                <h4 style="font-size:15px; font-weight:800; margin:0 0 14px;"><i class="fa fa-plus-circle"></i> Issue Book</h4>
                <form method="post" action="issue_return.php">
                    <input type="hidden" name="action" value="IssueBook">
                    <div class="form-group">
                        <label class="form-lbl">Student <span style="color:red;">*</span></label>
                        <input type="text" id="studentSearch" class="form-control" placeholder="Type student name or GR No to search..." style="margin-bottom:6px;">
                        <select name="student_id" id="student_id" class="form-control" required>
                            <option value="">Select Student</option>
                            <?php foreach ($students as $s): ?>
                                <option value="<?php echo $s['student_id']; ?>" data-name="<?php echo e($s['first_name'] . ' ' . $s['last_name'] . ' ' . $s['father_name']); ?>" data-gr="<?php echo e($s['gr_no'] ?? ''); ?>">
                                    <?php echo e(trim($s['first_name'] . ' ' . $s['last_name']) . ' — ' . ($s['gr_no'] ?? '-')); ?>
                                </option>
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
                                <label class="form-lbl">Issue Date <span style="color:red;">*</span></label>
                                <input type="date" name="issue_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-lbl">Due Date <span style="color:red;">*</span></label>
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
            <div class="form-card" style="max-width:560px;">
                <h4 style="font-size:15px; font-weight:800; margin:0 0 14px;"><i class="fa fa-reply"></i> Return Book</h4>
                <?php if (count($issuedOnly) === 0): ?>
                    <div class="alert alert-info">No books are currently issued.</div>
                <?php else: ?>
                    <form method="post" action="issue_return.php">
                        <input type="hidden" name="action" value="ConfirmReturn">
                        <div class="form-group">
                            <label class="form-lbl">Select Issued Book <span style="color:red;">*</span></label>
                            <select name="issue_id" class="form-control" required>
                                <option value="">Select a record</option>
                                <?php foreach ($issuedOnly as $io): ?>
                                    <option value="<?php echo $io['issue_id']; ?>"><?php echo e(trim($io['first_name'] . ' ' . $io['last_name'])); ?> — <?php echo e($io['title']); ?> (Due: <?php echo date('d M Y', strtotime($io['due_date'])); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary" style="width:100%; color:#fff;"><i class="fa fa-check"></i> Confirm Return</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="search-card no-print">
            <i class="fa fa-search" style="color:#ed8936;"></i>
            <input type="text" id="recordSearch" class="form-control" style="flex:1; min-width:220px;" placeholder="Search by student name, GR No or book title...">
            <button type="button" class="btn btn-warning" onclick="applySearch();"><i class="fa fa-search"></i> Search</button>
            <button type="button" class="btn btn-default" onclick="resetSearch();"><i class="fa fa-list"></i> View All</button>
        </div>

        <div class="tbl-card">
            <div style="display:flex; justify-content:space-between; align-items:center; padding:12px 16px; border-bottom:1px solid #edf2f7; flex-wrap:wrap; gap:10px;">
                <h4 style="margin:0; color:#2d3748; font-size:15px; font-weight:600;"><i class="fa fa-book" style="color:#ed8936;"></i> &nbsp;Book Records (<?php echo count($records); ?>)</h4>
                <div class="tab-pills">
                    <button type="button" class="tab-pill active" onclick="filterStatus('', this);">All</button>
                    <button type="button" class="tab-pill" onclick="filterStatus('issued', this);">Issued</button>
                    <button type="button" class="tab-pill" onclick="filterStatus('returned', this);">Returned</button>
                </div>
            </div>

            <?php if (count($records) === 0): ?>
                <div class="empty"><i class="fa fa-book"></i> No book issue/return records found yet.</div>
            <?php else: ?>
                <div style="overflow-x:auto; padding:0 4px;">
                    <table class="table hist-tbl table-hover table-bordered" id="recordsTable" style="width:100%; margin:0;">
                        <thead>
                            <tr><th>#</th><th>Student Name</th><th>GR No / Family Code</th><th>Book Title</th><th>Issue Date</th><th>Due Date</th><th>Status</th><th>Action</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($records as $idx => $i):
                                $od = false;
                                $st = (string) ($i['status'] ?? '');
                                if ($st === 'issued' && $i['due_date'] && $i['due_date'] < date('Y-m-d')) {
                                    $od = true;
                                    $st = 'overdue';
                                }
                                $std = trim(($i['first_name'] ?? '') . ' ' . ($i['last_name'] ?? ''));
                                $gr = $i['gr_no'] ?? '';
                                $fc = $i['family_code'] ?? ($i['form_b_no'] ?? '');
                            ?>
                                <tr class="<?php echo $od ? 'row-overdue' : ''; ?>" data-key="<?php echo e(strtolower($std . ' ' . $gr . ' ' . ($i['title'] ?? ''))); ?>" data-status="<?php echo e($st); ?>">
                                    <td><?php echo $idx + 1; ?></td>
                                    <td><strong><?php echo e($std !== '' ? $std : ($i['father_name'] ?? '-')); ?></strong></td>
                                    <td><?php echo e($gr !== '' ? $gr : '-'); ?> / <?php echo e($fc !== '' ? $fc : '-'); ?></td>
                                    <td><?php echo e($i['title'] ?? '-'); ?></td>
                                    <td><?php echo $i['issue_date'] ? date('d M Y', strtotime($i['issue_date'])) : '-'; ?></td>
                                    <td>
                                        <?php echo $i['due_date'] ? date('d M Y', strtotime($i['due_date'])) : '-'; ?>
                                        <?php if ($od): ?><br><small style="color:#e53e3e;"><i class="fa fa-exclamation-triangle"></i> Overdue</small><?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($st === 'returned'): ?>
                                            <span class="chip chip-returned">Returned</span>
                                        <?php elseif ($st === 'overdue'): ?>
                                            <span class="chip chip-overdue">Overdue</span>
                                        <?php else: ?>
                                            <span class="chip chip-issued">Issued</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="white-space:nowrap;">
                                        <?php if ($st === 'returned'): ?>
                                            <button type="button" class="btn-returned-row" disabled=""><i class="fa fa-check"></i> Confirm Return</button>
                                        <?php else: ?>
                                            <form method="post" action="issue_return.php" style="display:inline;" onsubmit="return confirm('Mark this book as returned?');">
                                                <input type="hidden" name="action" value="ReturnBook">
                                                <input type="hidden" name="issue_id" value="<?php echo $i['issue_id']; ?>">
                                                <button class="btn-return-row"><i class="fa fa-reply"></i> Return</button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="post" action="issue_return.php" style="display:inline;" onsubmit="return confirm('Delete this book record?');">
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
function applySearch(){
    var q = document.getElementById('recordSearch').value.trim().toLowerCase();
    document.querySelectorAll('#recordsTable tbody tr').forEach(function(tr){
        tr.style.display = (q === '' || (tr.getAttribute('data-key') || '').indexOf(q) !== -1) ? '' : 'none';
    });
}
function resetSearch(){
    document.getElementById('recordSearch').value = '';
    applySearch();
    document.querySelectorAll('.tab-pills .tab-pill').forEach(function(b){ b.classList.remove('active'); });
    var first = document.querySelector('.tab-pills .tab-pill');
    if (first) first.classList.add('active');
}
document.getElementById('recordSearch').addEventListener('keyup', function(){ applySearch(); });
document.getElementById('studentSearch').addEventListener('input', function(){
    var q = this.value.trim().toLowerCase();
    var sel = document.getElementById('student_id');
    Array.prototype.forEach.call(sel.options, function(opt){
        if (!opt.value) return;
        var hay = (opt.getAttribute('data-name') + ' ' + opt.getAttribute('data-gr')).toLowerCase();
        opt.disabled = !(q === '' || hay.indexOf(q) !== -1);
        if (opt.disabled && opt.selected) { sel.value = ''; }
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
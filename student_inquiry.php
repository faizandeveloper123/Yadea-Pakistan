<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Student Inquiries';

db_query("CREATE TABLE IF NOT EXISTS student_inquiries (
  inquiry_id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  father_name VARCHAR(150) DEFAULT '',
  phone VARCHAR(30) DEFAULT '',
  father_cellno VARCHAR(30) DEFAULT '',
  email VARCHAR(120) DEFAULT '',
  class_id INT DEFAULT NULL,
  section_id INT DEFAULT NULL,
  session VARCHAR(20) DEFAULT '',
  admission_source VARCHAR(100) DEFAULT '',
  locality VARCHAR(150) DEFAULT '',
  address TEXT,
  visit_date DATE DEFAULT NULL,
  test_date DATE DEFAULT NULL, test_time VARCHAR(20) DEFAULT '',
  remarks TEXT,
  status VARCHAR(30) DEFAULT 'New',
  created_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

db_query("CREATE TABLE IF NOT EXISTS inquiry_notes (
  note_id INT AUTO_INCREMENT PRIMARY KEY,
  inquiry_id INT NOT NULL,
  note TEXT NOT NULL,
  created_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$countNew = (int) (db_query("SELECT COUNT(*) c FROM student_inquiries")->fetch_assoc()['c'] ?? 0);
if ($countNew === 0) {
    $legacy = db_query("SELECT * FROM inquiries");
    while ($row = $legacy->fetch_assoc()) {
        $st = db_prepare("INSERT INTO student_inquiries (name, phone, email, class_id, remarks, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $status = str_replace('new', 'New', $row['status'] ?? 'New');
        $status = str_replace('contacted', 'In-Process', $status);
        $status = str_replace('admitted', 'Admitted', $status);
        $status = str_replace('lost', 'Cancelled', $status);
        $st->bind_param('sssisss', $row['name'], $row['phone'], $row['email'], $row['class_id'], $row['message'], $status, $row['created_at']);
        $st->execute();
    }
}

$message = '';
$error = '';

$classes = [];
$res = db_query("Select Course/Class_id, class_name FROM classes WHERE status=1 ORDER BY class_name");
while ($row = $res->fetch_assoc()) { $classes[] = $row; }

$sections = [];
$res = db_query("SELECT section_id, class_id, section_name FROM sections ORDER BY section_name");
while ($row = $res->fetch_assoc()) { $sections[] = $row; }

$localities = [];
$res = db_query("SELECT DISTINCT locality FROM student_inquiries WHERE locality != '' AND locality IS NOT NULL ORDER BY locality");
while ($row = $res->fetch_assoc()) { $localities[] = $row['locality']; }

$statusMap = ['New' => 1, 'In-Process' => 2, 'Interested' => 4, 'Admitted' => 5, 'Cancelled' => 6];
$statusRev = [1 => 'New', 2 => 'In-Process', 4 => 'Interested', 5 => 'Admitted', 6 => 'Cancelled'];
$statusColors = ['New' => '#A05AFF', 'In-Process' => '#337ab7', 'Interested' => '#4BCBEB', 'Admitted' => '#1BCFB4', 'Cancelled' => '#D32D41'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'AddInquiry') {
        $name = trim($_POST['name'] ?? '');
        if ($name === '') { $error = 'Name is required.'; }
        else {
            $st2 = db_prepare("INSERT INTO student_inquiries (name, father_name, phone, father_cellno, email, class_id, section_id, session, admission_source, locality, address, visit_date, test_date, test_time, remarks, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'New', ?)");
            $uid = $_SESSION['user_id'] ?? null;
            $class_id = (int)($_POST['class_id'] ?? 0) > 0 ? (int)$_POST['class_id'] : null;
            $section_id = (int)($_POST['section_id'] ?? 0) > 0 ? (int)$_POST['section_id'] : null;
            $vis = trim($_POST['visit_date'] ?? '') !== '' ? trim($_POST['visit_date']) : null;
            $test = trim($_POST['test_date'] ?? '') !== '' ? trim($_POST['test_date']) : null;
            $st2->bind_param('ssssssssssssssssi', $name, trim($_POST['father_name'] ?? ''), trim($_POST['phone'] ?? ''), trim($_POST['father_cellno'] ?? ''), trim($_POST['email'] ?? ''), $class_id, $section_id, trim($_POST['session'] ?? ''), trim($_POST['admission_source'] ?? ''), trim($_POST['locality'] ?? ''), trim($_POST['address'] ?? ''), $vis, $test, trim($_POST['test_time'] ?? ''), trim($_POST['remarks'] ?? ''), $uid);
            $st2->execute(); $message = 'Inquiry added successfully!';
        }
    }
    if ($action === 'ChangeStatus') {
        $iid = (int)($_POST['inquiry_id'] ?? 0); $status = trim($_POST['status'] ?? 'New');
        if ($iid > 0) { $st2 = db_prepare("UPDATE student_inquiries SET status=? WHERE inquiry_id=?"); $st2->bind_param('si', $status, $iid); $st2->execute(); $message = 'Inquiry status updated!'; }
    }
    if ($action === 'AddNote') {
        $iid = (int)($_POST['inquiry_id'] ?? 0); $note = trim($_POST['note'] ?? '');
        if ($iid > 0 && $note !== '') { $st2 = db_prepare("INSERT INTO inquiry_notes (inquiry_id, note, created_by) VALUES (?, ?, ?)"); $uid = $_SESSION['user_id'] ?? null; $st2->bind_param('isi', $iid, $note, $uid); $st2->execute(); $message = 'Note added!'; }
    }
    if ($action === 'EnrollInquiry') {
        $iid = (int)($_POST['inquiry_id'] ?? 0); $name = trim($_POST['name'] ?? '');
        $father = trim($_POST['father_name'] ?? ''); $phone = trim($_POST['phone'] ?? '');
        $class_id = (int)($_POST['class_id'] ?? 0) > 0 ? (int)$_POST['class_id'] : null;
        $section_id = (int)($_POST['section_id'] ?? 0) > 0 ? (int)$_POST['section_id'] : null;
        $session = trim($_POST['session'] ?? '') !== '' ? trim($_POST['session'] ?? '') : (string)get_setting('session_year', '2026-2027');
        if ($name === '') { $error = 'Student name is required for enrollment.'; }
        else {
            $mx = db_query("SELECT MAX(CAST(gr_no AS UNSIGNED)) m FROM students WHERE gr_no <> ''")->fetch_assoc();
            $seq = ((int)($mx['m'] ?? 0)) + 1; $gr = date('y') . '-' . $seq;
            $st2 = db_prepare("INSERT INTO students (gr_no, first_name, last_name, father_name, phone, class_id, section_id, session, status, admission_date) VALUES (?, ?, '', ?, ?, ?, ?, ?, '1', CURDATE())");
            $st2->bind_param('ssssiis', $gr, $name, $father, $phone, $class_id, $section_id, $session); $st2->execute();
            $newId = (int)$st2->insert_id;
            $st3 = db_prepare("UPDATE student_inquiries SET status='Admitted', class_id=?, section_id=?, session=? WHERE inquiry_id=?"); $st3->bind_param('iisi', $class_id, $section_id, $session, $iid); $st3->execute();
            $st4 = db_prepare("INSERT INTO inquiry_notes (inquiry_id, note, created_by) VALUES (?, ?, ?)"); $uid = $_SESSION['user_id'] ?? null;
            $enote = 'Enrolled as student (GR No: ' . $gr . ', ID: ' . $newId . ')'; $st4->bind_param('isi', $iid, $enote, $uid); $st4->execute();
            $message = 'Inquiry enrolled! Student created with GR No ' . $gr . '.';
        }
    }
    if ($action === 'DeleteInquiry') {
        $iid = (int)($_POST['inquiry_id'] ?? 0);
        $st2 = db_prepare("DELETE FROM student_inquiries WHERE inquiry_id=?"); $st2->bind_param('i', $iid); $st2->execute(); $message = 'Inquiry deleted!';
    }
}

$delete_id = (int)($_GET['delete_id'] ?? 0);
if ($delete_id > 0) {
    $st2 = db_prepare("DELETE FROM student_inquiries WHERE inquiry_id=?"); $st2->bind_param('i', $delete_id); $st2->execute();
    header('Location: student_inquiry.php'); exit;
}

$filter_from = trim($_GET['from'] ?? '');
$filter_to = trim($_GET['to'] ?? '');
$filter_status = trim($_GET['status'] ?? '');
$filter_locality = trim($_GET['locality'] ?? '');
$filter_class_id = trim($_GET['class_id'] ?? '');
$filter_section = trim($_GET['section'] ?? '');
$search = trim($_GET['search'] ?? '');

$where = []; $params = []; $types = '';
if ($filter_from !== '') { $where[] = 'DATE(i.created_at) >= ?'; $params[] = $filter_from; $types .= 's'; }
if ($filter_to !== '') { $where[] = 'DATE(i.created_at) <= ?'; $params[] = $filter_to; $types .= 's'; }
if ($filter_status !== '' && $filter_status !== 'All') { $s = $statusRev[(int)$filter_status] ?? null; if ($s) { $where[] = 'i.status = ?'; $params[] = $s; $types .= 's'; } }
if ($filter_locality !== '' && $filter_locality !== 'All') { $where[] = 'i.locality = ?'; $params[] = $filter_locality; $types .= 's'; }
if ($filter_class_id !== '' && $filter_class_id !== 'All') { $where[] = 'i.class_id = ?'; $params[] = (int)$filter_class_id; $types .= 'i'; }
if ($filter_section !== '' && $filter_section !== 'All') { $where[] = 'i.section_id = ?'; $params[] = (int)$filter_section; $types .= 'i'; }
if ($search !== '') { $where[] = '(i.name LIKE ? OR i.father_name LIKE ? OR i.phone LIKE ? OR i.email LIKE ?)'; $like = '%' . $search . '%'; for ($x = 0; $x < 4; $x++) { $params[] = $like; $types .= 's'; } }

$sql = "SELECT i.*, c.class_name, sec.section_name, u.full_name added_by_name FROM student_inquiries i LEFT JOIN classes c ON i.class_id = c.class_id LEFT JOIN sections sec ON i.section_id = sec.section_id LEFT JOIN users u ON i.created_by = u.user_id";
if (count($where) > 0) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY i.created_at DESC';

$inquiries = [];
if (count($params) > 0) { $st = db_prepare($sql); $st->bind_param($types, ...$params); $st->execute(); $r = $st->get_result(); }
else { $r = db_query($sql); }
while ($row = $r->fetch_assoc()) { $inquiries[] = $row; }

$statuses = ['New', 'In-Process', 'Interested', 'Admitted', 'Cancelled'];
$courseNames = ['Graphic Designing', 'AI', 'Web Development', 'Sales', 'English Spoken', 'E-commerce'];
$statusCounts = [];
$sr = db_query("SELECT status, COUNT(*) c FROM student_inquiries GROUP BY status");
while ($row = $sr->fetch_assoc()) { $statusCounts[$row['status']] = (int)$row['c']; }
$totalAll = (int)(db_query("SELECT COUNT(*) c FROM student_inquiries")->fetch_assoc()['c'] ?? 0);

include __DIR__ . '/includes/header.php';
?>
<style>
:root { --si-brand: #00AFEF; --si-border: rgba(11,11,11,0.08); --si-page: #f6f7fb; --si-ink-secondary: #52514e; --si-ink-muted: #898781; }
.si-wrap { padding: 14px 18px; }
.si-topbar { background:#fff; border:1px solid var(--si-border); border-radius:12px; padding:14px 18px; margin-bottom:12px; display:flex; align-items:flex-start; justify-content:space-between; flex-wrap:wrap; gap:10px; }
.si-crumb { font-size:12px; color:var(--si-ink-muted); margin:0 0 4px; }
.si-crumb a { color:var(--si-ink-secondary); text-decoration:none; }
.si-title-row { display:flex; align-items:center; flex-wrap:wrap; gap:8px; }
.si-title-row h2 { margin:0; font-size:19px; font-weight:700; color:#0b0b0b; }
.si-title-badge { font-size:11px; font-weight:700; color:var(--si-brand); background:rgba(0,175,239,0.12); border-radius:999px; padding:3px 10px; }
.si-subtitle { margin:3px 0 0; font-size:12px; color:var(--si-ink-secondary); }
.si-topbar-actions { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
.si-topbar-actions .btn { border-radius:8px; font-size:12px; }
.si-stat-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(170px,1fr)); gap:10px; margin-bottom:12px; }
.si-stat-card { background:#fff; border:1px solid var(--si-border); border-radius:10px; padding:12px 14px; display:flex; align-items:center; gap:10px; color:inherit; text-decoration:none; cursor:pointer; transition:transform .15s,box-shadow .15s; }
.si-stat-card:hover { transform:translateY(-2px); box-shadow:0 4px 12px rgba(16,24,40,0.1); color:inherit; text-decoration:none; }
.si-stat-card.active { border-color:var(--si-brand); box-shadow:0 0 0 2px rgba(0,175,239,0.2); }
.si-stat-icon { width:38px; height:38px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:15px; flex-shrink:0; }
.si-stat-body { flex:1; min-width:0; }
.si-stat-label { font-size:11px; font-weight:700; color:var(--si-ink-secondary); margin-bottom:2px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.si-stat-value-row { display:flex; align-items:baseline; gap:6px; }
.si-stat-value { font-size:20px; font-weight:700; color:#0b0b0b; line-height:1; }
.si-stat-pct { font-size:10px; font-weight:700; padding:2px 6px; border-radius:999px; white-space:nowrap; }
.si-filter-panel { background:#fff; border:1px solid var(--si-border); border-radius:10px; padding:12px 14px; margin-bottom:12px; }
.si-filter-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:8px; }
.si-filter-grid.si-collapsed { display:none; }
.si-filter-grid .form-group { margin-bottom:0; }
.si-filter-grid label { display:block; font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.03em; color:var(--si-ink-muted); margin-bottom:3px; }
.si-filter-grid .form-control { border-radius:7px; border-color:var(--si-border); font-size:12px; height:32px; padding:4px 8px; box-shadow:none; }
.si-filter-grid select.form-control { height:32px; }
.si-search-row { display:flex; align-items:center; gap:8px; margin-top:10px; flex-wrap:wrap; }
.si-search-box { position:relative; flex:1 1 240px; }
.si-search-box i { position:absolute; left:10px; top:50%; transform:translateY(-50%); color:var(--si-ink-muted); font-size:12px; }
.si-search-box input { width:100%; border:1px solid var(--si-border); border-radius:7px; padding:6px 10px 6px 28px; font-size:12px; box-shadow:none; }
.si-btn-search,.si-btn-reset { border-radius:7px; font-size:12px; }
.si-advanced-toggle { margin-left:auto; font-size:11px; color:var(--si-ink-secondary); background:var(--si-page); border:1px solid var(--si-border); border-radius:999px; padding:6px 12px; cursor:pointer; }
.si-advanced-toggle i { margin-left:4px; transition:transform .15s; }
.si-advanced-toggle.open i { transform:rotate(180deg); }
.si-toolbar { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px; margin-bottom:8px; }
.si-export-group { display:flex; gap:6px; flex-wrap:wrap; }
.si-export-btn { display:inline-flex; align-items:center; gap:5px; font-size:11px; font-weight:600; padding:6px 10px; border-radius:7px; border:1px solid var(--si-border); background:#fff; color:#0b0b0b; text-decoration:none; cursor:pointer; }
.si-export-btn:hover { background:var(--si-page); }
.si-export-btn i { font-size:12px; }
.si-export-btn.xls-btn i { color:#1e7d34; }
.si-export-btn.csv-btn i { color:#2a78d6; }
.si-export-btn.pdf-btn i { color:#d63939; }
.si-export-btn.txt-btn i { color:#6c757d; }
.si-show-entries { display:flex; align-items:center; gap:5px; font-size:11px; color:var(--si-ink-secondary); }
.si-show-entries select { border-radius:7px; border:1px solid var(--si-border); padding:4px 6px; font-size:11px; }
.si-table-card { background:#fff; border:1px solid var(--si-border); border-radius:10px; overflow:hidden; }
.si-table-wrap { overflow-x:auto; }
table#excelexpo { margin-bottom:0 !important; }
table#excelexpo caption { display:none; }
table#excelexpo thead th { background:var(--si-brand); color:#fff; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.02em; border:none !important; padding:10px 8px; white-space:nowrap; }
table#excelexpo tbody td { font-size:12px; vertical-align:middle; padding:8px; border-bottom:1px solid var(--si-page); border-top:none !important; }
table#excelexpo tbody tr:hover { background:#f9fbff; }
.si-status-pill { display:inline-block; padding:3px 10px; border-radius:999px; font-size:11px; font-weight:700; color:#fff; border:none; cursor:pointer; }
.si-action-icons { display:flex; align-items:center; gap:5px; }
.si-icon-btn { width:26px; height:26px; border-radius:6px; display:inline-flex; align-items:center; justify-content:center; border:1px solid var(--si-border); background:var(--si-page); color:var(--si-ink-secondary); text-decoration:none; font-size:11px; }
.si-icon-btn:hover { color:#fff; text-decoration:none; }
.si-icon-btn.si-icon-view:hover { background:#2a78d6; border-color:#2a78d6; }
.si-icon-btn.si-icon-edit:hover { background:#eda100; border-color:#eda100; }
.si-icon-btn.si-icon-delete:hover { background:#dc3545; border-color:#dc3545; }
.si-icon-btn.si-icon-more:hover { background:#52514e; border-color:#52514e; }
.si-pagination-bar { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px; padding:10px 14px; }
.si-pagination-info { font-size:11px; color:var(--si-ink-secondary); }
.si-pager { display:flex; gap:3px; }
.si-page-btn { min-width:26px; height:26px; padding:0 6px; border-radius:5px; border:1px solid var(--si-border); background:#fff; color:var(--si-ink-secondary); font-size:11px; cursor:pointer; }
.si-page-btn.active { background:var(--si-brand); border-color:var(--si-brand); color:#fff; font-weight:700; }
.si-page-btn:disabled { opacity:0.4; cursor:not-allowed; }
@media (max-width:768px){ .si-stat-grid { grid-template-columns:repeat(auto-fit,minmax(130px,1fr)); } .si-filter-grid { grid-template-columns:1fr 1fr; } }
</style>

<div class="si-wrap">
<?php if ($message): ?><div class="alert alert-success" style="padding:8px 14px;font-size:12px;border-radius:8px;margin-bottom:10px;"><?php echo e($message); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger" style="padding:8px 14px;font-size:12px;border-radius:8px;margin-bottom:10px;"><?php echo e($error); ?></div><?php endif; ?>

<div class="si-topbar">
<div>
<p class="si-crumb"><a href="<?php echo BASE_URL; ?>dashboard.php">Dashboard</a> <i class="fas fa-angle-double-right"></i> Front Office <i class="fas fa-angle-double-right"></i> Students Inquiries</p>
<div class="si-title-row">
<h2>Student Inquiry</h2>
<span class="si-title-badge"><?php echo $totalAll; ?> Records</span>
</div>
<p class="si-subtitle">Manage and track all student admission inquiries</p>
</div>
<div class="si-topbar-actions">
<button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addInquiryModal" style="background:var(--si-brand);border-color:var(--si-brand);"><i class="fas fa-plus"></i> Add New Inquiry</button>
</div>
</div>

<div class="si-stat-grid">
<?php
$statCards = ['New' => ['icon'=>'fa-file-alt','color'=>'#A05AFF'],'In-Process'=>['icon'=>'fa-hourglass-half','color'=>'#337ab7'],'Interested'=>['icon'=>'fa-info-circle','color'=>'#4BCBEB'],'Admitted'=>['icon'=>'fa-user-plus','color'=>'#1BCFB4'],'Cancelled'=>['icon'=>'fa-times-circle','color'=>'#D32D41']];
foreach ($statCards as $st=>$info):
    $cnt = $statusCounts[$st] ?? 0;
    $pct = $totalAll > 0 ? round(($cnt/$totalAll)*100,1) : 0;
    $active = ($filter_status === (string)($statusMap[$st])) ? ' active' : '';
?>
<a class="si-stat-card<?php echo $active; ?>" href="student_inquiry.php?from=<?php echo e($filter_from ?: date('Y-01-01')); ?>&to=<?php echo e($filter_to ?: date('Y-m-d')); ?>&status=<?php echo $statusMap[$st]; ?>&class_id=All&section=All&locality=All&addaccountAdmin=1">
<div class="si-stat-icon" style="background:<?php echo $info['color']; ?>22;color:<?php echo $info['color']; ?>;"><i class="fas <?php echo $info['icon']; ?>"></i></div>
<div class="si-stat-body"><div class="si-stat-label"><?php echo $st; ?></div><div class="si-stat-value-row"><span class="si-stat-value"><?php echo $cnt; ?></span><span class="si-stat-pct" style="background:<?php echo $info['color']; ?>22;color:<?php echo $info['color']; ?>;"><?php echo $pct; ?>%</span></div></div>
</a>
<?php endforeach; ?>
<a class="si-stat-card<?php echo ($filter_status===''||$filter_status==='All')?' active':''; ?>" href="student_inquiry.php?from=<?php echo e($filter_from?:date('Y-01-01')); ?>&to=<?php echo e($filter_to?:date('Y-m-d')); ?>&status=All&class_id=All&section=All&locality=All&addaccountAdmin=1">
<div class="si-stat-icon" style="background:#2a78d622;color:#2a78d6;"><i class="fas fa-check-circle"></i></div>
<div class="si-stat-body"><div class="si-stat-label">Total Inquiries</div><div class="si-stat-value-row"><span class="si-stat-value"><?php echo $totalAll; ?></span><span class="si-stat-pct" style="background:#2a78d622;color:#2a78d6;">100%</span></div></div>
</a>
</div>

<form action="student_inquiry.php" method="get">
<div class="si-filter-panel">
<div class="si-filter-grid" id="siFilterGrid">
<div class="form-group"><label>From Date</label><input name="from" value="<?php echo e($filter_from?:date('Y-01-01')); ?>" type="date" class="form-control"></div>
<div class="form-group"><label>To Date</label><input name="to" value="<?php echo e($filter_to?:date('Y-m-d')); ?>" type="date" class="form-control"></div>
<div class="form-group"><label>Course/Class</label>
<select name="class_id" class="form-control" onchange="getSections(this.value,'txt_section1')">
<option value="All">All</option>
<?php foreach ($classes as $c): if (!in_array($c['class_name'],$courseNames)) continue; ?>
<option value="<?php echo $c['class_id']; ?>" <?php echo $filter_class_id===(string)$c['class_id']?'selected':''; ?>><?php echo e($c['class_name']); ?></option>
<?php endforeach; ?>
</select></div>
<div class="form-group"><label>Section</label><select name="section" id="txt_section1" class="form-control"><option value="All">All</option></select></div>
<div class="form-group"><label>Status</label><select name="status" class="form-control"><option value="All">All</option><?php foreach ($statuses as $st): ?><option value="<?php echo $statusMap[$st]; ?>" <?php echo $filter_status===(string)$statusMap[$st]?'selected':''; ?>><?php echo $st; ?></option><?php endforeach; ?></select></div>
<div class="form-group"><label>Locality</label><select name="locality" class="form-control"><option value="All">All</option><?php foreach ($localities as $loc): ?><option value="<?php echo e($loc); ?>" <?php echo $filter_locality===$loc?'selected':''; ?>><?php echo e($loc); ?></option><?php endforeach; ?></select></div>
</div>
<div class="si-search-row">
<div class="si-search-box"><i class="fas fa-search"></i><input type="text" name="search" value="<?php echo e($search); ?>" placeholder="Search by Name, Cell No..."></div>
<input type="hidden" name="addaccountAdmin" value="1">
<button type="submit" class="btn btn-primary si-btn-search btn-sm" style="background:var(--si-brand);border-color:var(--si-brand);"><i class="fas fa-search"></i> Search</button>
<a href="student_inquiry.php" class="btn btn-default si-btn-reset btn-sm">Reset</a>
<button type="button" class="si-advanced-toggle" id="siAdvToggle">Advanced Filter <i class="fas fa-chevron-down"></i></button>
</div>
</div>
</form>

<div class="si-toolbar">
<div class="si-export-group">
<button type="button" class="si-export-btn xls-btn" id="siExportExcel"><i class="fas fa-file-excel"></i> Excel</button>
<button type="button" class="si-export-btn csv-btn" id="siExportCsv"><i class="fas fa-file-alt"></i> CSV</button>
<button type="button" class="si-export-btn pdf-btn" id="siExportPdf"><i class="fas fa-file-pdf"></i> PDF</button>
<button type="button" class="si-export-btn txt-btn" id="siExportTxt"><i class="fas fa-file"></i> TXT</button>
</div>
<div class="si-show-entries">Show <select id="siShowEntries"><option value="10">10</option><option value="25">25</option><option value="50">50</option><option value="100">100</option><option value="All">All</option></select> entries</div>
</div>

<div class="si-table-card">
<div class="si-table-wrap">
<table id="excelexpo" class="table table-striped">
<thead>
<tr><th width="3%"><input type="checkbox" id="siSelectAll"></th><th width="4%">S.No</th><th width="12%">Student Name</th><th width="10%">Cell</th><th width="12%">Course/Class</th><th width="8%">Status</th><th width="8%">Locality</th><th width="8%">Visiting</th><th width="8%">Added By</th><th width="10%">Action</th></tr>
</thead>
<tbody>
<?php if ($totalAll===0): ?><tr><td colspan="10" style="text-align:center;color:#898781;padding:24px;">No inquiries found.</td></tr><?php endif; ?>
<?php $sn=1; foreach ($inquiries as $i): ?>
<tr>
<td style="text-align:center;"><input type="checkbox" class="si-row-cb" data-id="<?php echo $i['inquiry_id']; ?>"></td>
<td style="text-align:center;"><?php echo $sn++; ?></td>
<td><strong><?php echo e($i['name']); ?></strong><?php if (!empty($i['father_name'])) echo '<br><small style="color:#898781;">' . e($i['father_name']) . '</small>'; ?></td>
<td><?php echo e($i['phone'] ?? '-'); ?></td>
<td><?php echo e($i['class_name'] ?? '-'); ?> / <?php echo e($i['section_name'] ?? '-'); ?></td>
<td><button type="button" class="si-status-pill" style="background:<?php echo $statusColors[$i['status']]??'#6B7280'; ?>;" data-toggle="modal" data-target="#status<?php echo $i['inquiry_id']; ?>"><?php echo e($i['status']); ?></button></td>
<td><?php echo e($i['locality'] ?? '-'); ?></td>
<td><?php echo date('d-M-Y',strtotime($i['created_at'])); ?></td>
<td><?php echo e($i['added_by_name'] ?? 'Admin'); ?></td>
<td>
<div class="si-action-icons">
<a href="#" class="si-icon-btn si-icon-view" data-toggle="modal" data-target="#view<?php echo $i['inquiry_id']; ?>" title="View"><i class="fas fa-eye"></i></a>
<a href="#" class="si-icon-btn si-icon-edit" title="Edit" onclick="editInquiry(<?php echo $i['inquiry_id']; ?>);return false;"><i class="fas fa-pencil-alt"></i></a>
<a onclick="return confirm('Delete this inquiry?');" href="student_inquiry.php?delete_id=<?php echo $i['inquiry_id']; ?>" class="si-icon-btn si-icon-delete" title="Delete"><i class="fas fa-trash"></i></a>
<div class="btn-group">
<button data-toggle="dropdown" class="si-icon-btn si-icon-more" type="button" title="More"><i class="fas fa-ellipsis-v"></i></button>
<ul class="dropdown-menu dropdown-menu-right" style="font-size:12px;min-width:150px;">
<li><a href="#" onclick="viewInquiry(<?php echo $i['inquiry_id']; ?>);return false;"><i class="fa fa-eye"></i> View Details</a></li>
<li><a href="#" onclick="changeStatus(<?php echo $i['inquiry_id']; ?>);return false;"><i class="fa fa-pencil"></i> Change Status</a></li>
<?php if ($i['status']!=='Admitted'): ?>
<li><a href="#" data-toggle="modal" data-target="#enroll<?php echo $i['inquiry_id']; ?>"><i class="fa fa-user-plus"></i> Enroll</a></li>
<?php endif; ?>
</ul>
</div>
</div>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<div class="si-pagination-bar">
<div class="si-pagination-info" id="siPageInfo">Showing 0 entries</div>
<div class="si-pager" id="siPager"></div>
</div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addInquiryModal"><div class="modal-dialog modal-lg"><div class="modal-content">
<form method="post" action="student_inquiry.php"><input type="hidden" name="action" value="AddInquiry">
<div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">Add New Inquiry</h4></div>
<div class="modal-body">
<div class="row"><div class="form-group col-md-6"><label>Student Name</label><input type="text" name="name" class="form-control" required></div><div class="form-group col-md-6"><label>Father Name</label><input type="text" name="father_name" class="form-control"></div></div>
<div class="row"><div class="form-group col-md-4"><label>Contact No</label><input type="text" name="phone" class="form-control"></div><div class="form-group col-md-4"><label>Father Cell</label><input type="text" name="father_cellno" class="form-control"></div><div class="form-group col-md-4"><label>Email</label><input type="email" name="email" class="form-control"></div></div>
<div class="row"><div class="form-group col-md-4"><label>Course/Class</label><select name="class_id" class="form-control" onchange="getSections(this.value,'ai_section')"><option value="0">Any</option><?php foreach ($classes as $c): if (!in_array($c['class_name'],$courseNames)) continue; ?><option value="<?php echo $c['class_id']; ?>"><?php echo e($c['class_name']); ?></option><?php endforeach; ?></select></div><div class="form-group col-md-4"><label>Section</label><select name="section_id" id="ai_section" class="form-control"><option value="0">Any</option></select></div><div class="form-group col-md-4"><label>Session</label><input type="text" name="session" class="form-control" value="<?php echo e(get_setting('session_year','2026-2027')); ?>"></div></div>
<div class="row"><div class="form-group col-md-6"><label>Locality</label><input type="text" name="locality" class="form-control"></div><div class="form-group col-md-6"><label>Source</label><input type="text" name="admission_source" class="form-control" placeholder="Walk-in, Friend, etc"></div></div>
<div class="row"><div class="form-group col-md-4"><label>Visit Date</label><input type="date" name="visit_date" class="form-control"></div><div class="form-group col-md-4"><label>Test Date</label><input type="date" name="test_date" class="form-control"></div><div class="form-group col-md-4"><label>Test Time</label><input type="text" name="test_time" class="form-control" placeholder="e.g. 10:30 AM"></div></div>
<div class="row"><div class="form-group col-md-6"><label>Address</label><textarea name="address" class="form-control" rows="2"></textarea></div><div class="form-group col-md-6"><label>Remarks</label><textarea name="remarks" class="form-control" rows="2"></textarea></div></div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Close</button><button type="submit" class="btn btn-primary" style="background:var(--si-brand);border-color:var(--si-brand);">Save Inquiry</button></div>
</form>
</div></div></div>

<?php foreach ($inquiries as $i): ?>
<div id="view<?php echo $i['inquiry_id']; ?>" class="modal fade"><div class="modal-dialog"><div class="modal-content">
<div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">Inquiry Details - <?php echo e($i['name']); ?></h4></div>
<div class="modal-body">
<table class="table table-condensed table-bordered" style="font-size:12px;">
<tr><td width="35%"><strong>Inquiry No</strong></td><td><?php echo $i['inquiry_id']; ?></td></tr>
<tr><td><strong>Student Name</strong></td><td><?php echo e($i['name']); ?></td></tr>
<tr><td><strong>Father Name</strong></td><td><?php echo e($i['father_name'] ?? '-'); ?></td></tr>
<tr><td><strong>Student Cell</strong></td><td><?php echo e($i['phone'] ?? '-'); ?></td></tr>
<tr><td><strong>Father Cell</strong></td><td><?php echo e($i['father_cellno'] ?? '-'); ?></td></tr>
<tr><td><strong>Course/Class</strong></td><td><?php echo e($i['class_name'] ?? '-'); ?> / <?php echo e($i['section_name'] ?? '-'); ?></td></tr>
<tr><td><strong>Status</strong></td><td><span class="si-status-pill" style="background:<?php echo $statusColors[$i['status']]??'#6B7280'; ?>;"><?php echo e($i['status']); ?></span></td></tr>
<tr><td><strong>Locality</strong></td><td><?php echo e($i['locality'] ?? '-'); ?></td></tr>
<tr><td><strong>Visiting Date</strong></td><td><?php echo $i['visit_date']?date('d-M-Y',strtotime($i['visit_date'])):'-'; ?></td></tr>
<tr><td><strong>Test Date</strong></td><td><?php echo $i['test_date']?date('d-M-Y',strtotime($i['test_date'])).($i['test_time']?' / '.$i['test_time']:''):'-'; ?></td></tr>
<tr><td><strong>Address</strong></td><td><?php echo e($i['address'] ?? '-'); ?></td></tr>
<tr><td><strong>Remarks</strong></td><td><?php echo e($i['remarks'] ?? '-'); ?></td></tr>
<tr><td><strong>Added By</strong></td><td><?php echo e($i['added_by_name'] ?? 'Admin'); ?></td></tr>
</table>
</div>
<div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Close</button></div>
</div></div></div>

<div class="modal fade" id="status<?php echo $i['inquiry_id']; ?>"><div class="modal-dialog"><div class="modal-content">
<form method="post" action="student_inquiry.php"><input type="hidden" name="action" value="ChangeStatus"><input type="hidden" name="inquiry_id" value="<?php echo $i['inquiry_id']; ?>">
<div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">Change Status</h4></div>
<div class="modal-body"><div class="form-group"><label>Status</label><select name="status" class="form-control" style="height:36px;"><?php foreach ($statuses as $st): ?><option value="<?php echo $st; ?>" <?php echo $i['status']===$st?'selected':''; ?>><?php echo $st; ?></option><?php endforeach; ?></select></div></div>
<div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Close</button><button type="submit" class="btn btn-primary" style="background:var(--si-brand);border-color:var(--si-brand);">Update</button></div>
</form>
</div></div></div>

<div class="modal fade" id="enroll<?php echo $i['inquiry_id']; ?>"><div class="modal-dialog"><div class="modal-content">
<form method="post" action="student_inquiry.php"><input type="hidden" name="action" value="EnrollInquiry"><input type="hidden" name="inquiry_id" value="<?php echo $i['inquiry_id']; ?>"><input type="hidden" name="name" value="<?php echo e($i['name']); ?>"><input type="hidden" name="father_name" value="<?php echo e($i['father_name']??''); ?>"><input type="hidden" name="phone" value="<?php echo e($i['phone']??''); ?>">
<div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">Enroll Student</h4></div>
<div class="modal-body"><p style="font-size:12px;color:#52514e;">Enroll <strong><?php echo e($i['name']); ?></strong> as a student.</p>
<div class="form-group"><label>Course/Class</label><select name="class_id" class="form-control" required><option value="">Select</option><?php foreach ($classes as $c): if (!in_array($c['class_name'],$courseNames)) continue; ?><option value="<?php echo $c['class_id']; ?>" <?php echo ($i['class_id']==$c['class_id'])?'selected':''; ?>><?php echo e($c['class_name']); ?></option><?php endforeach; ?></select></div>
<div class="form-group"><label>Section</label><select name="section_id" class="form-control"><option value="">Select</option></select></div>
<div class="form-group"><label>Session</label><input type="text" name="session" class="form-control" value="<?php echo e(get_setting('session_year','2026-2027')); ?>"></div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Close</button><button type="submit" class="btn btn-primary" style="background:var(--si-brand);border-color:var(--si-brand);">Confirm Enrollment</button></div>
</form>
</div></div></div>
<?php endforeach; ?>

</div>

<script src="https://cdn.datatables.net/1.10.13/js/jquery.dataTables.min.js"></script>
<script>
var sectionsData = <?php echo json_encode($sections); ?>;
function getSections(cid,tid){var s=document.getElementById(tid);if(!s)return;s.innerHTML='<option value="All">All</option>';if(!cid||cid==='All')return;sectionsData.forEach(function(sv){if(String(sv.class_id)===String(cid)){var o=document.createElement('option');o.value=sv.section_id;o.textContent=sv.section_name;s.appendChild(o);}});}
(function(){var ps=10,cp=1,rows=Array.prototype.slice.call(document.querySelectorAll('#excelexpo tbody tr')),pi=document.getElementById('siPageInfo'),pg=document.getElementById('siPager'),se=document.getElementById('siShowEntries');function rp(tp){pg.innerHTML='';function ab(l,p,d,a){var b=document.createElement('button');b.type='button';b.className='si-page-btn'+(a?' active':'');b.textContent=l;b.disabled=d;b.addEventListener('click',function(){cp=p;r();});pg.appendChild(b);}ab('\u00ab',1,cp===1,false);ab('\u2039',Math.max(1,cp-1),cp===1,false);var sp=Math.max(1,cp-2),ep=Math.min(tp,sp+4);for(var p=sp;p<=ep;p++)ab(String(p),p,false,p===cp);ab('\u203a',Math.min(tp,cp+1),cp===tp,false);ab('\u00bb',tp,cp===tp,false);}function r(){var t=rows.length,tp=Math.max(1,Math.ceil(t/ps));if(cp>tp)cp=tp;var st=(cp-1)*ps,en=Math.min(st+ps,t);rows.forEach(function(rw,i){rw.style.display=(i>=st&&i<en)?'':'\u0020none';});pi.textContent=t===0?'Showing 0 entries':'Showing '+(st+1)+' to '+en+' of '+t+' entries';rp(tp);}if(se){se.addEventListener('change',function(){ps=this.value==='All'?Math.max(rows.length,1):parseInt(this.value,10);cp=1;r();});}r();})();
(function(){var t=document.getElementById('siAdvToggle'),g=document.getElementById('siFilterGrid');if(t&&g){t.addEventListener('click',function(){g.classList.toggle('si-collapsed');t.classList.toggle('open');});}})();
(function(){var sa=document.getElementById('siSelectAll');if(sa){sa.addEventListener('change',function(){document.querySelectorAll('.si-row-cb').forEach(function(cb){if(cb.closest('tr').style.display!=='none')cb.checked=sa.checked;});});}document.addEventListener('change',function(e){if(e.target.classList&&e.target.classList.contains('si-row-cb')){var all=document.querySelectorAll('.si-row-cb');if(sa)sa.checked=all.length>0&&Array.prototype.every.call(all,function(cb){return cb.checked;});}});})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
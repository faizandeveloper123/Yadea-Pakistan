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
$res = db_query("SELECT class_id, class_name FROM classes WHERE status=1 ORDER BY class_name");
while ($row = $res->fetch_assoc()) { $classes[] = $row; }

$sections = [];
$res = db_query("SELECT section_id, class_id, section_name FROM sections ORDER BY section_name");
while ($row = $res->fetch_assoc()) { $sections[] = $row; }

$localities = [];
$res = db_query("SELECT DISTINCT locality FROM student_inquiries WHERE locality != '' AND locality IS NOT NULL ORDER BY locality");
while ($row = $res->fetch_assoc()) { $localities[] = $row['locality']; }

$statusMap = [
    'New' => 1, 'In-Process' => 2, 'Interested' => 4, 'Admitted' => 5, 'Cancelled' => 6
];
$statusRev = [1 => 'New', 2 => 'In-Process', 4 => 'Interested', 5 => 'Admitted', 6 => 'Cancelled'];
$statusColors = [
    'New' => '#A05AFF', 'In-Process' => '#337ab7', 'Interested' => '#4BCBEB',
    'Admitted' => '#1BCFB4', 'Cancelled' => '#D32D41',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'AddInquiry') {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        if ($name === '') {
            $error = 'Name is required.';
        } else {
            $st2 = db_prepare("INSERT INTO student_inquiries (name, father_name, phone, father_cellno, email, class_id, section_id, session, admission_source, locality, address, visit_date, test_date, test_time, remarks, status, created_by)
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'New', ?)");
            $uid = $_SESSION['user_id'] ?? null;
            $class_id = (int) ($_POST['class_id'] ?? 0) > 0 ? (int) $_POST['class_id'] : null;
            $section_id = (int) ($_POST['section_id'] ?? 0) > 0 ? (int) $_POST['section_id'] : null;
            $vis = trim($_POST['visit_date'] ?? '') !== '' ? trim($_POST['visit_date']) : null;
            $test = trim($_POST['test_date'] ?? '') !== '' ? trim($_POST['test_date']) : null;
            $test_time = trim($_POST['test_time'] ?? '');
            $st2->bind_param('ssssssssssssssssi',
                $name, trim($_POST['father_name'] ?? ''), $phone, trim($_POST['father_cellno'] ?? ''),
                trim($_POST['email'] ?? ''), $class_id, $section_id, trim($_POST['session'] ?? ''),
                trim($_POST['admission_source'] ?? ''), trim($_POST['locality'] ?? ''), trim($_POST['address'] ?? ''),
                $vis, $test, $test_time, trim($_POST['remarks'] ?? ''), $uid);
            $st2->execute();
            $message = 'Inquiry added successfully!';
        }
    }

    if ($action === 'ChangeStatus') {
        $iid = (int) ($_POST['inquiry_id'] ?? 0);
        $status = trim($_POST['status'] ?? 'New');
        if ($iid > 0) {
            $st2 = db_prepare("UPDATE student_inquiries SET status=? WHERE inquiry_id=?");
            $st2->bind_param('si', $status, $iid);
            $st2->execute();
            $message = 'Inquiry status updated!';
        }
    }

    if ($action === 'AddNote') {
        $iid = (int) ($_POST['inquiry_id'] ?? 0);
        $note = trim($_POST['note'] ?? '');
        if ($iid > 0 && $note !== '') {
            $st2 = db_prepare("INSERT INTO inquiry_notes (inquiry_id, note, created_by) VALUES (?, ?, ?)");
            $uid = $_SESSION['user_id'] ?? null;
            $st2->bind_param('isi', $iid, $note, $uid);
            $st2->execute();
            $message = 'Note added!';
        }
    }

    if ($action === 'EnrollInquiry') {
        $iid = (int) ($_POST['inquiry_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $father = trim($_POST['father_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $class_id = (int) ($_POST['class_id'] ?? 0) > 0 ? (int) $_POST['class_id'] : null;
        $section_id = (int) ($_POST['section_id'] ?? 0) > 0 ? (int) $_POST['section_id'] : null;
        $session = trim($_POST['session'] ?? '') !== '' ? trim($_POST['session'] ?? '') : (string) get_setting('session_year', '2026-2027');

        if ($name === '') {
            $error = 'Student name is required for enrollment.';
        } else {
            $gr = '';
            $mx = db_query("SELECT MAX(CAST(gr_no AS UNSIGNED)) m FROM students WHERE gr_no <> ''")->fetch_assoc();
            $seq = ((int) ($mx['m'] ?? 0)) + 1;
            $gr = date('y') . '-' . $seq;
            $st2 = db_prepare("INSERT INTO students (gr_no, first_name, last_name, father_name, phone, class_id, section_id, session, status, admission_date)
                               VALUES (?, ?, '', ?, ?, ?, ?, ?, '1', CURDATE())");
            $st2->bind_param('ssssiis', $gr, $name, $father, $phone, $class_id, $section_id, $session);
            $st2->execute();
            $newId = (int) $st2->insert_id;

            $st3 = db_prepare("UPDATE student_inquiries SET status='Admitted', class_id=?, section_id=?, session=? WHERE inquiry_id=?");
            $st3->bind_param('iisi', $class_id, $section_id, $session, $iid);
            $st3->execute();

            $st4 = db_prepare("INSERT INTO inquiry_notes (inquiry_id, note, created_by) VALUES (?, ?, ?)");
            $uid = $_SESSION['user_id'] ?? null;
            $enote = 'Enrolled as student (GR No: ' . $gr . ', ID: ' . $newId . ')';
            $st4->bind_param('isi', $iid, $enote, $uid);
            $st4->execute();

            $message = 'Inquiry enrolled! Student created with GR No ' . $gr . '.';
        }
    }

    if ($action === 'DeleteInquiry') {
        $iid = (int) ($_POST['inquiry_id'] ?? 0);
        $st2 = db_prepare("DELETE FROM student_inquiries WHERE inquiry_id=?");
        $st2->bind_param('i', $iid);
        $st2->execute();
        $message = 'Inquiry deleted!';
    }
}

$delete_id = (int) ($_GET['delete_id'] ?? 0);
if ($delete_id > 0) {
    $st2 = db_prepare("DELETE FROM student_inquiries WHERE inquiry_id=?");
    $st2->bind_param('i', $delete_id);
    $st2->execute();
    $message = 'Inquiry deleted!';
    $url = 'student_inquiry.php';
    if ($filter_from) $url .= '?from=' . urlencode($filter_from);
    header('Location: ' . $url);
    exit;
}

$filter_from = trim($_GET['from'] ?? '');
$filter_to = trim($_GET['to'] ?? '');
$filter_status = trim($_GET['status'] ?? '');
$filter_locality = trim($_GET['locality'] ?? '');
$filter_class_id = trim($_GET['class_id'] ?? '');
$filter_section = trim($_GET['section'] ?? '');
$search = trim($_GET['search'] ?? '');

$where = [];
$params = [];
$types = '';
if ($filter_from !== '') {
    $where[] = 'DATE(i.created_at) >= ?'; $params[] = $filter_from; $types .= 's';
}
if ($filter_to !== '') {
    $where[] = 'DATE(i.created_at) <= ?'; $params[] = $filter_to; $types .= 's';
}
if ($filter_status !== '' && $filter_status !== 'All') {
    $s = $statusRev[(int)$filter_status] ?? null;
    if ($s) { $where[] = 'i.status = ?'; $params[] = $s; $types .= 's'; }
}
if ($filter_locality !== '' && $filter_locality !== 'All') {
    $where[] = 'i.locality = ?'; $params[] = $filter_locality; $types .= 's';
}
if ($filter_class_id !== '' && $filter_class_id !== 'All') {
    $where[] = 'i.class_id = ?'; $params[] = (int)$filter_class_id; $types .= 'i';
}
if ($filter_section !== '' && $filter_section !== 'All') {
    $where[] = 'i.section_id = ?'; $params[] = (int)$filter_section; $types .= 'i';
}
if ($search !== '') {
    $where[] = '(i.name LIKE ? OR i.father_name LIKE ? OR i.phone LIKE ? OR i.email LIKE ?)';
    $like = '%' . $search . '%';
    for ($x = 0; $x < 4; $x++) { $params[] = $like; $types .= 's'; }
}

$sql = "SELECT i.*, c.class_name, sec.section_name, u.full_name added_by_name FROM student_inquiries i
        LEFT JOIN classes c ON i.class_id = c.class_id
        LEFT JOIN sections sec ON i.section_id = sec.section_id
        LEFT JOIN users u ON i.created_by = u.user_id";
if (count($where) > 0) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY i.created_at DESC';

$inquiries = [];
if (count($params) > 0) {
    $st = db_prepare($sql); $st->bind_param($types, ...$params); $st->execute(); $r = $st->get_result();
} else {
    $r = db_query($sql);
}
while ($row = $r->fetch_assoc()) { $inquiries[] = $row; }

$statuses = ['New', 'In-Process', 'Interested', 'Admitted', 'Cancelled'];
$statusCounts = [];
$sr = db_query("SELECT status, COUNT(*) c FROM student_inquiries GROUP BY status");
while ($row = $sr->fetch_assoc()) { $statusCounts[$row['status']] = (int) $row['c']; }
$totalAll = (int) (db_query("SELECT COUNT(*) c FROM student_inquiries")->fetch_assoc()['c'] ?? 0);

include __DIR__ . '/includes/header.php';
?>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root {
  --si-surface: #ffffff;
  --si-page: #f6f7fb;
  --si-ink: #0b0b0b;
  --si-ink-secondary: #52514e;
  --si-ink-muted: #898781;
  --si-border: rgba(11,11,11,0.08);
  --si-brand: #00AFEF;
}
body { font-family:'Inter',Arial,sans-serif; }
.si-topbar { background:var(--si-surface); border:1px solid var(--si-border); border-radius:14px; padding:16px 20px; margin-bottom:14px; display:flex; align-items:flex-start; justify-content:space-between; flex-wrap:wrap; gap:12px; box-shadow:0 1px 3px rgba(16,24,40,0.05); }
.si-crumb { font-size:12px; color:var(--si-ink-muted); margin:0 0 6px 0; }
.si-crumb a { color:var(--si-ink-secondary); text-decoration:none; }
.si-title-row { display:flex; align-items:center; flex-wrap:wrap; gap:8px; }
.si-title-row h2 { margin:0; font-size:21px; font-weight:700; color:var(--si-ink); }
.si-title-badge { font-size:11px; font-weight:700; color:var(--si-brand); background:rgba(0,175,239,0.12); border-radius:999px; padding:3px 10px; }
.si-subtitle { margin:4px 0 0 0; font-size:12.5px; color:var(--si-ink-secondary); }
.si-topbar-actions { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
.si-topbar-actions .btn { border-radius:8px; font-size:13px; }
.si-stat-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(190px,1fr)); gap:12px; margin-bottom:14px; }
.si-stat-card { background:var(--si-surface); border:1px solid var(--si-border); border-radius:12px; padding:14px 16px; box-shadow:0 1px 3px rgba(16,24,40,0.05); display:flex; align-items:center; gap:12px; color:inherit; text-decoration:none; cursor:pointer; transition:transform .15s ease,box-shadow .15s ease,border-color .15s ease; }
.si-stat-card:hover,.si-stat-card:focus { transform:translateY(-2px); box-shadow:0 6px 16px rgba(16,24,40,0.12); color:inherit; text-decoration:none; border-color:var(--si-brand); }
.si-stat-card.active { border-color:var(--si-brand); box-shadow:0 0 0 2px rgba(0,175,239,0.25),0 6px 16px rgba(16,24,40,0.10); }
.si-stat-icon { width:42px; height:42px; border-radius:11px; display:flex; align-items:center; justify-content:center; font-size:17px; flex-shrink:0; }
.si-stat-body { flex:1 1 auto; min-width:0; }
.si-stat-label { font-size:12px; font-weight:700; color:var(--si-ink-secondary); margin-bottom:3px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.si-stat-value-row { display:flex; align-items:baseline; gap:8px; }
.si-stat-value { font-size:22px; font-weight:700; color:var(--si-ink); line-height:1; }
.si-stat-pct { font-size:11px; font-weight:700; padding:2px 7px; border-radius:999px; white-space:nowrap; }
.si-filter-panel { background:var(--si-surface); border:1px solid var(--si-border); border-radius:12px; padding:14px 16px; margin-bottom:14px; box-shadow:0 1px 3px rgba(16,24,40,0.05); }
.si-filter-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:10px; }
.si-filter-grid.si-collapsed { display:none; }
.si-filter-grid .form-group { margin-bottom:0; }
.si-filter-grid label { display:block; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.03em; color:var(--si-ink-muted); margin-bottom:4px; }
.si-filter-grid .form-control { border-radius:8px; border-color:var(--si-border); font-size:13px; height:36px; padding:6px 10px; box-shadow:none; }
.si-filter-grid select.form-control { height:36px; }
.si-search-row { display:flex; align-items:center; gap:8px; margin-top:12px; flex-wrap:wrap; }
.si-search-box { position:relative; flex:1 1 260px; }
.si-search-box i { position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--si-ink-muted); font-size:13px; }
.si-search-box input { width:100%; border:1px solid var(--si-border); border-radius:8px; padding:8px 12px 8px 32px; font-size:13px; box-shadow:none; }
.si-btn-search,.si-btn-reset { border-radius:8px; }
.si-advanced-toggle { margin-left:auto; font-size:12px; color:var(--si-ink-secondary); background:var(--si-page); border:1px solid var(--si-border); border-radius:999px; padding:7px 14px; cursor:pointer; }
.si-advanced-toggle i { margin-left:5px; transition:transform .15s ease; }
.si-advanced-toggle.open i { transform:rotate(180deg); }
.si-toolbar { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; margin-bottom:10px; }
.si-export-group { display:flex; gap:8px; flex-wrap:wrap; }
.si-export-btn { display:inline-flex; align-items:center; gap:6px; font-size:12px; font-weight:600; padding:7px 12px; border-radius:8px; border:1px solid var(--si-border); background:var(--si-surface); color:var(--si-ink); text-decoration:none; cursor:pointer; }
.si-export-btn:hover { background:var(--si-page); color:var(--si-ink); text-decoration:none; }
.si-export-btn i { font-size:13px; }
.si-export-btn.xls-btn i { color:#1e7d34; }
.si-export-btn.csv-btn i { color:#2a78d6; }
.si-export-btn.pdf-btn i { color:#d63939; }
.si-export-btn.txt-btn i { color:#6c757d; }
.si-show-entries { display:flex; align-items:center; gap:6px; font-size:12px; color:var(--si-ink-secondary); }
.si-show-entries select { border-radius:8px; border:1px solid var(--si-border); padding:5px 8px; font-size:12px; }
.si-table-card { background:var(--si-surface); border:1px solid var(--si-border); border-radius:12px; overflow:hidden; box-shadow:0 1px 3px rgba(16,24,40,0.05); }
.si-table-wrap { overflow-x:auto; }
table#excelexpo { margin-bottom:0 !important; }
table#excelexpo caption { display:none; }
table#excelexpo thead th { background:var(--si-brand); color:#fff; font-size:11.5px; font-weight:700; text-transform:uppercase; letter-spacing:0.02em; border:none !important; padding:12px 10px; white-space:nowrap; }
table#excelexpo tbody td { font-size:12.5px; vertical-align:middle; padding:9px 10px; border-bottom:1px solid var(--si-page); border-top:none !important; }
table#excelexpo tbody tr:hover { background:#f9fbff; }
.si-status-pill { display:inline-block; padding:4px 12px; border-radius:999px; font-size:11px; font-weight:700; color:#fff; border:none; cursor:pointer; }
.si-action-icons { display:flex; align-items:center; gap:6px; }
.si-icon-btn { width:28px; height:28px; border-radius:7px; display:inline-flex; align-items:center; justify-content:center; border:1px solid var(--si-border); background:var(--si-page); color:var(--si-ink-secondary); text-decoration:none; font-size:12px; }
.si-icon-btn:hover { color:#fff; text-decoration:none; }
.si-icon-btn.si-icon-view:hover { background:#2a78d6; border-color:#2a78d6; }
.si-icon-btn.si-icon-edit:hover { background:#eda100; border-color:#eda100; }
.si-icon-btn.si-icon-delete:hover { background:#dc3545; border-color:#dc3545; }
.si-icon-btn.si-icon-more:hover { background:#52514e; border-color:#52514e; }
.si-pagination-bar { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; padding:12px 16px; }
.si-pagination-info { font-size:12px; color:var(--si-ink-secondary); }
.si-pager { display:flex; gap:4px; }
.si-page-btn { min-width:28px; height:28px; padding:0 8px; border-radius:6px; border:1px solid var(--si-border); background:var(--si-surface); color:var(--si-ink-secondary); font-size:12px; cursor:pointer; }
.si-page-btn.active { background:var(--si-brand); border-color:var(--si-brand); color:#fff; font-weight:700; }
.si-page-btn:disabled { opacity:0.4; cursor:not-allowed; }
</style>

<div class="right_col" role="main">
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
<?php if ($message): ?><div class="alert alert-success" style="margin:0;padding:6px 12px;font-size:12px;border-radius:8px;"><?php echo e($message); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger" style="margin:0;padding:6px 12px;font-size:12px;border-radius:8px;"><?php echo e($error); ?></div><?php endif; ?>
<button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addInquiryModal" style="background:var(--si-brand);border-color:var(--si-brand);"><i class="fas fa-plus"></i> Add New Inquiry</button>
</div>
</div>

<div class="si-stat-grid">
<?php
$statCards = [
    'New' => ['icon' => 'fa-file-alt', 'color' => '#A05AFF'],
    'In-Process' => ['icon' => 'fa-hourglass-half', 'color' => '#337ab7'],
    'Interested' => ['icon' => 'fa-info-circle', 'color' => '#4BCBEB'],
    'Admitted' => ['icon' => 'fa-user-plus', 'color' => '#1BCFB4'],
    'Cancelled' => ['icon' => 'fa-times-circle', 'color' => '#D32D41'],
];
$totalInquiries = count($inquiries);
foreach ($statCards as $st => $info):
    $cnt = $statusCounts[$st] ?? 0;
    $pct = $totalAll > 0 ? round(($cnt / $totalAll) * 100, 1) : 0;
    $active = ($filter_status === (string)($statusMap[$st])) ? ' active' : '';
?>
<a class="si-stat-card<?php echo $active; ?>" href="student_inquiry.php?from=<?php echo e($filter_from ?: date('Y-01-01')); ?>&to=<?php echo e($filter_to ?: date('Y-m-d')); ?>&status=<?php echo $statusMap[$st]; ?>&class_id=All&section=All&locality=All&addaccountAdmin=1" title="Show only <?php echo $st; ?> inquiries">
<div class="si-stat-icon" style="background:<?php echo $info['color']; ?>22; color:<?php echo $info['color']; ?>;"><i class="fas <?php echo $info['icon']; ?>"></i></div>
<div class="si-stat-body">
<div class="si-stat-label"><?php echo $st; ?></div>
<div class="si-stat-value-row">
<span class="si-stat-value"><?php echo $cnt; ?></span>
<span class="si-stat-pct" style="background:<?php echo $info['color']; ?>22; color:<?php echo $info['color']; ?>;"><?php echo $pct; ?>%</span>
</div>
</div>
</a>
<?php endforeach; ?>
<a class="si-stat-card<?php echo ($filter_status === '' || $filter_status === 'All') ? ' active' : ''; ?>" href="student_inquiry.php?from=<?php echo e($filter_from ?: date('Y-01-01')); ?>&to=<?php echo e($filter_to ?: date('Y-m-d')); ?>&status=All&class_id=All&section=All&locality=All&addaccountAdmin=1" title="Show all inquiries">
<div class="si-stat-icon" style="background:#2a78d622; color:#2a78d6;"><i class="fas fa-check-circle"></i></div>
<div class="si-stat-body">
<div class="si-stat-label">Total Inquiries</div>
<div class="si-stat-value-row">
<span class="si-stat-value"><?php echo $totalAll; ?></span>
<span class="si-stat-pct" style="background:#2a78d622; color:#2a78d6;">100%</span>
</div>
</div>
</a>
</div>

<form action="student_inquiry.php" method="get">
<div class="si-filter-panel">
<div class="si-filter-grid" id="siFilterGrid">
<div class="form-group">
<label class="required">From Date</label>
<input name="from" value="<?php echo e($filter_from ?: date('Y-01-01')); ?>" type="date" class="form-control">
</div>
<div class="form-group">
<label class="required">To Date</label>
<input name="to" value="<?php echo e($filter_to ?: date('Y-m-d')); ?>" type="date" class="form-control">
</div>
<div class="form-group">
<label class="required">Class</label>
<select name="class_id" class="form-control" onchange="getSections(this.value,'txt_section1')">
<option value="All">All</option>
<?php foreach ($classes as $c): ?>
<option value="<?php echo $c['class_id']; ?>" <?php echo $filter_class_id === (string)$c['class_id'] ? 'selected' : ''; ?>><?php echo e($c['class_name']); ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="form-group">
<label>Section</label>
<select name="section" id="txt_section1" class="form-control">
<option value="All">All</option>
</select>
</div>
<div class="form-group">
<label class="required">Inquiry Status</label>
<select name="status" class="form-control">
<option value="All">All</option>
<?php foreach ($statuses as $st): ?>
<option value="<?php echo $statusMap[$st]; ?>" <?php echo $filter_status === (string)$statusMap[$st] ? 'selected' : ''; ?>><?php echo $st; ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="form-group">
<label class="required">Localities</label>
<select name="locality" class="form-control">
<option value="All">All</option>
<?php foreach ($localities as $loc): ?>
<option value="<?php echo e($loc); ?>" <?php echo $filter_locality === $loc ? 'selected' : ''; ?>><?php echo e($loc); ?></option>
<?php endforeach; ?>
</select>
</div>
</div>
<div class="si-search-row">
<div class="si-search-box">
<i class="fas fa-search"></i>
<input type="text" name="search" value="<?php echo e($search); ?>" placeholder="Search by Name, Cell No, Inquiry No...">
</div>
<input type="hidden" name="addaccountAdmin" value="1">
<button type="submit" class="btn btn-primary si-btn-search btn-sm" style="background:var(--si-brand);border-color:var(--si-brand);"><i class="fas fa-search"></i> Search</button>
<a href="student_inquiry.php" class="btn btn-default si-btn-reset btn-sm">Reset</a>
<button type="button" class="si-advanced-toggle" id="siAdvToggle">Advanced Filter <i class="fas fa-chevron-down"></i></button>
</div>
</div>
</form>

<div class="si-toolbar">
<div class="si-export-group">
<button type="button" class="si-export-btn xls-btn" id="siExportExcel"><i class="fas fa-file-excel"></i> Export to Excel</button>
<button type="button" class="si-export-btn csv-btn" id="siExportCsv"><i class="fas fa-file-alt"></i> Export to CSV</button>
<button type="button" class="si-export-btn pdf-btn" id="siExportPdf"><i class="fas fa-file-pdf"></i> Export to PDF</button>
<button type="button" class="si-export-btn txt-btn" id="siExportTxt"><i class="fas fa-file"></i> Export to TXT</button>
</div>
<div class="si-show-entries">
Show <select id="siShowEntries"><option value="10">10</option><option value="25">25</option><option value="50">50</option><option value="100">100</option><option value="All">All</option></select> entries
</div>
</div>

<div class="si-table-card">
<div class="si-table-wrap">
<table id="excelexpo" class="table table-striped" style="width:100%;">
<thead>
<tr>
<th width="3%"><input type="checkbox" id="siSelectAll" title="Select All"></th>
<th width="5%">S.No</th>
<th width="10%">Student Name</th>
<th width="8%">Student Cell</th>
<th width="12%">Class/Section</th>
<th width="8%">Status</th>
<th width="7%">Locality</th>
<th width="7%">Visiting</th>
<th width="7%">Added By</th>
<th width="9%">Action</th>
</tr>
</thead>
<tbody>
<?php if ($totalInquiries === 0): ?>
<tr><td colspan="10" style="text-align:center;color:#898781;padding:30px;">No inquiries found.</td></tr>
<?php endif; ?>
<?php $sn = 1; foreach ($inquiries as $i): ?>
<tr>
<td style="text-align:center;"><input type="checkbox" class="si-row-cb" data-id="<?php echo $i['inquiry_id']; ?>" data-name="<?php echo e($i['name']); ?>" data-phone="<?php echo e($i['phone']); ?>" data-class="<?php echo e($i['class_name'] ?? ''); ?>" data-date="<?php echo e($i['created_at']); ?>"></td>
<td style="text-align:center;"><?php echo $sn++; ?></td>
<td><?php echo e($i['name']); ?></td>
<td><?php echo e($i['phone'] ?? '-'); ?></td>
<td><?php echo e($i['class_name'] ?? '-'); ?> / <?php echo e($i['section_name'] ?? '-'); ?></td>
<td><button type="button" class="si-status-pill" style="background-color:<?php echo $statusColors[$i['status']] ?? '#6B7280'; ?>;" data-toggle="modal" data-target="#status<?php echo $i['inquiry_id']; ?>"><?php echo e($i['status']); ?></button></td>
<td><?php echo e($i['locality'] ?? '-'); ?></td>
<td><?php echo date('d-M-Y', strtotime($i['created_at'])); ?></td>
<td><?php echo e($i['added_by_name'] ?? 'Admin'); ?></td>
<td>
<div class="si-action-icons">
<a href="#" class="si-icon-btn si-icon-view" data-toggle="modal" data-target="#view<?php echo $i['inquiry_id']; ?>" title="View"><i class="fas fa-eye"></i></a>
<a href="student_inquiry_form.php?id=<?php echo $i['inquiry_id']; ?>" class="si-icon-btn si-icon-edit" title="Edit"><i class="fas fa-pencil-alt"></i></a>
<a onClick="return confirm('Are you sure you want to delete this record?');" href="student_inquiry.php?delete_id=<?php echo $i['inquiry_id']; ?>" class="si-icon-btn si-icon-delete" title="Delete"><i class="fas fa-trash"></i></a>
<div class="btn-group">
<button data-toggle="dropdown" class="si-icon-btn si-icon-more" type="button" title="More"><i class="fas fa-ellipsis-v"></i></button>
<ul role="menu" class="dropdown-menu dropdown-menu-right" style="font-size:13px;">
<li><a href="#" onclick="viewInquiry(<?php echo $i['inquiry_id']; ?>); return false;">Add Notes</a></li>
<li><a href="#" onclick="printInquiry(<?php echo $i['inquiry_id']; ?>); return false;">Print</a></li>
<li><a href="#" data-toggle="modal" data-target="#enroll<?php echo $i['inquiry_id']; ?>">Enroll</a></li>
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

<!-- Modals for each inquiry -->
<?php foreach ($inquiries as $i): ?>
<div id="view<?php echo $i['inquiry_id']; ?>" class="modal fade" role="dialog">
<div class="modal-dialog"><div class="modal-content"><div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">Inquiry Details - <?php echo e($i['name']); ?></h4></div>
<div class="modal-body">
<table class="table table-condensed">
<tr><td width="40%"><strong>Inquiry No</strong></td><td><?php echo $i['inquiry_id']; ?></td></tr>
<tr><td><strong>Student Name</strong></td><td><?php echo e($i['name']); ?></td></tr>
<tr><td><strong>Father Name</strong></td><td><?php echo e($i['father_name'] ?? '-'); ?></td></tr>
<tr><td><strong>Student Cell</strong></td><td><?php echo e($i['phone'] ?? '-'); ?></td></tr>
<tr><td><strong>Father Cell</strong></td><td><?php echo e($i['father_cellno'] ?? '-'); ?></td></tr>
<tr><td><strong>Class / Section</strong></td><td><?php echo e($i['class_name'] ?? '-'); ?> / <?php echo e($i['section_name'] ?? '-'); ?></td></tr>
<tr><td><strong>Status</strong></td><td><span class="si-status-pill" style="background-color:<?php echo $statusColors[$i['status']] ?? '#6B7280'; ?>;"><?php echo e($i['status']); ?></span></td></tr>
<tr><td><strong>Locality</strong></td><td><?php echo e($i['locality'] ?? '-'); ?></td></tr>
<tr><td><strong>Visiting Date</strong></td><td><?php echo $i['visit_date'] ? date('d-M-Y', strtotime($i['visit_date'])) : '-'; ?></td></tr>
<tr><td><strong>Test Date</strong></td><td><?php echo $i['test_date'] ? date('d-M-Y', strtotime($i['test_date'])) . ($i['test_time'] ? ' / ' . $i['test_time'] : '') : '-'; ?></td></tr>
<tr><td><strong>Address</strong></td><td><?php echo e($i['address'] ?? '-'); ?></td></tr>
<tr><td><strong>Remarks</strong></td><td><?php echo e($i['remarks'] ?? '-'); ?></td></tr>
<tr><td><strong>Added By</strong></td><td><?php echo e($i['added_by_name'] ?? 'Admin'); ?></td></tr>
</table>
</div>
<div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Close</button></div></div></div></div>

<div class="modal fade" id="status<?php echo $i['inquiry_id']; ?>" tabindex="-1" role="dialog">
<div class="modal-dialog" role="document"><div class="modal-content">
<form method="post" action="student_inquiry.php">
<input type="hidden" name="action" value="ChangeStatus">
<input type="hidden" name="inquiry_id" value="<?php echo $i['inquiry_id']; ?>">
<div class="modal-header"><h5 class="modal-title">Change Inquiry Status</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
<div class="modal-body">
<div class="form-group">
<label>Status</label>
<select name="status" class="form-control" style="height:40px;">
<?php foreach ($statuses as $st): ?>
<option value="<?php echo $st; ?>" <?php echo $i['status'] === $st ? 'selected' : ''; ?>><?php echo $st; ?></option>
<?php endforeach; ?>
</select>
</div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Close</button><button type="submit" class="btn btn-primary" style="background:var(--si-brand);border-color:var(--si-brand);">Change Status</button></div>
</form>
</div></div></div>

<div class="modal fade" id="enroll<?php echo $i['inquiry_id']; ?>" role="dialog">
<div class="modal-dialog"><div class="modal-content">
<form method="post" action="student_inquiry.php">
<input type="hidden" name="action" value="EnrollInquiry">
<input type="hidden" name="inquiry_id" value="<?php echo $i['inquiry_id']; ?>">
<input type="hidden" name="name" value="<?php echo e($i['name']); ?>">
<input type="hidden" name="father_name" value="<?php echo e($i['father_name'] ?? ''); ?>">
<input type="hidden" name="phone" value="<?php echo e($i['phone'] ?? ''); ?>">
<div class="modal-header" style="border-bottom:none;"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title" style="text-align:center;">Are you sure you want to Enroll this Student? <br> <?php echo e($i['name']); ?> - <?php echo e($i['father_name'] ?? ''); ?></h4></div>
<div class="modal-body">
<div class="col-md-12" style="padding:8px;">
<div class="form-group"><label>Class</label>
<select name="class_id" class="form-control" required>
<option value="">-- Select --</option>
<?php foreach ($classes as $c): ?>
<option value="<?php echo $c['class_id']; ?>" <?php echo ($i['class_id'] == $c['class_id']) ? 'selected' : ''; ?>><?php echo e($c['class_name']); ?></option>
<?php endforeach; ?>
</select></div>
<div class="form-group"><label>Section</label>
<select name="section_id" class="form-control">
<option value="">-- Select --</option>
</select></div>
<div class="form-group"><label>Session</label>
<input type="text" name="session" class="form-control" value="<?php echo e(get_setting('session_year', '2026-2027')); ?>"></div>
</div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Close</button><button type="submit" class="btn btn-primary" style="background:var(--si-brand);border-color:var(--si-brand);">Confirm Enrollment</button></div>
</form>
</div></div></div>
<?php endforeach; ?>

</div>

<!-- Add Inquiry Modal -->
<div class="modal fade" id="addInquiryModal" tabindex="-1" role="dialog">
<div class="modal-dialog modal-lg" role="document">
<div class="modal-content">
<form method="post" action="student_inquiry.php">
<input type="hidden" name="action" value="AddInquiry">
<div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">Add New Inquiry</h4></div>
<div class="modal-body">
<div class="row">
<div class="form-group col-md-6"><label class="required">Student Name</label><input type="text" name="name" class="form-control" required></div>
<div class="form-group col-md-6"><label>Father Name</label><input type="text" name="father_name" class="form-control"></div>
</div>
<div class="row">
<div class="form-group col-md-4"><label>Contact No</label><input type="text" name="phone" class="form-control"></div>
<div class="form-group col-md-4"><label>Father Cell No</label><input type="text" name="father_cellno" class="form-control"></div>
<div class="form-group col-md-4"><label>Email</label><input type="email" name="email" class="form-control"></div>
</div>
<div class="row">
<div class="form-group col-md-4"><label>Interested Class</label><select name="class_id" class="form-control" onchange="getSections(this.value,'ai_section')"><option value="0">Any</option><?php foreach ($classes as $c): ?><option value="<?php echo $c['class_id']; ?>"><?php echo e($c['class_name']); ?></option><?php endforeach; ?></select></div>
<div class="form-group col-md-4"><label>Section</label><select name="section_id" id="ai_section" class="form-control"><option value="0">Any</option></select></div>
<div class="form-group col-md-4"><label>Session</label><input type="text" name="session" class="form-control" value="<?php echo e(get_setting('session_year', '2026-2027')); ?>"></div>
</div>
<div class="row">
<div class="form-group col-md-6"><label>Locality</label><input type="text" name="locality" class="form-control"></div>
<div class="form-group col-md-6"><label>Admission Source</label><input type="text" name="admission_source" class="form-control" placeholder="e.g. Walk-in, Friend, Advertisement"></div>
</div>
<div class="row">
<div class="form-group col-md-4"><label>Visit Date</label><input type="date" name="visit_date" class="form-control"></div>
<div class="form-group col-md-4"><label>Test Date</label><input type="date" name="test_date" class="form-control"></div>
<div class="form-group col-md-4"><label>Test Time</label><input type="text" name="test_time" class="form-control" placeholder="e.g. 10:30 AM"></div>
</div>
<div class="row">
<div class="form-group col-md-6"><label>Address</label><textarea name="address" class="form-control" rows="2"></textarea></div>
<div class="form-group col-md-6"><label>Remarks</label><textarea name="remarks" class="form-control" rows="2"></textarea></div>
</div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Close</button><button type="submit" class="btn btn-primary" style="background:var(--si-brand);border-color:var(--si-brand);">Save Inquiry</button></div>
</form>
</div></div></div>

<script src="https://cdn.datatables.net/1.10.13/js/jquery.dataTables.min.js"></script>
<script>
var sectionsData = <?php echo json_encode($sections); ?>;

function getSections(classId, targetId) {
    var sel = document.getElementById(targetId);
    if (!sel) return;
    sel.innerHTML = '<option value="All">All</option>';
    if (!classId || classId === 'All') return;
    sectionsData.forEach(function(s) {
        if (String(s.class_id) === String(classId)) {
            var o = document.createElement('option');
            o.value = s.section_id;
            o.textContent = s.section_name;
            sel.appendChild(o);
        }
    });
}

// Pagination
(function() {
    var pageSize = 10, currentPage = 1;
    var rows = Array.prototype.slice.call(document.querySelectorAll('#excelexpo tbody tr'));
    var pageInfo = document.getElementById('siPageInfo');
    var pager = document.getElementById('siPager');
    var showEntries = document.getElementById('siShowEntries');

    function renderPager(totalPages) {
        pager.innerHTML = '';
        function addBtn(label, page, disabled, active) {
            var b = document.createElement('button');
            b.type = 'button'; b.className = 'si-page-btn' + (active ? ' active' : '');
            b.textContent = label; b.disabled = disabled;
            b.addEventListener('click', function() { currentPage = page; render(); });
            pager.appendChild(b);
        }
        addBtn('\u00ab', 1, currentPage === 1, false);
        addBtn('\u2039', Math.max(1, currentPage - 1), currentPage === 1, false);
        var startPage = Math.max(1, currentPage - 2);
        var endPage = Math.min(totalPages, startPage + 4);
        for (var p = startPage; p <= endPage; p++) addBtn(String(p), p, false, p === currentPage);
        addBtn('\u203a', Math.min(totalPages, currentPage + 1), currentPage === totalPages, false);
        addBtn('\u00bb', totalPages, currentPage === totalPages, false);
    }
    function render() {
        var total = rows.length;
        var totalPages = Math.max(1, Math.ceil(total / pageSize));
        if (currentPage > totalPages) currentPage = totalPages;
        var start = (currentPage - 1) * pageSize;
        var end = Math.min(start + pageSize, total);
        rows.forEach(function(r, i) { r.style.display = (i >= start && i < end) ? '' : 'none'; });
        pageInfo.textContent = total === 0 ? 'Showing 0 entries' : ('Showing ' + (start + 1) + ' to ' + end + ' of ' + total + ' entries');
        renderPager(totalPages);
    }
    if (showEntries) {
        showEntries.addEventListener('change', function() {
            pageSize = this.value === 'All' ? Math.max(rows.length, 1) : parseInt(this.value, 10);
            currentPage = 1; render();
        });
    }
    render();
})();

// Advanced filter toggle
(function() {
    var advToggle = document.getElementById('siAdvToggle');
    var filterGrid = document.getElementById('siFilterGrid');
    if (advToggle && filterGrid) {
        advToggle.addEventListener('click', function() {
            filterGrid.classList.toggle('si-collapsed');
            advToggle.classList.toggle('open');
        });
    }
})();

// Select all
(function() {
    var selectAllCb = document.getElementById('siSelectAll');
    if (selectAllCb) {
        selectAllCb.addEventListener('change', function() {
            document.querySelectorAll('.si-row-cb').forEach(function(cb) {
                if (cb.closest('tr').style.display !== 'none') cb.checked = selectAllCb.checked;
            });
        });
    }
    document.addEventListener('change', function(e) {
        if (e.target.classList && e.target.classList.contains('si-row-cb')) {
            var all = document.querySelectorAll('.si-row-cb');
            if (selectAllCb) selectAllCb.checked = all.length > 0 && Array.prototype.every.call(all, function(cb) { return cb.checked; });
        }
    });
})();
</script>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
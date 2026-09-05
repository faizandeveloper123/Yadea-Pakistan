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
  test_date DATE DEFAULT NULL,
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'AddInquiry') {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        if ($name === '') {
            $error = 'Name is required.';
        } else {
            $st2 = db_prepare("INSERT INTO student_inquiries (name, father_name, phone, father_cellno, email, class_id, section_id, session, admission_source, locality, address, visit_date, test_date, remarks, status, created_by)
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'New', ?)");
            $uid = $_SESSION['user_id'] ?? null;
            $class_id = (int) ($_POST['class_id'] ?? 0) > 0 ? (int) $_POST['class_id'] : null;
            $section_id = (int) ($_POST['section_id'] ?? 0) > 0 ? (int) $_POST['section_id'] : null;
            $vis = trim($_POST['visit_date'] ?? '') !== '' ? trim($_POST['visit_date']) : null;
            $test = trim($_POST['test_date'] ?? '') !== '' ? trim($_POST['test_date']) : null;
            $st2->bind_param('ssssssssssssssi',
                $name, trim($_POST['father_name'] ?? ''), $phone, trim($_POST['father_cellno'] ?? ''),
                trim($_POST['email'] ?? ''), $class_id, $section_id, trim($_POST['session'] ?? ''),
                trim($_POST['admission_source'] ?? ''), trim($_POST['locality'] ?? ''), trim($_POST['address'] ?? ''),
                $vis, $test, trim($_POST['remarks'] ?? ''), $uid);
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

$filter_from = trim($_GET['from'] ?? '');
$filter_to = trim($_GET['to'] ?? '');
$filter_status = trim($_GET['status'] ?? '');
$filter_locality = trim($_GET['locality'] ?? '');
$search = trim($_GET['search'] ?? '');

$where = [];
$params = [];
$types = '';
if ($filter_from !== '') {
    $where[] = 'DATE(i.created_at) >= ?';
    $params[] = $filter_from; $types .= 's';
}
if ($filter_to !== '') {
    $where[] = 'DATE(i.created_at) <= ?';
    $params[] = $filter_to; $types .= 's';
}
if ($filter_status !== '') {
    $where[] = 'i.status = ?';
    $params[] = $filter_status; $types .= 's';
}
if ($filter_locality !== '') {
    $where[] = 'i.locality LIKE ?';
    $params[] = '%' . $filter_locality . '%'; $types .= 's';
}
if ($search !== '') {
    $where[] = '(i.name LIKE ? OR i.father_name LIKE ? OR i.phone LIKE ? OR i.email LIKE ?)';
    $like = '%' . $search . '%';
    for ($x = 0; $x < 4; $x++) { $params[] = $like; $types .= 's'; }
}

$sql = "SELECT i.*, c.class_name, sec.section_name FROM student_inquiries i
        LEFT JOIN classes c ON i.class_id = c.class_id
        LEFT JOIN sections sec ON i.section_id = sec.section_id";
if (count($where) > 0) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY i.created_at DESC';

$inquiries = [];
if (count($params) > 0) {
    $st = db_prepare($sql);
    $st->bind_param($types, ...$params);
    $st->execute();
    $r = $st->get_result();
} else {
    $r = db_query($sql);
}
while ($row = $r->fetch_assoc()) { $inquiries[] = $row; }

$statusColors = [
    'New'        => '#A05AFF',
    'In-Process' => '#337ab7',
    'Interested' => '#4BCBEB',
    'Admitted'   => '#1BCFB4',
    'Cancelled'  => '#D32D41',
];
$statuses = array_keys($statusColors);

$statusCounts = [];
$sr = db_query("SELECT status, COUNT(*) c FROM student_inquiries GROUP BY status");
while ($row = $sr->fetch_assoc()) { $statusCounts[$row['status']] = (int) $row['c']; }
$totalInquiries = count($inquiries);
$totalAll = (int) (db_query("SELECT COUNT(*) c FROM student_inquiries")->fetch_assoc()['c'] ?? 0);

include __DIR__ . '/includes/header.php';
?>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
body { font-family: 'Inter', Arial, sans-serif; background: radial-gradient(1200px 800px at 10% 10%, #eef2ff 0%, #f4f4f4 50%, #f8fafc 100%); }
.page-header-section { margin-bottom:14px; }
.page-header-section h2 { font-size:18px; font-weight:800; color:#111827; margin:0; }
.breadcrumb-modern { display:flex; align-items:center; gap:8px; font-size:12.5px; color:#6B7280; margin:6px 0 0; padding:0; list-style:none; }
.breadcrumb-modern a { color:#377DFF; text-decoration:none; }
.breadcrumb-modern i { font-size:11px; color:#9CA3AF; }
.inq-topbar { display:flex; align-items:center; justify-content:space-between; padding:14px 4px; flex-wrap:wrap; gap:10px; }
.inq-topbar h3 { font-size:18px; font-weight:800; color:#111827; margin:0; }
.stat-cards { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:12px; margin-bottom:16px; }
.stat-card { border-radius:14px; padding:16px; color:#fff; position:relative; overflow:hidden; transition:transform .15s; }
.stat-card:hover { transform:translateY(-2px); }
.stat-card .sc-num { font-size:24px; font-weight:800; line-height:1; }
.stat-card .sc-lbl { font-size:12px; opacity:.9; margin-top:6px; }
.stat-card .sc-ico { position:absolute; right:12px; top:12px; font-size:22px; opacity:.35; }
.filter-panel { background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:8px 16px 16px; margin-bottom:16px; box-shadow:0 4px 16px rgba(15,23,42,.06); }
.filter-panel h4 { font-size:14px; font-weight:800; color:#111827; margin:0 0 4px; padding-top:10px; }
.filter-panel label { font-size:12px; font-weight:600; color:#374151; }
.filter-panel .form-control { border:1px solid #E5E7EB; border-radius:10px; font-size:13px; padding:8px 12px; }
.filter-panel .form-control:focus { border-color:#ff8c00; box-shadow:0 0 0 4px rgba(255,140,0,.12); }
.inq-status { padding:4px 12px; border-radius:999px; color:#fff; font-size:11px; font-weight:700; white-space:nowrap; }
.notes-box { background:#F7F8FA; border:1px solid #E5E7EB; border-radius:10px; padding:12px; max-height:220px; overflow:auto; }
.note-item { border-left:3px solid #ff8c00; background:#fff; padding:8px 10px; margin-bottom:8px; border-radius:6px; font-size:13px; }
.note-item small { color:#9CA3AF; }
.inq-actions .btn { margin:1px 0; }
.btn-primary { background:#ff8c00 !important; border-color:#ff8c00 !important; }
.btn-primary:hover { background:#e07c00 !important; border-color:#e07c00 !important; }
.table-card { background:#fff; border:1px solid #E5E7EB; border-radius:14px; overflow:hidden; box-shadow:0 4px 16px rgba(15,23,42,.06); }
.table-card table { margin-bottom:0; }
.table-card thead th { background:#F8FAFC; color:#374151; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; border-bottom:2px solid #E5E7EB; }
.table-card tbody td { font-size:12px; vertical-align:middle; }
.dataTables_wrapper { padding:0 12px 12px; }
@media print { .no-print { display:none !important; } }
</style>

<div class="main-content">
    <div class="container-fluid">
        <div class="page-header-section">
            <h2><i class="fa fa-question-circle" style="color:#ff8c00;"></i> Student Inquiry <span class="badge" style="background:#ff8c00; color:#fff; font-size:11px; vertical-align:middle; margin-left:8px;"><?php echo $totalAll; ?> Records</span></h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb-modern">
                    <li><a href="<?php echo BASE_URL; ?>dashboard.php"><i class="fa fa-home"></i> Dashboard</a></li>
                    <li><i class="fa fa-angle-right"></i></li>
                    <li><a href="#">Front Office</a></li>
                    <li><i class="fa fa-angle-right"></i></li>
                    <li><span>Student Inquiries</span></li>
                </ol>
            </nav>
        </div>

        <?php if ($message): ?><div class="alert alert-success" style="border-radius:10px; font-size:13px;"><?php echo e($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger" style="border-radius:10px; font-size:13px;"><?php echo e($error); ?></div><?php endif; ?>

        <div class="inq-topbar">
            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                <button type="button" class="btn no-print" style="background:#ff8c00; color:#fff; border-radius:10px; font-weight:600;" data-toggle="modal" data-target="#addInquiryModal"><i class="fa fa-plus"></i> Add Inquiry</button>
                <a href="<?php echo BASE_URL; ?>manage_complaint.php" class="btn btn-default no-print" style="border-radius:10px; font-weight:600;"><i class="fa fa-comments-o"></i> Complaints</a>
            </div>
        </div>

        <div class="stat-cards">
            <div class="stat-card" style="background:linear-gradient(135deg,#111827,#374151);">
                <i class="fa fa-inbox sc-ico"></i>
                <div class="sc-num"><?php echo $totalAll; ?></div>
                <div class="sc-lbl">Total Inquiries</div>
            </div>
            <?php foreach ($statuses as $st): ?>
                <a href="student_inquiry.php?status=<?php echo urlencode($st); ?>" class="stat-card" style="background:<?php echo $statusColors[$st]; ?>; text-decoration:none;">
                    <i class="fa fa-tag sc-ico"></i>
                    <div class="sc-num"><?php echo $statusCounts[$st] ?? 0; ?></div>
                    <div class="sc-lbl"><?php echo e($st); ?></div>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="filter-panel">
            <h4><i class="fa fa-filter"></i> Filter Inquiries</h4>
            <form method="get" action="student_inquiry.php">
                <div class="row">
                    <div class="col-md-3 col-xs-12" style="padding:8px;">
                        <div class="form-group">
                            <label>From Date</label>
                            <input type="date" name="from" class="form-control" value="<?php echo e($filter_from); ?>">
                        </div>
                    </div>
                    <div class="col-md-3 col-xs-12" style="padding:8px;">
                        <div class="form-group">
                            <label>To Date</label>
                            <input type="date" name="to" class="form-control" value="<?php echo e($filter_to); ?>">
                        </div>
                    </div>
                    <div class="col-md-2 col-xs-12" style="padding:8px;">
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status" class="form-control">
                                <option value="">All Status</option>
                                <?php foreach ($statuses as $st): ?>
                                    <option value="<?php echo e($st); ?>" <?php echo $filter_status === $st ? 'selected' : ''; ?>><?php echo e($st); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4 col-xs-12" style="padding:8px;">
                        <div class="form-group">
                            <label>Locality</label>
                            <input type="text" name="locality" class="form-control" value="<?php echo e($filter_locality); ?>">
                        </div>
                    </div>
                    <div class="col-md-4 col-xs-12" style="padding:8px;">
                        <div class="form-group">
                            <label>Search</label>
                            <input type="text" name="search" class="form-control" value="<?php echo e($search); ?>" placeholder="Name / Father / Phone / Email">
                        </div>
                    </div>
                    <div class="col-md-1 col-xs-12" style="padding:8px;">
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-primary" style="width:100%; border-radius:10px;"><i class="fa fa-search"></i></button>
                        </div>
                    </div>
                    <div class="col-md-1 col-xs-12" style="padding:8px;">
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <a href="student_inquiry.php" class="btn btn-default" style="width:100%; border-radius:10px;"><i class="fa fa-refresh"></i></a>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <div class="table-card">
            <table class="table table-striped table-bordered" style="width:100%; margin-bottom:0;">
                <thead>
                    <tr>
                        <th width="3%">S.No</th>
                        <th width="15%">Name</th>
                        <th width="12%">Father Name</th>
                        <th width="10%">Class / Section</th>
                        <th width="10%">Cell No</th>
                        <th width="8%">Date</th>
                        <th width="8%">Status</th>
                        <th width="10%">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($totalInquiries === 0): ?>
                        <tr><td colspan="8" style="text-align:center; color:#6B7280; padding:30px;">No inquiries found.</td></tr>
                    <?php endif; ?>
                    <?php $sn = 1; foreach ($inquiries as $i): ?>
                        <tr>
                            <td style="text-align:center;"><?php echo $sn++; ?></td>
                            <td>
                                <strong><?php echo e($i['name']); ?></strong>
                                <?php if (!empty($i['phone'])) echo '<br><small style="color:#6B7280;">' . e($i['phone']) . '</small>'; ?>
                            </td>
                            <td><?php echo e($i['father_name'] ?? '-'); ?></td>
                            <td><?php echo e($i['class_name'] ?? '-'); ?> / <?php echo e($i['section_name'] ?? '-'); ?></td>
                            <td><?php echo e($i['phone'] ?? '-'); ?></td>
                            <td><?php echo date('d M Y', strtotime($i['created_at'])); ?></td>
                            <td>
                                <span class="inq-status" style="background:<?php echo $statusColors[$i['status']] ?? '#6B7280'; ?>;"><?php echo e($i['status']); ?></span>
                            </td>
                            <td class="inq-actions">
                                <div class="btn-group">
                                    <button type="button" class="btn btn-default btn-xs dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                        Action <span class="caret"></span>
                                    </button>
                                    <ul class="dropdown-menu" style="right:0; left:auto;">
                                        <li><a href="#" onclick="viewInquiry(<?php echo $i['inquiry_id']; ?>); return false;"><i class="fa fa-eye"></i> View / Add Notes</a></li>
                                        <li><a href="#" onclick="changeStatus(<?php echo $i['inquiry_id']; ?>); return false;"><i class="fa fa-pencil"></i> Change Status</a></li>
                                        <?php if ($i['status'] !== 'Admitted'): ?>
                                            <li><a href="#" onclick="enrollInquiry(<?php echo $i['inquiry_id']; ?>); return false;"><i class="fa fa-user-plus"></i> Enroll</a></li>
                                        <?php endif; ?>
                                        <li><a href="<?php echo BASE_URL; ?>new_message.php" target="_blank"><i class="fa fa-envelope"></i> Send Message</a></li>
                                        <li role="separator" class="divider"></li>
                                        <li>
                                            <form method="post" action="student_inquiry.php" style="display:inline;" onsubmit="return confirm('Delete this inquiry?');">
                                                <input type="hidden" name="action" value="DeleteInquiry">
                                                <input type="hidden" name="inquiry_id" value="<?php echo $i['inquiry_id']; ?>">
                                                <button type="submit" style="border:0; background:none; color:#D32D41; padding:3px 20px;"><i class="fa fa-trash"></i> Delete</button>
                                            </form>
                                        </li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Inquiry Modal -->
<div class="modal fade" id="addInquiryModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content" style="border-radius:14px;">
            <form method="post" action="student_inquiry.php">
                <input type="hidden" name="action" value="AddInquiry">
                <div class="modal-header" style="border-bottom:1px solid #E5E7EB;">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title" style="font-weight:800;">Add New Inquiry</h4>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="form-group col-md-6">
                            <label class="required">Student Name</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Father Name</label>
                            <input type="text" name="father_name" class="form-control">
                        </div>
                    </div>
                    <div class="row">
                        <div class="form-group col-md-4">
                            <label>Contact No</label>
                            <input type="text" name="phone" class="form-control">
                        </div>
                        <div class="form-group col-md-4">
                            <label>Father Cell No</label>
                            <input type="text" name="father_cellno" class="form-control">
                        </div>
                        <div class="form-group col-md-4">
                            <label>Email</label>
                            <input type="email" name="email" class="form-control">
                        </div>
                    </div>
                    <div class="row">
                        <div class="form-group col-md-4">
                            <label>Interested Class</label>
                            <select name="class_id" class="form-control" id="ai_class" onchange="loadSections(this.value, 'ai_section');">
                                <option value="0">Any</option>
                                <?php foreach ($classes as $c): ?>
                                    <option value="<?php echo $c['class_id']; ?>"><?php echo e($c['class_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group col-md-4">
                            <label>Section</label>
                            <select name="section_id" id="ai_section" class="form-control">
                                <option value="0">Any</option>
                            </select>
                        </div>
                        <div class="form-group col-md-4">
                            <label>Session</label>
                            <input type="text" name="session" class="form-control" value="<?php echo e(get_setting('session_year', '2026-2027')); ?>">
                        </div>
                    </div>
                    <div class="row">
                        <div class="form-group col-md-6">
                            <label>Locality</label>
                            <input type="text" name="locality" class="form-control">
                        </div>
                        <div class="form-group col-md-6">
                            <label>Admission Source</label>
                            <input type="text" name="admission_source" class="form-control" placeholder="e.g. Walk-in, Friend, Advertisement">
                        </div>
                    </div>
                    <div class="row">
                        <div class="form-group col-md-4">
                            <label>Visit Date</label>
                            <input type="date" name="visit_date" class="form-control">
                        </div>
                        <div class="form-group col-md-4">
                            <label>Test Date</label>
                            <input type="date" name="test_date" class="form-control">
                        </div>
                    </div>
                    <div class="row">
                        <div class="form-group col-md-6">
                            <label>Address</label>
                            <textarea name="address" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Remarks</label>
                            <textarea name="remarks" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="border-top:1px solid #E5E7EB;">
                    <button type="button" class="btn btn-default" data-dismiss="modal" style="border-radius:10px;">Close</button>
                    <button type="submit" class="btn btn-primary" style="border-radius:10px;">Save Inquiry</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View / Notes Modal -->
<div class="modal fade" id="viewModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content" style="border-radius:14px;">
            <form method="post" action="student_inquiry.php" id="viewForm">
                <input type="hidden" name="action" value="AddNote">
                <input type="hidden" name="inquiry_id" id="vq_id">
                <div class="modal-header" style="border-bottom:1px solid #E5E7EB;">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title" style="font-weight:800;">Inquiry Details</h4>
                </div>
                <div class="modal-body">
                    <div class="row" id="vq_details" style="font-size:13.5px;"></div>
                    <div style="font-weight:800; margin:14px 0 8px;">Notes / Follow-up</div>
                    <div class="notes-box" id="vq_notes"></div>
                    <div class="form-group" style="margin-top:10px;">
                        <textarea name="note" class="form-control" rows="2" placeholder="Add a new note..."></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="border-top:1px solid #E5E7EB;">
                    <button type="button" class="btn btn-default" data-dismiss="modal" style="border-radius:10px;">Close</button>
                    <button type="submit" class="btn btn-primary" style="border-radius:10px;"><i class="fa fa-plus"></i> Add Note</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Change Status Modal -->
<div class="modal fade" id="statusModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius:14px;">
            <form method="post" action="student_inquiry.php">
                <input type="hidden" name="action" value="ChangeStatus">
                <input type="hidden" name="inquiry_id" id="s_id">
                <div class="modal-header" style="border-bottom:1px solid #E5E7EB;">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title" style="font-weight:800;">Change Inquiry Status</h4>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" id="s_status" class="form-control">
                            <?php foreach ($statuses as $st): ?>
                                <option value="<?php echo e($st); ?>"><?php echo e($st); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer" style="border-top:1px solid #E5E7EB;">
                    <button type="button" class="btn btn-default" data-dismiss="modal" style="border-radius:10px;">Close</button>
                    <button type="submit" class="btn btn-primary" style="border-radius:10px;">Update Status</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Enroll Modal -->
<div class="modal fade" id="enrollModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content" style="border-radius:14px;">
            <form method="post" action="student_inquiry.php">
                <input type="hidden" name="action" value="EnrollInquiry">
                <input type="hidden" name="inquiry_id" id="en_id">
                <div class="modal-header" style="border-bottom:1px solid #E5E7EB;">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title" style="font-weight:800;">Enroll as Student</h4>
                </div>
                <div class="modal-body">
                    <p style="color:#6B7280; font-size:13px;">This will create a student record and mark the inquiry as <strong>Admitted</strong>.</p>
                    <div class="row">
                        <div class="form-group col-md-6">
                            <label class="required">Student Name</label>
                            <input type="text" name="name" id="en_name" class="form-control" required>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Father Name</label>
                            <input type="text" name="father_name" id="en_father" class="form-control">
                        </div>
                    </div>
                    <div class="row">
                        <div class="form-group col-md-4">
                            <label>Phone</label>
                            <input type="text" name="phone" id="en_phone" class="form-control">
                        </div>
                        <div class="form-group col-md-4">
                            <label>Class</label>
                            <select name="class_id" class="form-control">
                                <option value="0">-- Select --</option>
                                <?php foreach ($classes as $c): ?>
                                    <option value="<?php echo $c['class_id']; ?>"><?php echo e($c['class_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group col-md-2">
                            <label>Section</label>
                            <select name="section_id" class="form-control">
                                <option value="0">--</option>
                            </select>
                        </div>
                        <div class="form-group col-md-2">
                            <label>Session</label>
                            <input type="text" name="session" class="form-control" value="<?php echo e(get_setting('session_year', '2026-2027')); ?>">
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="border-top:1px solid #E5E7EB;">
                    <button type="button" class="btn btn-default" data-dismiss="modal" style="border-radius:10px;">Close</button>
                    <button type="submit" class="btn btn-primary" style="border-radius:10px;"><i class="fa fa-user-plus"></i> Confirm Enrollment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
var inquiryData = <?php echo json_encode($inquiries, JSON_UNESCAPED_UNICODE); ?>;

function loadSections(classId, targetId) {
    var sel = document.getElementById(targetId);
    sel.innerHTML = '<option value="0">Any</option>';
    if (!classId || classId == '0' || classId == '') return;
    fetch('ajax_get_sections.php?class_id=' + classId)
        .then(function(r){ return r.json(); })
        .then(function(data){
            data.forEach(function(s){
                var o = document.createElement('option');
                o.value = s.section_id;
                o.textContent = s.section_name;
                sel.appendChild(o);
            });
        });
}

function findInquiry(id) {
    return inquiryData.find(function(x){ return x.inquiry_id == id; });
}

function viewInquiry(id) {
    var i = findInquiry(id);
    if (!i) return;
    document.getElementById('vq_id').value = id;
    var fields = [
        ['Name', i.name],
        ['Father Name', i.father_name],
        ['Contact', i.phone],
        ['Father Cell', i.father_cellno],
        ['Email', i.email],
        ['Class / Section', (i.class_name || '-') + ' / ' + (i.section_name || '-')],
        ['Session', i.session],
        ['Locality', i.locality],
        ['Source', i.admission_source],
        ['Address', i.address],
        ['Visit Date', i.visit_date],
        ['Test Date', i.test_date],
        ['Remarks', i.remarks],
        ['Status', i.status]
    ];
    var blocks = '';
    fields.forEach(function(f){
        if (f[1] && String(f[1]).trim() !== '') {
            blocks += '<div class="col-md-6" style="margin-bottom:8px;"><div style="font-size:11px; color:#9CA3AF; text-transform:uppercase; letter-spacing:.5px;">' + f[0] + '</div><div style="font-weight:600; color:#111827;">' + f[1] + '</div></div>';
        }
    });
    document.getElementById('vq_details').innerHTML = blocks || '<div style="color:#6B7280; padding:6px;">No details.</div>';
    document.getElementById('vq_notes').innerHTML = 'Loading...';
    loadNotes(id);
    $('#viewModal').modal('show');
}

function loadNotes(id) {
    var notes = document.getElementById('vq_notes');
    notes.innerHTML = 'Loading...';
    fetch('ajax_get_inquiry_notes.php?inquiry_id=' + id)
        .then(function(r){ return r.json(); })
        .then(function(data){
            notes.innerHTML = '';
            if (data.length === 0) { notes.innerHTML = '<div style="color:#9CA3AF;">No notes yet.</div>'; return; }
            data.forEach(function(n){
                var d = document.createElement('div');
                d.className = 'note-item';
                d.innerHTML = '<div>' + n.note + '</div><small>' + n.created_at + '</small>';
                notes.appendChild(d);
            });
            notes.scrollTop = notes.scrollHeight;
        });
}

function changeStatus(id) {
    var i = findInquiry(id);
    if (!i) return;
    document.getElementById('s_id').value = id;
    document.getElementById('s_status').value = i.status || 'New';
    $('#statusModal').modal('show');
}

function enrollInquiry(id) {
    var i = findInquiry(id);
    if (!i) return;
    document.getElementById('en_id').value = id;
    document.getElementById('en_name').value = i.name || '';
    document.getElementById('en_father').value = i.father_name || '';
    document.getElementById('en_phone').value = i.phone || '';
    $('#enrollModal').modal('show');
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
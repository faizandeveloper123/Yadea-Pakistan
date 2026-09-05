<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Add Multi Students';

$message = '';
$error = '';

$classes = [];
$res = db_query("Select class_id, class_name FROM classes WHERE status=1 ORDER BY class_name");
while ($row = $res->fetch_assoc()) { $classes[] = $row; }

$sessions = [];
for ($y = 2018; $y <= 2030; $y++) { $sessions[] = $y . '-' . substr($y + 1, -2); }
$cur_session = get_setting('session_year', '2026-2027');
if (!in_array($cur_session, $sessions, true)) { array_unshift($sessions, $cur_session); }

function bulk_parse_date($d) {
    $d = trim($d);
    if ($d === '') return null;
    $ts = strtotime(str_replace('/', '-', $d));
    return $ts ? date('Y-m-d', $ts) : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'BulkAdd') {
    $class_id   = (int) ($_POST['class'] ?? 0);
    $section_id = (int) ($_POST['section'] ?? 0) ?: null;
    $session    = trim($_POST['session'] ?? '');
    $gender     = strtolower($_POST['gender'] ?? '') ?: 'male';
    $religion   = $_POST['religion'] ?? 'Islam';
    $father_name = trim($_POST['lname'] ?? '');
    $mother_name = trim($_POST['mother_name'] ?? '');
    $father_cell = trim($_POST['father_cellno'] ?? '');
    $guardian_cell = trim($_POST['Gcellno'] ?? '');
    $address    = trim($_POST['address'] ?? '');
    $guardian_name = trim($_POST['gname'] ?? '');
    $guardian_cnic = trim($_POST['Gcnic'] ?? '');
    $father_cnic = trim($_POST['cnic'] ?? '');
    $adm_db     = bulk_parse_date($_POST['date_of_adms'] ?? '') ?? date('Y-m-d');

    $names = isset($_POST['names']) ? array_map('trim', (array)$_POST['names']) : [];
    $cells = isset($_POST['cells']) ? array_map('trim', (array)$_POST['cells']) : [];
    $dobs  = isset($_POST['dobs']) ? (array)$_POST['dobs'] : [];

    if ($class_id === 0) {
        $error = 'Please select a class.';
    } else {
        $added = 0;
        // Derive family code from guardian cell / father cell
        $family_code = null;
        $key = $guardian_cell !== '' ? $guardian_cell : ($father_cell !== '' ? $father_cell : null);
        if ($key) {
            $ex = db_prepare("SELECT family_code FROM students WHERE family_code IS NOT NULL AND family_code <> '' AND (guardian_cellno = ? OR father_cellno = ?) LIMIT 1");
            $ex->bind_param('ss', $key, $key);
            $ex->execute();
            $fr = $ex->get_result()->fetch_assoc();
            $family_code = $fr ? $fr['family_code'] : ('F-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT));
        }

        foreach ($names as $i => $name) {
            if ($name === '') continue;
            $cell = $cells[$i] ?? '';
            $dob  = bulk_parse_date($dobs[$i] ?? '');

            $cols = ['first_name','last_name','father_name','mother_name','phone','dob','gender','religion','session',
                     'father_cnic','father_cellno','guardian_name','guardian_cnic','guardian_cellno','address',
                     'class_id','section_id','admission_date','status'];
            $vals = [$name, $father_name, $father_name, $mother_name, $cell, $dob, $gender, $religion, $session !== '' ? $session : null,
                     $father_cnic !== '' ? $father_cnic : null, $father_cell !== '' ? $father_cell : null,
                     $guardian_name !== '' ? $guardian_name : null, $guardian_cnic !== '' ? $guardian_cnic : null,
                     $guardian_cell !== '' ? $guardian_cell : null, $address !== '' ? $address : null,
                     $class_id, $section_id, $adm_db, 1];

            $placeholders = implode(',', array_fill(0, count($cols), '?'));
            $sql = 'INSERT INTO students (`' . implode('`,`', $cols) . '`) VALUES (' . $placeholders . ')';
            try {
                $stmt = db_prepare($sql);
                $types = str_repeat('s', count($vals));
                $bindVals = [$types];
                foreach ($vals as $k => $v) { $bindVals[] = &$vals[$k]; }
                call_user_func_array([$stmt, 'bind_param'], $bindVals);
                $stmt->execute();
                $sid = $stmt->insert_id;
                if ($sid > 0) {
                    $gr = substr(date('Y'), 2) . '-' . str_pad($sid, 3, '0', STR_PAD_LEFT);
                    $u = db_prepare('UPDATE students SET gr_no = ? WHERE student_id = ?');
                    $u->bind_param('si', $gr, $sid);
                    $u->execute();
                    if ($family_code) {
                        $u2 = db_prepare('UPDATE students SET family_code = ? WHERE student_id = ?');
                        $u2->bind_param('si', $family_code, $sid);
                        $u2->execute();
                    }
                    $added++;
                }
            } catch (Exception $ex2) {
                $error = 'Error while adding: ' . $ex2->getMessage();
            }
        }
        if ($added > 0) { $message = $added . ' student(s) added successfully!'; }
        elseif ($error === '') { $error = 'No valid student names were provided.'; }
    }
}

include __DIR__ . '/includes/header.php';
?>
<style>
.bulk-row { display:flex; gap:10px; flex-wrap:wrap; align-items:center; background:#fff; border:1px solid #E5E7EB; border-radius:10px; padding:10px 12px; margin-bottom:10px; }
.bulk-row .form-group { margin-bottom:0; flex:1 1 160px; }
.bulk-row .name-in { flex:2 1 220px; }
.bulk-row .cell-in { flex:1 1 160px; }
.bulk-row .dob-in { flex:1 1 140px; }
.top-tabs-row { border-bottom:1px solid #eaecef; padding:10px 4px 0; }
.page-card { background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:16px 20px; }
</style>
<div class="main-content">
    <div class="container-fluid">
        <div class="container mt-4 page-card" style="width:100%;">
            <div class="top-tabs-row">
                <ul class="nav nav-tabs" id="studentTabs">
                    <li><a href="add_student.php"><i class="fa fa-user-plus"></i> Add New Student</a></li>
                    <li class="active"><a href="bulk_stdns.php"><i class="fa fa-users"></i>&nbsp; Add Multi Students</a></li>
                    <li><a href="import_data.php"><i class="fa fa-upload"></i> &nbsp; Import Students with CSV</a></li>
                    <li><a href="adm_form.php" target="_blank"><i class="fa fa-file-text"></i> &nbsp; Admission Form</a></li>
                </ul>
            </div>

            <?php if ($message): ?><div class="alert alert-success" style="margin-top:12px;"><?php echo e($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger" style="margin-top:12px;"><?php echo e($error); ?></div><?php endif; ?>

            <form id="bulkForm" method="post" action="bulk_stdns.php" style="margin-top:16px;">
                <input type="hidden" name="action" value="BulkAdd">
                <div style="background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:20px; margin-bottom:16px;">
                    <h4 style="font-size:15px; font-weight:700; color:#111827; margin:0 0 14px;"><i class="fa fa-cog"></i> Common Details</h4>
                    <div class="row">
                        <div class="form-group col-md-3">
                            <label>Course/Class <span style="color:red;">*</span></label>
                            <select name="class_id" id="class_id" class="form-control" required onchange="loadSections(this.value)">
                                <option value="">Select Course/Class</option>
                                <?php foreach ($classes as $c): ?><option value="<?php echo $c['class_id']; ?>"><?php echo e($c['class_name']); ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group col-md-3">
                            <label>Section</label>
                            <select name="section" id="txt_section" class="form-control"><option value="">Select Section</option></select>
                        </div>
                        <div class="form-group col-md-3">
                            <label>Session</label>
                            <select name="session" class="form-control">
                                <option value="">Select Session</option>
                                <?php foreach ($sessions as $s): ?><option value="<?php echo e($s); ?>" <?php echo $s === $cur_session ? 'selected' : ''; ?>><?php echo e($s); ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group col-md-3">
                            <label>Gender</label>
                            <select name="gender" class="form-control"><option value="male">Male</option><option value="female">Female</option></select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="form-group col-md-3">
                            <label>Date Of Admission</label>
                            <input type="text" name="date_of_adms" class="form-control" value="<?php echo date('d/m/Y'); ?>">
                        </div>
                        <div class="form-group col-md-3">
                            <label>Father Name</label>
                            <input type="text" name="lname" class="form-control" placeholder="Father Name">
                        </div>
                        <div class="form-group col-md-3">
                            <label>Mother Name</label>
                            <input type="text" name="mother_name" class="form-control" placeholder="Mother Name">
                        </div>
                        <div class="form-group col-md-3">
                            <label>Father Cell No</label>
                            <input type="text" name="father_cellno" class="form-control" placeholder="Father Cell No">
                        </div>
                    </div>
                    <div class="row">
                        <div class="form-group col-md-3">
                            <label>Guardian Name</label>
                            <input type="text" name="gname" class="form-control" placeholder="Guardian Name">
                        </div>
                        <div class="form-group col-md-3">
                            <label>Guardian Cell No</label>
                            <input type="text" name="Gcellno" class="form-control" placeholder="Guardian Cell No">
                        </div>
                        <div class="form-group col-md-3">
                            <label>Guardian CNIC</label>
                            <input type="text" name="Gcnic" class="form-control" placeholder="Guardian CNIC">
                        </div>
                        <div class="form-group col-md-3">
                            <label>Father CNIC</label>
                            <input type="text" name="cnic" class="form-control" placeholder="Father CNIC">
                        </div>
                    </div>
                    <div class="row">
                        <div class="form-group col-md-12">
                            <label>Home Address</label>
                            <input type="text" name="address" class="form-control" placeholder="Family Home Address">
                        </div>
                    </div>
                </div>

                <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:12px;">
                    <h4 style="font-size:15px; font-weight:700; color:#111827; margin:0;"><i class="fa fa-users"></i> Student Names / Details</h4>
                    <button type="button" class="btn btn-success" id="addRow" style="color:#fff;"><i class="fa fa-plus"></i> Add More</button>
                </div>
                <div id="rowsContainer"></div>

                <div style="text-align:right; margin-top:14px;">
                    <button type="submit" class="btn btn-primary" style="color:#fff; padding:10px 30px;"><i class="fa fa-check"></i> Save All Students</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
var rowIndex = 0;
function addRow() {
    var container = document.getElementById('rowsContainer');
    var div = document.createElement('div');
    div.className = 'bulk-row';
    div.id = 'row_' + rowIndex;
    div.innerHTML =
        '<div class="form-group name-in">' +
            '<label>Student Name <span style="color:red;">*</span></label>' +
            '<input type="text" name="names[]" class="form-control" placeholder="Student Name" required>' +
        '</div>' +
        '<div class="form-group cell-in">' +
            '<label>Cell Number</label>' +
            '<input type="text" name="cells[]" class="form-control" placeholder="Cell Number">' +
        '</div>' +
        '<div class="form-group dob-in">' +
            '<label>Date Of Birth</label>' +
            '<input type="text" name="dobs[]" class="form-control" placeholder="dd/mm/yyyy">' +
        '</div>' +
        '<div><button type="button" class="btn btn-danger btn-sm" onclick="removeRow(' + rowIndex + ')"><i class="fa fa-trash"></i></button></div>';
    container.appendChild(div);
    rowIndex++;
}
function removeRow(idx) {
    var el = document.getElementById('row_' + idx);
    if (el) el.remove();
}
function loadSections(cid) {
    var sel = document.getElementById('txt_section');
    sel.innerHTML = '<option value="">Loading...</option>';
    eduGet(HIIFI_BASE + 'ajax_get_sections.php?class_id=' + encodeURIComponent(cid), function (data) {
        sel.innerHTML = '<option value="">Select Section</option>';
        (data || []).forEach(function (s) {
            var o = document.createElement('option');
            o.value = s.section_id; o.textContent = s.section_name;
            sel.appendChild(o);
        });
    });
}
// Preload one row
addRow();
addRow();
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>

<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Import Students with CSV';

$message = '';
$error = '';

$classes = [];
$res = db_query("Select Course/Class_id, class_name FROM classes WHERE status=1 ORDER BY class_name");
while ($row = $res->fetch_assoc()) { $classes[] = $row; }

function imp_parse_date($d) {
    $d = trim($d);
    if ($d === '') return null;
    $ts = strtotime(str_replace('/', '-', $d));
    return $ts ? date('Y-m-d', $ts) : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'ImportCSV') {
    $class_id = (int) ($_POST['class'] ?? 0);
    $section_id = (int) ($_POST['section'] ?? 0) ?: null;
    $session = trim($_POST['session'] ?? '');

    if ($class_id === 0) { $error = 'Please select a class.'; }
    elseif (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please upload a CSV file.';
    } else {
        $fh = fopen($_FILES['csv_file']['tmp_name'], 'r');
        $added = 0; $skipped = 0; $line = 0;
        while (($row = fgetcsv($fh)) !== false) {
            $line++;
            if ($line === 1) continue; // header
            $row = array_map('trim', $row);
            $name  = $row[0] ?? '';
            $fname = $row[1] ?? '';
            $mname = $row[2] ?? '';
            $cell  = $row[3] ?? '';
            $dob   = imp_parse_date($row[4] ?? '');
            $gname = $row[5] ?? '';
            $gcell = $row[6] ?? '';
            $gnic  = $row[7] ?? '';
            $addr  = $row[8] ?? '';
            if ($name === '') { $skipped++; continue; }

            $cols = ['first_name','last_name','father_name','mother_name','phone','dob','gender','religion','session',
                     'guardian_name','guardian_cnic','guardian_cellno','address','class_id','section_id','admission_date','status'];
            $vals = [$name, $fname, $fname, $mname, $cell, $dob, 'male', 'Islam',
                     $session !== '' ? $session : null, $gname !== '' ? $gname : null,
                     $gnic !== '' ? $gnic : null, $gcell !== '' ? $gcell : null, $addr !== '' ? $addr : null,
                     $class_id, $section_id, date('Y-m-d'), 1];
            $sql = 'INSERT INTO students (`' . implode('`,`', $cols) . '`) VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')';
            try {
                $stmt = db_prepare($sql);
                $bindVals = [str_repeat('s', count($vals))];
                foreach ($vals as $k => $v) { $bindVals[] = &$vals[$k]; }
                call_user_func_array([$stmt, 'bind_param'], $bindVals);
                $stmt->execute();
                $sid = $stmt->insert_id;
                if ($sid > 0) {
                    $gr = substr(date('Y'), 2) . '-' . str_pad($sid, 3, '0', STR_PAD_LEFT);
                    $u = db_prepare('UPDATE students SET gr_no = ? WHERE student_id = ?');
                    $u->bind_param('si', $gr, $sid);
                    $u->execute();
                    $added++;
                } else { $skipped++; }
            } catch (Exception $e2) {
                $skipped++;
            }
        }
        fclose($fh);
        $message = "Import complete: $added added, $skipped skipped/rejected.";
    }
}

if (isset($_GET['template'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="students_import_template.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Student Name','Father Name','Mother Name','Cell Number','Date Of Birth(dd/mm/yyyy)','Guardian Name','Guardian Cell','Guardian CNIC','Address']);
    fputcsv($out, ['Ali Hassan','Muhammad Hassan','Fatima','03001234567','15/08/2014','Muhammad Hassan','03001234567','35202-1234567-1','House 12, Street 3, Lahore']);
    fclose($out);
    exit;
}

include __DIR__ . '/includes/header.php';
?>
<style>
.top-tabs-row { border-bottom:1px solid #eaecef; padding:10px 4px 0; }
.page-card { background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:20px; }
.import-card { background:#fff; border:1.5px dashed #94A3B8; border-radius:14px; padding:40px 24px; text-align:center; }
</style>
<div class="main-content">
    <div class="container-fluid">
        <div class="container mt-4 page-card" style="width:100%;">
            <div class="top-tabs-row">
                <ul class="nav nav-tabs" id="studentTabs">
                    <li><a href="add_student.php"><i class="fa fa-user-plus"></i> Add New Student</a></li>
                    <li><a href="bulk_stdns.php"><i class="fa fa-users"></i>&nbsp; Add Multi Students</a></li>
                    <li class="active"><a href="import_data.php"><i class="fa fa-upload"></i> &nbsp; Import Students with CSV</a></li>
                    <li><a href="adm_form.php" target="_blank"><i class="fa fa-file-text"></i> &nbsp; Admission Form</a></li>
                </ul>
            </div>

            <?php if ($message): ?><div class="alert alert-success" style="margin-top:12px;"><?php echo e($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger" style="margin-top:12px;"><?php echo e($error); ?></div><?php endif; ?>

            <form method="post" action="import_data.php" enctype="multipart/form-data" style="margin-top:16px;">
                <input type="hidden" name="action" value="ImportCSV">
                <div class="row">
                    <div class="form-group col-md-4">
                        <label>Course/Class <span style="color:red;">*</span></label>
                        <select name="Course/Class" class="form-control" required>
                            <option value="">Select Course/Class</option>
                            <?php foreach ($classes as $c): ?><option value="<?php echo $c['class_id']; ?>"><?php echo e($c['class_name']); ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group col-md-4">
                        <label>Section</label>
                        <select name="section" class="form-control"><option value="">Select Section</option></select>
                    </div>
                    <div class="form-group col-md-4">
                        <label>Session</label>
                        <select name="session" class="form-control">
                            <option value="">Select Session</option>
                            <?php for ($y = 2020; $y <= 2030; $y++): $s = $y . '-' . substr($y + 1, -2); ?>
                            <option value="<?php echo e($s); ?>" <?php echo $s === get_setting('session_year', '2026-2027') ? 'selected' : ''; ?>><?php echo e($s); ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>

                <div class="import-card">
                    <i class="fa fa-cloud-upload" style="font-size:48px; color:#3B82F6; margin-bottom:12px;"></i>
                    <h4 style="font-size:16px; font-weight:700; color:#111827;">Drop your CSV file here or browse</h4>
                    <p style="color:#6B7280; font-size:13px; margin:6px 0 18px;">CSV columns: Student Name, Father Name, Mother Name, Cell Number, Date Of Birth, Guardian Name, Guardian Cell, Guardian CNIC, Address</p>
                    <input type="file" name="csv_file" accept=".csv,text/csv" class="form-control" style="margin:0 auto; max-width:420px;" required>
                    <div style="margin-top:18px;">
                        <button type="submit" class="btn btn-primary" style="color:#fff;"><i class="fa fa-upload"></i> Import Students</button>
                        <a href="import_data.php?template=1" class="btn btn-default"><i class="fa fa-download"></i> Download CSV Template</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>

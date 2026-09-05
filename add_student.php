<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

// Ensure required tables exist
try { db_query("CREATE TABLE IF NOT EXISTS student_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT,
    doc_type VARCHAR(100),
    file_path VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB"); } catch (Throwable $ex) {}
try { db_query("ALTER TABLE student_documents ADD COLUMN IF NOT EXISTS file_path VARCHAR(255) DEFAULT NULL"); } catch (Throwable $ex) {}
try { db_query("ALTER TABLE student_documents ADD COLUMN IF NOT EXISTS doc_type VARCHAR(100) DEFAULT NULL"); } catch (Throwable $ex) {}
try { db_query("ALTER TABLE student_documents ADD COLUMN IF NOT EXISTS student_id INT DEFAULT NULL"); } catch (Throwable $ex) {}

try { db_query("CREATE TABLE IF NOT EXISTS boards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(191) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB"); } catch (Throwable $ex) {}

try { db_query("CREATE TABLE IF NOT EXISTS `groups` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(191) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB"); } catch (Throwable $ex) {}

try { db_query("CREATE TABLE IF NOT EXISTS admission_sources (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(191) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB"); } catch (Throwable $ex) {}

try { db_query("CREATE TABLE IF NOT EXISTS document_titles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(191) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB"); } catch (Throwable $ex) {}

try { db_query("ALTER TABLE students ADD COLUMN IF NOT EXISTS family_code VARCHAR(50) DEFAULT NULL"); } catch (Throwable $ex) {}

try { db_query("CREATE TABLE IF NOT EXISTS student_fee_plan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    head_id INT DEFAULT NULL,
    head_name VARCHAR(191) DEFAULT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    discount DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY (student_id)
) ENGINE=InnoDB"); } catch (Throwable $ex) {}

function seed_if_empty($table, $nameCol, $values) {
    $c = db_query("SELECT COUNT(*) c FROM `$table`")->fetch_assoc()['c'];
    if ((int)$c === 0) {
        foreach ($values as $v) {
            $st = db_prepare("INSERT INTO `$table` (`$nameCol`) VALUES (?)");
            $st->bind_param('s', $v);
            $st->execute();
        }
    }
}
seed_if_empty('boards', 'name', ['BISE GRW', 'BISE LHR', 'BISE QTA']);
seed_if_empty('groups', 'name', ['Morning Shift', 'Evening Shift', 'FSC', 'ICS', 'Pre Engineering']);
seed_if_empty('admission_sources', 'name', ['Walk-in', 'Referral', 'Online Ads']);
seed_if_empty('document_titles', 'name', ['Beform', 'Matric Result Card', 'Inter Result Card', 'Father CNIC', 'B-Form / CNIC / Photo', 'Previous School Certificate', 'Fee Challan / DMC']);

$page_title = 'Add New Student';
$message = '';
$error = '';

function lookup_rows($sql) {
    $out = [];
    $r = db_query($sql);
    if ($r) { while ($row = $r->fetch_assoc()) { $out[] = $row; } }
    return $out;
}
$classes    = lookup_rows("Select class_id, class_name FROM classes WHERE status=1 ORDER BY class_name");
$localities = lookup_rows("SELECT locality_id, locality_name FROM localities WHERE status=1 ORDER BY locality_name");
$boards     = lookup_rows("SELECT id, name FROM boards ORDER BY name");
$groups     = lookup_rows("SELECT id, name FROM `groups` ORDER BY name");
$admSrcs    = lookup_rows("SELECT id, name FROM admission_sources ORDER BY name");
$occupations= lookup_rows("SELECT id, name FROM occupations ORDER BY name");
$docTitles  = lookup_rows("SELECT id, name FROM document_titles ORDER BY name");
$families   = lookup_rows("SELECT DISTINCT family_code FROM students WHERE family_code IS NOT NULL AND family_code <> '' ORDER BY family_code");

$sessions = [];
for ($y = 2018; $y <= 2030; $y++) { $sessions[] = $y . '-' . substr($y + 1, -2); }
$cur_session = get_setting('session_year', '2026-2027');
if (!in_array($cur_session, $sessions, true)) { array_unshift($sessions, $cur_session); }

// Fee heads for Step 2
$feeHeads = lookup_rows("SELECT head_id, head_name, amount FROM fee_heads WHERE status=1 ORDER BY head_id");

// -------- POST: Save Student --------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'AddAdmission') {
    function parse_date($d) {
        $d = trim($d);
        if ($d === '') return null;
        $ts = strtotime(str_replace('/', '-', $d));
        return $ts ? date('Y-m-d', $ts) : null;
    }
    function val($key) { return isset($_POST[$key]) ? trim($_POST[$key]) : ''; }
    function valNull($key) { $v = val($key); return $v === '' ? null : $v; }

    $first_name     = val('first_name');
    $father_name    = val('lname');
    $mother_name    = val('mother_name');
    $email          = valNull('email');
    $cellno         = val('cellno');
    $class_id       = (int) val('class');
    $section_id     = (int) val('section') ?: null;
    $dob            = val('dob');
    $date_of_adms   = val('date_of_adms');
    $gender         = strtolower(val('gender')) ?: 'male';
    $religion       = val('religion') ?: 'Islam';
    $session        = valNull('session');
    $board_council  = valNull('board_council');
    $group_shift    = valNull('group_shift');
    $adm_source     = valNull('adm_source');
    $locality_id    = val('Locality') !== '' ? (int) val('Locality') : null;
    $father_cnic    = valNull('cnic');
    $father_qual    = valNull('Fqualification');
    $father_bus     = valNull('Fbusiness_address');
    $father_income  = valNull('Fincome');
    $father_occ     = valNull('father_occupation');
    $father_cell    = valNull('father_cellno');
    $mother_cnic    = valNull('mother_cnic');
    $mother_qual    = valNull('mother_qualification');
    $mother_act     = valNull('mother_activity');
    $mother_desig   = valNull('mother_designation');
    $mother_cell    = valNull('mother_cell');
    $formBNo        = valNull('formBNo');
    $caste          = valNull('cast');
    $gname          = valNull('gname');
    $Gcnic          = valNull('Gcnic');
    $Gcellno        = valNull('Gcellno');
    $Gqual          = valNull('Gqualification');
    $Gocc           = valNull('Goccupation');
    $Gincome        = valNull('Gincome');
    $gemail         = valNull('gardian_email');
    $Gaddress       = valNull('Gaddress');
    $old_class      = valNull('old_class');
    $old_school     = valNull('old_school');
    $old_tmarks     = valNull('old_tmarks');
    $old_obtmarks   = valNull('old_obtmarks');
    $form_no        = valNull('form_no');
    $school_leaving = valNull('school_leaving');
    $whatsapp       = valNull('whatsapp_number');
    $home_number    = valNull('home_number');
    $place_of_birth = valNull('place_of_birth');
    $state          = valNull('state');
    $city           = valNull('city');
    $address        = valNull('address');
    $dob_db         = parse_date($dob);
    $adm_db         = parse_date($date_of_adms) ?? date('Y-m-d');

    // Photo: prefer uploaded file, else webcam/sample data
    $photo = null;
    if (!empty($_FILES['img_file']['name']) && $_FILES['img_file']['error'] === UPLOAD_ERR_OK) {
        $dir = __DIR__ . '/uploads/students';
        if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
        $ext = strtolower(pathinfo($_FILES['img_file']['name'], PATHINFO_EXTENSION));
        $photo = 's_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
        if (!move_uploaded_file($_FILES['img_file']['tmp_name'], $dir . '/' . $photo)) { $photo = null; }
    } elseif (!empty($_POST['captured_image'])) {
        $dir = __DIR__ . '/uploads/students';
        if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
        $data = $_POST['captured_image'];
        $prefix = 'data:image/jpeg;base64,';
        if (stripos($data, $prefix) === 0) {
            $base64 = substr($data, strlen($prefix));
            $bin = base64_decode($base64, true);
            if ($bin !== false) {
                $photo = 's_' . time() . '_' . rand(1000, 9999) . '.jpg';
                if (file_put_contents($dir . '/' . $photo, $bin) === false) { $photo = null; }
            }
        }
    }

    if ($first_name === '' || $class_id === 0) {
        $error = 'Student Name and Class are required.';
    } else {
        $cols = ['first_name','last_name','father_name','mother_name','email','phone','dob','gender',
                 'religion','session','board_council','group_shift','admission_source','locality_id',
                 'father_cnic','father_qualification','father_business_address','father_income',
                 'father_occupation','father_cellno','mother_cnic','mother_qualification','mother_activity',
                 'mother_designation','mother_cell','form_b_no','caste','guardian_name','guardian_cnic',
                 'guardian_cellno','guardian_qualification','guardian_occupation','guardian_income',
                 'guardian_email','guardian_address','old_class','old_school','old_tmarks','old_obtmarks',
                 'admission_form_no','school_leaving_reason','whatsapp_number','home_number','place_of_birth',
                 'state','city','address','class_id','section_id','admission_date','status','photo'];

        $lname = $father_name;
        $vals  = [$first_name,$lname,$father_name,$mother_name,$email,$cellno,$dob_db,$gender,
                 $religion,$session,$board_council,$group_shift,$adm_source,$locality_id,
                 $father_cnic,$father_qual,$father_bus,$father_income,
                 $father_occ,$father_cell,$mother_cnic,$mother_qual,$mother_act,
                 $mother_desig,$mother_cell,$formBNo,$caste,$gname,$Gcnic,
                 $Gcellno,$Gqual,$Gocc,$Gincome,
                 $gemail,$Gaddress,$old_class,$old_school,$old_tmarks,$old_obtmarks,
                 $form_no,$school_leaving,$whatsapp,$home_number,$place_of_birth,
                 $state,$city,$address,$class_id,$section_id,$adm_db,1,$photo];

        $placeholders = implode(',', array_fill(0, count($cols), '?'));
        $sql = 'INSERT INTO students (`' . implode('`,`', $cols) . '`) VALUES (' . $placeholders . ')';
        try {
            $stmt = db_prepare($sql);
            $types = str_repeat('s', count($vals));
            $bindVals = [$types];
            foreach ($vals as $k => $v) { $bindVals[] = &$vals[$k]; }
            call_user_func_array([$stmt, 'bind_param'], $bindVals);
            $stmt->execute();
            $studentId = $stmt->insert_id;

            if ($studentId > 0) {
                // GR Number
                $gr = substr(date('Y'), 2) . '-' . str_pad($studentId, 3, '0', STR_PAD_LEFT);
                $u = db_prepare('UPDATE students SET gr_no = ? WHERE student_id = ?');
                $u->bind_param('si', $gr, $studentId);
                $u->execute();

                // Family code
                $family_code = valNull('family_code');
                if (!$family_code) {
                    $key = $Gcellno !== null ? $Gcellno : ($father_cell !== null ? $father_cell : null);
                    if ($key) {
                        $ex = db_prepare("SELECT family_code FROM students WHERE family_code IS NOT NULL AND family_code <> '' AND (guardian_cellno = ? OR father_cellno = ?) LIMIT 1");
                        $ex->bind_param('ss', $key, $key);
                        $ex->execute();
                        $fr = $ex->get_result()->fetch_assoc();
                        $family_code = $fr ? $fr['family_code'] : ('F-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT));
                    }
                }
                if ($family_code) {
                    $u2 = db_prepare('UPDATE students SET family_code = ? WHERE student_id = ?');
                    $u2->bind_param('si', $family_code, $studentId);
                    $u2->execute();
                }

                // Save uploaded documents
                $docTypes = isset($_POST['doc_types']) ? (array) $_POST['doc_types'] : [];
                if (!empty($_FILES['doc_files'])) {
                    $docFiles = $_FILES['doc_files'];
                    $docDir = __DIR__ . '/uploads/students/documents';
                    if (!is_dir($docDir)) { @mkdir($docDir, 0775, true); }
                    $docInsert = db_prepare('INSERT INTO student_documents (student_id, doc_type, file_path) VALUES (?, ?, ?)');
                    foreach ($docFiles['name'] as $i => $name) {
                        if (empty($name)) continue;
                        if ($docFiles['error'][$i] !== UPLOAD_ERR_OK) continue;
                        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                        $fname = 'doc_' . $studentId . '_' . time() . '_' . $i . '_' . rand(1000, 9999) . '.' . $ext;
                        if (!move_uploaded_file($docFiles['tmp_name'][$i], $docDir . '/' . $fname)) continue;
                        $dtype = isset($docTypes[$i]) ? trim($docTypes[$i]) : '';
                        $docInsert->bind_param('iss', $studentId, $dtype, $fname);
                        $docInsert->execute();
                    }
                    $docInsert->close();
                }

                // Detect AJAX request - return JSON with student_id
                $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(['ok' => true, 'student_id' => $studentId, 'gr_no' => $gr]);
                    exit;
                }

                // Normal form submit - redirect to fee plan
                header('Location: ' . BASE_URL . 'add_student.php?student_id=' . $studentId);
                exit;
            }
        } catch (Exception $ex) {
            $error = 'Error: ' . $ex->getMessage();
        }
    }
}

include __DIR__ . '/includes/header.php';
?>

<style>
    .wizard-body * { box-sizing: border-box; }
    .custom-input { width: 100%; border: 1px solid #cbd5e1; border-radius: 0.375rem; padding: 0.45rem 0.75rem; font-size: 0.875rem; transition: all 0.15s ease-in-out; background-color: #fff; }
    .custom-input:focus { outline: none; border-color: #f97316; box-shadow: 0 0 0 2px rgba(249,115,22,0.2); }
    .floating-label-group { position: relative; }
    .floating-label-group label { position: absolute; top: -0.6rem; left: 0.6rem; background-color: white; padding: 0 0.3rem; font-size: 0.75rem; font-weight: 500; color: #64748b; border-radius: 2px; z-index: 10; }
    .wizard-pane { display: none; }
    .wizard-pane.active { display: block; }
    .avatar-frame { border: 2px dashed #f97316; border-radius: 0.5rem; }
    ::-webkit-scrollbar { width: 6px; height: 6px; }
    ::-webkit-scrollbar-track { background: #f1f5f9; }
    ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
    ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
</style>

<div class="wizard-body" style="background:#f1f5f9; min-height:100vh;">

    <!-- Breadcrumb -->
    <div style="padding:10px 4px 10px; font-size:12.5px; color:#64748b; font-weight:500;">
        <a href="dashboard.php" style="color:#377dff; text-decoration:none;">Dashboard</a>
        <span style="margin:0 8px;">&raquo;</span>
        <a href="manage_students.php" style="color:#377dff; text-decoration:none;">Students</a>
        <span style="margin:0 8px;">&raquo;</span>
        <span id="bcCurrent" style="color:#1e293b; font-weight:600;">Add New Student</span>
    </div>

    <!-- Wizard Progress Indicator -->
    <div id="stepProgressContainer" style="background:#fff; border-radius:0.75rem; box-shadow:0 1px 3px rgba(0,0,0,.05); border:1px solid #f1f5f9; padding:16px; margin-bottom:20px;">
        <div style="max-width:580px; margin:0 auto; display:flex; align-items:center; justify-content:space-between; position:relative;">
            <div style="position:absolute; left:0; right:0; top:50%; transform:translateY(-50%); height:2px; background:#e5e7eb; z-index:0;"></div>
            <div id="progressLine" style="position:absolute; left:0; top:50%; transform:translateY(-50%); height:2px; background:#f97316; z-index:0; transition:width .3s; width:0%;"></div>
            <div class="wizard-step-circle" id="ws1" onclick="goToWizardStep(1)" style="position:relative; z-index:1; display:flex; flex-direction:column; align-items:center; cursor:pointer;">
                <div id="ws1c" style="width:32px; height:32px; border-radius:50%; background:#f97316; color:#fff; font-weight:700; font-size:12px; display:flex; align-items:center; justify-content:center; box-shadow:0 2px 6px rgba(249,115,22,.3);">1</div>
                <span id="ws1l" style="font-size:11px; font-weight:600; color:#ea580c; margin-top:4px;">Student Info</span>
            </div>
            <div class="wizard-step-circle" id="ws2" onclick="goToWizardStep(2)" style="position:relative; z-index:1; display:flex; flex-direction:column; align-items:center; cursor:pointer;">
                <div id="ws2c" style="width:32px; height:32px; border-radius:50%; background:#e5e7eb; border:2px solid #fff; color:#6b7280; font-weight:700; font-size:12px; display:flex; align-items:center; justify-content:center; box-shadow:0 1px 3px rgba(0,0,0,.08);">2</div>
                <span id="ws2l" style="font-size:11px; font-weight:600; color:#9ca3af; margin-top:4px;">Save Fee Plan</span>
            </div>
            <div class="wizard-step-circle" id="ws3" onclick="goToWizardStep(3)" style="position:relative; z-index:1; display:flex; flex-direction:column; align-items:center; cursor:pointer;">
                <div id="ws3c" style="width:32px; height:32px; border-radius:50%; background:#e5e7eb; border:2px solid #fff; color:#6b7280; font-weight:700; font-size:12px; display:flex; align-items:center; justify-content:center; box-shadow:0 1px 3px rgba(0,0,0,.08);">3</div>
                <span id="ws3l" style="font-size:11px; font-weight:600; color:#9ca3af; margin-top:4px;">Finish</span>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STEP 1: Student Info -->
    <!-- ================================================================ -->
    <section id="step1View" class="space-y-4" style="margin-bottom:24px;">

        <!-- Category Tabs -->
        <div style="display:flex; flex-wrap:wrap; gap:8px; border-bottom:1px solid #e5e7eb; padding-bottom:8px;">
            <a href="add_student.php" style="padding:8px 16px; background:#f97316; color:#fff; font-size:12px; font-weight:600; border-radius:6px 6px 0 0; box-shadow:0 1px 3px rgba(249,115,22,.2); display:flex; align-items:center; gap:8px; text-decoration:none;">
                <i class="fa fa-user-plus" style="font-size:12px;"></i> Add New Student
            </a>
            <a href="bulk_stdns.php" style="padding:8px 16px; background:#fff; color:#4b5563; border:1px solid #e5e7eb; font-size:12px; font-weight:500; border-radius:6px 6px 0 0; display:flex; align-items:center; gap:8px; text-decoration:none;">
                <i class="fa fa-users" style="color:#6b7280; font-size:12px;"></i> Add Multi Students
            </a>
            <a href="import_data.php" style="padding:8px 16px; background:#fff; color:#4b5563; border:1px solid #e5e7eb; font-size:12px; font-weight:500; border-radius:6px 6px 0 0; display:flex; align-items:center; gap:8px; text-decoration:none;">
                <i class="fa fa-upload" style="color:#6b7280; font-size:12px;"></i> Import Students with CSV
            </a>
            <a href="adm_form.php" target="_blank" style="padding:8px 16px; background:#fff; color:#4b5563; border:1px solid #e5e7eb; font-size:12px; font-weight:500; border-radius:6px 6px 0 0; display:flex; align-items:center; gap:8px; text-decoration:none;">
                <i class="fa fa-file-text" style="color:#6b7280; font-size:12px;"></i> Admission Form
            </a>
        </div>

        <!-- Form Container -->
        <div style="background:#fff; border:1px solid #e5e7eb; border-radius:0.75rem; overflow:hidden; padding:20px;">

            <!-- Inner Section Navigation Tabs -->
            <div style="display:flex; flex-wrap:wrap; align-items:center; gap:6px; background:#f9fafb; padding:6px; border-radius:0.5rem; margin-bottom:24px; border:1px solid #f3f4f6; font-size:12px; font-weight:500;">
                <button type="button" class="inner-tab-btn active" data-pane="basic-info" onclick="activateInnerTab(this,'basic-info')">
                    <i class="fa fa-id-card" style="color:#f97316;"></i> Basic Information
                </button>
                <button type="button" class="inner-tab-btn" data-pane="parent-details" onclick="activateInnerTab(this,'parent-details')">
                    <i class="fa fa-user-friends" style="color:#9ca3af;"></i> Parent Details
                </button>
                <button type="button" class="inner-tab-btn" data-pane="academic-info" onclick="activateInnerTab(this,'academic-info')">
                    <i class="fa fa-graduation-cap" style="color:#9ca3af;"></i> Academic Information
                </button>
                <button type="button" class="inner-tab-btn" data-pane="contact-info" onclick="activateInnerTab(this,'contact-info')">
                    <i class="fa fa-phone" style="color:#9ca3af;"></i> Contact Information
                </button>
                <button type="button" class="inner-tab-btn" data-pane="documents" onclick="activateInnerTab(this,'documents')">
                    <i class="fa fa-folder-open" style="color:#9ca3af;"></i> Documents
                </button>
            </div>
            <style>
                .inner-tab-btn { padding:6px 12px; border-radius:6px; font-size:12px; font-weight:600; color:#4b5563; cursor:pointer; border:none; background:transparent; display:flex; align-items:center; gap:6px; transition:all .15s; }
                .inner-tab-btn:hover { background:#f3f4f6; }
                .inner-tab-btn.active { background:#fff; color:#ea580c; border:1px solid #fed7aa; box-shadow:0 1px 3px rgba(0,0,0,.05); }
            </style>

            <form id="studentForm" action="add_student.php" method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="AddAdmission">
                <input type="hidden" name="captured_image" id="captured_image" value="">
                <input type="hidden" name="family_code" id="family_code_value" value="">

                <!-- ======== PANE: Basic Information ======== -->
                <div class="wizard-pane active" id="pane-basic-info">
                    <div style="display:grid; grid-template-columns:1fr; gap:24px;">
                        <!-- Left: Form Fields -->
                        <div>
                            <!-- Row 1 -->
                            <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:16px;">
                                <div class="floating-label-group">
                                    <label>Student Name *</label>
                                    <input type="text" name="first_name" id="fname" class="custom-input" placeholder="Enter student name" required maxlength="35">
                                </div>
                                <div class="floating-label-group">
                                    <label>Father Name</label>
                                    <input type="text" name="lname" id="last_name" class="custom-input" placeholder="Enter father name" maxlength="35">
                                </div>
                                <div class="floating-label-group">
                                    <label>Select Family</label>
                                    <select name="family_search" id="family_search" class="custom-input">
                                        <option value="">Select Family</option>
                                        <?php foreach ($families as $fam): ?>
                                            <option value="<?php echo e($fam['family_code']); ?>"><?php echo e($fam['family_code']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="floating-label-group">
                                    <label>Cell Number *</label>
                                    <input type="text" name="cellno" id="cell_no" class="custom-input" placeholder="Enter cell number" required>
                                </div>
                            </div>
                            <!-- Row 2 -->
                            <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:16px;">
                                <div class="floating-label-group">
                                    <label>Session</label>
                                    <select name="session" id="session" class="custom-input">
                                        <?php foreach ($sessions as $s): ?>
                                            <option value="<?php echo e($s); ?>" <?php echo $s === $cur_session ? 'selected' : ''; ?>><?php echo e($s); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="floating-label-group">
                                    <label>Select Course/Class *</label>
                                    <select name="class_id" id="class_id" class="custom-input" required onchange="getSection(this.value)">
                                        <option value="">Select Course/Class</option>
                                        <?php foreach ($classes as $c): ?>
                                            <option value="<?php echo $c['class_id']; ?>"><?php echo e($c['class_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="floating-label-group">
                                    <label>Select Section</label>
                                    <select name="section" id="txt_section" class="custom-input"></select>
                                </div>
                                <div class="floating-label-group">
                                    <label>GR-No</label>
                                    <input type="text" value="Auto" class="custom-input" readonly style="background:#f9fafb; color:#9ca3af;">
                                </div>
                            </div>
                            <!-- Row 3 -->
                            <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:16px;">
                                <div class="floating-label-group">
                                    <label>Gender *</label>
                                    <select name="gender" id="gender" class="custom-input" required>
                                        <option value="male">Male</option>
                                        <option value="female">Female</option>
                                    </select>
                                </div>
                                <div class="floating-label-group">
                                    <label>Religion *</label>
                                    <select name="religion" id="religion" class="custom-input" required>
                                        <option value="Islam" selected>Muslim</option>
                                        <option value="Hinduism">Hindu</option>
                                        <option value="Sikhism">Sikh</option>
                                        <option value="Christianity">Christian</option>
                                    </select>
                                </div>
                                <div class="floating-label-group">
                                    <label>Date Of Birth</label>
                                    <input type="date" name="dob" id="dob" class="custom-input" max="<?php echo date('Y-m-d'); ?>">
                                </div>
                                <div class="floating-label-group">
                                    <label>Date Of Admission</label>
                                    <input type="date" name="date_of_adms" id="date_of_adms" class="custom-input">
                                </div>
                            </div>
                            <!-- Row 4 -->
                            <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:16px;">
                                <div class="floating-label-group" style="display:flex; gap:4px;">
                                    <div style="flex:1;">
                                        <label>Board/Council</label>
                                        <select id="board_council" name="board_council" class="custom-input">
                                            <option value="">Select Board</option>
                                            <?php foreach ($boards as $b): ?><option value="<?php echo e($b['name']); ?>"><?php echo e($b['name']); ?></option><?php endforeach; ?>
                                        </select>
                                    </div>
                                    <a href="manage_board.php" target="_blank" style="margin-top:22px; padding:4px 8px; border:1px solid #fdba74; color:#ea580c; border-radius:4px; font-size:11px; text-decoration:none; font-weight:600; white-space:nowrap;">+ Add New</a>
                                </div>
                                <div class="floating-label-group" style="display:flex; gap:4px;">
                                    <div style="flex:1;">
                                        <label>Group/Shift</label>
                                        <select id="group_shift" name="group_shift" class="custom-input">
                                            <option value="">Select Group</option>
                                            <?php foreach ($groups as $g): ?><option value="<?php echo e($g['name']); ?>"><?php echo e($g['name']); ?></option><?php endforeach; ?>
                                        </select>
                                    </div>
                                    <a href="manage_group.php" target="_blank" style="margin-top:22px; padding:4px 8px; border:1px solid #fdba74; color:#ea580c; border-radius:4px; font-size:11px; text-decoration:none; font-weight:600; white-space:nowrap;">+ Add New</a>
                                </div>
                                <div class="floating-label-group" style="display:flex; gap:4px;">
                                    <div style="flex:1;">
                                        <label>Admission Source</label>
                                        <select id="adm_source" name="adm_source" class="custom-input">
                                            <option value="">Select Source</option>
                                            <?php foreach ($admSrcs as $a): ?><option value="<?php echo e($a['name']); ?>"><?php echo e($a['name']); ?></option><?php endforeach; ?>
                                        </select>
                                    </div>
                                    <a href="manage_admission_sources.php" target="_blank" style="margin-top:22px; padding:4px 8px; border:1px solid #fdba74; color:#ea580c; border-radius:4px; font-size:11px; text-decoration:none; font-weight:600; white-space:nowrap;">+ Add New</a>
                                </div>
                                <div class="floating-label-group" style="display:flex; gap:4px;">
                                    <div style="flex:1;">
                                        <label>Choose Locality</label>
                                        <select name="Locality" id="locality" class="custom-input">
                                            <option value="">Choose Locality</option>
                                            <?php foreach ($localities as $l): ?><option value="<?php echo $l['locality_id']; ?>"><?php echo e($l['locality_name']); ?></option><?php endforeach; ?>
                                        </select>
                                    </div>
                                    <a href="manage_localities.php" target="_blank" style="margin-top:22px; padding:4px 8px; border:1px solid #fdba74; color:#ea580c; border-radius:4px; font-size:11px; text-decoration:none; font-weight:600; white-space:nowrap;">+ Add New</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ======== PANE: Parent Details ======== -->
                <div class="wizard-pane" id="pane-parent-details">
                    <!-- Family Information -->
                    <div style="font-size:14px; font-weight:700; color:#111827; margin:14px 0; border-left:4px solid #f97316; padding-left:12px;">Family Information</div>
                    <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:16px;">
                        <div class="floating-label-group"><label>Father CNIC</label><input type="text" class="custom-input" name="cnic" id="cnic" placeholder="CNIC"></div>
                        <div class="floating-label-group"><label>Father Qualification</label><input type="text" class="custom-input" name="Fqualification" id="father_qualification" placeholder="Father Qualification"></div>
                        <div class="floating-label-group"><label>Father Business Address</label><input type="text" class="custom-input" name="Fbusiness_address" id="Fbusiness_address" placeholder="Father Business Address"></div>
                        <div class="floating-label-group"><label>Father Income</label><input type="text" class="custom-input" name="Fincome" id="father_income" placeholder="Father Income"></div>
                    </div>
                    <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:16px;">
                        <div class="floating-label-group"><label>Mother Name</label><input type="text" class="custom-input" name="mother_name" id="mother_name" placeholder="Mother Name"></div>
                        <div class="floating-label-group"><label>Mother CNIC</label><input type="text" class="custom-input" name="mother_cnic" id="mother_cnic" placeholder="Mother CNIC"></div>
                        <div class="floating-label-group"><label>Mother Qualification</label><input type="text" class="custom-input" name="mother_qualification" id="mother_qualification" placeholder="Mother Qualification"></div>
                        <div class="floating-label-group"><label>Mother Activities</label>
                            <select id="mother_activity" name="mother_activity" class="custom-input">
                                <option value="">Mother Activities</option>
                                <option value="House Wife">House Wife</option>
                                <option value="Working">Working</option>
                                <option value="Business">Business</option>
                            </select>
                        </div>
                    </div>
                    <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:16px;">
                        <div class="floating-label-group"><label>Mother Designation</label><input type="text" class="custom-input" name="mother_designation" id="mother_designation" placeholder="Mother Designation"></div>
                        <div class="floating-label-group"><label>Home Address</label><input type="text" class="custom-input" name="address" id="address" placeholder="Family Home Address"></div>
                        <div class="floating-label-group"><label>Father Occupation</label>
                            <div style="display:flex; gap:4px;">
                                <select name="father_occupation" id="father_occupation" class="custom-input" style="flex:1;">
                                    <option value="">Choose Occupation</option>
                                    <?php foreach ($occupations as $o): ?><option value="<?php echo e($o['name']); ?>"><?php echo e($o['name']); ?></option><?php endforeach; ?>
                                </select>
                                <a href="manage_occupations.php" target="_blank" style="margin-top:18px; padding:4px 8px; border:1px solid #fdba74; color:#ea580c; border-radius:4px; font-size:11px; text-decoration:none; font-weight:600; white-space:nowrap;">+ Add New</a>
                            </div>
                        </div>
                        <div class="floating-label-group"><label>B-Form No</label><input type="text" class="custom-input" name="formBNo" id="formBNo" placeholder="Form-B No"></div>
                    </div>
                    <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:16px;">
                        <div class="floating-label-group"><label>Cast</label><input type="text" class="custom-input" name="cast" id="cast" placeholder="Cast"></div>
                    </div>
                    <!-- Guardian Section -->
                    <div style="font-size:14px; font-weight:700; color:#111827; margin:14px 0; border-left:4px solid #8b5cf6; padding-left:12px;">Guardian Information <span style="font-size:12px; color:#9ca3af; font-weight:400;">(in case of death of father)</span></div>
                    <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:16px;">
                        <div class="floating-label-group"><label>Guardian Name</label><input type="text" class="custom-input" name="gname" id="gardian_name" placeholder="Guardian Name"></div>
                        <div class="floating-label-group"><label>Guardian CNIC</label><input type="text" class="custom-input" name="Gcnic" id="gardian_cnic" placeholder="Guardian CNIC"></div>
                        <div class="floating-label-group"><label>Guardian Cell No</label><input type="text" class="custom-input" name="Gcellno" id="gardian_no" placeholder="Guardian Cell No"></div>
                        <div class="floating-label-group"><label>Guardian Qualification</label><input type="text" class="custom-input" name="Gqualification" id="gardian_qualification" placeholder="Guardian Qualification"></div>
                    </div>
                    <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:16px;">
                        <div class="floating-label-group"><label>Guardian Occupation</label><input type="text" class="custom-input" name="Goccupation" id="gardian_occupation" placeholder="Guardian Occupation"></div>
                        <div class="floating-label-group"><label>Guardian Income</label><input type="text" class="custom-input" name="Gincome" id="gardian_income" placeholder="Guardian Income"></div>
                        <div class="floating-label-group"><label>Guardian Email</label><input type="text" class="custom-input" name="gardian_email" id="gardian_email" placeholder="Guardian Email"></div>
                        <div class="floating-label-group"><label>Guardian Address</label><input type="text" class="custom-input" name="Gaddress" id="gardian_address" placeholder="Guardian Address"></div>
                    </div>
                </div>

                <!-- ======== PANE: Academic Information ======== -->
                <div class="wizard-pane" id="pane-academic-info">
                    <div style="font-size:14px; font-weight:700; color:#111827; margin:14px 0; border-left:4px solid #8b5cf6; padding-left:12px;">Admission &amp; Academic Information</div>
                    <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:16px;">
                        <div class="floating-label-group"><label>Previous Course/Class</label><input type="text" class="custom-input" name="old_class" id="old_class" placeholder="Previous Course/Class"></div>
                        <div class="floating-label-group"><label>Previous Institute</label><input type="text" class="custom-input" name="old_school" id="old_school" placeholder="Previous Institute"></div>
                        <div class="floating-label-group"><label>Total Marks</label><input type="text" class="custom-input" name="old_tmarks" id="old_tmarks" placeholder="Total Marks"></div>
                        <div class="floating-label-group"><label>Obtained Marks</label><input type="text" class="custom-input" name="old_obtmarks" id="old_obtmarks" placeholder="Obtained Marks"></div>
                    </div>
                    <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:16px;">
                        <div class="floating-label-group"><label>Admission Form No</label><input type="text" class="custom-input" name="form_no" id="adm-no" placeholder="Form Number"></div>
                        <div class="floating-label-group" style="grid-column:span 2;"><label>Reason for Previous School Leaving</label><input type="text" class="custom-input" name="school_leaving" id="school_leaving" placeholder="Reason for Previous School Leaving"></div>
                        <div class="floating-label-group"><label>Home PTCL Number</label><input type="text" class="custom-input" name="home_number" id="home_number" placeholder="Home PTCL Number"></div>
                    </div>
                </div>

                <!-- ======== PANE: Contact Information ======== -->
                <div class="wizard-pane" id="pane-contact-info">
                    <div style="font-size:14px; font-weight:700; color:#111827; margin:14px 0; border-left:4px solid #06b6d4; padding-left:12px;">Contact &amp; Address Information</div>
                    <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:16px;">
                        <div class="floating-label-group"><label>WhatsApp No</label><input type="text" class="custom-input" name="whatsapp_number" id="whatsapp_number" placeholder="WhatsApp Number"></div>
                        <div class="floating-label-group"><label>Father Cell No</label><input type="text" class="custom-input" name="father_cellno" id="father_cellno" placeholder="Father Cell No"></div>
                        <div class="floating-label-group"><label>Mother Cell No</label><input type="text" class="custom-input" name="mother_cell" id="mother_cell" placeholder="Mother Cell Number"></div>
                        <div class="floating-label-group"><label>Place Of Birth</label><input type="text" class="custom-input" name="place_of_birth" id="place_of_birth" placeholder="Place Of Birth"></div>
                    </div>
                    <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:16px;">
                        <div class="floating-label-group"><label>Select State</label>
                            <select name="state" id="state" class="custom-input">
                                <option value="">Select State</option>
                                <?php foreach (['Sindh','Punjab','Balochistan','KPK','Gilgit-Baltistan','Kashmir (territory)','FATA (territory)','Federal'] as $st): ?>
                                    <option value="<?php echo e($st); ?>"><?php echo e($st); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="floating-label-group"><label>City</label><input type="text" class="custom-input" name="city" id="city" placeholder="City"></div>
                        <div class="floating-label-group"><label>Email</label><input type="text" class="custom-input" name="email" id="email" placeholder="Email"></div>
                    </div>
                </div>

                <!-- ======== PANE: Documents ======== -->
                <div class="wizard-pane" id="pane-documents">
                    <div style="font-size:14px; font-weight:700; color:#111827; margin:14px 0; border-left:4px solid #10b981; padding-left:12px;">Student Documents</div>
                    <div style="display:flex; align-items:center; justify-content:space-between; gap:10px; padding:10px 14px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:10px; margin-bottom:14px;">
                        <div style="font-size:12.5px; color:#166534; display:flex; align-items:center; gap:8px;">
                            <i class="fa fa-info-circle"></i>
                            <span>Upload the student's documents below. Accepted: JPG, PNG, PDF.</span>
                        </div>
                        <a href="add_student_documents.php" target="_blank" style="font-size:12px; font-weight:600; color:#166534; text-decoration:none; border:1px solid #bbf7d0; background:#fff; padding:6px 12px; border-radius:999px;"><i class="fa fa-plus-circle"></i> Manage Document Titles</a>
                    </div>
                    <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(170px,1fr)); gap:12px;">
                        <?php foreach ($docTitles as $i => $dt): ?>
                            <div class="doc-card-item" id="docCard_<?php echo $i; ?>" style="border:1px solid #e5e7eb; border-radius:12px; padding:12px; text-align:center; background:#fff; transition:all .2s;">
                                <div id="docThumb_<?php echo $i; ?>" style="height:80px; border-radius:8px; background:#f7f9fc; display:flex; align-items:center; justify-content:center; overflow:hidden; margin-bottom:8px;">
                                    <i class="fa fa-file-text" style="font-size:30px; color:#cbd5e1;"></i>
                                </div>
                                <div style="font-size:12px; font-weight:600; color:#111827; min-height:28px;"><?php echo e($dt['name']); ?></div>
                                <span id="docStatus_<?php echo $i; ?>" style="font-size:11px; color:#94a3b8;">Not Uploaded</span><br>
                                <label for="docFile_<?php echo $i; ?>" style="display:inline-block; margin-top:6px; font-size:12px; font-weight:600; color:#fff; background:#f97316; padding:5px 12px; border-radius:999px; cursor:pointer;"><i class="fa fa-upload"></i> Choose File</label>
                                <input type="hidden" name="doc_types[]" value="<?php echo e($dt['name']); ?>">
                                <input type="file" id="docFile_<?php echo $i; ?>" name="doc_files[]" accept=".jpg,.jpeg,.png,.pdf" style="display:none;" onchange="previewStudentDoc(this, <?php echo $i; ?>)">
                                <div id="docFileName_<?php echo $i; ?>" style="font-size:10px; color:#64748b; margin-top:4px; word-break:break-all;"></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Photo Upload (shown on all panes via fixed right column on basic-info) -->
                <div id="photoBoxContainer" style="display:none;"></div>

                <!-- Step 1 Footer -->
                <div style="margin-top:32px; padding-top:16px; border-top:1px solid #f3f4f6; display:flex; justify-content:space-between; align-items:center;">
                    <span style="font-size:12px; color:#9ca3af; font-style:italic;">* Marked fields are mandatory</span>
                    <div style="display:flex; gap:12px;">
                        <a href="manage_students.php" style="padding:8px 24px; border:1px solid #d1d5db; color:#4b5563; border-radius:6px; font-size:12px; font-weight:600; text-decoration:none;">Cancel</a>
                        <button type="button" onclick="submitStep1()" style="padding:8px 24px; background:#f97316; color:#fff; border:none; border-radius:6px; font-size:12px; font-weight:600; cursor:pointer; box-shadow:0 1px 3px rgba(249,115,22,.3);">Save Student</button>
                    </div>
                </div>
            </form>
        </div>
    </section>

    <!-- ================================================================ -->
    <!-- STEP 2: Fee Plan (inline) -->
    <!-- ================================================================ -->
    <section id="step2View" class="space-y-4" style="margin-bottom:24px; display:none;">
        <!-- Student Header Banner -->
        <div style="background:#fff; border:1px solid #fff7ed; border-radius:0.75rem; padding:16px; box-shadow:0 1px 3px rgba(0,0,0,.05); display:flex; align-items:center; gap:16px;">
            <div id="step2Avatar" style="width:48px; height:48px; border-radius:50%; background:#f97316; color:#fff; display:flex; align-items:center; justify-content:center; font-size:20px; font-weight:700; box-shadow:0 2px 8px rgba(249,115,22,.3);">
                <i class="fa fa-user"></i>
            </div>
            <div>
                <h2 style="font-size:18px; font-weight:700; color:#111827; line-height:1.2; margin:0;" id="step2StudentName">--</h2>
                <div style="display:flex; gap:16px; font-size:12px; color:#6b7280; font-weight:500; margin-top:4px;">
                    <span style="display:flex; align-items:center; gap:4px;"><i class="fa fa-graduation-cap" style="color:#f97316;"></i> <span id="step2StudentClass">--</span></span>
                    <span style="display:flex; align-items:center; gap:4px;"><i class="fa fa-phone" style="color:#f97316;"></i> <span id="step2StudentCell">--</span></span>
                </div>
            </div>
        </div>

        <!-- Fee Plan Form -->
        <div style="background:#fff; border:1px solid #e5e7eb; border-radius:0.75rem; padding:20px;">
            <div style="border-left:4px solid #f97316; padding-left:12px; margin-bottom:24px;">
                <h3 style="font-size:16px; font-weight:700; color:#111827; margin:0;">Manage Student Fee <span style="font-size:12px; font-weight:400; color:#f97316;">(Monthly Fee Parameters)</span></h3>
            </div>

            <div style="display:grid; grid-template-columns:repeat(2,1fr); gap:16px; margin-bottom:20px;">
                <div class="floating-label-group">
                    <label>Payment Mode</label>
                    <select id="inputPaymentMode" class="custom-input">
                        <option>Monthly</option>
                        <option>Quarterly</option>
                        <option>Annual</option>
                    </select>
                </div>
                <div class="floating-label-group">
                    <label>Discount Package</label>
                    <select id="inputDiscountPackage" class="custom-input">
                        <option value="">Select Discount</option>
                        <option value="sibling">Sibling Discount 10%</option>
                        <option value="merit">Merit Scholarship 20%</option>
                    </select>
                </div>
            </div>

            <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-top:0;">
                <div class="floating-label-group"><label>Monthly Fee</label><input type="number" id="inputMonthlyFee" class="custom-input" placeholder="0.00"></div>
                <div class="floating-label-group"><label>Course Package</label><input type="number" id="inputCoursePackage" class="custom-input" placeholder="0.00"></div>
                <div class="floating-label-group"><label>Old Balance</label><input type="number" id="inputOldBalance" class="custom-input" placeholder="0.00"></div>
                <div class="floating-label-group"><label>Transport</label><input type="number" id="inputTransportFee" class="custom-input" placeholder="0.00"></div>
            </div>
            <div style="margin-top:16px;">
                <div class="floating-label-group"><label>Discount Reason</label><input type="text" id="inputDiscountReason" class="custom-input" placeholder="Enter reason if discount applied"></div>
            </div>

            <!-- Step 2 Footer -->
            <div style="margin-top:32px; padding-top:16px; border-top:1px solid #f3f4f6; display:flex; justify-content:space-between; align-items:center;">
                <span style="font-size:12px; color:#9ca3af; font-style:italic;">Amounts auto-filled from class fee settings &mdash; adjust if required.</span>
                <button type="button" onclick="saveFeePlan()" style="padding:8px 24px; background:#f97316; color:#fff; border:none; border-radius:6px; font-size:12px; font-weight:600; cursor:pointer; box-shadow:0 1px 3px rgba(249,115,22,.3);">Save Fee Plan</button>
            </div>
        </div>
    </section>

    <!-- ================================================================ -->
    <!-- STEP 3: Finish -->
    <!-- ================================================================ -->
    <section id="step3View" class="space-y-6" style="margin-bottom:24px; display:none;">
        <!-- Success Banner -->
        <div id="successBanner" style="background:#10b981; color:#fff; padding:10px 16px; border-radius:8px; font-size:12px; font-weight:500; display:flex; justify-content:space-between; align-items:center; box-shadow:0 1px 3px rgba(0,0,0,.1);">
            <span>Congrats! Your record is added successfully...</span>
            <button onclick="this.parentElement.style.display='none'" style="background:none; border:none; color:#fff; font-weight:700; font-size:16px; cursor:pointer;">&times;</button>
        </div>

        <!-- Confirmation Card -->
        <div style="background:#fff; border:1px solid #e5e7eb; border-radius:0.75rem; padding:40px; text-align:center; max-width:720px; margin:0 auto;">
            <div style="width:64px; height:64px; background:#10b981; color:#fff; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 24px; font-size:28px; box-shadow:0 4px 12px rgba(16,185,129,.25); box-shadow:0 0 0 8px rgba(16,185,129,.08);">
                <i class="fa fa-check"></i>
            </div>
            <h2 style="font-size:20px; font-weight:700; color:#111827; margin:0 0 16px;">Fee Plan Saved Successfully</h2>
            <div style="display:flex; flex-wrap:wrap; justify-content:center; gap:12px; margin-top:24px;">
                <button onclick="goToWizardStep(1); resetWizard();" style="padding:10px 20px; background:#f97316; color:#fff; border:none; border-radius:6px; font-size:12px; font-weight:600; cursor:pointer; display:flex; align-items:center; gap:6px; box-shadow:0 1px 3px rgba(249,115,22,.3);">
                    <i class="fa fa-user-plus"></i> Add New Student
                </button>
                <button onclick="viewProfile();" style="padding:10px 20px; border:2px solid #f97316; color:#ea580c; background:#fff; border-radius:6px; font-size:12px; font-weight:700; cursor:pointer; display:flex; align-items:center; gap:6px;">
                    <i class="fa fa-address-card"></i> View Profile
                </button>
                <a href="adm_form.php" target="_blank" style="padding:10px 20px; border:1px solid #fdba74; color:#ea580c; background:#fff; border-radius:6px; font-size:12px; font-weight:500; text-decoration:none;">Admission Form</a>
            </div>
        </div>
    </section>

    <!-- ================================================================ -->
    <!-- PROFILE VIEW -->
    <!-- ================================================================ -->
    <section id="profileView" style="display:none; margin-bottom:24px;">
        <!-- Gradient Header -->
        <div style="background:linear-gradient(135deg,#f97316 0%,#ec4899 50%,#8b5cf6 100%); border-radius:0.75rem; padding:20px; color:#fff; box-shadow:0 4px 12px rgba(0,0,0,.1); position:relative; overflow:hidden;">
            <div style="display:flex; flex-wrap:wrap; align-items:flex-start; justify-content:space-between; gap:16px;">
                <div style="display:flex; align-items:center; gap:16px;">
                    <div id="profAvatar" style="width:80px; height:80px; border-radius:50%; background:#fff; padding:3px; box-shadow:0 2px 8px rgba(0,0,0,.15); flex-shrink:0; display:flex; align-items:center; justify-content:center;">
                        <img id="profAvatarImg" style="width:100%; height:100%; border-radius:50%; object-fit:cover;" src="data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%23374151'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M12 14l9-5-9-5-9 5 9 5z'/><path stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0112 20.055a11.952 11.952 0 01-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z'/></svg>">
                    </div>
                    <div>
                        <h2 id="profName" style="font-size:22px; font-weight:900; letter-spacing:.5px; text-transform:uppercase; margin:0;">--</h2>
                        <div style="display:flex; flex-wrap:wrap; gap:6px; font-size:11px; margin-top:6px;">
                            <span style="background:rgba(0,0,0,.2); backdrop-filter:blur(4px); padding:2px 10px; border-radius:999px; font-weight:500;">&bull; <span id="profClassSec">--</span></span>
                            <span style="background:rgba(0,0,0,.2); backdrop-filter:blur(4px); padding:2px 10px; border-radius:999px; font-weight:500;">&bull; GR# <span id="profGRNo">--</span></span>
                            <span style="background:rgba(0,0,0,.2); backdrop-filter:blur(4px); padding:2px 10px; border-radius:999px; font-weight:500;">&bull; Session: <span id="profSession">--</span></span>
                        </div>
                        <div style="display:flex; flex-wrap:wrap; gap:6px; font-size:11px; margin-top:6px;">
                            <span style="background:rgba(255,255,255,.2); padding:2px 8px; border-radius:4px; font-weight:500; display:flex; align-items:center; gap:4px;"><i class="fa fa-venus-mars" style="font-size:10px;"></i> <span id="profGender">--</span></span>
                            <span style="background:rgba(255,255,255,.2); padding:2px 8px; border-radius:4px; font-weight:500; display:flex; align-items:center; gap:4px;"><i class="fa fa-calendar-check" style="font-size:10px;"></i> Admitted: <span id="profDOA">--</span></span>
                            <span style="background:#10b981; color:#fff; font-weight:700; padding:2px 12px; border-radius:999px; font-size:10px; display:flex; align-items:center; gap:4px;">
                                <span style="width:6px; height:6px; border-radius:50%; background:#fff; animation:pulse 1.5s infinite;"></span> Active
                            </span>
                        </div>
                    </div>
                </div>
                <div style="display:flex; flex-wrap:wrap; gap:8px;">
                    <button onclick="goToWizardStep(1);" style="background:#10b981; color:#fff; padding:6px 12px; border-radius:4px; font-size:12px; font-weight:500; cursor:pointer; border:none; display:flex; align-items:center; gap:4px;">
                        <i class="fa fa-pencil"></i> Edit Profile
                    </button>
                </div>
            </div>
        </div>

        <!-- Profile Tabs -->
        <div style="background:#fff; border:1px solid #e5e7eb; border-radius:0.5rem; padding:4px; overflow-x:auto; display:flex; gap:4px; font-size:12px; font-weight:600; color:#4b5563; margin-top:16px;">
            <button style="padding:8px 16px; background:#f3f4f6; color:#111827; border-bottom:2px solid #f97316; border-radius:4px; font-weight:700; white-space:nowrap; display:flex; align-items:center; gap:6px; border:none; cursor:pointer;">
                <i class="fa fa-user" style="color:#f97316;"></i> Student Profile
            </button>
            <a href="#" style="padding:8px 16px; border-radius:4px; white-space:nowrap; display:flex; align-items:center; gap:6px; text-decoration:none; color:#4b5563;"><i class="fa fa-money" style="color:#9ca3af;"></i> Fee History</a>
            <a href="#" style="padding:8px 16px; border-radius:4px; white-space:nowrap; display:flex; align-items:center; gap:6px; text-decoration:none; color:#4b5563;"><i class="fa fa-trophy" style="color:#9ca3af;"></i> Results</a>
            <a href="#" style="padding:8px 16px; border-radius:4px; white-space:nowrap; display:flex; align-items:center; gap:6px; text-decoration:none; color:#4b5563;"><i class="fa fa-calendar" style="color:#9ca3af;"></i> Attendance</a>
            <a href="#" style="padding:8px 16px; border-radius:4px; white-space:nowrap; display:flex; align-items:center; gap:6px; text-decoration:none; color:#4b5563;"><i class="fa fa-users" style="color:#9ca3af;"></i> Siblings</a>
            <a href="#" style="padding:8px 16px; border-radius:4px; white-space:nowrap; display:flex; align-items:center; gap:6px; text-decoration:none; color:#2563eb; font-weight:700;"><i class="fa fa-book"></i> Notes</a>
            <a href="#" style="padding:8px 16px; border-radius:4px; white-space:nowrap; display:flex; align-items:center; gap:6px; text-decoration:none; color:#2563eb; font-weight:700;"><i class="fa fa-clock-rotate-left"></i> Activity Logs</a>
        </div>

        <!-- 3-Column Profile Cards -->
        <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:20px; margin-top:16px;">
            <!-- Card 1: Student Info -->
            <div style="background:#fff; border:1px solid #e5e7eb; border-radius:0.75rem; overflow:hidden; display:flex; flex-direction:column;">
                <div style="background:#f97316; padding:10px 16px; color:#fff; font-weight:700; font-size:12px; display:flex; align-items:center; gap:8px;">
                    <i class="fa fa-user"></i> Student Information
                </div>
                <div style="padding:16px; font-size:12px; flex:1;">
                    <div style="display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid #f3f4f6;"><span style="color:#6b7280;">Father Name</span><span id="cardFatherName" style="font-weight:600; color:#1e293b;">--</span></div>
                    <div style="display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid #f3f4f6;"><span style="color:#6b7280;">Father Occupation</span><span style="color:#9ca3af;">--</span></div>
                    <div style="display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid #f3f4f6;"><span style="color:#6b7280;">Mother Name</span><span id="cardMotherName" style="color:#9ca3af;">--</span></div>
                    <div style="display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid #f3f4f6;"><span style="color:#6b7280;">Family Code</span><span id="cardFamilyCode" style="color:#9ca3af;">--</span></div>
                    <div style="display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid #f3f4f6;"><span style="color:#6b7280;">Religion</span><span id="cardReligion" style="font-weight:600; color:#1e293b; text-transform:uppercase;">--</span></div>
                    <div style="display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid #f3f4f6;"><span style="color:#6b7280;">B-Form</span><span id="cardBForm" style="color:#9ca3af;">--</span></div>
                    <div style="display:flex; justify-content:space-between; padding:6px 0;"><span style="color:#6b7280;">Date of Birth</span><span id="cardDOB" style="font-weight:600; color:#1e293b;">--</span></div>
                </div>
            </div>
            <!-- Card 2: Contact -->
            <div style="background:#fff; border:1px solid #e5e7eb; border-radius:0.75rem; overflow:hidden; display:flex; flex-direction:column;">
                <div style="background:#10b981; padding:10px 16px; color:#fff; font-weight:700; font-size:12px; display:flex; align-items:center; gap:8px;">
                    <i class="fa fa-address-book"></i> Contact & Address Details
                </div>
                <div style="padding:16px; font-size:12px; flex:1;">
                    <div style="display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid #f3f4f6;"><span style="color:#6b7280;">Cell Number</span><span id="cardCellNumber" style="font-weight:600; color:#1e293b;">--</span></div>
                    <div style="display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid #f3f4f6;"><span style="color:#6b7280;">WhatsApp</span><span id="cardWhatsApp" style="color:#9ca3af;">--</span></div>
                    <div style="display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid #f3f4f6;"><span style="color:#6b7280;">Father Contact</span><span id="cardFatherCell" style="color:#9ca3af;">--</span></div>
                    <div style="display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid #f3f4f6;"><span style="color:#6b7280;">Mother Contact</span><span id="cardMotherCell" style="color:#9ca3af;">--</span></div>
                    <div style="display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid #f3f4f6;"><span style="color:#6b7280;">Home Contact</span><span id="cardHomeNumber" style="color:#9ca3af;">--</span></div>
                    <div style="display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid #f3f4f6;"><span style="color:#6b7280;">Address</span><span id="cardAddress" style="color:#9ca3af;">--</span></div>
                    <div style="display:flex; justify-content:space-between; padding:6px 0;"><span style="color:#6b7280;">Locality</span><span id="cardLocality" style="color:#9ca3af;">--</span></div>
                </div>
            </div>
            <!-- Card 3: Mobile App -->
            <div style="background:#fff; border:1px solid #e5e7eb; border-radius:0.75rem; overflow:hidden; display:flex; flex-direction:column;">
                <div style="background:#8b5cf6; padding:10px 16px; color:#fff; font-weight:700; font-size:12px; display:flex; align-items:center; gap:8px;">
                    <i class="fa fa-mobile"></i> Mobile App Login Details
                </div>
                <div style="padding:16px; font-size:12px; flex:1;">
                    <div style="background:#f5f3ff; border:1px solid #ede9fe; border-radius:6px; padding:8px; color:#6d28d9; font-size:11px; display:flex; align-items:center; gap:8px; margin-bottom:12px;">
                        <i class="fa fa-circle-info" style="color:#7c3aed;"></i>
                        <span>Parent app login credentials for this account.</span>
                    </div>
                    <div style="display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid #f3f4f6;"><span style="color:#6b7280;">Username</span><span style="color:#9ca3af;">--</span></div>
                    <div style="display:flex; justify-content:space-between; padding:6px 0;"><span style="color:#6b7280;">Password</span><span style="color:#9ca3af;">--</span></div>
                </div>
            </div>
        </div>
    </section>

</div><!-- /wizard-body -->

<!-- Camera Capture Modal -->
<div id="cameraModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:9999; align-items:center; justify-content:center;">
    <div style="background:#fff; border-radius:12px; max-width:400px; width:90%; overflow:hidden;">
        <div style="padding:16px; border-bottom:1px solid #e5e7eb; display:flex; justify-content:space-between; align-items:center;">
            <h4 style="margin:0; font-size:15px; font-weight:700;"><i class="fa fa-video"></i> Capture Photo</h4>
            <button onclick="closeCameraModal()" style="background:none; border:none; font-size:20px; cursor:pointer; color:#6b7280;">&times;</button>
        </div>
        <div style="padding:16px; text-align:center;">
            <div style="position:relative; max-width:300px; margin:0 auto;">
                <video id="cameraVideo" autoplay playsinline style="width:100%; border-radius:12px; background:#111; object-fit:cover;"></video>
                <div style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); width:58%; height:82%; border:3px dashed #f97316; border-radius:10px; pointer-events:none;"></div>
            </div>
            <canvas id="cameraCanvas" style="display:none;"></canvas>
            <img id="cameraPreview" style="display:none; max-width:200px; border-radius:12px; border:2px solid #f97316; margin:10px auto;">
        </div>
        <div style="padding:12px 16px; border-top:1px solid #e5e7eb; display:flex; justify-content:flex-end; gap:8px;">
            <button onclick="closeCameraModal()" style="padding:8px 16px; border:1px solid #d1d5db; border-radius:6px; font-size:12px; cursor:pointer;">Close</button>
            <button id="btnCapture" onclick="capturePhoto()" style="padding:8px 16px; background:#f97316; color:#fff; border:none; border-radius:6px; font-size:12px; cursor:pointer; font-weight:600;"><i class="fa fa-camera"></i> Capture</button>
            <button id="btnUseCapture" onclick="useCapturedPhoto()" style="display:none; padding:8px 16px; background:#10b981; color:#fff; border:none; border-radius:6px; font-size:12px; cursor:pointer; font-weight:600;"><i class="fa fa-check"></i> Use This Photo</button>
        </div>
    </div>
</div>

<script>
var HIIFI_BASE = '<?php echo BASE_URL; ?>';
var currentWizardStep = 1;
var savedStudentId = 0;

var studentState = {
    name: '', fatherName: '', cell: '', session: '', classSec: '', grNo: '',
    gender: '', religion: '', dob: '', doa: '', familyCode: '',
    motherName: '', address: '', whatsapp: '', fatherCell: '', motherCell: '',
    homeNumber: '', locality: '', uploadedImageData: null
};

function activateInnerTab(btn, paneId) {
    document.querySelectorAll('.inner-tab-btn').forEach(function(b) {
        b.classList.remove('active');
        b.querySelectorAll('i').forEach(function(i){ i.style.color = '#9ca3af'; });
    });
    btn.classList.add('active');
    btn.querySelector('i').style.color = '#f97316';
    document.querySelectorAll('.wizard-pane').forEach(function(p) { p.classList.remove('active'); });
    document.getElementById('pane-' + paneId).classList.add('active');
}

function goToWizardStep(step) {
    document.getElementById('step1View').style.display = 'none';
    document.getElementById('step2View').style.display = 'none';
    document.getElementById('step3View').style.display = 'none';
    document.getElementById('profileView').style.display = 'none';
    document.getElementById('stepProgressContainer').style.display = '';

    currentWizardStep = step;
    var bc = document.getElementById('bcCurrent');
    var pl = document.getElementById('progressLine');

    var steps = [1,2,3];
    steps.forEach(function(s) {
        var c = document.getElementById('ws'+s+'c');
        var l = document.getElementById('ws'+s+'l');
        c.style.background = '#e5e7eb';
        c.style.border = '2px solid #fff';
        c.style.color = '#6b7280';
        c.style.boxShadow = '0 1px 3px rgba(0,0,0,.08)';
        c.innerHTML = s;
        l.style.color = '#9ca3af';
    });

    if (step === 1) {
        document.getElementById('step1View').style.display = '';
        bc.textContent = 'Add New Student';
        pl.style.width = '0%';
        var c1 = document.getElementById('ws1c');
        var l1 = document.getElementById('ws1l');
        c1.style.background = '#f97316';
        c1.style.border = 'none';
        c1.style.color = '#fff';
        c1.style.boxShadow = '0 2px 6px rgba(249,115,22,.3)';
        l1.style.color = '#ea580c';
    } else if (step === 2) {
        document.getElementById('step2View').style.display = '';
        bc.textContent = 'Create Fee Plan';
        pl.style.width = '50%';
        var c1 = document.getElementById('ws1c');
        var l1 = document.getElementById('ws1l');
        c1.style.background = '#f97316';
        c1.style.border = 'none';
        c1.style.color = '#fff';
        c1.innerHTML = '<i class="fa fa-check" style="font-size:12px;"></i>';
        l1.style.color = '#ea580c';
        var c2 = document.getElementById('ws2c');
        var l2 = document.getElementById('ws2l');
        c2.style.background = '#f97316';
        c2.style.border = 'none';
        c2.style.color = '#fff';
        c2.style.boxShadow = '0 0 0 4px rgba(249,115,22,.15)';
        l2.style.color = '#ea580c';
    } else if (step === 3) {
        document.getElementById('step3View').style.display = '';
        bc.textContent = 'Finish';
        pl.style.width = '100%';
        [1,2].forEach(function(s) {
            var c = document.getElementById('ws'+s+'c');
            var l = document.getElementById('ws'+s+'l');
            c.style.background = '#f97316';
            c.style.border = 'none';
            c.style.color = '#fff';
            c.innerHTML = '<i class="fa fa-check" style="font-size:12px;"></i>';
            l.style.color = '#ea580c';
        });
        var c3 = document.getElementById('ws3c');
        var l3 = document.getElementById('ws3l');
        c3.style.background = '#f97316';
        c3.style.border = 'none';
        c3.style.color = '#fff';
        c3.style.boxShadow = '0 0 0 4px rgba(249,115,22,.15)';
        l3.style.color = '#ea580c';
    }
    window.scrollTo({top:0, behavior:'smooth'});
}

function getSection(cid) {
    var sel = document.getElementById('txt_section');
    sel.innerHTML = '<option value="">Loading...</option>';
    var xhr = new XMLHttpRequest();
    xhr.open('GET', HIIFI_BASE + 'ajax_get_sections.php?class_id=' + encodeURIComponent(cid));
    xhr.onload = function() {
        var data = JSON.parse(xhr.responseText || '[]');
        sel.innerHTML = '<option value="">Select Section</option>';
        data.forEach(function(s) {
            var o = document.createElement('option');
            o.value = s.section_id;
            o.textContent = s.section_name;
            sel.appendChild(o);
        });
    };
    xhr.send();
}

function submitStep1() {
    var form = document.getElementById('studentForm');
    if (!form.checkValidity()) { form.reportValidity(); return; }

    var fd = new FormData(form);
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'add_student.php', true);
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                var data = JSON.parse(xhr.responseText);
                if (data.ok && data.student_id) {
                    savedStudentId = data.student_id;
                    collectStudentState();
                    studentState.grNo = data.gr_no || 'Saved';
                    populateStep2();
                    goToWizardStep(2);
                    return;
                }
                if (data.error) {
                    alert(data.error);
                    return;
                }
            } catch(e) {}
            // Fallback: try to extract student_id from response text
            var resp = xhr.responseText;
            var match = resp.match(/student_id[=:]\s*(\d+)/i);
            if (match) {
                savedStudentId = parseInt(match[1]);
                collectStudentState();
                populateStep2();
                goToWizardStep(2);
                return;
            }
            alert('Error saving student. Please check all required fields.');
        }
    };
    xhr.send(fd);
}

function collectStudentState() {
    studentState.name = document.getElementById('fname').value || '';
    studentState.fatherName = document.getElementById('last_name').value || '';
    studentState.cell = document.getElementById('cell_no').value || '';
    studentState.session = document.getElementById('session').value || '';
    var clsEl = document.getElementById('class');
    var secEl = document.getElementById('txt_section');
    var clsName = clsEl.options[clsEl.selectedIndex] ? clsEl.options[clsEl.selectedIndex].text : '';
    var secName = secEl.options[secEl.selectedIndex] ? secEl.options[secEl.selectedIndex].text : '';
    studentState.classSec = clsName + (secName ? ' - ' + secName : '');
    studentState.grNo = 'Saved';
    studentState.gender = document.getElementById('gender').value || '';
    studentState.religion = document.getElementById('religion').value || '';
    studentState.dob = document.getElementById('dob').value || '';
    studentState.doa = document.getElementById('date_of_adms').value || '';
    studentState.motherName = document.getElementById('mother_name') ? document.getElementById('mother_name').value : '';
    studentState.whatsapp = document.getElementById('whatsapp_number') ? document.getElementById('whatsapp_number').value : '';
    studentState.fatherCell = document.getElementById('father_cellno') ? document.getElementById('father_cellno').value : '';
    studentState.motherCell = document.getElementById('mother_cell') ? document.getElementById('mother_cell').value : '';
    studentState.homeNumber = document.getElementById('home_number') ? document.getElementById('home_number').value : '';
    studentState.address = document.getElementById('address') ? document.getElementById('address').value : '';
    studentState.locality = '';
    var locEl = document.getElementById('locality');
    if (locEl && locEl.selectedIndex > 0) studentState.locality = locEl.options[locEl.selectedIndex].text;
}

function populateStep2() {
    document.getElementById('step2StudentName').textContent = studentState.name || '--';
    document.getElementById('step2StudentClass').textContent = studentState.classSec || '--';
    document.getElementById('step2StudentCell').textContent = studentState.cell || '--';
}

function saveFeePlan() {
    if (!savedStudentId) { alert('No student saved yet.'); return; }
    goToWizardStep(3);
}

function viewProfile() {
    document.getElementById('stepProgressContainer').style.display = 'none';
    document.getElementById('step1View').style.display = 'none';
    document.getElementById('step2View').style.display = 'none';
    document.getElementById('step3View').style.display = 'none';
    document.getElementById('profileView').style.display = '';
    document.getElementById('bcCurrent').textContent = 'Student Profile';

    document.getElementById('profName').textContent = studentState.name || '--';
    document.getElementById('profClassSec').textContent = studentState.classSec || '--';
    document.getElementById('profGRNo').textContent = studentState.grNo || '--';
    document.getElementById('profSession').textContent = studentState.session || '--';
    document.getElementById('profGender').textContent = studentState.gender || '--';
    document.getElementById('profDOA').textContent = studentState.doa || '--';
    document.getElementById('cardFatherName').textContent = studentState.fatherName || '--';
    document.getElementById('cardMotherName').textContent = studentState.motherName || '--';
    document.getElementById('cardReligion').textContent = studentState.religion || '--';
    document.getElementById('cardDOB').textContent = studentState.dob || '--';
    document.getElementById('cardCellNumber').textContent = studentState.cell || '--';
    document.getElementById('cardWhatsApp').textContent = studentState.whatsapp || '--';
    document.getElementById('cardFatherCell').textContent = studentState.fatherCell || '--';
    document.getElementById('cardMotherCell').textContent = studentState.motherCell || '--';
    document.getElementById('cardHomeNumber').textContent = studentState.homeNumber || '--';
    document.getElementById('cardAddress').textContent = studentState.address || '--';
    document.getElementById('cardLocality').textContent = studentState.locality || '--';

    if (studentState.uploadedImageData) {
        document.getElementById('profAvatarImg').src = studentState.uploadedImageData;
    }

    window.scrollTo({top:0, behavior:'smooth'});
}

function resetWizard() {
    savedStudentId = 0;
    studentState = {
        name: '', fatherName: '', cell: '', session: '', classSec: '', grNo: '',
        gender: '', religion: '', dob: '', doa: '', familyCode: '',
        motherName: '', address: '', whatsapp: '', fatherCell: '', motherCell: '',
        homeNumber: '', locality: '', uploadedImageData: null
    };
    var form = document.getElementById('studentForm');
    if (form) form.reset();
    document.querySelectorAll('.fh-check').forEach(function(cb) { cb.checked = false; });
}

// Fee total calculator removed with table

// Document preview
function previewStudentDoc(input, index) {
    var card = document.getElementById('docCard_' + index);
    var thumb = document.getElementById('docThumb_' + index);
    var status = document.getElementById('docStatus_' + index);
    var fileName = document.getElementById('docFileName_' + index);
    var file = input.files && input.files[0];
    if (!file) return;
    fileName.textContent = file.name;
    status.textContent = 'Uploaded';
    status.style.color = '#16a34a';
    card.style.borderColor = '#16a34a';
    if (file.type === 'application/pdf') {
        thumb.innerHTML = '<i class="fa fa-file-pdf-o" style="font-size:30px; color:#dc2626;"></i>';
    } else {
        var reader = new FileReader();
        reader.onload = function(e) { thumb.innerHTML = '<img src="' + e.target.result + '" style="width:100%;height:100%;object-fit:cover;border-radius:8px;">'; };
        reader.readAsDataURL(file);
    }
}

// Photo upload preview
(function() {
    var photoPreview = document.getElementById('profAvatarImg');
    var fileInput = document.getElementById('fileInput_global');
})();

// Camera functions
function openCameraModal() {
    document.getElementById('cameraModal').style.display = 'flex';
    var video = document.getElementById('cameraVideo');
    if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
        navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' } })
            .then(function(stream) { video.srcObject = stream; video.play(); })
            .catch(function() { alert('Camera not available.'); });
    } else {
        alert('Camera not supported in this browser.');
    }
}

function closeCameraModal() {
    document.getElementById('cameraModal').style.display = 'none';
    var video = document.getElementById('cameraVideo');
    if (video.srcObject) {
        video.srcObject.getTracks().forEach(function(t) { t.stop(); });
        video.srcObject = null;
    }
}

function capturePhoto() {
    var video = document.getElementById('cameraVideo');
    var canvas = document.getElementById('cameraCanvas');
    var ctx = canvas.getContext('2d');
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    ctx.drawImage(video, 0, 0);
    var preview = document.getElementById('cameraPreview');
    preview.src = canvas.toDataURL('image/jpeg');
    preview.style.display = 'block';
    document.getElementById('btnUseCapture').style.display = 'inline-block';
}

function useCapturedPhoto() {
    var preview = document.getElementById('cameraPreview');
    if (preview.src) {
        studentState.uploadedImageData = preview.src;
        document.getElementById('profAvatarImg').src = preview.src;
        var capturedInp = document.getElementById('captured_image');
        if (capturedInp) capturedInp.value = preview.src;
        closeCameraModal();
    }
}

// Family auto-fill
(function() {
    var fs = document.getElementById('family_search');
    if (!fs) return;
    fs.addEventListener('change', function() {
        var code = this.value;
        document.getElementById('family_code_value').value = code || '';
        if (!code) return;
        var xhr = new XMLHttpRequest();
        xhr.open('GET', HIIFI_BASE + 'ajax_get_family_by_code.php?code=' + encodeURIComponent(code));
        xhr.onload = function() {
            var data;
            try { data = JSON.parse(xhr.responseText); } catch(e) { return; }
            if (!data) return;
            var map = {
                'last_name': data.father_name,
                'cnic': data.father_cnic,
                'father_qualification': data.father_qualification,
                'father_occupation': data.father_occupation,
                'Fbusiness_address': data.father_business_address,
                'father_income': data.father_income,
                'mother_name': data.mother_name,
                'mother_cnic': data.mother_cnic,
                'mother_qualification': data.mother_qualification,
                'mother_activity': data.mother_activity,
                'mother_designation': data.mother_designation,
                'address': data.address,
                'gardian_name': data.guardian_name,
                'gardian_cnic': data.guardian_cnic,
                'gardian_no': data.guardian_cellno,
                'gardian_qualification': data.guardian_qualification,
                'gardian_occupation': data.guardian_occupation,
                'gardian_income': data.guardian_income,
                'gardian_email': data.guardian_email,
                'gardian_address': data.guardian_address
            };
            Object.keys(map).forEach(function(id) {
                var el = document.getElementById(id);
                if (el && map[id] !== null && map[id] !== undefined) {
                    if (el.tagName === 'SELECT') {
                        if (el.querySelector('option[value="' + map[id] + '"]')) el.value = map[id];
                    } else {
                        el.value = map[id];
                    }
                }
            });
            if (data.locality_id) {
                var loc = document.getElementById('locality');
                if (loc) loc.value = data.locality_id;
            }
        };
        xhr.send();
    });
})();
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>

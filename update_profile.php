<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Update Profile';

$message = '';
$error = '';

$user = db_query("SELECT * FROM users WHERE user_id=" . (int) $_SESSION['user_id'])->fetch_assoc();

$cols = [];
$res = db_query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '" . DB_NAME . "' AND TABLE_NAME = 'employees'");
if ($res) { while ($row = $res->fetch_assoc()) { $cols[$row['COLUMN_NAME']] = true; } }
foreach (['father_name', 'qualification', 'cnic', 'marital_status'] as $need) {
    if (!isset($cols[$need])) {
        $type = ($need === 'marital_status') ? "VARCHAR(50) NOT NULL DEFAULT ''" : "VARCHAR(255) NOT NULL DEFAULT ''";
        db_query("ALTER TABLE employees ADD COLUMN `$need` $type");
    }
}

$emp = null;
if (!empty($user['email'])) {
    $se = db_prepare("SELECT * FROM employees WHERE email=? LIMIT 1");
    $se->bind_param('s', $user['email']);
    $se->execute();
    $r = $se->get_result();
    $emp = $r->fetch_assoc();
}
if (!$emp) {
    $emp = ['emp_id' => 0, 'first_name' => $user['full_name'], 'last_name' => '', 'father_name' => '', 'qualification' => '', 'phone' => '', 'cnic' => '', 'dob' => '', 'marital_status' => '', 'address' => ''];
}

$maritalOptions = ['', 'Married', 'Widowed', 'Separated', 'Divorced', 'Single'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'UpdateEmployeeProfile') {
    $full_name = trim($_POST['fname'] ?? '');
    $father_name = trim($_POST['lname'] ?? '');
    $qualification = trim($_POST['qualification'] ?? '');
    $cell_no = trim($_POST['cell_no'] ?? '');
    $cnic = trim($_POST['cnic'] ?? '');
    $dob = trim($_POST['dob'] ?? '');
    $marital_status = trim($_POST['marital_status'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $emp_id = (int) ($_POST['emp'] ?? 0);

    if ($full_name === '') {
        $error = 'Employee Name is required.';
    } elseif ($qualification === '') {
        $error = 'Qualification is required.';
    } elseif ($cell_no === '') {
        $error = 'Cell No is required.';
    } else {
        $dobVal = ($dob === '' || $dob === '0000-00-00') ? null : $dob;
        $roleLbl = $user['role'] ? ucfirst(str_replace('_', ' ', $user['role'])) : 'Staff';

        $conn = db_connect();
        if ($emp_id > 0) {
            $st2 = $conn->prepare("UPDATE employees SET first_name=?, father_name=?, qualification=?, phone=?, cnic=?, dob=?, marital_status=?, address=? WHERE emp_id=?");
            $st2->bind_param('ssssssssi', $full_name, $father_name, $qualification, $cell_no, $cnic, $dobVal, $marital_status, $address, $emp_id);
            $st2->execute();
        } else {
            $st2 = $conn->prepare("INSERT INTO employees (first_name, last_name, email, phone, designation, department, dob, salary, address, status, photo, created_at, father_name, qualification, cnic, marital_status) VALUES (?, '', ?, ?, ?, '', ?, 0, ?, 1, '', NOW(), ?, ?, ?, ?)");
            $st2->bind_param('ssssssssss', $full_name, $user['email'], $cell_no, $roleLbl, $dobVal, $address, $father_name, $qualification, $cnic, $marital_status);
            $st2->execute();
            $emp_id = (int) $st2->insert_id;
        }

        $st3 = $conn->prepare("UPDATE users SET full_name=?, email=? WHERE user_id=?");
        $st3->bind_param('ssi', $full_name, $user['email'], $user['user_id']);
        $st3->execute();
        $_SESSION['full_name'] = $full_name;

        $emp['emp_id'] = $emp_id;
        $emp['first_name'] = $full_name;
        $emp['father_name'] = $father_name;
        $emp['qualification'] = $qualification;
        $emp['phone'] = $cell_no;
        $emp['cnic'] = $cnic;
        $emp['dob'] = $dobVal;
        $emp['marital_status'] = $marital_status;
        $emp['address'] = $address;
        $user['full_name'] = $full_name;
        $message = 'Profile updated successfully!';
    }
}

include __DIR__ . '/includes/header.php';
?>
<div class="main-content">
    <div class="container-fluid">
        <?php if ($message): ?><div class="alert alert-success"><?php echo e($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>

        <div style="display:flex; align-items:center; justify-content:space-between; padding:14px 4px;">
            <h3 style="font-size:18px; font-weight:800; color:#111827; margin:0;"><i class="fa fa-user"></i> Update Employee Information</h3>
            <a href="<?php echo BASE_URL; ?>update_pswd.php" class="btn btn-warning" style="color:#fff;"><i class="fa fa-key"></i> Change Password</a>
        </div>

        <form method="post" action="update_profile.php" style="max-width:680px; background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:22px;">
            <input type="hidden" name="action" value="UpdateEmployeeProfile">
            <input type="hidden" name="emp" value="<?php echo (int) $emp['emp_id']; ?>">
            <div class="row">
                <div class="form-group col-md-6">
                    <label class="required">Employee Name</label>
                    <input type="text" name="fname" class="form-control" value="<?php echo e($emp['first_name']); ?>" required>
                </div>
                <div class="form-group col-md-6">
                    <label>Father / Husband Name</label>
                    <input type="text" name="lname" class="form-control" value="<?php echo e($emp['father_name']); ?>">
                </div>
                <div class="form-group col-md-6">
                    <label class="required">Qualification</label>
                    <input type="text" name="qualification" class="form-control" value="<?php echo e($emp['qualification']); ?>" required>
                </div>
                <div class="form-group col-md-6">
                    <label class="required">Cell No</label>
                    <input type="text" name="cell_no" class="form-control" value="<?php echo e($emp['phone']); ?>" required>
                </div>
                <div class="form-group col-md-6">
                    <label>CNIC</label>
                    <input type="text" name="cnic" class="form-control" maxlength="15" placeholder="00000-0000000-0" value="<?php echo e($emp['cnic']); ?>">
                </div>
                <div class="form-group col-md-6">
                    <label>Date of Birth</label>
                    <input type="date" name="dob" class="form-control" value="<?php echo ($emp['dob'] && $emp['dob'] !== '0000-00-00') ? e($emp['dob']) : ''; ?>">
                </div>
                <div class="form-group col-md-6">
                    <label>Marital Status</label>
                    <select name="marital_status" class="form-control">
                        <?php foreach ($maritalOptions as $opt): ?>
                            <option value="<?php echo e($opt); ?>" <?php echo $emp['marital_status'] === $opt ? 'selected' : ''; ?>><?php echo $opt === '' ? 'Select Status' : e($opt); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-6">
                    <label>Address</label>
                    <input type="text" name="address" class="form-control" value="<?php echo e($emp['address']); ?>">
                </div>
            </div>
            <button type="submit" class="btn btn-success" style="padding:10px 30px;"><i class="fa fa-save"></i> Update Record</button>
        </form>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Old Employees';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'Rehire') {
    $emp_id = (int) ($_POST['emp_id'] ?? 0);
    if ($emp_id > 0) {
        $st2 = db_prepare("UPDATE employees SET status=1 WHERE emp_id=?");
        $st2->bind_param('i', $emp_id);
        $st2->execute();
        $message = 'Employee reactivated successfully!';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'Deactivate') {
    $emp_id = (int) ($_POST['emp_id'] ?? 0);
    if ($emp_id > 0) {
        $st2 = db_prepare("UPDATE employees SET status=0 WHERE emp_id=?");
        $st2->bind_param('i', $emp_id);
        $st2->execute();
        $message = 'Employee deactivated (moved to old employees).';
    }
}

$employees = [];
$res = db_query("SELECT * FROM employees WHERE status=0 ORDER BY emp_id DESC");
while ($row = $res->fetch_assoc()) { $employees[] = $row; }

include __DIR__ . '/includes/header.php';
?>
<div class="main-content">
    <div class="container-fluid">
        <?php if ($message): ?><div class="alert alert-success"><?php echo e($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>

        <div style="display:flex; align-items:center; justify-content:space-between; padding:14px 4px;">
            <h3 style="font-size:18px; font-weight:800; color:#111827; margin:0;"><i class="fa fa-user-times"></i> List Of Old Employee</h3>
            <a href="<?php echo BASE_URL; ?>staff_security.php" class="btn btn-primary" style="color:#fff;"><i class="fa fa-users"></i> Active Employees</a>
        </div>

        <div style="overflow-x:auto; background:#fff; border:1px solid #E5E7EB; border-radius:14px;">
            <table class="table table-striped table-bordered" style="width:100%; background:#fff; margin-bottom:0;">
                <thead><tr><th>#</th><th>Employee</th><th>Designation</th><th>Department</th><th>Phone</th><th>Left/Old</th><th></th></tr></thead>
                <tbody>
                    <?php if (count($employees) === 0): ?><tr><td colspan="7" style="text-align:center; color:#6B7280; padding:25px;">Koi old employee nahi. Kisi active employee ko deactivate karnay se yeh list mein aayega.</td></tr><?php endif; ?>
                    <?php foreach ($employees as $e): ?>
                        <tr>
                            <td><?php echo $e['emp_id']; ?></td>
                            <td><strong><?php echo e($e['first_name'] . ' ' . $e['last_name']); ?></strong><br><small style="color:#6B7280;"><?php echo e($e['email'] ?? ''); ?></small></td>
                            <td><?php echo e($e['designation'] ?? '-'); ?></td>
                            <td><?php echo e($e['department'] ?? '-'); ?></td>
                            <td><?php echo e($e['phone'] ?? '-'); ?></td>
                            <td><span class="status-badge status-absent">Inactive</span></td>
                            <td>
                                <form method="post" action="old_employee.php" style="display:inline;">
                                    <input type="hidden" name="action" value="Rehire">
                                    <input type="hidden" name="emp_id" value="<?php echo $e['emp_id']; ?>">
                                    <button class="btn btn-success btn-xs"><i class="fa fa-undo"></i> Rehire</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
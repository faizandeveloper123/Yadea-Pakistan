<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Manage Occupations';

try {
    db_query("CREATE TABLE IF NOT EXISTS occupations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(191) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");
} catch (Throwable $ex) {}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'AddOccupation') {
        $name = trim($_POST['occupation_name'] ?? '');
        if ($name === '') {
            $error = 'Occupation name is required.';
        } else {
            $st2 = db_prepare("INSERT INTO occupations (name) VALUES (?)");
            $st2->bind_param('s', $name);
            $st2->execute();
            $message = 'Occupation saved successfully!';
        }
    }

    if ($action === 'UpdateOccupation') {
        $oid = (int) ($_POST['occupation_id'] ?? 0);
        $name = trim($_POST['occupation_name'] ?? '');
        if ($name === '') {
            $error = 'Occupation name is required.';
        } else {
            $st2 = db_prepare("UPDATE occupations SET name=? WHERE id=?");
            $st2->bind_param('si', $name, $oid);
            $st2->execute();
            $message = 'Occupation updated successfully!';
        }
    }

    if ($action === 'DeleteOccupation') {
        $oid = (int) ($_POST['occupation_id'] ?? 0);
        $st2 = db_prepare("DELETE FROM occupations WHERE id=?");
        $st2->bind_param('i', $oid);
        $st2->execute();
        $message = 'Occupation deleted successfully!';
    }
}

$occupations = [];
$res = db_query("SELECT * FROM occupations ORDER BY name");
while ($row = $res->fetch_assoc()) { $occupations[] = $row; }

include __DIR__ . '/includes/header.php';
?>
<style>
.form-lbl { font-size: 13px; font-weight: 600; color: #4a5568; margin-bottom: 4px; }
.required::after { content: ' *'; color: #e53e3e; }
</style>

<div class="main-content">
    <div class="container-fluid">
        <?php if ($message): ?><div class="alert alert-success"><?php echo e($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>

        <div style="display:flex; align-items:center; justify-content:space-between; padding:14px 4px; flex-wrap:wrap; gap:10px;">
            <h3 style="font-size:18px; font-weight:800; color:#111827; margin:0;"><i class="fa fa-briefcase"></i> Manage Occupations <span style="font-size:14px; color:#6B7280;">(<?php echo count($occupations); ?> records)</span></h3>
            <div style="display:flex; gap:8px;">
                <a href="<?php echo BASE_URL; ?>manage_localities.php" class="btn btn-info" style="color:#fff;"><i class="fa fa-map-marker"></i> Manage Localities</a>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4">
                <form method="post" action="manage_occupations.php" style="background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:16px;">
                    <h4 style="font-size:15px; font-weight:800; margin:0 0 14px;"><i class="fa fa-plus" style="color:#48bb78;"></i> Add Occupation</h4>
                    <input type="hidden" name="action" value="AddOccupation">
                    <div class="form-group">
                        <label class="form-lbl required">Add New Occupation</label>
                        <input type="text" name="occupation_name" class="form-control" placeholder="Enter Occupation Name..." maxlength="100" autofocus required>
                    </div>
                    <button type="submit" class="btn btn-success" style="width:100%;"><i class="fa fa-check"></i> Save Occupation</button>
                </form>
            </div>

            <div class="col-md-8">
                <div style="overflow-x:auto; background:#fff; border:1px solid #E5E7EB; border-radius:14px;">
                    <table class="table table-striped table-bordered" style="width:100%; background:#fff; margin-bottom:0;">
                        <thead>
                            <tr><th style="width:1%; text-align:center;">S.No</th><th>Occupation Name</th><th style="width:140px;">Action</th></tr>
                        </thead>
                        <tbody>
                            <?php if (count($occupations) === 0): ?>
                                <tr><td colspan="3" style="text-align:center; color:#6B7280; padding:36px;"><i class="fa fa-briefcase" style="font-size:36px; display:block; margin-bottom:10px;"></i>No occupations added yet. Use the form to save your first record.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($occupations as $i => $o): ?>
                                <tr>
                                    <td style="text-align:center;"><?php echo $i + 1; ?></td>
                                    <td><strong><?php echo e($o['name']); ?></strong></td>
                                    <td>
                                        <button class="btn btn-success btn-xs edit-o" data-id="<?php echo $o['id']; ?>" data-name="<?php echo e($o['name']); ?>"><i class="fa fa-pencil"></i></button>
                                        <form method="post" action="manage_occupations.php" style="display:inline;" onsubmit="return confirm('Delete this occupation?');">
                                            <input type="hidden" name="action" value="DeleteOccupation">
                                            <input type="hidden" name="occupation_id" value="<?php echo $o['id']; ?>">
                                            <button class="btn btn-danger btn-xs"><i class="fa fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="editOccupationModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius:14px;">
            <div class="modal-header" style="border-bottom:1px solid #E5E7EB; padding:16px 20px;">
                <h4 class="modal-title" style="font-weight:800; font-size:16px;"><i class="fa fa-pencil" style="color:#ed8936;"></i> Edit Occupation</h4>
            </div>
            <form method="post" action="manage_occupations.php">
                <div class="modal-body" style="padding:20px;">
                    <input type="hidden" name="action" value="UpdateOccupation">
                    <input type="hidden" name="occupation_id" id="eoId">
                    <div class="form-group">
                        <label class="form-lbl required">Occupation Name</label>
                        <input type="text" name="occupation_name" id="eoName" class="form-control" maxlength="100" required>
                    </div>
                </div>
                <div class="modal-footer" style="border-top:1px solid #E5E7EB;">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fa fa-check"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.edit-o').forEach(function(btn){
    btn.addEventListener('click', function(){
        document.getElementById('eoId').value = this.dataset.id;
        document.getElementById('eoName').value = this.dataset.name;
        jQuery('#editOccupationModal').modal('show');
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
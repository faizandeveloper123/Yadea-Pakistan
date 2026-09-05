<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Manage Boards';

try {
    db_query("CREATE TABLE IF NOT EXISTS boards (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(191) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");
} catch (Throwable $ex) {}

$message = '';
$error = '';

$edit_row = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'AddBoard') {
        $name = trim($_POST['name'] ?? '');
        if ($name === '') { $error = 'Name is required.'; }
        else {
            $st = db_prepare("INSERT INTO boards (name) VALUES (?)");
            $st->bind_param('s', $name);
            $st->execute();
            $message = 'Board saved successfully!';
        }
    }

    if ($action === 'UpdateBoard') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        if ($name === '') { $error = 'Name is required.'; }
        else {
            $st = db_prepare("UPDATE boards SET name=? WHERE id=?");
            $st->bind_param('si', $name, $id);
            $st->execute();
            $message = 'Board updated successfully!';
        }
    }

    if ($action === 'DeleteBoard') {
        $id = (int) ($_POST['id'] ?? 0);
        $st = db_prepare("DELETE FROM boards WHERE id=?");
        $st->bind_param('i', $id);
        $st->execute();
        $message = 'Board deleted successfully!';
    }
}

if (isset($_GET['edit'])) {
    $eid = (int) $_GET['edit'];
    $st = db_prepare("SELECT * FROM boards WHERE id=?");
    $st->bind_param('i', $eid);
    $st->execute();
    $edit_row = $st->get_result()->fetch_assoc();
}

$rows = [];
$res = db_query("SELECT * FROM boards ORDER BY name");
while ($row = $res->fetch_assoc()) { $rows[] = $row; }

include __DIR__ . '/includes/header.php';
?>
<style>
.crud-card { background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:20px; }
.crud-table th { font-size:12px; text-transform:uppercase; color:#8A99A8; font-weight:700; border-bottom:1px solid #E6E9ED; padding:10px 14px; }
.crud-table td { padding:9px 14px; font-size:13.5px; border-bottom:1px solid #EEF1F4; vertical-align:middle; }
</style>
<div class="main-content">
    <div class="container-fluid">
        <div style="display:flex; align-items:center; justify-content:space-between; padding:14px 4px;">
            <h3 style="font-size:18px; font-weight:800; color:#111827; margin:0;"><i class="fa fa-graduation-cap"></i> Manage Boards / Councils</h3>
            <a href="add_student.php" class="btn btn-primary" style="color:#fff;"><i class="fa fa-arrow-left"></i> Back to Add Student</a>
        </div>

        <?php if ($message): ?><div class="alert alert-success"><?php echo e($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>

        <div class="row">
            <div class="col-md-5">
                <div class="crud-card">
                    <h4 style="font-size:15px; font-weight:700; color:#111827; margin:0 0 14px;">
                        <?php echo $edit_row ? 'Edit Board' : 'Add New Board'; ?>
                    </h4>
                    <form method="post" action="manage_board.php">
                        <?php if ($edit_row): ?>
                            <input type="hidden" name="action" value="UpdateBoard">
                            <input type="hidden" name="id" value="<?php echo (int)$edit_row['id']; ?>">
                        <?php else: ?>
                            <input type="hidden" name="action" value="AddBoard">
                        <?php endif; ?>
                        <div class="form-group">
                            <label>Board / Council Name</label>
                            <input type="text" name="name" class="form-control" required value="<?php echo e($edit_row['name'] ?? ''); ?>" placeholder="e.g. BISE GRW">
                        </div>
                        <button type="submit" class="btn btn-success" style="color:#fff;"><i class="fa fa-save"></i> <?php echo $edit_row ? 'Update' : 'Save'; ?></button>
                        <?php if ($edit_row): ?><a href="manage_board.php" class="btn btn-default">Cancel</a><?php endif; ?>
                    </form>
                </div>
            </div>
            <div class="col-md-7">
                <div class="crud-card" style="overflow-x:auto;">
                    <h4 style="font-size:15px; font-weight:700; color:#111827; margin:0 0 14px;">Board List (<?php echo count($rows); ?>)</h4>
                    <table class="table crud-table" style="width:100%; margin:0;">
                        <thead><tr><th>#</th><th>Name</th><th style="width:100px;">Actions</th></tr></thead>
                        <tbody>
                            <?php if (!$rows): ?>
                                <tr><td colspan="3" style="text-align:center; color:#95A5A6; padding:24px;">No boards yet.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($rows as $i => $r): ?>
                            <tr>
                                <td><?php echo $i + 1; ?></td>
                                <td style="font-weight:600; color:#2A3F54;"><?php echo e($r['name']); ?></td>
                                <td>
                                    <a href="manage_board.php?edit=<?php echo $r['id']; ?>" class="btn btn-primary btn-xs" title="Edit"><i class="fa fa-pencil"></i></a>
                                    <form method="post" action="manage_board.php" style="display:inline;" onsubmit="return confirm('Delete this board?');">
                                        <input type="hidden" name="action" value="DeleteBoard">
                                        <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
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
<?php include __DIR__ . '/includes/footer.php'; ?>

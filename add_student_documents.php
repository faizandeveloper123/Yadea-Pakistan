<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Manage Document Titles';

$error = '';
$success = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            $error = 'Document title cannot be empty.';
        } else {
            $st = db_prepare('INSERT INTO document_titles (name) VALUES (?)');
            $st->bind_param('s', $name);
            $st->execute();
            $success = 'Document title added successfully.';
        }
    } elseif ($action === 'update') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        if ($id <= 0 || $name === '') {
            $error = 'Document title and ID are required.';
        } else {
            $st = db_prepare('UPDATE document_titles SET name = ? WHERE id = ?');
            $st->bind_param('si', $name, $id);
            $st->execute();
            $success = 'Document title updated successfully.';
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            $error = 'Invalid document title ID.';
        } else {
            $st = db_prepare('DELETE FROM document_titles WHERE id = ?');
            $st->bind_param('i', $id);
            $st->execute();
            $success = 'Document title deleted successfully.';
        }
    }
}

// Editing an existing title
$editing = null;
if (isset($_GET['edit']) && (int) $_GET['edit'] > 0) {
    $st = db_prepare('SELECT id, name FROM document_titles WHERE id = ?');
    $st->bind_param('i', $_GET['edit']);
    $st->execute();
    $editing = $st->get_result()->fetch_assoc();
}

$titles = [];
$result = db_query('SELECT id, name, created_at FROM document_titles ORDER BY name');
if ($result) { while ($row = $result->fetch_assoc()) { $titles[] = $row; } }

include __DIR__ . '/includes/header.php';
?>
<style>
.doc-title-th { font-size: 12px; text-transform: uppercase; letter-spacing: .4px; color: #8A99A8; font-weight: 700; border-bottom: 1px solid #E6E9ED; padding: 10px 14px; white-space: nowrap; }
.doc-title-td { padding: 10px 14px; font-size: 13.5px; vertical-align: middle; border-bottom: 1px solid #EEF1F4; }
.doc-title-tr:hover { background: #F7FAFC; }
.title-chip { display: inline-flex; align-items: center; gap: 7px; background: #EAF2F8; color: #3E7CB1; font-size: 12px; font-weight: 600; border-radius: 6px; padding: 5px 11px; }
.title-num { font-family: Consolas, monospace; font-size: 12px; color: #2A3F54; background: #F4F6F8; display: inline-block; padding: 2px 8px; border-radius: 6px; }
.btn-sm-ghost { padding: 5px 11px; font-size: 12px; border-radius: 8px; }
.doc-empty { text-align: center; padding: 48px 20px; color: #95A5A6; }
.doc-empty i { font-size: 40px; color: #D5DBDB; display: block; margin-bottom: 10px; }
</style>

<div class="main-content">
    <div class="container-fluid">
        <div style="display:flex; align-items:center; justify-content:space-between; padding:14px 4px; flex-wrap:wrap; gap:10px;">
            <div>
                <h3 style="font-size:18px; font-weight:800; color:#111827; margin:0;"><i class="fa fa-file-text-o"></i> Manage Document Titles</h3>
                <span style="font-size:12.5px; color:#8A99A8;">Titles shown when uploading student documents</span>
            </div>
            <a href="add_student.php" class="btn btn-sm btn-default"><i class="fa fa-arrow-left"></i> Back to Add Student</a>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success" style="padding:10px 14px; font-size:13px;"><i class="fa fa-check-circle"></i> <?php echo e($success); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger" style="padding:10px 14px; font-size:13px;"><i class="fa fa-exclamation-circle"></i> <?php echo e($error); ?></div>
        <?php endif; ?>

        <div class="row" style="margin:0;">
            <div class="col-md-4" style="padding-left:0;">
                <div style="background:#fff; border:1px solid #E6E9ED; border-radius:14px; padding:18px;">
                    <?php if ($editing): ?>
                        <h4 style="font-size:14px; font-weight:700; color:#2A3F54; margin:0 0 14px;"><i class="fa fa-pencil"></i> Edit Title</h4>
                        <form method="post" action="add_student_documents.php">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="id" value="<?php echo (int) $editing['id']; ?>">
                            <div class="form-group">
                                <label style="font-size:12px; color:#64748B;">Title Name</label>
                                <input type="text" name="name" class="form-control" value="<?php echo e($editing['name']); ?>" required>
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm" style="width:100%;"><i class="fa fa-save"></i> Update</button>
                            <a href="add_student_documents.php" class="btn btn-default btn-sm" style="width:100%; margin-top:6px;"><i class="fa fa-times"></i> Cancel</a>
                        </form>
                    <?php else: ?>
                        <h4 style="font-size:14px; font-weight:700; color:#2A3F54; margin:0 0 14px;"><i class="fa fa-plus-circle"></i> Add New Title</h4>
                        <form method="post" action="add_student_documents.php">
                            <input type="hidden" name="action" value="add">
                            <div class="form-group">
                                <label style="font-size:12px; color:#64748B;">Title Name</label>
                                <input type="text" name="name" class="form-control" placeholder="e.g. School Leaving Certificate" required>
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm" style="width:100%;"><i class="fa fa-plus"></i> Add Title</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-md-8" style="padding-right:0;">
                <div style="background:#fff; border:1px solid #E6E9ED; border-radius:14px; overflow:hidden;">
                    <?php if (count($titles) > 0): ?>
                        <table class="table" style="margin:0;">
                            <thead>
                                <tr>
                                    <th class="doc-title-th">#</th>
                                    <th class="doc-title-th">Title</th>
                                    <th class="doc-title-th">Created</th>
                                    <th class="doc-title-th" style="text-align:right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($titles as $t): ?>
                                <tr class="doc-title-tr">
                                    <td class="doc-title-td"><span class="title-num"><?php echo (int) $t['id']; ?></span></td>
                                    <td class="doc-title-td"><span class="title-chip"><i class="fa fa-file-text-o"></i> <?php echo e($t['name']); ?></span></td>
                                    <td class="doc-title-td" style="color:#5A6B7B; font-size:12.5px;"><?php echo e(date('d M Y', strtotime($t['created_at']))); ?></td>
                                    <td class="doc-title-td" style="text-align:right; white-space:nowrap;">
                                        <a href="add_student_documents.php?edit=<?php echo (int) $t['id']; ?>" class="btn btn-sm btn-warning btn-sm-ghost"><i class="fa fa-pencil"></i> Edit</a>
                                        <form method="post" action="add_student_documents.php" style="display:inline-block;" onsubmit="return confirm('Delete this document title?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo (int) $t['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger btn-sm-ghost"><i class="fa fa-trash"></i> Delete</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="doc-empty">
                            <i class="fa fa-file-text-o"></i>
                            No document titles yet. Add one using the form on the left.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>

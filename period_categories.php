<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

require_once __DIR__ . '/includes/period_schema.php';

$page_title = 'Period Category';

$message = '';
$error = '';

$edit_mode = 0;
$edit_title = '';

if (isset($_GET['PeriodCat'])) {
    $edit_mode = (int) $_GET['PeriodCat'];
    if ($edit_mode > 0) {
        $st = db_prepare("SELECT * FROM period_categories WHERE id=?");
        $st->bind_param('i', $edit_mode);
        $st->execute();
        $res = $st->get_result();
        if ($cat = $res->fetch_assoc()) { $edit_title = $cat['name']; }
        else { $edit_mode = 0; }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'PeriodCategory') {
        $title = trim($_POST['PcategoryTitle'] ?? '');
        $edit_id = (int) ($_POST['edit_id'] ?? 0);
        if ($title === '') {
            $error = 'Period category title is required.';
        } elseif ($edit_id > 0) {
            $st2 = db_prepare("UPDATE period_categories SET name=? WHERE id=?");
            $st2->bind_param('si', $title, $edit_id);
            $st2->execute();
            $message = 'Period category updated successfully!';
            $edit_mode = 0; $edit_title = '';
        } else {
            $st2 = db_prepare("INSERT INTO period_categories (name) VALUES (?)");
            $st2->bind_param('s', $title);
            $st2->execute();
            $message = 'Period category added successfully!';
        }
    }

    if ($action === 'DeleteCategoryPeriod') {
        $id = (int) ($_POST['PeriodCat'] ?? 0);
        if ($id > 0) {
            $d = db_prepare("UPDATE periods SET category_id=NULL WHERE category_id=?");
            $d->bind_param('i', $id);
            $d->execute();
            $d2 = db_prepare("DELETE FROM class_periods WHERE period_cat_id=?");
            $d2->bind_param('i', $id);
            $d2->execute();
            $st2 = db_prepare("DELETE FROM period_categories WHERE id=?");
            $st2->bind_param('i', $id);
            $st2->execute();
            $message = 'Period category deleted.';
        }
    }
}

$categories = [];
$res = db_query("SELECT * FROM period_categories ORDER BY name");
while ($row = $res->fetch_assoc()) { $categories[] = $row; }

include __DIR__ . '/includes/header.php';
?>
<style>
.a2-breadcrumb a { color:#6366F1; text-decoration:none; cursor:pointer; }
.a2-breadcrumb i { color:#9CA3AF; margin:0 6px; }
</style>

<div class="main-content">
    <div class="container-fluid">
        <div class="a2-breadcrumb" style="padding:8px 4px; font-size:13px; color:#111827;">
            <a href="<?php echo BASE_URL; ?>index.php"><i class="fa fa-home"></i> Dashboard</a>
            <i class="fa fa-angle-double-right"></i> Period Category
        </div>

        <?php if ($message): ?><div class="alert alert-success"><?php echo e($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>

        <h3 style="font-size:18px; font-weight:800; color:#111827; padding:10px 4px; margin:0;">
            List View Period Category <small style="color:#6B7280;">(<?php echo count($categories); ?> Record<?php echo count($categories) === 1 ? '' : 's'; ?> Found)</small>
        </h3>

        <div class="row">
            <div class="col-md-6">
                <div style="overflow-x:auto; background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:4px;">
                    <table class="table table-striped table-bordered" style="width:100%; background:#fff; margin-bottom:0; font-size:12.5px;">
                        <thead>
                            <tr>
                                <th style="width:10%; text-align:center;">S.No</th>
                                <th>Period Title</th>
                                <th style="width:15%; text-align:center;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($categories) === 0): ?>
                                <tr><td colspan="3" style="text-align:center; color:#6B7280; padding:30px;">No period categories created yet. Use the form to add one.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($categories as $i => $cat): ?>
                                <tr>
                                    <td style="text-align:center;"><?php echo $i + 1; ?></td>
                                    <td><?php echo e($cat['name']); ?></td>
                                    <td style="text-align:center; white-space:nowrap;">
                                        <a href="<?php echo BASE_URL; ?>period_categories.php?PeriodCat=<?php echo $cat['id']; ?>" class="btn btn-success" style="padding:2px 8px; font-size:13px;"><i class="fa fa-pencil" aria-hidden="true"></i></a>
                                        <form method="post" action="period_categories.php" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this record');">
                                            <input type="hidden" name="action" value="DeleteCategoryPeriod">
                                            <input type="hidden" name="PeriodCat" value="<?php echo $cat['id']; ?>">
                                            <button type="submit" class="btn btn-danger" style="padding:2px 8px; font-size:13px;"><i class="fa fa-remove" aria-hidden="true"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="col-md-6">
                <form method="post" action="period_categories.php" style="background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:18px;">
                    <input type="hidden" name="action" value="PeriodCategory">
                    <input type="hidden" name="edit_id" value="<?php echo $edit_mode; ?>">
                    <div class="form-group">
                        <label for="PcategoryTitle">Period Category Title</label>
                        <input type="text" name="PcategoryTitle" id="PcategoryTitle" placeholder="Enter Title..." autocomplete="off" required maxlength="100" class="form-control" autofocus value="<?php echo e($edit_title); ?>">
                        <?php if ($edit_mode > 0): ?>
                            <p style="margin-top:8px; color:#6B7280; font-size:12.5px;"><i class="fa fa-info-circle"></i> Editing existing category. Saving will update it.</p>
                        <?php endif; ?>
                    </div>
                    <div class="clearfix"></div>
                    <button type="submit" name="submit" class="btn btn-primary" style="padding:10px 30px;"><i class="fa fa-save"></i> Save Period</button>
                    <?php if ($edit_mode > 0): ?>
                        <a href="<?php echo BASE_URL; ?>period_categories.php" class="btn btn-default">Cancel Edit</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
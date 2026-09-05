<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'View Templates';

$templates = [];
$res = db_query("SELECT * FROM message_templates ORDER BY template_id DESC");
while ($row = $res->fetch_assoc()) { $templates[] = $row; }

include __DIR__ . '/includes/header.php';
?>
<div class="main-content">
    <div class="container-fluid">
        <div style="display:flex; align-items:center; justify-content:space-between; padding:14px 4px;">
            <h3 style="font-size:18px; font-weight:800; color:#111827; margin:0;"><i class="fa fa-files-o"></i> View Templates</h3>
            <a href="<?php echo BASE_URL; ?>new_message.php" class="btn btn-success" style="color:#fff;"><i class="fa fa-plus"></i> New Message</a>
        </div>

        <div class="row">
            <?php if (count($templates) === 0): ?>
                <div style="text-align:center; color:#6B7280; padding:40px; width:100%;">Koi template nahi. New Message page se template save karein.</div>
            <?php endif; ?>
            <?php foreach ($templates as $t): ?>
                <div class="col-md-6" style="margin-bottom:14px;">
                    <div style="background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:16px;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                            <strong style="font-size:15px; color:#111827;"><?php echo e($t['title']); ?></strong>
                            <span class="status-badge" style="background:#E0E7FF; color:#4338CA;">#<?php echo $t['template_id']; ?></span>
                        </div>
                        <p style="color:#4B5563; font-size:13.5px; margin:0;"><?php echo nl2br(e($t['body'])); ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
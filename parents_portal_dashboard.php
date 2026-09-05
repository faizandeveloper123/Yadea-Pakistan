<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Parents Portal';

$students = [];
$res = db_query("SELECT s.*, c.class_name FROM students s LEFT JOIN classes c ON s.class_id=c.class_id ORDER BY s.first_name");
while ($row = $res->fetch_assoc()) { $students[] = $row; }

$an = (int) (db_query("SELECT COUNT(*) c FROM attendance WHERE marked_by IS NOT NULL")->fetch_assoc()['c'] ?? 0);
$ch = (int) (db_query("SELECT COUNT(*) c FROM fee_challans WHERE status='unpaid'")->fetch_assoc()['c'] ?? 0);

include __DIR__ . '/includes/header.php';
?>
<style>
.parent-card { background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:16px; margin-bottom:12px; display:flex; align-items:center; gap:14px; }
.parent-card .pc-avatar { width:48px; height:48px; border-radius:999px; background:linear-gradient(135deg,#FF7A1B,#ffa35c); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:18px; flex-shrink:0; }
</style>

<div class="main-content">
    <div class="container-fluid">
        <div style="display:flex; align-items:center; justify-content:space-between; padding:14px 4px;">
            <h3 style="font-size:18px; font-weight:800; color:#111827; margin:0;"><i class="fa fa-users"></i> Parents Portal <span style="font-size:14px; color:#6B7280;">(<?php echo count($students); ?> students)</span></h3>
        </div>

        <div class="alert alert-info" style="border-radius:12px; display:flex; gap:20px; align-items:center;">
            <div><i class="fa fa-calendar-check-o fa-2x"></i> <strong><?php echo $an; ?></strong> attendance records</div>
            <div><i class="fa fa-money fa-2x"></i> <strong><?php echo $ch; ?></strong> unpaid challans</div>
        </div>

        <?php if (count($students) === 0): ?>
            <div style="text-align:center; color:#6B7280; padding:50px;">No students registered yet.</div>
        <?php endif; ?>
        <?php foreach ($students as $st): ?>
            <div class="parent-card">
                <div class="pc-avatar"><?php echo strtoupper(substr($st['first_name'], 0, 1)); ?></div>
                <div style="flex:1;">
                    <div style="font-weight:800; color:#111827;"><?php echo e($st['first_name']); ?> <span style="color:#6B7280; font-weight:500;">(<?php echo e($st['class_name'] ?? '-'); ?>)</span></div>
                    <div style="font-size:12.5px; color:#6B7280;">Father: <?php echo e($st['father_name'] ?? '-'); ?> &nbsp;|&nbsp; Phone: <?php echo e($st['phone'] ?? '-'); ?></div>
                </div>
                <div style="display:flex; gap:8px;">
                    <a href="<?php echo BASE_URL; ?>student_fee_payments_view.php?student_id=<?php echo $st['student_id']; ?>" class="btn btn-info btn-xs" style="color:#fff;"><i class="fa fa-money"></i> Fee</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
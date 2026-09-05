<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Front Desk Analytics';

$today = date('Y-m-d');
$month = date('m'); $year = date('Y');

$totalInquiries   = (int) (db_query("SELECT COUNT(*) c FROM inquiries")->fetch_assoc()['c'] ?? 0);
$newInquiries     = (int) (db_query("SELECT COUNT(*) c FROM inquiries WHERE MONTH(created_at)=$month AND YEAR(created_at)=$year")->fetch_assoc()['c'] ?? 0);
$todayInquiries   = (int) (db_query("SELECT COUNT(*) c FROM inquiries WHERE DATE(created_at)='$today'")->fetch_assoc()['c'] ?? 0);

$totalComplaints  = (int) (db_query("SELECT COUNT(*) c FROM complaints")->fetch_assoc()['c'] ?? 0);
$openComplaints   = (int) (db_query("SELECT COUNT(*) c FROM complaints WHERE status IN ('open','in_progress')")->fetch_assoc()['c'] ?? 0);
$resolvedComplaints = (int) (db_query("SELECT COUNT(*) c FROM complaints WHERE status IN ('resolved','closed')")->fetch_assoc()['c'] ?? 0);

$recentInquiries = [];
$res = db_query("SELECT i.*, c.class_name FROM inquiries i LEFT JOIN classes c ON i.class_id=c.class_id ORDER BY i.created_at DESC LIMIT 8");
while ($row = $res->fetch_assoc()) { $recentInquiries[] = $row; }

$recentComplaints = [];
$res = db_query("SELECT * FROM complaints ORDER BY created_at DESC LIMIT 8");
while ($row = $res->fetch_assoc()) { $recentComplaints[] = $row; }

include __DIR__ . '/includes/header.php';
?>
<style>
.kpi-row { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:16px; }
.kpi-card { background:#fff; border:1px solid #E5E7EB; border-radius:16px; padding:18px; }
.kpi-top { display:flex; align-items:center; gap:11px; }
.kpi-icon { width:42px; height:42px; border-radius:13px; display:flex; align-items:center; justify-content:center; font-size:17px; }
.kpi-label { font-size:12.5px; color:#6B7280; font-weight:600; }
.kpi-value { font-size:23px; font-weight:800; color:#111827; margin-top:14px; }
.row2 { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
@media (max-width:1200px){ .kpi-row{grid-template-columns:repeat(2,1fr);} .row2{grid-template-columns:1fr;} }
</style>

<div class="main-content">
    <div class="container-fluid">
        <div style="display:flex; align-items:center; justify-content:space-between; padding:14px 4px;">
            <h3 style="font-size:18px; font-weight:800; color:#111827; margin:0;"><i class="fa fa-desktop"></i> Front Desk Overview</h3>
        </div>

        <div class="kpi-row">
            <div class="kpi-card"><div class="kpi-top"><div class="kpi-icon" style="background:#E9F2FF; color:#377DFF;"><i class="fa fa-user-plus"></i></div><div class="kpi-label">Total Inquiries</div></div><div class="kpi-value"><?php echo $totalInquiries; ?></div></div>
            <div class="kpi-card"><div class="kpi-top"><div class="kpi-icon" style="background:#D3F3E4; color:#22C55E;"><i class="fa fa-plus-circle"></i></div><div class="kpi-label">This Month</div></div><div class="kpi-value"><?php echo $newInquiries; ?></div></div>
            <div class="kpi-card"><div class="kpi-top"><div class="kpi-icon" style="background:#FFE5D1; color:#FF7C1B;"><i class="fa fa-calendar-check-o"></i></div><div class="kpi-label">Today</div></div><div class="kpi-value"><?php echo $todayInquiries; ?></div></div>
            <div class="kpi-card"><div class="kpi-top"><div class="kpi-icon" style="background:#FFD4D1; color:#FF261B;"><i class="fa fa-comment-dots"></i></div><div class="kpi-label">Open Complaints</div></div><div class="kpi-value"><?php echo $openComplaints; ?></div></div>
        </div>

        <div class="row2">
            <div style="background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:16px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                    <h4 style="font-size:15px; font-weight:800; margin:0;">Recent Inquiries</h4>
                    <a href="<?php echo BASE_URL; ?>student_inquiry.php" class="btn btn-info btn-xs" style="color:#fff;">View All</a>
                </div>
                <?php if (count($recentInquiries) === 0): ?><div style="color:#6B7280; padding:20px; text-align:center;">Koi inquiry nahi.</div><?php endif; ?>
                <?php foreach ($recentInquiries as $inq): ?>
                    <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #F3F4F6; padding:9px 0;">
                        <div><strong style="color:#111827;"><?php echo e($inq['name']); ?></strong><br><small style="color:#6B7280;"><?php echo e($inq['class_name'] ?? '-'); ?> • <?php echo e($inq['phone'] ?? ''); ?></small></div>
                        <span class="status-badge status-<?php echo $inq['status'] == 'new' ? 'pending' : 'present'; ?>"><?php echo ucfirst($inq['status']); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <div style="background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:16px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                    <h4 style="font-size:15px; font-weight:800; margin:0;">Recent Complaints</h4>
                    <a href="<?php echo BASE_URL; ?>manage_complaint.php" class="btn btn-info btn-xs" style="color:#fff;">View All</a>
                </div>
                <?php if (count($recentComplaints) === 0): ?><div style="color:#6B7280; padding:20px; text-align:center;">Koi complaint nahi.</div><?php endif; ?>
                <?php foreach ($recentComplaints as $c): ?>
                    <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #F3F4F6; padding:9px 0;">
                        <div><strong style="color:#111827;"><?php echo e($c['subject']); ?></strong><br><small style="color:#6B7280;"><?php echo e($c['complaint_type'] ?? 'general'); ?></small></div>
                        <span class="status-badge status-<?php echo in_array($c['status'], ['resolved','closed']) ? 'present' : 'pending'; ?>"><?php echo ucfirst($c['status']); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
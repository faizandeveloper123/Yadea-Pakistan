<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Staff Dashboard';

$today = date('Y-m-d');

$totalStudents   = (int) (db_query("SELECT COUNT(*) c FROM students WHERE status=1")->fetch_assoc()['c'] ?? 0);
$totalEmployees  = (int) (db_query("SELECT COUNT(*) c FROM employees WHERE status=1")->fetch_assoc()['c'] ?? 0);
$booksTotal      = (int) (db_query("SELECT COUNT(*) c FROM books")->fetch_assoc()['c'] ?? 0);
$issuedBooks     = (int) (db_query("SELECT COUNT(*) c FROM book_issues WHERE status='issued'")->fetch_assoc()['c'] ?? 0);
$vehiclesTotal   = (int) (db_query("SELECT COUNT(*) c FROM vehicles WHERE status=1")->fetch_assoc()['c'] ?? 0);
$routesTotal     = (int) (db_query("SELECT COUNT(*) c FROM routes WHERE status=1")->fetch_assoc()['c'] ?? 0);

$msgCount        = (int) (db_query("SELECT COUNT(*) c FROM messages")->fetch_assoc()['c'] ?? 0);
$complaintsOpen  = (int) (db_query("SELECT COUNT(*) c FROM complaints WHERE status != 'resolved'")->fetch_assoc()['c'] ?? 0);
$ticketsCount    = (int) (db_query("SELECT COUNT(*) c FROM messages WHERE 1=1")->fetch_assoc()['c'] ?? 0);

// Today's attendance
$attTotal = (int) (db_query("SELECT COUNT(*) c FROM attendance WHERE date='$today'")->fetch_assoc()['c'] ?? 0);
$attPresent = (int) (db_query("SELECT COUNT(*) c FROM attendance WHERE date='$today' AND status='present'")->fetch_assoc()['c'] ?? 0);
$attPct = $attTotal > 0 ? round(($attPresent / $attTotal) * 100) : 0;

// Recent messages
$recentMsgs = [];
$res = db_query("SELECT * FROM messages ORDER BY created_at DESC, message_id DESC LIMIT 5");
while ($row = $res->fetch_assoc()) { $recentMsgs[] = $row; }

include __DIR__ . '/includes/header.php';
?>
<style>
.aqib-dash { padding-top: 10px; padding-bottom: 30px; }
.aqib-dash * { box-sizing: border-box; }
.kpi-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 16px; }
.kpi-card { background:#fff; border:1px solid #E5E7EB; border-radius:16px; padding:18px; box-shadow:0 1px 3px rgba(16,24,40,.06); }
.kpi-card:hover { box-shadow:0 8px 24px rgba(15,23,42,.08); }
.kpi-top { display:flex; align-items:center; gap:11px; }
.kpi-icon { width:42px; height:42px; border-radius:13px; display:flex; align-items:center; justify-content:center; font-size:17px; flex-shrink:0; }
.kpi-label { font-size:12.5px; color:#6B7280; font-weight:600; }
.kpi-value { font-size:23px; font-weight:800; color:#111827; margin-top:14px; line-height:1.2; }
.row2 { display:grid; grid-template-columns:1.6fr 1fr; gap:14px; }
.card-head { display:flex; align-items:center; justify-content:space-between; padding:16px 18px 0 18px; }
.card-title { font-size:15px; font-weight:700; color:#111827; }
@media (max-width:1400px){ .kpi-row{grid-template-columns:repeat(2,1fr);} .row2{grid-template-columns:1fr;} }
@media (max-width:600px){ .kpi-row{grid-template-columns:1fr;} }
</style>

<div class="main-content">
    <div class="container-fluid">
        <div class="aqib-dash">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; padding:4px 4px 14px;">
                <h3 style="font-size:18px; font-weight:800; color:#111827; margin:0;"><i class="fa fa-tachometer"></i> Staff Dashboard</h3>
                <span style="font-size:12.5px; color:#6B7280; background:#F3F4F6; border:1px solid #E5E7EB; padding:5px 12px; border-radius:999px;"><?php echo date('d M, Y'); ?></span>
            </div>

            <div class="kpi-row">
                <div class="kpi-card">
                    <div class="kpi-top"><div class="kpi-icon" style="background:#E9F2FF; color:#377DFF;"><i class="fa fa-users"></i></div><div class="kpi-label">Students</div></div>
                    <div class="kpi-value"><?php echo $totalStudents; ?></div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-top"><div class="kpi-icon" style="background:#D3F3E4; color:#22C55E;"><i class="fa fa-user-tie"></i></div><div class="kpi-label">Staff</div></div>
                    <div class="kpi-value"><?php echo $totalEmployees; ?></div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-top"><div class="kpi-icon" style="background:#FFE5D1; color:#FF7C1B;"><i class="fa fa-book"></i></div><div class="kpi-label">Books (<?php echo $issuedBooks; ?> issued)</div></div>
                    <div class="kpi-value"><?php echo $booksTotal; ?></div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-top"><div class="kpi-icon" style="background:#EADAFF; color:#9747FF;"><i class="fa fa-bus"></i></div><div class="kpi-label">Vehicles / Routes</div></div>
                    <div class="kpi-value"><?php echo $vehiclesTotal; ?> / <?php echo $routesTotal; ?></div>
                </div>
            </div>

            <div class="row2">
                <div class="kpi-card">
                    <div class="card-head" style="padding:0 0 12px;">
                        <div class="card-title"><i class="fa fa-envelope" style="color:#377DFF; margin-right:8px;"></i> Recent Messages</div>
                        <span class="status-badge status-present"><?php echo $msgCount; ?> total</span>
                    </div>
                    <div style="overflow-x:auto; background:#fff; border-radius:12px;">
                        <table class="table table-striped table-bordered" style="width:100%; background:#fff; margin-bottom:0; font-size:13px;">
                            <thead><tr><th>To</th><th>Message</th><th>Status</th><th>Time</th></tr></thead>
                            <tbody>
                                <?php if (count($recentMsgs) === 0): ?><tr><td colspan="4" style="text-align:center; color:#6B7280; padding:20px;">Koi message nahi.</td></tr><?php endif; ?>
                                <?php foreach ($recentMsgs as $m): ?>
                                    <tr>
                                        <td><strong><?php echo e($m['recipient'] ?? 'All'); ?></strong></td>
                                        <td><?php echo e(mb_strimwidth($m['message'] ?? $m['body'] ?? '', 0, 60, '...')); ?></td>
                                        <td><span class="status-badge status-<?php echo ($m['status'] ?? 'sent') == 'sent' ? 'present' : 'pending'; ?>"><?php echo e(ucfirst($m['status'] ?? 'sent')); ?></span></td>
                                        <td><?php echo date('d M h:i', strtotime($m['sent_at'] ?? $m['created_at'] ?? 'now')); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="kpi-card">
                    <div class="card-head" style="padding:0 0 12px;">
                        <div class="card-title"><i class="fa fa-calendar-check-o" style="color:#16A34A; margin-right:8px;"></i> Today Attendance</div>
                    </div>
                    <div style="text-align:center; padding:10px 0;">
                        <div style="width:120px; height:120px; border-radius:50%; margin:0 auto; background:conic-gradient(#22C55E <?php echo $attPct; ?>%, #E5EAF0 0); display:flex; align-items:center; justify-content:center;">
                            <div style="width:88px; height:88px; border-radius:50%; background:#fff; display:flex; flex-direction:column; align-items:center; justify-content:center;">
                                <div style="font-size:20px; font-weight:800; color:#16A34A;"><?php echo $attPct; ?>%</div>
                                <div style="font-size:9.5px; color:#9CA3AF;">Present</div>
                            </div>
                        </div>
                        <div style="margin-top:12px; color:#6B7280; font-size:13px;"><?php echo $attPresent; ?> of <?php echo $attTotal; ?> present today</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
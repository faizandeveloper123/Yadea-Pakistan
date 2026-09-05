<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Datewise Fee Collection Report';

$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');

$classHead = $_GET['class_head'] ?? 'All';
$classId = $_GET['class_id'] ?? 'All';
$sectionId = $_GET['section'] ?? 'All';
$channel = $_GET['channelName'] ?? 'All';
$userId = $_GET['user_id'] ?? 'All';
$feeType = $_GET['fee_type'] ?? 'All';
$orderBy = $_GET['orderBy'] ?? 'GRnoWise';

// Class Head -> classes mapping from settings (class_head_* keys)
$classHeads = [];
$res = db_query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'class_head%'");
while ($row = $res->fetch_assoc()) {
    $label = str_replace('class_head_', '', $row['setting_key']);
    $ids = array_values(array_filter(array_map('intval', explode(',', $row['setting_value']))));
    if (count($ids) > 0) { $classHeads[] = ['label' => $label, 'ids' => $ids]; }
}

$classes = [];
$res = db_query("Select Course/Class_id, class_name FROM classes WHERE status=1 ORDER BY class_name");
while ($row = $res->fetch_assoc()) { $classes[] = $row; }

$sections = [];
$res = db_query("SELECT section_id, class_id, section_name FROM sections ORDER BY section_name");
while ($row = $res->fetch_assoc()) { $sections[] = $row; }

$users = [];
$res = db_query("SELECT user_id, full_name FROM users WHERE status=1 ORDER BY full_name");
while ($row = $res->fetch_assoc()) { $users[] = $row; }

$feeHeads = [];
$res = db_query("SELECT head_id, head_name FROM fee_heads WHERE status=1 ORDER BY head_name");
while ($row = $res->fetch_assoc()) { $feeHeads[] = $row; }

// ------------------------------------------------------------
// Build query
// ------------------------------------------------------------
$where = [];
$params = [];
$types = 'ss';
$params[] = $from;
$params[] = $to;
$where[] = 'DATE(p.created_at) BETWEEN ? AND ?';

if ($classHead !== '' && $classHead !== 'All') {
    $ids = array_values(array_filter(array_map('intval', explode(',', $classHead))));
    if (count($ids) > 0) {
        $where[] = 'c.class_id IN (' . implode(',', $ids) . ')';
    }
}
if ($classId !== '' && $classId !== 'All') {
    $where[] = 'c.class_id = ?';
    $params[] = (int)$classId;
    $types .= 'i';
}
if ($sectionId !== '' && $sectionId !== 'All') {
    $where[] = 's.section_id = ?';
    $params[] = (int)$sectionId;
    $types .= 'i';
}
if ($channel !== '' && $channel !== 'All') {
    $where[] = 'LOWER(p.payment_method) = LOWER(?)';
    $params[] = $channel;
    $types .= 's';
}
if ($userId !== '' && $userId !== 'All') {
    $where[] = 'p.received_by = ?';
    $params[] = (int)$userId;
    $types .= 'i';
}
if ($feeType !== '' && $feeType !== 'All') {
    $where[] = "EXISTS (SELECT 1 FROM fee_challan_items fi WHERE fi.challan_id = p.challan_id AND fi.head_id = ?)";
    $params[] = (int)$feeType;
    $types .= 'i';
}

$orderMap = [
    'GRnoWise' => 's.gr_no',
    'asc' => 's.first_name',
    'ClassWise' => 'cl.class_name, sec.section_name',
    'PaidDateTime' => 'p.created_at DESC',
];
$orderSql = $orderMap[$orderBy] ?? 's.gr_no';

$sql = "SELECT p.*, c.challan_no, c.month, c.year, s.student_id, s.gr_no, s.first_name, s.father_name, s.session,
        cl.class_name, sec.section_name, u.full_name collected_by
        FROM fee_payments p
        LEFT JOIN fee_challans c ON p.challan_id = c.challan_id
        LEFT JOIN students s ON c.student_id = s.student_id
        LEFT JOIN classes cl ON c.class_id = cl.class_id
        LEFT JOIN sections sec ON s.section_id = sec.section_id
        LEFT JOIN users u ON p.received_by = u.user_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY " . $orderSql;

$rows = [];
$st2 = db_prepare($sql);
$st2->bind_param($types, ...$params);
$st2->execute();
$res2 = $st2->get_result();
while ($row = $res2->fetch_assoc()) { $rows[] = $row; }

$grand = 0.0;
$byMethod = [];
foreach ($rows as $r) {
    $grand += (float) $r['amount'];
    $m = $r['payment_method'] ?: 'cash';
    $byMethod[$m] = ($byMethod[$m] ?? 0) + (float) $r['amount'];
}

include __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="https://cdn.datatables.net/1.10.13/css/jquery.dataTables.min.css">
<style>
.page-header-section { margin-bottom:14px; }
.page-header-section h2 { font-size:18px; font-weight:800; color:#111827; margin:0; }
.record-count-badge { display:inline-block; font-size:11px; font-weight:700; color:#377DFF; background:#E9F2FF; border-radius:999px; padding:4px 10px; margin-left:8px; vertical-align:middle; }
.breadcrumb-modern { display:flex; align-items:center; gap:8px; font-size:12.5px; color:#6B7280; margin:6px 0 0; padding:0; list-style:none; }
.breadcrumb-modern a { color:#377DFF; text-decoration:none; }
.breadcrumb-modern i { font-size:11px; color:#9CA3AF; }
.filter-panel { background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:8px 16px 16px; margin-bottom:16px; }
.filter-panel h4 { font-size:14px; font-weight:800; color:#111827; margin:0 0 4px; padding-top:10px; }
.page-actions { margin-bottom:16px; }
.dataTables_wrapper { padding: 0 12px 12px; }
</style>

<div class="main-content">
    <div class="container-fluid">
        <div class="page-header-section">
            <h2><i class="fa fa-chart-line"></i> Datewise Fee Collection Report <span class="record-count-badge"><?php echo count($rows); ?> Records</span></h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb-modern">
                    <li><a href="<?php echo BASE_URL; ?>dashboard.php"><i class="fa fa-home"></i> Dashboard</a></li>
                    <li><i class="fa fa-angle-right"></i></li>
                    <li><a href="#">Fee Reports</a></li>
                    <li><i class="fa fa-angle-right"></i></li>
                    <li><span>Datewise Fee Collection Report</span></li>
                </ol>
            </nav>
        </div>

        <div class="filter-panel">
            <h4><i class="fa fa-filter"></i> Filter Collection</h4>
            <form action="datewise_fee_collection_report_new.php" method="get">
                <input type="hidden" name="addaccountAdmin" value="1">
                <div class="row">
                    <div class="col-md-2 col-xs-12" style="padding:8px;">
                        <div class="form-group">
                            <label class="required">Course/Class Head</label>
                            <select name="class_head" id="class_head" class="form-control" onchange="getClassesByHead(this.value)">
                                <option value="All">All</option>
                                <?php foreach ($classHeads as $ch): ?>
                                    <option value="<?php echo e(implode(',', $ch['ids'])); ?>" <?php echo $classHead === implode(',', $ch['ids']) ? 'selected' : ''; ?>><?php echo e($ch['label']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2 col-xs-12" style="padding:8px;">
                        <div class="form-group">
                            <label class="required">Course/Class</label>
                            <select name="class_id" id="class_dropdown" class="form-control" onchange="getSections(this.value)">
                                <option value="All">All</option>
                                <?php foreach ($classes as $cl): ?>
                                    <?php
                                    $courseNames = ['Graphic Designing', 'AI', 'Web Development', 'Sales', 'English Spoken', 'E-commerce'];
                                    if (!in_array($cl['class_name'], $courseNames)) continue;
                                    ?>
                                    <option value="<?php echo $cl['class_id']; ?>" <?php echo $classId === (string)$cl['class_id'] ? 'selected' : ''; ?>><?php echo e($cl['class_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2 col-xs-12" style="padding:8px;">
                        <div class="form-group">
                            <label class="required">Section</label>
                            <select name="section" id="txt_section" class="form-control">
                                <option value="All">All</option>
                                <?php foreach ($sections as $sec): ?>
                                    <?php if ($classId !== 'All' && $classId !== '0' && (int)$sec['class_id'] !== (int)$classId) { continue; } ?>
                                    <option value="<?php echo $sec['section_id']; ?>" <?php echo $sectionId === (string)$sec['section_id'] ? 'selected' : ''; ?>><?php echo e($sec['section_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2 col-xs-12" style="padding:8px;">
                        <div class="form-group">
                            <label class="required" style="font-size:11px;">Payment Method</label>
                            <select name="channelName" class="form-control">
                                <option value="All">All</option>
                                <?php foreach (['Cash','JazzCash','Easypaisa','BankAccount','UblOmni','OnlineTransfer','POSCard'] as $pm): ?>
                                    <option value="<?php echo $pm; ?>" <?php echo $channel === $pm ? 'selected' : ''; ?>><?php echo $pm === 'POSCard' ? 'POS / Card' : ($pm === 'BankAccount' ? 'Bank Account' : ($pm === 'UblOmni' ? 'Ubl Omni' : ($pm === 'OnlineTransfer' ? 'Online Transfer' : $pm))); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2 col-xs-12" style="padding:8px;">
                        <div class="form-group">
                            <label class="required">From Date</label>
                            <input name="from" type="date" class="form-control" value="<?php echo e($from); ?>">
                        </div>
                    </div>
                    <div class="col-md-2 col-xs-12" style="padding:8px;">
                        <div class="form-group">
                            <label class="required">To Date</label>
                            <input name="to" type="date" class="form-control" value="<?php echo e($to); ?>">
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-12 col-xs-12" style="padding:8px;">
                        <label>Paid By</label>
                        <select name="user_id" class="form-control">
                            <option value="All">All</option>
                            <?php foreach ($users as $us): ?>
                                <option value="<?php echo $us['user_id']; ?>" <?php echo $userId === (string)$us['user_id'] ? 'selected' : ''; ?>><?php echo e($us['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 col-sm-12 col-xs-12" style="padding:8px;">
                        <label>Fee Head</label>
                        <select name="fee_type" class="form-control">
                            <option value="All">All</option>
                            <?php foreach ($feeHeads as $fh): ?>
                                <option value="<?php echo $fh['head_id']; ?>" <?php echo $feeType === (string)$fh['head_id'] ? 'selected' : ''; ?>><?php echo e($fh['head_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 col-xs-12" style="padding:8px;">
                        <label>Sort Students By</label>
                        <select name="orderBy" class="form-control">
                            <option value="GRnoWise" <?php echo $orderBy === 'GRnoWise' ? 'selected' : ''; ?>>By GR Number</option>
                            <option value="asc" <?php echo $orderBy === 'asc' ? 'selected' : ''; ?>>By Name (A–Z)</option>
                            <option value="ClassWise" <?php echo $orderBy === 'ClassWise' ? 'selected' : ''; ?>>By Class &amp; Section</option>
                            <option value="PaidDateTime" <?php echo $orderBy === 'PaidDateTime' ? 'selected' : ''; ?>>By Paid Date Time</option>
                        </select>
                    </div>
                    <div class="col-md-1 pull-left" style="padding:8px;">
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary default_btn" style="margin-top:22px;"><i class="fa fa-filter"></i> Filter</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <div class="page-actions">
            <a href="<?php echo BASE_URL; ?>datewise_fee_collection_new.php?from=<?php echo e($from); ?>&to=<?php echo e($to); ?>" class="btn btn-info" style="color:#fff;"><i class="fa fa-calendar"></i> Date Wise Total Collection</a>
            <button type="button" class="btn btn-success" style="color:#fff;" onclick="window.print()"><i class="fa fa-print"></i> Print Report</button>
        </div>

        <?php if (count($byMethod) > 0): ?>
            <div style="margin-bottom:14px; font-size:13px;">
                <strong style="color:#16A34A; font-size:16px;"><?php echo get_setting('currency_symbol', 'Rs.') . number_format($grand, 2); ?></strong> collected
                <?php foreach ($byMethod as $m => $amt): ?>
                    <span class="status-badge" style="background:#F3F4F6; color:#374151;"><?php echo e(ucfirst($m)) . ': ' . number_format($amt, 2); ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div style="overflow-x:auto; background:#fff; border:1px solid #E5E7EB; border-radius:14px;">
            <table id="listofstudents" class="table table-striped table-bordered" style="width:100%; background:#fff; margin-bottom:0; font-size:11px;">
                <thead>
                    <tr>
                        <th width="2%">S.No</th>
                        <th width="5%">GR.No</th>
                        <th width="15%">Student Name</th>
                        <th width="12%" style="text-align:center;">Course/Class</th>
                        <th width="8%" style="text-align:center;">Session</th>
                        <th width="8%" style="text-align:center;">Month</th>
                        <th width="9%">Challan No</th>
                        <th width="9%" style="text-align:center;">Received Amount</th>
                        <th width="9%">Receiv. Date</th>
                        <th width="8%">Payment Mode</th>
                        <th width="8%">Received By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($rows) === 0): ?>
                        <tr><td colspan="11" style="text-align:center; color:#6B7280; padding:25px;">No records found for the selected filters.</td></tr>
                    <?php endif; ?>
                    <?php $i = 1; foreach ($rows as $r): ?>
                        <tr>
                            <td><?php echo $i++; ?></td>
                            <td><?php echo e($r['gr_no'] ?? '-'); ?></td>
                            <td><strong><?php echo e($r['first_name']); ?></strong><br><small style="color:#6B7280;"><?php echo e($r['father_name'] ?? ''); ?></small></td>
                            <td style="text-align:center;"><?php echo e($r['class_name'] ?? '-'); ?><?php echo !empty($r['section_name']) ? ' - ' . e($r['section_name']) : ''; ?></td>
                            <td style="text-align:center;"><?php echo e($r['session'] ?? '-'); ?></td>
                            <td style="text-align:center;"><?php echo e($r['month']); ?> / <?php echo e($r['year']); ?></td>
                            <td><strong><?php echo e($r['challan_no']); ?></strong></td>
                            <td style="text-align:center; color:#16A34A; font-weight:700;"><?php echo number_format($r['amount'], 2); ?></td>
                            <td><?php echo date('d M Y h:i A', strtotime($r['created_at'])); ?></td>
                            <td><span class="status-badge" style="background:#E0E7FF; color:#4338CA;"><?php echo e($r['payment_method'] === 'POSCard' ? 'POS / Card' : ($r['payment_method'] === 'BankAccount' ? 'Bank Account' : ucfirst($r['payment_method']))); ?></span></td>
                            <td><?php echo e($r['collected_by'] ?? 'Admin'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="font-size:18px;">
                        <th colspan="7" style="text-align:right;">Total</th>
                        <th style="text-align:center; color:#16A34A;"><?php echo number_format($grand, 2); ?></th>
                        <th colspan="3"></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.datatables.net/1.10.13/js/jquery.dataTables.min.js"></script>
<script>
var SECTIONS = <?php echo json_encode($sections); ?>;

function getSections(classId) {
    var sel = document.getElementById('txt_section');
    var prev = sel.value;
    sel.innerHTML = '';
    var opt = document.createElement('option'); opt.value = 'All'; opt.textContent = 'All'; sel.appendChild(opt);
    if (classId && classId !== 'All') {
        SECTIONS.forEach(function(sec){
            if (String(sec.class_id) === String(classId)) {
                var o = document.createElement('option'); o.value = sec.section_id; o.textContent = sec.section_name; sel.appendChild(o);
            }
        });
    }
}

function getClassesByHead(headVal) {
    // Class Head -> class ids are embedded in the option value; classes are always listed.
}

$(document).ready(function(){
    $('#listofstudents').DataTable({ order: [], pageLength: 200 });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
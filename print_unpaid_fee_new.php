<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Unpaid Fee Students List';

db_query("CREATE TABLE IF NOT EXISTS fee_commitments (
    commitment_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    challan_id INT NOT NULL,
    commitment_date DATE DEFAULT NULL,
    commitment TEXT,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_student (student_id),
    KEY idx_challan (challan_id)
) ENGINE=InnoDB");
db_query("ALTER TABLE students ADD COLUMN IF NOT EXISTS family_code VARCHAR(50) DEFAULT NULL");
db_query("ALTER TABLE fee_challans ADD COLUMN IF NOT EXISTS fee_remarks VARCHAR(191) DEFAULT NULL");
db_query("ALTER TABLE fee_challans ADD COLUMN IF NOT EXISTS reminder_sent TINYINT(1) NOT NULL DEFAULT 0");
db_query("ALTER TABLE fee_challans ADD COLUMN IF NOT EXISTS reminder_sent_at DATETIME DEFAULT NULL");

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_fee_commitment') {
        $sid = (int) ($_POST['student_id'] ?? 0);
        $cid = (int) ($_POST['challan_id'] ?? 0);
        $cdate = trim($_POST['commitment_date'] ?? '');
        $commitment = trim($_POST['fee_commitment'] ?? '');
        if ($cid <= 0 || $cdate === '' || $commitment === '') {
            $error = 'Please provide both commitment date and details.';
        } else {
            $st = db_prepare("UPDATE fee_challans SET commitment=?, commitment_date=? WHERE challan_id=? AND student_id=?");
            $st->bind_param('ssii', $commitment, $cdate, $cid, $sid);
            $st->execute();
            $uid = (int) ($_SESSION['user_id'] ?? 0);
            $st2 = db_prepare("INSERT INTO fee_commitments (student_id, challan_id, commitment_date, commitment, created_by) VALUES (?, ?, ?, ?, ?)");
            $st2->bind_param('iissi', $sid, $cid, $cdate, $commitment, $uid);
            $st2->execute();
            $message = 'Fee commitment saved for challan <strong>#' . (int) $cid . '</strong>.';
        }
    }

    if ($action === 'send_defaulter_reminders') {
        $challan_ids = $_POST['challan_ids'] ?? [];
        if (!is_array($challan_ids)) { $challan_ids = []; }
        $ids = [];
        foreach ($challan_ids as $ci) {
            $ci = (int) $ci;
            if ($ci > 0) { $ids[] = $ci; }
        }
        if (count($ids) === 0) {
            $error = 'Please select at least one student to send reminders.';
        } else {
            $place = implode(',', array_fill(0, count($ids), '?'));
            $types = str_repeat('i', count($ids));
            $st = db_prepare("UPDATE fee_challans SET reminder_sent=1, reminder_sent_at=NOW() WHERE challan_id IN ($place)");
            $st->bind_param($types, ...$ids);
            $st->execute();
            $message = 'Pending fee defaulter reminder flag updated for <strong>' . count($ids) . '</strong> challan(s).';
        }
    }
}

$sessions = [];
for ($y = 2018; $y <= 2030; $y++) { $sessions[] = $y . '-' . substr($y + 1, -2); }

$sel_session = $_GET['session'] ?? 'All';
if ($sel_session !== 'All' && !in_array($sel_session, $sessions)) { $sel_session = 'All'; }

$classes = [];
$res = db_query("SELECT c.class_id, c.class_name FROM classes c ORDER BY c.class_name");
while ($row = $res->fetch_assoc()) { $classes[] = $row; }
$sel_class = $_GET['class_id'] ?? 'All';
$sel_section = (int) ($_GET['section'] ?? 0);
$sel_month = trim($_GET['month_year'] ?? date('m/Y'));
$sel_order = $_GET['orderBy'] ?? 'GRnoWise';
$sel_report = $_GET['reportType'] ?? 'All';
$sel_arrears = $_GET['arrearsType'] ?? '';
$sel_arrear_amount = (float) ($_GET['arrearAmount'] ?? 0);
$sel_student_status = $_GET['student_status'] ?? 'All';
$sel_fee_head = (int) ($_GET['feeHeadFilter'] ?? 0);

$feeHeads = [];
$res = db_query("SELECT head_id, head_name FROM fee_heads WHERE status=1 ORDER BY head_id");
while ($row = $res->fetch_assoc()) { $feeHeads[] = $row; }

$sections = [];
if ($sel_class !== 'All' && (int)$sel_class > 0) {
    $st = db_prepare("SELECT section_id, section_name FROM sections WHERE class_id=? ORDER BY section_name");
    $st->bind_param('i', (int) $sel_class);
    $st->execute();
    $res = $st->get_result();
    while ($row = $res->fetch_assoc()) { $sections[] = $row; }
}

$where = ["c.status != 'paid'"];
$params = [];
$types = '';

if ($sel_month !== '' && preg_match('#^(\d{1,2})/(\d{4})$#', $sel_month, $mm)) {
    $mn = (int) $mm[1];
    $my = (int) $mm[2];
    if ($mn >= 1 && $mn <= 12) {
        $where[] = "CAST(c.month AS UNSIGNED) = ? AND c.year = ?";
        $params[] = $mn; $params[] = $my;
        $types .= 'ii';
    }
} else {
    $sel_month = date('m/Y');
}

if ($sel_session !== 'All' && preg_match('/^(\d{4})-(\d{2})$/', $sel_session, $sm)) {
    $y1 = (int) $sm[1];
    $y2 = $y1 + 1;
    $where[] = "(c.year = ? OR c.year = ?)";
    $params[] = $y1; $params[] = $y2;
    $types .= 'ii';
}
if ($sel_class !== 'All' && (int)$sel_class > 0) {
    $where[] = "c.class_id = ?";
    $params[] = (int) $sel_class;
    $types .= 'i';
}
if ($sel_section > 0) {
    $where[] = "s.section_id = ?";
    $params[] = $sel_section;
    $types .= 'i';
}
if ($sel_report === 'partial') {
    $where[] = "c.status = 'partial'";
} elseif ($sel_report === 'unpaid') {
    $where[] = "c.status = 'unpaid'";
}
if ($sel_fee_head > 0) {
    $where[] = "EXISTS (SELECT 1 FROM fee_challan_items i WHERE i.challan_id = c.challan_id AND i.head_id = ?)";
    $params[] = $sel_fee_head;
    $types .= 'i';
}
if ($sel_student_status === 'Active') {
    $where[] = "s.status = 1";
} elseif ($sel_student_status === 'StruckOff') {
    $where[] = "(s.status != 1 OR s.status IS NULL)";
}

$orderBy = 'c.year DESC, CAST(c.month AS UNSIGNED) DESC, c.challan_id';
if ($sel_order === 'GRnoWise') $orderBy = 's.gr_no, c.challan_id';
elseif ($sel_order === 'Alphabet') $orderBy = 's.first_name, s.last_name, c.challan_id';
elseif ($sel_order === 'Class') $orderBy = 'c.class_id, s.gr_no, c.challan_id';

$sql = "SELECT c.challan_id, c.challan_no, c.student_id, c.class_id, c.month, c.year, c.total_amount, c.paid_amount, c.status, c.commitment, c.commitment_date, c.fee_remarks, c.reminder_sent, c.reminder_sent_at,
        s.first_name, s.last_name, s.father_name, s.gr_no, s.phone, s.whatsapp_number, s.status student_status,
        s.family_code, s.father_cellno, s.mother_cell,
        cl.class_name, sec.section_name,
        (SELECT MAX(a.date) FROM attendance a WHERE a.student_id = s.student_id AND a.status = 'present') AS last_present,
        (SELECT COALESCE(SUM(c2.total_amount - c2.paid_amount),0) FROM fee_challans c2
            WHERE c2.student_id = c.student_id AND c2.challan_id != c.challan_id
            AND c2.status != 'paid' AND c2.year <= c.year) AS arrears,
        (SELECT MAX(p2.created_at) FROM fee_payments p2 JOIN fee_challans c3 ON p2.challan_id = c3.challan_id
            WHERE c3.student_id = c.student_id) AS last_paid
        FROM fee_challans c
        LEFT JOIN students s ON c.student_id = s.student_id
        LEFT JOIN classes cl ON c.class_id = cl.class_id
        LEFT JOIN sections sec ON s.section_id = sec.section_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY $orderBy";

$rows = [];
if (count($params) > 0) {
    $st = db_prepare($sql);
    $st->bind_param($types, ...$params);
    $st->execute();
    $res = $st->get_result();
} else {
    $res = db_query($sql);
}
while ($row = $res->fetch_assoc()) {
    $due = (float) $row['total_amount'] - (float) $row['paid_amount'];
    $row['_due'] = $due;
    $row['_payable'] = (float) $row['arrears'] + $due;
    if ($sel_arrears === 'Above' && $row['_payable'] < $sel_arrear_amount) continue;
    if ($sel_arrears === 'Below' && $row['_payable'] > $sel_arrear_amount) continue;
    $rows[] = $row;
}

$cs = get_setting('currency_symbol', 'Rs.');

include __DIR__ . '/includes/header.php';
?>
<style>
.fee-report-toolbar { background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:14px 16px; margin-bottom:16px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; }
.fee-report-toolbar .btn { border-radius:9px; }
#listofstudents { width:100%; background:#fff; font-size:13px; margin-bottom:0; }
#listofstudents thead th { background:#F9FAFB; }
.data-row.struckoff-row td { background:#FFF1F2; color:#9CA3AF; }
.struckoff-tag { background:#FEE2E2; color:#DC2626; border-radius:999px; padding:1px 8px; font-size:11px; font-weight:700; margin-left:6px; }
.commit-tag { background:#E0E7FF; color:#4338CA; border-radius:999px; padding:1px 8px; font-size:11px; font-weight:700; margin-left:6px; cursor:help; }
.fee-mode-badge { padding:3px 10px; border-radius:999px; font-size:12px; font-weight:700; }
.fee-empty-state { text-align:center; color:#6B7280; padding:50px 20px; background:#fff; border:1px solid #E5E7EB; border-radius:14px; }
.fee-empty-state i { font-size:42px; color:#D1D5DB; margin-bottom:12px; }
.report-kpis { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:16px; }
.report-kpi { background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:14px 16px; border-left:5px solid #F59E0B; }
.report-kpi .label { font-size:12px; color:#6B7280; text-transform:uppercase; letter-spacing:.3px; }
.report-kpi .value { font-size:21px; font-weight:800; color:#111827; }
.report-kpi.red { border-left-color:#EF4444; } .report-kpi.green { border-left-color:#10B981; } .report-kpi.amber { border-left-color:#F59E0B; }
@media (max-width:900px){ .report-kpis { grid-template-columns:repeat(2,1fr); } }
</style>

<div class="main-content">
    <div class="container-fluid">
        <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; padding:14px 4px;">
            <h3 style="font-size:18px; font-weight:800; color:#111827; margin:0;"><i class="fa fa-file-text-o"></i> Unpaid Fee Students List</h3>
            <div>
                <button type="button" class="btn btn-info" data-toggle="modal" data-target="#Modal-"><i class="fa fa-filter"></i> Apply Filters</button>
                <button type="button" class="btn btn-default" data-toggle="modal" data-target="#customize"><i class="fa fa-columns"></i> Display / Hide Columns</button>
                <a href="<?php echo BASE_URL; ?>new_message.php" class="btn btn-success" style="background-color:#25D366; border-color:#25D366;"><i class="fa fa-whatsapp"></i> Send via WhatsApp</a>
                <button type="button" class="btn btn-warning" id="sendPendingSmsBtn"><i class="fa fa-clock-o"></i> Send Pending Fee Defaulter Reminders</button>
            </div>
        </div>

        <?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>

        <div class="report-toolbar">
            <div style="font-size:13px; color:#374151;">
                <strong>Report Month:</strong> <?php echo e($sel_month); ?>
                &nbsp;|&nbsp; <strong>Session:</strong> <?php echo $sel_session === 'All' ? 'All Sessions' : e($sel_session); ?>
                &nbsp;|&nbsp; <strong>Class:</strong> <?php echo $sel_class === 'All' ? 'All' : e($sel_class); ?>
                &nbsp;|&nbsp; <strong><?php echo count($rows); ?></strong> unsettled challan(s)
            </div>
            <div id="smsStatus" style="font-size:13px; font-weight:700; color:#16A34A;"></div>
        </div>

        <div class="report-kpis">
            <?php
            $kpi_total = 0.0; $kpi_due = 0.0; $kpi_payable = 0.0; $kpi_students = [];
            foreach ($rows as $r) { $kpi_total += (float) $r['total_amount']; $kpi_due += $r['_due']; $kpi_payable += $r['_payable']; $kpi_students[(int) $r['student_id']] = 1; }
            ?>
            <div class="report-kpi red"><div class="label">Outstanding Challans</div><div class="value"><?php echo count($rows); ?></div></div>
            <div class="report-kpi amber"><div class="label">Total Demanded</div><div class="value"><?php echo $cs . number_format($kpi_total, 2); ?></div></div>
            <div class="report-kpi red"><div class="label">Total Payables</div><div class="value"><?php echo $cs . number_format($kpi_payable, 2); ?></div></div>
            <div class="report-kpi green"><div class="label">Students</div><div class="value"><?php echo count($kpi_students); ?></div></div>
        </div>

        <?php if (count($rows) === 0): ?>
            <div class="fee-empty-state">
                <i class="fa fa-check-circle"></i>
                <p style="margin:0 0 6px 0; font-size:15px; font-weight:700; color:#374151;">No unsettled fee challans found.</p>
                <span>Adjust the filters using <strong>Apply Filters</strong> to load the list.</span>
            </div>
        <?php else: ?>
        <div style="overflow-x:auto; background:#fff; border:1px solid #E5E7EB; border-radius:14px;">
            <table class="table table-hover table-bordered" id="listofstudents" style="border-collapse:collapse;">
                <thead>
                    <tr>
                        <th style="text-align:center; width:44px;">S.No</th>
                        <th style="text-align:center; width:36px;"><input type="checkbox" id="checkAll" checked></th>
                        <th>Student Name</th>
                        <th class="feecol-grNo">GR.No</th>
                        <th class="feecol-chNo">Challan No</th>
                        <th class="feecol-father_name">Father Name</th>
                        <th class="feecol-class_sec">Class/Section</th>
                        <th class="feecol-familyCode">Family Code</th>
                        <th>Last Paid Date</th>
                        <th class="feecol-monthly">Monthly Fee</th>
                        <th class="feecol-misfee">Misc Fee</th>
                        <th class="feecol-transport">Transport Fee</th>
                        <th class="feecol-discount">Discount</th>
                        <th class="feecol-total">Total</th>
                        <th class="feecol-paidfee">Paid</th>
                        <th class="feecol-feeRemarks">Fee Remarks</th>
                        <th>Previous Arrears</th>
                        <th style="border-right:2px solid #111;">Total Payables</th>
                        <th class="feecol-commitment">Commitment</th>
                        <th class="feecol-cell_no">Cell No</th>
                        <th class="feecol-father_cellno">Father No</th>
                        <th class="feecol-mother_cell">Mother No</th>
                        <th class="feecol-last_present">Last Present</th>
                        <th style="width:90px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach ($rows as $r):
                        $struck = (int) ($r['student_status'] ?? 1) !== 1;
                        $last_paid = $r['last_paid'] ? date('d-M-Y', strtotime($r['last_paid'])) : '';
                        $mode_badge = 'background:#FEE2E2;color:#DC2626;';
                        if ($r['status'] === 'partial') $mode_badge = 'background:#FFF7E0;color:#F59E0B;';
                        $wa_num = '';
                        $num = trim($r['whatsapp_number'] ?: $r['phone'] ?: '');
                        $num = preg_replace('/[^0-9]/', '', $num);
                        if ($num !== '') {
                            if (substr($num, 0, 1) === '0') $num = substr($num, 1);
                            if (strlen($num) === 10) $num = '92' . $num;
                            $wa_num = $num;
                        }
                    ?>
                        <tr class="data-row <?php echo $struck ? 'struckoff-row' : ''; ?>"
                            data-sid="<?php echo (int) $r['student_id']; ?>"
                            data-sname="<?php echo e($r['first_name'] . ' ' . $r['last_name']); ?>"
                            data-challan="<?php echo (int) $r['challan_id']; ?>"
                            data-challanno="<?php echo e($r['challan_no']); ?>"
                            data-due="<?php echo $r['_due']; ?>"
                            data-total="<?php echo (float) $r['total_amount']; ?>"
                            data-wa="<?php echo e($wa_num); ?>">
                            <td class="center"><?php echo $i++; ?></td>
                            <td class="center"><input type="checkbox" name="students[]" class="student-check" checked></td>
                            <td>
                                <strong><?php echo e($r['first_name'] . ' ' . $r['last_name']); ?></strong>
                                <?php if ($struck): ?><span class="struckoff-tag">Struck Off</span><?php endif; ?>
                                <span class="fee-mode-badge" style="<?php echo $mode_badge; ?>"><?php echo ucfirst($r['status']); ?></span>
                                <?php if (!empty($r['reminder_sent'])): ?><span class="struckoff-tag" style="background:#D1FAE5; color:#047857;" title="Reminder sent <?php echo $r['reminder_sent_at'] ? date('d-M-Y H:i', strtotime($r['reminder_sent_at'])) : ''; ?>">Reminder Sent</span><?php endif; ?>
                                <?php if ($r['commitment_date']): ?><span class="commit-tag" title="<?php echo e($r['commitment']); ?>">Commit till <?php echo date('d-M-Y', strtotime($r['commitment_date'])); ?></span><?php endif; ?>
                            </td>
                            <td class="feecol-grNo"><?php echo e($r['gr_no'] ?? '-'); ?></td>
                            <td class="feecol-chNo"><?php echo e($r['challan_no']); ?></td>
                            <td class="feecol-father_name"><?php echo e($r['father_name'] ?? '-'); ?></td>
                            <td class="feecol-class_sec"><?php echo e($r['class_name'] ?? '-') . ($r['section_name'] ? '-' . e($r['section_name']) : ''); ?></td>
                            <td class="feecol-familyCode"><?php echo e($r['family_code'] ?? ''); ?></td>
                            <td><?php echo e($last_paid ?: '—'); ?></td>
                            <td class="feecol-monthly" style="text-align:right;"><?php echo $cs . number_format($r['_payable'], 2); ?></td>
                            <td class="feecol-misfee" style="text-align:right;">0.00</td>
                            <td class="feecol-transport" style="text-align:right;">0.00</td>
                            <td class="feecol-discount" style="text-align:right;">0.00</td>
                            <td class="feecol-total" style="text-align:right;"><?php echo number_format($r['total_amount'], 2); ?></td>
                            <td class="feecol-paidfee" style="text-align:right; color:#16A34A;"><?php echo number_format($r['paid_amount'], 2); ?></td>
                            <td class="feecol-feeRemarks"><?php echo e($r['fee_remarks'] ?? ''); ?></td>
                            <td style="text-align:right; color:#DC2626;"><?php echo number_format($r['arrears'], 2); ?></td>
                            <td style="text-align:right; color:#DC2626; font-weight:800; border-right:2px solid #111;"><?php echo number_format($r['_payable'], 2); ?></td>
                            <td class="feecol-commitment" style="text-align:center;">
                                <button class="btn btn-sm btn-default commitment-btn" title="Add Fee Commitment"
                                    data-sid="<?php echo (int) $r['student_id']; ?>"
                                    data-cid="<?php echo (int) $r['challan_id']; ?>"
                                    data-sname="<?php echo e($r['first_name'] . ' ' . $r['last_name']); ?>"
                                    data-challanno="<?php echo e($r['challan_no']); ?>"><i class="fa fa-handshake-o"></i></button>
                            </td>
                            <td class="feecol-cell_no"><?php echo e($r['phone'] ?? ''); ?></td>
                            <td class="feecol-father_cellno"><?php echo e($r['father_cellno'] ?? ''); ?></td>
                            <td class="feecol-mother_cell"><?php echo e($r['mother_cell'] ?? ''); ?></td>
                            <td class="feecol-last_present"><?php echo $r['last_present'] ? date('d-M-Y', strtotime($r['last_present'])) : '—'; ?></td>
                            <td>
                                <?php if ($wa_num !== ''): ?>
                                <a href="https://wa.me/<?php echo e($wa_num); ?>?text=<?php echo rawurlencode('Dear Parent, Kindly clear your pending fee challan ' . $r['challan_no'] . ' (' . $r['month'] . '/' . $r['year'] . ') amounting to ' . $cs . number_format($r['_due'], 2) . '. - ' . get_setting('school_name', 'School')); ?>" target="_blank" class="btn btn-success btn-xs" title="WhatsApp"><i class="fa fa-whatsapp"></i></a>
                                <?php endif; ?>
                                <button class="btn btn-primary btn-xs commitment-btn" title="Add Fee Commitment"
                                    data-sid="<?php echo (int) $r['student_id']; ?>"
                                    data-cid="<?php echo (int) $r['challan_id']; ?>"
                                    data-sname="<?php echo e($r['first_name'] . ' ' . $r['last_name']); ?>"
                                    data-challanno="<?php echo e($r['challan_no']); ?>"><i class="fa fa-handshake-o"></i></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Apply Filters Modal -->
<div class="modal fade" id="Modal-" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" style="width:88%;">
        <div class="modal-content" style="border-radius:14px;">
            <form method="get" action="print_unpaid_fee_new.php">
                <input type="hidden" name="action" value="recevabl">
                <input type="hidden" name="recevabl" value="1">
                <div class="modal-header" style="border-bottom:1px solid #E5E7EB; padding:16px 20px;">
                    <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title" style="margin:0; font-weight:800; font-size:16px;"><i class="fa fa-filter"></i> Apply Filters</h4>
                </div>
                <div class="modal-body" style="padding:10px 20px;">
                    <div class="row">
                        <div class="col-md-3 col-xs-12" style="padding:8px;">
                            <div class="form-group" style="margin-bottom:6px;">
                                <label style="font-size:12px;font-weight:700;">Session</label>
                                <select name="session" class="form-control">
                                    <option value="All" <?php echo $sel_session === 'All' ? 'selected' : ''; ?>>All Sessions</option>
                                    <?php foreach ($sessions as $sv): ?>
                                        <option value="<?php echo e($sv); ?>" <?php echo $sel_session === $sv ? 'selected' : ''; ?>><?php echo e($sv); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3 col-xs-12" style="padding:8px;">
                            <div class="form-group" style="margin-bottom:6px;">
                                <label style="font-size:12px;font-weight:700;">Course/Class</label>
                                <select name="class_id" id="class_dropdown" class="form-control" onchange="document.querySelector('#txt_section_modal').innerHTML='<option value=\'0\'>All</option>';">
                                    <option value="All" <?php echo $sel_class === 'All' ? 'selected' : ''; ?>>All</option>
                                    <?php foreach ($classes as $cl): ?>
                                        <option value="<?php echo $cl['class_id']; ?>" <?php echo $sel_class === (string)$cl['class_id'] ? 'selected' : ''; ?>><?php echo e($cl['class_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3 col-xs-12" style="padding:8px;">
                            <div class="form-group" style="margin-bottom:6px;">
                                <label style="font-size:12px;font-weight:700;">Section</label>
                                <select name="section" id="txt_section_modal" class="form-control">
                                    <option value="0">All</option>
                                    <?php foreach ($sections as $sec): ?>
                                        <option value="<?php echo $sec['section_id']; ?>" <?php echo $sel_section === (int)$sec['section_id'] ? 'selected' : ''; ?>><?php echo e($sec['section_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3 col-xs-12" style="padding:8px;">
                            <div class="form-group" style="margin-bottom:6px;">
                                <label style="font-size:12px;font-weight:700;">Challan Month (MM/YYYY)</label>
                                <input type="text" name="month_year" class="form-control" value="<?php echo e($sel_month); ?>" placeholder="MM/YYYY">
                            </div>
                        </div>
                        <div class="col-md-3 col-xs-12" style="padding:8px;">
                            <div class="form-group" style="margin-bottom:6px;">
                                <label style="font-size:12px;font-weight:700;">Sort Students</label>
                                <select name="orderBy" class="form-control">
                                    <option value="GRnoWise" <?php echo $sel_order === 'GRnoWise' ? 'selected' : ''; ?>>By GR No</option>
                                    <option value="Alphabet" <?php echo $sel_order === 'Alphabet' ? 'selected' : ''; ?>>By Alphabet</option>
                                    <option value="Class" <?php echo $sel_order === 'Class' ? 'selected' : ''; ?>>By Course/Class</option>
                                    <option value="DefaultOrder" <?php echo $sel_order === 'DefaultOrder' ? 'selected' : ''; ?>>Default Order</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3 col-xs-12" style="padding:8px;">
                            <div class="form-group" style="margin-bottom:6px;">
                                <label style="font-size:12px;font-weight:700;">Filter by Payment Status</label>
                                <select name="reportType" class="form-control">
                                    <option value="All" <?php echo $sel_report === 'All' ? 'selected' : ''; ?>>All (Partial &amp; Unpaid)</option>
                                    <option value="partial" <?php echo $sel_report === 'partial' ? 'selected' : ''; ?>>Partially Paid</option>
                                    <option value="unpaid" <?php echo $sel_report === 'unpaid' ? 'selected' : ''; ?>>Unpaid Challans</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3 col-xs-12" style="padding:8px;">
                            <div class="form-group" style="margin-bottom:6px;">
                                <label style="font-size:12px;font-weight:700;">Arrears Filter</label>
                                <select name="arrearsType" class="form-control" id="arrearsType">
                                    <option value="">All</option>
                                    <option value="Above" <?php echo $sel_arrears === 'Above' ? 'selected' : ''; ?>>Above Amount</option>
                                    <option value="Below" <?php echo $sel_arrears === 'Below' ? 'selected' : ''; ?>>Below Amount</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3 col-xs-12" style="padding:8px;">
                            <div class="form-group" style="margin-bottom:6px;">
                                <label style="font-size:12px;font-weight:700;">Enter Arrears Amount</label>
                                <input type="number" step="0.01" min="0" name="arrearAmount" class="form-control" value="<?php echo $sel_arrear_amount > 0 ? $sel_arrear_amount : ''; ?>">
                            </div>
                        </div>
                        <div class="col-md-3 col-xs-12" style="padding:8px;">
                            <div class="form-group" style="margin-bottom:6px;">
                                <label style="font-size:12px;font-weight:700;">Students Status</label>
                                <select name="student_status" class="form-control">
                                    <option value="All" <?php echo $sel_student_status === 'All' ? 'selected' : ''; ?>>All</option>
                                    <option value="Active" <?php echo $sel_student_status === 'Active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="StruckOff" <?php echo $sel_student_status === 'StruckOff' ? 'selected' : ''; ?>>Struck Off</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3 col-xs-12" style="padding:8px;">
                            <div class="form-group" style="margin-bottom:6px;">
                                <label style="font-size:12px;font-weight:700;">Fee Head</label>
                                <select name="feeHeadFilter" class="form-control">
                                    <option value="0">All Fee Heads</option>
                                    <?php foreach ($feeHeads as $fh): ?>
                                        <option value="<?php echo $fh['head_id']; ?>" <?php echo $sel_fee_head === (int)$fh['head_id'] ? 'selected' : ''; ?>><?php echo e($fh['head_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="border-top:1px solid #E5E7EB;">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Apply Filter</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Display / Hide Columns Modal -->
<div class="modal fade" id="customize" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" style="width:88%;">
        <div class="modal-content" style="border-radius:14px;">
            <div class="modal-header" style="border-bottom:1px solid #E5E7EB; padding:16px 20px;">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" style="margin:0; font-weight:800; font-size:16px;"><i class="fa fa-columns"></i> Display / Hide Columns</h4>
            </div>
            <div class="modal-body" style="padding:10px 20px;">
                <div class="row print-checkbox-grid">
                    <?php
                    $toggles = [
                        'grNo' => 'GR.No', 'chNo' => 'Challan No', 'father_name' => 'Father Name', 'class_sec' => 'Class/Section',
                        'familyCode' => 'Family Code', 'monthly' => 'Monthly Fee', 'misfee' => 'Misc Fee', 'transport' => 'Transport Fee',
                        'discount' => 'Discount', 'total' => 'Total', 'paidfee' => 'Paid', 'feeRemarks' => 'Fee Remarks',
                        'cell_no' => 'Cell No', 'father_cellno' => 'Father No', 'mother_cell' => 'Mother No', 'last_present' => 'Last Present',
                        'commitment' => 'Commitment',
                    ];
                    foreach ($toggles as $name => $label): ?>
                        <div class="col-md-3 col-xs-12" style="padding:4px 8px;">
                            <label style="font-size:13px; font-weight:600; display:flex; align-items:center; gap:6px;">
                                <input type="checkbox" class="col-toggle" data-col="<?php echo $name; ?>" checked> <?php echo $label; ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="modal-footer" style="border-top:1px solid #E5E7EB;">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="applyColToggle"><i class="fa fa-check"></i> Apply</button>
            </div>
        </div>
    </div>
</div>

<!-- Defaulter Reminder Form (hidden) -->
<form id="defaulterReminderForm" method="post" action="print_unpaid_fee_new.php" style="display:none;">
    <input type="hidden" name="action" value="send_defaulter_reminders">
    <div id="reminderChallanIds"></div>
</form>

<!-- Fee Commitment Modal -->
<div class="modal fade" id="feeCommitmentModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" style="max-width:500px;">
        <div class="modal-content" style="border-radius:14px;">
            <div class="modal-header" style="border-bottom:1px solid #E5E7EB; padding:16px 20px; background:#F59E0B;">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" style="margin:0; font-weight:800; font-size:16px; color:#fff;"><i class="fa fa-handshake-o"></i> Fee Commitment — <span id="studentNameDisplay"></span></h4>
                <div style="color:#fff; font-size:12px; margin-top:2px;" id="challanNoDisplay"></div>
            </div>
            <form method="post" action="print_unpaid_fee_new.php">
                <input type="hidden" name="action" value="save_fee_commitment">
                <input type="hidden" name="student_id" id="commitment_student_id">
                <input type="hidden" name="challan_id" id="commitment_challan_id">
                <div class="modal-body" style="padding:20px;">
                    <div class="form-group">
                        <label style="font-size:12px;font-weight:700;">Commitment Date</label>
                        <input type="date" name="commitment_date" id="commitment_date_new" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label style="font-size:12px;font-weight:700;">Fee Commitment</label>
                        <textarea name="fee_commitment" id="fee_commitment_new" rows="4" class="form-control" placeholder="Enter fee commitment details..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="border-top:1px solid #E5E7EB;">
                    <button type="button" class="btn btn-default" data-dismiss="modal"><i class="fa fa-times"></i> Close</button>
                    <button type="submit" class="btn btn-success"><i class="fa fa-save"></i> Save Commitment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('checkAll').addEventListener('change', function(){
    document.querySelectorAll('.student-check').forEach(function(cb){ cb.checked = document.getElementById('checkAll').checked; });
});
document.querySelectorAll('.commitment-btn').forEach(function(btn){
    btn.addEventListener('click', function(){
        document.getElementById('commitment_student_id').value = this.dataset.sid;
        document.getElementById('commitment_challan_id').value = this.dataset.cid;
        document.getElementById('studentNameDisplay').textContent = this.dataset.sname;
        document.getElementById('challanNoDisplay').textContent = 'Challan: ' + this.dataset.challanno;
        document.getElementById('commitment_date_new').value = '';
        document.getElementById('fee_commitment_new').value = '';
        jQuery('#feeCommitmentModal').modal('show');
    });
});
document.getElementById('applyColToggle').addEventListener('click', function(){
    document.querySelectorAll('.col-toggle').forEach(function(cb){
        var cls = 'feecol-' + cb.dataset.col;
        document.querySelectorAll('.' + cls).forEach(function(td){
            td.style.display = cb.checked ? '' : 'none';
        });
    });
    jQuery('#customize').modal('hide');
});
document.getElementById('sendPendingSmsBtn').addEventListener('click', function(){
    var selected = document.querySelectorAll('.student-check:checked');
    if (selected.length === 0) { alert('Please select at least one student to send reminders.'); return; }
    var challanIds = [];
    selected.forEach(function(cb){
        var cid = cb.closest('tr') ? (cb.closest('tr').dataset.challan || '') : '';
        if (cid) challanIds.push(cid);
    });
    if (challanIds.length === 0) { alert('No challan found for the selected students.'); return; }
    if (!confirm('Mark ' + challanIds.length + ' selected challan(s) as reminded? This updates the reminder flag.')) return;
    var form = document.getElementById('defaulterReminderForm');
    if (!form) { alert('Reminder form not found.'); return; }
    var wrapper = form.querySelector('#reminderChallanIds');
    wrapper.innerHTML = '';
    challanIds.forEach(function(ci){
        var inp = document.createElement('input');
        inp.type = 'hidden';
        inp.name = 'challan_ids[]';
        inp.value = ci;
        wrapper.appendChild(inp);
    });
    form.submit();
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
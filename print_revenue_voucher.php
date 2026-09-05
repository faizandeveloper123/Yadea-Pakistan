<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Revenue Voucher';

$fFrom = $_GET['from'] ?? '';
$fTo = $_GET['to'] ?? '';

$where = [];
$params = [];
$types = '';
if ($fFrom !== '') { $where[] = "r.paid_date >= ?"; $params[] = $fFrom; $types .= 's'; }
if ($fTo !== '') { $where[] = "r.paid_date <= ?"; $params[] = $fTo; $types .= 's'; }

$sql = "SELECT r.*, h.head_name,
        CONCAT(s.first_name, ' ', COALESCE(s.father_name, '')) AS student_display,
        cl.class_name, sec.section_name
        FROM revenues r
        LEFT JOIN revenue_heads h ON r.head_id = h.head_id
        LEFT JOIN students s ON r.student_id = s.student_id
        LEFT JOIN classes cl ON s.class_id = cl.class_id
        LEFT JOIN sections sec ON s.section_id = sec.section_id";
if (count($where) > 0) { $sql .= " WHERE " . implode(' AND ', $where); }
$sql .= " ORDER BY COALESCE(r.paid_date, r.revenue_date), r.head_id, r.revenue_id";

$rows = [];
if (count($params) > 0) {
    $st2 = db_prepare($sql);
    $st2->bind_param($types, ...$params);
    $st2->execute();
    $res = $st2->get_result();
} else {
    $res = db_query($sql);
}
while ($row = $res->fetch_assoc()) { $rows[] = $row; }

$grand = 0.0;
$byHead = [];
foreach ($rows as $r) {
    $grand += (float) $r['amount'];
    $key = $r['head_name'] ?: 'Miscellaneous';
    if (!isset($byHead[$key])) { $byHead[$key] = ['name' => $key, 'total' => 0.0, 'rows' => []]; }
    $byHead[$key]['total'] += (float) $r['amount'];
    $byHead[$key]['rows'][] = $r;
}

$schoolName = get_setting('school_name', 'School Name');
$schoolAddr = get_setting('school_address', '');

include __DIR__ . '/includes/header.php';
?>
<style>
@media print {
    .main-content { margin: 0; }
    .no-print { display: none !important; }
}
.report-header { text-align:center; margin-bottom:16px; }
.report-header h2 { font-size:20px; font-weight:800; color:#111827; margin:0 0 4px; }
.report-header p { margin:2px 0; color:#6B7280; font-size:13px; }
.voucher-single { page-break-inside: avoid; margin-bottom:22px; }
.voucher-single h4 { font-weight:800; border-bottom:2px solid #111827; padding-bottom:6px; }
</style>

<div class="main-content">
    <div class="container-fluid">
        <div class="no-print" style="display:flex; align-items:center; justify-content:space-between; padding:14px 4px;">
            <h3 style="font-size:18px; font-weight:800; color:#111827; margin:0;"><i class="fa fa-print"></i> Revenue Voucher</h3>
            <button onclick="window.print()" class="btn btn-success" style="color:#fff;"><i class="fa fa-print"></i> Print Report</button>
        </div>

        <div style="background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:24px;">
            <div class="report-header">
                <h2><?php echo e($schoolName); ?></h2>
                <?php if ($schoolAddr !== ''): ?><p><?php echo e($schoolAddr); ?></p><?php endif; ?>
                <p><strong>REVENUE VOUCHER LIST</strong></p>
                <p>Period: <?php echo $fFrom !== '' ? e($fFrom) : 'All'; ?> To: <?php echo $fTo !== '' ? e($fTo) : 'Today'; ?></p>
            </div>

            <?php if (count($byHead) === 0): ?>
                <p style="text-align:center; color:#6B7280; padding:30px 0;">No voucher record found.</p>
            <?php endif; ?>

            <?php foreach ($byHead as $group): ?>
                <div class="voucher-single">
                    <h4><?php echo e($group['name']); ?></h4>
                    <table class="table table-striped table-bordered" style="width:100%; background:#fff; margin-bottom:0;">
                        <thead>
                            <tr>
                                <th width="5%">S.No</th>
                                <th width="22%">Student Name</th>
                                <th width="10%">Month</th>
                                <th width="12%">Date</th>
                                <th width="31%">Remarks</th>
                                <th width="13%" style="text-align:right;">Amount (Rs.)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 1; foreach ($group['rows'] as $r): ?>
                                <?php $payDate = $r['paid_date'] ?: $r['revenue_date']; ?>
                                <tr>
                                    <td><?php echo $i++; ?></td>
                                    <td>
                                        <?php if (!empty($r['student_display']) && trim($r['student_display']) !== ''): ?>
                                            <?php echo e($r['student_display']); ?>
                                            <small style="color:#6B7280;"><?php echo e($r['class_name'] ?? ''); ?><?php echo !empty($r['section_name']) ? ' - ' . e($r['section_name']) : ''; ?></small>
                                        <?php else: ?>
                                            Other's
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $payDate ? date('M Y', strtotime($payDate)) : '-'; ?></td>
                                    <td><?php echo $payDate ? date('d M Y', strtotime($payDate)) : '-'; ?></td>
                                    <td><?php echo e($r['remarks'] ?: $r['description'] ?: '-'); ?></td>
                                    <td style="text-align:right; color:#16A34A; font-weight:700;"><?php echo number_format($r['amount'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr style="background:#F9FAFB;">
                                <th colspan="5" style="text-align:right;">Voucher Total</th>
                                <th style="text-align:right; color:#16A34A;"><?php echo number_format($group['total'], 2); ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php endforeach; ?>

            <div style="margin-top:10px; text-align:right; font-size:16px; font-weight:800;">
                <span style="color:#6B7280; font-weight:600;">Grand Total: </span>
                <span style="color:#16A34A;"><?php echo get_setting('currency_symbol', 'Rs.') . ' ' . number_format($grand, 2); ?></span>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Date Wise Total Collection';

$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');

$rows = [];
$params = [];
$types = 'ss';
$params[] = $from;
$params[] = $to;

$st2 = db_prepare("SELECT DATE(p.created_at) pay_date, COUNT(*) payments, SUM(p.amount) day_total
                   FROM fee_payments p
                   WHERE DATE(p.created_at) BETWEEN ? AND ?
                   GROUP BY DATE(p.created_at)
                   ORDER BY pay_date");
$st2->bind_param($types, ...$params);
$st2->execute();
$res = $st2->get_result();
while ($row = $res->fetch_assoc()) { $rows[] = $row; }

$grand = 0.0;
foreach ($rows as $r) { $grand += (float) $r['day_total']; }

include __DIR__ . '/includes/header.php';
?>
<style>
.page-header-section { margin-bottom:14px; }
.page-header-section h2 { font-size:18px; font-weight:800; color:#111827; margin:0; }
.record-count-badge { display:inline-block; font-size:11px; font-weight:700; color:#377DFF; background:#E9F2FF; border-radius:999px; padding:4px 10px; margin-left:8px; vertical-align:middle; }
.breadcrumb-modern { display:flex; align-items:center; gap:8px; font-size:12.5px; color:#6B7280; margin:6px 0 0; padding:0; list-style:none; }
.breadcrumb-modern a { color:#377DFF; text-decoration:none; }
.breadcrumb-modern i { font-size:11px; color:#9CA3AF; }
</style>

<div class="main-content">
    <div class="container-fluid">
        <div class="page-header-section">
            <h2><i class="fa fa-calendar"></i> Date Wise Total Collection <span class="record-count-badge"><?php echo count($rows); ?> Days</span></h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb-modern">
                    <li><a href="<?php echo BASE_URL; ?>dashboard.php"><i class="fa fa-home"></i> Dashboard</a></li>
                    <li><i class="fa fa-angle-right"></i></li>
                    <li><a href="<?php echo BASE_URL; ?>datewise_fee_collection_report_new.php">Fee Reports</a></li>
                    <li><i class="fa fa-angle-right"></i></li>
                    <li><span>Date Wise Total Collection</span></li>
                </ol>
            </nav>
        </div>

        <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; padding:14px 4px;">
            <a href="<?php echo BASE_URL; ?>datewise_fee_collection_report_new.php" class="btn btn-info" style="color:#fff;"><i class="fa fa-arrow-left"></i> Back to Report</a>
            <button onclick="window.print()" class="btn btn-success" style="color:#fff;"><i class="fa fa-print"></i> Print Report</button>
        </div>

        <form method="get" action="datewise_fee_collection_new.php" style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end; background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:16px; margin-bottom:16px;">
            <div class="form-group col-md-3" style="margin-bottom:0;">
                <label>From Date</label>
                <input type="date" name="from" class="form-control" value="<?php echo e($from); ?>">
            </div>
            <div class="form-group col-md-3" style="margin-bottom:0;">
                <label>To Date</label>
                <input type="date" name="to" class="form-control" value="<?php echo e($to); ?>">
            </div>
            <div class="form-group col-md-2" style="margin-bottom:0;">
                <button type="submit" class="btn btn-primary" style="width:100%;"><i class="fa fa-filter"></i> Filter</button>
            </div>
            <div class="form-group col-md-4" style="margin-bottom:0; font-size:13px; color:#6B7280; padding-top:22px;">
                Grand Total Collected: <strong style="color:#16A34A; font-size:16px;"><?php echo get_setting('currency_symbol', 'Rs.') . number_format($grand, 2); ?></strong>
            </div>
        </form>

        <div style="overflow-x:auto; background:#fff; border:1px solid #E5E7EB; border-radius:14px;">
            <table class="table table-striped table-bordered" style="width:100%; background:#fff; margin-bottom:0;">
                <thead>
                    <tr><th width="10%">S.No</th><th width="40%">Date</th><th width="25%" style="text-align:center;">No. of Payments</th><th width="25%" style="text-align:right;">Day Collection</th></tr>
                </thead>
                <tbody>
                    <?php if (count($rows) === 0): ?>
                        <tr><td colspan="4" style="text-align:center; color:#6B7280; padding:25px;">No records found for the selected date range.</td></tr>
                    <?php endif; ?>
                    <?php $i = 1; foreach ($rows as $r): ?>
                        <tr>
                            <td><?php echo $i++; ?></td>
                            <td><strong><?php echo date('l, d M Y', strtotime($r['pay_date'])); ?></strong></td>
                            <td style="text-align:center;"><?php echo (int) $r['payments']; ?></td>
                            <td style="text-align:right; color:#16A34A; font-weight:700;"><?php echo number_format($r['day_total'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background:#F9FAFB;">
                        <th colspan="3" style="text-align:right;">Grand Total</th>
                        <th style="text-align:right; color:#16A34A;"><?php echo number_format($grand, 2); ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
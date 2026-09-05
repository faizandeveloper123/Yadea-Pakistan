<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Fee Analytics Dashboard';

$sel_month = $_GET['month_year'] ?? date('Y-m');
if (!preg_match('/^(\d{4})-(\d{2})$/', $sel_month, $mm)) { $sel_month = date('Y-m'); $mm = explode('-', $sel_month); }
$sel_year = (int) $mm[1];
$sel_m = (int) $mm[2];

$cs = get_setting('currency_symbol', 'Rs.');
function money($n) { return number_format((float) $n, 2); }

$monthPay = db_query("SELECT COALESCE(SUM(p.amount),0) t FROM fee_payments p WHERE YEAR(p.created_at)=$sel_year AND MONTH(p.created_at)=$sel_m")->fetch_assoc()['t'];
$monthDiscount = db_query("SELECT COALESCE(SUM(p.discount),0) t FROM fee_payments p WHERE YEAR(p.created_at)=$sel_year AND MONTH(p.created_at)=$sel_m")->fetch_assoc()['t'];

$arrSplit = db_query("SELECT
    COALESCE(SUM(CASE WHEN CAST(c.month AS UNSIGNED) = $sel_m THEN p.amount ELSE 0 END),0) cur_amt,
    COALESCE(SUM(CASE WHEN CAST(c.month AS UNSIGNED) < $sel_m THEN p.amount ELSE 0 END),0) arr_amt,
    COALESCE(SUM(CASE WHEN CAST(c.month AS UNSIGNED) > $sel_m THEN p.amount ELSE 0 END),0) adv_amt,
    COALESCE(SUM(p.amount),0) tot_amt
    FROM fee_payments p LEFT JOIN fee_challans c ON p.challan_id = c.challan_id
    WHERE YEAR(p.created_at)=$sel_year AND MONTH(p.created_at)=$sel_m")->fetch_assoc();

$todayPay = db_query("SELECT COALESCE(SUM(p.amount),0) t FROM fee_payments p WHERE DATE(p.created_at)=CURDATE()")->fetch_assoc()['t'];
$starting = $sel_year . '-' . str_pad($sel_m, 2, '0') . '-01';
$total_pay_m = db_query("SELECT COALESCE(SUM(p.amount),0) t FROM fee_payments p WHERE (YEAR(p.created_at) < $sel_year OR (YEAR(p.created_at)=$sel_year AND MONTH(p.created_at) <= $sel_m))")->fetch_assoc()['t'];

$avg_daily = 0;
if ((int)date('Y') === $sel_year && (int)date('m') === $sel_m) {
    $days_elapsed = max(1, (int) date('j'));
    $avg_daily = $total_pay_m / $days_elapsed;
} else {
    $days_in_month = cal_days_in_month(CAL_GREGORIAN, $sel_m, $sel_year);
    $avg_daily = $total_pay_m / max(1, $days_in_month);
}

$receivable = db_query("SELECT COALESCE(SUM(total_amount - paid_amount),0) t FROM fee_challans WHERE status IN ('unpaid','partial')")->fetch_assoc()['t'];
$advance_total = db_query("SELECT COALESCE(SUM(total_amount - paid_amount),0) t FROM fee_challans WHERE CAST(month AS UNSIGNED) > $sel_m AND status != 'paid'")->fetch_assoc()['t'];
$prev_arrears = db_query("SELECT COALESCE(SUM(total_amount - paid_amount),0) t FROM fee_challans WHERE CAST(month AS UNSIGNED) < $sel_m AND status != 'paid'")->fetch_assoc()['t'];

$active_students = (int) db_query("SELECT COUNT(*) c FROM students WHERE status=1")->fetch_assoc()['c'];
$inactive_students = (int) db_query("SELECT COUNT(*) c FROM students WHERE status != 1 OR status IS NULL")->fetch_assoc()['c'];
$total_students = $active_students + $inactive_students;

$trend = [];
$res = db_query("SELECT DATE(p.created_at) d, COALESCE(SUM(p.amount),0) amt
                 FROM fee_payments p
                 WHERE p.created_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
                 GROUP BY DATE(p.created_at)");
while ($row = $res->fetch_assoc()) { $trend[$row['d']] = (float) $row['amt']; }
$trend_points = [];
for ($i = 29; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i day"));
    $trend_points[$d] = $trend[$d] ?? 0;
}

function build_spark($points, $w, $h, $pad) {
    $vals = array_values($points);
    $max = max(1, max($vals));
    $n = count($vals);
    $step = ($w - 2 * $pad) / max(1, $n - 1);
    $coords = [];
    foreach ($vals as $i => $v) {
        $x = $pad + $i * $step;
        $y = $h - $pad - ($v / $max) * ($h - 2 * $pad);
        $coords[] = round($x, 1) . ',' . round($y, 1);
    }
    return ['pts' => implode(' ', $coords), 'area' => $pad . ',' . ($h - $pad) . ' ' . implode(' ', $coords) . ' ' . ($w - $pad) . ',' . ($h - $pad)];
}

$spark = build_spark($trend_points, 660, 200, 24);

$modes = [];
$res = db_query("SELECT p.payment_method, COALESCE(SUM(p.amount),0) amt
                 FROM fee_payments p
                 WHERE YEAR(p.created_at)=$sel_year AND MONTH(p.created_at)=$sel_m
                 GROUP BY p.payment_method ORDER BY amt DESC");
$mode_total = 0;
while ($row = $res->fetch_assoc()) { $modes[] = $row; $mode_total += (float) $row['amt']; }
if ($mode_total <= 0) $mode_total = 1;

$heads = [];
$res = db_query("SELECT h.head_name, COALESCE(SUM(i.amount),0) tot
                 FROM fee_challans c
                 JOIN fee_payments p ON p.challan_id = c.challan_id
                 JOIN fee_challan_items i ON i.challan_id = c.challan_id
                 JOIN fee_heads h ON i.head_id = h.head_id
                 WHERE YEAR(p.created_at)=$sel_year AND MONTH(p.created_at)=$sel_m AND p.amount > 0
                 GROUP BY h.head_name ORDER BY tot DESC LIMIT 6");
$head_total = 0;
while ($row = $res->fetch_assoc()) { $heads[] = $row; $head_total += (float) $row['tot']; }
if ($head_total <= 0) $head_total = 1;

$classHeadCounts = [];
$res = db_query("SELECT h.class_head_name, COUNT(s.student_id) c
                 FROM students s
                 LEFT JOIN classes cl ON s.class_id = cl.class_id
                 LEFT JOIN class_heads h ON cl.class_head_id = h.class_head_id
                 WHERE s.status=1 GROUP BY h.class_head_name ORDER BY c DESC");
while ($row = $res->fetch_assoc()) { $classHeadCounts[] = $row; }

include __DIR__ . '/includes/header.php';
?>
<style>
.ka-card { background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:16px; margin-bottom:14px; }
.ka-kpi { background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:16px; margin-bottom:14px; border-left:5px solid #6366F1; }
.ka-kpi .ka-title { font-size:12px; font-weight:700; color:#6B7280; text-transform:uppercase; letter-spacing:.3px; margin-bottom:6px; }
.ka-kpi .ka-value { font-size:26px; font-weight:800; color:#111827; }
.ka-kpi .ka-sub { font-size:12px; color:#9CA3AF; margin-top:4px; }
.ka-kpi.green { border-left-color:#10B981; } .ka-kpi.red { border-left-color:#EF4444; } .ka-kpi.amber { border-left-color:#F59E0B; } .ka-kpi.indigo { border-left-color:#6366F1; } .ka-kpi.pink { border-left-color:#EC4899; } .ka-kpi.cyan { border-left-color:#06B6D4; } .ka-kpi.teal { border-left-color:#14B8A6; } .ka-kpi.purple { border-left-color:#8B5CF6; }
.ka-chart-title { font-size:14px; font-weight:800; color:#111827; margin:0; }
.ka-chart-sub { font-size:12px; color:#9CA3AF; margin:0 0 12px 0; }
.pbar-row { display:flex; align-items:center; gap:8px; margin-bottom:10px; }
.pbar-label { flex:0 0 120px; font-size:12px; font-weight:600; color:#374151; text-align:right; }
.pbar-track { flex:1; background:#F3F4F6; border-radius:8px; height:18px; overflow:hidden; }
.pbar-fill { height:100%; border-radius:8px; background:linear-gradient(90deg,#6366F1,#8B5CF6); }
.pbar-val { flex:0 0 90px; font-size:12px; font-weight:700; color:#111827; }
.report-link { display:block; border:1px solid #E0E7EF; border-left:4px solid #F59E0B; border-radius:10px; padding:10px 12px; margin-bottom:8px; text-decoration:none; color:inherit; background:#FDFDFD; }
.report-link:hover { background:#FFF7EC; border-color:#F59E0B; }
.report-link .rl-title { font-weight:700; font-size:13px; color:#111827; }
.report-link .rl-sub { font-size:11px; color:#6B7280; margin-top:2px; }
.month-picker { height:32px; border-radius:8px; border:1px solid #E5E7EB; padding:4px 8px; font-size:12px; }
.snap-row { display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px dashed #EEF0F3; }
.snap-row:last-child { border-bottom:none; }
</style>

<div class="main-content">
    <div class="container-fluid" style="padding:20px 24px;">
        <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; padding:6px 2px 18px 2px;">
            <div>
                <span style="color:#6B7280; font-size:12px;">Home docs / Fee Portal </span>
                <i class="fa fa-angle-double-right" style="color:#9CA3AF; font-size:12px;"></i>
                <span style="color:#111827; font-weight:700; font-size:14px;">Fee Analytics Dashboard</span>
            </div>
            <form method="get" action="multi_fee_reports.php" style="margin:0;">
                <label style="font-size:12px; margin-right:6px; color:#374151;">Analytics Month</label>
                <input type="month" name="month_year" value="<?php echo e($sel_month); ?>" class="month-picker" onchange="this.form.submit()">
            </form>
        </div>

        <div class="row">
            <div class="col-md-8">

                <div class="row">
                    <div class="col-md-3 col-sm-6">
                        <div class="ka-kpi indigo">
                            <div class="ka-title"><i class="fa fa-check-circle"></i> Amount Received This Month</div>
                            <div class="ka-value"><?php echo $cs . money($monthPay); ?></div>
                            <div class="ka-sub">
                                <i class="fa fa-info-circle" style="cursor:help;" title="Current Month Fee: <?php echo money($arrSplit['cur_amt']); ?>
Previous Arrears Received: <?php echo money($arrSplit['arr_amt']); ?>
Advance (Next Month) Received: <?php echo money($arrSplit['adv_amt']); ?>
Total: <?php echo money($arrSplit['tot_amt']); ?>"></i>
                                Current: <?php echo $cs . money($arrSplit['cur_amt']); ?> &middot; Arrears: <?php echo $cs . money($arrSplit['arr_amt']); ?> &middot; Advance: <?php echo $cs . money($arrSplit['adv_amt']); ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="ka-kpi pink">
                            <div class="ka-title"><i class="fa fa-percent"></i> Discounts This Month</div>
                            <div class="ka-value"><?php echo $cs . money($monthDiscount); ?></div>
                            <div class="ka-sub">Concessions applied on collections.</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="ka-kpi red">
                            <div class="ka-title"><i class="fa fa-hourglass-half"></i> Fee Receivable</div>
                            <div class="ka-value"><?php echo $cs . money($receivable); ?></div>
                            <div class="ka-sub">Total outstanding (unpaid + partial).</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="ka-kpi teal">
                            <div class="ka-title"><i class="fa fa-calendar-plus-o"></i> Advance Fee</div>
                            <div class="ka-value"><?php echo $cs . money($advance_total); ?></div>
                            <div class="ka-sub">Unsettled future-month challans.</div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-3 col-sm-6">
                        <div class="ka-kpi green">
                            <div class="ka-title"><i class="fa fa-hand-holding-usd"></i> Today's Collection</div>
                            <div class="ka-value"><?php echo $cs . money($todayPay); ?></div>
                            <div class="ka-sub">Recorded today.</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="ka-kpi cyan">
                            <div class="ka-title"><i class="fa fa-tachometer"></i> Avg Daily (Month)</div>
                            <div class="ka-value"><?php echo $cs . money($avg_daily); ?></div>
                            <div class="ka-sub">Cumulative collections &divide; days.</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="ka-kpi amber">
                            <div class="ka-title"><i class="fa fa-user-graduate"></i> Active Students</div>
                            <div class="ka-value"><?php echo $active_students; ?></div>
                            <div class="ka-sub">Out of <?php echo $total_students; ?> total students.</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="ka-kpi purple">
                            <div class="ka-title"><i class="fa fa-history"></i> Previous Arrears</div>
                            <div class="ka-value"><?php echo $cs . money($prev_arrears); ?></div>
                            <div class="ka-sub">Outstanding from earlier months.</div>
                        </div>
                    </div>
                </div>

                <div class="ka-card">
                    <p class="ka-chart-title"><i class="fa fa-line-chart" style="color:#F59E0B;"></i> Fee Recovery Trend (Last 30 Days)</p>
                    <p class="ka-chart-sub">Daily collections over the past 30 days.</p>
                    <svg viewBox="0 0 660 210" style="width:100%; height:auto;">
                        <polygon points="<?php echo $spark['area']; ?>" fill="#EEF2FF" stroke="none"></polygon>
                        <polyline points="<?php echo $spark['pts']; ?>" fill="none" stroke="#6366F1" stroke-width="2.5"></polyline>
                        <?php $keys = array_keys($trend_points); for ($i = 0; $i < 30; $i += 6):
                            $d = $keys[$i]; ?>
                            <text x="<?php echo round(24 + (660 - 48) * ($i / 29), 0); ?>" y="202" font-size="10" fill="#9CA3AF"><?php echo substr($d, 5); ?></text>
                        <?php endfor; ?>
                        <text x="8" y="16" font-size="10" fill="#9CA3AF"><?php echo $cs . number_format(max(1, max($trend_points))); ?></text>
                    </svg>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="ka-card">
                            <p class="ka-chart-title"><i class="fa fa-pie-chart" style="color:#F59E0B;"></i> Payment Mode Mix</p>
                            <p class="ka-chart-sub">Collections by payment method (<?php echo date('M Y', strtotime($sel_year . '-' . $sel_m . '-01')); ?>).</p>
                            <?php if (count($modes) === 0): ?>
                                <p style="color:#9CA3AF; font-size:13px;">No payments recorded for this month.</p>
                            <?php else: foreach ($modes as $md): $pct = round(($md['amt'] / $mode_total) * 100, 1); ?>
                                <div class="pbar-row">
                                    <div class="pbar-label"><?php echo e($md['payment_method'] ?: 'Cash'); ?></div>
                                    <div class="pbar-track"><div class="pbar-fill" style="width:<?php echo $pct; ?>%;"></div></div>
                                    <div class="pbar-val"><?php echo $cs . money($md['amt']); ?> (<?php echo $pct; ?>%)</div>
                                </div>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="ka-card">
                            <p class="ka-chart-title"><i class="fa fa-bar-chart" style="color:#F59E0B;"></i> Top Fee Heads by Collection</p>
                            <p class="ka-chart-sub">Head-wise contribution this month.</p>
                            <?php if (count($heads) === 0): ?>
                                <p style="color:#9CA3AF; font-size:13px;">No billed heads this month.</p>
                            <?php else: foreach ($heads as $hd): $pct = round(($hd['tot'] / $head_total) * 100, 1); ?>
                                <div class="pbar-row">
                                    <div class="pbar-label"><?php echo e($hd['head_name']); ?></div>
                                    <div class="pbar-track"><div class="pbar-fill" style="width:<?php echo $pct; ?>%; background:linear-gradient(90deg,#F59E0B,#F97316);"></div></div>
                                    <div class="pbar-val"><?php echo $cs . money($hd['tot']); ?></div>
                                </div>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>
                </div>

                <div class="ka-card">
                    <p class="ka-chart-title"><i class="fa fa-users" style="color:#F59E0B;"></i> Student Base Snapshot</p>
                    <p class="ka-chart-sub">Active vs struck-off students and campus distribution.</p>
                    <?php $shareActive = $total_students > 0 ? round(($active_students / $total_students) * 100, 1) : 0; ?>
                    <div style="display:flex; gap:18px; align-items:center; flex-wrap:wrap;">
                        <div style="flex:1; min-width:220px;">
                            <div class="pbar-row"><div class="pbar-label" style="flex-basis:110px;">Active</div><div class="pbar-track"><div class="pbar-fill" style="width:<?php echo $shareActive; ?>%; background:linear-gradient(90deg,#10B981,#34D399);"></div></div><div class="pbar-val"><?php echo $active_students; ?> (<?php echo $shareActive; ?>%)</div></div>
                            <div class="pbar-row"><div class="pbar-label" style="flex-basis:110px;">Inactive</div><div class="pbar-track"><div class="pbar-fill" style="width:<?php echo max(0, 100 - $shareActive); ?>%; background:#F87171;"></div></div><div class="pbar-val"><?php echo $inactive_students; ?> (<?php echo number_format(100 - $shareActive, 1); ?>%)</div></div>
                        </div>
                        <div style="flex:1; min-width:220px;">
                            <?php foreach ($classHeadCounts as $chc): ?>
                                <div class="snap-row"><span style="font-size:13px; color:#374151;"><?php echo e($chc['class_head_name'] ?: 'Unassigned'); ?></span><strong style="font-size:14px;"><?php echo $chc['c']; ?></strong></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="ka-card" style="padding:18px; position:sticky; top:20px;">
                    <h3 class="ka-chart-title" style="margin-bottom:14px;"><i class="fa fa-list-alt"></i> Fee Reports &amp; Tools</h3>
                    <a href="<?php echo BASE_URL; ?>monthly_invoices.php" class="report-link"><div><div class="rl-title"><i class="fa fa-calendar-alt" style="color:#F59E0B;"></i> Monthly Fee Summary</div><div class="rl-sub">Challan-wise fee billed, discounts &amp; remaining</div></div></a>
                    <a href="<?php echo BASE_URL; ?>datewise_fee_collection_report_new.php" class="report-link" style="border-left-color:#4facfe;"><div><div class="rl-title"><i class="fa fa-calendar-day" style="color:#4facfe;"></i> Date-wise Collection Report</div><div class="rl-sub">Filter collections by date, class, and user</div></div></a>
                    <a href="#" class="report-link" title="Coming Soon"><div><div class="rl-title"><i class="fa fa-balance-scale"></i> User Wise Profit / Loss Report</div><div class="rl-sub">Fee income vs branch expenses</div></div></a>
                    <a href="<?php echo BASE_URL; ?>print_unpaid_fee_new.php" class="report-link"><div><div class="rl-title"><i class="fa fa-money"></i> Fee Receivables Report</div><div class="rl-sub">Track pending &amp; overdue amounts</div></div></a>
                    <a href="<?php echo BASE_URL; ?>print_unpaid_fee_new.php" class="report-link"><div><div class="rl-title"><i class="fa fa-user-times"></i> Students Arrears Report</div><div class="rl-sub">Unsettled arrears by student</div></div></a>
                    <a href="#" class="report-link" title="Coming Soon"><div><div class="rl-title"><i class="fa fa-gift"></i> Fee Discount Summary</div><div class="rl-sub">Overview of concessions and waivers</div></div></a>
                    <a href="<?php echo BASE_URL; ?>fee_challans.php" class="report-link"><div><div class="rl-title"><i class="fa fa-file-text-o"></i> Fee Challans Lists</div><div class="rl-sub">Generate monthly challan lists</div></div></a>
                    <a href="#" class="report-link" title="Coming Soon"><div><div class="rl-title"><i class="fa fa-close"></i> Daily Closing Report</div><div class="rl-sub">End-of-day financial closing</div></div></a>
                    <a href="#" class="report-link" title="Coming Soon"><div><div class="rl-title"><i class="fa fa-calendar-check-o"></i> Monthly Closing Report</div><div class="rl-sub">Branch-level month closing</div></div></a>
                    <a href="<?php echo BASE_URL; ?>student_fee_payments_view.php" class="report-link"><div><div class="rl-title"><i class="fa fa-user"></i> Individual Student Fee</div><div class="rl-sub">Search &amp; view student fee history</div></div></a>
                    <a href="#" class="report-link" title="Coming Soon"><div><div class="rl-title"><i class="fa fa-users"></i> Create Families</div><div class="rl-sub">Create families by code / cell no</div></div></a>
                    <a href="#" class="report-link" title="Coming Soon"><div><div class="rl-title"><i class="fa fa-credit-card"></i> Family Voucher Management</div><div class="rl-sub">Manage family payment vouchers</div></div></a>
                    <a href="#" class="report-link" title="Coming Soon"><div><div class="rl-title"><i class="fa fa-file"></i> Family Fee Reports</div><div class="rl-sub">Family fee reports by month</div></div></a>
                    <a href="#" class="report-link" title="Coming Soon"><div><div class="rl-title"><i class="fa fa-cogs"></i> Class-wise Fee Configuration</div><div class="rl-sub">Configure class fee settings</div></div></a>
                    <a href="<?php echo BASE_URL; ?>update_fee_settings.php" class="report-link"><div><div class="rl-title"><i class="fa fa-sliders"></i> Fee Module Settings</div><div class="rl-sub">Update multiple fee settings</div></div></a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
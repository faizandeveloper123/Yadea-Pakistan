<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Attendance Summary';

try {
    db_query("ALTER TABLE attendance MODIFY status ENUM('present','absent','late','leave','short_leave') NOT NULL DEFAULT 'present'");
} catch (\Throwable $e) { /* already fine */ }

$today = date('Y-m-d');

$classes = [];
$res = db_query("Select class_id, class_name FROM classes WHERE status=1 ORDER BY class_name");
while ($row = $res->fetch_assoc()) { $classes[] = $row; }

// Active students / staff counts
$totalStudents = (int) db_query("SELECT COUNT(*) c FROM students WHERE status=1")->fetch_assoc()['c'];
$totalStaff    = (int) db_query("SELECT COUNT(*) c FROM employees WHERE status=1")->fetch_assoc()['c'];

// Today's student attendance counts
$todayCounts = ['present'=>0,'absent'=>0,'late'=>0,'leave'=>0,'short_leave'=>0];
$res = db_query("SELECT status, COUNT(*) c FROM attendance WHERE date='" . db_connect()->real_escape_string($today) . "' GROUP BY status");
while ($row = $res->fetch_assoc()) { if (isset($todayCounts[$row['status']])) $todayCounts[$row['status']] = (int) $row['c']; }
$todayMarked   = $totalStudents > 0 ? array_sum($todayCounts) : 0;
$todayUnmarked = max(0, $totalStudents - $todayMarked);
$todayRate     = $totalStudents > 0 ? round((($todayCounts['present'] + $todayCounts['late']) / $totalStudents) * 100) : 0;

// 7-day average rate
$sevenDayRate = 0;
$rates = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $r = db_query("SELECT status, COUNT(*) c FROM attendance WHERE date='" . db_connect()->real_escape_string($d) . "' GROUP BY status");
    $marked = 0;
    while ($row = $r->fetch_assoc()) { $marked += (int) $row['c']; }
    $rates[] = $totalStudents > 0 ? ($marked / $totalStudents) * 100 : 0;
}
$sevenDayRate = count($rates) ? round(array_sum($rates) / count($rates)) : 0;

// Daily attendance last 30 days
$daily = [];
$start = date('Y-m-d', strtotime('-29 days'));
$res = db_query("SELECT date, status, COUNT(*) c FROM attendance WHERE date BETWEEN '$start' AND '$today' GROUP BY date, status");
$dailyMap = [];
while ($row = $res->fetch_assoc()) { $dailyMap[$row['date']][$row['status']] = (int) $row['c']; }
for ($i = 0; $i < 30; $i++) {
    $d = date('Y-m-d', strtotime("+$i days", strtotime($start)));
    $daily[] = [
        'date' => $d,
        'label' => date('d M', strtotime($d)),
        'present' => $dailyMap[$d]['present'] ?? 0,
        'absent'  => $dailyMap[$d]['absent'] ?? 0,
        'late'    => ($dailyMap[$d]['late'] ?? 0) + ($dailyMap[$d]['short_leave'] ?? 0),
        'leave'   => $dailyMap[$d]['leave'] ?? 0,
    ];
}

// Monthly last 6 months
$monthly = [];
for ($i = 5; $i >= 0; $i--) {
    $first = date('Y-m-01', strtotime("-$i months"));
    $label = date('M Y', strtotime($first));
    $next  = date('Y-m-01', strtotime("+1 months", strtotime($first)));
    $res = db_query("SELECT date, status, COUNT(*) c FROM attendance WHERE date >= '$first' AND date < '$next' GROUP BY date, status");
    $cnt  = ['present'=>0,'absent'=>0,'late'=>0,'leave'=>0,'short_leave'=>0];
    $dates = [];
    while ($row = $res->fetch_assoc()) {
        if (isset($cnt[$row['status']])) $cnt[$row['status']] = (int) $row['c'];
        $dates[$row['date']] = true;
    }
    $totalRec = $cnt['present'] + $cnt['absent'] + $cnt['late'] + $cnt['leave'] + $cnt['short_leave'];
    $monthly[] = [
        'label' => $label,
        'present' => $cnt['present'],
        'absent' => $cnt['absent'],
        'rate' => $totalRec > 0 ? round(($cnt['present'] / $totalRec) * 100) : 0,
        'working_days' => count($dates),
    ];
}

// Distribution today (donut)
$dist = [
    'present' => $todayCounts['present'],
    'absent'  => $todayCounts['absent'],
    'late'    => $todayCounts['late'],
    'leave'   => $todayCounts['leave'] + $todayCounts['short_leave'],
];
$distTotal = array_sum($dist);
$colors = ['#10b981', '#f43f5e', '#f59e0b', '#8b5cf6'];
$stops = [];
$acc = 0;
foreach (array_values($dist) as $idx => $v) {
    $pct = $distTotal > 0 ? ($v / $distTotal) * 100 : 0;
    $stops[] = $colors[$idx] . ' ' . round($acc, 2) . '% ' . round($acc + $pct, 2) . '%';
    $acc += $pct;
}
if ($distTotal === 0) { $stops = ['#f1f5f9 0% 100%']; }
$donutBg = 'conic-gradient(' . implode(', ', $stops) . ')';

// Staff attendance today
$staffToday = ['present'=>0,'absent'=>0,'late'=>0,'leave'=>0,'short_leave'=>0];
$totalStaffMarked = 0;
try {
    db_query("CREATE TABLE IF NOT EXISTS staff_attendance (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        att_date DATE NOT NULL,
        status VARCHAR(10) NOT NULL DEFAULT 'present',
        time_in TIME NULL, time_out TIME NULL,
        UNIQUE KEY uq_emp_attdate (employee_id, att_date)
    ) ENGINE=InnoDB");
} catch (\Throwable $e) { /* table exists */ }
$res = db_query("SELECT status, COUNT(*) c FROM staff_attendance WHERE att_date='" . db_connect()->real_escape_string($today) . "' GROUP BY status");
while ($row = $res->fetch_assoc()) {
    if (isset($staffToday[$row['status']])) $staffToday[$row['status']] = (int) $row['c'];
    $totalStaffMarked += (int) $row['c'];
}

// SVG daily chart geometry
$n = count($daily);
$W = 800; $H = 240; $padL = 40; $padR = 12; $padT = 14; $padB = 26;
$maxV = 1;
foreach ($daily as $d) { $maxV = max($maxV, $d['present'], $d['absent']); }
$maxV = max(10, $maxV);
function chart_point($i, $val, $n, $W2, $H2, $padL2, $padR2, $padT2, $padB2, $maxVal) {
    $x = $n <= 1 ? $padL2 : $padL2 + ($i / ($n - 1)) * ($W2 - $padL2 - $padR2);
    $y = $padT2 + (1 - ($val / $maxVal)) * ($H2 - $padT2 - $padB2);
    return [$x, $y];
}
$presentPts = []; $absentPts = [];
foreach ($daily as $i => $d) {
    list($x1, $y1) = chart_point($i, $d['present'], $n, $W, $H, $padL, $padR, $padT, $padB, $maxV);
    $presentPts[] = "$x1,$y1";
    list($x2, $y2) = chart_point($i, $d['absent'], $n, $W, $H, $padL, $padR, $padT, $padB, $maxV);
    $absentPts[] = "$x2,$y2";
}
$presentArea = "M " . ($padL) . " " . ($H - $padB) . " L " . implode(' L ', $presentPts) . " L " . ($W - $padR) . " " . ($H - $padB) . " Z";
$absentArea  = "M " . ($padL) . " " . ($H - $padB) . " L " . implode(' L ', $absentPts) . " L " . ($W - $padR) . " " . ($H - $padB) . " Z";
$gridHtml = '';
foreach ([0.25, 0.5, 0.75, 1.0] as $g) {
    $gy = $padT + (1 - $g) * ($H - $padT - $padB);
    $gridHtml .= '<line x1="' . $padL . '" y1="' . $gy . '" x2="' . ($W - $padR) . '" y2="' . $gy . '" stroke="#eef2f7" stroke-width="1"/>';
    $gridHtml .= '<text x="' . ($padL - 8) . '" y="' . ($gy + 4) . '" font-size="10" fill="#9aa5b1" text-anchor="end">' . round($maxV * $g) . '</text>';
}
$labelEvery = max(1, ceil($n / 10));
$axisLabels = '';
foreach ($daily as $i => $d) {
    if ($i % $labelEvery !== 0 && $i !== $n - 1) continue;
    list($x, $y) = chart_point($i, 0, $n, $W, $H, $padL, $padR, $padT, $padB, $maxV);
    $axisLabels .= '<text x="' . $x . '" y="' . ($H - $padB + 16) . '" font-size="9.5" fill="#9aa5b1" text-anchor="middle">' . e($d['label']) . '</text>';
}

// Yearly per-student report (kept from previous behaviour)
$sel_class = (int) ($_GET['class_id'] ?? 0);
$sel_month = (int) ($_GET['month'] ?? (int) date('m'));
$sel_year  = (int) ($_GET['year'] ?? (int) date('Y'));

$report = [];
$summary = ['present' => 0, 'absent' => 0, 'late' => 0, 'leave' => 0, 'total' => 0];
$rows = [];
if ($sel_class > 0) {
    $res = db_query("SELECT a.status, s.first_name, s.student_id, s.roll_no, c.class_name FROM attendance a
                     JOIN students s ON a.student_id = s.student_id
                     LEFT JOIN classes c ON s.class_id = c.class_id
                     WHERE MONTH(a.date) = $sel_month AND YEAR(a.date) = $sel_year AND s.class_id = $sel_class ORDER BY s.first_name");
    while ($r = $res->fetch_assoc()) { $rows[] = $r; $summary[$r['status']]++; $summary['total']++; }
}
$byStudent = [];
foreach ($rows as $r) {
    if (!isset($byStudent[$r['student_id']])) {
        $byStudent[$r['student_id']] = ['name'=>$r['first_name'],'roll'=>$r['roll_no'],'class'=>$r['class_name'],
            'present'=>0,'absent'=>0,'late'=>0,'leave'=>0,'total'=>0];
    }
    $byStudent[$r['student_id']][$r['status']]++;
    $byStudent[$r['student_id']]['total']++;
}

include __DIR__ . '/includes/header.php';
?>
<style>
.search-box { display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end; background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:14px; margin-bottom:14px; }
.report-link:hover { background:#e0e7ff !important; transform:translateX(5px); }
.dashboard-summery-one { background:#fff; }
@media print { .no-print { display:none!important; } }
</style>

<div class="main-content">
    <div class="container-fluid">

        <!-- Page Header -->
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px; margin:6px 0 12px 0;">
            <div>
                <a href="<?php echo BASE_URL; ?>dashboard.php"><i class="fa fa-home"></i> Dashboard</a>
                &nbsp; <i class="fa fa-angle-right"></i> &nbsp;
                <span style="color:#334155; font-weight:600; font-size:13px;">Attendance Summary</span>
            </div>
            <div style="display:flex; gap:8px; align-items:center;">
                <a href="<?php echo BASE_URL; ?>attendance_device_setup.php" target="_blank" class="btn btn-primary" style="padding:6px 14px; font-size:13px; color:white;"><i class="fa fa-cogs"></i>&nbsp;Settings</a>
                <a href="<?php echo BASE_URL; ?>qr_attendance_scan.php" target="_blank" class="btn btn-primary" style="padding:6px 14px; font-size:13px; color:white;"><i class="fa fa-qrcode"></i>&nbsp;QR Attendance</a>
            </div>
        </div>

        <div style="display:flex; flex-wrap:wrap;">
            <!-- Left: Dashboard (75%) -->
            <div class="col-md-9" style="padding-left:0;">

                <!-- Summary cards -->
                <div style="display:flex; flex-wrap:wrap; margin-bottom:12px;">
                    <div class="col-md-3 col-sm-6" style="padding:0 6px; margin-bottom:10px;">
                        <div class="dashboard-summery-one" style="background:linear-gradient(135deg,#6366f1 0%,#8b5cf6 100%); color:#fff; padding:12px; border-radius:8px;">
                            <div style="display:flex; align-items:center; justify-content:space-between;">
                                <div><div style="font-size:26px; font-weight:bold; margin-bottom:3px;"><?php echo $todayCounts['present']; ?></div>
                                <div style="font-size:12px; opacity:0.9;"><i class="fa fa-check-circle"></i> Present</div></div>
                                <div style="font-size:36px; opacity:0.25;"><i class="fa fa-check-circle"></i></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6" style="padding:0 6px; margin-bottom:10px;">
                        <div class="dashboard-summery-one" style="background:linear-gradient(135deg,#f43f5e 0%,#ec4899 100%); color:#fff; padding:12px; border-radius:8px;">
                            <div style="display:flex; align-items:center; justify-content:space-between;">
                                <div><div style="font-size:26px; font-weight:bold; margin-bottom:3px;"><?php echo $todayCounts['absent']; ?></div>
                                <div style="font-size:12px; opacity:0.9;"><i class="fa fa-times-circle"></i> Absent</div></div>
                                <div style="font-size:36px; opacity:0.25;"><i class="fa fa-times-circle"></i></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6" style="padding:0 6px; margin-bottom:10px;">
                        <div class="dashboard-summery-one" style="background:linear-gradient(135deg,#f59e0b 0%,#f97316 100%); color:#fff; padding:12px; border-radius:8px;">
                            <div style="display:flex; align-items:center; justify-content:space-between;">
                                <div><div style="font-size:26px; font-weight:bold; margin-bottom:3px;"><?php echo $todayUnmarked; ?></div>
                                <div style="font-size:12px; opacity:0.9;"><i class="fa fa-exclamation-circle"></i> Unmarked</div></div>
                                <div style="font-size:36px; opacity:0.25;"><i class="fa fa-exclamation-circle"></i></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6" style="padding:0 6px; margin-bottom:10px;">
                        <div class="dashboard-summery-one" style="background:linear-gradient(135deg,#10b981 0%,#14b8a6 100%); color:#fff; padding:12px; border-radius:8px;">
                            <div style="display:flex; align-items:center; justify-content:space-between;">
                                <div><div style="font-size:26px; font-weight:bold; margin-bottom:3px;"><?php echo $todayRate; ?>%</div>
                                <div style="font-size:12px; opacity:0.9;"><i class="fa fa-percent"></i> Rate</div></div>
                                <div style="font-size:36px; opacity:0.25;"><i class="fa fa-line-chart"></i></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 7-Day Average + Total Students -->
                <div style="display:flex; flex-wrap:wrap; margin-bottom:12px;">
                    <div class="col-md-6" style="padding:0 6px; margin-bottom:10px;">
                        <div class="dashboard-summery-one" style="padding:14px; background:linear-gradient(135deg,#6366f1 0%,#8b5cf6 100%); color:#fff; border-radius:8px;">
                            <h4 style="margin:0 0 10px 0; color:#fff; font-weight:600; font-size:14px;"><i class="fa fa-calendar-week"></i> 7-Day Average</h4>
                            <div style="font-size:36px; font-weight:bold; margin:8px 0;"><?php echo $sevenDayRate; ?>%</div>
                            <div style="font-size:12px; opacity:0.85;">Average attendance last 7 days</div>
                        </div>
                    </div>
                    <div class="col-md-6" style="padding:0 6px; margin-bottom:10px;">
                        <div class="dashboard-summery-one" style="padding:14px; background:linear-gradient(135deg,#10b981 0%,#14b8a6 100%); color:#fff; border-radius:8px;">
                            <h4 style="margin:0 0 10px 0; color:#fff; font-weight:600; font-size:14px;"><i class="fa fa-users"></i> Total Students</h4>
                            <div style="font-size:36px; font-weight:bold; margin:8px 0;"><?php echo $totalStudents; ?></div>
                            <div style="font-size:12px; opacity:0.85;">Active in current session</div>
                        </div>
                    </div>
                </div>

                <!-- Daily Attendance (Last 30 Days) - SVG -->
                <div class="dashboard-summery-one" style="margin-bottom:12px; padding:14px; border:1px solid #e2e8f0; border-radius:8px;">
                    <h3 style="margin-top:0; color:#334155; font-weight:600; border-bottom:1px solid #e2e8f0; padding-bottom:10px; margin-bottom:12px; font-size:15px;">
                        <i class="fa fa-line-chart"></i> Daily Attendance (Last 30 Days)
                    </h3>
                    <svg viewBox="0 0 <?php echo $W; ?> <?php echo $H; ?>" style="width:100%; height:auto; display:block;">
                        <?php echo $gridHtml; ?>
                        <path d="<?php echo $presentArea; ?>" fill="rgba(16,185,129,0.12)" stroke="none"/>
                        <polyline points="<?php echo implode(' ', $presentPts); ?>" fill="none" stroke="#10b981" stroke-width="2"/>
                        <path d="<?php echo $absentArea; ?>" fill="rgba(244,63,94,0.10)" stroke="none"/>
                        <polyline points="<?php echo implode(' ', $absentPts); ?>" fill="none" stroke="#f43f5e" stroke-width="2"/>
                        <?php echo $axisLabels; ?>
                        <text x="16" y="14" font-size="11" fill="#10b981" font-weight="700">Present</text>
                        <text x="16" y="28" font-size="11" fill="#f43f5e" font-weight="700">Absent</text>
                    </svg>
                </div>

                <!-- Monthly Percentage (Last 6 Months) - CSS bars -->
                <div class="dashboard-summery-one" style="margin-bottom:12px; padding:14px; border:1px solid #e2e8f0; border-radius:8px;">
                    <h3 style="margin-top:0; color:#334155; font-weight:600; border-bottom:1px solid #e2e8f0; padding-bottom:10px; margin-bottom:12px; font-size:15px;">
                        <i class="fa fa-bar-chart"></i> Monthly Percentage (Last 6 Months)
                    </h3>
                    <div style="display:flex; align-items:flex-end; gap:14px; height:190px; padding:0 10px;">
                        <?php foreach ($monthly as $m): ?>
                            <div style="flex:1; text-align:center; height:100%; display:flex; flex-direction:column; justify-content:flex-end;">
                                <div style="color:#334155; font-weight:700; font-size:12px; margin-bottom:4px;"><?php echo $m['rate']; ?>%</div>
                                <div style="background:linear-gradient(180deg,#6366f1,#8b5cf6); border-radius:6px 6px 0 0; height:<?php echo max(3, (int) ($m['rate'] * 1.35)); ?>px;" title="Attendance <?php echo $m['rate']; ?>% (<?php echo $m['working_days']; ?> working days)"></div>
                                <div style="color:#64748b; font-size:11px; margin-top:6px; white-space:nowrap;"><?php echo e($m['label']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Distribution + Staff Attendance -->
                <div style="display:flex; flex-wrap:wrap;">
                    <div class="col-md-6" style="padding:0 6px; margin-bottom:12px;">
                        <div class="dashboard-summery-one" style="padding:14px; border:1px solid #e2e8f0; border-radius:8px;">
                            <h4 style="margin:0 0 10px 0; color:#334155; font-weight:600; font-size:14px;"><i class="fa fa-pie-chart"></i> Distribution</h4>
                            <div style="position:relative; height:190px; display:flex; align-items:center; justify-content:center;">
                                <div style="width:150px; height:150px; border-radius:50%; background:<?php echo $donutBg; ?>; position:relative;">
                                    <div style="position:absolute; inset:36px; background:#fff; border-radius:50%; display:flex; align-items:center; justify-content:center; flex-direction:column;">
                                        <div style="font-size:22px; font-weight:800; color:#334155;"><?php echo $distTotal; ?></div>
                                        <div style="font-size:10px; color:#64748b;">Marked</div>
                                    </div>
                                </div>
                            </div>
                            <div style="display:flex; justify-content:center; gap:12px; flex-wrap:wrap; margin-top:8px;">
                                <span style="font-size:12px;"><i class="fa fa-circle" style="color:#10b981;"></i> P: <?php echo $dist['present']; ?></span>
                                <span style="font-size:12px;"><i class="fa fa-circle" style="color:#f43f5e;"></i> A: <?php echo $dist['absent']; ?></span>
                                <span style="font-size:12px;"><i class="fa fa-circle" style="color:#f59e0b;"></i> L: <?php echo $dist['late']; ?></span>
                                <span style="font-size:12px;"><i class="fa fa-circle" style="color:#8b5cf6;"></i> Lv: <?php echo $dist['leave']; ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6" style="padding:0 6px; margin-bottom:12px;">
                        <div class="dashboard-summery-one" style="padding:14px; border:1px solid #e2e8f0; border-radius:8px;">
                            <h4 style="margin:0 0 10px 0; color:#334155; font-weight:600; font-size:14px;"><i class="fa fa-users"></i> Staff Attendance</h4>
                            <div style="padding:8px;">
                                <div style="display:flex; justify-content:space-between; align-items:center; padding:8px 10px; background:#f8fafc; border-radius:6px; margin-bottom:8px;">
                                    <div style="font-size:13px;"><i class="fa fa-check-circle" style="color:#10b981;"></i> <strong>Present:</strong></div>
                                    <div style="font-size:18px; font-weight:bold; color:#10b981;"><?php echo $staffToday['present']; ?></div>
                                </div>
                                <div style="display:flex; justify-content:space-between; align-items:center; padding:8px 10px; background:#f8fafc; border-radius:6px; margin-bottom:8px;">
                                    <div style="font-size:13px;"><i class="fa fa-times-circle" style="color:#f43f5e;"></i> <strong>Absent:</strong></div>
                                    <div style="font-size:18px; font-weight:bold; color:#f43f5e;"><?php echo $staffToday['absent']; ?></div>
                                </div>
                                <div style="display:flex; justify-content:space-between; align-items:center; padding:8px 10px; background:#f8fafc; border-radius:6px; margin-bottom:8px;">
                                    <div style="font-size:13px;"><i class="fa fa-clock-o" style="color:#f59e0b;"></i> <strong>Late:</strong></div>
                                    <div style="font-size:18px; font-weight:bold; color:#f59e0b;"><?php echo $staffToday['late']; ?></div>
                                </div>
                                <div style="display:flex; justify-content:space-between; align-items:center; padding:8px 10px; background:#f8fafc; border-radius:6px;">
                                    <div style="font-size:13px;"><i class="fa fa-calendar-times-o" style="color:#8b5cf6;"></i> <strong>Leave:</strong></div>
                                    <div style="font-size:18px; font-weight:bold; color:#8b5cf6;"><?php echo $staffToday['leave'] + $staffToday['short_leave']; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: Reports (25%) -->
            <div class="col-md-3">
                <div class="dashboard-summery-one" style="padding:14px; position:sticky; top:20px; background:#fff; border:1px solid #e2e8f0; border-radius:8px; box-shadow:0 1px 4px rgba(0,0,0,0.06);">
                    <h3 style="margin:0 0 12px 0; color:#334155; font-weight:600; border-bottom:1px solid #e2e8f0; padding-bottom:10px; font-size:15px;">
                        <i class="fa fa-list-alt"></i> Reports
                    </h3>
                    <div style="max-height:calc(100vh - 230px); overflow-y:auto; padding-right:4px;">

                        <div style="background:linear-gradient(135deg,#f8fafc 0%,#f1f5f9 100%); padding:10px; border-radius:6px; margin-bottom:12px;">
                            <h5 style="margin:0 0 8px 0; color:#475569; font-size:13px; font-weight:600;"><i class="fa fa-info-circle"></i> Quick Stats</h5>
                            <div style="display:flex; justify-content:space-between; margin-bottom:6px;"><span style="color:#64748b; font-size:12px;">Students:</span><strong style="color:#334155; font-size:12px;"><?php echo $totalStudents; ?></strong></div>
                            <div style="display:flex; justify-content:space-between; margin-bottom:6px;"><span style="color:#64748b; font-size:12px;">Staff:</span><strong style="color:#334155; font-size:12px;"><?php echo $totalStaff; ?></strong></div>
                            <div style="display:flex; justify-content:space-between;"><span style="color:#64748b; font-size:12px;">Today's Rate:</span><strong style="color:#10b981; font-size:12px;"><?php echo $todayRate; ?>%</strong></div>
                        </div>

                        <h5 style="color:#475569; font-size:13px; font-weight:600; margin-bottom:10px; margin-top:0;"><i class="fa fa-file-text"></i> Available Reports</h5>

                        <a href="<?php echo BASE_URL; ?>day_attendance_summary.php?page=&date=<?php echo e($today); ?>&attendance=All" target="_blank" class="report-link" style="display:block; padding:10px; margin-bottom:8px; background:#f8fafc; border-radius:8px; text-decoration:none; color:#334155; border-left:3px solid #6366f1; box-shadow:0 1px 3px rgba(0,0,0,0.04);">
                            <div style="display:flex; align-items:center; gap:10px;">
                                <div style="width:32px; height:32px; border-radius:8px; background:linear-gradient(135deg,#6366f1 0%,#8b5cf6 100%); display:flex; align-items:center; justify-content:center; flex-shrink:0;"><i class="fa fa-calendar-check-o" style="color:white; font-size:14px;"></i></div>
                                <div style="flex:1; min-width:0;"><div style="font-weight:600; font-size:13px; margin-bottom:2px;">Day Attendance</div><small style="color:#64748b; font-size:11px;">Daily details</small></div>
                                <i class="fa fa-chevron-right" style="color:#cbd5e1; font-size:10px; flex-shrink:0;"></i>
                            </div>
                        </a>
                        <a href="<?php echo BASE_URL; ?>classwise_monthly_attendance_report.php" target="_blank" class="report-link" style="display:block; padding:10px; margin-bottom:8px; background:#f8fafc; border-radius:8px; text-decoration:none; color:#334155; border-left:3px solid #0ea5e9; box-shadow:0 1px 3px rgba(0,0,0,0.04);">
                            <div style="display:flex; align-items:center; gap:10px;">
                                <div style="width:32px; height:32px; border-radius:8px; background:linear-gradient(135deg,#0ea5e9 0%,#06b6d4 100%); display:flex; align-items:center; justify-content:center; flex-shrink:0;"><i class="fa fa-building" style="color:white; font-size:14px;"></i></div>
                                <div style="flex:1; min-width:0;"><div style="font-weight:600; font-size:13px; margin-bottom:2px;">Institute Summary</div><small style="color:#64748b; font-size:11px;">Monthly reports</small></div>
                                <i class="fa fa-chevron-right" style="color:#cbd5e1; font-size:10px; flex-shrink:0;"></i>
                            </div>
                        </a>
                        <a href="<?php echo BASE_URL; ?>monthly_attendance_report.php" target="_blank" class="report-link" style="display:block; padding:10px; margin-bottom:8px; background:#f8fafc; border-radius:8px; text-decoration:none; color:#334155; border-left:3px solid #06b6d4; box-shadow:0 1px 3px rgba(0,0,0,0.04);">
                            <div style="display:flex; align-items:center; gap:10px;">
                                <div style="width:32px; height:32px; border-radius:8px; background:linear-gradient(135deg,#06b6d4 0%,#14b8a6 100%); display:flex; align-items:center; justify-content:center; flex-shrink:0;"><i class="fa fa-file-text" style="color:white; font-size:14px;"></i></div>
                                <div style="flex:1; min-width:0;"><div style="font-weight:600; font-size:13px; margin-bottom:2px;">Filled Sheet</div><small style="color:#64748b; font-size:11px;">Completed sheets</small></div>
                                <i class="fa fa-chevron-right" style="color:#cbd5e1; font-size:10px; flex-shrink:0;"></i>
                            </div>
                        </a>
                        <a href="<?php echo BASE_URL; ?>attendance_sheet.php" target="_blank" class="report-link" style="display:block; padding:10px; margin-bottom:8px; background:#f8fafc; border-radius:8px; text-decoration:none; color:#334155; border-left:3px solid #f43f5e; box-shadow:0 1px 3px rgba(0,0,0,0.04);">
                            <div style="display:flex; align-items:center; gap:10px;">
                                <div style="width:32px; height:32px; border-radius:8px; background:linear-gradient(135deg,#f43f5e 0%,#ec4899 100%); display:flex; align-items:center; justify-content:center; flex-shrink:0;"><i class="fa fa-file-o" style="color:white; font-size:14px;"></i></div>
                                <div style="flex:1; min-width:0;"><div style="font-weight:600; font-size:13px; margin-bottom:2px;">Blank Sheet</div><small style="color:#64748b; font-size:11px;">Download templates</small></div>
                                <i class="fa fa-chevron-right" style="color:#cbd5e1; font-size:10px; flex-shrink:0;"></i>
                            </div>
                        </a>
                        <a href="#" data-toggle="modal" data-target="#mostabsent" class="report-link" style="display:block; padding:10px; margin-bottom:8px; background:#f8fafc; border-radius:8px; text-decoration:none; color:#334155; border-left:3px solid #10b981; box-shadow:0 1px 3px rgba(0,0,0,0.04); cursor:pointer;">
                            <div style="display:flex; align-items:center; gap:10px;">
                                <div style="width:32px; height:32px; border-radius:8px; background:linear-gradient(135deg,#10b981 0%,#14b8a6 100%); display:flex; align-items:center; justify-content:center; flex-shrink:0;"><i class="fa fa-bar-chart" style="color:white; font-size:14px;"></i></div>
                                <div style="flex:1; min-width:0;"><div style="font-weight:600; font-size:13px; margin-bottom:2px;">Most Absents</div><small style="color:#64748b; font-size:11px;">Detailed reports</small></div>
                                <i class="fa fa-chevron-right" style="color:#cbd5e1; font-size:10px; flex-shrink:0;"></i>
                            </div>
                        </a>
                        <a href="<?php echo BASE_URL; ?>regular_students_report.php" target="_blank" class="report-link" style="display:block; padding:10px; margin-bottom:8px; background:#f8fafc; border-radius:8px; text-decoration:none; color:#334155; border-left:3px solid #f59e0b; box-shadow:0 1px 3px rgba(0,0,0,0.04);">
                            <div style="display:flex; align-items:center; gap:10px;">
                                <div style="width:32px; height:32px; border-radius:8px; background:linear-gradient(135deg,#f59e0b 0%,#f97316 100%); display:flex; align-items:center; justify-content:center; flex-shrink:0;"><i class="fa fa-star" style="color:white; font-size:14px;"></i></div>
                                <div style="flex:1; min-width:0;"><div style="font-weight:600; font-size:13px; margin-bottom:2px;">100% Regular Students</div><small style="color:#64748b; font-size:11px;">No absence/leave in month</small></div>
                                <i class="fa fa-chevron-right" style="color:#cbd5e1; font-size:10px; flex-shrink:0;"></i>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div style="margin-top:25px;">
            <h3 style="color:#334155; font-weight:600; font-size:18px; padding-left:5px; border-left:4px solid #3b82f6; background:white; padding:12px;">&nbsp;Quick Actions</h3>
            <div style="display:flex; flex-wrap:wrap;">
                <div class="col-md-3" style="margin-bottom:12px;">
                    <a href="<?php echo BASE_URL; ?>day_attendance_summary.php?page=&date=<?php echo e($today); ?>&attendance=All" target="_blank" style="display:block; padding:20px; background:#fff; border-radius:12px; text-decoration:none; color:#334155; box-shadow:0 4px 6px rgba(0,0,0,0.05); border:1px solid #e2e8f0; height:100%;">
                        <div style="display:flex; align-items:center; gap:15px;">
                            <div style="width:48px; height:48px; border-radius:12px; background:linear-gradient(135deg,#ec4899 0%,#f43f5e 100%); display:flex; align-items:center; justify-content:center; flex-shrink:0;"><i class="fa fa-calendar" style="color:white; font-size:20px;"></i></div>
                            <div style="flex:1; min-width:0;"><div style="font-weight:700; font-size:15px; margin-bottom:4px; color:#1e293b;">Daily Report</div><div style="color:#64748b; font-size:13px; line-height:1.4;">View daily attendance</div></div>
                            <i class="fa fa-chevron-right" style="color:#cbd5e1; font-size:14px;"></i>
                        </div>
                    </a>
                </div>
                <div class="col-md-3" style="margin-bottom:12px;">
                    <a href="<?php echo BASE_URL; ?>datewise_class_attendance_report.php" target="_blank" style="display:block; padding:20px; background:#fff; border-radius:12px; text-decoration:none; color:#334155; box-shadow:0 4px 6px rgba(0,0,0,0.05); border:1px solid #e2e8f0; height:100%;">
                        <div style="display:flex; align-items:center; gap:15px;">
                            <div style="width:48px; height:48px; border-radius:12px; background:linear-gradient(135deg,#f59e0b 0%,#f97316 100%); display:flex; align-items:center; justify-content:center; flex-shrink:0;"><i class="fa fa-clock-o" style="color:white; font-size:20px;"></i></div>
                            <div style="flex:1; min-width:0;"><div style="font-weight:700; font-size:15px; margin-bottom:4px; color:#1e293b;">Datewise Class</div><div style="color:#64748b; font-size:13px; line-height:1.4;">By date range</div></div>
                            <i class="fa fa-chevron-right" style="color:#cbd5e1; font-size:14px;"></i>
                        </div>
                    </a>
                </div>
                <div class="col-md-3" style="margin-bottom:12px;">
                    <a href="<?php echo BASE_URL; ?>classwise_attendance_summary.php?page=&date=<?php echo e($today); ?>&attendance=All" target="_blank" style="display:block; padding:20px; background:#fff; border-radius:12px; text-decoration:none; color:#334155; box-shadow:0 4px 6px rgba(0,0,0,0.05); border:1px solid #e2e8f0; height:100%;">
                        <div style="display:flex; align-items:center; gap:15px;">
                            <div style="width:48px; height:48px; border-radius:12px; background:linear-gradient(135deg,#8b5cf6 0%,#6366f1 100%); display:flex; align-items:center; justify-content:center; flex-shrink:0;"><i class="fa fa-users" style="color:white; font-size:20px;"></i></div>
                            <div style="flex:1; min-width:0;"><div style="font-weight:700; font-size:15px; margin-bottom:4px; color:#1e293b;">Classwise</div><div style="color:#64748b; font-size:13px; line-height:1.4;">View by class summary</div></div>
                            <i class="fa fa-chevron-right" style="color:#cbd5e1; font-size:14px;"></i>
                        </div>
                    </a>
                </div>
                <div class="col-md-3" style="margin-bottom:12px;">
                    <a href="<?php echo BASE_URL; ?>attendance_device_setup.php" target="_blank" style="display:block; padding:20px; background:#fff; border-radius:12px; text-decoration:none; color:#334155; box-shadow:0 4px 6px rgba(0,0,0,0.05); border:1px solid #e2e8f0; height:100%;">
                        <div style="display:flex; align-items:center; gap:15px;">
                            <div style="width:48px; height:48px; border-radius:12px; background:linear-gradient(135deg,#6366f1 0%,#06b6d4 100%); display:flex; align-items:center; justify-content:center; flex-shrink:0;"><i class="fa fa-cogs" style="color:white; font-size:20px;"></i></div>
                            <div style="flex:1; min-width:0;"><div style="font-weight:700; font-size:15px; margin-bottom:4px; color:#1e293b;">Device Setup</div><div style="color:#64748b; font-size:13px; line-height:1.4;">Configure devices</div></div>
                            <i class="fa fa-chevron-right" style="color:#cbd5e1; font-size:14px;"></i>
                        </div>
                    </a>
                </div>
            </div>
        </div>

        <!-- Keep existing yearly per-student report -->
        <div style="margin-top:20px; background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:16px;">
            <div style="display:flex; align-items:center; justify-content:space-between; padding:4px 0 12px 0;">
                <h3 style="font-size:17px; font-weight:800; color:#111827; margin:0;"><i class="fa fa-table"></i> Per-Student Monthly Attendance</h3>
            </div>
            <form method="get" action="<?php echo BASE_URL; ?>mark_attendanceReport_list.php" class="search-box no-print">
                <div class="form-group col-md-3" style="padding:0 4px; margin-bottom:0;">
                    <label>Course/Class</label>
                    <select name="class_id" class="form-control">
                        <option value="">All Classes</option>
                        <?php foreach ($classes as $c): ?>
                            <option value="<?php echo $c['class_id']; ?>" <?php echo $sel_class == $c['class_id'] ? 'selected' : ''; ?>><?php echo e($c['class_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-2" style="padding:0 4px; margin-bottom:0;">
                    <label>Month</label>
                    <select name="month" class="form-control">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo $m; ?>" <?php echo $sel_month == $m ? 'selected' : ''; ?>><?php echo date('F', mktime(0,0,0,$m,1)); ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group col-md-2" style="padding:0 4px; margin-bottom:0;">
                    <label>Year</label>
                    <select name="year" class="form-control">
                        <?php for ($y = 2018; $y <= 2030; $y++): ?>
                            <option value="<?php echo $y; ?>" <?php echo $sel_year == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group col-md-2" style="padding:0 4px; margin-bottom:0;">
                    <button type="submit" class="btn btn-primary" style="width:100%; margin-top:24px;"><i class="fa fa-search"></i> Load</button>
                </div>
            </form>
            <div style="overflow-x:auto;">
                <table class="table table-striped table-bordered" style="width:100%; background:#fff; margin-bottom:0;">
                    <thead>
                        <tr><th>GR. No</th><th>Student</th><th>Course/Class</th><th>Present</th><th>Absent</th><th>Late</th><th>Leave</th><th>Total</th><th>Attendance %</th></tr>
                    </thead>
                    <tbody>
                        <?php if (count($byStudent) === 0): ?>
                            <tr><td colspan="9" style="text-align:center; color:#6B7280; padding:30px;">No attendance records found for selected filters.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($byStudent as $sid => $st): $pct = $st['total'] > 0 ? round(($st['present'] / $st['total']) * 100) : 0; ?>
                            <tr>
                                <td><?php echo e($st['roll'] ?? $sid); ?></td>
                                <td><strong><?php echo e($st['name']); ?></strong></td>
                                <td><?php echo e($st['class']); ?></td>
                                <td style="color:#16A34A; font-weight:700;"><?php echo $st['present']; ?></td>
                                <td style="color:#DC2626; font-weight:700;"><?php echo $st['absent']; ?></td>
                                <td style="color:#377DFF; font-weight:700;"><?php echo $st['late']; ?></td>
                                <td style="color:#F59E0B; font-weight:700;"><?php echo $st['leave']; ?></td>
                                <td><?php echo $st['total']; ?></td>
                                <td><span style="color:<?php echo $pct >= 75 ? '#16A34A' : '#DC2626'; ?>; font-weight:800;"><?php echo $pct; ?>%</span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<!-- Most Absents / Generate Attendance Report modal -->
<div id="mostabsent" class="modal fade" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title" style="text-align:center; width:100%;"> Generate Attendance Report </h4>
                <button type="button" class="close" data-dismiss="modal" style="color:#333; opacity:1;">&times;</button>
            </div>
            <div class="modal-body">
                <form action="<?php echo BASE_URL; ?>day_attendance_summary.php" method="get" target="_blank" onsubmit="return buildReportDate(this);">
                    <input type="hidden" name="date" id="report_date" value="">
                    <input type="hidden" name="attendance" value="All" id="report_attendance">
                    <div class="col-md-6 col-xs-12" style="padding:8px;">
                        <div class="form-group">
                            <label class="required">Year</label>
                            <select name="year" class="form-control" style="height:40px;">
                                <?php for ($y = 2018; $y <= 2030; $y++): ?>
                                    <option value="<?php echo $y; ?>" <?php echo $y === (int) date('Y') ? 'selected' : ''; ?>><?php echo $y; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6" style="padding:8px;">
                        <div class="form-group">
                            <label class="required">Month</label>
                            <select name="month" id="rep_month" class="form-control" style="height:40px;">
                                <option value="All">All</option>
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?php echo $m; ?>" <?php echo $m === (int) date('m') ? 'selected' : ''; ?>><?php echo date('F', mktime(0,0,0,$m,1)); ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6" style="padding:8px;">
                        <div class="form-group">
                            <label class="required">No Of Records</label>
                            <select name="records" class="form-control">
                                <?php foreach ([50,100,200,500,800,1000,1500] as $rec): ?>
                                    <option value="<?php echo $rec; ?>"><?php echo $rec; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6" style="padding:8px;">
                        <div class="form-group">
                            <label class="required">Attendance</label>
                            <select name="attendance_code" id="rep_attendance" class="form-control">
                                <option value="A">Absents</option>
                                <option value="P">Presents</option>
                                <option value="L">Leaves</option>
                                <option value="SL">Short Leaves</option>
                                <option value="LA">Late Arrivals</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary" name="submit">Submit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function buildReportDate(form){
    var y = form.querySelector('select[name="year"]').value;
    var m = form.querySelector('select[name="month"]').value;
    var code = form.querySelector('select[name="attendance_code"]').value;
    document.getElementById('report_date').value = (m === 'All' ? y + '-01-01' : y + '-' + ('0' + m).slice(-2) + '-01');
    document.getElementById('report_attendance').value = code;
    return true;
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
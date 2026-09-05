<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Day Attendance Summary';

// Ensure short_leave is accepted by the student attendance status column (idempotent)
try {
    db_query("ALTER TABLE attendance MODIFY status ENUM('present','absent','late','leave','short_leave') NOT NULL DEFAULT 'present'");
} catch (\Throwable $e) { /* column already supports the values */ }

$sel_class       = $_GET['class_id'] ?? 'All';
$sel_section     = $_GET['section'] ?? 'All';
$sel_attendance  = $_GET['attendance'] ?? 'All';
$date            = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) { $date = date('Y-m-d'); }

$classes = [];
$res = db_query("Select Course/Class_id, class_name FROM classes WHERE status=1 ORDER BY class_name");
while ($row = $res->fetch_assoc()) { $classes[] = $row; }

// Fetch all students (for stats) with lightweight filters build later for the table
$allSQL = "SELECT s.student_id, s.first_name, s.last_name, s.father_name, s.phone, s.father_cellno, s.roll_no, s.gr_no,
                  s.class_id, s.section_id, c.class_name, sec.section_name
           FROM students s
           LEFT JOIN classes c ON s.class_id = c.class_id
           LEFT JOIN sections sec ON s.section_id = sec.section_id
           WHERE s.status = 1";
$res = db_query($allSQL);
$allStudents = [];
while ($row = $res->fetch_assoc()) { $allStudents[$row['student_id']] = $row; }

// Attendance for the selected date
$att_map = [];
$res = db_query("SELECT student_id, status FROM attendance WHERE date = '" . db_connect()->real_escape_string($date) . "'");
while ($row = $res->fetch_assoc()) { $att_map[$row['student_id']] = $row['status']; }

// Last present date (before selected date) per student
$lastPresent = [];
$res = db_query("SELECT student_id, MAX(date) AS last_present FROM attendance WHERE status='present' AND date < '" . db_connect()->real_escape_string($date) . "' GROUP BY student_id");
while ($row = $res->fetch_assoc()) { $lastPresent[$row['student_id']] = $row['last_present']; }

// Stats (global for the date, mirrors the reference stat card links)
$total    = count($allStudents);
$counts   = ['present' => 0, 'absent' => 0, 'late' => 0, 'leave' => 0, 'short_leave' => 0];
foreach ($att_map as $st) { if (isset($counts[$st])) $counts[$st]++; }
$unmarked = $total - count($att_map);
$pct      = $total > 0 ? round((($counts['present'] + $counts['late']) / $total) * 100) : 0;

// Filtered list for the table
$rows = [];
foreach ($allStudents as $sid => $st) {
    $st['att'] = $att_map[$sid] ?? null;
    $st['last_present'] = $lastPresent[$sid] ?? '';
    $status   = $st['att'] ?? '';
    if ($sel_attendance === 'unmarked' && $st['att'] !== null) continue;
    if ($sel_attendance === 'unmarked') { $rows[] = $st; continue; }
    if ($sel_attendance !== 'All' && $sel_attendance !== '') {
        $want = ['P'=>'present','A'=>'absent','L'=>'leave','LA'=>'late','SL'=>'short_leave'][$sel_attendance] ?? '';
        if ($status !== $want) continue;
    }
    $rows[] = $st;
}
// Apply class/section filters on the table rows
if ($sel_class !== 'All' && (int) $sel_class > 0) {
    $rows = array_values(array_filter($rows, function ($r) use ($sel_class) { return (int) $r['class_id'] === (int) $sel_class; }));
}
if ($sel_class === 'All' && $sel_section !== 'All' && (int) $sel_section > 0) {
    $rows = array_values(array_filter($rows, function ($r) use ($sel_section) { return (int) $r['section_id'] === (int) $sel_section; }));
}

// Sections for selected class (server side prefill)
$sections = [];
if ($sel_class !== 'All' && (int) $sel_class > 0) {
    $res = db_query("SELECT section_id, section_name FROM sections WHERE class_id=" . (int) $sel_class . " ORDER BY section_name");
    while ($row = $res->fetch_assoc()) { $sections[] = $row; }
}

function att_badge($status) {
    switch ($status) {
        case 'present':     return ['P',  '#16A34A', '#DCFCE7'];
        case 'absent':      return ['A',  '#DC2626', '#FEE2E2'];
        case 'late':        return ['LA', '#D97706', '#FEF3C7'];
        case 'leave':       return ['L',  '#7C3AED', '#EDE9FE'];
        case 'short_leave': return ['SL', '#2563EB', '#DBEAFE'];
        default:            return ['--', '#6B7280', '#F3F4F6'];
    }
}

include __DIR__ . '/includes/header.php';
?>
<style type="text/css">
  .search-box { display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end; background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:14px; margin-bottom:14px; }
  .attn-summary-row { display:flex; flex-wrap:wrap; gap:8px; margin:0 0 15px 0; padding:0; }
  .attn-stat { flex:1 1 0; min-width:128px; display:flex; align-items:center; gap:8px; background:#FFF; border:1px solid #E4E4E4; border-radius:6px; padding:8px 10px; text-decoration:none; transition:box-shadow .15s ease, border-color .15s ease; }
  .attn-stat:hover { box-shadow:0 2px 6px rgba(0,0,0,0.12); text-decoration:none; }
  .attn-stat.active { border-color:#37474F; box-shadow:0 0 0 2px rgba(55,71,79,0.15); }
  .attn-stat-icon { flex-shrink:0; width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center; color:#FFF; font-size:14px; }
  .attn-stat-text { min-width:0; }
  .attn-stat-count { font-size:18px; font-weight:700; line-height:1.1; color:#2A3F54; }
  .attn-stat-label { font-size:10.5px; color:#73879C; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  @media (max-width:767px){ .attn-summary-row { flex-wrap:wrap; } .attn-stat { min-width:calc(50% - 8px); flex:1 1 calc(50% - 8px); } }
</style>

<div class="main-content">
    <div class="container-fluid">

        <div style="padding:10px 0 14px 0; font-size:13px;">
            <a href="<?php echo BASE_URL; ?>dashboard.php">Dashboard</a> &nbsp; <i class="fa fa-angle-double-right"></i> &nbsp;
            <a href="<?php echo BASE_URL; ?>mark_attendanceReport_list.php">Attendance Reports</a> &nbsp; <i class="fa fa-angle-double-right"></i> &nbsp;
            <strong>Day Attendance Summary (<?php echo count($rows); ?> records)</strong>
        </div>

        <div class="attn-summary-row">
            <a class="attn-stat" href="<?php echo BASE_URL; ?>day_attendance_summary.php?page=&class_head=&class_id=All&section=All&date=<?php echo e($date); ?>&attendance=All" title="View Total Students for <?php echo e($date); ?>">
                <div class="attn-stat-icon" style="background:#5B9BD5;"><i class="fa fa-users"></i></div>
                <div class="attn-stat-text"><div class="attn-stat-count"><?php echo $total; ?></div><div class="attn-stat-label">Total Students</div></div>
            </a>
            <a class="attn-stat <?php echo $sel_attendance==='P' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>day_attendance_summary.php?page=&class_head=&class_id=All&section=All&date=<?php echo e($date); ?>&attendance=P" title="View Present (<?php echo $pct; ?>%) for <?php echo e($date); ?>">
                <div class="attn-stat-icon" style="background:#27AE60;"><i class="fa fa-check"></i></div>
                <div class="attn-stat-text"><div class="attn-stat-count"><?php echo $counts['present']; ?></div><div class="attn-stat-label">Present (<?php echo $pct; ?>%)</div></div>
            </a>
            <a class="attn-stat <?php echo $sel_attendance==='A' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>day_attendance_summary.php?page=&class_head=&class_id=All&section=All&date=<?php echo e($date); ?>&attendance=A" title="View Absent for <?php echo e($date); ?>">
                <div class="attn-stat-icon" style="background:#E74C3C;"><i class="fa fa-times"></i></div>
                <div class="attn-stat-text"><div class="attn-stat-count"><?php echo $counts['absent']; ?></div><div class="attn-stat-label">Absent</div></div>
            </a>
            <a class="attn-stat <?php echo $sel_attendance==='L' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>day_attendance_summary.php?page=&class_head=&class_id=All&section=All&date=<?php echo e($date); ?>&attendance=L" title="View Leave for <?php echo e($date); ?>">
                <div class="attn-stat-icon" style="background:#7F8C8D;"><i class="fa fa-calendar-times-o"></i></div>
                <div class="attn-stat-text"><div class="attn-stat-count"><?php echo $counts['leave']; ?></div><div class="attn-stat-label">Leave</div></div>
            </a>
            <a class="attn-stat <?php echo $sel_attendance==='LA' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>day_attendance_summary.php?page=&class_head=&class_id=All&section=All&date=<?php echo e($date); ?>&attendance=LA" title="View Late for <?php echo e($date); ?>">
                <div class="attn-stat-icon" style="background:#F39C12;"><i class="fa fa-clock-o"></i></div>
                <div class="attn-stat-text"><div class="attn-stat-count"><?php echo $counts['late']; ?></div><div class="attn-stat-label">Late</div></div>
            </a>
            <a class="attn-stat <?php echo $sel_attendance==='SL' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>day_attendance_summary.php?page=&class_head=&class_id=All&section=All&date=<?php echo e($date); ?>&attendance=SL" title="View Short Leave for <?php echo e($date); ?>">
                <div class="attn-stat-icon" style="background:#9B59B6;"><i class="fa fa-sign-out"></i></div>
                <div class="attn-stat-text"><div class="attn-stat-count"><?php echo $counts['short_leave']; ?></div><div class="attn-stat-label">Short Leave</div></div>
            </a>
            <a class="attn-stat <?php echo $sel_attendance==='unmarked' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>day_attendance_summary.php?page=&class_head=&class_id=All&section=All&date=<?php echo e($date); ?>&attendance=unmarked" title="View Unmarked for <?php echo e($date); ?>">
                <div class="attn-stat-icon" style="background:#34495E;"><i class="fa fa-question"></i></div>
                <div class="attn-stat-text"><div class="attn-stat-count"><?php echo $unmarked; ?></div><div class="attn-stat-label">Unmarked</div></div>
            </a>
        </div>

        <form class="search-box" action="<?php echo BASE_URL; ?>day_attendance_summary.php" method="get">
            <input type="hidden" name="page" value="">
            <div class="col-md-2 col-xs-6" style="padding:0 4px;">
                <div class="form-group" style="margin-bottom:0;">
                    <label class="required">Course/Class Head</label>
                    <select name="class_head" id="class_head" class="form-control inputheight" onchange="getClassesByHead(this.value)">
                        <option value="">All</option>
                    </select>
                </div>
            </div>
            <div class="col-md-2 col-xs-6" style="padding:0 4px;">
                <div class="form-group" style="margin-bottom:0;">
                    <label class="required">Course/Class</label>
                    <select name="class_id" id="class_id" class="form-control inputheight" onchange="getSection(this.value)">
                        <option value="All">All</option>
                        <?php foreach ($classes as $c): ?>
                            <option value="<?php echo $c['class_id']; ?>" <?php echo $sel_class == $c['class_id'] ? 'selected' : ''; ?>><?php echo e($c['class_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="col-md-2 col-xs-6" style="padding:0 4px;">
                <div class="form-group" style="margin-bottom:0;">
                    <label class="required">Section</label>
                    <select name="section" id="txt_section" class="form-control inputheight">
                        <option value="All">All</option>
                        <?php foreach ($sections as $sec): ?>
                            <option value="<?php echo $sec['section_id']; ?>" <?php echo $sel_section == $sec['section_id'] ? 'selected' : ''; ?>><?php echo e($sec['section_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="col-md-2 col-xs-9" style="padding:0 4px;">
                <div class="form-group" style="margin-bottom:0;">
                    <label class="required">Attendance Date</label>
                    <input class="form-control" type="date" name="date" id="date" value="<?php echo e($date); ?>" required="">
                </div>
            </div>
            <div class="col-md-2 col-xs-9" style="padding:0 4px;">
                <div class="form-group" style="margin-bottom:0;">
                    <label class="required">Attendance</label>
                    <Select Course/Class="form-control" id="attendance" name="attendance">
                        <option value="All" <?php echo $sel_attendance === 'All' ? 'selected' : ''; ?>>All</option>
                        <option value="P"  <?php echo $sel_attendance === 'P' ? 'selected' : ''; ?>>Present</option>
                        <option value="A"  <?php echo $sel_attendance === 'A' ? 'selected' : ''; ?>>Absent</option>
                        <option value="L"  <?php echo $sel_attendance === 'L' ? 'selected' : ''; ?>>Leave</option>
                        <option value="LA" <?php echo $sel_attendance === 'LA' ? 'selected' : ''; ?>>Late</option>
                        <option value="SL" <?php echo $sel_attendance === 'SL' ? 'selected' : ''; ?>>Short Leave</option>
                        <option value="unmarked" <?php echo $sel_attendance === 'unmarked' ? 'selected' : ''; ?>>Unmarked Students</option>
                    </select>
                </div>
            </div>
            <div class="col-md-2 col-xs-9" style="padding:0 4px;">
                <div class="form-group" style="margin-bottom:0;">
                    <label style="opacity:0;">Search</label>
                    <button type="submit" class="btn btn-primary" style="white-space:nowrap;"><i class="fa fa-search"></i> Search</button>
                </div>
            </div>
            <div class="clearfix"></div>
        </form>

        <div class="col-md-12" style="padding:0;">
            <div class="form-group" style="display:flex; align-items:center; gap:8px; margin:6px 0 10px 0;">
                <input type="text" id="tableSearch" class="form-control" style="max-width:280px;" placeholder="Search student name...">
            </div>
            <div style="overflow-x:auto; background:#fff; border:1px solid #E5E7EB; border-radius:14px;">
                <table id="listofstudents" class="table table-striped table-bordered" style="width:100%; background:#fff; margin-bottom:0;">
                    <thead>
                        <tr style="color:#2A3F54;">
                            <th>S.No</th><th>Student Name</th><th>Father Name</th><th>Cell No</th>
                            <th>Roll#</th><th style="text-align:center;">Course/Class / Sec</th><th style="text-align:center;">Date</th>
                            <th>Attendance</th><th style="text-align:center;">Last Present</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($rows) === 0): ?>
                            <tr><td colspan="9" style="text-align:center; color:#6B7280; padding:30px;">No data available for the selected filters.</td></tr>
                        <?php endif; ?>
                        <?php $i = 1; foreach ($rows as $st): list($lbl, $fg, $bg) = att_badge($st['att'] ?? ''); ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td><strong><?php echo e($st['first_name']); ?></strong></td>
                                <td><?php echo e($st['father_name'] ?? $st['last_name']); ?></td>
                                <td><?php echo e($st['phone'] ?? $st['father_cellno'] ?? '-'); ?></td>
                                <td><?php echo e($st['roll_no'] ?? $st['gr_no'] ?? $st['student_id']); ?></td>
                                <td style="text-align:center;"><?php echo e($st['class_name'] ?? '-'); ?> / <?php echo e($st['section_name'] ?? '-'); ?></td>
                                <td style="text-align:center;"><?php echo e($date); ?></td>
                                <td><span style="display:inline-block; padding:3px 10px; border-radius:20px; font-size:12px; font-weight:700; color:<?php echo $fg; ?>; background:<?php echo $bg; ?>;"><?php echo $lbl; ?></span></td>
                                <td style="text-align:center;"><?php echo $st['last_present'] ? e($st['last_present']) : '-'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<script>
document.getElementById('tableSearch').addEventListener('input', function(){
    var q = this.value.toLowerCase();
    var rows = document.querySelectorAll('#listofstudents tbody tr');
    rows.forEach(function(tr){
        if (tr.querySelector('td[colspan]')) return;
        tr.style.display = tr.textContent.toLowerCase().indexOf(q) > -1 ? '' : 'none';
    });
});

function getSection(val){
    var sel = document.getElementById('txt_section');
    sel.innerHTML = '<option value="All">All</option>';
    if (!val || val === 'All') return;
    fetch('<?php echo BASE_URL; ?>ajax_get_sections.php?class_id=' + val)
        .then(function(r){ return r.json(); })
        .then(function(data){
            data.forEach(function(s){
                var o = document.createElement('option');
                o.value = s.section_id; o.textContent = s.section_name;
                sel.appendChild(o);
            });
        })
        .catch(function(){ /* fallback: All only */ });
}
function getClassesByHead(val){
    if (!val) return;
    fetch('<?php echo BASE_URL; ?>ajax_get_classes_by_head.php?head=' + encodeURIComponent(val))
        .then(function(r){ return r.json(); })
        .then(function(data){
            if (data && data.length) {
                var sel = document.getElementById('class_id');
                sel.innerHTML = '<option value="All">All</option>';
                data.forEach(function(c){
                    var o = document.createElement('option');
                    o.value = c.class_id; o.textContent = c.class_name;
                    sel.appendChild(o);
                });
                getSection('');
            }
        })
        .catch(function(){ /* capability fallback: keep all classes */ });
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
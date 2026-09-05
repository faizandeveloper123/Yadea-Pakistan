<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'View Datesheet';

if (!function_exists('hiifi_ensure_exam_schema')) {
    function hiifi_ensure_exam_schema() {
        $queries = [
            "ALTER TABLE exams ADD COLUMN IF NOT EXISTS session VARCHAR(50) DEFAULT NULL AFTER exam_name",
            "ALTER TABLE exams ADD COLUMN IF NOT EXISTS exam_type VARCHAR(20) DEFAULT NULL AFTER session",
            "ALTER TABLE exams ADD COLUMN IF NOT EXISTS display_mobile VARCHAR(3) DEFAULT 'YES' AFTER exam_type",
            "CREATE TABLE IF NOT EXISTS datesheet (
                id INT AUTO_INCREMENT PRIMARY KEY,
                exam_id INT NOT NULL,
                class_id INT NOT NULL,
                section_id INT DEFAULT NULL,
                subject_id INT NOT NULL,
                exam_date DATE DEFAULT NULL,
                exam_time VARCHAR(80) DEFAULT NULL,
                UNIQUE KEY uq_ds (exam_id, class_id, section_id, subject_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS syllabus_entry (
                id INT AUTO_INCREMENT PRIMARY KEY,
                class_id INT NOT NULL,
                section_id INT NOT NULL DEFAULT 0,
                subject_id INT NOT NULL,
                term_id INT NOT NULL,
                syllabus TEXT,
                UNIQUE KEY uq_syl (class_id, section_id, subject_id, term_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        ];
        foreach ($queries as $sql) {
            try { db_query($sql); } catch (Throwable $e) {}
        }
        try {
            $res = db_query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'datesheet\\_%'");
            if ($res) {
                $ins = db_prepare("INSERT INTO datesheet (exam_id, class_id, section_id, subject_id, exam_date, exam_time) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE exam_date=VALUES(exam_date), exam_time=VALUES(exam_time)");
                while ($row = $res->fetch_assoc()) {
                    $parts = explode('_', $row['setting_key']);
                    if (count($parts) < 4) continue;
                    $exam = (int)$parts[1];
                    $class = (int)$parts[2];
                    $sub = (int)$parts[3];
                    if ($exam <= 0 || $class <= 0 || $sub <= 0) continue;
                    $v = explode('|', $row['setting_value']);
                    $date = $v[0] ?? null;
                    $time = $v[1] ?? null;
                    $sec = null;
                    $ins->bind_param('iiisss', $exam, $class, $sec, $sub, $date, $time);
                    try { $ins->execute(); } catch (Throwable $e) {}
                }
            }
        } catch (Throwable $e) {}
    }
}
hiifi_ensure_exam_schema();

$message = '';
$error = '';

$sessions = [];
for ($y = 2018; $y <= 2030; $y++) { $sessions[] = $y . '-' . ($y + 1); }
$cur_session = get_setting('session_year', '2026-2027');
if (!in_array($cur_session, $sessions, true)) { array_unshift($sessions, $cur_session); }

$f_session = trim($_REQUEST['session'] ?? '');
$f_term = (int)($_REQUEST['term_id'] ?? 0);

$terms = [];
if ($f_session !== '') {
    try {
        $st = db_prepare("SELECT * FROM exams WHERE session=? ORDER BY exam_date ASC, exam_name ASC");
        $st->bind_param('s', $f_session);
        $st->execute();
        $res = $st->get_result();
        while ($row = $res->fetch_assoc()) { $terms[] = $row; }
    } catch (Throwable $e) { $terms = []; }
}

$term_info = null;
$by_class = [];
if ($f_term > 0) {
    foreach ($terms as $t) { if ((int)$t['exam_id'] === $f_term) { $term_info = $t; break; } }
    try {
        $res = db_query("SELECT d.*, s.subject_name, s.subject_code, c.class_name FROM datesheet d
            LEFT JOIN subjects s ON s.subject_id=d.subject_id
            LEFT JOIN classes c ON c.class_id=d.class_id
            WHERE d.exam_id=$f_term ORDER BY c.class_name ASC, d.exam_date ASC, s.subject_name ASC");
        while ($row = $res->fetch_assoc()) {
            $cid = (int)$row['class_id'];
            $by_class[$cid]['class_name'] = $row['class_name'] ?? ('Class #' . $cid);
            $by_class[$cid]['rows'][] = $row;
        }
    } catch (Throwable $e) { $by_class = []; }
}

$s2css = __DIR__ . '/assets/plugins/select2/select2.min.css';
$s2js = __DIR__ . '/assets/plugins/select2/select2.min.js';

include __DIR__ . '/includes/header.php';
?>
<?php if (file_exists($s2css)): ?><link href="<?php echo BASE_URL; ?>assets/plugins/select2/select2.min.css" rel="stylesheet"><?php endif; ?>
<?php if (file_exists($s2js)): ?><script src="<?php echo BASE_URL; ?>assets/plugins/select2/select2.min.js"></script><?php endif; ?>
<style>
.search-bar-student { display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end; background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:16px; margin-bottom:16px; }
.crumb { font-size:13px; color:#6B7280; margin:6px 4px 14px; }
.crumb a { color:#e67e22; text-decoration:none; }
.crumb a:hover { text-decoration:underline; }
.panel-white { background:#fff; border:1px solid #E5E7EB; border-radius:14px; }
.exp-toggle { cursor:pointer; font-size:16px; }
.exp-row { display:none; background:#f9fafb; }
@media print {
    .no-print, form, .nav-container, .crumb { display:none !important; }
    body { background:#fff !important; }
    .panel-white { border:none; box-shadow:none; }
}
</style>

<div class="main-content">
    <div class="container-fluid">
        <?php if ($message): ?><div class="alert alert-success"><?php echo e($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>

        <div style="display:flex; align-items:center; justify-content:space-between; padding:14px 4px 6px; flex-wrap:wrap; gap:10px;">
            <h3 style="font-size:18px; font-weight:800; color:#111827; margin:0;"><i class="fa fa-calendar-check-o"></i> View Date Sheet</h3>
            <button type="button" class="btn btn-success no-print" onclick="window.print();" style="color:#fff;"><i class="fa fa-print"></i> Print</button>
        </div>
        <div class="crumb"><a href="<?php echo BASE_URL; ?>dashboard.php">Dashboard</a> &nbsp;<i class="fa fa-angle-double-right"></i>&nbsp; <a href="<?php echo BASE_URL; ?>view_datesheet.php">Datesheet</a> &nbsp;<i class="fa fa-angle-double-right"></i>&nbsp; View Date Sheet</div>

        <div class="alert alert-info" style="font-size:14px;">
            <i class="fa fa-info-circle"></i> Click the <strong>+</strong> icon next to a class to expand its subject-wise date &amp; time schedule.
        </div>

        <form method="get" action="view_datesheet.php" class="search-bar-student">
            <div class="form-group col-md-3 col-sm-6" style="margin-bottom:0;">
                <label class="required">Session</label>
                <select name="session" class="form-control form-select2" onchange="this.form.submit()">
                    <option value="">Select Session</option>
                    <?php foreach ($sessions as $s): ?>
                        <option value="<?php echo e($s); ?>" <?php echo $f_session === $s ? 'selected' : ''; ?>><?php echo e($s); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group col-md-3 col-sm-6" style="margin-bottom:0;">
                <label class="required">Term</label>
                <select name="term_id" class="form-control form-select2">
                    <option value="">Select Term</option>
                    <?php foreach ($terms as $t): ?>
                        <option value="<?php echo (int)$t['exam_id']; ?>" <?php echo $f_term === (int)$t['exam_id'] ? 'selected' : ''; ?>><?php echo e($t['exam_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group col-md-2 col-sm-4" style="margin-bottom:0;">
                <button type="submit" class="btn btn-primary" style="margin-top:24px; width:100%;"><i class="fa fa-search"></i> Search</button>
            </div>
        </form>

        <?php if ($f_session === '' || $f_term <= 0): ?>
            <div class="alert alert-danger" role="alert">
                <strong><i class="fa fa-exclamation-triangle"></i> Warning !</strong> Please select Session and Term to view the Date Sheet !!!
            </div>
        <?php else: ?>
            <div class="panel-white" style="overflow-x:auto;">
                <?php if (count($by_class) === 0): ?>
                    <div style="text-align:center; color:#6B7280; padding:40px;">
                        <i class="fa fa-calendar-times-o" style="font-size:28px; display:block; margin-bottom:8px; color:#D1D5DB;"></i>
                        No date sheet entries found for <?php echo e($term_info['exam_name'] ?? ('Term #' . $f_term)); ?>. Use "Create Date Sheet" to add entries.
                    </div>
                <?php else: ?>
                    <table class="table table-bordered" style="width:100%; background:#fff; margin-bottom:0;">
                        <thead>
                            <tr>
                                <th style="width:5%; text-align:center;"></th>
                                <th>Course/Class</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($by_class as $cid => $grp): ?>
                                <tr data-toggle-row>
                                    <td style="text-align:center;"><span class="exp-toggle" data-target="exp<?php echo (int)$cid; ?>" onclick="toggleExpand(this);"><i class="fa fa-plus-circle" style="color:#e67e22;"></i></span></td>
                                    <td><strong><?php echo e($grp['class_name']); ?></strong> <span class="status-badge" style="background:#E0E7FF; color:#4338CA; margin-left:6px;"><?php echo count($grp['rows']); ?> subjects</span></td>
                                </tr>
                                <tr class="exp-row" id="exp<?php echo (int)$cid; ?>">
                                    <td colspan="2" style="padding:0;">
                                        <table class="table table-bordered" style="width:100%; background:#f9fafb; margin-bottom:0; font-size:13.5px;">
                                            <thead>
                                                <tr>
                                                    <th style="width:5%; text-align:center;">#</th>
                                                    <th>Subject</th>
                                                    <th style="width:22%;">Date</th>
                                                    <th style="width:22%;">Time</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php $n = 1; foreach ($grp['rows'] as $r): ?>
                                                    <tr>
                                                        <td style="text-align:center;"><?php echo $n++; ?></td>
                                                        <td><strong><?php echo e($r['subject_name'] ?? 'Subject #' . (int)$r['subject_id']); ?></strong> <?php echo !empty($r['subject_code']) ? '(' . e($r['subject_code']) . ')' : ''; ?></td>
                                                        <td><?php echo $r['exam_date'] ? date('d M Y', strtotime($r['exam_date'])) : '-'; ?></td>
                                                        <td><?php echo e($r['exam_time'] ?? '-'); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleExpand(el){
    var id = el.getAttribute('data-target');
    var row = document.getElementById(id);
    if (!row) return;
    var isHidden = row.style.display === 'none' || !row.style.display;
    row.style.display = isHidden ? '' : 'none';
    var icon = el.querySelector('i');
    if (icon) icon.className = isHidden ? 'fa fa-minus-circle' : 'fa fa-plus-circle';
}
</script>
<script>
(function(){
    function initFormSelect2(){
        var S2 = (typeof window.Select2 === 'function') ? window.Select2 : ((window.jQuery && jQuery.fn && jQuery.fn.select2) ? jQuery.fn.select2 : null);
        if (!S2 || !window.jQuery) return;
        jQuery('.form-select2').each(function(){ try { S2.call(jQuery(this), { width: '100%' }); } catch (e) {} });
    }
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', initFormSelect2); } else { initFormSelect2(); }
    window.addEventListener('load', initFormSelect2);
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
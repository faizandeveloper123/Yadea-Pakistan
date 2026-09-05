<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Create Datesheet';

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
$f_dayno = (int)($_REQUEST['dayno'] ?? 5);
if ($f_dayno < 1 || $f_dayno > 30) { $f_dayno = 5; }

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'SaveDatesheet') {
    $term_id = (int)($_POST['term_id'] ?? ($_POST['exam_id'] ?? 0));
    $entries = $_POST['ds'] ?? [];
    if ($term_id <= 0 || !is_array($entries)) {
        $error = 'Invalid term. Please select Session and Term to creat Date Sheet !!!';
    } else {
        $saved = 0;
        try {
            $st3 = db_prepare("INSERT INTO datesheet (exam_id, class_id, section_id, subject_id, exam_date, exam_time) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE exam_date=VALUES(exam_date), exam_time=VALUES(exam_time)");
            $sec = null;
            foreach ($entries as $k1 => $v1) {
                if (!is_array($v1)) continue;
                $class_id = (int)$k1;
                if ($class_id <= 0) continue;
                foreach ($v1 as $sub_id => $info) {
                    $sub_id = (int)$sub_id;
                    if ($sub_id <= 0 || !is_array($info)) continue;
                    $date = trim($info['date'] ?? '');
                    $time = trim($info['time'] ?? '');
                    $ed = $date !== '' ? $date : null;
                    $et = $time !== '' ? $time : null;
                    $st3->bind_param('iiisss', $term_id, $class_id, $sec, $sub_id, $ed, $et);
                    $st3->execute();
                    $saved++;
                }
            }
            $message = "Date sheet saved with $saved entries!";
        } catch (Throwable $e) {
            $error = 'Save failed: ' . $e->getMessage();
        }
    }
}

$subjects_by_class = [];
$class_names = [];
try {
    $res = db_query("SELECT s.*, c.class_name FROM subjects s JOIN classes c ON c.class_id=s.class_id AND c.status=1 ORDER BY c.class_name ASC, s.subject_name ASC");
    while ($row = $res->fetch_assoc()) {
        $class_id = (int)$row['class_id'];
        $subjects_by_class[$class_id][] = $row;
        $class_names[$class_id] = $row['class_name'];
    }
} catch (Throwable $e) { $subjects_by_class = []; }

$existing = [];
if ($f_term > 0) {
    try {
        $res = db_query("SELECT * FROM datesheet WHERE exam_id=$f_term");
        while ($row = $res->fetch_assoc()) {
            $existing[(int)$row['class_id']][(int)$row['subject_id']] = $row;
        }
    } catch (Throwable $e) { $existing = []; }
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
.ds-class-head { background:#f9fafb; border-bottom:1px solid #E5E7EB; padding:10px 14px; font-weight:700; color:#111827; }
</style>

<div class="main-content">
    <div class="container-fluid">
        <?php if ($message): ?><div class="alert alert-success"><?php echo e($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>

        <div style="padding:14px 4px 6px;">
            <h3 style="font-size:18px; font-weight:800; color:#111827; margin:0;"><i class="fa fa-calendar"></i> Create Date Sheet</h3>
        </div>
        <div class="crumb"><a href="<?php echo BASE_URL; ?>dashboard.php">Dashboard</a> &nbsp;<i class="fa fa-angle-double-right"></i>&nbsp; <a href="<?php echo BASE_URL; ?>create_datesheet.php">Datesheet</a> &nbsp;<i class="fa fa-angle-double-right"></i>&nbsp; Create Date Sheet</div>

        <div class="alert alert-warning" style="color:#111; font-size:14px;">
            <strong>Instruction!</strong> Software will automatically pick the day of each date !!! To add multi subject paper in same date, save the same date on multiple subjects.
        </div>

        <form method="get" action="create_datesheet.php" class="search-bar-student">
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
                <label class="required">Number Of Days</label>
                <select name="dayno" class="form-control form-select2">
                    <?php for ($d = 1; $d <= 30; $d++): ?>
                        <option value="<?php echo $d; ?>" <?php echo $f_dayno === $d ? 'selected' : ''; ?>><?php echo $d; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="form-group col-md-2 col-sm-4" style="margin-bottom:0;">
                <button type="submit" class="btn btn-primary" style="margin-top:24px; width:100%;"><i class="fa fa-search"></i> Search</button>
            </div>
        </form>

        <?php if ($f_session === '' || $f_term <= 0): ?>
            <div class="alert alert-danger" role="alert">
                <strong><i class="fa fa-exclamation-triangle"></i> Warning !</strong> Please select Session and Term to creat Date Sheet !!!
            </div>
        <?php else: ?>
            <?php $classes_with_subjects = array_filter(array_keys($subjects_by_class), function($cid) { return count($subjects_by_class[$cid]) > 0; }); ?>
            <?php if (count($classes_with_subjects) === 0): ?>
                <div class="panel-white" style="text-align:center; color:#6B7280; padding:40px;">
                    <i class="fa fa-book" style="font-size:28px; display:block; margin-bottom:8px; color:#D1D5DB;"></i>
                    No subjects found. Add subjects for classes first, then create the date sheet.
                </div>
            <?php else: ?>
                <form method="post" action="create_datesheet.php">
                    <input type="hidden" name="action" value="SaveDatesheet">
                    <input type="hidden" name="term_id" value="<?php echo $f_term; ?>">
                    <div class="panel-white" style="overflow:hidden;">
                        <div style="padding:12px 16px; background:linear-gradient(135deg,#ff9800,#ff7800); color:#fff; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
                            <strong style="font-size:15px;"><i class="fa fa-calendar-check-o"></i> <?php echo count($terms) > 0 ? e($terms[0]['exam_name']) : ('Term #' . $f_term); ?> — Date Sheet</strong>
                            <span style="font-size:12.5px; opacity:.95;">Exam period: <?php echo $f_dayno; ?> working day(s)</span>
                        </div>
                        <?php foreach ($subjects_by_class as $class_id => $subs): ?>
                            <div class="ds-class-head"><?php echo e($class_names[$class_id] ?? ('Class #' . $class_id)); ?></div>
                            <table class="table table-bordered" style="width:100%; background:#fff; margin-bottom:0; font-size:14px;">
                                <thead>
                                    <tr><th style="width:35%;">Subject</th><th style="width:30%;">Date</th><th style="width:35%;">Time</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($subs as $sub): $sl = $existing[$class_id][$sub['subject_id']] ?? null; ?>
                                        <tr>
                                            <td><strong><?php echo e($sub['subject_name']); ?></strong> <?php echo $sub['subject_code'] ? '(' . e($sub['subject_code']) . ')' : ''; ?></td>
                                            <td><input type="date" name="ds[<?php echo (int)$class_id; ?>][<?php echo (int)$sub['subject_id']; ?>][date]" class="form-control" value="<?php echo e($sl['exam_date'] ?? ''); ?>"></td>
                                            <td><input type="text" name="ds[<?php echo (int)$class_id; ?>][<?php echo (int)$sub['subject_id']; ?>][time]" class="form-control" placeholder="09:00 AM" value="<?php echo e($sl['exam_time'] ?? ''); ?>"></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endforeach; ?>
                        <div style="padding:14px; text-align:right; background:#fff;">
                            <button type="submit" class="btn btn-success" style="padding:10px 30px;"><i class="fa fa-save"></i> Save Date Sheet</button>
                        </div>
                    </div>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

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
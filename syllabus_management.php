<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Syllabus Management';

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
$f_class = (int)($_REQUEST['class_id'] ?? 0);
$f_section = (int)($_REQUEST['section'] ?? 0);

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
foreach ($terms as $t) { if ((int)$t['exam_id'] === $f_term) { $term_info = $t; break; } }

$classes = [];
try {
    $res = db_query("Select class_id, class_name FROM classes WHERE status=1 ORDER BY class_name");
    while ($row = $res->fetch_assoc()) { $classes[] = $row; }
} catch (Throwable $e) { $classes = []; }

$sections = [];
if ($f_class > 0) {
    try {
        $res = db_query("SELECT section_id, section_name FROM sections WHERE class_id=$f_class ORDER BY section_name");
        while ($row = $res->fetch_assoc()) { $sections[] = $row; }
    } catch (Throwable $e) { $sections = []; }
}

$subjects = [];
$existing = [];
$loaded = ($f_session !== '' && $f_term > 0 && $f_class > 0);
if ($loaded) {
    try {
        $res = db_query("SELECT * FROM subjects WHERE class_id=$f_class ORDER BY subject_name");
        while ($row = $res->fetch_assoc()) { $subjects[] = $row; }
        $res = db_query("SELECT * FROM syllabus_entry WHERE term_id=$f_term AND class_id=$f_class AND section_id=$f_section");
        while ($row = $res->fetch_assoc()) { $existing[(int)$row['subject_id']] = $row['syllabus']; }
    } catch (Throwable $e) { $subjects = []; $existing = []; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'SaveSyllabus') {
    $class_id = (int)($_POST['class_id'] ?? 0);
    $section_id = (int)($_POST['section_id'] ?? 0);
    $term_id = (int)($_POST['term_id'] ?? 0);
    $session = trim($_POST['session'] ?? '');
    $syl = $_POST['syl'] ?? [];
    if ($class_id <= 0 || $term_id <= 0) {
        $error = 'Please select Term/Class and search to create Syllabus !!!';
    } else {
        $saved = 0;
        try {
            $st = db_prepare("INSERT INTO syllabus_entry (class_id, section_id, subject_id, term_id, syllabus) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE syllabus=VALUES(syllabus)");
            foreach ($syl as $subject_id => $text) {
                $subject_id = (int)$subject_id;
                if ($subject_id <= 0) continue;
                $text = trim((string)$text);
                $st->bind_param('iiiss', $class_id, $section_id, $subject_id, $term_id, $text);
                $st->execute();
                $saved++;
            }
            $message = "Syllabus saved for $saved subject(s)!";
            if ($class_id === $f_class && $section_id === $f_section) {
                $existing = [];
                $res = db_query("SELECT * FROM syllabus_entry WHERE term_id=$term_id AND class_id=$class_id AND section_id=$section_id");
                while ($row = $res->fetch_assoc()) { $existing[(int)$row['subject_id']] = $row['syllabus']; }
            }
        } catch (Throwable $e) {
            $error = 'Save failed: ' . $e->getMessage();
        }
    }
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
.syl-textarea { min-height:110px; resize:vertical; }
@media print {
    .no-print, form, .crumb { display:none !important; }
}
</style>

<div class="main-content">
    <div class="container-fluid">
        <?php if ($message): ?><div class="alert alert-success"><?php echo e($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>

        <div style="display:flex; align-items:center; justify-content:space-between; padding:14px 4px 6px; flex-wrap:wrap; gap:10px;">
            <h3 style="font-size:18px; font-weight:800; color:#111827; margin:0;"><i class="fa fa-book"></i> Syllabus Management</h3>
            <?php if ($loaded): ?>
                <button type="button" class="btn btn-success no-print" onclick="window.print();" style="color:#fff;"><i class="fa fa-print"></i> Print</button>
            <?php endif; ?>
        </div>
        <div class="crumb"><a href="<?php echo BASE_URL; ?>dashboard.php">Dashboard</a> &nbsp;<i class="fa fa-angle-double-right"></i>&nbsp; <a href="<?php echo BASE_URL; ?>syllabus_management.php">Datesheet</a> &nbsp;<i class="fa fa-angle-double-right"></i>&nbsp; Syllabus Management</div>

        <form method="get" action="syllabus_management.php" class="search-bar-student">
            <div class="form-group col-md-2 col-sm-6" style="margin-bottom:0;">
                <label class="required">Session</label>
                <select name="session" class="form-control form-select2" onchange="this.form.submit()">
                    <option value="">Select Session</option>
                    <?php foreach ($sessions as $s): ?>
                        <option value="<?php echo e($s); ?>" <?php echo $f_session === $s ? 'selected' : ''; ?>><?php echo e($s); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group col-md-2 col-sm-6" style="margin-bottom:0;">
                <label class="required">Term</label>
                <select name="term_id" class="form-control form-select2">
                    <option value="">Select Term</option>
                    <?php foreach ($terms as $t): ?>
                        <option value="<?php echo (int)$t['exam_id']; ?>" <?php echo $f_term === (int)$t['exam_id'] ? 'selected' : ''; ?>><?php echo e($t['exam_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group col-md-2 col-sm-4" style="margin-bottom:0;">
                <label class="required">Course/Class</label>
                <select name="class_id" class="form-control form-select2" onchange="this.form.submit()">
                    <option value="">Select Course/Class</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?php echo (int)$c['class_id']; ?>" <?php echo $f_class === (int)$c['class_id'] ? 'selected' : ''; ?>><?php echo e($c['class_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group col-md-2 col-sm-4" style="margin-bottom:0;">
                <label>Section</label>
                <select name="section" class="form-control form-select2">
                    <option value="0" <?php echo $f_section === 0 ? 'selected' : ''; ?>>All</option>
                    <?php foreach ($sections as $s): ?>
                        <option value="<?php echo (int)$s['section_id']; ?>" <?php echo $f_section === (int)$s['section_id'] ? 'selected' : ''; ?>><?php echo e($s['section_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group col-md-2 col-sm-4" style="margin-bottom:0;">
                <button type="submit" class="btn btn-primary" style="margin-top:24px; width:100%;"><i class="fa fa-search"></i> Search</button>
            </div>
        </form>

        <?php if (!$loaded): ?>
            <div class="alert alert-danger" role="alert">
                <strong><i class="fa fa-exclamation-triangle"></i> Warning !</strong> Please select Term/Class and search to create Syllabus !!!
            </div>
        <?php elseif (count($subjects) === 0): ?>
            <div class="panel-white" style="text-align:center; color:#6B7280; padding:40px;">
                <i class="fa fa-book" style="font-size:28px; display:block; margin-bottom:8px; color:#D1D5DB;"></i>
                No subjects found for this class. Please add subjects for the class first.
            </div>
        <?php else: ?>
            <?php $class_name_q = ''; foreach ($classes as $c) { if ((int)$c['class_id'] === $f_class) { $class_name_q = $c['class_name']; break; } } ?>
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px; margin-bottom:12px;">
                <div>
                    <strong style="font-size:15px; color:#111827;"><?php echo e($term_info['exam_name'] ?? ('Term #' . $f_term)); ?> — <?php echo e($class_name_q ?: ('Class #' . $f_class)); ?></strong>
                    <span class="status-badge" style="background:#E0E7FF; color:#4338CA; margin-left:6px;">Section: <?php echo $f_section === 0 ? 'All' : e((function() use ($f_section, $sections) { foreach ($sections as $s) { if ((int)$s['section_id'] === $f_section) return $s['section_name']; } return ''; })()); ?></span>
                </div>
            </div>

            <form method="post" action="syllabus_management.php">
                <input type="hidden" name="action" value="SaveSyllabus">
                <input type="hidden" name="session" value="<?php echo e($f_session); ?>">
                <input type="hidden" name="term_id" value="<?php echo $f_term; ?>">
                <input type="hidden" name="class_id" value="<?php echo $f_class; ?>">
                <input type="hidden" name="section_id" value="<?php echo $f_section; ?>">
                <div class="panel-white" style="overflow:hidden;">
                    <div style="padding:12px 16px; background:linear-gradient(135deg,#ff9800,#ff7800); color:#fff; font-weight:700; font-size:14.5px;">
                        <i class="fa fa-book"></i> Syllabus Editor — <?php echo e($term_info['exam_name'] ?? ''); ?>, <?php echo e($class_name_q ?: ''); ?>
                    </div>
                    <div style="padding:16px;">
                        <?php foreach ($subjects as $idx => $sub): ?>
                            <div style="background:#fff; border:1px solid #E5E7EB; border-radius:10px; margin-bottom:14px; padding:14px;">
                                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:6px; margin-bottom:8px;">
                                    <strong style="color:#111827;"><?php echo $idx + 1; ?>. <?php echo e($sub['subject_name']); ?> <?php echo $sub['subject_code'] ? '(' . e($sub['subject_code']) . ')' : ''; ?></strong>
                                    <?php if (!empty($existing[$sub['subject_id']] ?? '')): ?>
                                        <span class="status-badge status-present">Saved</span>
                                    <?php endif; ?>
                                </div>
                                <textarea name="syl[<?php echo (int)$sub['subject_id']; ?>]" class="form-control syl-textarea" placeholder="Enter syllabus / topics to cover for <?php echo e($sub['subject_name']); ?> in this term..."><?php echo e($existing[$sub['subject_id']] ?? ''); ?></textarea>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div style="padding:14px 16px; text-align:right; background:#fff; border-top:1px solid #E5E7EB;">
                        <button type="submit" class="btn btn-success" style="padding:10px 30px;"><i class="fa fa-save"></i> Save Syllabus</button>
                    </div>
                </div>
            </form>

            <div class="panel-white no-print" style="margin-top:18px; overflow-x:auto;">
                <div style="padding:12px 16px; border-bottom:1px solid #E5E7EB; font-weight:700; color:#111827;"><i class="fa fa-table"></i> Printable Syllabus — Subject to Syllabus</div>
                <table class="table table-bordered" id="print_table" style="width:100%; background:#fff; margin-bottom:0; font-size:13.5px;">
                    <thead>
                        <tr><th style="width:25%;">Subject</th><th>Syllabus</th></tr>
                    </thead>
                    <tbody>
                        <?php if (count($subjects) === 0): ?>
                            <tr><td colspan="2" style="text-align:center; color:#6B7280; padding:25px;">No subjects.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($subjects as $sub): ?>
                            <tr>
                                <td><strong><?php echo e($sub['subject_name']); ?></strong></td>
                                <td style="white-space:pre-wrap;"><?php echo nl2br(e($existing[$sub['subject_id']] ?? '')); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div style="padding:12px 16px; text-align:right; border-top:1px solid #E5E7EB;">
                    <button type="button" class="btn btn-success" onclick="printSyllabusTable();"><i class="fa fa-print"></i> Print Syllabus</button>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function printSyllabusTable(){
    var table = document.getElementById('print_table');
    if (!table) { window.print(); return; }
    var html = '<html><head><title>Print Syllabus</title></head><body style="font-family:Segoe UI,Arial,sans-serif;">'
        + '<h3 style="text-align:center; margin-bottom:4px;"><?php echo addslashes(e(($term_info['exam_name'] ?? '') . ' Syllabus')); ?></h3>'
        + '<p style="text-align:center; color:#555; margin-top:0;"><?php echo addslashes(e($loaded ? ($class_name_q ?? '') : '')); ?></p>'
        + table.outerHTML
        + '</body></html>';
    var w = window.open('', '_blank', 'width=900,height=700');
    if (!w) { window.print(); return; }
    w.document.write(html);
    w.document.close();
    w.focus();
    setTimeout(function(){ w.print(); }, 350);
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
<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Promote / Demote Students';

$studentIds = array_filter(array_map('intval', explode(',', trim($_GET['student_ids'] ?? ''))));
$from_session = trim($_GET['session'] ?? '');
$from_class   = (int) ($_GET['class_id'] ?? 0);
$from_section = (int) ($_GET['section'] ?? 0);

$classes  = [];
$res = db_query("Select class_id, class_name FROM classes WHERE status=1 ORDER BY class_name");
while ($row = $res->fetch_assoc()) { $classes[] = $row; }

$sessions = [];
$rs = db_query("SELECT DISTINCT session FROM students WHERE session IS NOT NULL AND session <> '' ORDER BY session DESC");
while ($row = $rs->fetch_assoc()) { $sessions[] = $row['session']; }
if (!$sessions) { $sessions = ['2026-2027', '2025-2026', '2024-2025']; }

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'DoPromote') {
    $to_class   = (int) ($_POST['to_class'] ?? 0);
    $to_section = (int) ($_POST['to_section'] ?? 0) ?: null;
    $to_session = trim($_POST['to_session'] ?? '');
    $mode       = $_POST['mode'] ?? 'promote'; // promote | demote
    $ids        = $_POST['student_ids'] ?? [];

    if (count($ids) === 0) {
        $error = 'No students selected.';
    } elseif ($to_class <= 0) {
        $error = 'Please select a target class.';
    } else {
        $count = 0;
        foreach ($ids as $sid) {
            $sid = (int) $sid;
            if ($sid <= 0) continue;
            if ($mode === 'demote') {
                // Demote: move to a lower class selected by user (same form).
                $up = db_prepare("UPDATE students SET class_id=?, section_id=?, session=? WHERE student_id=?");
                $up->bind_param('iisi', $to_class, $to_section, $to_session, $sid);
                $up->execute();
                $count++;
            } else {
                $up = db_prepare("UPDATE students SET class_id=?, section_id=?, session=? WHERE student_id=?");
                $up->bind_param('iisi', $to_class, $to_section, $to_session, $sid);
                $up->execute();
                $count++;
            }
        }
        $message = "$count student(s) promoted successfully!";
    }
}

include __DIR__ . '/includes/header.php';
?>
<style>
.pd-box { padding:4px 6px; }
.pd-title { font-size:15px; font-weight:800; color:#111827; margin-bottom:12px; }
.pd-summary { font-size:12.5px; color:#6B7280; background:#FFF7ED; border:1px solid #FED7AA; border-radius:8px; padding:8px 12px; margin-bottom:14px; }
</style>
<div style="padding:10px 16px;">
    <div class="pd-box">
        <div class="pd-title"><i class="fa fa-users"></i> Promote / Demote Students</div>

        <?php if ($message): ?><div class="alert alert-success"><?php echo e($message); ?>
            <br><a href="class_promotion.php" target="_parent" style="color:#FF7C1B; font-weight:700;">&larr; Back to Course/Class Promotion</a>
        </div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>

        <div class="pd-summary">
            <strong>Selected students:</strong> <?php echo count($studentIds); ?> &nbsp;|&nbsp;
            <strong>From Session:</strong> <?php echo $from_session !== '' ? e($from_session) : 'Any'; ?>
        </div>

        <form method="post" action="<?php echo BASE_URL; ?>promotDemotPopup.php?<?php echo http_build_query(['student_ids' => implode(',', $studentIds), 'session' => $from_session, 'class_id' => $from_class, 'section' => $from_section]); ?>" enctype="multipart/form-data">
            <input type="hidden" name="action" value="DoPromote">
            <input type="hidden" name="mode" value="promote">
            <?php foreach ($studentIds as $sid): ?>
                <input type="hidden" name="student_ids[]" value="<?php echo $sid; ?>">
            <?php endforeach; ?>

            <div class="row">
                <div class="form-group col-md-4">
                    <label>Target Session</label>
                    <select name="to_session" class="form-control" required>
                        <option value="">Select Session</option>
                        <?php foreach ($sessions as $ss): ?>
                            <option value="<?php echo e($ss); ?>"><?php echo e($ss); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-4">
                    <label>Move To Course/Class <span style="color:red;">*</span></label>
                    <select name="to_class" class="form-control" id="pd_to_class" required onchange="loadSections(this.value)">
                        <option value="">Select Target Class</option>
                        <?php foreach ($classes as $c): ?>
                            <option value="<?php echo $c['class_id']; ?>"><?php echo e($c['class_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-4">
                    <label>Section</label>
                    <select name="to_section" id="pd_to_section" class="form-control">
                        <option value="0">-- Keep Existing --</option>
                    </select>
                </div>
            </div>

            <div style="text-align:right; margin-top:8px;">
                <button type="submit" class="btn btn-success" style="color:#fff;"><i class="fa fa-check"></i> Confirm Move</button>
                <button type="button" class="btn btn-default" onclick="window.parent.closeModal();">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function loadSections(cid) {
    var sel = document.getElementById('pd_to_section');
    sel.innerHTML = '<option value="0">-- Keep Existing --</option>';
    if (!cid) return;
    fetch('<?php echo BASE_URL; ?>ajax_get_sections.php?class_id=' + cid)
        .then(function (r) { return r.json(); })
        .then(function (data) {
            data.forEach(function (s) {
                var o = document.createElement('option');
                o.value = s.section_id;
                o.textContent = s.section_name;
                sel.appendChild(o);
            });
        });
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
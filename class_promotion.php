<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Course/Class Promotion';

$classes = [];
$res = db_query("Select class_id, class_name FROM classes WHERE status=1 ORDER BY class_name");
while ($row = $res->fetch_assoc()) { $classes[] = $row; }

$sessions = [];
$rs = db_query("SELECT DISTINCT session FROM students WHERE session IS NOT NULL AND session <> '' ORDER BY session DESC");
while ($row = $rs->fetch_assoc()) { $sessions[] = $row['session']; }
if (!$sessions) { $sessions = ['2026-2027', '2025-2026', '2024-2025']; }

$sel_session = trim($_GET['session'] ?? '');
$sel_class   = (int) ($_GET['class_id'] ?? 0);
$sel_section = (int) ($_GET['section'] ?? 0);

$selected = [];
if ($sel_class > 0 || $sel_session !== '') {
    $sql = "SELECT s.student_id, s.first_name, s.father_name, s.gr_no, s.session,
                   c.class_name, sec.section_name
            FROM students s
            LEFT JOIN classes c ON s.class_id = c.class_id
            LEFT JOIN sections sec ON s.section_id = sec.section_id
            WHERE s.status = 1";
    $types = '';
    $params = [];
    if ($sel_class > 0)      { $sql .= ' AND s.class_id = ?'; $types .= 'i'; $params[] = $sel_class; }
    if ($sel_session !== '') { $sql .= ' AND s.session = ?';  $types .= 's'; $params[] = $sel_session; }
    if ($sel_section > 0)    { $sql .= ' AND s.section_id = ?'; $types .= 'i'; $params[] = $sel_section; }
    $sql .= ' ORDER BY s.first_name';

    $stmt = db_prepare($sql);
    if ($types !== '') {
        $bindVals = [$types];
        foreach ($params as $k => $v) { $bindVals[] = &$params[$k]; }
        call_user_func_array([$stmt, 'bind_param'], $bindVals);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) { $selected[] = $row; }
}

include __DIR__ . '/includes/header.php';
?>
<style>
.cp-top { display:flex; align-items:center; justify-content:space-between; padding:14px 4px; }
.cp-top h3 { font-size:18px; font-weight:800; color:#111827; margin:0; }
.promo-filter { background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:16px; }
.promo-table-wrap { background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:14px 16px; margin-top:16px; }
.modal-overlay { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,.5); z-index:9999; }
.modal-content { position:fixed; top:50%; left:50%; transform:translate(-50%,-50%); background:#fff; padding:20px; border-radius:12px; box-shadow:0 8px 32px rgba(0,0,0,.3); z-index:10000; width:92%; max-width:1000px; height:82%; max-height:760px; overflow:hidden; display:flex; flex-direction:column; }
.modal-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; padding-bottom:12px; border-bottom:2px solid #FF7C1B; }
.modal-title { font-size:20px; font-weight:800; color:#333; }
.modal-close { background:#f8f9fa; border:2px solid #e9ecef; border-radius:50%; font-size:20px; cursor:pointer; color:#666; width:38px; height:38px; }
.modal-close:hover { background:#FF7C1B; color:#fff; border-color:#FF7C1B; }
.modal-iframe { width:100%; flex:1; border:none; border-radius:8px; }
#selectedCount { background:#FF7C1B; color:#fff; border-radius:999px; padding:2px 9px; font-size:12px; }
</style>

<div class="main-content">
    <div class="container-fluid">
        <div class="cp-top">
            <h3><i class="fa fa-arrow-up"></i> Promot / Demote Students</h3>
        </div>

        <div class="promo-filter">
            <form method="get" action="class_promotion.php" class="row" style="align-items:flex-end;">
                <div class="form-group col-md-3">
                    <label>Session</label>
                    <select name="session" class="form-control">
                        <option value="">From Session</option>
                        <?php foreach ($sessions as $ss): ?>
                            <option value="<?php echo e($ss); ?>" <?php echo $sel_session === $ss ? 'selected' : ''; ?>><?php echo e($ss); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-3">
                    <label>Course/Class</label>
                    <select name="class_id" class="form-control" id="FromClass" onchange="getsec(this.value)">
                        <option value="">All</option>
                        <?php foreach ($classes as $c): ?>
                            <option value="<?php echo $c['class_id']; ?>" <?php echo $sel_class == $c['class_id'] ? 'selected' : ''; ?>><?php echo e($c['class_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-3">
                    <label>Section</label>
                    <select name="section" id="txt_section" class="form-control">
                        <option value="0">All</option>
                        <?php if ($sel_class > 0): $ssq = db_query("SELECT section_id, section_name FROM sections WHERE class_id=$sel_class ORDER BY section_name"); while ($srow = $ssq->fetch_assoc()): ?>
                            <option value="<?php echo $srow['section_id']; ?>" <?php echo $sel_section == $srow['section_id'] ? 'selected' : ''; ?>><?php echo e($srow['section_name']); ?></option>
                        <?php endwhile; endif; ?>
                    </select>
                </div>
                <div class="form-group col-md-2">
                    <button type="reset" class="btn btn-default">Clear</button>
                    <button type="submit" class="btn btn-warning" style="color:#fff;"><i class="fa fa-filter"></i> Filter</button>
                </div>
                <div class="form-group col-md-1" style="text-align:right;">
                    <a href="promotDemotPopup.php" id="promoteBtn" class="btn btn-warning" style="color:#fff; white-space:nowrap;">
                        <i class="fa fa-random"></i> Promote/Demote <span id="selectedCount" class="badge" style="background:#fff; color:#FF7C1B; margin-left:5px;">0</span>
                    </a>
                </div>
            </form>
        </div>

        <div class="promo-table-wrap">
            <table id="listofstudents" class="table table-striped table-bordered" style="width:100%; margin-bottom:0;">
                <thead>
                    <tr>
                        <th style="width:8%;">Sr No</th>
                        <th style="width:24%;">Student Name</th>
                        <th style="width:22%;">Father Name</th>
                        <th style="width:12%;">GR No</th>
                        <th style="width:18%;">Course/Class</th>
                        <th style="width:8%;">Session</th>
                        <th style="width:8%; text-align:center;"><input type="checkbox" id="checkAll"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($selected) === 0): ?>
                        <tr><td colspan="7" style="text-align:center; padding:16px; color:#6B7280;">Please select Session, Class and Section, then click Filter.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($selected as $i => $st): ?>
                        <tr>
                            <td><?php echo $i + 1; ?></td>
                            <td><?php echo e($st['first_name']); ?></td>
                            <td><?php echo e($st['father_name'] ?? ''); ?></td>
                            <td><?php echo e($st['gr_no'] ?? $st['student_id']); ?></td>
                            <td><?php echo e($st['class_name'] ?? ''); ?><?php echo $st['section_name'] ? ' - ' . e($st['section_name']) : ''; ?></td>
                            <td><?php echo e($st['session'] ?? ''); ?></td>
                            <td style="text-align:center;"><input type="checkbox" class="row-check" value="<?php echo $st['student_id']; ?>"></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Popup -->
<div id="promoteModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <div>
                <div class="modal-title">Move Students To New Class</div>
                <small style="color:#666;">Select the session, class, and section to transfer students to their new academic level.</small>
            </div>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <iframe id="promoteIframe" class="modal-iframe" src=""></iframe>
    </div>
</div>

<script src="https://cdn.datatables.net/1.10.13/js/jquery.dataTables.min.js"></script>
<link href="https://cdn.datatables.net/1.10.13/css/jquery.dataTables.min.css" rel="stylesheet">
<script>
function getsec(cid) {
    var sel = document.getElementById('txt_section');
    sel.innerHTML = '<option value="0">All</option>';
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

$(document).ready(function () {
    if ($.fn.DataTable && $.fn.DataTable.isDataTable('#listofstudents')) {
        $('#listofstudents').DataTable().destroy();
    }
    if ($.fn.DataTable) {
        $('#listofstudents').DataTable({
            pageLength: 100,
            ordering: true,
            searching: true,
            lengthChange: false,
            destroy: true
        });
    }

    $('#checkAll').on('click', function () {
        var isChecked = this.checked;
        $('.row-check').prop('checked', isChecked);
        $('#selectedCount').text(isChecked ? $('.row-check').length : 0);
    });
    $(document).on('change', '.row-check', function () {
        var total = $('.row-check').length;
        var checked = $('.row-check:checked').length;
        $('#checkAll').prop('checked', checked > 0 && checked === total);
        $('#selectedCount').text(checked);
    });

    $('#promoteBtn').on('click', function (e) {
        e.preventDefault();
        var selectedStudents = [];
        $('.row-check:checked').each(function () {
            selectedStudents.push($(this).val());
        });
        if (selectedStudents.length === 0) {
            alert('Please select at least one student to promote/demote.');
            return false;
        }
        var studentIds = selectedStudents.join(',');
        var baseUrl = $(this).attr('href');
        var fromSession = $('#FromClass').closest('form').find('select[name="session"]').val() || '';
        var fromClass = $('#FromClass').val() || '';
        var fromSection = $('#txt_section').val() || '';
        var finalUrl = baseUrl + '?student_ids=' + encodeURIComponent(studentIds)
            + '&session=' + encodeURIComponent(fromSession)
            + '&class_id=' + encodeURIComponent(fromClass)
            + '&section=' + encodeURIComponent(fromSection);
        openModal(finalUrl);
    });
});

function openModal(url) {
    $('#promoteModal').show();
    $('#promoteIframe').attr('src', '');
    if (!$('#loadingIndicator').length) {
        $('#promoteIframe').before('<div id="loadingIndicator" style="text-align:center; padding:40px; color:#666;"><i class="fa fa-spinner fa-spin fa-2x"></i><br>Loading...</div>');
    }
    setTimeout(function () {
        $('#promoteIframe').attr('src', url);
        $('#promoteIframe').on('load', function () { $('#loadingIndicator').remove(); });
    }, 100);
}
function closeModal() {
    $('#promoteModal').hide();
    $('#promoteIframe').attr('src', '');
}
$(document).on('click', '.modal-overlay', function (e) { if (e.target === this) closeModal(); });
$(document).on('keydown', function (e) { if (e.key === 'Escape') closeModal(); });
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
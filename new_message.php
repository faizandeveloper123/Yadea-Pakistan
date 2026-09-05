<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/ensure_schema.php';
require_login();

$page_title = 'New Message';

$message = '';
$error = '';

$pre_title   = trim($_GET['title'] ?? '');
$pre_message = trim($_GET['message'] ?? '');

$classes = [];
$res = db_query("Select class_id, class_name FROM classes WHERE status=1 ORDER BY class_name");
while ($row = $res->fetch_assoc()) { $classes[] = $row; }

$templates = [];
$res = db_query("SELECT id, title, body, channel FROM sms_templates WHERE status=1 ORDER BY title");
while ($row = $res->fetch_assoc()) { $templates[] = $row; }

// WhatsApp quota values come from settings (set via whatsapp_setting.php).
$wa_used  = (int) (get_setting('whatsapp_sms_used', 0) ?: 0);
$wa_limit = (int) (get_setting('whatsapp_sms_limit', 1000) ?: 1000);
$wa_remaining = max(0, $wa_limit - $wa_used);

$channelNames = ['0' => 'whatsapp', '1' => 'sim', '2' => 'branded', '3' => 'app'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'SendMessage') {
    $title          = trim($_POST['title'] ?? '');
    $body           = trim($_POST['messages'] ?? '');
    $channel        = (string) ($_POST['channel'] ?? '0');
    $channel_name   = $channelNames[$channel] ?? 'whatsapp';
    $group          = trim($_POST['group'] ?? '');
    $class_id       = (int) ($_POST['class_id'] ?? 0);
    $section_id     = (int) ($_POST['section_id'] ?? 0);
    $numbers        = trim($_POST['numbers'] ?? '');
    $message_type   = ((int) ($_POST['unicode_status'] ?? 0) === 1) ? 'urdu' : 'english';
    $save_template  = (int) ($_POST['save_template'] ?? 0) === 1;
    $template_title = trim($_POST['template_title'] ?? '');

    if ($title === '' || $body === '') {
        $error = 'Title and Message are required.';
    } else {
        $uid = $_SESSION['user_id'];

        // Optional attachment -> uploads/messages/
        $attachment = null;
        if (!empty($_FILES['img_file']['name']) && $_FILES['img_file']['error'] === UPLOAD_ERR_OK) {
            $dir = __DIR__ . '/uploads/messages';
            if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
            $ext = strtolower(pathinfo($_FILES['img_file']['name'], PATHINFO_EXTENSION));
            $attachment = 'm_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
            if (!move_uploaded_file($_FILES['img_file']['tmp_name'], $dir . '/' . $attachment)) { $attachment = null; }
        }

        $st2 = db_prepare("INSERT INTO messages (title, message, channel, recipient_type, recipient_list, status, message_type, attachment, created_by)
                           VALUES (?, ?, ?, ?, ?, 'pending', ?, ?, ?)");
        $recipient_type = $group !== '' ? $group : 'all';
        $st2->bind_param('sssssssi', $title, $body, $channel_name, $recipient_type, $numbers, $message_type, $attachment, $uid);
        $st2->execute();

        // Store a reusable template when requested.
        if ($save_template) {
            $tTitle = $template_title !== '' ? $template_title : $title;
            $st3 = db_prepare("INSERT INTO sms_templates (title, body, channel, status) VALUES (?, ?, ?, 1)");
            $st3->bind_param('sss', $tTitle, $body, $channel_name);
            $st3->execute();
        }

        $message = 'Message saved (status: pending) for ' . $channel_name . ' channel.';
    }
}

include __DIR__ . '/includes/header.php';
?>
<style>
.msg-panel { background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:22px; margin-bottom:16px; }
.msg-panel h4 { margin:0 0 16px; font-weight:800; font-size:15px; color:#111827; }
.msg-panel .form-group label { font-weight:600; color:#374151; }
.wa-banner { display:flex; align-items:center; gap:10px; background:#FFF7E6; border:1px solid #FFE0B2; color:#7A4E00;
             padding:12px 16px; border-radius:12px; margin-bottom:16px; font-size:13.5px; }
.wa-banner .wa-bar { flex:1; height:8px; background:#F3E2C3; border-radius:99px; overflow:hidden; }
.wa-banner .wa-bar i { display:block; height:100%; background:linear-gradient(90deg,#F59E0B,#F97316); border-radius:99px; }
</style>

<div class="main-content">
    <div class="container-fluid">
        <?php if ($message): ?><div class="alert alert-success"><?php echo e($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>

        <div style="display:flex; align-items:center; justify-content:space-between; padding:14px 4px;">
            <h3 style="font-size:18px; font-weight:800; color:#111827; margin:0;"><i class="fa fa-envelope"></i> Send New Message</h3>
            <div>
                <a href="<?php echo BASE_URL; ?>messages_history.php" class="btn btn-primary" style="color:#fff;"><i class="fa fa-clock-o"></i> View Messages</a>
                <a href="<?php echo BASE_URL; ?>view_templates.php" class="btn btn-warning" style="color:#fff;"><i class="fa fa-files-o"></i> View Templates</a>
            </div>
        </div>

        <?php if ($wa_limit > 0): ?>
            <div class="wa-banner">
                <i class="fa fa-whatsapp" style="font-size:20px; color:#25D366;"></i>
                <strong>WhatsApp SMS Used <?php echo $wa_used; ?> / Limit <?php echo $wa_limit; ?></strong>
                <span style="color:#6B7280;">(<?php echo $wa_remaining; ?> remaining)</span>
                <div class="wa-bar"><i style="width:<?php echo min(100, round(($wa_used / max(1, $wa_limit)) * 100)); ?>%;"></i></div>
            </div>
        <?php endif; ?>

        <div class="msg-panel">
            <?php if (count($templates) === 0): ?>
                <div style="background:#F3F4F6; border:1px dashed #D1D5DB; border-radius:10px; padding:14px; margin-bottom:16px; color:#6B7280; font-size:13.5px;">
                    No saved templates yet. Use the "Save as Template" option below to create one.
                </div>
            <?php endif; ?>

            <form method="post" action="new_message.php" enctype="multipart/form-data" onsubmit="return msg_check();">
                <input type="hidden" name="action" value="SendMessage">
                <div class="row">
                    <div class="col-md-6 col-xs-12">
                        <div class="form-group">
                            <label class="required">Title</label>
                            <input type="text" name="title" class="form-control" placeholder="e.g. Eid Holiday Announcement" required>
                        </div>
                    </div>
                    <div class="col-md-6 col-xs-12">
                        <div class="form-group">
                            <label class="required">Channel</label>
                            <select name="channel" id="channel" class="form-control" onchange="hideAttachment(this.value)">
                                <option value="0" selected>WhatsApp</option>
                                <option value="1">Mobile Sim</option>
                                <option value="2">Branded</option>
                                <option value="3">App</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6 col-xs-12">
                        <div class="form-group">
                            <label>Choose Group</label>
                            <select name="group" id="group" class="form-control" onchange="getStudentsContacts(this.value)">
                                <option value="">Select Group</option>
                                <option value="AllStudents">All Students</option>
                                <option value="AllFamilies">All Families</option>
                                <option value="feeDefaulters">Fee Defaulters</option>
                                <option value="Employees">All Employees</option>
                                <option value="Contacts">Contacts Directory</option>
                                <option value="OldStudents">All Old Students</option>
                                <option value="AdmInquiries">Admission Inquiries</option>
                                <option value="OldEmployees">All Old Employees</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6 col-xs-12" style="display:none;" id="classRow">
                        <div class="form-group">
                            <label>Select Course/Class</label>
                            <select name="class_id" id="classes" class="form-control" onchange="getMsgsec(this.value)">
                                <option value="" selected>All Classes</option>
                                <?php foreach ($classes as $c): ?>
                                    <option value="<?php echo $c['class_id']; ?>"><?php echo e($c['class_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6 col-xs-12" style="display:none;" id="secRow">
                        <div class="form-group">
                            <label>Select Section</label>
                            <select name="section_id" id="txt_section" class="form-control" onchange="getClassSectionNumber(this.value)">
                                <option value="">All Sections</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6 col-xs-12">
                        <div class="form-group">
                            <label>Select Template</label>
                            <select name="template" id="template" class="form-control" onchange="applyTemplate(this.value)">
                                <option value="">Choose Template</option>
                                <?php foreach ($templates as $t): ?>
                                    <option value="<?php echo e($t['body']); ?>"><?php echo e($t['title']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6 col-xs-12">
                        <div class="form-group">
                            <label>Message Type</label>
                            <select name="unicode_status" id="unicode_status" class="form-control" onchange="toggleLanguage(this.value)">
                                <option value="0">English</option>
                                <option value="1">Urdu</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6 col-xs-12">
                        <div class="form-group">
                            <label class="required">Cell Numbers</label>
                            <textarea id="numbers" name="numbers" rows="6" class="form-control" oninput="updateManualCount()"
                                      placeholder="923016138728&#10;923016138662&#10;923016138728"></textarea>
                        </div>
                    </div>
                    <div class="col-md-6 col-xs-12">
                        <div class="form-group">
                            <label class="required">Message</label>
                            <textarea id="messageID" name="messages" rows="6" class="form-control" placeholder="Write your message here"></textarea>
                        </div>
                    </div>
                    <div class="col-md-4 col-xs-12">
                        <div class="form-group" id="attachment" style="display:block;">
                            <label>Attach File</label>
                            <input type="file" class="form-control" name="img_file" id="img_file">
                        </div>
                    </div>
                    <div class="col-md-3 col-xs-12">
                        <div class="form-group">
                            <label>Save as Template</label>
                            <div style="display:flex; align-items:center; gap:8px; padding-top:4px;">
                                <input type="checkbox" name="save_template" value="1" id="saveTemplateChk" style="width:18px; height:18px;">
                                <span style="color:#374151; font-size:13px;">Save template</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-5 col-xs-12">
                        <div class="form-group">
                            <label>Template Title (if saving)</label>
                            <input type="text" name="template_title" class="form-control" placeholder="Template name...">
                        </div>
                    </div>
                    <div class="col-md-12" style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; margin-top:6px;">
                        <div>
                            <strong>Contacts: <span id="recipient">0</span></strong>
                            <span style="color:#6B7280; font-size:12.5px; margin-left:6px;" id="recipientHint">(from selected group or manual list)</span>
                        </div>
                        <button type="submit" class="btn btn-success" style="padding:10px 30px;"><i class="fa fa-send"></i> Send Message</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
var smsLeft = <?php echo (int) ($wa_limit - $wa_used); ?>;
var msgCountCache = 1;

function msg_check() {
    var numbers = document.getElementById('numbers').value;
    var arr = numbers.split('\n').map(function (s) { return s.trim(); }).filter(function (s) { return s.length > 0; });
    var total = arr.length;
    if (total === 0) { alert('Please enter at least one cell number.'); return false; }
    var msgcount = msgCountCache;
    var needed = msgcount * total;
    if (needed > smsLeft) {
        alert("Can't Send. Because your SMS Limit has been Exceeded. Remaining SMS: " + smsLeft);
        return false;
    }
    return true;
}

function applyTemplate(str) {
    if (!str) return;
    var ta = document.getElementById('messageID');
    if (str.length <= 1600) {
        ta.value = str;
        msgCountCache = Math.max(1, Math.ceil(str.length / 160));
    } else {
        alert('You have entered more than 1600 characters.');
        ta.value = str.substring(0, 1600);
    }
}

function toggleLanguage(code) {
    var ta = document.getElementById('messageID');
    if (code === '1') {
        ta.style.direction = 'rtl';
        ta.style.fontFamily = '"Noto Nastaliq Urdu", serif';
        ta.placeholder = '\u067E\u06CC\u063A\u0627\u0645 \u06CC\u06C1\u0627\u06BA \u0644\u06A9\u06BE\u06CC\u06BA';
    } else {
        ta.style.direction = 'ltr';
        ta.style.fontFamily = 'inherit';
        ta.placeholder = 'Write your message here';
    }
}

function hideAttachment(ch) {
    document.getElementById('attachment').style.display = (ch === '0' || ch === '3') ? 'block' : 'none';
}

function updateManualCount() {
    var numbers = document.getElementById('numbers').value;
    var arr = numbers.split('\n').map(function (s) { return s.trim(); }).filter(function (s) { return s.length > 0; });
    document.getElementById('recipient').innerText = arr.length;
    document.getElementById('recipientHint').innerText = '(manual list)';
}

function fillNumbers(numbers, count) {
    var ta = document.getElementById('numbers');
    ta.value = numbers.join('\n');
    document.getElementById('recipient').innerText = count;
    document.getElementById('recipientHint').innerText = '(from selected group)';
    msgCountCache = 1;
}

function getMsgsec(classId) {
    var secSel = document.getElementById('txt_section');
    secSel.innerHTML = '<option value="">All Sections</option>';
    if (!classId) {
        document.getElementById('secRow').style.display = 'none';
        loadContacts();
        return;
    }
    document.getElementById('secRow').style.display = 'block';
    fetch('<?php echo BASE_URL; ?>ajax_get_sections.php?class_id=' + encodeURIComponent(classId))
        .then(function (r) { return r.json(); })
        .then(function (rows) {
            rows.forEach(function (s) {
                var o = document.createElement('option');
                o.value = s.section_id;
                o.text = s.section_name;
                secSel.appendChild(o);
            });
            loadContacts();
        });
}

function getClassSectionNumber() {
    loadContacts();
}

function loadContacts() {
    var group = document.getElementById('group').value;
    if (!group) return;
    var cls = document.getElementById('classes').value || '';
    var sec = document.getElementById('txt_section').value || '';
    var url = '<?php echo BASE_URL; ?>ajax_get_sms_contacts.php?group=' + encodeURIComponent(group);
    if (cls) url += '&class_id=' + encodeURIComponent(cls);
    if (sec) url += '&section_id=' + encodeURIComponent(sec);
    fetch(url)
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data && data.count !== undefined) fillNumbers(data.numbers || [], data.count);
        });
}

function getStudentsContacts(group) {
    var showClsSec = (group === 'AllStudents' || group === 'AllFamilies');
    document.getElementById('classRow').style.display = showClsSec ? 'block' : 'none';
    document.getElementById('secRow').style.display = showClsSec ? 'block' : 'none';
    if (showClsSec) return; // class/section selection triggers loadContacts()
    loadContacts();
}

document.addEventListener('DOMContentLoaded', function () {
    hideAttachment(document.getElementById('channel').value);
    updateManualCount();
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
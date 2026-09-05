<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Customized SMS';

$message = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'SaveCustomizedSms') {
    $keys = [
        'sms_fee_reminder', 'sms_fee_overdue', 'sms_fee_defaulters',
        'sms_attendance_absent', 'sms_attendance_late', 'sms_welcome', 'sms_student_leaving', 'sms_exam_results',
    ];
    $saved = 0;
    foreach ($keys as $k) {
        $v = trim($_POST[$k] ?? '');
        $st = db_prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
        $st->bind_param('ss', $k, $v);
        $st->execute();
        $saved++;
    }
    $message = "Customized SMS settings saved successfully! ($saved keys)";
}

include __DIR__ . '/includes/header.php';
?>
<div class="main-content">
    <div class="container-fluid">
        <?php if ($message): ?><div class="alert alert-success"><?php echo e($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>

        <div style="display:flex; align-items:center; justify-content:space-between; padding:14px 4px;">
            <h3 style="font-size:18px; font-weight:800; color:#111827; margin:0;"><i class="fa fa-comments"></i> Customized SMS</h3>
            <a href="<?php echo BASE_URL; ?>new_message.php" class="btn btn-primary" style="color:#fff;"><i class="fa fa-envelope"></i> New Message</a>
        </div>

        <form method="post" action="customized_sms.php" style="max-width:860px; background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:22px;">
            <input type="hidden" name="action" value="SaveCustomizedSms">

            <h4 class="page-sub-title3" style="font-size:15px; font-weight:800; color:#111827; margin:0 0 14px;">Auto SMS Text</h4>
            <p style="color:#6B7280; font-size:13px; margin-top:-6px;">These messages are used automatically by the system wherever an SMS trigger is configured.</p>

            <?php
            $fields = [
                'sms_welcome'           => 'Welcome / New Admission',
                'sms_fee_reminder'      => 'Fee Reminder',
                'sms_fee_overdue'       => 'Fee Overdue',
                'sms_fee_defaulters'    => 'Fee Defaulters',
                'sms_attendance_absent' => 'Attendance (Absent)',
                'sms_attendance_late'   => 'Attendance (Latecomer)',
                'sms_student_leaving'   => 'Student Leaving Certificate',
                'sms_exam_results'      => 'Exam Results',
            ];
            foreach ($fields as $key => $label):
            ?>
            <div class="form-group">
                <label><?php echo $label; ?></label>
                <textarea name="<?php echo $key; ?>" rows="3" class="form-control"><?php echo e(get_setting($key)); ?></textarea>
            </div>
            <hr style="border-color:#EEF2F7; margin:12px 0;">
            <?php endforeach; ?>

            <button type="submit" class="btn btn-success" style="padding:10px 30px;"><i class="fa fa-save"></i> Save Customized SMS</button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
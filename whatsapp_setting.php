<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'WhatsApp / SMS Settings';

$message = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'SaveWhatsappSettings') {
    $keys = [
        'whatsapp_activation', 'whatsapp_api_url', 'whatsapp_api_key', 'whatsapp_sender_id',
        'whatsapp_sms_limit', 'whatsapp_sms_used',
        'sim_sms_provider', 'sim_sms_api_key', 'sim_sms_limit', 'sim_sms_used',
        'easypaisa_mobile', 'easypaisa_username',
    ];
    $saved = 0;
    foreach ($keys as $k) {
        $v = trim($_POST[$k] ?? '');
        $st = db_prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
        $st->bind_param('ss', $k, $v);
        $st->execute();
        $saved++;
    }
    $message = "WhatsApp / SMS settings saved successfully! ($saved keys)";
}

include __DIR__ . '/includes/header.php';
?>
<div class="main-content">
    <div class="container-fluid">
        <?php if ($message): ?><div class="alert alert-success"><?php echo e($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>

        <div style="display:flex; align-items:center; justify-content:space-between; padding:14px 4px;">
            <h3 style="font-size:18px; font-weight:800; color:#111827; margin:0;"><i class="fa fa-cog"></i> WhatsApp / SMS Settings</h3>
            <a href="<?php echo BASE_URL; ?>messages_history.php" class="btn btn-primary" style="color:#fff;"><i class="fa fa-clock-o"></i> Message History</a>
        </div>

        <form method="post" action="whatsapp_setting.php" style="max-width:860px; background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:22px;">
            <input type="hidden" name="action" value="SaveWhatsappSettings">

            <h4 style="font-size:15px; font-weight:800; color:#111827; margin:0 0 14px;"><i class="fa fa-whatsapp" style="color:#25D366;"></i> WhatsApp Gateway</h4>
            <div class="row">
                <div class="form-group col-md-6">
                    <label>Activation</label>
                    <select name="whatsapp_activation" class="form-control">
                        <option value="enabled" <?php echo get_setting('whatsapp_activation', 'enabled') === 'enabled' ? 'selected' : ''; ?>>Enabled</option>
                        <option value="disabled" <?php echo get_setting('whatsapp_activation', 'enabled') === 'disabled' ? 'selected' : ''; ?>>Disabled</option>
                    </select>
                </div>
                <div class="form-group col-md-6">
                    <label>API URL</label>
                    <input type="text" name="whatsapp_api_url" class="form-control" value="<?php echo e(get_setting('whatsapp_api_url')); ?>" placeholder="https://api.example.com/send">
                </div>
                <div class="form-group col-md-6">
                    <label>API Key</label>
                    <input type="text" name="whatsapp_api_key" class="form-control" value="<?php echo e(get_setting('whatsapp_api_key')); ?>">
                </div>
                <div class="form-group col-md-6">
                    <label>Sender ID</label>
                    <input type="text" name="whatsapp_sender_id" class="form-control" value="<?php echo e(get_setting('whatsapp_sender_id')); ?>">
                </div>
                <div class="form-group col-md-6">
                    <label>WhatsApp SMS Limit (monthly)</label>
                    <input type="number" name="whatsapp_sms_limit" class="form-control" value="<?php echo e(get_setting('whatsapp_sms_limit', '1000')); ?>">
                </div>
                <div class="form-group col-md-6">
                    <label>WhatsApp SMS Used</label>
                    <input type="number" name="whatsapp_sms_used" class="form-control" value="<?php echo e(get_setting('whatsapp_sms_used', '0')); ?>">
                </div>
            </div>

            <hr style="border-color:#EEF2F7; margin:18px 0;">
            <h4 style="font-size:15px; font-weight:800; color:#111827; margin:0 0 14px;"><i class="fa fa-mobile" style="color:#007bff;"></i> Mobile Sim SMS</h4>
            <div class="row">
                <div class="form-group col-md-6">
                    <label>Provider</label>
                    <input type="text" name="sim_sms_provider" class="form-control" value="<?php echo e(get_setting('sim_sms_provider')); ?>" placeholder="e.g. SMSKnow / Connect">
                </div>
                <div class="form-group col-md-6">
                    <label>API Key</label>
                    <input type="text" name="sim_sms_api_key" class="form-control" value="<?php echo e(get_setting('sim_sms_api_key')); ?>">
                </div>
                <div class="form-group col-md-6">
                    <label>Sim SMS Limit (monthly)</label>
                    <input type="number" name="sim_sms_limit" class="form-control" value="<?php echo e(get_setting('sim_sms_limit', '5000')); ?>">
                </div>
                <div class="form-group col-md-6">
                    <label>Sim SMS Used</label>
                    <input type="number" name="sim_sms_used" class="form-control" value="<?php echo e(get_setting('sim_sms_used', '0')); ?>">
                </div>
            </div>

            <hr style="border-color:#EEF2F7; margin:18px 0;">
            <h4 style="font-size:15px; font-weight:800; color:#111827; margin:0 0 14px;"><i class="fa fa-money" style="color:#10B981;"></i> Easypaisa</h4>
            <div class="row">
                <div class="form-group col-md-6">
                    <label>Mobile Number</label>
                    <input type="text" name="easypaisa_mobile" class="form-control" value="<?php echo e(get_setting('easypaisa_mobile')); ?>">
                </div>
                <div class="form-group col-md-6">
                    <label>Username</label>
                    <input type="text" name="easypaisa_username" class="form-control" value="<?php echo e(get_setting('easypaisa_username')); ?>">
                </div>
            </div>

            <button type="submit" class="btn btn-success" style="padding:10px 30px;"><i class="fa fa-save"></i> Save Settings</button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
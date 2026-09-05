<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Update Password';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'ChangePassword') {
    $old = $_POST['old_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    $user = db_query("SELECT * FROM users WHERE user_id=" . (int) $_SESSION['user_id'])->fetch_assoc();
    if (hash('sha256', $old) !== $user['password']) {
        $error = 'Current password is incorrect.';
    } elseif (strlen($new) < 8) {
        $error = 'New password must be at least 8 characters.';
    } elseif (!preg_match('/[A-Z]/', $new) || !preg_match('/[a-z]/', $new) || !preg_match('/[0-9]/', $new) || !preg_match('/[^A-Za-z0-9]/', $new)) {
        $error = 'New password must contain uppercase, lowercase, a number and a special character.';
    } elseif ($new !== $confirm) {
        $error = 'New password and confirmation do not match.';
    } else {
        $hp = hash('sha256', $new);
        $st2 = db_prepare("UPDATE users SET password=? WHERE user_id=?");
        $st2->bind_param('si', $hp, $_SESSION['user_id']);
        $st2->execute();
        $message = 'Password changed successfully!';
    }
}

include __DIR__ . '/includes/header.php';
?>
<div class="main-content">
    <div class="container-fluid">
        <?php if ($message): ?><div class="alert alert-success"><?php echo e($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>

        <div style="display:flex; align-items:center; justify-content:space-between; padding:14px 4px;">
            <h3 style="font-size:18px; font-weight:800; color:#111827; margin:0;"><i class="fa fa-key"></i> Update Password</h3>
            <a href="<?php echo BASE_URL; ?>update_profile.php" class="btn btn-primary" style="color:#fff;"><i class="fa fa-user"></i> Update Profile</a>
        </div>

        <form method="post" action="update_pswd.php" style="max-width:520px; background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:20px;">
            <input type="hidden" name="action" value="ChangePassword">
            <div class="form-group">
                <label class="required">Current Password</label>
                <input type="password" id="old_password" name="old_password" class="form-control" autocomplete="current-password" required>
            </div>
            <div class="form-group">
                <label class="required">New Password</label>
                <input type="password" id="new_password" name="new_password" class="form-control" autocomplete="new-password" required>
            </div>
            <div class="form-group">
                <label class="required">Confirm New Password</label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-control" autocomplete="new-password" required>
                <div id="pswd_errors" style="display:none; margin-top:8px; font-size:13px; line-height:1.6;">
                    <div data-req="min" style="color:#E74C3C;"><i class="fa fa-times-circle"></i> At least 8 characters</div>
                    <div data-req="upper" style="color:#E74C3C;"><i class="fa fa-times-circle"></i> At least one uppercase letter (A-Z)</div>
                    <div data-req="lower" style="color:#E74C3C;"><i class="fa fa-times-circle"></i> At least one lowercase letter (a-z)</div>
                    <div data-req="number" style="color:#E74C3C;"><i class="fa fa-times-circle"></i> At least one number (0-9)</div>
                    <div data-req="special" style="color:#E74C3C;"><i class="fa fa-times-circle"></i> At least one special character</div>
                    <div data-req="match" style="color:#E74C3C;"><i class="fa fa-times-circle"></i> Passwords must match</div>
                </div>
            </div>
            <button type="submit" id="submit_btn" class="btn btn-success" style="padding:10px 30px;"><i class="fa fa-key"></i> Change Password</button>
        </form>
    </div>
</div>
<style>
#pswd_errors div.pass { color: #27AE60 !important; }
#pswd_errors div.pass i::before { content: "\f00c"; }
</style>
<script type="text/javascript">
(function () {
    "use strict";
    var newP = document.getElementById('new_password');
    var confP = document.getElementById('confirm_password');
    var box = document.getElementById('pswd_errors');
    var btn = document.getElementById('submit_btn');
    var rules = {
        min: function (v) { return v.length >= 8; },
        upper: function (v) { return /[A-Z]/.test(v); },
        lower: function (v) { return /[a-z]/.test(v); },
        number: function (v) { return /[0-9]/.test(v); },
        special: function (v) { return /[^A-Za-z0-9]/.test(v); },
        match: function () { return newP.value !== '' && newP.value === confP.value; }
    };

    function validate() {
        var v = newP.value;
        var allGood = true;
        Object.keys(rules).forEach(function (key) {
            var ok = rules[key](v);
            var el = box.querySelector('[data-req="' + key + '"]');
            if (el) { el.classList.toggle('pass', ok); }
            if (!ok) { allGood = false; }
        });
        box.style.display = newP.value.length > 0 ? 'block' : 'none';
        btn.disabled = !allGood;
    }

    newP.addEventListener('input', validate);
    confP.addEventListener('input', validate);
    validate();
})();
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Student Birthdays';

$month = (int) ($_GET['month'] ?? (int) date('m'));

$students = [];
$stmt = db_prepare("SELECT s.*, c.class_name, sec.section_name FROM students s
                    LEFT JOIN classes c ON s.class_id = c.class_id
                    LEFT JOIN sections sec ON s.section_id = sec.section_id
                    WHERE MONTH(s.dob) = ? AND s.status = 1
                    ORDER BY DAY(s.dob), s.first_name");
$stmt->bind_param('i', $month);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) { $students[] = $row; }

include __DIR__ . '/includes/header.php';
?>
<style>
.bday-month-nav { display:flex; gap:6px; flex-wrap:wrap; margin-bottom:16px; }
.bday-month-nav .mbtn { padding:8px 14px; border-radius:999px; border:1px solid #E5E7EB; background:#fff; font-size:13px; font-weight:600; color:#374151; text-decoration:none; }
.bday-month-nav .mbtn.active { background:#FF7A1B; color:#fff; border-color:#FF7A1B; }
</style>

<div class="main-content">
    <div class="container-fluid">
        <div style="display:flex; align-items:center; justify-content:space-between; padding:14px 4px;">
            <h3 style="font-size:18px; font-weight:800; color:#111827; margin:0;"><i class="fa fa-birthday-cake"></i> Student Birthdays <span style="font-size:14px; color:#6B7280;">(<?php echo count($students); ?> records)</span></h3>
        </div>

        <div class="bday-month-nav">
            <?php for ($m = 1; $m <= 12; $m++): ?>
                <a href="student_birthday.php?month=<?php echo $m; ?>" class="mbtn <?php echo $month == $m ? 'active' : ''; ?>"><?php echo date('M', mktime(0,0,0,$m,1)); ?></a>
            <?php endfor; ?>
        </div>

        <div style="overflow-x:auto; background:#fff; border:1px solid #E5E7EB; border-radius:14px;">
            <table class="table table-striped table-bordered" style="width:100%; background:#fff; margin-bottom:0;">
                <thead>
                    <tr>
                        <th>Day</th>
                        <th>Student Name</th>
                        <th>Father Name</th>
                        <th>Course/Class / Section</th>
                        <th>Date of Birth</th>
                        <th>Phone</th>
                        <th>SMS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($students) === 0): ?>
                        <tr><td colspan="7" style="text-align:center; color:#6B7280; padding:30px;">No birthdays in this month.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($students as $st): ?>
                        <tr>
                            <td><span style="background:#FFE0EC; color:#EC4899; padding:3px 10px; border-radius:999px; font-weight:700; font-size:12px;"><?php echo date('d', strtotime($st['dob'])); ?></span></td>
                            <td><strong><?php echo e($st['first_name']); ?></strong></td>
                            <td><?php echo e($st['father_name'] ?? $st['last_name']); ?></td>
                            <td><?php echo e($st['class_name'] ?? '-'); ?> / <?php echo e($st['section_name'] ?? '-'); ?></td>
                            <td><?php echo $st['dob'] ? date('d M Y', strtotime($st['dob'])) : '-'; ?></td>
                            <td><?php echo e($st['phone'] ?? '-'); ?></td>
                            <td>
                                <a href="<?php echo BASE_URL; ?>new_message.php?title=<?php echo urlencode('Happy Birthday ' . $st['first_name']); ?>&message=<?php echo urlencode('Dear ' . $st['first_name'] . ', wishing you a very happy birthday!'); ?>" class="btn btn-info btn-xs" target="_blank"><i class="fa fa-envelope"></i> SMS</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
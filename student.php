<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Student Profile';

$student_id = (int) ($_GET['student'] ?? $_GET['student_id'] ?? 0);

$stu = null;
if ($student_id > 0) {
    $st1 = db_prepare("SELECT s.*, c.class_name, sec.section_name, l.locality_name
                       FROM students s
                       LEFT JOIN classes c ON c.class_id = s.class_id
                       LEFT JOIN sections sec ON sec.section_id = s.section_id
                       LEFT JOIN localities l ON l.locality_id = s.locality_id
                       WHERE s.student_id = ?");
    $st1->bind_param('i', $student_id);
    $st1->execute();
    $stu = $st1->get_result()->fetch_assoc();
}

if (!$stu) {
    include __DIR__ . '/includes/header.php';
    ?>
    <div class="main-content">
        <div class="container-fluid">
            <div class="container mt-4 page-card" style="width:100%; text-align:center; padding:60px 20px;">
                <i class="fa fa-user-times" style="font-size:52px; color:#D5DBDB;"></i>
                <h3 style="font-size:18px; color:#111827; margin:14px 0 6px;">Student not found</h3>
                <p style="color:#8A99A8; font-size:13.5px;">The student you are looking for does not exist or may have been removed.</p>
                <a href="<?php echo BASE_URL; ?>manage_students.php" class="btn btn-default" style="margin-top:14px;"><i class="fa fa-arrow-left"></i> Back to Students</a>
            </div>
        </div>
    </div>
    <?php
    include __DIR__ . '/includes/footer.php';
    exit;
}

// Uploaded documents
$docs = [];
$dd = db_prepare('SELECT doc_id, doc_type, file_path, uploaded_at FROM student_documents WHERE student_id = ? ORDER BY doc_id');
$dd->bind_param('i', $student_id);
$dd->execute();
$r = $dd->get_result();
if ($r) { while ($row = $r->fetch_assoc()) { $docs[] = $row; } }

// Student fee plan
$feePlan = [];
$fd = db_prepare('SELECT head_name, amount, discount FROM student_fee_plan WHERE student_id = ? ORDER BY id');
$fd->bind_param('i', $student_id);
$fd->execute();
$r2 = $fd->get_result();
$feeTotal = 0.0;
if ($r2) { while ($row = $r2->fetch_assoc()) { $feePlan[] = $row; $feeTotal += (float)$row['amount'] - (float)$row['discount']; } }

// Global active fee heads (for reference when no per-student plan exists)
$globalHeads = [];
$gh = db_query('SELECT head_name, amount FROM fee_heads WHERE status = 1 ORDER BY head_id');
if ($gh) { while ($row = $gh->fetch_assoc()) { $globalHeads[] = $row; } }

$photoUrl = (!empty($stu['photo'])) ? BASE_URL . 'uploads/students/' . $stu['photo'] : null;
$docDirUrl = BASE_URL . 'uploads/students/documents/';

include __DIR__ . '/includes/header.php';
?>
<style>
.profile-head { background: linear-gradient(120deg, #FF7A1B, #ff9838); border-radius: 14px; padding: 22px 24px; color: #fff; display: flex; align-items: center; gap: 18px; flex-wrap: wrap; }
.profile-avatar { width: 84px; height: 84px; border-radius: 50%; overflow: hidden; background: #fff; border: 3px solid rgba(255,255,255,.7); flex: 0 0 auto; display:flex; align-items:center; justify-content:center; }
.profile-avatar img { width: 100%; height: 100%; object-fit: cover; }
.profile-avatar i { font-size: 34px; color: #C0CDD8; }
.profile-title h2 { margin: 0; font-size: 20px; font-weight: 800; }
.profile-title .sub { opacity: .92; font-size: 13px; margin-top: 4px; }
.info-block { background: #fff; border: 1px solid #E6E9ED; border-radius: 14px; padding: 18px 20px; margin-bottom: 16px; }
.info-block h4 { margin: 0 0 14px; font-size: 14px; font-weight: 800; color: #2A3F54; border-bottom: 1px solid #EEF1F4; padding-bottom: 10px; }
.ikv { display: flex; flex-wrap: wrap; gap: 14px 24px; }
.ikv .item { min-width: 150px; }
.ikv .k { font-size: 11px; text-transform: uppercase; letter-spacing: .3px; color: #8A99A8; font-weight: 700; }
.ikv .v { font-size: 13.5px; color: #1F2937; font-weight: 500; margin-top: 2px; word-break: break-word; }
.doc-thumb { width: 100%; height: 92px; border-radius: 8px; background: #F4F6F8; display: flex; align-items: center; justify-content: center; overflow: hidden; }
.doc-thumb img { width: 100%; height: 100%; object-fit: cover; }
.doc-thumb i { font-size: 26px; color: #9AA7B4; }
.doc-card { border: 1px solid #E5E7EB; border-radius: 12px; padding: 10px; text-align: center; background: #fff; }
.doc-card .t { font-size: 12.5px; font-weight: 600; color: #2A3F54; margin-top: 8px; line-height: 1.3; }
.badge-status { padding: 4px 10px; border-radius: 999px; font-size: 11.5px; font-weight: 700; }
.badge-status.active { background: #E6F7EE; color: #1E9E5A; }
.badge-status.inactive { background: #FDEBEA; color: #D64545; }
.amount-chip { font-family: Consolas, monospace; font-weight: 700; color: #2A3F54; }
.actions-bar { display: flex; gap: 8px; flex-wrap: wrap; }
.actions-bar .btn { font-size: 12.5px; }
</style>

<div class="main-content">
    <div class="container-fluid">
        <div class="container mt-4 page-card" style="width:100%;">

            <div style="padding:6px 4px 12px; font-size:12.5px; color:#6B7280;">
                <a href="dashboard.php" style="color:#377dff;">Dashboard</a> <i class="fa fa-angle-double-right"></i>
                <a href="manage_students.php" style="color:#377dff;">Students</a> <i class="fa fa-angle-double-right"></i>
                Student Profile
            </div>

            <!-- Header -->
            <div class="profile-head">
                <div class="profile-avatar">
                    <?php if ($photoUrl): ?>
                        <img src="<?php echo e($photoUrl); ?>" alt="">
                    <?php else: ?>
                        <i class="fa fa-user"></i>
                    <?php endif; ?>
                </div>
                <div class="profile-title">
                    <h2><?php echo e($stu['first_name'] . ' ' . $stu['last_name']); ?></h2>
                    <div class="sub">
                        GR No: <strong><?php echo e($stu['gr_no'] ?? '—'); ?></strong>
                        &nbsp;•&nbsp; <?php echo e($stu['class_name'] ?? '—'); ?> <?php echo e($stu['section_name'] ?? ''); ?>
                        &nbsp;•&nbsp; <span class="badge-status <?php echo (int)$stu['status'] === 1 ? 'active' : 'inactive'; ?>"><?php echo (int)$stu['status'] === 1 ? 'Active' : 'Inactive'; ?></span>
                    </div>
                </div>
                <div style="margin-left:auto;" class="actions-bar">
                    <a href="<?php echo BASE_URL; ?>fee_plan.php?student_id=<?php echo (int)$stu['student_id']; ?>" class="btn btn-warning btn-sm"><i class="fa fa-money"></i> Fee Plan</a>
                    <a href="<?php echo BASE_URL; ?>student_fee_payments_view.php?student_id=<?php echo (int)$stu['student_id']; ?>" class="btn btn-default btn-sm"><i class="fa fa-credit-card"></i> Payments</a>
                    <a href="<?php echo BASE_URL; ?>manage_students.php" class="btn btn-default btn-sm"><i class="fa fa-arrow-left"></i> Back</a>
                </div>
            </div>

            <div class="row" style="margin-top:18px;">
                <div class="col-md-8" style="padding-left:0;">
                    <!-- Basic Information -->
                    <div class="info-block">
                        <h4><i class="fa fa-id-card"></i> Basic Information</h4>
                        <div class="ikv">
                            <div class="item"><div class="k">GR No</div><div class="v"><?php echo e($stu['gr_no'] ?? '—'); ?></div></div>
                            <div class="item"><div class="k">Roll No</div><div class="v"><?php echo e($stu['roll_no'] ?? '—'); ?></div></div>
                            <div class="item"><div class="k">Date Of Birth</div><div class="v"><?php echo $stu['dob'] ? e(date('d M Y', strtotime($stu['dob']))) : '—'; ?></div></div>
                            <div class="item"><div class="k">Gender</div><div class="v"><?php echo e(ucfirst($stu['gender'] ?? '—')); ?></div></div>
                            <div class="item"><div class="k">Religion</div><div class="v"><?php echo e($stu['religion'] ?? '—'); ?></div></div>
                            <div class="item"><div class="k">Session</div><div class="v"><?php echo e($stu['session'] ?? '—'); ?></div></div>
                            <div class="item"><div class="k">Admission Date</div><div class="v"><?php echo $stu['admission_date'] ? e(date('d M Y', strtotime($stu['admission_date']))) : '—'; ?></div></div>
                            <div class="item"><div class="k">Board/Council</div><div class="v"><?php echo e($stu['board_council'] ?? '—'); ?></div></div>
                            <div class="item"><div class="k">Group/Shift</div><div class="v"><?php echo e($stu['group_shift'] ?? '—'); ?></div></div>
                            <div class="item"><div class="k">Admission Source</div><div class="v"><?php echo e($stu['admission_source'] ?? '—'); ?></div></div>
                            <div class="item"><div class="k">Caste</div><div class="v"><?php echo e($stu['caste'] ?? '—'); ?></div></div>
                            <div class="item"><div class="k">B-Form No</div><div class="v"><?php echo e($stu['form_b_no'] ?? '—'); ?></div></div>
                            <div class="item"><div class="k">Family Code</div><div class="v"><?php echo e($stu['family_code'] ?? '—'); ?></div></div>
                            <div class="item"><div class="k">Place of Birth</div><div class="v"><?php echo e($stu['place_of_birth'] ?? '—'); ?></div></div>
                            <div class="item"><div class="k">Locality</div><div class="v"><?php echo e($stu['locality_name'] ?? '—'); ?></div></div>
                        </div>
                    </div>

                    <!-- Academic Information -->
                    <div class="info-block">
                        <h4><i class="fa fa-graduation-cap"></i> Academic Information</h4>
                        <div class="ikv">
                            <div class="item"><div class="k">Previous Course/Class</div><div class="v"><?php echo e($stu['old_class'] ?? '—'); ?></div></div>
                            <div class="item"><div class="k">Previous School</div><div class="v"><?php echo e($stu['old_school'] ?? '—'); ?></div></div>
                            <div class="item"><div class="k">Previous Total Marks</div><div class="v"><?php echo e($stu['old_tmarks'] ?? '—'); ?></div></div>
                            <div class="item"><div class="k">Previous Obtained Marks</div><div class="v"><?php echo e($stu['old_obtmarks'] ?? '—'); ?></div></div>
                            <div class="item"><div class="k">Admission Form No</div><div class="v"><?php echo e($stu['admission_form_no'] ?? '—'); ?></div></div>
                            <div class="item"><div class="k">School Leaving Reason</div><div class="v"><?php echo e($stu['school_leaving_reason'] ?? '—'); ?></div></div>
                        </div>
                    </div>

                    <!-- Parent Details -->
                    <div class="info-block">
                        <h4><i class="fa fa-user-friends"></i> Parent / Guardian Details</h4>
                        <div class="row" style="margin:0;">
                            <div class="col-md-6" style="padding-left:0;">
                                <strong style="font-size:12px; color:#8A99A8;">FATHER</strong>
                                <div class="ikv" style="margin-top:6px;">
                                    <div class="item" style="min-width:100%;"><div class="k">Name</div><div class="v"><?php echo e($stu['father_name'] ?? '—'); ?></div></div>
                                    <div class="item" style="min-width:100%;"><div class="k">CNIC</div><div class="v"><?php echo e($stu['father_cnic'] ?? '—'); ?></div></div>
                                    <div class="item" style="min-width:100%;"><div class="k">Cell No</div><div class="v"><?php echo e($stu['father_cellno'] ?? '—'); ?></div></div>
                                    <div class="item" style="min-width:100%;"><div class="k">Qualification</div><div class="v"><?php echo e($stu['father_qualification'] ?? '—'); ?></div></div>
                                    <div class="item" style="min-width:100%;"><div class="k">Occupation</div><div class="v"><?php echo e($stu['father_occupation'] ?? '—'); ?></div></div>
                                    <div class="item" style="min-width:100%;"><div class="k">Monthly Income</div><div class="v"><?php echo e($stu['father_income'] ?? '—'); ?></div></div>
                                    <div class="item" style="min-width:100%;"><div class="k">Business Address</div><div class="v"><?php echo e($stu['father_business_address'] ?? '—'); ?></div></div>
                                </div>
                            </div>
                            <div class="col-md-6" style="padding-right:0;">
                                <strong style="font-size:12px; color:#8A99A8;">MOTHER</strong>
                                <div class="ikv" style="margin-top:6px;">
                                    <div class="item" style="min-width:100%;"><div class="k">Name</div><div class="v"><?php echo e($stu['mother_name'] ?? '—'); ?></div></div>
                                    <div class="item" style="min-width:100%;"><div class="k">CNIC</div><div class="v"><?php echo e($stu['mother_cnic'] ?? '—'); ?></div></div>
                                    <div class="item" style="min-width:100%;"><div class="k">Cell No</div><div class="v"><?php echo e($stu['mother_cell'] ?? '—'); ?></div></div>
                                    <div class="item" style="min-width:100%;"><div class="k">Qualification</div><div class="v"><?php echo e($stu['mother_qualification'] ?? '—'); ?></div></div>
                                    <div class="item" style="min-width:100%;"><div class="k">Activity</div><div class="v"><?php echo e($stu['mother_activity'] ?? '—'); ?></div></div>
                                    <div class="item" style="min-width:100%;"><div class="k">Designation</div><div class="v"><?php echo e($stu['mother_designation'] ?? '—'); ?></div></div>
                                </div>
                                <strong style="font-size:12px; color:#8A99A8; display:block; margin-top:14px;">GUARDIAN</strong>
                                <div class="ikv" style="margin-top:6px;">
                                    <div class="item" style="min-width:100%;"><div class="k">Name</div><div class="v"><?php echo e($stu['guardian_name'] ?? '—'); ?></div></div>
                                    <div class="item" style="min-width:100%;"><div class="k">CNIC</div><div class="v"><?php echo e($stu['guardian_cnic'] ?? '—'); ?></div></div>
                                    <div class="item" style="min-width:100%;"><div class="k">Cell No</div><div class="v"><?php echo e($stu['guardian_cellno'] ?? '—'); ?></div></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Contact Information -->
                    <div class="info-block">
                        <h4><i class="fa fa-phone"></i> Contact Information</h4>
                        <div class="ikv">
                            <div class="item"><div class="k">Email</div><div class="v"><?php echo e($stu['email'] ?? '—'); ?></div></div>
                            <div class="item"><div class="k">Phone / Cell</div><div class="v"><?php echo e($stu['phone'] ?? '—'); ?></div></div>
                            <div class="item"><div class="k">WhatsApp</div><div class="v"><?php echo e($stu['whatsapp_number'] ?? '—'); ?></div></div>
                            <div class="item"><div class="k">Home No</div><div class="v"><?php echo e($stu['home_number'] ?? '—'); ?></div></div>
                            <div class="item"><div class="k">City</div><div class="v"><?php echo e($stu['city'] ?? '—'); ?></div></div>
                            <div class="item"><div class="k">State / Province</div><div class="v"><?php echo e($stu['state'] ?? '—'); ?></div></div>
                            <div class="item"><div class="k" style="min-width:180px;">Address</div><div class="v"><?php echo e($stu['address'] ?? '—'); ?></div></div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4" style="padding-right:0;">
                    <!-- Fee Plan -->
                    <div class="info-block">
                        <h4><i class="fa fa-money"></i> Fee Plan</h4>
                        <?php if (count($feePlan) > 0): ?>
                            <table class="table" style="margin:0; font-size:13px;">
                                <tbody>
                                    <?php foreach ($feePlan as $f): ?>
                                        <tr>
                                            <td style="padding:6px 4px; color:#4B5563;"><?php echo e($f['head_name']); ?></td>
                                            <td style="padding:6px 4px; text-align:right;" class="amount-chip"><?php echo number_format((float)$f['amount'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr style="border-top:2px solid #EEF1F4;">
                                        <td style="padding:8px 4px; font-weight:800; color:#111827;">Total</td>
                                        <td style="padding:8px 4px; text-align:right; font-weight:800; color:#FF7A1B;"><?php echo number_format($feeTotal, 2); ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p style="font-size:13px; color:#8A99A8; margin:0 0 10px;">No per-student fee plan set yet.</p>
                            <?php if (count($globalHeads) > 0): ?>
                                <div style="font-size:11px; text-transform:uppercase; letter-spacing:.3px; color:#8A99A8; font-weight:700; margin-bottom:6px;">Default Fee Heads</div>
                                <table class="table" style="margin:0; font-size:13px;">
                                    <tbody>
                                        <?php foreach ($globalHeads as $g): ?>
                                            <tr><td style="padding:5px 4px; color:#4B5563;"><?php echo e($g['head_name']); ?></td></tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <a href="<?php echo BASE_URL; ?>fee_plan.php?student_id=<?php echo (int)$stu['student_id']; ?>" class="btn btn-warning btn-sm" style="width:100%; margin-top:8px;"><i class="fa fa-pencil"></i> Set Fee Plan</a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Documents -->
                    <div class="info-block">
                        <h4><i class="fa fa-file-text"></i> Documents</h4>
                        <?php if (count($docs) > 0): ?>
                            <div class="row" style="margin:0;">
                                <?php foreach ($docs as $d): ?>
                                    <?php $isPdf = $d['file_path'] && strtolower(pathinfo($d['file_path'], PATHINFO_EXTENSION)) === 'pdf'; ?>
                                    <div class="col-xs-6" style="padding:4px;">
                                        <div class="doc-card">
                                            <div class="doc-thumb">
                                                <?php if ($isPdf): ?>
                                                    <i class="fa fa-file-pdf-o"></i>
                                                <?php elseif ($d['file_path']): ?>
                                                    <img src="<?php echo e($docDirUrl . $d['file_path']); ?>" alt="" onerror="this.style.display='none';">
                                                <?php else: ?>
                                                    <i class="fa fa-file-alt"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div class="t"><?php echo e($d['doc_type'] ?? 'Document'); ?> <?php if ($d['file_path']): ?><a href="<?php echo e($docDirUrl . $d['file_path']); ?>" target="_blank" style="color:#FF7A1B;"><i class="fa fa-external-link"></i></a><?php endif; ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p style="font-size:13px; color:#8A99A8; margin:0;">No documents uploaded.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
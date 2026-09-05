<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Assign Student Routes';

function asr_col_exists($c) {
    $r = db_query("SHOW COLUMNS FROM students LIKE '" . str_replace("'", '', $c) . "'");
    return $r && $r->num_rows > 0;
}
try {
    if (!asr_col_exists('route_id')) { db_query("ALTER TABLE students ADD COLUMN route_id INT NULL AFTER locality_id"); }
} catch (Throwable $ex) {}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'UpdateStudentRoute') {
    $student_id = (int) ($_POST['student_id'] ?? 0);
    $route_id = (int) ($_POST['route_id'] ?? 0);
    if ($student_id > 0) {
        if ($route_id > 0) {
            $st2 = db_prepare("UPDATE students SET route_id=? WHERE student_id=?");
            $st2->bind_param('ii', $route_id, $student_id);
        } else {
            $st2 = db_prepare("UPDATE students SET route_id=NULL WHERE student_id=?");
            $st2->bind_param('i', $student_id);
        }
        $st2->execute();
        $message = $route_id > 0 ? 'Route assigned to student!' : 'Route removed from student.';
    }
}

$routes = [];
$res = db_query("SELECT r.*, COALESCE(v.vehicle_no, '') AS vehicle_no, COALESCE(v.vehicle_name, '') AS vehicle_model
    FROM routes r
    LEFT JOIN vehicle_route vr ON vr.route_id = r.route_id
    LEFT JOIN vehicles v ON v.vehicle_id = vr.vehicle_id
    ORDER BY r.route_name");
$routeVehicleMap = [];
while ($row = $res->fetch_assoc()) {
    $routes[] = $row;
    if ($row['vehicle_no'] !== '') {
        $routeVehicleMap[$row['route_id']] = $row['vehicle_no'] . (($row['vehicle_model'] !== '') ? ' (' . $row['vehicle_model'] . ')' : '');
    } else {
        $routeVehicleMap[$row['route_id']] = '';
    }
}

$students = [];
$res = db_query("SELECT s.student_id, s.first_name, s.last_name, s.father_name, s.gr_no, s.form_b_no, s.roll_no, s.phone, s.route_id,
        COALESCE(c.class_name, s.old_class) AS class_label
    FROM students s
    LEFT JOIN classes c ON c.class_id = s.class_id
    WHERE s.status = 1
    ORDER BY s.first_name ASC");
while ($row = $res->fetch_assoc()) {
    $row['route_id'] = $row['route_id'] !== null ? (int) $row['route_id'] : 0;
    $students[] = $row;
}

$assignedCount = 0;
foreach ($students as $s) { if ($s['route_id'] > 0) { $assignedCount++; } }

include __DIR__ . '/includes/header.php';
?>
<style>
.form-lbl { font-size: 13px; font-weight: 600; color: #4a5568; margin-bottom: 4px; }
.filter-row { display: flex; align-items: flex-end; gap: 10px; flex-wrap: wrap; margin-bottom: 14px; }
.filter-item { display: flex; flex-direction: column; gap: 4px; flex: 1 1 160px; min-width: 150px; }
.filter-item label { font-size: 10.5px; font-weight: 700; color: #a0aec0; text-transform: uppercase; letter-spacing: .4px; margin-bottom: 0; }
.filter-row .form-control { height: 34px; padding: 4px 10px; font-size: 12.5px; }
.search-input-wrap { position: relative; }
.search-input-wrap i { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #a0aec0; font-size: 12px; pointer-events: none; }
.search-input-wrap input { padding-left: 28px; }
.veh-label { font-size: 11px; color: #48bb78; font-weight: 600; margin-left: 4px; }
table thead th { background-color: #4a5568 !important; color: #fff !important; border: none !important; font-size: 11.5px; white-space: nowrap; }
table tbody td { font-size: 12.5px; vertical-align: middle; }
</style>

<div class="main-content">
    <div class="container-fluid">
        <?php if ($message): ?><div class="alert alert-success"><?php echo e($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>

        <div style="display:flex; align-items:center; justify-content:space-between; padding:14px 4px; flex-wrap:wrap; gap:10px;">
            <h3 style="font-size:18px; font-weight:800; color:#111827; margin:0;"><i class="fa fa-users"></i> Assign Student Routes <span style="font-size:14px; color:#6B7280;">(<?php echo count($students); ?> students, <?php echo $assignedCount; ?> assigned)</span></h3>
            <div style="display:flex; gap:8px;">
                <a href="<?php echo BASE_URL; ?>vehicle_route.php" class="btn btn-primary" style="color:#fff;"><i class="fa fa-road"></i> Vehicle Routes</a>
                <a href="<?php echo BASE_URL; ?>route.php" class="btn btn-info" style="color:#fff;"><i class="fa fa-signal"></i> Routes</a>
            </div>
        </div>

        <?php if (count($routes) === 0): ?>
            <div class="alert alert-warning">No routes available yet. <a href="<?php echo BASE_URL; ?>route.php" style="font-weight:700; color:#2b6cb0;">Add routes first</a>, then assign them to students here.</div>
        <?php endif; ?>

        <div style="background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:18px;">
            <div class="filter-row">
                <div class="filter-item" style="flex:1.8 1 240px;">
                    <label>Search Student</label>
                    <div class="search-input-wrap">
                        <i class="fa fa-search"></i>
                        <input type="text" id="studentSearch" class="form-control" placeholder="Type GR No., Form B No. or Student name...">
                    </div>
                </div>
                <div class="filter-item">
                    <label>Route</label>
                    <select id="routeFilter" class="form-control">
                        <option value="">All Routes</option>
                        <option value="none">Not Assigned</option>
                        <?php foreach ($routes as $r): ?><option value="<?php echo $r['route_id']; ?>"><?php echo e($r['route_name']); ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-item" style="flex-direction:row; gap:8px; align-items:flex-end;">
                    <button type="button" id="resetFilters" class="btn btn-default btn-sm"><i class="fa fa-refresh"></i> Reset</button>
                </div>
            </div>

            <?php if (count($students) === 0): ?>
                <div style="text-align:center; padding:40px 20px; color:#9CA3AF;">
                    <i class="fa fa-users" style="font-size:48px; display:block; margin-bottom:10px;"></i>
                    No active students found.
                </div>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="table table-striped table-bordered" id="studentTable" style="width:100%; margin:0;">
                        <thead>
                            <tr><th>#</th><th>Student</th><th>Father Name</th><th>GR No.</th><th>Form B No.</th><th>Course/Class</th><th style="min-width:240px;">Route</th><th>Assigned Vehicle</th><th style="width:80px;">Action</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $i => $s):
                                $key = strtolower(($s['first_name'] ?? '') . ' ' . ($s['last_name'] ?? '') . ' ' . ($s['gr_no'] ?? '') . ' ' . ($s['form_b_no'] ?? '') . ' ' . ($s['roll_no'] ?? ''));
                            ?>
                                <tr data-key="<?php echo e($key); ?>" data-route="<?php echo $s['route_id']; ?>">
                                    <td><?php echo $i + 1; ?></td>
                                    <td><strong><?php echo e(trim(($s['first_name'] ?? '') . ' ' . ($s['last_name'] ?? ''))); ?></strong></td>
                                    <td><?php echo e($s['father_name'] ?? '-'); ?></td>
                                    <td><?php echo e($s['gr_no'] ?? '-'); ?></td>
                                    <td><?php echo e($s['form_b_no'] ?? '-'); ?></td>
                                    <td><?php echo e($s['class_label'] ?? '-'); ?></td>
                                    <td>
                                        <form method="post" action="assign_student_routes.php" style="display:flex; gap:6px; align-items:center;">
                                            <input type="hidden" name="action" value="UpdateStudentRoute">
                                            <input type="hidden" name="student_id" value="<?php echo $s['student_id']; ?>">
                                            <select name="route_id" class="form-control" style="width:190px;">
                                                <option value="0">None</option>
                                                <?php foreach ($routes as $r): ?>
                                                    <option value="<?php echo $r['route_id']; ?>" <?php echo $s['route_id'] === (int) $r['route_id'] ? 'selected' : ''; ?>><?php echo e($r['route_name']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button class="btn btn-success btn-xs" style="color:#fff;"><i class="fa fa-check"></i></button>
                                        </form>
                                    </td>
                                    <td>
                                        <span class="veh-label">
                                            <?php echo $s['route_id'] > 0 && isset($routeVehicleMap[$s['route_id']]) && $routeVehicleMap[$s['route_id']] !== '' ? e($routeVehicleMap[$s['route_id']]) : '<i class="fa fa-minus" aria-hidden="true"></i>'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($s['route_id'] > 0): ?>
                                            <form method="post" action="assign_student_routes.php" style="display:inline;" onsubmit="return confirm('Remove this student\'s route?');">
                                                <input type="hidden" name="action" value="UpdateStudentRoute">
                                                <input type="hidden" name="student_id" value="<?php echo $s['student_id']; ?>">
                                                <input type="hidden" name="route_id" value="0">
                                                <button class="btn btn-danger btn-xs"><i class="fa fa-times"></i></button>
                                            </form>
                                        <?php else: ?>
                                            <span style="color:#cbd5e0;">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function applyFilter(){
    var q = document.getElementById('studentSearch').value.trim().toLowerCase();
    var route = document.getElementById('routeFilter').value;
    document.querySelectorAll('#studentTable tbody tr').forEach(function(tr){
        var show = true;
        if (q !== '' && (tr.getAttribute('data-key') || '').indexOf(q) === -1) show = false;
        if (show && route !== '') {
            var rv = tr.getAttribute('data-route');
            if (route === 'none') { if (rv !== '0') show = false; }
            else if (rv !== route) show = false;
        }
        tr.style.display = show ? '' : 'none';
    });
}
document.getElementById('studentSearch').addEventListener('keyup', applyFilter);
document.getElementById('routeFilter').addEventListener('change', applyFilter);
document.getElementById('resetFilters').addEventListener('click', function(){
    document.getElementById('studentSearch').value = '';
    document.getElementById('routeFilter').value = '';
    applyFilter();
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
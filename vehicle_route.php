<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Vehicle Route';

try {
    db_query("CREATE TABLE IF NOT EXISTS vehicle_route (
        route_id INT NOT NULL,
        vehicle_id INT NOT NULL,
        assigned_on DATE NULL,
        PRIMARY KEY (route_id)
    ) ENGINE=InnoDB");
} catch (Throwable $ex) {}

function vr_route_col_exists($c) {
    $r = db_query("SHOW COLUMNS FROM students LIKE '" . str_replace("'", '', $c) . "'");
    return $r && $r->num_rows > 0;
}
try {
    if (!vr_route_col_exists('route_id')) { db_query("ALTER TABLE students ADD COLUMN route_id INT NULL AFTER locality_id"); }
} catch (Throwable $ex) {}

try {
    $st2 = db_prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'route_vehicle_%'");
    $st2->execute();
    $res = $st2->get_result();
    $migrate = db_prepare("INSERT INTO vehicle_route (route_id, vehicle_id, assigned_on) VALUES (?, ?, CURDATE()) ON DUPLICATE KEY UPDATE vehicle_id=VALUES(vehicle_id)");
    $del = db_prepare("DELETE FROM settings WHERE setting_key=?");
    while ($row = $res->fetch_assoc()) {
        $rid = (int) substr($row['setting_key'], strlen('route_vehicle_'));
        $vid = (int) $row['setting_value'];
        if ($rid > 0 && $vid > 0) {
            $migrate->bind_param('ii', $rid, $vid);
            $migrate->execute();
        }
        $del->bind_param('s', $row['setting_key']);
        $del->execute();
    }
} catch (Throwable $ex) {}

$message = '';
$error = '';

$vehicles = [];
$res = db_query("SELECT * FROM vehicles WHERE status=1 ORDER BY vehicle_name, vehicle_no");
while ($row = $res->fetch_assoc()) { $vehicles[] = $row; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'AddRoute') {
        $route_name = trim($_POST['route_name'] ?? '');
        $fare = (float) ($_POST['fare'] ?? 0);
        if ($route_name === '') {
            $error = 'Route title is required.';
        } else {
            $st2 = db_prepare("INSERT INTO routes (route_name, fare) VALUES (?, ?)");
            $st2->bind_param('sd', $route_name, $fare);
            $st2->execute();
            $message = "Route '$route_name' added successfully!";
        }
    }

    if ($action === 'MapVehicle') {
        $route_id = (int) ($_POST['route_id'] ?? 0);
        $vehicle_id = (int) ($_POST['vehicle_id'] ?? 0);
        if ($route_id <= 0) {
            $error = 'Please select a route.';
        } else {
            if ($vehicle_id <= 0) {
                $st2 = db_prepare("DELETE FROM vehicle_route WHERE route_id=?");
                $st2->bind_param('i', $route_id);
                $st2->execute();
                $message = 'Vehicle assignment removed from route.';
            } else {
                $st2 = db_prepare("INSERT INTO vehicle_route (route_id, vehicle_id, assigned_on) VALUES (?, ?, CURDATE()) ON DUPLICATE KEY UPDATE vehicle_id=VALUES(vehicle_id), assigned_on=VALUES(assigned_on)");
                $st2->bind_param('ii', $route_id, $vehicle_id);
                $st2->execute();
                $message = 'Vehicle assigned to route successfully!';
            }
        }
    }

    if ($action === 'DeleteRoute') {
        $route_id = (int) ($_POST['route_id'] ?? 0);
        $st2 = db_prepare("DELETE FROM vehicle_route WHERE route_id=?");
        $st2->bind_param('i', $route_id);
        $st2->execute();
        $st2 = db_prepare("DELETE FROM routes WHERE route_id=?");
        $st2->bind_param('i', $route_id);
        $st2->execute();
        $st2 = db_prepare("UPDATE students SET route_id=NULL WHERE route_id=?");
        $st2->bind_param('i', $route_id);
        $st2->execute();
        $message = 'Route deleted successfully!';
    }
}

$routes = [];
$res = db_query("SELECT r.*, COALESCE(vr.vehicle_id, 0) AS mapped_vehicle, COALESCE(v.vehicle_no, '') AS vehicle_no, COALESCE(v.vehicle_name, '') AS vehicle_model
    FROM routes r
    LEFT JOIN vehicle_route vr ON vr.route_id = r.route_id
    LEFT JOIN vehicles v ON v.vehicle_id = vr.vehicle_id
    ORDER BY r.route_name");
while ($row = $res->fetch_assoc()) { $routes[] = $row; }

include __DIR__ . '/includes/header.php';
?>
<style>
.form-lbl { font-size: 13px; font-weight: 600; color: #4a5568; margin-bottom: 4px; }
.required::after { content: ' *'; color: #e53e3e; }
.vr-panel { background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:18px; margin-bottom:14px; }
</style>

<div class="main-content">
    <div class="container-fluid">
        <?php if ($message): ?><div class="alert alert-success"><?php echo e($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>

        <div style="display:flex; align-items:center; justify-content:space-between; padding:14px 4px; flex-wrap:wrap; gap:10px;">
            <h3 style="font-size:18px; font-weight:800; color:#111827; margin:0;"><i class="fa fa-road"></i> Vehicle Route List <span style="font-size:14px; color:#6B7280;">(<?php echo count($routes); ?> records)</span></h3>
            <div style="display:flex; gap:8px;">
                <a href="<?php echo BASE_URL; ?>assign_student_routes.php" class="btn btn-success" style="color:#fff;"><i class="fa fa-users"></i> Assign Student Routes</a>
                <a href="<?php echo BASE_URL; ?>route.php" class="btn btn-info" style="color:#fff;"><i class="fa fa-signal"></i> Routes</a>
            </div>
        </div>

        <div class="row">
            <div class="col-md-5">
                <div class="vr-panel">
                    <h4 style="font-size:15px; font-weight:800; margin:0 0 12px;"><i class="fa fa-plus" style="color:#48bb78;"></i> Add New Route</h4>
                    <form method="post" action="vehicle_route.php">
                        <input type="hidden" name="action" value="AddRoute">
                        <div class="form-group">
                            <label class="form-lbl required">Route Title</label>
                            <input type="text" name="route_name" class="form-control" placeholder="eg Ikhlas Pur" required>
                        </div>
                        <div class="form-group">
                            <label class="form-lbl">Fare</label>
                            <input type="number" step="0.01" name="fare" class="form-control" value="0">
                        </div>
                        <button type="submit" class="btn btn-success" style="width:100%;"><i class="fa fa-check"></i> Save Record</button>
                    </form>
                </div>

                <div class="vr-panel">
                    <h4 style="font-size:15px; font-weight:800; margin:0 0 12px;"><i class="fa fa-bus" style="color:#667eea;"></i> Assign Vehicle to Route</h4>
                    <form method="post" action="vehicle_route.php">
                        <input type="hidden" name="action" value="MapVehicle">
                        <div class="form-group">
                            <label class="form-lbl required">Route Title</label>
                            <select name="route_id" class="form-control" required>
                                <option value="">Select Route</option>
                                <?php foreach ($routes as $r): ?><option value="<?php echo $r['route_id']; ?>"><?php echo e($r['route_name']); ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-lbl required">Vehicle</label>
                            <select name="vehicle_id" class="form-control" required>
                                <option value="">Select Vehicle</option>
                                <?php foreach ($vehicles as $v): ?><option value="<?php echo $v['vehicle_id']; ?>"><?php echo e($v['vehicle_no']); ?> (<?php echo e($v['vehicle_name']); ?>)</option><?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary" style="width:100%; color:#fff;"><i class="fa fa-check"></i> Assign Vehicle</button>
                    </form>
                </div>
            </div>

            <div class="col-md-7">
                <div style="overflow-x:auto; background:#fff; border:1px solid #E5E7EB; border-radius:14px;">
                    <table class="table table-striped table-bordered" style="width:100%; background:#fff; margin-bottom:0;">
                        <thead>
                            <tr><th>S.No</th><th>Route Title</th><th style="min-width:200px;">Assign Vehicle</th><th style="width:90px;">Action</th></tr>
                        </thead>
                        <tbody>
                            <?php if (count($routes) === 0): ?>
                                <tr><td colspan="4" style="text-align:center; color:#6B7280; padding:36px;"><i class="fa fa-road" style="font-size:36px; display:block; margin-bottom:10px;"></i>No routes yet. Use the form to add one.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($routes as $i => $r): ?>
                                <tr>
                                    <td><?php echo $i + 1; ?></td>
                                    <td><strong><?php echo e($r['route_name']); ?></strong><?php if ((float) $r['fare'] > 0): ?><br><span style="font-size:11px; color:#718096;">Fare: <?php echo number_format($r['fare'], 2); ?></span><?php endif; ?></td>
                                    <td>
                                        <form method="post" action="vehicle_route.php" style="display:flex; gap:6px;">
                                            <input type="hidden" name="action" value="MapVehicle">
                                            <input type="hidden" name="route_id" value="<?php echo $r['route_id']; ?>">
                                            <select name="vehicle_id" class="form-control" style="width:150px;">
                                                <option value="0">None</option>
                                                <?php foreach ($vehicles as $v): ?>
                                                    <option value="<?php echo $v['vehicle_id']; ?>" <?php echo (int) $r['mapped_vehicle'] === (int) $v['vehicle_id'] ? 'selected' : ''; ?>><?php echo e($v['vehicle_no']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button class="btn btn-primary btn-xs" style="color:#fff;"><i class="fa fa-check"></i></button>
                                        </form>
                                        <?php if ($r['mapped_vehicle']): ?><span style="font-size:11px; color:#6B7280;"><?php echo e($r['vehicle_model']); ?></span><?php endif; ?>
                                    </td>
                                    <td>
                                        <form method="post" action="vehicle_route.php" style="display:inline;" onsubmit="return confirm('Delete this route and its mapping?');">
                                            <input type="hidden" name="action" value="DeleteRoute">
                                            <input type="hidden" name="route_id" value="<?php echo $r['route_id']; ?>">
                                            <button class="btn btn-danger btn-xs"><i class="fa fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
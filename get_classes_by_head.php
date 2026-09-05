<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

$head = isset($_GET['head']) ? (int)$_GET['head'] : (isset($_GET['class_head']) ? (int)$_GET['class_head'] : 0);
$out = [];

$cols = [];
$res = db_query("SHOW COLUMNS FROM classes");
if ($res) { while ($r = $res->fetch_assoc()) { $cols[] = $r['Field']; } }

if ($head > 0 && in_array('class_head_id', $cols)) {
    $stmt = db_prepare("SELECT class_id, class_name FROM classes WHERE class_head_id = ? AND status = 1 ORDER BY class_name");
    $stmt->bind_param('i', $head);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) { $out[] = ['id' => $row['class_id'], 'name' => $row['class_name']]; }
} else {
    $res = db_query("SELECT class_id, class_name FROM classes WHERE status = 1 ORDER BY class_name");
    if ($res) { while ($row = $res->fetch_assoc()) { $out[] = ['id' => $row['class_id'], 'name' => $row['class_name']]; } }
}

echo json_encode($out);
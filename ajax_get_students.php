<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

header('Content-Type: application/json');

$class_id = (int) ($_GET['class_id'] ?? 0);

$sql = "SELECT student_id, first_name, last_name, gr_no, father_name, class_id, section_id
        FROM students WHERE status=1";
if ($class_id > 0) { $sql .= " AND class_id = $class_id"; }
$sql .= " ORDER BY first_name ASC";

$out = [];
$res = db_query($sql);
while ($row = $res->fetch_assoc()) { $out[] = $row; }

echo json_encode($out);

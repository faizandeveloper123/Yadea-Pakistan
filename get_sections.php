<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

header('Content-Type: application/json');

$class_id = (int) ($_GET['class_id'] ?? 0);
$out = [];

if ($class_id > 0) {
    $stmt = db_prepare("SELECT section_id, section_name FROM sections WHERE class_id = ? ORDER BY section_name");
    $stmt->bind_param('i', $class_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) { $out[] = $row; }
}

echo json_encode($out);
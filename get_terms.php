<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

$session = isset($_GET['session']) ? trim($_GET['session']) : '';
$out = [];

if ($session !== '') {
    $stmt = db_prepare("SELECT DISTINCT exam_name AS name FROM exams WHERE session = ? AND exam_name IS NOT NULL AND exam_name <> '' ORDER BY exam_name");
    $stmt->bind_param('s', $session);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) { $out[] = $row['name']; }
}

echo json_encode(['results' => $out]);
<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

$term = isset($_GET['term']) ? trim($_GET['term']) : '';
$out = [];

$sql = "SELECT e.emp_id AS id, CONCAT(e.first_name, ' ', COALESCE(e.last_name, '')) AS name
        FROM employees e
        WHERE e.status = 1 AND (e.designation LIKE '%teacher%' OR e.designation LIKE '%instructor%' OR e.designation = '')
          AND CONCAT(e.first_name, ' ', COALESCE(e.last_name, '')) LIKE ?
        ORDER BY name LIMIT 25";
$stmt = db_prepare($sql);
$like = '%' . $term . '%';
$stmt->bind_param('s', $like);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) { $out[] = ['id' => $row['id'], 'name' => trim($row['name'])]; }

echo json_encode($out);
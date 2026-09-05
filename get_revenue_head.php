<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

$out = [];
$res = db_query("SELECT head_id, head_name FROM revenue_heads WHERE status = 1 ORDER BY head_name");
if ($res) { while ($row = $res->fetch_assoc()) { $out[] = ['id' => $row['head_id'], 'name' => $row['head_name']]; } }

echo json_encode($out);
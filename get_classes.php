<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

$q = isset($_GET['term']) ? trim($_GET['term']) : '';
$head = isset($_GET['class_head']) ? (int)$_GET['class_head'] : 0;

$rows = [];
if ($q !== '') {
    $sql = "SELECT c.id, c.name AS text,
                   COALESCE((SELECT COUNT(*) FROM students s WHERE s.class_id = c.id), 0) AS student_count
            FROM classes c
            WHERE c.name LIKE ?
            ORDER BY c.name ASC LIMIT 20";
    $stmt = db_prepare($sql);
    $like = '%' . $q . '%';
    $stmt->bind_param('s', $like);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) { $rows[] = $r; }
}

echo json_encode(['results' => $rows, 'term' => $q, 'class_head' => $head]);
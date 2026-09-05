<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$iid = (int) ($_GET['inquiry_id'] ?? 0);
$out = [];
if ($iid > 0) {
    $r = db_query("SELECT n.note, n.created_at, IFNULL(u.username, '') AS uname
                   FROM inquiry_notes n
                   LEFT JOIN users u ON u.user_id = n.created_by
                   WHERE n.inquiry_id = $iid ORDER BY n.created_at ASC");
    while ($row = $r->fetch_assoc()) {
        $out[] = [
            'note' => $row['note'],
            'created_at' => date('d M Y h:i A', strtotime($row['created_at'])),
            'by' => $row['uname'],
        ];
    }
}
header('Content-Type: application/json');
echo json_encode($out, JSON_UNESCAPED_UNICODE);
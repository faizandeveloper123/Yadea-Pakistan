<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

$q = isset($_GET['term']) ? trim($_GET['term']) : '';
$out = [];
if ($q !== '') {
    $qr = '/^[0-9-]+$/';
    $like = '%' . $q . '%';
    if (preg_match($qr, $q)) {
        $stmt = db_prepare("SELECT s.student_id AS id, s.first_name, s.last_name, s.father_name, s.gr_no,
                        CONCAT(s.first_name, ' ', COALESCE(s.last_name,'')) AS name,
                        CONCAT(COALESCE(c.class_name,''), ' ', COALESCE(sec.section_name,'')) AS class_sec
                        FROM students s
                        LEFT JOIN classes c ON c.class_id = s.class_id
                        LEFT JOIN sections sec ON sec.section_id = s.section_id
                        WHERE s.gr_no LIKE ? OR s.roll_no LIKE ? OR s.student_id = ? OR CONCAT(s.first_name,' ',COALESCE(s.last_name,'')) LIKE ? OR COALESCE(s.father_name,'') LIKE ? OR s.phone LIKE ? OR s.father_cellno LIKE ?
                        ORDER BY s.first_name LIMIT 12");
        $qlike = '%' . $q . '%';
        $exact = (int)$q;
        $stmt->bind_param('sssissss', $qlike, $qlike, $exact, $like, $like, $qlike, $qlike);
    } else {
        $stmt = db_prepare("SELECT s.student_id AS id, s.first_name, s.last_name, s.father_name, s.gr_no,
                        CONCAT(s.first_name, ' ', COALESCE(s.last_name,'')) AS name,
                        CONCAT(COALESCE(c.class_name,''), ' ', COALESCE(sec.section_name,'')) AS class_sec
                        FROM students s
                        LEFT JOIN classes c ON c.class_id = s.class_id
                        LEFT JOIN sections sec ON sec.section_id = s.section_id
                        WHERE CONCAT(s.first_name,' ',COALESCE(s.last_name,'')) LIKE ? OR COALESCE(s.father_name,'') LIKE ? OR s.phone LIKE ? OR s.father_cellno LIKE ?
                        ORDER BY s.first_name LIMIT 12");
        $stmt->bind_param('ssss', $like, $like, $like, $like);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $out[] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'father' => $row['father_name'],
            'gr' => $row['gr_no'],
            'class_sec' => trim($row['class_sec']),
        ];
    }
}
echo json_encode($out);
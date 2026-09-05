<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

header('Content-Type: application/json');

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
if ($q === '') {
    echo '[]';
    exit;
}

$term = '%' . $q . '%';

// Group students into families keyed by combination of guardian cell / guardian CNIC / father CNIC / address.
// We query the most recent student per family cluster, then pick representative fields.
$sql = "SELECT s.student_id, s.first_name, s.father_name, s.address,
               s.guardian_name, s.guardian_cnic, s.guardian_cellno, s.guardian_qualification,
               s.guardian_occupation, s.guardian_income, s.guardian_email, s.guardian_address,
               s.father_cnic, s.father_occupation
        FROM students s
        WHERE s.guardian_cellno LIKE ? OR s.guardian_cnic LIKE ? OR s.father_cnic LIKE ?
           OR s.address LIKE ? OR s.guardian_name LIKE ? OR s.father_name LIKE ?
        ORDER BY s.student_id DESC
        LIMIT 500";

$stmt = db_prepare($sql);
$stmt->bind_param('ssssss', $term, $term, $term, $term, $term, $term);
$stmt->execute();
$res = $stmt->get_result();

$seen = [];
$families = [];
while ($row = $res->fetch_assoc()) {
    $gcell = trim((string)($row['guardian_cellno'] ?? ''));
    $gcnic = trim((string)($row['guardian_cnic'] ?? ''));
    $fcnic = trim((string)($row['father_cnic'] ?? ''));
    $addr  = trim((string)($row['address'] ?? ''));

    // Build a stable family key: prefer guardian cell, then CNICs, then address
    $key = null;
    foreach ([$gcell, $gcnic, $fcnic, $addr] as $candidate) {
        if ($candidate !== '') { $key = $candidate; break; }
    }
    if ($key === null) { continue; }

    if (isset($seen[$key])) { continue; }
    $seen[$key] = true;

    $displayName = !empty(trim((string)$row['father_name'])) ? trim($row['father_name']) : trim((string)$row['guardian_name']);

    $families[] = [
        'family_name'             => $displayName !== '' ? $displayName : 'Unknown',
        'father_name'             => trim((string)$row['father_name']),
        'gcellno'                 => $gcell,
        'gcnic'                   => $gcnic,
        'cnic'                    => $fcnic,
        'address'                 => $addr,
        'guardian_name'           => trim((string)$row['guardian_name']),
        'guardian_cnic'           => trim((string)$row['guardian_cnic']),
        'guardian_cellno'         => $gcell,
        'guardian_qualification'  => trim((string)$row['guardian_qualification']),
        'guardian_occupation'     => trim((string)$row['guardian_occupation']),
        'guardian_income'         => trim((string)$row['guardian_income']),
        'guardian_email'          => trim((string)$row['guardian_email']),
        'guardian_address'        => trim((string)$row['guardian_address']),
        'father_occupation'       => trim((string)$row['father_occupation'])
    ];
}

echo json_encode($families);
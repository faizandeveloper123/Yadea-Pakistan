<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

header('Content-Type: application/json');

$code = isset($_GET['code']) ? trim($_GET['code']) : '';
if ($code === '') { echo 'null'; exit; }

$stmt = db_prepare("SELECT student_id, first_name, father_name, mother_name, phone,
        guardian_name, guardian_cnic, guardian_cellno, guardian_qualification,
        guardian_occupation, guardian_income, guardian_email, guardian_address,
        father_cnic, father_qualification, father_business_address, father_income,
        father_occupation, father_cellno, mother_cnic, mother_qualification,
        mother_activity, mother_designation,  locality_id, address
        FROM students WHERE family_code = ? ORDER BY student_id DESC LIMIT 1");
$stmt->bind_param('s', $code);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if (!$row) { echo 'null'; exit; }

echo json_encode([
    'family_code'             => $code,
    'father_name'             => $row['father_name'],
    'mother_name'             => $row['mother_name'],
    'phone'                   => $row['phone'],
    'father_cnic'             => $row['father_cnic'],
    'father_qualification'    => $row['father_qualification'],
    'father_business_address' => $row['father_business_address'],
    'father_income'           => $row['father_income'],
    'father_occupation'       => $row['father_occupation'],
    'father_cellno'           => $row['father_cellno'],
    'mother_cnic'             => $row['mother_cnic'],
    'mother_qualification'    => $row['mother_qualification'],
    'mother_activity'         => $row['mother_activity'],
    'mother_designation'      => $row['mother_designation'],
    'locality_id'             => $row['locality_id'],
    'address'                 => $row['address'],
    'guardian_name'           => $row['guardian_name'],
    'guardian_cnic'           => $row['guardian_cnic'],
    'guardian_cellno'         => $row['guardian_cellno'],
    'guardian_qualification'  => $row['guardian_qualification'],
    'guardian_occupation'     => $row['guardian_occupation'],
    'guardian_income'         => $row['guardian_income'],
    'guardian_email'          => $row['guardian_email'],
    'guardian_address'        => $row['guardian_address']
]);

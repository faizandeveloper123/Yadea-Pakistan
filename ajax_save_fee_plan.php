<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

// Ensure student_fee_plan table exists
try { db_query("CREATE TABLE IF NOT EXISTS student_fee_plan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    head_id INT DEFAULT NULL,
    head_name VARCHAR(191) DEFAULT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    discount DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY (student_id)
) ENGINE=InnoDB"); } catch (Throwable $ex) {}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Invalid request method.']);
    exit;
}

$sid = (int) ($_POST['student_id'] ?? 0);
if ($sid <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Invalid student ID.']);
    exit;
}

// Delete existing fee plan for this student
$del = db_prepare('DELETE FROM student_fee_plan WHERE student_id = ?');
$del->bind_param('i', $sid);
$del->execute();
$del->close();

// Parse the fee heads JSON
$headsJson = $_POST['heads_data'] ?? '{}';
$heads = json_decode($headsJson, true);
if (!is_array($heads)) $heads = [];

$done = 0;
$ins = db_prepare('INSERT INTO student_fee_plan (student_id, head_id, head_name, amount, discount) VALUES (?, ?, ?, ?, ?)');
foreach ($heads as $headId => $row) {
    $hid = (int) $headId;
    $hname = trim($row['name'] ?? '');
    $amt = (float) ($row['amount'] ?? 0);
    $disc = (float) ($row['discount'] ?? 0);
    $ins->bind_param('iisdd', $sid, $hid, $hname, $amt, $disc);
    $ins->execute();
    $done++;
}
$ins->close();

echo json_encode(['ok' => true, 'saved' => $done]);

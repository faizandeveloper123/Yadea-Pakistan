<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

header('Content-Type: application/json');

function sms_clean_numbers(array $raw): array {
    $out = [];
    foreach ($raw as $n) {
        $n = trim((string) $n);
        if ($n === '') continue;
        $n = preg_replace('/[^0-9+]/', '', $n);
        if (strlen($n) < 7) continue;
        if (str_starts_with($n, '00')) { $n = '+' . substr($n, 2); }
        else if (str_starts_with($n, '0')) { $n = '92' . substr($n, 1); }
        $out[$n] = true;
    }
    return array_keys($out);
}

function sms_student_numbers(array $s): array {
    $cands = [$s['phone'] ?? '', $s['whatsapp_number'] ?? '', $s['father_cellno'] ?? '',
              $s['mother_cell'] ?? '', $s['guardian_cellno'] ?? '', $s['home_number'] ?? ''];
    return sms_clean_numbers($cands);
}

function sms_contacts_query(string $sql, array $extra = []): array {
    $params = [];
    $types  = '';
    foreach ($extra as $p) {
        if ($p['v'] === null || $p['v'] === '' || $p['v'] === 0) continue;
        $types .= $p['t'];
        $params[] = (string) $p['v'];
    }
    if ($params) {
        $stmt = db_prepare($sql . ' LIMIT 20000');
        $bindVals = [$types];
        foreach ($params as $k => $v) { $bindVals[] = &$params[$k]; }
        call_user_func_array([$stmt, 'bind_param'], $bindVals);
        $stmt->execute();
        $res = $stmt->get_result();
    } else {
        $res = db_query($sql . ' LIMIT 20000');
    }
    $rows = [];
    while ($row = $res->fetch_assoc()) { $rows[] = $row; }
    return $rows;
}

$group      = $_GET['group'] ?? '';
$class_id   = (int) ($_GET['class_id'] ?? 0);
$section_id = (int) ($_GET['section_id'] ?? 0);
$numbers    = [];

$studentSelect = "SELECT phone, whatsapp_number, father_cellno, mother_cell, guardian_cellno, home_number FROM students";
$classWhere = '';
if ($class_id > 0 && ($group === 'AllStudents' || $group === 'AllFamilies' || $group === 'feeDefaulters' || $group === '' || $group === 'sectionNumbers')) {
    $classWhere = ' AND class_id = ' . (int) $class_id;
    if ($section_id > 0) { $classWhere .= ' AND section_id = ' . (int) $section_id; }
}

switch ($group) {
    case 'AllStudents':
        $rows = sms_contacts_query($studentSelect . ' WHERE status = 1' . $classWhere);
        foreach ($rows as $r) { $numbers = array_merge($numbers, sms_student_numbers($r)); }
        break;

    case 'AllFamilies':
        $rows = sms_contacts_query($studentSelect . ' WHERE status = 1' . $classWhere);
        foreach ($rows as $r) {
            $fam = sms_clean_numbers([$r['father_cellno'] ?? '', $r['guardian_cellno'] ?? '']);
            if (!$fam) { $fam = sms_student_numbers($r); }
            $numbers = array_merge($numbers, array_slice($fam, 0, 1));
        }
        break;

    case 'feeDefaulters':
        $rows = sms_contacts_query($studentSelect . " WHERE status = 1" . $classWhere . " AND student_id IN (
                    SELECT student_id FROM fee_challans WHERE status IN ('unpaid','partial'))");
        foreach ($rows as $r) { $numbers = array_merge($numbers, sms_student_numbers($r)); }
        break;

    case 'Employees':
        $rows = sms_contacts_query("SELECT emp_id, phone FROM employees WHERE status = 1");
        foreach ($rows as $r) { $numbers = array_merge($numbers, sms_clean_numbers([$r['phone'] ?? ''])); }
        break;

    case 'Contacts':
        $rows = sms_contacts_query("SELECT phone, whatsapp_number, father_cellno, mother_cell, guardian_cellno, home_number FROM students WHERE status = 1");
        foreach ($rows as $r) { $numbers = array_merge($numbers, sms_student_numbers($r)); }
        $rows = sms_contacts_query("SELECT emp_id, phone FROM employees WHERE status = 1");
        foreach ($rows as $r) { $numbers = array_merge($numbers, sms_clean_numbers([$r['phone'] ?? ''])); }
        $rows = sms_contacts_query("SELECT inquiry_id, phone FROM inquiries WHERE phone IS NOT NULL AND phone <> ''");
        foreach ($rows as $r) { $numbers = array_merge($numbers, sms_clean_numbers([$r['phone'] ?? ''])); }
        break;

    case 'OldStudents':
        $rows = sms_contacts_query($studentSelect . ' WHERE status = 0');
        foreach ($rows as $r) { $numbers = array_merge($numbers, sms_student_numbers($r)); }
        break;

    case 'AdmInquiries':
        $rows = sms_contacts_query("SELECT inquiry_id, phone FROM inquiries WHERE phone IS NOT NULL AND phone <> ''");
        foreach ($rows as $r) { $numbers = array_merge($numbers, sms_clean_numbers([$r['phone'] ?? ''])); }
        break;

    case 'OldEmployees':
        $rows = sms_contacts_query("SELECT emp_id, phone FROM employees WHERE status = 0");
        foreach ($rows as $r) { $numbers = array_merge($numbers, sms_clean_numbers([$r['phone'] ?? ''])); }
        break;

    case 'sectionNumbers':
    default:
        if ($class_id > 0) {
            $rows = sms_contacts_query($studentSelect . ' WHERE status = 1' . $classWhere);
            foreach ($rows as $r) { $numbers = array_merge($numbers, sms_student_numbers($r)); }
        }
        break;
}

$numbers = array_values(array_unique($numbers));
echo json_encode(['group' => $group, 'count' => count($numbers), 'numbers' => $numbers]);
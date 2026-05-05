<?php

require_once '../../config.php';

validateRequestMethod('POST');
$auth= requireAuth(['super_admin', 'admin']);
$input= requireParams(['student_id', 'item_id']);

$student_id = $input['student_id'] ;
$item_id = $input['item_id'] ;
$start_date = $input['start_date'] ?? date('Y-m-d H:i:s');
$end_date = $input['end_date'] ?? null;
$status = $input['status'] ?? 'active';

$stmt = $conn->prepare("SELECT id FROM student_access WHERE student_id = ? AND item_id = ?");
$stmt->bind_param("ii", $student_id, $item_id);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

if ($result->num_rows > 0) {
    respond('error', 'Access already exists for this student and item');
}

$stmt = $conn->prepare("
    INSERT INTO student_access (student_id, item_id, start_date, end_date, status)
    VALUES (?, ?, ?, ?, ?)
");

$stmt->bind_param("iisss", $student_id, $item_id, $start_date, $end_date, $status);

$stmt->execute();
$studentAccessId = $stmt->insert_id;
$stmt->close();

logAction($auth['id'], 'create_student_access', 'student', $student_id, "Created student_access: $studentAccessId");
respond('success', 'Access granted successfully');
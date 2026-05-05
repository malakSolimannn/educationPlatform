<?php

require_once '../../config.php';

validateRequestMethod('POST');
$auth= requireAuth(['super_admin', 'admin']);

$input=requireParams(['id']);
$id = (int)$input['id'];
$start_date = $input['start_date'] ?? null;
$end_date = $input['end_date'] ?? null;
$status = $input['status'] ?? null;

$stmt = $conn->prepare("SELECT * FROM student_access WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$current = $result->fetch_assoc();
$stmt->close();

if (!$current) {
    respond('error', 'Access not found');
}

if (!$start_date) $start_date = $current['start_date'];
if (!array_key_exists('end_date', $_POST)) $end_date = $current['end_date'];
if (!$status) $status = $current['status'];

$stmt = $conn->prepare("
    UPDATE student_access SET
        start_date = ?,
        end_date = ?,
        status = ?
    WHERE id = ?
");

$stmt->bind_param("sssi", $start_date, $end_date, $status, $id);

$stmt->execute();
$stmt->close();

logAction($auth['id'], 'update_student_access', 'student', $current['student_id'], "Updated student_access: $id");
respond('success', 'Access updated successfully');
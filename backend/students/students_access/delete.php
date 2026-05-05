<?php

require_once '../../config.php';

validateRequestMethod('POST');
$auth=requireAuth(['super_admin', 'admin']);

$input = requireParams(['id']);
$id = (int)$input['id'];

$stmt = $conn->prepare("DELETE FROM student_access WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();

if ($stmt->affected_rows === 0) {
    $stmt->close();
    respond('error', 'Access not found or already deleted');
}

$stmt->close();

logAction($auth['id'], 'delete_student_access', 'student', "Deleted student_access: $id");
respond('success', 'Access deleted successfully');
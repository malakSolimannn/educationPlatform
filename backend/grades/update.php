<?php

require_once '../config.php';

validateRequestMethod('PUT');
$auth=requireAuth(['super_admin', 'admin']);
$input = requireParams(['id']);

$gradeId = (int)$input['id'];

$stmt = $conn->prepare("SELECT id FROM grades WHERE id = ?");
$stmt->bind_param("i", $gradeId);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

if ($result->num_rows === 0) {
    respond('error', 'Grade not found');
}

if (!isset($input['name']) || trim($input['name']) === '') {
    respond('error', 'Name is required');
}

$name = trim($input['name']);

$stmt = $conn->prepare("SELECT id FROM grades WHERE name = ? AND id != ?");
$stmt->bind_param("si", $name, $gradeId);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

if ($result->num_rows > 0) {
    respond('error', 'Grade name already exists');
}

$stmt = $conn->prepare("UPDATE grades SET name = ? WHERE id = ?");
$stmt->bind_param("si", $name, $gradeId);

if ($stmt->execute()) {
    $stmt->close();

    logAction($auth['id'], 'update_grade', 'grade', $gradeId, "Updated grade: $name");

    respond('success', ['message' => 'Grade updated successfully']);
} else {
    $stmt->close();
    respond('error', 'Failed to update grade');
}
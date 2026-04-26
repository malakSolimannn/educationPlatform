<?php

require_once '../config.php';

validateRequestMethod('DELETE');
requireAuth(['super_admin', 'admin']);
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

// can't delete grade that is has items
$stmt = $conn->prepare("SELECT id FROM items WHERE grade_id = ? LIMIT 1");
$stmt->bind_param("i", $gradeId);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

if ($result->num_rows > 0) {
    respond('error', 'Cannot delete grade that is assigned to items');
}

$stmt = $conn->prepare("DELETE FROM grades WHERE id = ?");
$stmt->bind_param("i", $gradeId);

if ($stmt->execute()) {
    $stmt->close();

    logAction($auth['id'], 'delete_grade', 'grade', $gradeId, "Deleted grade ID: $gradeId");

    respond('success', ['message' => 'Grade deleted successfully']);
} else {
    $stmt->close();
    respond('error', 'Failed to delete grade');
}
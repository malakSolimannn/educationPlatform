<?php

require_once '../config.php';

validateRequestMethod('DELETE');
$auth = requireAuth(['super_admin', 'admin']);
$input = requireParams(['id']);
$assignmentId = (int)$input['id'];

$stmt = $conn->prepare("SELECT id FROM assignments WHERE id = ?");
$stmt->bind_param("i", $assignmentId);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

if ($result->num_rows === 0) {
    respond('error', 'Assignment not found');
}

$conn->begin_transaction();

try {
    $stmt = $conn->prepare("DELETE FROM assignment_submissions WHERE assignment_id = ?");
    $stmt->bind_param("i", $assignmentId);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("DELETE FROM assignments WHERE id = ?");
    $stmt->bind_param("i", $assignmentId);
    $stmt->execute();
    $stmt->close();

    logAction($auth['id'], 'delete_assignment', 'assignment', $assignmentId, "Deleted assignment ID: $assignmentId");

    $conn->commit();

    respond('success', ['message' => 'Assignment deleted successfully']);

} catch (Exception $e) {
    $conn->rollback();
    respond('error', 'Failed to delete assignment');
}

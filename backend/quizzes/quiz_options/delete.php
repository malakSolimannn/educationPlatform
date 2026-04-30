<?php

require_once '../../config.php';

validateRequestMethod('DELETE');
$auth = requireAuth(['super_admin', 'admin']);
$input = requireParams(['id']);
$optionId = (int)$input['id'];

$stmt = $conn->prepare("SELECT id FROM quiz_options WHERE id = ?");
$stmt->bind_param("i", $optionId);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

if ($result->num_rows === 0) {
    respond('error', 'Option not found');
}

$conn->begin_transaction();

try {
    $stmt = $conn->prepare("DELETE FROM student_quiz_answers WHERE selected_option_id = ?");
    $stmt->bind_param("i", $optionId);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("DELETE FROM quiz_options WHERE id = ?");
    $stmt->bind_param("i", $optionId);
    $stmt->execute();
    $stmt->close();

    logAction($auth['id'], 'delete_quiz_option', 'quiz_option', $optionId, "Deleted option ID: $optionId");

    $conn->commit();

    respond('success', ['message' => 'Option deleted successfully']);

} catch (Exception $e) {
    $conn->rollback();
    respond('error', 'Failed to delete option');
}

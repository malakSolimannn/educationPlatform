<?php

require_once '../../config.php';

validateRequestMethod('DELETE');
$auth = requireAuth(['super_admin', 'admin']);
$input = requireParams(['id']);
$questionId = (int)$input['id'];

$stmt = $conn->prepare("SELECT id FROM quiz_questions WHERE id = ?");
$stmt->bind_param("i", $questionId);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

if ($result->num_rows === 0) {
    respond('error', 'Question not found');
}

$conn->begin_transaction();

try {
    $stmt = $conn->prepare("DELETE FROM student_quiz_answers WHERE question_id = ?");
    $stmt->bind_param("i", $questionId);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("DELETE FROM quiz_options WHERE question_id = ?");
    $stmt->bind_param("i", $questionId);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("DELETE FROM quiz_questions WHERE id = ?");
    $stmt->bind_param("i", $questionId);
    $stmt->execute();
    $stmt->close();

    logAction($auth['id'], 'delete_quiz_question', 'quiz_question', $questionId, "Deleted question ID: $questionId");

    $conn->commit();

    respond('success', ['message' => 'Question deleted successfully']);

} catch (Exception $e) {
    $conn->rollback();
    respond('error', 'Failed to delete question');
}

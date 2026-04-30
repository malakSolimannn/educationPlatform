<?php

require_once '../config.php';

validateRequestMethod('DELETE');
$auth = requireAuth(['super_admin', 'admin']);
$input = requireParams(['id']);
$quizId = (int)$input['id'];

$stmt = $conn->prepare("SELECT id FROM quizzes WHERE id = ?");
$stmt->bind_param("i", $quizId);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

if ($result->num_rows === 0) {
    respond('error', 'Quiz not found');
}

$conn->begin_transaction();

try {
    $stmt = $conn->prepare("SELECT id FROM quiz_questions WHERE quiz_id = ?");
    $stmt->bind_param("i", $quizId);
    $stmt->execute();
    $questionsResult = $stmt->get_result();
    $stmt->close();

    $questionIds = [];
    while ($row = $questionsResult->fetch_assoc()) {
        $questionIds[] = $row['id'];
    }

    if (!empty($questionIds)) {
        $placeholders = implode(',', array_fill(0, count($questionIds), '?'));
        
        $stmt = $conn->prepare("DELETE FROM student_quiz_answers WHERE question_id IN ($placeholders)");
        $stmt->bind_param(str_repeat('i', count($questionIds)), ...$questionIds);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("DELETE FROM quiz_options WHERE question_id IN ($placeholders)");
        $stmt->bind_param(str_repeat('i', count($questionIds)), ...$questionIds);
        $stmt->execute();
        $stmt->close();
    }

    $stmt = $conn->prepare("DELETE FROM quiz_questions WHERE quiz_id = ?");
    $stmt->bind_param("i", $quizId);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("DELETE FROM quiz_attempts WHERE quiz_id = ?");
    $stmt->bind_param("i", $quizId);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("DELETE FROM quizzes WHERE id = ?");
    $stmt->bind_param("i", $quizId);
    $stmt->execute();
    $stmt->close();

    logAction($auth['id'], 'delete_quiz', 'quiz', $quizId, "Deleted quiz ID: $quizId");

    $conn->commit();

    respond('success', ['message' => 'Quiz deleted successfully']);

} catch (Exception $e) {
    $conn->rollback();
    respond('error', 'Failed to delete quiz');
}

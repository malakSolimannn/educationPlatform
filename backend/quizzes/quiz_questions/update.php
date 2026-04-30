<?php

require_once '../../config.php';

validateRequestMethod('POST');
$auth = requireAuth(['super_admin', 'admin']);
$input = requireParams(['id']);
$questionId = (int)$input['id'];

$stmt = $conn->prepare("SELECT * FROM quiz_questions WHERE id = ?");
$stmt->bind_param("i", $questionId);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

if ($result->num_rows === 0) {
    respond('error', 'Question not found');
}

$updates = [];
$params = [];
$types = '';

$allowedQuestionTypes = ['mcq', 'true_false', 'text'];

if (isset($input['question_type']) && in_array($input['question_type'], $allowedQuestionTypes)) {
    $updates[] = 'question_type = ?';
    $params[] = $input['question_type'];
    $types .= 's';
}

if (isset($input['question_text']) && $input['question_text'] !== '') {
    $updates[] = 'question_text = ?';
    $params[] = trim($input['question_text']);
    $types .= 's';
}

if (isset($input['points']) && is_numeric($input['points'])) {
    $updates[] = 'points = ?';
    $params[] = (float)$input['points'];
    $types .= 'd';
}

if (empty($updates)) {
    respond('error', 'No fields to update');
}

$params[] = $questionId;
$types .= 'i';

$sql = "UPDATE quiz_questions SET " . implode(', ', $updates) . " WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    $stmt->close();
    logAction($auth['id'], 'update_quiz_question', 'quiz_question', $questionId, "Updated question ID: $questionId");
    respond('success', ['message' => 'Question updated successfully']);
} else {
    $stmt->close();
    respond('error', 'Failed to update question');
}

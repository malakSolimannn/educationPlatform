<?php

require_once '../../config.php';

validateRequestMethod('POST');
$auth = requireAuth(['super_admin', 'admin']);
$input = requireParams(['quiz_id', 'question_type', 'question_text', 'points']);

$quizId = (int)$input['quiz_id'];
$questionType = trim($input['question_type']);
$questionText = trim($input['question_text']);
$points = isset($input['points']) ? (float)$input['points'] : 1.00;

$allowedQuestionTypes = ['mcq', 'true_false', 'text'];

if (!in_array($questionType, $allowedQuestionTypes)) {
    respond('error', 'Invalid question type');
}

if (empty($questionText)) {
    respond('error', 'Question text is required');
}

$stmt = $conn->prepare("SELECT id FROM quizzes WHERE id = ?");
$stmt->bind_param("i", $quizId);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

if ($result->num_rows === 0) {
    respond('error', 'Quiz not found');
}

$stmt = $conn->prepare("
    INSERT INTO quiz_questions (
        quiz_id,
        question_type,
        question_text,
        points
    ) VALUES (?, ?, ?, ?)
");

$stmt->bind_param(
    "issd",
    $quizId,
    $questionType,
    $questionText,
    $points
);

if (!$stmt->execute()) {
    $stmt->close();
    respond('error', 'Failed to create question');
}

$questionId = $stmt->insert_id;
$stmt->close();

logAction($auth['id'], 'create_quiz_question', 'quiz_question', $questionId, json_encode([
    'quiz_id' => $quizId,
    'question_type' => $questionType
]));

respond('success', [
    'id' => $questionId,
    'quiz_id' => $quizId,
    'question_type' => $questionType,
    'question_text' => $questionText,
    'points' => $points
]);

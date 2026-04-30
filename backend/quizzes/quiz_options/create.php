<?php

require_once '../../config.php';

validateRequestMethod('POST');
$auth = requireAuth(['super_admin', 'admin']);
$input = requireParams(['question_id', 'option_text']);

$questionId = (int)$input['question_id'];
$optionText = trim($input['option_text']);
$isCorrect = isset($input['is_correct']) ? (int)$input['is_correct'] : 0;

if (empty($optionText)) {
    respond('error', 'Option text is required');
}

$stmt = $conn->prepare("SELECT id FROM quiz_questions WHERE id = ?");
$stmt->bind_param("i", $questionId);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

if ($result->num_rows === 0) {
    respond('error', 'Question not found');
}

$stmt = $conn->prepare("
    INSERT INTO quiz_options (
        question_id,
        option_text,
        is_correct
    ) VALUES (?, ?, ?)
");

$stmt->bind_param(
    "isi",
    $questionId,
    $optionText,
    $isCorrect
);

if (!$stmt->execute()) {
    $stmt->close();
    respond('error', 'Failed to create option');
}

$optionId = $stmt->insert_id;
$stmt->close();

logAction($auth['id'], 'create_quiz_option', 'quiz_option', $optionId, json_encode([
    'question_id' => $questionId,
    'is_correct' => $isCorrect
]));

respond('success', [
    'id' => $optionId,
    'question_id' => $questionId,
    'option_text' => $optionText,
    'is_correct' => $isCorrect
]);

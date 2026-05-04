<?php

require_once '../../config.php';

validateRequestMethod('POST');

$auth = requireAuth('student');
$studentId = (int)$auth['id'];

$input = requireParams(['attempt_id', 'question_id']);

$attemptId = (int)$input['attempt_id'];
$questionId = (int)$input['question_id'];

$selectedOptionId = isset($input['selected_option_id']) ? (int)$input['selected_option_id'] : null;
$textAnswer = isset($input['text_answer']) ? trim($input['text_answer']) : null;

$stmt = $conn->prepare("
    SELECT *
    FROM quiz_attempts
    WHERE id = ?
    AND student_id = ?
    AND status = 'in_progress'
    LIMIT 1
");

$stmt->bind_param("ii", $attemptId, $studentId);
$stmt->execute();
$attempt = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$attempt) {
    respond('error', 'Invalid or closed attempt');
}


$stmt = $conn->prepare("
    SELECT *
    FROM quiz_questions
    WHERE id = ?
    AND quiz_id = ?
    LIMIT 1
");

$stmt->bind_param("ii", $questionId, $attempt['quiz_id']);
$stmt->execute();
$question = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$question) {
    respond('error', 'Question not found');
}

if ($question['question_type'] === 'text') {
    if (!$textAnswer) {
        respond('error', 'Text answer is required');
    }
    $selectedOptionId = null;
} else {
    if (!$selectedOptionId) {
        respond('error', 'Option must be selected');
    }

    $stmt = $conn->prepare("
        SELECT id
        FROM quiz_options
        WHERE id = ?
        AND question_id = ?
        LIMIT 1
    ");

    $stmt->bind_param("ii", $selectedOptionId, $questionId);
    $stmt->execute();
    $option = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$option) {
        respond('error', 'Invalid option selected');
    }

    $textAnswer = null;
}


$stmt = $conn->prepare("
    SELECT id
    FROM student_quiz_answers
    WHERE attempt_id = ?
    AND question_id = ?
    LIMIT 1
");

$stmt->bind_param("ii", $attemptId, $questionId);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($existing) {
    $stmt = $conn->prepare("
        UPDATE student_quiz_answers
        SET 
            selected_option_id = ?,
            text_answer = ?,
            is_correct = NULL,
            grade = NULL
        WHERE id = ?
    ");

    $stmt->bind_param("isi", $selectedOptionId, $textAnswer, $existing['id']);
} else {
    $stmt = $conn->prepare("
        INSERT INTO student_quiz_answers (
            attempt_id,
            question_id,
            selected_option_id,
            text_answer
        ) VALUES (?, ?, ?, ?)
    ");

    $stmt->bind_param("iiis", $attemptId, $questionId, $selectedOptionId, $textAnswer);
}

if (!$stmt->execute()) {
    $stmt->close();
    respond('error', 'Failed to save answer');
}

$stmt->close();

respond('success', [
    'attempt_id' => $attemptId,
    'question_id' => $questionId,
    'message' => 'Answer saved successfully'
]);

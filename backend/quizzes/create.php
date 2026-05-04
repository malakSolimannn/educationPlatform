<?php

require_once '../config.php';

validateRequestMethod('POST');
$auth = requireAuth(['super_admin', 'admin']);
$input = requireParams(['title', 'type']);

$itemId = isset($input['item_id']) && $input['item_id'] !== '' ? (int)$input['item_id'] : null;
$title = trim($input['title']);
$type = trim($input['type']);
$timeLimitMinutes = isset($input['time_limit_minutes']) && $input['time_limit_minutes'] !== '' 
    ? (int)$input['time_limit_minutes'] 
    : null;
$attemptLimit = isset($input['attempt_limit']) && $input['attempt_limit'] !== '' 
    ? (int)$input['attempt_limit'] 
    : null;
$randomizeQuestions = isset($input['randomize_questions']) ? (int)$input['randomize_questions'] : 0;
$randomizeAnswers = isset($input['randomize_answers']) ? (int)$input['randomize_answers'] : 0;
$isPublished = isset($input['is_published']) ? (int)$input['is_published'] : 1;

$allowedQuizTypes = ['practice', 'exam', 'assignment', 'placement'];

if (!in_array($type, $allowedQuizTypes)) {
    respond('error', 'Invalid quiz type');
}
if ($timeLimitMinutes !== null && $timeLimitMinutes < 1) {
    respond('error', 'Time limit must be at least 1 minute');
}

if ($attemptLimit !== null && $attemptLimit < 1) {
    respond('error', 'Attempt limit must be at least 1');
}

if ($itemId !== null) {
    $stmt = $conn->prepare("SELECT id FROM items WHERE id = ?");
    $stmt->bind_param("i", $itemId);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    if ($result->num_rows === 0) {
        respond('error', 'Item not found');
    }
}

$stmt = $conn->prepare("
    INSERT INTO quizzes (
        item_id,
        title,
        time_limit_minutes,
        type,
        attempt_limit,
        randomize_questions,
        randomize_answers,
        is_published
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
");

$stmt->bind_param(
    "isisiiii",
    $itemId,
    $title,
    $timeLimitMinutes,
    $type,
    $attemptLimit,
    $randomizeQuestions,
    $randomizeAnswers,
    $isPublished
);

if (!$stmt->execute()) {
    $stmt->close();
    respond('error', 'Failed to create quiz');
}

$quizId = $stmt->insert_id;
$stmt->close();

logAction($auth['id'], 'create_quiz', 'quiz', $quizId, "Created quiz: $title");

respond('success', [
    'id' => $quizId,
    'item_id' => $itemId,
    'title' => $title,
    'type' => $type,
    'time_limit_minutes' => $timeLimitMinutes,
    'attempt_limit' => $attemptLimit,
    'randomize_questions' => $randomizeQuestions,
    'randomize_answers' => $randomizeAnswers,
    'is_published' => $isPublished
]);

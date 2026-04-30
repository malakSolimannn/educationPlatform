<?php

require_once '../config.php';

validateRequestMethod('POST');
$auth = requireAuth(['super_admin', 'admin']);
$input = requireParams(['id']);
$quizId = (int)$input['id'];

$stmt = $conn->prepare("SELECT * FROM quizzes WHERE id = ?");
$stmt->bind_param("i", $quizId);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

if ($result->num_rows === 0) {
    respond('error', 'Quiz not found');
}

$updates = [];
$params = [];
$types = '';

if (isset($input['title']) && $input['title'] !== '') {
    $updates[] = 'title = ?';
    $params[] = trim($input['title']);
    $types .= 's';
}

if (isset($input['type']) && $input['type'] !== '') {
    $updates[] = 'type = ?';
    $params[] = trim($input['type']);
    $types .= 's';
}

if (isset($input['item_id'])) {
    if ($input['item_id'] === '' || $input['item_id'] === null) {
        $updates[] = 'item_id = NULL';
    } else {
        $itemId = (int)$input['item_id'];
        
        $stmt = $conn->prepare("SELECT id FROM items WHERE id = ?");
        $stmt->bind_param("i", $itemId);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();

        if ($result->num_rows === 0) {
            respond('error', 'Item not found');
        }

        $updates[] = 'item_id = ?';
        $params[] = $itemId;
        $types .= 'i';
    }
}

if (isset($input['time_limit_minutes'])) {
    if ($input['time_limit_minutes'] === '' || $input['time_limit_minutes'] === null) {
        $updates[] = 'time_limit_minutes = NULL';
    } else {
        $minutes = (int)$input['time_limit_minutes'];
        if ($minutes < 1) {
            respond('error', 'Time limit must be at least 1 minute');
        }
        $updates[] = 'time_limit_minutes = ?';
        $params[] = $minutes;
        $types .= 'i';
    }
}

if (isset($input['attempt_limit'])) {
    if ($input['attempt_limit'] === '' || $input['attempt_limit'] === null) {
        $updates[] = 'attempt_limit = NULL';
    } else {
        $limit = (int)$input['attempt_limit'];
        if ($limit < 1) {
            respond('error', 'Attempt limit must be at least 1');
        }
        $updates[] = 'attempt_limit = ?';
        $params[] = $limit;
        $types .= 'i';
    }
}

if (isset($input['randomize_questions'])) {
    $updates[] = 'randomize_questions = ?';
    $params[] = (int)$input['randomize_questions'];
    $types .= 'i';
}

if (isset($input['randomize_answers'])) {
    $updates[] = 'randomize_answers = ?';
    $params[] = (int)$input['randomize_answers'];
    $types .= 'i';
}

if (isset($input['is_published'])) {
    $updates[] = 'is_published = ?';
    $params[] = (int)$input['is_published'];
    $types .= 'i';
}

if (empty($updates)) {
    respond('error', 'No fields to update');
}

$params[] = $quizId;
$types .= 'i';

$sql = "UPDATE quizzes SET " . implode(', ', $updates) . " WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    $stmt->close();
    logAction($auth['id'], 'update_quiz', 'quiz', $quizId, "Updated quiz ID: $quizId");
    respond('success', ['message' => 'Quiz updated successfully']);
} else {
    $stmt->close();
    respond('error', 'Failed to update quiz');
}

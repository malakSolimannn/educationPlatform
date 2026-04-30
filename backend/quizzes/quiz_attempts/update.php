<?php

require_once '../../config.php';

validateRequestMethod('POST');
$auth = requireAuth(['super_admin', 'admin']);
$input = requireParams(['id']);
$attemptId = (int)$input['id'];

$stmt = $conn->prepare("SELECT * FROM quiz_attempts WHERE id = ?");
$stmt->bind_param("i", $attemptId);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

if ($result->num_rows === 0) {
    respond('error', 'Quiz attempt not found');
}

$updates = [];
$params = [];
$types = '';

$allowedStatuses = ['in_progress', 'submitted', 'pending_review', 'graded'];

if (isset($input['score'])) {
    $updates[] = 'score = ?';
    $params[] = $input['score'] !== '' ? (float)$input['score'] : null;
    $types .= 'd';
}

if (isset($input['status']) && in_array($input['status'], $allowedStatuses)) {
    $updates[] = 'status = ?';
    $params[] = $input['status'];
    $types .= 's';
}

if (empty($updates)) {
    respond('error', 'No fields to update');
}

$params[] = $attemptId;
$types .= 'i';

$sql = "UPDATE quiz_attempts SET " . implode(', ', $updates) . " WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    $stmt->close();
    logAction($auth['id'], 'update_quiz_attempt', 'quiz_attempt', $attemptId, json_encode([
        'score' => isset($input['score']) ? $input['score'] : null,
        'status' => isset($input['status']) ? $input['status'] : null
    ]));
    respond('success', ['message' => 'Quiz attempt updated successfully']);
} else {
    $stmt->close();
    respond('error', 'Failed to update quiz attempt');
}

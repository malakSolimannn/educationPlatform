<?php

require_once '../../config.php';

validateRequestMethod('POST');
$auth = requireAuth(['super_admin', 'admin']);
$input = requireParams(['id']);
$optionId = (int)$input['id'];

$stmt = $conn->prepare("SELECT * FROM quiz_options WHERE id = ?");
$stmt->bind_param("i", $optionId);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

if ($result->num_rows === 0) {
    respond('error', 'Option not found');
}

$updates = [];
$params = [];
$types = '';

if (isset($input['option_text']) && $input['option_text'] !== '') {
    $updates[] = 'option_text = ?';
    $params[] = trim($input['option_text']);
    $types .= 's';
}

if (isset($input['is_correct'])) {
    $updates[] = 'is_correct = ?';
    $params[] = (int)$input['is_correct'];
    $types .= 'i';
}

if (empty($updates)) {
    respond('error', 'No fields to update');
}

$params[] = $optionId;
$types .= 'i';

$sql = "UPDATE quiz_options SET " . implode(', ', $updates) . " WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    $stmt->close();
    logAction($auth['id'], 'update_quiz_option', 'quiz_option', $optionId, "Updated option ID: $optionId");
    respond('success', ['message' => 'Option updated successfully']);
} else {
    $stmt->close();
    respond('error', 'Failed to update option');
}

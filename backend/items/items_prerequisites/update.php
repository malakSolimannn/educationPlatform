<?php

require_once '../../config.php';

validateRequestMethod('PUT');
$auth = requireAuth(['super_admin', 'admin']);
$input = requireParams(['id']);
$id = (int)$input['id'];

$stmt = $conn->prepare("SELECT * FROM item_prerequisites WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

if ($result->num_rows === 0) {
    respond('error', 'Prerequisite not found');
}

$old = $result->fetch_assoc();

$itemId = isset($input['item_id']) && $input['item_id'] !== ''
    ? (int)$input['item_id']
    : (int)$old['item_id'];

$requirementType = isset($input['requirement_type']) && $input['requirement_type'] !== ''
    ? trim($input['requirement_type'])
    : $old['requirement_type'];

$prerequisiteItemId = isset($input['prerequisite_item_id']) && $input['prerequisite_item_id'] !== ''
    ? (int)$input['prerequisite_item_id']
    : null;

$prerequisiteQuizId = isset($input['prerequisite_quiz_id']) && $input['prerequisite_quiz_id'] !== ''
    ? (int)$input['prerequisite_quiz_id']
    : null;

$requiredScore = isset($input['required_score']) && $input['required_score'] !== ''
    ? (float)$input['required_score']
    : null;

if (!isset($input['prerequisite_item_id'])) {
    $prerequisiteItemId = $old['prerequisite_item_id'] !== null
        ? (int)$old['prerequisite_item_id']
        : null;
}

if (!isset($input['prerequisite_quiz_id'])) {
    $prerequisiteQuizId = $old['prerequisite_quiz_id'] !== null
        ? (int)$old['prerequisite_quiz_id']
        : null;
}

if (!isset($input['required_score'])) {
    $requiredScore = $old['required_score'] !== null
        ? (float)$old['required_score']
        : null;
}

$allowedTypes = ['lesson_completed', 'quiz_passed'];

if (!in_array($requirementType, $allowedTypes)) {
    respond('error', 'Invalid requirement type');
}

$stmt = $conn->prepare("SELECT id FROM items WHERE id = ?");
$stmt->bind_param("i", $itemId);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

if ($result->num_rows === 0) {
    respond('error', 'Item not found');
}

if ($requirementType === 'lesson_completed') {
    if ($prerequisiteItemId === null) {
        respond('error', 'prerequisite_item_id is required');
    }

    if ($prerequisiteItemId === $itemId) {
        respond('error', 'Item cannot require itself');
    }

    if ($prerequisiteQuizId !== null || $requiredScore !== null) {
        respond('error', 'Quiz fields are not allowed for lesson_completed');
    }

    $stmt = $conn->prepare("SELECT id FROM items WHERE id = ?");
    $stmt->bind_param("i", $prerequisiteItemId);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    if ($result->num_rows === 0) {
        respond('error', 'Prerequisite item not found');
    }
}

if ($requirementType === 'quiz_passed') {
    if ($prerequisiteQuizId === null) {
        respond('error', 'prerequisite_quiz_id is required');
    }

    if ($prerequisiteItemId !== null) {
        respond('error', 'prerequisite_item_id is not allowed for quiz_passed');
    }

    if ($requiredScore === null) {
        respond('error', 'required_score is required for quiz_passed');
    }

    if ($requiredScore < 0 || $requiredScore > 100) {
        respond('error', 'required_score must be between 0 and 100');
    }

    $stmt = $conn->prepare("SELECT id FROM quizzes WHERE id = ?");
    $stmt->bind_param("i", $prerequisiteQuizId);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    if ($result->num_rows === 0) {
        respond('error', 'Prerequisite quiz not found');
    }
}

$stmt = $conn->prepare("
    UPDATE item_prerequisites
    SET
        item_id = ?,
        prerequisite_item_id = ?,
        prerequisite_quiz_id = ?,
        required_score = ?,
        requirement_type = ?
    WHERE id = ?
");

$stmt->bind_param(
    "iiidsi",
    $itemId,
    $prerequisiteItemId,
    $prerequisiteQuizId,
    $requiredScore,
    $requirementType,
    $id
);

if ($stmt->execute()) {
    $stmt->close();

    logAction(
        $auth['id'],
        'update_item_prerequisite',
        'item_prerequisite',
        $id,
        "Updated prerequisite ID: $id"
    );

    respond('success', [
        'id' => $id,
        'item_id' => $itemId,
        'prerequisite_item_id' => $prerequisiteItemId,
        'prerequisite_quiz_id' => $prerequisiteQuizId,
        'required_score' => $requiredScore,
        'requirement_type' => $requirementType
    ]);
} else {
    $stmt->close();
    respond('error', 'Failed to update prerequisite');
}
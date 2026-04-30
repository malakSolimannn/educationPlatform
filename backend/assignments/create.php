<?php

require_once '../config.php';
require_once '../helpers/upload_file.php';

validateRequestMethod('POST');
$auth = requireAuth(['super_admin', 'admin']);
$input = requireParams(['title', 'due_date']);

$title = trim($input['title']);
$description = isset($input['description']) ? trim($input['description']) : null;
$dueDate = trim($input['due_date']);
$itemId = isset($input['item_id']) && $input['item_id'] !== '' ? (int)$input['item_id'] : null;

if (!strtotime($dueDate)) {
    respond('error', 'Invalid due date format');
}

$parsedDate = date('Y-m-d H:i:s', strtotime($dueDate));

if (strtotime($parsedDate) <= time()) {
    respond('error', 'Due date must be in the future');
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

$filePath = null;
if (isset($_FILES['file'])) {
    $filePath = uploadFile('file', 'assignments');
}

$stmt = $conn->prepare("
    INSERT INTO assignments (
        item_id,
        title,
        description,
        due_date,
        file_path
    ) VALUES (?, ?, ?, ?, ?)
");

$stmt->bind_param(
    "issss",
    $itemId,
    $title,
    $description,
    $parsedDate,
    $filePath
);

if (!$stmt->execute()) {
    $stmt->close();
    respond('error', 'Failed to create assignment');
}

$assignmentId = $stmt->insert_id;
$stmt->close();

logAction($auth['id'], 'create_assignment', 'assignment', $assignmentId, json_encode([
    'title' => $title,
    'item_id' => $itemId,
    'due_date' => $parsedDate
]));

respond('success', [
    'id' => $assignmentId,
    'item_id' => $itemId,
    'title' => $title,
    'description' => $description,
    'due_date' => $parsedDate,
    'file_path' => $filePath
]);

<?php

require_once '../config.php';
require_once '../helpers/upload_file.php';

validateRequestMethod('POST');
$auth = requireAuth(['super_admin', 'admin']);
$input = requireParams(['id']);
$assignmentId = (int)$input['id'];

$stmt = $conn->prepare("SELECT * FROM assignments WHERE id = ?");
$stmt->bind_param("i", $assignmentId);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

if ($result->num_rows === 0) {
    respond('error', 'Assignment not found');
}

$updates = [];
$params = [];
$types = '';

if (isset($input['title']) && $input['title'] !== '') {
    $updates[] = 'title = ?';
    $params[] = trim($input['title']);
    $types .= 's';
}

if (isset($input['description'])) {
    $updates[] = 'description = ?';
    $params[] = $input['description'] !== '' ? trim($input['description']) : null;
    $types .= 's';
}

if (isset($input['due_date']) && $input['due_date'] !== '') {
    $dueDate = trim($input['due_date']);
    
    if (!strtotime($dueDate)) {
        respond('error', 'Invalid due date format');
    }
    
    $parsedDate = date('Y-m-d H:i:s', strtotime($dueDate));
    
    $updates[] = 'due_date = ?';
    $params[] = $parsedDate;
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

if (isset($_FILES['file'])) {
    $filePath = uploadFile('file', 'assignments');
    $updates[] = 'file_path = ?';
    $params[] = $filePath;
    $types .= 's';
}

if (empty($updates)) {
    respond('error', 'No fields to update');
}

$params[] = $assignmentId;
$types .= 'i';

$sql = "UPDATE assignments SET " . implode(', ', $updates) . " WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    $stmt->close();
    logAction($auth['id'], 'update_assignment', 'assignment', $assignmentId, "Updated assignment ID: $assignmentId");
    respond('success', ['message' => 'Assignment updated successfully']);
} else {
    $stmt->close();
    respond('error', 'Failed to update assignment');
}

<?php

require_once '../config.php';

validateRequestMethod('DELETE');
$auth = requireAuth(['super_admin', 'admin']);
$input = requireParams(['id']);
$itemId = (int)$input['id'];

$stmt = $conn->prepare("SELECT id FROM items WHERE id = ?");
$stmt->bind_param("i", $itemId);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

if ($result->num_rows === 0) {
    respond('error', 'Item not found');
}

// Delete related records (item_access_map, student_access, lesson_progress, quizzes, etc.)
$tables = [
    'item_access_map' => 'item_id',
    'student_access' => 'item_id',
    'lesson_progress' => 'item_id',
    'quizzes' => 'item_id',
    'assignments' => 'item_id',
    'transactions' => 'item_id',
    'items_prerequisites' => 'item_id',
];
foreach ($tables as $table => $col) {
    $stmt = $conn->prepare("DELETE FROM $table WHERE $col = ?");
    $stmt->bind_param("i", $itemId);
    $stmt->execute();
    $stmt->close();
}

$stmt = $conn->prepare("DELETE FROM items WHERE id = ?");
$stmt->bind_param("i", $itemId);
if ($stmt->execute()) {
    $stmt->close();
    logAction($auth['id'], 'delete_item', 'item', $itemId, "Deleted item ID: $itemId");
    respond('success', ['message' => 'Item deleted successfully']);
} else {
    $stmt->close();
    respond('error', 'Failed to delete item');
}

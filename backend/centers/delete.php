<?php

require_once '../config.php';

validateRequestMethod('DELETE');
$auth = requireAuth(['super_admin', 'admin']);
$input = requireParams(['id']);
$centerId = (int)$input['id'];

$stmt = $conn->prepare("SELECT id FROM centers WHERE id = ?");
$stmt->bind_param("i", $centerId);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

if ($result->num_rows === 0) {
    respond('error', 'Center not found');
}

$tables = [
    'code_batches' => 'center_id'
];

foreach ($tables as $table => $col) {
    $stmt = $conn->prepare("DELETE FROM $table WHERE $col = ?");
    $stmt->bind_param("i", $centerId);
    $stmt->execute();
    $stmt->close();
}

$stmt = $conn->prepare("DELETE FROM centers WHERE id = ?");
$stmt->bind_param("i", $centerId);
if ($stmt->execute()) {
    $stmt->close();
    logAction($auth['id'], 'delete_center', 'center', $centerId, "Deleted center ID: $centerId");
    respond('success', ['message' => 'Center deleted successfully']);
} else {
    $stmt->close();
    respond('error', 'Failed to delete center');
}

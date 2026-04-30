<?php

require_once '../config.php';

validateRequestMethod('DELETE');

$auth = requireAuth(['super_admin', 'admin']);
$input = requireParams(['id']);
$id = (int)$input['id'];

$stmt = $conn->prepare("SELECT id FROM item_access_map WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

if ($result->num_rows === 0) {
    respond('error', 'Mapping not found');
}

$stmt = $conn->prepare("DELETE FROM item_access_map WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    $stmt->close();

    logAction(
        $auth['id'],
        'delete_item_access_map',
        'item_access_map',
        $id,
        "Deleted mapping ID: $id"
    );

    respond('success', [
        'message' => 'Mapping deleted successfully',
        'id' => $id
    ]);
} else {
    $stmt->close();
    respond('error', 'Failed to delete mapping');
}
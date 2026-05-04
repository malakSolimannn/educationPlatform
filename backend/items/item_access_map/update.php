<?php

require_once '../../config.php';

validateRequestMethod('PUT');
$auth = requireAuth(['super_admin', 'admin']);
$input = requireParams(['id']);
$id = (int)$input['id'];

$stmt = $conn->prepare("SELECT * FROM item_access_map WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

if ($result->num_rows === 0) {
    respond('error', 'Mapping not found');
}

$oldMapping = $result->fetch_assoc();

$newItemId = isset($input['item_id'])
    ? (int)$input['item_id']
    : (int)$oldMapping['item_id'];

$newGrantsItemId = isset($input['grants_item_id'])
    ? (int)$input['grants_item_id']
    : (int)$oldMapping['grants_item_id'];

if ($newItemId === $newGrantsItemId) {
    respond('error', 'Item cannot grant access to itself');
}

foreach ([$newItemId, $newGrantsItemId] as $checkItemId) {
    $stmt = $conn->prepare("SELECT id FROM items WHERE id = ?");
    $stmt->bind_param("i", $checkItemId);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    if ($result->num_rows === 0) {
        respond('error', "Invalid item ID: $checkItemId");
    }
}

$stmt = $conn->prepare("
    UPDATE item_access_map
    SET item_id = ?, grants_item_id = ?
    WHERE id = ?
");

$stmt->bind_param("iii", $newItemId, $newGrantsItemId, $id);

if ($stmt->execute()) {
    $stmt->close();

    logAction($auth['id'], 'update_item_access_map', 'item_access_map', $id, "Updated mapping");

    respond('success', [
        'message' => 'Mapping updated successfully',
        'id' => $id,
        'item_id' => $newItemId,
        'grants_item_id' => $newGrantsItemId
    ]);
} else {
    $stmt->close();
    respond('error', 'Failed to update mapping');
}
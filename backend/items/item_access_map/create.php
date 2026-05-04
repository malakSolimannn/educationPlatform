<?php

require_once '../../config.php';

validateRequestMethod('POST');
$auth= requireAuth(['super_admin', 'admin']);
$input = requireParams(['item_id', 'grants_item_id']);

$itemId = (int)$input['item_id'];
$grants = is_array($input['grants_item_id']) ? $input['grants_item_id'] : [$input['grants_item_id']];

$stmt = $conn->prepare("SELECT id FROM items WHERE id = ?");
$stmt->bind_param("i", $itemId);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();
if ($result->num_rows === 0) {
    respond('error', 'Item not found');
}

$inserted = [];
foreach ($grants as $grantsItemId) {
    $grantsItemId = (int)$grantsItemId;

    if ($grantsItemId === $itemId) {
        $skipped[] = $grantsItemId;
        continue;
    }
    $stmt = $conn->prepare("SELECT id FROM items WHERE id = ?");
    $stmt->bind_param("i", $grantsItemId);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    if ($result->num_rows === 0) {
        continue; 
    }
    $stmt = $conn->prepare("INSERT IGNORE INTO item_access_map (item_id, grants_item_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $itemId, $grantsItemId);
    if ($stmt->execute()) {
        $inserted[] = $grantsItemId;
    }
    $stmt->close();
}

logAction($auth['id'], 'create_item_access_map', 'item_access_map', $itemId, "Granted items: " . implode(',', $inserted));
respond('success', ['item_id' => $itemId, 'granted' => $inserted]);

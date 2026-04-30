<?php

require_once '../config.php';

validateRequestMethod('POST');
$auth = requireAuth(['super_admin', 'admin']);

$input = requireParams(['id']);
$id = (int)$input['id'];

$status = isset($input['status']) ? trim($input['status']) : null;
$notes = isset($input['notes']) ? trim($input['notes']) : null;

if (!$status && !$notes) {
    respond('error', 'Nothing to update');
}

$updates = [];
$params = [];
$types = '';

if ($status) {
    $updates[] = "status = ?";
    $params[] = $status;
    $types .= 's';
}

if ($notes !== null) {
    $updates[] = "notes = ?";
    $params[] = $notes;
    $types .= 's';
}

$params[] = $id;
$types .= 'i';

$sql = "UPDATE payments SET " . implode(', ', $updates) . " WHERE id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();

$stmt->close();

logAction($auth['id'], 'update_payment', 'payment', $id, json_encode([
    'status' => $status,
    'notes' => $notes
]));

respond('success', 'Payment updated successfully');
<?php

require_once '../config.php';

validateRequestMethod('POST');
$auth = requireAuth(['super_admin', 'admin']);
$input = requireParams(['name']);

$name = trim($input['name']);
$phone = isset($input['phone']) ? trim($input['phone']) : null;
$address = isset($input['address']) ? trim($input['address']) : null;
$status = isset($input['status']) ? trim($input['status']) : 'active';

$allowedStatuses = ['active', 'inactive'];

if (!in_array($status, $allowedStatuses)) {
    respond('error', 'Invalid status value');
}

$stmt = $conn->prepare("
    INSERT INTO centers (
        name,
        phone,
        address,
        status
    ) VALUES (?, ?, ?, ?)
");

$stmt->bind_param(
    "ssss",
    $name,
    $phone,
    $address,
    $status
);

if (!$stmt->execute()) {
    $stmt->close();
    respond('error', 'Failed to create center');
}

$centerId = $stmt->insert_id;
$stmt->close();

logAction($auth['id'], 'create_center', 'center', $centerId, "Created center: $name");

respond('success', [
    'id' => $centerId,
    'name' => $name,
    'phone' => $phone,
    'address' => $address,
    'status' => $status
]);

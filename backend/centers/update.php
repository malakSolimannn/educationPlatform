<?php

require_once '../config.php';

validateRequestMethod('POST');
$auth = requireAuth(['super_admin', 'admin']);
$input = requireParams(['id']);
$centerId = (int)$input['id'];

$stmt = $conn->prepare("SELECT * FROM centers WHERE id = ?");
$stmt->bind_param("i", $centerId);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

if ($result->num_rows === 0) {
    respond('error', 'Center not found');
}

$updates = [];
$params = [];
$types = '';

$allowedStatuses = ['active', 'inactive'];

if (isset($input['name']) && $input['name'] !== '') {
    $updates[] = 'name = ?';
    $params[] = trim($input['name']);
    $types .= 's';
}

if (isset($input['phone'])) {
    $updates[] = 'phone = ?';
    $params[] = $input['phone'] !== '' ? trim($input['phone']) : null;
    $types .= 's';
}

if (isset($input['address'])) {
    $updates[] = 'address = ?';
    $params[] = $input['address'] !== '' ? trim($input['address']) : null;
    $types .= 's';
}

if (isset($input['status']) && in_array($input['status'], $allowedStatuses)) {
    $updates[] = 'status = ?';
    $params[] = $input['status'];
    $types .= 's';
}

if (empty($updates)) {
    respond('error', 'No fields to update');
}

$params[] = $centerId;
$types .= 'i';

$sql = "UPDATE centers SET " . implode(', ', $updates) . " WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    $stmt->close();
    logAction($auth['id'], 'update_center', 'center', $centerId, "Updated center ID: $centerId");
    respond('success', ['message' => 'Center updated successfully']);
} else {
    $stmt->close();
    respond('error', 'Failed to update center');
}

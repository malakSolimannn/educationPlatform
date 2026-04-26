<?php

require_once '../config.php';

validateRequestMethod('PUT');
$auth = requireAuth(['super_admin', 'admin']);
$input = requireParams(['id']);

$adminId = (int)$input['id'];

$stmt = $conn->prepare("SELECT id, role FROM admins WHERE id = ?");
$stmt->bind_param("i", $adminId);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

if ($result->num_rows === 0) {
    respond('error', 'Admin not found');
}

$targetAdmin = $result->fetch_assoc();

if ($auth['auth_type'] === 'admin' && $targetAdmin['role'] === 'super_admin') {
    respond('error', 'Admin cannot modify super_admin');
}

$updates = [];
$params = [];
$types = '';

if (isset($input['name']) && $input['name'] !== '') {
    $updates[] = 'name = ?';
    $params[] = $input['name'];
    $types .= 's';
}

if (isset($input['email']) && $input['email'] !== '') {
    $stmt = $conn->prepare("SELECT id FROM admins WHERE email = ? AND id != ?");
    $stmt->bind_param("si", $input['email'], $adminId);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    if ($result->num_rows > 0) {
        respond('error', 'Email already exists');
    }

    $updates[] = 'email = ?';
    $params[] = $input['email'];
    $types .= 's';
}

if (isset($input['password']) && $input['password'] !== '') {
    $updates[] = 'password = ?';
    $params[] = password_hash($input['password'], PASSWORD_DEFAULT);
    $types .= 's';
}

if (isset($input['role']) && $input['role'] !== '') {
    if ($auth['auth_type'] === 'admin' && $input['role'] === 'super_admin') {
        respond('error', 'Admin cannot change role to super_admin');
    }

    $allowedRoles = ['super_admin', 'admin', 'assistant'];
    if (!in_array($input['role'], $allowedRoles)) {
        respond('error', 'Invalid role');
    }

    $updates[] = 'role = ?';
    $params[] = $input['role'];
    $types .= 's';
}

if (isset($input['status']) && $input['status'] !== '') {
    $allowedStatuses = ['active', 'inactive'];
    if (!in_array($input['status'], $allowedStatuses)) {
        respond('error', 'Invalid status');
    }

    $updates[] = 'status = ?';
    $params[] = $input['status'];
    $types .= 's';
}

if (empty($updates)) {
    respond('error', 'No fields to update');
}

$params[] = $adminId;
$types .= 'i';

$sql = "UPDATE admins SET " . implode(', ', $updates) . " WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    $stmt->close();
    logAction($auth['id'], 'update_admin', 'admin', $adminId, "Updated admin ID: $adminId");
    respond('success', ['message' => 'Admin updated successfully']);
} else {
    $stmt->close();
    respond('error', 'Failed to update admin');
}
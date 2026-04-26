<?php

require_once '../config.php';

validateRequestMethod('DELETE');
$auth = requireAuth(['super_admin', 'admin']);
$input = requireParams(['id']);

$adminId = (int)$input['id'];

if ($adminId === $auth['id']) {
    respond('error', 'Cannot delete your own account');
}

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
    respond('error', 'Admin cannot delete super_admin');
}

$stmt = $conn->prepare("DELETE FROM admins WHERE id = ?");
$stmt->bind_param("i", $adminId);

if ($stmt->execute()) {
    $stmt->close();
    $stmt = $conn->prepare("DELETE FROM admins_sessions WHERE admin_id = ?");
    $stmt->bind_param("i", $adminId);
    $stmt->execute();
    $stmt->close();

    logAction($auth['id'], 'delete_admin', 'admin', $adminId, "Deleted admin ID: $adminId");

    respond('success', ['message' => 'Admin deleted successfully']);
} else {
    $stmt->close();
    respond('error', 'Failed to delete admin');
}
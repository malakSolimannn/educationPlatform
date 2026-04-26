<?php

require_once '../config.php';

validateRequestMethod('GET');
requireAuth(['super_admin', 'admin']);

$adminId = isset($_GET['id']) ? (int)$_GET['id'] : null;

if ($adminId) {
    $stmt = $conn->prepare("SELECT id, name, email, role, status, created_at FROM admins WHERE id = ?");
    $stmt->bind_param("i", $adminId);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    if ($result->num_rows === 0) {
        respond('error', 'Admin not found');
    }

    $admin = $result->fetch_assoc();
    respond('success', $admin);
} else {
    $stmt = $conn->prepare("SELECT id, name, email, role, status, created_at FROM admins ORDER BY id DESC");
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    $admins = [];
    while ($row = $result->fetch_assoc()) {
        $admins[] = $row;
    }

    respond('success', $admins);
}
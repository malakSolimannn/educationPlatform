<?php

require_once '../config.php';

validateRequestMethod('POST');
$auth = requireAuth(['super_admin', 'admin']);
$input = requireParams(['name', 'email', 'password', 'role']);

$name = $input['name'];
$email = $input['email'];
$password = $input['password'];
$role = $input['role'];

if ($auth['auth_type'] === 'admin' && $role === 'super_admin') {
    respond('error', 'Admin cannot create super_admin');
}

$allowedRoles = ['super_admin', 'admin', 'assistant'];
if (!in_array($role, $allowedRoles)) {
    respond('error', 'Invalid role.');
}

$stmt = $conn->prepare("SELECT id FROM admins WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

if ($result->num_rows > 0) {
    respond('error', 'Email already exists');
}

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("INSERT INTO admins (name, email, password, role) VALUES (?, ?, ?, ?)");
$stmt->bind_param("ssss", $name, $email, $hashedPassword, $role);

if (!$stmt->execute()) {
    $stmt->close();
    respond('error', 'Failed to create admin');}


    $adminId = $stmt->insert_id;
    $stmt->close();
    logAction($auth['id'], 'create_admin', 'admin', $adminId, "Created admin: $email with role: $role");

    respond('success', [
        'id' => $adminId,
        'name' => $name,
        'email' => $email,
        'role' => $role
    ]);

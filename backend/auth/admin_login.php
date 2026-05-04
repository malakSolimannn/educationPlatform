<?php

require_once '../config.php';

validateRequestMethod('POST');
$input = requireParams(['email', 'password']);

$email = $input['email'];
$password = $input['password'];

$stmt = $conn->prepare("SELECT id, name, email, password, role, status FROM admins WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

if ($result->num_rows === 0) {
    respond('error', 'Invalid credentials');
}

$admin = $result->fetch_assoc();

if ($admin['status'] !== 'active') {
    respond('error', 'Account is inactive');
}

if (!password_verify($password, $admin['password'])) {
    respond('error', 'Invalid credentials');
}

$token = bin2hex(random_bytes(32));
$expiresAt = date('Y-m-d H:i:s', strtotime('+7 days'));

$stmt = $conn->prepare("INSERT INTO admins_sessions (admin_id, token, expires_at) VALUES (?, ?, ?)");
$stmt->bind_param("iss", $admin['id'], $token, $expiresAt);
$stmt->execute();
$stmt->close();

logAction($admin['id'], 'login', 'admin', $admin['id'], 'Admin logged in');

respond('success', [
    'token' => $token,
    'expires_at' => $expiresAt,
    'admin' => [
        'id' => $admin['id'],
        'name' => $admin['name'],
        'email' => $admin['email'],
        'role' => $admin['role']
    ]
]);
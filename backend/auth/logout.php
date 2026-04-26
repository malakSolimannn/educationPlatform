<?php

require_once '../config.php';

validateRequestMethod('POST');
$auth = requireAuth();

$token = getToken();

if (!$token) {
    respond('error', 'Authorization token is required');
}
    $stmt = $conn->prepare("UPDATE admins_sessions SET status = 'revoked' WHERE token = ? AND admin_id = ?");
    $stmt->bind_param("si", $token, $auth['id']);
    $stmt->execute();
    $stmt->close();

    logAction($auth['id'], 'logout', 'admin', $auth['id'], 'Admin logged out');

respond('success', ['message' => 'Logged out successfully']);
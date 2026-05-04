<?php

require_once '../config.php';

validateRequestMethod('POST');
$auth = requireAuth('student');
$token = getToken();

if (!$token) {
    respond('error', 'Authorization token is required');
}

$stmt = $conn->prepare("
    UPDATE student_sessions
    SET status = 'revoked'
    WHERE token = ?
    AND student_id = ?
    AND status = 'active'
");

$stmt->bind_param("si", $auth['token'], $auth['id']);
$stmt->execute();
$stmt->close();

respond('success', 'Logged out successfully');
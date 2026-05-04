<?php

require_once '../config.php';

validateRequestMethod('POST');

$input = requireParams(['email', 'password']);

$email = trim($input['email']);
$password = $input['password'];

$stmt = $conn->prepare("
    SELECT id, full_name, phone, email, password, grade_id, school_name, wallet_balance, status
    FROM students
    WHERE email = ?
    LIMIT 1
");

$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();
$stmt->close();

if (!$student) {
    respond('error', 'Invalid login credentials');
}

if ($student['status'] !== 'active') {
    respond('error', 'Your account is inactive');
}

if (!password_verify($password, $student['password'])) {
    respond('error', 'Invalid login credentials');
}

$token = bin2hex(random_bytes(32));
$expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));

$stmt = $conn->prepare("
    INSERT INTO student_sessions
    (student_id, token, expires_at)
    VALUES (?, ?, ?)
");

$stmt->bind_param("iss", $student['id'], $token, $expiresAt);
$stmt->execute();
$stmt->close();

unset($student['password']);

respond('success', [
    'token' => $token,
    'student' => $student,
    'expires_at' => $expiresAt
]);
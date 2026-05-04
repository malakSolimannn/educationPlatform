<?php

require_once '../config.php';

validateRequestMethod('POST');

$input = getBody();

if (empty($input)) {
    $input = $_POST;
}

foreach (['full_name', 'phone', 'password'] as $field) {
    if (!isset($input[$field]) || (is_string($input[$field]) && trim($input[$field]) === '')) {
        respond('error', "Missing parameter: $field");
    }
}

$fullName = trim($input['full_name']);
$phone = trim($input['phone']);
$email = isset($input['email']) && trim($input['email']) !== '' ? trim($input['email']) : null;
$gradeId = isset($input['grade_id']) && $input['grade_id'] !== '' ? (int)$input['grade_id'] : null;
$schoolName = isset($input['school_name']) && trim($input['school_name']) !== '' ? trim($input['school_name']) : null;
$password = $input['password'];

$stmt = $conn->prepare("SELECT id FROM students WHERE phone = ? OR email <=> ?");
$stmt->bind_param("ss", $phone, $email);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

if ($result->num_rows > 0) {
    respond('error', 'Phone or email already exists');
}

if ($gradeId !== null) {
    $stmt = $conn->prepare("SELECT id FROM grades WHERE id = ?");
    $stmt->bind_param("i", $gradeId);
    $stmt->execute();
    $gradeResult = $stmt->get_result();
    $stmt->close();

    if ($gradeResult->num_rows === 0) {
        respond('error', 'Invalid grade');
    }
}

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("
    INSERT INTO students
    (full_name, phone, email, password, grade_id, school_name)
    VALUES (?, ?, ?, ?, ?, ?)
");
$stmt->bind_param(
    "ssssis",
    $fullName,
    $phone,
    $email,
    $hashedPassword,
    $gradeId,
    $schoolName
);

if (!$stmt->execute()) {
    $stmt->close();
    respond('error', 'Failed to register student');
}

$studentId = $stmt->insert_id;
$stmt->close();

$token = bin2hex(random_bytes(32));
$expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));

$stmt = $conn->prepare("
    INSERT INTO student_sessions
    (student_id, token, expires_at)
    VALUES (?, ?, ?)
");
$stmt->bind_param("iss", $studentId, $token, $expiresAt);
$stmt->execute();
$stmt->close();

respond('success', [
    'token' => $token,
    'student' => [
        'id' => $studentId,
        'full_name' => $fullName,
        'phone' => $phone,
        'email' => $email,
        'grade_id' => $gradeId,
        'school_name' => $schoolName,
        'wallet_balance' => 0,
        'status' => 'active'
    ],
    'expires_at' => $expiresAt,
    'message' => 'Student registered successfully'
]);

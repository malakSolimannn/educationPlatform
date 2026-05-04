<?php

require_once '../config.php';

validateRequestMethod('POST');

$input = requireParams(['full_name', 'phone', 'password']);

$fullName = trim($input['full_name']);
$phone = trim($input['phone']);
$email = isset($input['email']) ? trim($input['email']) : null;
$gradeId = isset($input['grade_id']) ? (int)$input['grade_id'] : null;
$schoolName = isset($input['school_name']) ? trim($input['school_name']) : null;
$password = $input['password'];

$stmt = $conn->prepare("SELECT id FROM students WHERE phone = ? OR email = ?");
$stmt->bind_param("ss", $phone, $email);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

if ($result->num_rows > 0) {
    respond('error', 'Phone or email already exists');
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

$stmt->execute();
$studentId = $stmt->insert_id;
$stmt->close();

respond('success', [
    'id' => $studentId,
    'message' => 'Student registered successfully'
]);
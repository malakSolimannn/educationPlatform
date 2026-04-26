<?php

require_once '../config.php';

validateRequestMethod('POST');
$auth = requireAuth(['super_admin', 'admin', 'assistant']);
$input = requireParams(['full_name', 'phone','email', 'password']);

$fullName = trim($input['full_name']);
$phone = trim($input['phone']);
$email = trim($input['email']) ;
$password = $input['password'];
$gradeId = isset($input['grade_id']) ? intval($input['grade_id']) : null;
$schoolName = isset($input['school_name']) ? trim($input['school_name']) : null;

$stmt = $conn->prepare("SELECT id FROM students WHERE phone = ?");
$stmt->bind_param("s", $phone);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

if ($result->num_rows > 0) {
    respond('error', 'Phone number already exists');
}


$stmt = $conn->prepare("SELECT id FROM students WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

if ($result->num_rows > 0) {
    respond('error', 'Email already exists');
    }


$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("INSERT INTO students (full_name, phone, email, password, grade_id, school_name) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->bind_param("ssssss", $fullName, $phone, $email, $hashedPassword, $gradeId, $schoolName);

if ($stmt->execute()) {
    $studentId = $stmt->insert_id;
    $stmt->close();

    logAction($auth['id'], 'create_student', 'student', $studentId, "Created student: $phone");

    respond('success', [
        'id' => $studentId,
        'full_name' => $fullName,
        'phone' => $phone,
        'email' => $email,
        'grade_id' => $gradeId,
        'school_name' => $schoolName
    ]);
} else {
    $stmt->close();
    respond('error', 'Failed to create student');
}
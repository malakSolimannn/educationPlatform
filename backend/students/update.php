<?php

require_once '../config.php';

validateRequestMethod('PUT');

$auth = requireAuth(['super_admin', 'admin', 'student']);
$input = getBody();

if ($auth['auth_type'] === 'student') {
    $studentId = (int)$auth['id'];
} else {
    requireParams(['id']);
    $studentId = (int)$input['id'];
}
$stmt = $conn->prepare("SELECT id FROM students WHERE id = ?");
$stmt->bind_param("i", $studentId);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

if ($result->num_rows === 0) {
    respond('error', 'Student not found');
}

$updates = [];
$params = [];
$types = '';

if (isset($input['full_name']) && $input['full_name'] !== '') {
    $updates[] = 'full_name = ?';
    $params[] = trim($input['full_name']);
    $types .= 's';
}

if (isset($input['phone']) && $input['phone'] !== '') {
    $phone = trim($input['phone']);

    $stmt = $conn->prepare("SELECT id FROM students WHERE phone = ? AND id != ?");
    $stmt->bind_param("si", $phone, $studentId);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    if ($result->num_rows > 0) {
        respond('error', 'Phone number already exists');
    }

    $updates[] = 'phone = ?';
    $params[] = $phone;
    $types .= 's';
}

if (isset($input['email']) && $input['email'] !== '') {
    $email = trim($input['email']);
    $stmt = $conn->prepare("SELECT id FROM students WHERE email = ? AND id != ?");
    $stmt->bind_param("si", $email, $studentId);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    if ($result->num_rows > 0) {
        respond('error', 'Email already exists');
    }

    $updates[] = 'email = ?';
    $params[] = $email;
    $types .= 's';
}

if (isset($input['password']) && $input['password'] !== '') {
    $updates[] = 'password = ?';
    $params[] = password_hash($input['password'], PASSWORD_DEFAULT);
    $types .= 's';
}

if (isset($input['grade_id']) && $input['grade_id'] !== '') {
    $gradeId = (int)$input['grade_id'];

    $stmt = $conn->prepare("SELECT id FROM grades WHERE id = ?");
    $stmt->bind_param("i", $gradeId);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    if ($result->num_rows === 0) {
        respond('error', 'Invalid grade');
    }

    $updates[] = 'grade_id = ?';
    $params[] = $gradeId;
    $types .= 'i';
}

if (isset($input['school_name']) && $input['school_name'] !== '') {
    $updates[] = 'school_name = ?';
    $params[] = trim($input['school_name']);
    $types .= 's';
}

if ($auth['auth_type'] !== 'student') {
    if (isset($input['status']) && $input['status'] !== '') {
        $allowedStatuses = ['active', 'inactive'];

        if (!in_array($input['status'], $allowedStatuses)) {
            respond('error', 'Invalid status');
        }

        $updates[] = 'status = ?';
        $params[] = $input['status'];
        $types .= 's';
    }

    if (isset($input['wallet_balance'])) {
        $updates[] = 'wallet_balance = ?';
        $params[] = (float)$input['wallet_balance'];
        $types .= 'd';
    }
}

if (empty($updates)) {
    respond('error', 'No fields to update');
}

$params[] = $studentId;
$types .= 'i';

$sql = "UPDATE students SET " . implode(', ', $updates) . " WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    $stmt->close();

    if ($auth['auth_type'] === 'student') {
        respond('success', ['message' => 'Profile updated successfully']);
    }

    logAction($auth['id'], 'update_student', 'student', $studentId, "Updated student ID: $studentId");

    respond('success', ['message' => 'Student updated successfully']);
}

$stmt->close();
respond('error', 'Failed to update student');

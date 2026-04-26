<?php

require_once '../config.php';

validateRequestMethod('DELETE');
$auth= requireAuth(['super_admin', 'admin']);
$input = requireParams(['id']);

$studentId = (int)$input['id'];

$stmt = $conn->prepare("SELECT id FROM students WHERE id = ?");
$stmt->bind_param("i", $studentId);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

if ($result->num_rows === 0) {
    respond('error', 'Student not found');
}

// $tables = [
//     'student_sessions',
//     'student_access',
//     'lesson_progress',
//     'quiz_attempts',
//     'assignment_submissions',
//     'notifications',
//     'transactions'
// ];

// foreach ($tables as $table) {
//     $stmt = $conn->prepare("DELETE FROM $table WHERE student_id = ?");
//     $stmt->bind_param("i", $studentId);
//     $stmt->execute();
//     $stmt->close();
// }

$stmt = $conn->prepare("DELETE FROM students WHERE id = ?");
$stmt->bind_param("i", $studentId);

if ($stmt->execute()) {
    $stmt->close();

    logAction($auth['id'], 'delete_student', 'student', $studentId, "Deleted student ID: $studentId");

    respond('success', ['message' => 'Student deleted successfully']);
} else {
    $stmt->close();
    respond('error', 'Failed to delete student');
}
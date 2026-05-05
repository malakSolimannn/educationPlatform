<?php

require_once '../../config.php';
require_once '../../helpers/upload_file.php';
require_once '../../helpers/student_access.php';

validateRequestMethod('POST');

$auth = requireAuth('student');
$studentId = (int)$auth['id'];
$input= requireParams(['assignment_id']);
$assignmentId = isset($input['assignment_id']) ? (int)$input['assignment_id'] : 0;
$submissionText = isset($input['submission_text']) ? trim($input['submission_text']) : null;

$stmt = $conn->prepare("
    SELECT a.id, a.item_id, a.due_date
    FROM assignments a
    JOIN items i ON i.id = a.item_id
    WHERE a.id = ?
    AND i.is_published = 1
    LIMIT 1
");

$stmt->bind_param("i", $assignmentId);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

if ($result->num_rows === 0) {
    respond('error', 'Assignment not found');
}

$assignment = $result->fetch_assoc();
if (!studentCanOpenItem($studentId, (int)$assignment['item_id'])) {
    respond('error', 'You do not have access to this assignment');
}

$filePath = null;

if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
    $filePath = uploadFile('file', 'assignment_submissions');
}

if (!$submissionText && !$filePath) {
    respond('error', 'You must submit text or a file');
}

if ($assignment['due_date'] && strtotime($assignment['due_date']) < time()) {
    respond('error', 'Assignment deadline has passed');
}

$stmt = $conn->prepare("
    SELECT id
    FROM assignment_submissions
    WHERE assignment_id = ?
    AND student_id = ?
    LIMIT 1
");

$stmt->bind_param("ii", $assignmentId, $studentId);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($existing) {
    $stmt = $conn->prepare("
        UPDATE assignment_submissions
        SET 
            submission_text = ?,
            file_path = ?,
            submitted_at = NOW(),
            grade = NULL,
            feedback = NULL
        WHERE id = ?
    ");

    $stmt->bind_param("ssi", $submissionText, $filePath, $existing['id']);
} else {
    $stmt = $conn->prepare("
        INSERT INTO assignment_submissions (
            assignment_id,
            student_id,
            submission_text,
            file_path,
            submitted_at
        ) VALUES (?, ?, ?, ?, NOW())
    ");

    $stmt->bind_param("iiss", $assignmentId, $studentId, $submissionText, $filePath);
}

if (!$stmt->execute()) {
    $stmt->close();
    respond('error', 'Failed to submit assignment');
}

$stmt->close();

respond('success', [
    'assignment_id' => $assignmentId,
    'message' => 'Assignment submitted successfully'
]);
<?php

require_once '../../config.php';
require_once '../../helpers/view_download_file.php';

validateRequestMethod('GET');

$auth = requireAuth(['admins', 'student']);
$isStudent = $auth['auth_type'] === 'student';
$studentId = $isStudent ? (int)$auth['id'] : null;

$submissionId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$mode = isset($_GET['mode']) && $_GET['mode'] === 'download' ? 'download' : 'view';

if (!$submissionId) {
    respond('error', 'Submission id is required');
}

$stmt = $conn->prepare("
    SELECT file_path, student_id
    FROM assignment_submissions
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $submissionId);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

if ($result->num_rows === 0) {
    respond('error', 'Submission not found');
}

$submission = $result->fetch_assoc();

if (!$submission['file_path']) {
    respond('error', 'No file uploaded');
}


if ($isStudent) {
    if ((int)$submission['student_id'] !== $studentId) {
        respond('error', 'You can only access your own submission');
    }
}

if ($mode === 'download') {
    downloadFile($submission['file_path']);
} else {
    viewFile($submission['file_path']);
}
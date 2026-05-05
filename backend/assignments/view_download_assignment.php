<?php

require_once '../config.php';
require_once '../helpers/view_download_file.php';
require_once '../helpers/student_access.php';

validateRequestMethod('GET');

$auth = requireAuth(['admins', 'student']);
$isStudent = $auth['auth_type'] === 'student';
$studentId = $isStudent ? (int)$auth['id'] : null;

$assignmentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$mode = isset($_GET['mode']) && $_GET['mode'] === 'download' ? 'download' : 'view';

if (!$assignmentId) {
    respond('error', 'Assignment id is required');
}

$stmt = $conn->prepare("
    SELECT a.file_path, a.item_id
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

if (!$assignment['file_path']) {
    respond('error', 'Assignment has no file');
}


if ($isStudent) {
    if (!studentCanOpenItem($studentId, (int)$assignment['item_id'])) {
        respond('error', 'You do not have access to this assignment');
    }
}

if ($mode === 'download') {
    downloadFile($assignment['file_path']);
} else {
    viewFile($assignment['file_path']);
}
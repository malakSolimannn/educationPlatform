<?php

require_once '../config.php';

validateRequestMethod('POST');
$auth= requireAuth(['super_admin', 'admin']);
$input = requireParams(['title', 'message']);

$title = trim($input['title']);
$message = trim($input['message']);

$stmt = $conn->prepare("INSERT INTO announcements (title, message) VALUES (?, ?)");
$stmt->bind_param("ss", $title, $message);

if ($stmt->execute()) {
    $announcementId = $stmt->insert_id;
    $stmt->close();
    logAction($auth['id'], 'create_announcement', 'announcement', $announcementId, "Created announcement");
    respond('success', [
        'id' => $announcementId,
        'title' => $title,
        'message' => $message
    ]);
} else {
    $stmt->close();
    respond('error', 'Failed to create announcement');
}

<?php

require_once '../config.php';

validateRequestMethod('DELETE');
requireAuth(['super_admin', 'admin']);
$input = requireParams(['id']);

$id = (int)$input['id'];

$stmt = $conn->prepare("SELECT * FROM announcements WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();
if ($result->num_rows === 0) {
    respond('error', 'Announcement not found');
}

$stmt = $conn->prepare("DELETE FROM announcements WHERE id = ?");
$stmt->bind_param("i", $id);
if ($stmt->execute()) {
    $stmt->close();
    logAction($auth['id'], 'delete_announcement', 'announcement', $id, "Deleted announcement");
    respond('success', ['message' => 'Announcement deleted successfully']);
} else {
    $stmt->close();
    respond('error', 'Failed to delete announcement');
}

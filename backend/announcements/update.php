<?php

require_once '../config.php';

validateRequestMethod('PUT');
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

$updates = [];
$params = [];
$types = '';

if (isset($input['title']) && $input['title'] !== '') {
    $updates[] = 'title = ?';
    $params[] = $input['title'];
    $types .= 's';
}
if (isset($input['message']) && $input['message'] !== '') {
    $updates[] = 'message = ?';
    $params[] = $input['message'];
    $types .= 's';
}

if (empty($updates)) {
    respond('error', 'No fields to update');
}

$params[] = $id;
$types .= 'i';

$sql = "UPDATE announcements SET " . implode(', ', $updates) . " WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
if ($stmt->execute()) {
    $stmt->close();
    logAction($auth['id'], 'update_announcement', 'announcement', $id, "Updated announcement");
    respond('success', ['message' => 'Announcement updated successfully']);
} else {
    $stmt->close();
    respond('error', 'Failed to update announcement');
}

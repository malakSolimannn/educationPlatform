<?php

require_once '../config.php';
require_once '../helpers/upload_file.php';
require_once '../helpers/cloudflare_stream.php';
require_once '../helpers/upload_image.php';

validateRequestMethod('POST');
$auth = requireAuth(['super_admin', 'admin']);
$input = requireParams(['id']);
$itemId = (int)$input['id'];

$stmt = $conn->prepare("SELECT * FROM items WHERE id = ?");
$stmt->bind_param("i", $itemId);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

if ($result->num_rows === 0) {
    respond('error', 'Item not found');
}

$updates = [];
$params = [];
$types = '';

$allowedItemTypes = ['course', 'chapter', 'lesson', 'package', 'pass'];
$allowedContentTypes = ['video', 'pdf', 'none'];
$allowedAccessTypes = ['lifetime', 'limited'];

if (isset($input['item_type']) && in_array($input['item_type'], $allowedItemTypes)) {
    $updates[] = 'item_type = ?';
    $params[] = $input['item_type'];
    $types .= 's';
}
if (isset($input['title']) && $input['title'] !== '') {
    $updates[] = 'title = ?';
    $params[] = $input['title'];
    $types .= 's';
}
if (isset($input['description'])) {
    $updates[] = 'description = ?';
    $params[] = $input['description'];
    $types .= 's';
}
if (isset($input['parent_id'])) {

    if ($input['parent_id'] === '') {
        $updates[] = 'parent_id = NULL';
    } else {

        $parentId = (int)$input['parent_id'];

        if ($parentId === $itemId) {
            respond('error', 'Item cannot be its own parent');
        }

        $stmt = $conn->prepare("SELECT id FROM items WHERE id = ?");
        $stmt->bind_param("i", $parentId);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();

        if ($result->num_rows === 0) {
            respond('error', 'Invalid parent item');
        }

        $updates[] = 'parent_id = ?';
        $params[] = $parentId;
        $types .= 'i';
    }
}
if (isset($input['content_type']) && $input['content_type'] === 'none') {
    $updates[] = 'video_url = NULL';
    $updates[] = 'file_path = NULL';
}
if (isset($input['content_type']) && $input['content_type'] === 'video') {
    if (isset($_FILES['video'])) {
        $video = uploadVideoToCloudflare('video');
        $videoUrl = $video['uid'];
    } else {
        $videoUrl = isset($input['video_url']) ? $input['video_url'] : null;
    }

    if (!$videoUrl) {
        respond('error', 'Video is required');
    }

    $updates[] = 'video_url = ?';
    $params[] = $videoUrl;
    $types .= 's';

    $updates[] = 'file_path = NULL';
}
if (isset($input['content_type']) && $input['content_type'] === 'pdf') {
    if (isset($_FILES['file'])) {
        $filePath = uploadFile('file', 'lessons');
    } else {
        $filePath = isset($input['file_path']) ? $input['file_path'] : null;
    }

    if (!$filePath) {
        respond('error', 'PDF file is required');
    }

    $updates[] = 'file_path = ?';
    $params[] = $filePath;
    $types .= 's';

    $updates[] = 'video_url = NULL';
}
if (isset($input['price'])) {
    $updates[] = 'price = ?';
    $params[] = (float)$input['price'];
    $types .= 'd';
}
if (isset($input['duration_days'])) {
    $updates[] = 'duration_days = ?';
    $params[] = $input['duration_days'] !== '' ? (int)$input['duration_days'] : null;
    $types .= 'i';
}
if (isset($input['access_type']) && in_array($input['access_type'], $allowedAccessTypes)) {
    $updates[] = 'access_type = ?';
    $params[] = $input['access_type'];
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
}
if (isset($_FILES['image'])) {
    $imageUrl = uploadImage('image', 'items', 3);

    $updates[] = 'image_url = ?';
    $params[] = $imageUrl;
    $types .= 's';
}
if (isset($input['is_free'])) {
    $updates[] = 'is_free = ?';
    $params[] = (int)$input['is_free'];
    $types .= 'i';
}
if (isset($input['is_published'])) {
    $updates[] = 'is_published = ?';
    $params[] = (int)$input['is_published'];
    $types .= 'i';
}
if (isset($input['sort_order'])) {
    $updates[] = 'sort_order = ?';
    $params[] = (int)$input['sort_order'];
    $types .= 'i';
}

if (empty($updates)) {
    respond('error', 'No fields to update');
}

$params[] = $itemId;
$types .= 'i';

$sql = "UPDATE items SET " . implode(', ', $updates) . " WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    $stmt->close();
    logAction($auth['id'], 'update_item', 'item', $itemId, "Updated item ID: $itemId");
    respond('success', ['message' => 'Item updated successfully']);
} else {
    $stmt->close();
    respond('error', 'Failed to update item');
}

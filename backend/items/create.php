<?php

require_once '../config.php';
require_once '../helpers/upload_file.php';
require_once '../helpers/cloudflare_stream.php';

validateRequestMethod('POST');

$auth = requireAuth(['super_admin', 'admin']);
$input= requireParams(['item_type', 'title', 'grade_id']);

$itemType = trim($input['item_type']);
$title = trim($input['title']);
$description = isset($input['description']) ? trim($input['description']) : null;

$parentId = isset($input['parent_id']) && $input['parent_id'] !== ''
    ? intval($input['parent_id'])
    : null;

$contentType = isset($input['content_type']) ? trim($input['content_type']) : 'none';
$videoUrl = isset($input['video_url']) ? trim($input['video_url']) : null;

$price = isset($input['price']) ? floatval($input['price']) : 0.00;

$durationDays = isset($input['duration_days']) && $input['duration_days'] !== ''
    ? intval($input['duration_days'])
    : null;

$accessType = isset($input['access_type']) ? trim($input['access_type']) : 'lifetime';

$gradeId = $input['grade_id']; 

$isFree = isset($input['is_free']) ? intval($input['is_free']) : 0;
$isPublished = isset($input['is_published']) ? intval($input['is_published']) : 1;
$sortOrder = isset($input['sort_order']) ? intval($input['sort_order']) : 0;

$allowedItemTypes = ['course', 'chapter', 'lesson', 'package', 'pass'];
$allowedContentTypes = ['video', 'pdf', 'none'];
$allowedAccessTypes = ['lifetime', 'limited'];

if (!in_array($itemType, $allowedItemTypes)) {
    respond('error', 'Invalid item type');
}

if (!in_array($contentType, $allowedContentTypes)) {
    respond('error', 'Invalid content type');
}

if (!in_array($accessType, $allowedAccessTypes)) {
    respond('error', 'Invalid access type');
}

if ($accessType === 'limited' && $durationDays === null) {
    respond('error', 'Duration days is required for limited access');
}

if ($accessType === 'lifetime') {
    $durationDays = null;
}

if ($isFree === 1) {
    $price = 0.00;
}

if ($contentType !== 'video') {
    $videoUrl = null;
}


if ($parentId !== null) {
    $stmt = $conn->prepare("SELECT id FROM items WHERE id = ?");
    $stmt->bind_param("i", $parentId);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    if ($result->num_rows === 0) {
        respond('error', 'Invalid parent item');
    }
}

if ($gradeId !== null) {
    $stmt = $conn->prepare("SELECT id FROM grades WHERE id = ?");
    $stmt->bind_param("i", $gradeId);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    if ($result->num_rows === 0) {
        respond('error', 'Invalid grade');
    }
}

$filePath = null;
if ($contentType === 'pdf') {
    $filePath = uploadFile('file', 'lessons');
}
$videoUrl = null;

if ($contentType === 'video') {
    $video = uploadVideoToCloudflare('video');
    $videoUrl = $video['uid'];
}
$stmt = $conn->prepare("
    INSERT INTO items (
        parent_id,
        item_type,
        title,
        description,
        content_type,
        video_url,
        file_path,
        price,
        duration_days,
        access_type,
        grade_id,
        is_free,
        is_published,
        sort_order
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

if (!$stmt) {
    respond('error', 'Failed to prepare item creation query');
}

$stmt->bind_param(
    "issssssdisiiii",
    $parentId,
    $itemType,
    $title,
    $description,
    $contentType,
    $videoUrl,
    $filePath,
    $price,
    $durationDays,
    $accessType,
    $gradeId,
    $isFree,
    $isPublished,
    $sortOrder
);

if (!$stmt->execute()) {
    $stmt->close();
    respond('error', 'Failed to create item');
}

$itemId = $stmt->insert_id;
$stmt->close();

logAction($auth['id'], 'create_item', 'item', $itemId, "Created item: $title");


respond('success', [
    'id' => $itemId,
    'parent_id' => $parentId,
    'item_type' => $itemType,
    'title' => $title,
    'description' => $description,
    'content_type' => $contentType,
    'video_url' => $videoUrl,
    'file_path' => $filePath,
    'price' => $price,
    'duration_days' => $durationDays,
    'access_type' => $accessType,
    'grade_id' => $gradeId,
    'is_free' => $isFree,
    'is_published' => $isPublished,
    'sort_order' => $sortOrder]);
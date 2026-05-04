<?php

require_once '../config.php';
require_once '../helpers/student_access.php';

validateRequestMethod('GET');

$auth = requireAuth('student');
$studentId = (int)$auth['id'];

$itemId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$itemId) {
    respond('error', 'Item id is required');
}

$stmt = $conn->prepare("
    SELECT 
        id,
        parent_id,
        item_type,
        title,
        description,
        content_type,
        price,
        duration_days,
        access_type,
        grade_id,
        is_free,
        is_published,
        sort_order,
        created_at,
        image_url
    FROM items
    WHERE id = ?
    AND is_published = 1
    LIMIT 1
");

$stmt->bind_param("i", $itemId);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

if ($result->num_rows === 0) {
    respond('error', 'Item not found');
}

$item = $result->fetch_assoc();

$hasAccess = studentHasAccessToItem($studentId, $itemId);
$prerequisitesMet = studentMeetsItemPrerequisites($studentId, $itemId);
$canOpen = studentCanOpenItem($studentId, $itemId);
$statusMessage = null;
$lockReason = null;
$missingRequirements = [];

if (!$hasAccess) {
    $lockReason = 'purchase_required';
    $statusMessage = 'You need to purchase this item to access it.';
}

if ($hasAccess && !$prerequisitesMet) {
    $lockReason = 'prerequisites_not_met';
    $statusMessage = 'You must complete required lessons or quizzes first.';

    $stmt = $conn->prepare("
        SELECT *
        FROM item_prerequisites
        WHERE item_id = ?
    ");

    $stmt->bind_param("i", $itemId);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {

        if ($row['requirement_type'] === 'lesson_completed') {
            $requiredItemId = (int)$row['prerequisite_item_id'];

            $missingRequirements[] = [
                'type' => 'lesson_completed',
                'item_id' => $requiredItemId
            ];
        }

        if ($row['requirement_type'] === 'quiz_passed') {
            $requiredQuizId = (int)$row['prerequisite_quiz_id'];

            $missingRequirements[] = [
                'type' => 'quiz_passed',
                'quiz_id' => $requiredQuizId,
                'required_score' => $row['required_score']
            ];
        }
    }

    $stmt->close();
}

if ($canOpen) {
    $statusMessage = 'You have access to this item.';
}

$item['has_access'] = $hasAccess ? 1 : 0;
$item['prerequisites_met'] = $prerequisitesMet ? 1 : 0;
$item['can_open'] = $canOpen ? 1 : 0;
$item['lock_reason'] = $lockReason;
$item['status_message'] = $statusMessage;
$item['missing_requirements'] = $missingRequirements;

if ($canOpen) {
    $stmt = $conn->prepare("
        SELECT content_type, video_url, file_path
        FROM items
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->bind_param("i", $itemId);
    $stmt->execute();
    $contentResult = $stmt->get_result();
    $content = $contentResult->fetch_assoc();
    $stmt->close();

    $item['content'] = [
        'content_type' => $content['content_type'],
        'video_url' => $content['video_url'],
        'file_path' => $content['file_path']
    ];
} else {
    $item['content'] = null;
}

$stmt = $conn->prepare("
    SELECT 
        i.id,
        i.parent_id,
        i.item_type,
        i.title,
        i.description,
        i.content_type,
        i.price,
        i.duration_days,
        i.access_type,
        i.grade_id,
        i.is_free,
        i.is_published,
        i.sort_order,
        i.image_url
    FROM item_access_map iam
    JOIN items i ON i.id = iam.grants_item_id
    WHERE iam.item_id = ?
    AND i.is_published = 1
    ORDER BY i.sort_order ASC, i.id ASC
");

$stmt->bind_param("i", $itemId);
$stmt->execute();
$accessResult = $stmt->get_result();

$accessedItems = [];

while ($row = $accessResult->fetch_assoc()) {
    $accessedItems[] = $row;
}

$stmt->close();

$item['accessed_items'] = $accessedItems;

respond('success', $item);
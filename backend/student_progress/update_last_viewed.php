<?php

require_once '../config.php';
require_once '../helpers/student_access.php';

validateRequestMethod('POST');

$auth = requireAuth('student');
$studentId = (int)$auth['id'];
$input = requireParams(['item_id']);

$itemId = (int)$input['item_id'];

if ($itemId <= 0) {
    respond('error', 'Invalid item ID');
}

$stmt = $conn->prepare("
    SELECT id, item_type, is_published
    FROM items
    WHERE id = ?
    LIMIT 1
");
$stmt->bind_param("i", $itemId);
$stmt->execute();
$result = $stmt->get_result();
$item = $result->fetch_assoc();
$stmt->close();

if (!$item || (int)$item['is_published'] !== 1) {
    respond('error', 'Item not found');
}

if ($item['item_type'] !== 'lesson') {
    respond('error', 'Only lessons can update lesson progress');
}

if (!studentCanOpenItem($studentId, $itemId)) {
    respond('error', 'You do not have access to this lesson');
}

$stmt = $conn->prepare("
    INSERT INTO lesson_progress (
        student_id,
        item_id,
        is_completed,
        last_viewed_at
    ) VALUES (?, ?, 0, NOW())
    ON DUPLICATE KEY UPDATE
        last_viewed_at = NOW()
");
$stmt->bind_param("ii", $studentId, $itemId);

if (!$stmt->execute()) {
    $stmt->close();
    respond('error', 'Failed to update lesson progress');
}

$stmt->close();

respond('success', [
    'student_id' => $studentId,
    'item_id' => $itemId,
    'message' => 'Last viewed updated successfully'
]);

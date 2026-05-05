<?php

require_once '../config.php';
require_once '../helpers/student_access.php';

validateRequestMethod('GET');

$auth = requireAuth('student');
$studentId = (int)$auth['id'];

$itemId = isset($_GET['item_id']) ? (int)$_GET['item_id'] : null;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;

if ($page < 1) $page = 1;
if ($limit < 1) $limit = 20;
if ($limit > 100) $limit = 100;

$offset = ($page - 1) * $limit;

if ($itemId) {
    if (!studentCanOpenItem($studentId, $itemId)) {
        respond('error', 'You do not have access to this item');
    }

    $stmt = $conn->prepare("
        SELECT
            lp.id,
            lp.student_id,
            lp.item_id,
            lp.is_completed,
            lp.last_viewed_at,
            i.title AS item_title,
            i.item_type
        FROM lesson_progress lp
        JOIN items i ON i.id = lp.item_id
        WHERE lp.student_id = ?
        AND lp.item_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("ii", $studentId, $itemId);
    $stmt->execute();
    $result = $stmt->get_result();
    $progress = $result->fetch_assoc();
    $stmt->close();

    if (!$progress) {
        $stmt = $conn->prepare("
            SELECT id, title, item_type
            FROM items
            WHERE id = ?
            AND is_published = 1
            LIMIT 1
        ");
        $stmt->bind_param("i", $itemId);
        $stmt->execute();
        $itemResult = $stmt->get_result();
        $item = $itemResult->fetch_assoc();
        $stmt->close();

        if (!$item) {
            respond('error', 'Item not found');
        }

        respond('success', [
            'id' => null,
            'student_id' => $studentId,
            'item_id' => $itemId,
            'is_completed' => 0,
            'last_viewed_at' => null,
            'item_title' => $item['title'],
            'item_type' => $item['item_type']
        ]);
    }

    respond('success', $progress);
}

$countSql = "
    SELECT COUNT(*) AS total
    FROM lesson_progress lp
    JOIN items i ON i.id = lp.item_id
    WHERE lp.student_id = ?
    AND i.is_published = 1
";
$stmt = $conn->prepare($countSql);
$stmt->bind_param("i", $studentId);
$stmt->execute();
$countResult = $stmt->get_result();
$totalCount = (int)$countResult->fetch_assoc()['total'];
$stmt->close();

$stmt = $conn->prepare("
    SELECT
        lp.id,
        lp.student_id,
        lp.item_id,
        lp.is_completed,
        lp.last_viewed_at,
        i.title AS item_title,
        i.item_type
    FROM lesson_progress lp
    JOIN items i ON i.id = lp.item_id
    WHERE lp.student_id = ?
    AND i.is_published = 1
    ORDER BY lp.last_viewed_at DESC, lp.id DESC
    LIMIT ? OFFSET ?
");
$stmt->bind_param("iii", $studentId, $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();

$progressItems = [];

while ($row = $result->fetch_assoc()) {
    if (!studentCanOpenItem($studentId, (int)$row['item_id'])) {
        continue;
    }

    $progressItems[] = $row;
}

$stmt->close();

respond('success', [
    'progress' => $progressItems,
    'total' => $totalCount,
    'page' => $page,
    'limit' => $limit,
    'total_pages' => ceil($totalCount / $limit)
]);

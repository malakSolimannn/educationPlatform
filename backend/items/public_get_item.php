<?php

require_once '../config.php';

validateRequestMethod('GET');

$itemId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$itemType = isset($_GET['item_type']) ? trim($_GET['item_type']) : null;
$gradeId = isset($_GET['grade_id']) ? (int)$_GET['grade_id'] : null;
$search = isset($_GET['search']) ? trim($_GET['search']) : null;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;

if ($page < 1) $page = 1;
if ($limit < 1) $limit = 20;
if ($limit > 100) $limit = 100;

$offset = ($page - 1) * $limit;

$safeColumns = "
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
    sort_order,
    created_at,
    image_url,
    content_type 
";

if ($itemId) {
    $stmt = $conn->prepare("
        SELECT $safeColumns
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
            i.sort_order,
            i.created_at,
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
}

$conditions = ["is_published = 1"];
$params = [];
$types = '';

if ($itemType) {
    $conditions[] = "item_type = ?";
    $params[] = $itemType;
    $types .= 's';
}

if ($gradeId) {
    $conditions[] = "grade_id = ?";
    $params[] = $gradeId;
    $types .= 'i';
}

if ($search) {
    $conditions[] = "(title LIKE ? OR description LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= 'ss';
}

$whereClause = "WHERE " . implode(" AND ", $conditions);

$countSql = "SELECT COUNT(*) AS total FROM items $whereClause";

$stmt = $conn->prepare($countSql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$countResult = $stmt->get_result();
$totalCount = (int)$countResult->fetch_assoc()['total'];
$stmt->close();

$sql = "
    SELECT $safeColumns
    FROM items
    $whereClause
    ORDER BY sort_order ASC, id DESC
    LIMIT ? OFFSET ?
";

$params[] = $limit;
$params[] = $offset;
$types .= 'ii';

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

$items = [];

while ($row = $result->fetch_assoc()) {
    $items[] = $row;
}

respond('success', [
    'items' => $items,
    'total' => $totalCount,
    'page' => $page,
    'limit' => $limit,
    'total_pages' => ceil($totalCount / $limit)
]);
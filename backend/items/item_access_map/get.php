<?php

require_once '../config.php';
validateRequestMethod('GET');
requireAuth(['super_admin', 'admin', 'assistant']);

$id = isset($_GET['id']) ? intval($_GET['id']) : null;
$itemId = isset($_GET['item_id']) && $_GET['item_id'] !== '' ? intval($_GET['item_id']) : null;
$grantsItemId = isset($_GET['grants_item_id']) && $_GET['grants_item_id'] !== '' ? intval($_GET['grants_item_id']) : null;
$search = isset($_GET['search']) ? trim($_GET['search']) : null;

$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;

if ($page < 1) $page = 1;
if ($limit < 1) $limit = 20;
if ($limit > 100) $limit = 100;

$offset = ($page - 1) * $limit;

if ($id) {
    $stmt = $conn->prepare("
        SELECT 
            iam.id,
            iam.item_id,
            i.title AS item_title,
            i.item_type AS item_type,
            iam.grants_item_id,
            gi.title AS grants_item_title,
            gi.item_type AS grants_item_type
        FROM item_access_map iam
        JOIN items i ON i.id = iam.item_id
        JOIN items gi ON gi.id = iam.grants_item_id
        WHERE iam.id = ?
    ");

    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    if ($result->num_rows === 0) {
        respond('error', 'Mapping not found');
    }

    respond('success', $result->fetch_assoc());
}

$conditions = [];
$params = [];
$types = '';

if ($itemId !== null) {
    $conditions[] = "iam.item_id = ?";
    $params[] = $itemId;
    $types .= 'i';
}

if ($grantsItemId !== null) {
    $conditions[] = "iam.grants_item_id = ?";
    $params[] = $grantsItemId;
    $types .= 'i';
}

if ($search) {
    $conditions[] = "(i.title LIKE ? OR gi.title LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= 'ss';
}

$whereClause = !empty($conditions)
    ? "WHERE " . implode(" AND ", $conditions)
    : "";

$countSql = "
    SELECT COUNT(*) AS total
    FROM item_access_map iam
    JOIN items i ON i.id = iam.item_id
    JOIN items gi ON gi.id = iam.grants_item_id
    $whereClause
";

$stmt = $conn->prepare($countSql);

if (!$stmt) {
    respond('error', 'Failed to prepare count query');
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$countResult = $stmt->get_result();
$totalCount = intval($countResult->fetch_assoc()['total']);
$stmt->close();

$sql = "
    SELECT 
        iam.id,
        iam.item_id,
        i.title AS item_title,
        i.item_type AS item_type,
        iam.grants_item_id,
        gi.title AS grants_item_title,
        gi.item_type AS grants_item_type
    FROM item_access_map iam
    JOIN items i ON i.id = iam.item_id
    JOIN items gi ON gi.id = iam.grants_item_id
    $whereClause
    ORDER BY iam.id DESC
    LIMIT ? OFFSET ?
";

$queryParams = $params;
$queryTypes = $types;

$queryParams[] = $limit;
$queryParams[] = $offset;
$queryTypes .= 'ii';

$stmt = $conn->prepare($sql);

$stmt->bind_param($queryTypes, ...$queryParams);
$stmt->execute();
$result = $stmt->get_result();

$mappings = [];

while ($row = $result->fetch_assoc()) {
    $mappings[] = $row;
}

$stmt->close();

respond('success', [
    'mappings' => $mappings,
    'total' => $totalCount,
    'page' => $page,
    'limit' => $limit,
    'total_pages' => ceil($totalCount / $limit)
]);
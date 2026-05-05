<?php

require_once '../config.php';

validateRequestMethod('GET');
requireAuth(['super_admin', 'admin', 'assistant']);

$itemId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$parentId = isset($_GET['parent_id']) && $_GET['parent_id'] !== '' ? (int)$_GET['parent_id'] : null;
$itemType = isset($_GET['item_type']) ? $_GET['item_type'] : null;
$gradeId = isset($_GET['grade_id']) ? $_GET['grade_id'] : null;
$status = isset($_GET['is_published']) ? (int)$_GET['is_published'] : null;
$search = isset($_GET['search']) ? trim($_GET['search']) : null;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;

if ($limit > 100) $limit = 100;
$offset = ($page - 1) * $limit;

if ($itemId) {
    $stmt = $conn->prepare("SELECT * FROM items WHERE id = ?");
    $stmt->bind_param("i", $itemId);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    if ($result->num_rows === 0) {
        respond('error', 'Item not found');
    }
    $item = $result->fetch_assoc();
    respond('success', $item);
}

$conditions = [];
$params = [];
$types = '';

if ($itemType) {
    $conditions[] = "item_type = ?";
    $params[] = $itemType;
    $types .= 's';
}
if ($parentId !== null) {
    $conditions[] = "parent_id = ?";
    $params[] = $parentId;
    $types .= 'i';
}
if ($gradeId) {
    $conditions[] = "grade_id = ?";
    $params[] = $gradeId;
    $types .= 'i';
}
if ($status !== null) {
    $conditions[] = "is_published = ?";
    $params[] = $status;
    $types .= 'i';
}
if ($search) {
    $conditions[] = "(title LIKE ? OR description LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= 'ss';
}
$whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

$countSql = "SELECT COUNT(*) as total FROM items $whereClause";
$stmt = $conn->prepare($countSql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$countResult = $stmt->get_result();
$totalCount = $countResult->fetch_assoc()['total'];
$stmt->close();

$sql = "SELECT * FROM items $whereClause ORDER BY id DESC LIMIT ? OFFSET ?";
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

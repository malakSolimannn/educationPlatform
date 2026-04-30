<?php

require_once '../config.php';

validateRequestMethod('GET');
requireAuth(['super_admin', 'admin', 'assistant']);

$assignmentId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$itemId = isset($_GET['item_id']) ? (int)$_GET['item_id'] : null;
$search = isset($_GET['search']) ? trim($_GET['search']) : null;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;

if ($limit > 100) $limit = 100;
$offset = ($page - 1) * $limit;

if ($assignmentId) {
    $stmt = $conn->prepare("SELECT * FROM assignments WHERE id = ?");
    $stmt->bind_param("i", $assignmentId);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    if ($result->num_rows === 0) {
        respond('error', 'Assignment not found');
    }
    
    respond('success', $result->fetch_assoc());
}

$conditions = [];
$params = [];
$types = '';

if ($itemId) {
    $conditions[] = "item_id = ?";
    $params[] = $itemId;
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

$countSql = "SELECT COUNT(*) as total FROM assignments $whereClause";
$stmt = $conn->prepare($countSql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$countResult = $stmt->get_result();
$totalCount = $countResult->fetch_assoc()['total'];
$stmt->close();

$sql = "SELECT * FROM assignments $whereClause ORDER BY due_date ASC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= 'ii';
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

$assignments = [];
while ($row = $result->fetch_assoc()) {
    $assignments[] = $row;
}

respond('success', [
    'assignments' => $assignments,
    'total' => $totalCount,
    'page' => $page,
    'limit' => $limit,
    'total_pages' => ceil($totalCount / $limit)
]);

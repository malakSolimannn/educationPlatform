<?php

require_once '../config.php';

validateRequestMethod('GET');
requireAuth(['super_admin', 'admin', 'assistant']);

$centerId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$status = isset($_GET['status']) ? trim($_GET['status']) : null;
$search = isset($_GET['search']) ? trim($_GET['search']) : null;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;

if ($limit > 100) $limit = 100;
$offset = ($page - 1) * $limit;

if ($centerId) {
    $stmt = $conn->prepare("SELECT * FROM centers WHERE id = ?");
    $stmt->bind_param("i", $centerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    if ($result->num_rows === 0) {
        respond('error', 'Center not found');
    }
    $center = $result->fetch_assoc();
    respond('success', $center);
}

$conditions = [];
$params = [];
$types = '';

if ($status) {
    $allowedStatuses = ['active', 'inactive'];
    if (!in_array($status, $allowedStatuses)) {
        respond('error', 'Invalid status filter');
    }
    $conditions[] = "status = ?";
    $params[] = $status;
    $types .= 's';
}

if ($search) {
    $conditions[] = "(name LIKE ? OR phone LIKE ? OR address LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= 'sss';
}

$whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

$countSql = "SELECT COUNT(*) as total FROM centers $whereClause";
$stmt = $conn->prepare($countSql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$countResult = $stmt->get_result();
$totalCount = $countResult->fetch_assoc()['total'];
$stmt->close();

$sql = "SELECT * FROM centers $whereClause ORDER BY id DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= 'ii';
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

$centers = [];
while ($row = $result->fetch_assoc()) {
    $centers[] = $row;
}

respond('success', [
    'centers' => $centers,
    'total' => $totalCount,
    'page' => $page,
    'limit' => $limit,
    'total_pages' => ceil($totalCount / $limit)
]);

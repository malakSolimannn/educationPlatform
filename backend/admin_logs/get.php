<?php

require_once '../config.php';

validateRequestMethod('GET');
requireAuth(['super_admin', 'admin']);

$logId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$adminId = isset($_GET['admin_id']) ? (int)$_GET['admin_id'] : null;
$action = isset($_GET['action']) ? trim($_GET['action']) : null;
$targetType = isset($_GET['target_type']) ? trim($_GET['target_type']) : null;
$targetId = isset($_GET['target_id']) ? (int)$_GET['target_id'] : null;
$dateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : null;
$dateTo = isset($_GET['date_to']) ? trim($_GET['date_to']) : null;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;

if ($limit > 100) $limit = 100;
$offset = ($page - 1) * $limit;

if ($logId) {
    $stmt = $conn->prepare("SELECT * FROM admin_logs WHERE id = ?");
    $stmt->bind_param("i", $logId);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    if ($result->num_rows === 0) {
        respond('error', 'Log entry not found');
    }
    
    respond('success', $result->fetch_assoc());
}

$conditions = [];
$params = [];
$types = '';

if ($adminId) {
    $conditions[] = "admin_id = ?";
    $params[] = $adminId;
    $types .= 'i';
}

if ($action) {
    $conditions[] = "action = ?";
    $params[] = $action;
    $types .= 's';
}

if ($targetType) {
    $conditions[] = "target_type = ?";
    $params[] = $targetType;
    $types .= 's';
}

if ($targetId) {
    $conditions[] = "target_id = ?";
    $params[] = $targetId;
    $types .= 'i';
}

if ($dateFrom) {
    if (!strtotime($dateFrom)) {
        respond('error', 'Invalid date_from format');
    }
    $fromDate = date('Y-m-d H:i:s', strtotime($dateFrom));
    $conditions[] = "created_at >= ?";
    $params[] = $fromDate;
    $types .= 's';
}

if ($dateTo) {
    if (!strtotime($dateTo)) {
        respond('error', 'Invalid date_to format');
    }
    $toDate = date('Y-m-d H:i:s', strtotime($dateTo));
    $conditions[] = "created_at <= ?";
    $params[] = $toDate;
    $types .= 's';
}

$whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

$countSql = "SELECT COUNT(*) as total FROM admin_logs $whereClause";
$stmt = $conn->prepare($countSql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$countResult = $stmt->get_result();
$totalCount = $countResult->fetch_assoc()['total'];
$stmt->close();

$sql = "SELECT * FROM admin_logs $whereClause ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= 'ii';
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

$logs = [];
while ($row = $result->fetch_assoc()) {
    $logs[] = $row;
}

respond('success', [
    'logs' => $logs,
    'total' => $totalCount,
    'page' => $page,
    'limit' => $limit,
    'total_pages' => ceil($totalCount / $limit)
]);

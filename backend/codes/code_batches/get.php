<?php

require_once '../../config.php';

validateRequestMethod('GET');
requireAuth(['super_admin', 'admin', 'assistant']);

$batchId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$centerId = isset($_GET['center_id']) ? (int)$_GET['center_id'] : null;
$codeType = isset($_GET['code_type']) ? trim($_GET['code_type']) : null;
$createdByAdminId = isset($_GET['created_by_admin_id']) ? (int)$_GET['created_by_admin_id'] : null;
$search = isset($_GET['search']) ? trim($_GET['search']) : null;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;

if ($page < 1) $page = 1;
if ($limit < 1) $limit = 20;
if ($limit > 100) $limit = 100;

if ($codeType && !in_array($codeType, ['wallet', 'item'])) {
    respond('error', 'Invalid code type');
}

$offset = ($page - 1) * $limit;

if ($batchId) {
    $stmt = $conn->prepare("
        SELECT 
            cb.*,
            centers.name AS center_name,
            admins.name AS created_by_admin_name,
            COUNT(c.id) AS total_codes,
            COALESCE(SUM(c.is_used), 0) AS used_codes,
            COUNT(c.id) - COALESCE(SUM(c.is_used), 0) AS unused_codes
        FROM code_batches cb
        LEFT JOIN centers ON centers.id = cb.center_id
        LEFT JOIN admins ON admins.id = cb.created_by_admin_id
        LEFT JOIN codes c ON c.batch_id = cb.id
        WHERE cb.id = ?
        GROUP BY cb.id
    ");
    $stmt->bind_param("i", $batchId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();
        respond('error', 'Code batch not found');
    }

    $batch = $result->fetch_assoc();
    $stmt->close();

    $batch['total_codes'] = (int)$batch['total_codes'];
    $batch['used_codes'] = (int)$batch['used_codes'];
    $batch['unused_codes'] = (int)$batch['unused_codes'];

    respond('success', $batch);
}

$conditions = [];
$params = [];
$types = '';

if ($centerId !== null) {
    $conditions[] = "cb.center_id = ?";
    $params[] = $centerId;
    $types .= 'i';
}

if ($codeType) {
    $conditions[] = "cb.code_type = ?";
    $params[] = $codeType;
    $types .= 's';
}

if ($createdByAdminId !== null) {
    $conditions[] = "cb.created_by_admin_id = ?";
    $params[] = $createdByAdminId;
    $types .= 'i';
}

if ($search) {
    $conditions[] = "(cb.notes LIKE ? OR centers.name LIKE ?)";
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
    FROM code_batches cb
    LEFT JOIN centers ON centers.id = cb.center_id
    $whereClause
";

$stmt = $conn->prepare($countSql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$countResult = $stmt->get_result();
$totalCount = (int)$countResult->fetch_assoc()['total'];
$stmt->close();

$sql = "
    SELECT 
        cb.*,
        centers.name AS center_name,
        admins.name AS created_by_admin_name,
        COUNT(c.id) AS total_codes,
        COALESCE(SUM(c.is_used), 0) AS used_codes,
        COUNT(c.id) - COALESCE(SUM(c.is_used), 0) AS unused_codes
    FROM code_batches cb
    LEFT JOIN centers ON centers.id = cb.center_id
    LEFT JOIN admins ON admins.id = cb.created_by_admin_id
    LEFT JOIN codes c ON c.batch_id = cb.id
    $whereClause
    GROUP BY cb.id
    ORDER BY cb.id DESC
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

$batches = [];
while ($row = $result->fetch_assoc()) {
    $row['total_codes'] = (int)$row['total_codes'];
    $row['used_codes'] = (int)$row['used_codes'];
    $row['unused_codes'] = (int)$row['unused_codes'];

    $batches[] = $row;
}

$stmt->close();

respond('success', [
    'batches' => $batches,
    'total' => $totalCount,
    'page' => $page,
    'limit' => $limit,
    'total_pages' => ceil($totalCount / $limit)
]);
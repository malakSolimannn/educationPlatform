<?php

require_once '../config.php';

validateRequestMethod('GET');
requireAuth(['super_admin', 'admin', 'assistant']);

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$batchId = isset($_GET['batch_id']) ? (int)$_GET['batch_id'] : null;
$centerId = isset($_GET['center_id']) ? (int)$_GET['center_id'] : null;
$code = isset($_GET['code']) ? trim($_GET['code']) : null;
$codeType = isset($_GET['code_type']) ? trim($_GET['code_type']) : null;
$isUsed = isset($_GET['is_used']) ? (int)$_GET['is_used'] : null;
$usedByStudentId = isset($_GET['used_by_student_id']) ? (int)$_GET['used_by_student_id'] : null;
$itemId = isset($_GET['item_id']) ? (int)$_GET['item_id'] : null;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;

if ($page < 1) $page = 1;
if ($limit < 1) $limit = 20;
if ($limit > 100) $limit = 100;

$offset = ($page - 1) * $limit;

if ($codeType && !in_array($codeType, ['wallet', 'item'])) {
    respond('error', 'Invalid code type');
}

if ($isUsed !== null && !in_array($isUsed, [0, 1])) {
    respond('error', 'Invalid is_used value');
}

if ($id) {
    $stmt = $conn->prepare("
        SELECT 
            c.*,
            cb.center_id,
            centers.name AS center_name
        FROM codes c
        LEFT JOIN code_batches cb ON cb.id = c.batch_id
        LEFT JOIN centers ON centers.id = cb.center_id
        WHERE c.id = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();
        respond('error', 'Code not found');
    }

    $row = $result->fetch_assoc();
    $stmt->close();

    respond('success', $row);
}

$conditions = [];
$params = [];
$types = '';

if ($batchId !== null) {
    $conditions[] = "c.batch_id = ?";
    $params[] = $batchId;
    $types .= 'i';
}

if ($centerId !== null) {
    $conditions[] = "cb.center_id = ?";
    $params[] = $centerId;
    $types .= 'i';
}

if ($code) {
    $conditions[] = "c.code LIKE ?";
    $params[] = "%$code%";
    $types .= 's';
}

if ($codeType) {
    $conditions[] = "c.code_type = ?";
    $params[] = $codeType;
    $types .= 's';
}

if ($isUsed !== null) {
    $conditions[] = "c.is_used = ?";
    $params[] = $isUsed;
    $types .= 'i';
}

if ($usedByStudentId !== null) {
    $conditions[] = "c.used_by_student_id = ?";
    $params[] = $usedByStudentId;
    $types .= 'i';
}

if ($itemId !== null) {
    $conditions[] = "c.item_id = ?";
    $params[] = $itemId;
    $types .= 'i';
}

$whereClause = !empty($conditions)
    ? "WHERE " . implode(' AND ', $conditions)
    : '';

$countSql = "
    SELECT COUNT(*) AS total
    FROM codes c
    LEFT JOIN code_batches cb ON cb.id = c.batch_id
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
        c.*,
        cb.center_id,
        centers.name AS center_name
    FROM codes c
    LEFT JOIN code_batches cb ON cb.id = c.batch_id
    LEFT JOIN centers ON centers.id = cb.center_id
    $whereClause
    ORDER BY c.id DESC
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

$codes = [];
while ($row = $result->fetch_assoc()) {
    $codes[] = $row;
}

$stmt->close();

respond('success', [
    'codes' => $codes,
    'total' => $totalCount,
    'page' => $page,
    'limit' => $limit,
    'total_pages' => ceil($totalCount / $limit)
]);
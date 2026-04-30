<?php

require_once '../config.php';

validateRequestMethod('GET');
requireAuth(['super_admin', 'admin', 'assistant']);

$id = isset($_GET['id']) ? intval($_GET['id']) : null;
$itemId = isset($_GET['item_id']) && $_GET['item_id'] !== '' ? intval($_GET['item_id']) : null;
$type = isset($_GET['requirement_type']) ? trim($_GET['requirement_type']) : null;

$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;

if ($page < 1) $page = 1;
if ($limit < 1) $limit = 20;
if ($limit > 100) $limit = 100;

$offset = ($page - 1) * $limit;

if ($id) {
    $stmt = $conn->prepare("
        SELECT 
            ip.*,
            i.title AS item_title,
            pi.title AS prerequisite_item_title,
            q.title AS prerequisite_quiz_title
        FROM item_prerequisites ip
        JOIN items i ON i.id = ip.item_id
        LEFT JOIN items pi ON pi.id = ip.prerequisite_item_id
        LEFT JOIN quizzes q ON q.id = ip.prerequisite_quiz_id
        WHERE ip.id = ?
    ");

    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    if ($result->num_rows === 0) {
        respond('error', 'Prerequisite not found');
    }

    respond('success', $result->fetch_assoc());
}

$conditions = [];
$params = [];
$types = '';

if ($itemId !== null) {
    $conditions[] = "ip.item_id = ?";
    $params[] = $itemId;
    $types .= 'i';
}

if ($type) {
    $conditions[] = "ip.requirement_type = ?";
    $params[] = $type;
    $types .= 's';
}

$whereClause = !empty($conditions)
    ? "WHERE " . implode(" AND ", $conditions)
    : "";

$countSql = "
    SELECT COUNT(*) AS total
    FROM item_prerequisites ip
    $whereClause
";

$stmt = $conn->prepare($countSql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$countResult = $stmt->get_result();
$totalCount = intval($countResult->fetch_assoc()['total']);
$stmt->close();

$sql = "
    SELECT 
        ip.*,
        i.title AS item_title,
        pi.title AS prerequisite_item_title,
        q.title AS prerequisite_quiz_title
    FROM item_prerequisites ip
    JOIN items i ON i.id = ip.item_id
    LEFT JOIN items pi ON pi.id = ip.prerequisite_item_id
    LEFT JOIN quizzes q ON q.id = ip.prerequisite_quiz_id
    $whereClause
    ORDER BY ip.id DESC
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

$prerequisites = [];

while ($row = $result->fetch_assoc()) {
    $prerequisites[] = $row;
}

$stmt->close();

respond('success', [
    'prerequisites' => $prerequisites,
    'total' => $totalCount,
    'page' => $page,
    'limit' => $limit,
    'total_pages' => ceil($totalCount / $limit)
]);
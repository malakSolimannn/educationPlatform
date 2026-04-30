<?php

require_once '../../config.php';

validateRequestMethod('GET');
requireAuth(['super_admin', 'admin', 'assistant']);

$optionId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$questionId = isset($_GET['question_id']) ? (int)$_GET['question_id'] : null;
$isCorrect = isset($_GET['is_correct']) ? (int)$_GET['is_correct'] : null;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;

if ($limit > 100) $limit = 100;
$offset = ($page - 1) * $limit;

if ($optionId) {
    $stmt = $conn->prepare("SELECT * FROM quiz_options WHERE id = ?");
    $stmt->bind_param("i", $optionId);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    if ($result->num_rows === 0) {
        respond('error', 'Option not found');
    }
    
    respond('success', $result->fetch_assoc());
}

$conditions = [];
$params = [];
$types = '';

if ($questionId) {
    $conditions[] = "question_id = ?";
    $params[] = $questionId;
    $types .= 'i';
}

if ($isCorrect !== null) {
    $conditions[] = "is_correct = ?";
    $params[] = $isCorrect;
    $types .= 'i';
}

$whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

$countSql = "SELECT COUNT(*) as total FROM quiz_options $whereClause";
$stmt = $conn->prepare($countSql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$countResult = $stmt->get_result();
$totalCount = $countResult->fetch_assoc()['total'];
$stmt->close();

$sql = "SELECT * FROM quiz_options $whereClause ORDER BY id ASC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= 'ii';
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

$options = [];
while ($row = $result->fetch_assoc()) {
    $options[] = $row;
}

respond('success', [
    'options' => $options,
    'total' => $totalCount,
    'page' => $page,
    'limit' => $limit,
    'total_pages' => ceil($totalCount / $limit)
]);

<?php

require_once '../config.php';

validateRequestMethod('GET');
requireAuth(['super_admin', 'admin', 'assistant']);

$quizId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$itemId = isset($_GET['item_id']) ? (int)$_GET['item_id'] : null;
$isPublished = isset($_GET['is_published']) ? (int)$_GET['is_published'] : null;
$search = isset($_GET['search']) ? trim($_GET['search']) : null;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;

if ($limit > 100) $limit = 100;
$offset = ($page - 1) * $limit;

if ($quizId) {
    $stmt = $conn->prepare("SELECT * FROM quizzes WHERE id = ?");
    $stmt->bind_param("i", $quizId);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    if ($result->num_rows === 0) {
        respond('error', 'Quiz not found');
    }
    
    $quiz = $result->fetch_assoc();
    
    $stmt = $conn->prepare("SELECT COUNT(*) as total_questions FROM quiz_questions WHERE quiz_id = ?");
    $stmt->bind_param("i", $quizId);
    $stmt->execute();
    $questionsCount = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    $quiz['total_questions'] = (int)$questionsCount['total_questions'];
    
    respond('success', $quiz);
}

$conditions = [];
$params = [];
$types = '';

if ($itemId) {
    $conditions[] = "item_id = ?";
    $params[] = $itemId;
    $types .= 'i';
}

if ($isPublished !== null) {
    $conditions[] = "is_published = ?";
    $params[] = $isPublished;
    $types .= 'i';
}

if ($search) {
    $conditions[] = "(title LIKE ? OR type LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= 'ss';
}

$whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

$countSql = "SELECT COUNT(*) as total FROM quizzes $whereClause";
$stmt = $conn->prepare($countSql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$countResult = $stmt->get_result();
$totalCount = $countResult->fetch_assoc()['total'];
$stmt->close();

$sql = "SELECT * FROM quizzes $whereClause ORDER BY id DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= 'ii';
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

$quizzes = [];
while ($row = $result->fetch_assoc()) {
    $qId = (int)$row['id'];
    
    $stmt = $conn->prepare("SELECT COUNT(*) as total_questions FROM quiz_questions WHERE quiz_id = ?");
    $stmt->bind_param("i", $qId);
    $stmt->execute();
    $questionsCount = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    $row['total_questions'] = (int)$questionsCount['total_questions'];
    
    $quizzes[] = $row;
}

respond('success', [
    'quizzes' => $quizzes,
    'total' => $totalCount,
    'page' => $page,
    'limit' => $limit,
    'total_pages' => ceil($totalCount / $limit)
]);

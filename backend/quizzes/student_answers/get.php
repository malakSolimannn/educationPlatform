<?php

require_once '../../config.php';

validateRequestMethod('GET');
requireAuth(['super_admin', 'admin', 'assistant']);

$answerId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$attemptId = isset($_GET['attempt_id']) ? (int)$_GET['attempt_id'] : null;
$questionId = isset($_GET['question_id']) ? (int)$_GET['question_id'] : null;
$isCorrect = isset($_GET['is_correct']) ? (int)$_GET['is_correct'] : null;
$studentId = isset($_GET['student_id']) ? (int)$_GET['student_id'] : null;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;

if ($limit > 100) $limit = 100;
$offset = ($page - 1) * $limit;

if ($answerId) {
    $stmt = $conn->prepare("SELECT * FROM student_quiz_answers WHERE id = ?");
    $stmt->bind_param("i", $answerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    if ($result->num_rows === 0) {
        respond('error', 'Answer not found');
    }
    
    respond('success', $result->fetch_assoc());
}

$conditions = [];
$params = [];
$types = '';

if ($attemptId) {
    $conditions[] = "attempt_id = ?";
    $params[] = $attemptId;
    $types .= 'i';
}

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

if ($studentId) {
    $conditions[] = "attempt_id IN (SELECT id FROM quiz_attempts WHERE student_id = ?)";
    $params[] = $studentId;
    $types .= 'i';
}

$whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

$countSql = "SELECT COUNT(*) as total FROM student_quiz_answers $whereClause";
$stmt = $conn->prepare($countSql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$countResult = $stmt->get_result();
$totalCount = $countResult->fetch_assoc()['total'];
$stmt->close();

$sql = "SELECT * FROM student_quiz_answers $whereClause ORDER BY id DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= 'ii';
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

$answers = [];
while ($row = $result->fetch_assoc()) {
    $answers[] = $row;
}

respond('success', [
    'answers' => $answers,
    'total' => $totalCount,
    'page' => $page,
    'limit' => $limit,
    'total_pages' => ceil($totalCount / $limit)
]);

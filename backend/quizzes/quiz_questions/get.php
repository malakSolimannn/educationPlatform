<?php

require_once '../../config.php';

validateRequestMethod('GET');
requireAuth(['super_admin', 'admin', 'assistant']);

$questionId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$quizId = isset($_GET['quiz_id']) ? (int)$_GET['quiz_id'] : null;
$questionType = isset($_GET['question_type']) ? trim($_GET['question_type']) : null;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;

if ($limit > 100) $limit = 100;
$offset = ($page - 1) * $limit;

if ($questionId) {
    $stmt = $conn->prepare("SELECT * FROM quiz_questions WHERE id = ?");
    $stmt->bind_param("i", $questionId);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    if ($result->num_rows === 0) {
        respond('error', 'Question not found');
    }
    
    $question = $result->fetch_assoc();
    
    $stmt = $conn->prepare("SELECT * FROM quiz_options WHERE question_id = ? ORDER BY id ASC");
    $stmt->bind_param("i", $questionId);
    $stmt->execute();
    $optionsResult = $stmt->get_result();
    $stmt->close();
    
    $options = [];
    while ($option = $optionsResult->fetch_assoc()) {
        $options[] = $option;
    }
    
    $question['options'] = $options;
    
    respond('success', $question);
}

$conditions = [];
$params = [];
$types = '';

if ($quizId) {
    $conditions[] = "quiz_id = ?";
    $params[] = $quizId;
    $types .= 'i';
}

if ($questionType) {
    $allowedQuestionTypes = ['mcq', 'true_false', 'text'];
    if (!in_array($questionType, $allowedQuestionTypes)) {
        respond('error', 'Invalid question type filter');
    }
    $conditions[] = "question_type = ?";
    $params[] = $questionType;
    $types .= 's';
}

$whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

$countSql = "SELECT COUNT(*) as total FROM quiz_questions $whereClause";
$stmt = $conn->prepare($countSql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$countResult = $stmt->get_result();
$totalCount = $countResult->fetch_assoc()['total'];
$stmt->close();

$sql = "SELECT * FROM quiz_questions $whereClause ORDER BY id ASC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= 'ii';
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

$questions = [];
while ($row = $result->fetch_assoc()) {
    $qId = (int)$row['id'];
    
    $stmt = $conn->prepare("SELECT * FROM quiz_options WHERE question_id = ? ORDER BY id ASC");
    $stmt->bind_param("i", $qId);
    $stmt->execute();
    $optionsResult = $stmt->get_result();
    $stmt->close();
    
    $options = [];
    while ($option = $optionsResult->fetch_assoc()) {
        $options[] = $option;
    }
    
    $row['options'] = $options;
    
    $questions[] = $row;
}

respond('success', [
    'questions' => $questions,
    'total' => $totalCount,
    'page' => $page,
    'limit' => $limit,
    'total_pages' => ceil($totalCount / $limit)
]);

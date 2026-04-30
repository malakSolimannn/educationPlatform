<?php

require_once '../../config.php';

validateRequestMethod('GET');
requireAuth(['super_admin', 'admin', 'assistant']);

$submissionId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$assignmentId = isset($_GET['assignment_id']) ? (int)$_GET['assignment_id'] : null;
$studentId = isset($_GET['student_id']) ? (int)$_GET['student_id'] : null;
$submitted = isset($_GET['submitted']) ? (int)$_GET['submitted'] : null;
$graded = isset($_GET['graded']) ? (int)$_GET['graded'] : null;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;

if ($limit > 100) $limit = 100;
$offset = ($page - 1) * $limit;

if ($submissionId) {
    $stmt = $conn->prepare("SELECT * FROM assignment_submissions WHERE id = ?");
    $stmt->bind_param("i", $submissionId);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    if ($result->num_rows === 0) {
        respond('error', 'Submission not found');
    }
    
    respond('success', $result->fetch_assoc());
}

$conditions = [];
$params = [];
$types = '';

if ($assignmentId) {
    $conditions[] = "assignment_id = ?";
    $params[] = $assignmentId;
    $types .= 'i';
}

if ($studentId) {
    $conditions[] = "student_id = ?";
    $params[] = $studentId;
    $types .= 'i';
}

if ($submitted !== null) {
    if ($submitted === 1) {
        $conditions[] = "submitted_at IS NOT NULL";
    } else {
        $conditions[] = "submitted_at IS NULL";
    }
}

if ($graded !== null) {
    if ($graded === 1) {
        $conditions[] = "grade IS NOT NULL";
    } else {
        $conditions[] = "grade IS NULL";
    }
}

$whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

$countSql = "SELECT COUNT(*) as total FROM assignment_submissions $whereClause";
$stmt = $conn->prepare($countSql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$countResult = $stmt->get_result();
$totalCount = $countResult->fetch_assoc()['total'];
$stmt->close();

$sql = "SELECT * FROM assignment_submissions $whereClause ORDER BY id DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= 'ii';
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

$submissions = [];
while ($row = $result->fetch_assoc()) {
    $submissions[] = $row;
}

respond('success', [
    'submissions' => $submissions,
    'total' => $totalCount,
    'page' => $page,
    'limit' => $limit,
    'total_pages' => ceil($totalCount / $limit)
]);

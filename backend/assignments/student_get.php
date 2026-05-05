<?php

require_once '../config.php';
require_once '../helpers/student_access.php';

validateRequestMethod('GET');

$auth = requireAuth('student');
$studentId = (int)$auth['id'];

$assignmentId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$itemId = isset($_GET['item_id']) ? (int)$_GET['item_id'] : null;
$search = isset($_GET['search']) ? trim($_GET['search']) : null;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;

if ($page < 1) $page = 1;
if ($limit < 1) $limit = 20;
if ($limit > 100) $limit = 100;

$offset = ($page - 1) * $limit;

if ($assignmentId) {
    $stmt = $conn->prepare("
        SELECT 
            a.id,
            a.item_id,
            a.file_path,
            a.title,
            a.description,
            a.due_date,
            i.title AS item_title,
            i.item_type,
            s.id AS submission_id,
            s.submission_text,
            s.file_path AS submission_file_path,
            s.grade,
            s.feedback,
            s.submitted_at
        FROM assignments a
        JOIN items i ON i.id = a.item_id
        LEFT JOIN assignment_submissions s
            ON s.assignment_id = a.id
            AND s.student_id = ?
        WHERE a.id = ?
        AND i.is_published = 1
        LIMIT 1
    ");

    $stmt->bind_param("ii", $studentId, $assignmentId);
    $stmt->execute();
    $result = $stmt->get_result();
    $assignment = $result->fetch_assoc();
    $stmt->close();

    if (!$assignment) {
        respond('error', 'Assignment not found');
    }

    if (!studentCanOpenItem($studentId, (int)$assignment['item_id'])) {
        respond('error', 'You do not have access to this assignment');
    }

    $assignment['is_submitted'] = $assignment['submission_id'] ? 1 : 0;
    $assignment['is_graded'] = $assignment['grade'] !== null ? 1 : 0;
    $assignment['is_overdue'] = strtotime($assignment['due_date']) < time() ? 1 : 0;

    respond('success', $assignment);
}

$conditions = [
    "i.is_published = 1"
];

$params = [];
$types = '';

if ($itemId) {
    if (!studentCanOpenItem($studentId, $itemId)) {
        respond('error', 'You do not have access to this item');
    }

    $conditions[] = "a.item_id = ?";
    $params[] = $itemId;
    $types .= 'i';
}

if ($search) {
    $conditions[] = "(a.title LIKE ? OR a.description LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= 'ss';
}

$whereClause = "WHERE " . implode(" AND ", $conditions);

$countSql = "
    SELECT COUNT(*) AS total
    FROM assignments a
    JOIN items i ON i.id = a.item_id
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
        a.id,
        a.item_id,
        a.file_path,
        a.title,
        a.description,
        a.due_date,
        i.title AS item_title,
        i.item_type,
        s.id AS submission_id,
        s.grade,
        s.feedback,
        s.submitted_at
    FROM assignments a
    JOIN items i ON i.id = a.item_id
    LEFT JOIN assignment_submissions s
        ON s.assignment_id = a.id
        AND s.student_id = ?
    $whereClause
    ORDER BY a.due_date ASC
    LIMIT ? OFFSET ?
";

$queryParams = array_merge([$studentId], $params, [$limit, $offset]);
$queryTypes = 'i' . $types . 'ii';

$stmt = $conn->prepare($sql);
$stmt->bind_param($queryTypes, ...$queryParams);
$stmt->execute();
$result = $stmt->get_result();

$assignments = [];

while ($row = $result->fetch_assoc()) {
    if (!studentCanOpenItem($studentId, (int)$row['item_id'])) {
        continue;
    }

    $row['is_submitted'] = $row['submission_id'] ? 1 : 0;
    $row['is_graded'] = $row['grade'] !== null ? 1 : 0;
    $row['is_overdue'] = strtotime($row['due_date']) < time() ? 1 : 0;

    $assignments[] = $row;
}

$stmt->close();

respond('success', [
    'assignments' => $assignments,
    'total' => count($assignments),
    'page' => $page,
    'limit' => $limit,
    'total_pages' => ceil(count($assignments) / $limit)
]);
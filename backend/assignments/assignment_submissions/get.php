<?php

require_once '../../config.php';

validateRequestMethod('GET');

$auth = requireAuth(['admins', 'student']);
$isStudent = $auth['auth_type'] === 'student';

$submissionId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$assignmentId = isset($_GET['assignment_id']) ? (int)$_GET['assignment_id'] : null;
$studentId = isset($_GET['student_id']) ? (int)$_GET['student_id'] : null;
$submitted = isset($_GET['submitted']) ? (int)$_GET['submitted'] : null;
$graded = isset($_GET['graded']) ? (int)$_GET['graded'] : null;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;

if ($isStudent) {
    $studentId = (int)$auth['id'];
}

if ($page < 1) $page = 1;
if ($limit < 1) $limit = 20;
if ($limit > 100) $limit = 100;

$offset = ($page - 1) * $limit;

if ($submissionId) {
    if ($isStudent) {
        $stmt = $conn->prepare("
            SELECT 
                s.*,
                a.title AS assignment_title,
                a.due_date
            FROM assignment_submissions s
            LEFT JOIN assignments a ON a.id = s.assignment_id
            WHERE s.id = ?
            AND s.student_id = ?
            LIMIT 1
        ");
        $stmt->bind_param("ii", $submissionId, $studentId);
    } else {
        $stmt = $conn->prepare("
            SELECT 
                s.*,
                a.title AS assignment_title,
                a.due_date,
                st.full_name AS student_name,
                st.phone AS student_phone,
                st.email AS student_email
            FROM assignment_submissions s
            LEFT JOIN assignments a ON a.id = s.assignment_id
            LEFT JOIN students st ON st.id = s.student_id
            WHERE s.id = ?
            LIMIT 1
        ");
        $stmt->bind_param("i", $submissionId);
    }

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
    $conditions[] = "s.assignment_id = ?";
    $params[] = $assignmentId;
    $types .= 'i';
}

if ($studentId) {
    $conditions[] = "s.student_id = ?";
    $params[] = $studentId;
    $types .= 'i';
}

if ($submitted !== null) {
    $conditions[] = $submitted === 1
        ? "s.submitted_at IS NOT NULL"
        : "s.submitted_at IS NULL";
}

if ($graded !== null) {
    $conditions[] = $graded === 1
        ? "s.grade IS NOT NULL"
        : "s.grade IS NULL";
}

$whereClause = !empty($conditions)
    ? "WHERE " . implode(" AND ", $conditions)
    : "";

$countSql = "
    SELECT COUNT(*) AS total
    FROM assignment_submissions s
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

if ($isStudent) {
    $sql = "
        SELECT 
            s.*,
            a.title AS assignment_title,
            a.due_date
        FROM assignment_submissions s
        LEFT JOIN assignments a ON a.id = s.assignment_id
        $whereClause
        ORDER BY s.id DESC
        LIMIT ? OFFSET ?
    ";
} else {
    $sql = "
        SELECT 
            s.*,
            a.title AS assignment_title,
            a.due_date,
            st.full_name AS student_name,
            st.phone AS student_phone,
            st.email AS student_email
        FROM assignment_submissions s
        LEFT JOIN assignments a ON a.id = s.assignment_id
        LEFT JOIN students st ON st.id = s.student_id
        $whereClause
        ORDER BY s.id DESC
        LIMIT ? OFFSET ?
    ";
}

$params[] = $limit;
$params[] = $offset;
$types .= 'ii';

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$submissions = [];

while ($row = $result->fetch_assoc()) {
    $submissions[] = $row;
}

$stmt->close();

respond('success', [
    'submissions' => $submissions,
    'total' => $totalCount,
    'page' => $page,
    'limit' => $limit,
    'total_pages' => ceil($totalCount / $limit)
]);
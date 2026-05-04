<?php

require_once '../config.php';

validateRequestMethod('GET');

$auth = requireAuth(['admins', 'student']);

$studentId = isset($_GET['id']) ? (int)$_GET['id'] : null;

if ($auth['auth_type'] === 'student') {
    $studentId = (int)$auth['id'];
}

$status = isset($_GET['status']) ? trim($_GET['status']) : null;
$gradeId = isset($_GET['grade_id']) ? (int)$_GET['grade_id'] : null;
$search = isset($_GET['search']) ? trim($_GET['search']) : null;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;

if ($page < 1) $page = 1;
if ($limit < 1) $limit = 20;
if ($limit > 100) $limit = 100;

$offset = ($page - 1) * $limit;

if ($studentId) {
    $stmt = $conn->prepare("
        SELECT 
            id, full_name, phone, email, grade_id, school_name,
            wallet_balance, status, created_at
        FROM students
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->bind_param("i", $studentId);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    if ($result->num_rows === 0) {
        respond('error', 'Student not found');
    }

    $student = $result->fetch_assoc();
    respond('success', $student);
}

if ($auth['auth_type'] === 'student') {
    respond('error', 'You are not authorized to view students list');
}

$conditions = [];
$params = [];
$types = '';

if ($status) {
    $conditions[] = "status = ?";
    $params[] = $status;
    $types .= 's';
}

if ($gradeId) {
    $conditions[] = "grade_id = ?";
    $params[] = $gradeId;
    $types .= 'i';
}

if ($search) {
    $conditions[] = "(full_name LIKE ? OR phone LIKE ? OR email LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= 'sss';
}

$whereClause = !empty($conditions)
    ? "WHERE " . implode(" AND ", $conditions)
    : "";

$countSql = "SELECT COUNT(*) AS total FROM students $whereClause";

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
        id, full_name, phone, email, grade_id, school_name,
        wallet_balance, status, created_at
    FROM students
    $whereClause
    ORDER BY id DESC
    LIMIT ? OFFSET ?
";

$params[] = $limit;
$params[] = $offset;
$types .= 'ii';

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

$students = [];

while ($row = $result->fetch_assoc()) {
    $students[] = $row;
}

respond('success', [
    'students' => $students,
    'total' => $totalCount,
    'page' => $page,
    'limit' => $limit,
    'total_pages' => ceil($totalCount / $limit)
]);
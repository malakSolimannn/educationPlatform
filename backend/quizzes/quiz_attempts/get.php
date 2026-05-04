<?php

require_once '../../config.php';

validateRequestMethod('GET');

$auth = requireAuth(['admin','super_admin','assistant','student']);
$isStudent = $auth['auth_type'] === 'student';

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$quizId = isset($_GET['quiz_id']) ? (int)$_GET['quiz_id'] : null;
$studentId = isset($_GET['student_id']) ? (int)$_GET['student_id'] : null;
$status = isset($_GET['status']) ? trim($_GET['status']) : null;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;

if ($isStudent) {
    $studentId = (int)$auth['id'];
}

if ($page < 1) $page = 1;
if ($limit < 1) $limit = 20;
if ($limit > 100) $limit = 100;

if ($id !== null && $id <= 0) {
    respond('error', 'Invalid attempt ID');
}

if ($quizId !== null && $quizId <= 0) {
    respond('error', 'Invalid quiz ID');
}

if ($studentId !== null && $studentId <= 0) {
    respond('error', 'Invalid student ID');
}

if ($status && !in_array($status, ['in_progress', 'submitted', 'pending_review', 'graded'])) {
    respond('error', 'Invalid status');
}

$offset = ($page - 1) * $limit;

if ($id) {
    if ($isStudent) {
        $stmt = $conn->prepare("
            SELECT 
                qa.*,
                q.title AS quiz_title,
                q.type AS quiz_type,
                s.full_name AS student_name,
                s.phone AS student_phone,
                s.email AS student_email,
                (
                    SELECT COALESCE(SUM(points), 0)
                    FROM quiz_questions
                    WHERE quiz_questions.quiz_id = qa.quiz_id
                ) AS total_points
            FROM quiz_attempts qa
            LEFT JOIN quizzes q ON q.id = qa.quiz_id
            LEFT JOIN students s ON s.id = qa.student_id
            WHERE qa.id = ?
            AND qa.student_id = ?
            LIMIT 1
        ");

        if (!$stmt) {
            respond('error', 'Failed to prepare attempt query');
        }

        $stmt->bind_param("ii", $id, $studentId);
    } else {
        $stmt = $conn->prepare("
            SELECT 
                qa.*,
                q.title AS quiz_title,
                q.type AS quiz_type,
                s.full_name AS student_name,
                s.phone AS student_phone,
                s.email AS student_email,
                (
                    SELECT COALESCE(SUM(points), 0)
                    FROM quiz_questions
                    WHERE quiz_questions.quiz_id = qa.quiz_id
                ) AS total_points
            FROM quiz_attempts qa
            LEFT JOIN quizzes q ON q.id = qa.quiz_id
            LEFT JOIN students s ON s.id = qa.student_id
            WHERE qa.id = ?
            LIMIT 1
        ");

        if (!$stmt) {
            respond('error', 'Failed to prepare attempt query');
        }

        $stmt->bind_param("i", $id);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();
        respond('error', 'Attempt not found');
    }

    $attempt = $result->fetch_assoc();
    $stmt->close();

    $totalPoints = (float)$attempt['total_points'];
    $score = $attempt['score'] !== null ? (float)$attempt['score'] : null;

    $attempt['score_percentage'] = ($score !== null && $totalPoints > 0)
        ? round(($score / $totalPoints) * 100, 2)
        : null;

    $stmt = $conn->prepare("
        SELECT 
            sqa.*,
            qq.question_type,
            qq.question_text,
            qq.points,
            qo.option_text AS selected_option_text
        FROM student_quiz_answers sqa
        LEFT JOIN quiz_questions qq ON qq.id = sqa.question_id
        LEFT JOIN quiz_options qo ON qo.id = sqa.selected_option_id
        WHERE sqa.attempt_id = ?
        ORDER BY sqa.id ASC
    ");

    if (!$stmt) {
        respond('error', 'Failed to prepare answers query');
    }

    $stmt->bind_param("i", $id);
    $stmt->execute();
    $answersResult = $stmt->get_result();

    $answers = [];

    while ($row = $answersResult->fetch_assoc()) {
        $answers[] = $row;
    }

    $stmt->close();

    $attempt['answers'] = $answers;

    respond('success', $attempt);
}

$conditions = [];
$params = [];
$types = '';

if ($quizId !== null) {
    $conditions[] = "qa.quiz_id = ?";
    $params[] = $quizId;
    $types .= 'i';
}

if ($studentId !== null) {
    $conditions[] = "qa.student_id = ?";
    $params[] = $studentId;
    $types .= 'i';
}

if ($status) {
    $conditions[] = "qa.status = ?";
    $params[] = $status;
    $types .= 's';
}

$whereClause = !empty($conditions)
    ? "WHERE " . implode(" AND ", $conditions)
    : "";

$countSql = "
    SELECT COUNT(*) AS total
    FROM quiz_attempts qa
    $whereClause
";

$stmt = $conn->prepare($countSql);

if (!$stmt) {
    respond('error', 'Failed to prepare count query');
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$countResult = $stmt->get_result();
$totalCount = (int)$countResult->fetch_assoc()['total'];
$stmt->close();

$sql = "
    SELECT 
        qa.*,
        q.title AS quiz_title,
        q.type AS quiz_type,
        s.full_name AS student_name,
        s.phone AS student_phone,
        s.email AS student_email,
        (
            SELECT COALESCE(SUM(points), 0)
            FROM quiz_questions
            WHERE quiz_questions.quiz_id = qa.quiz_id
        ) AS total_points
    FROM quiz_attempts qa
    LEFT JOIN quizzes q ON q.id = qa.quiz_id
    LEFT JOIN students s ON s.id = qa.student_id
    $whereClause
    ORDER BY qa.id DESC
    LIMIT ? OFFSET ?
";

$queryParams = $params;
$queryTypes = $types;

$queryParams[] = $limit;
$queryParams[] = $offset;
$queryTypes .= 'ii';

$stmt = $conn->prepare($sql);

if (!$stmt) {
    respond('error', 'Failed to prepare attempts query');
}

$stmt->bind_param($queryTypes, ...$queryParams);
$stmt->execute();
$result = $stmt->get_result();

$attempts = [];

while ($row = $result->fetch_assoc()) {
    $totalPoints = (float)$row['total_points'];
    $score = $row['score'] !== null ? (float)$row['score'] : null;

    $row['score_percentage'] = ($score !== null && $totalPoints > 0)
        ? round(($score / $totalPoints) * 100, 2)
        : null;

    $attempts[] = $row;
}

$stmt->close();

respond('success', [
    'attempts' => $attempts,
    'total' => $totalCount,
    'page' => $page,
    'limit' => $limit,
    'total_pages' => ceil($totalCount / $limit)
]);
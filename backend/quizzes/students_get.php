<?php

require_once '../config.php';
require_once '../helpers/student_access.php';

validateRequestMethod('GET');

$auth = requireAuth('student');
$studentId = (int)$auth['id'];

$quizId = isset($_GET['quiz_id']) ? (int)$_GET['quiz_id'] : null;
$itemId = isset($_GET['item_id']) ? (int)$_GET['item_id'] : null;

if (!$quizId && !$itemId) {
    respond('error', 'quiz_id or item_id is required');
}

if ($itemId) {
    if (!studentCanOpenItem($studentId, $itemId)) {
        respond('error', 'You do not have access to this item');
    }

    $stmt = $conn->prepare("
        SELECT 
            q.id,
            q.item_id,
            q.title,
            q.time_limit_minutes,
            q.type,
            q.attempt_limit,
            q.randomize_questions,
            q.randomize_answers,
            COUNT(qq.id) AS total_questions
        FROM quizzes q
        LEFT JOIN quiz_questions qq ON qq.quiz_id = q.id
        WHERE q.item_id = ?
        AND q.is_published = 1
        GROUP BY q.id
        ORDER BY q.id DESC
    ");

    $stmt->bind_param("i", $itemId);
    $stmt->execute();
    $result = $stmt->get_result();

    $quizzes = [];

    while ($row = $result->fetch_assoc()) {
        $quizzes[] = $row;
    }

    $stmt->close();

    respond('success', $quizzes);
}

$stmt = $conn->prepare("
    SELECT 
        q.id,
        q.item_id,
        q.title,
        q.time_limit_minutes,
        q.type,
        q.attempt_limit,
        q.randomize_questions,
        q.randomize_answers,
        COUNT(qq.id) AS total_questions
    FROM quizzes q
    LEFT JOIN quiz_questions qq ON qq.quiz_id = q.id
    WHERE q.id = ?
    AND q.is_published = 1
    GROUP BY q.id
    LIMIT 1
");

$stmt->bind_param("i", $quizId);
$stmt->execute();
$result = $stmt->get_result();
$quiz = $result->fetch_assoc();
$stmt->close();

if (!$quiz) {
    respond('error', 'Quiz not found');
}

if ($quiz['item_id'] !== null) {
    if (!studentCanOpenItem($studentId, (int)$quiz['item_id'])) {
        respond('error', 'You do not have access to this quiz');
    }
}

respond('success', $quiz);
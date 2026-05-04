<?php
require_once '../../config.php';
require_once '../../helpers/student_access.php';

validateRequestMethod('POST');

$auth = requireAuth('student');
$studentId = (int)$auth['id'];

$input = requireParams(['quiz_id']);
$quizId = (int)$input['quiz_id'];

$stmt = $conn->prepare("
    SELECT *
    FROM quizzes
    WHERE id = ?
    AND is_published = 1
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

$stmt = $conn->prepare("
    SELECT id
    FROM quiz_attempts
    WHERE quiz_id = ?
    AND student_id = ?
    AND status = 'in_progress'
    LIMIT 1
");

$stmt->bind_param("ii", $quizId, $studentId);
$stmt->execute();
$existingAttempt = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($existingAttempt) {
    $attemptId = (int)$existingAttempt['id'];
} else {
    if ($quiz['attempt_limit'] !== null) {
        $stmt = $conn->prepare("
            SELECT COUNT(*) AS total
            FROM quiz_attempts
            WHERE quiz_id = ?
            AND student_id = ?
            AND status IN ('submitted', 'pending_review', 'graded')
        ");

        $stmt->bind_param("ii", $quizId, $studentId);
        $stmt->execute();
        $count = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ((int)$count['total'] >= (int)$quiz['attempt_limit']) {
            respond('error', 'Attempt limit reached');
        }
    }

    $stmt = $conn->prepare("
        INSERT INTO quiz_attempts (
            quiz_id,
            student_id,
            status,
            started_at
        ) VALUES (?, ?, 'in_progress', NOW())
    ");

    $stmt->bind_param("ii", $quizId, $studentId);

    if (!$stmt->execute()) {
        $stmt->close();
        respond('error', 'Failed to start quiz attempt');
    }

    $attemptId = $stmt->insert_id;
    $stmt->close();
}

$orderQuestions = ((int)$quiz['randomize_questions'] === 1)
    ? "ORDER BY RAND()"
    : "ORDER BY id ASC";

$stmt = $conn->prepare("
    SELECT 
        id,
        quiz_id,
        question_type,
        points,
        question_text
    FROM quiz_questions
    WHERE quiz_id = ?
    $orderQuestions
");

$stmt->bind_param("i", $quizId);
$stmt->execute();
$questionsResult = $stmt->get_result();

$questions = [];

while ($question = $questionsResult->fetch_assoc()) {
    $questionId = (int)$question['id'];

    if ($question['question_type'] !== 'text') {
        $orderOptions = ((int)$quiz['randomize_answers'] === 1)
            ? "ORDER BY RAND()"
            : "ORDER BY id ASC";

        $stmtOptions = $conn->prepare("
            SELECT 
                id,
                question_id,
                option_text
            FROM quiz_options
            WHERE question_id = ?
            $orderOptions
        ");

        $stmtOptions->bind_param("i", $questionId);
        $stmtOptions->execute();
        $optionsResult = $stmtOptions->get_result();

        $options = [];

        while ($option = $optionsResult->fetch_assoc()) {
            $options[] = $option;
        }

        $stmtOptions->close();

        $question['options'] = $options;
    } else {
        $question['options'] = [];
    }

    $questions[] = $question;
}

$stmt->close();

respond('success', [
    'attempt_id' => $attemptId,
    'quiz' => [
        'id' => (int)$quiz['id'],
        'item_id' => $quiz['item_id'],
        'title' => $quiz['title'],
        'type' => $quiz['type'],
        'time_limit_minutes' => $quiz['time_limit_minutes'],
        'attempt_limit' => $quiz['attempt_limit']
    ],
    'questions' => $questions
]);
<?php

require_once '../../config.php';

validateRequestMethod('POST');

$auth = requireAuth('student');
$studentId = (int)$auth['id'];

$input = requireParams(['attempt_id']);
$attemptId = (int)$input['attempt_id'];

$stmt = $conn->prepare("
    SELECT 
        qa.id,
        qa.quiz_id,
        qa.student_id,
        qa.status,
        q.type
    FROM quiz_attempts qa
    JOIN quizzes q ON q.id = qa.quiz_id
    WHERE qa.id = ?
    AND qa.student_id = ?
    LIMIT 1
");

$stmt->bind_param("ii", $attemptId, $studentId);
$stmt->execute();
$attempt = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$attempt) {
    respond('error', 'Attempt not found');
}

if ($attempt['status'] !== 'in_progress') {
    respond('error', 'Attempt already submitted');
}

$conn->begin_transaction();

try {
    $stmt = $conn->prepare("
        SELECT 
            qq.id,
            qq.question_type,
            qq.points,
            sqa.id AS answer_id,
            sqa.selected_option_id,
            sqa.text_answer
        FROM quiz_questions qq
        LEFT JOIN student_quiz_answers sqa
            ON sqa.question_id = qq.id
            AND sqa.attempt_id = ?
        WHERE qq.quiz_id = ?
    ");

    $stmt->bind_param("ii", $attemptId, $attempt['quiz_id']);
    $stmt->execute();
    $questionsResult = $stmt->get_result();
    $stmt->close();

    $totalScore = 0;
    $hasTextQuestions = false;

    while ($question = $questionsResult->fetch_assoc()) {
        $questionId = (int)$question['id'];
        $points = (float)$question['points'];
        $answerId = $question['answer_id'] ? (int)$question['answer_id'] : null;

        if ($question['question_type'] === 'text') {
            $hasTextQuestions = true;

            if ($answerId) {
                $stmt = $conn->prepare("
                    UPDATE student_quiz_answers
                    SET is_correct = NULL, grade = NULL
                    WHERE id = ?
                ");
                $stmt->bind_param("i", $answerId);
                $stmt->execute();
                $stmt->close();
            }

            continue;
        }

        if (!$answerId || !$question['selected_option_id']) {
            continue;
        }

        $selectedOptionId = (int)$question['selected_option_id'];

        $stmt = $conn->prepare("
            SELECT is_correct
            FROM quiz_options
            WHERE id = ?
            AND question_id = ?
            LIMIT 1
        ");

        $stmt->bind_param("ii", $selectedOptionId, $questionId);
        $stmt->execute();
        $option = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$option) {
            continue;
        }

        $isCorrect = (int)$option['is_correct'];
        $grade = $isCorrect ? $points : 0;

        $totalScore += $grade;

        $stmt = $conn->prepare("
            UPDATE student_quiz_answers
            SET is_correct = ?, grade = ?
            WHERE id = ?
        ");

        $stmt->bind_param("idi", $isCorrect, $grade, $answerId);
        $stmt->execute();
        $stmt->close();
    }

    $newStatus = $hasTextQuestions ? 'pending_review' : 'graded';

    $stmt = $conn->prepare("
        UPDATE quiz_attempts
        SET 
            status = ?,
            score = ?,
            submitted_at = NOW()
        WHERE id = ?
    ");

    $stmt->bind_param("sdi", $newStatus, $totalScore, $attemptId);
    $stmt->execute();
    $stmt->close();

    $conn->commit();

    respond('success', [
        'attempt_id' => $attemptId,
        'status' => $newStatus,
        'score' => $totalScore,
        'message' => $hasTextQuestions
            ? 'Attempt submitted successfully and is pending review'
            : 'Attempt submitted and graded successfully'
    ]);

} catch (Exception $e) {
    $conn->rollback();
    respond('error', $e->getMessage());
}
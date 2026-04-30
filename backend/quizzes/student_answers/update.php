<?php

require_once '../../config.php';

validateRequestMethod('POST');
$auth = requireAuth(['super_admin', 'admin', 'assistant']);
$input = requireParams(['answer_id', 'grade']);

$answerId = (int)$input['answer_id'];
$grade = (float)$input['grade'];

if ($grade < 0) {
    respond('error', 'Grade cannot be negative');
}

$conn->begin_transaction();

try {
    $stmt = $conn->prepare("
        SELECT 
            sqa.*,
            qq.points,
            qq.question_type
        FROM student_quiz_answers sqa
        INNER JOIN quiz_questions qq ON qq.id = sqa.question_id
        WHERE sqa.id = ?
        FOR UPDATE
    ");

    $stmt->bind_param("i", $answerId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();
        throw new Exception('Answer not found');
    }

    $answer = $result->fetch_assoc();
    $stmt->close();

    if ($answer['question_type'] !== 'text') {
        throw new Exception('Only text answers can be manually graded');
    }

    $points = (float)$answer['points'];

    if ($grade > $points) {
        throw new Exception('Grade cannot exceed question points');
    }

    $isCorrect = $grade > 0 ? 1 : 0;

    $stmt = $conn->prepare("
        UPDATE student_quiz_answers
        SET grade = ?,
            is_correct = ?
            WHERE id = ?
    ");

    if (!$stmt) {
        throw new Exception('Failed to prepare answer update');
    }

    $stmt->bind_param("dii", $grade, $isCorrect, $answerId);
    $stmt->execute();
    $stmt->close();

    $attemptId = (int)$answer['attempt_id'];

    $stmt = $conn->prepare("
        SELECT 
            SUM(COALESCE(grade, 0)) AS total_score,
            SUM(
                CASE 
                    WHEN qq.question_type = 'text' 
                    AND sqa.grade IS NULL 
                    THEN 1 
                    ELSE 0 
                END
            ) AS pending_text_answers
        FROM student_quiz_answers sqa
        INNER JOIN quiz_questions qq ON qq.id = sqa.question_id
        WHERE sqa.attempt_id = ?
    ");

    $stmt->bind_param("i", $attemptId);
    $stmt->execute();
    $scoreResult = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $totalScore = (float)$scoreResult['total_score'];
    $pendingTextAnswers = (int)$scoreResult['pending_text_answers'];

    $newStatus = $pendingTextAnswers > 0 ? 'pending_review' : 'graded';

    $stmt = $conn->prepare("
        UPDATE quiz_attempts
        SET score = ?,
            status = ?
        WHERE id = ?
    ");
    $stmt->bind_param("dsi", $totalScore, $newStatus, $attemptId);
    $stmt->execute();
    $stmt->close();

    logAction(
        $auth['id'],
        'grade_text_answer',
        'student_quiz_answers',
        $answerId,
        json_encode([
            'attempt_id' => $attemptId,
            'grade' => $grade,
            'status' => $newStatus
        ])
    );

    $conn->commit();

    respond('success', [
        'message' => 'Answer graded successfully',
        'attempt_id' => $attemptId,
        'score' => $totalScore,
        'status' => $newStatus
    ]);

} catch (Exception $e) {
    $conn->rollback();
    respond('error', $e->getMessage());
}
<?php

require_once '../../config.php';

validateRequestMethod('POST');
$auth = requireAuth(['super_admin', 'admin', 'assistant']);

$input = requireParams(['id']);

$id = (int)$input['id'];
$grade = isset($input['grade']) ? (float)$input['grade'] : null;
$feedback = isset($input['feedback']) ? trim($input['feedback']) : null;

if ($id <= 0) {
    respond('error', 'Invalid submission ID');
}

if ($grade !== null && $grade < 0) {
    respond('error', 'Grade cannot be negative');
}

$conn->begin_transaction();

try {
    $stmt = $conn->prepare("
        SELECT *
        FROM assignment_submissions
        WHERE id = ?
        FOR UPDATE
    ");
    
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();
        throw new Exception('Submission not found');
    }

    $submission = $result->fetch_assoc();
    $stmt->close();

    $updates = [];
    $params = [];
    $types = '';

    if ($grade !== null) {
        $updates[] = "grade = ?";
        $params[] = $grade;
        $types .= 'd';
    }

    if ($feedback !== null) {
        $updates[] = "feedback = ?";
        $params[] = $feedback;
        $types .= 's';
    }

    if (empty($updates)) {
        throw new Exception('Nothing to update');
    }

    $sql = "UPDATE assignment_submissions SET " . implode(', ', $updates) . " WHERE id = ?";
    $params[] = $id;
    $types .= 'i';

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception('Failed to prepare update query');
    }

    $stmt->bind_param($types, ...$params);
    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        $stmt->close();
        throw new Exception('Submission was not updated');
    }

    $stmt->close();

    logAction(
        $auth['id'],
        'grade_assignment_submission',
        'assignment_submissions',
        $id,
        json_encode([
            'grade' => $grade,
            'feedback' => $feedback
        ])
    );

    $conn->commit();

    respond('success', 'Submission updated successfully');

} catch (Exception $e) {
    $conn->rollback();
    respond('error', $e->getMessage());
}
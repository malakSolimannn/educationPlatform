<?php
require_once __DIR__ . '/../config.php';

function studentHasDirectAccess($studentId, $itemId)
{
    global $conn;

    $stmt = $conn->prepare("
        SELECT id
        FROM student_access
        WHERE student_id = ?
        AND item_id = ?
        AND status = 'active'
        AND (end_date IS NULL OR end_date >= NOW())
        LIMIT 1
    ");

    $stmt->bind_param("ii", $studentId, $itemId);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    return $result->num_rows > 0;
}

function getItemForAccessCheck($itemId)
{
    global $conn;

    $stmt = $conn->prepare("
        SELECT id, parent_id, item_type, is_free, is_published
        FROM items
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->bind_param("i", $itemId);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    if ($result->num_rows === 0) {
        return null;
    }

    return $result->fetch_assoc();
}

function studentHasAccessToItem($studentId, $itemId)
{
    $item = getItemForAccessCheck($itemId);

    if (!$item) {
        return false;
    }

    if ((int)$item['is_published'] !== 1) {
        return false;
    }

    if ((int)$item['is_free'] === 1) {
        return true;
    }

    if (studentHasDirectAccess($studentId, $itemId)) {
        return true;
    }

    return false;
}
function studentMeetsItemPrerequisites($studentId, $itemId)
{
    global $conn;

    $stmt = $conn->prepare("
        SELECT *
        FROM item_prerequisites
        WHERE item_id = ?
    ");

    $stmt->bind_param("i", $itemId);
    $stmt->execute();
    $result = $stmt->get_result();

    $prerequisites = [];

    while ($row = $result->fetch_assoc()) {
        $prerequisites[] = $row;
    }

    $stmt->close();

    foreach ($prerequisites as $prerequisite) {
        if ($prerequisite['requirement_type'] === 'lesson_completed') {
            $requiredItemId = (int)$prerequisite['prerequisite_item_id'];

            $stmt = $conn->prepare("
                SELECT id
                FROM lesson_progress
                WHERE student_id = ?
                AND item_id = ?
                AND is_completed = 1
                LIMIT 1
            ");

            $stmt->bind_param("ii", $studentId, $requiredItemId);
            $stmt->execute();
            $check = $stmt->get_result();
            $stmt->close();

            if ($check->num_rows === 0) {
                return false;
            }
        }

        if ($prerequisite['requirement_type'] === 'quiz_passed') {
            $requiredQuizId = (int)$prerequisite['prerequisite_quiz_id'];
            $requiredScore = $prerequisite['required_score'];

            $query = "
                SELECT id
                FROM quiz_attempts
                WHERE student_id = ?
                AND quiz_id = ?
                AND status = 'graded'
            ";

            if ($requiredScore !== null) {
                $query .= " AND score >= ?";
            }

            $query .= " LIMIT 1";

            $stmt = $conn->prepare($query);

            if ($requiredScore !== null) {
                $requiredScore = (float)$requiredScore;
                $stmt->bind_param("iid", $studentId, $requiredQuizId, $requiredScore);
            } else {
                $stmt->bind_param("ii", $studentId, $requiredQuizId);
            }

            $stmt->execute();
            $check = $stmt->get_result();
            $stmt->close();

            if ($check->num_rows === 0) {
                return false;
            }
        }
    }

    return true;
}
function studentCanOpenItem($studentId, $itemId)
{
    if (!studentHasAccessToItem($studentId, $itemId)) {
        return false;
    }

    if (!studentMeetsItemPrerequisites($studentId, $itemId)) {
        return false;
    }

    return true;
}

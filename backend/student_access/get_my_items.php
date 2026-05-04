<?php

require_once '../config.php';

validateRequestMethod('GET');

$auth = requireAuth('student');
$studentId = (int)$auth['id'];

$stmt = $conn->prepare("
    SELECT
        sa.id,
        sa.student_id,
        sa.item_id,
        sa.start_date,
        sa.end_date,
        sa.status,
        i.title,
        i.item_type,
        i.description,
        i.image_url,
        i.access_type
    FROM student_access sa
    JOIN items i ON i.id = sa.item_id
    WHERE sa.student_id = ?
    AND i.is_published = 1
    ORDER BY sa.start_date DESC, sa.id DESC
");
$stmt->bind_param("i", $studentId);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

$items = [];

while ($row = $result->fetch_assoc()) {
    $items[] = $row;
}

respond('success', $items);

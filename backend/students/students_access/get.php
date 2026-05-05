<?php

require_once '../../config.php';

validateRequestMethod('GET');
requireAuth(['super_admin', 'admin', 'assistant']);

$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : null;
$item_id = isset($_GET['item_id']) ? (int)$_GET['item_id'] : null;

$query = "SELECT * FROM student_access WHERE 1=1";
$params = [];
$types = "";

if ($student_id) {
    $query .= " AND student_id = ?";
    $params[] = $student_id;
    $types .= "i";
}

if ($item_id) {
    $query .= " AND item_id = ?";
    $params[] = $item_id;
    $types .= "i";
}

$query .= " ORDER BY start_date DESC";

$stmt = $conn->prepare($query);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

respond('success', $data);
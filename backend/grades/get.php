<?php

require_once '../config.php';

validateRequestMethod('GET');
requireAuth(['super_admin', 'admin', 'assistant']);

$gradeId = isset($_GET['id']) ? (int)$_GET['id'] : null;

if ($gradeId) {
    $stmt = $conn->prepare("SELECT id, name FROM grades WHERE id = ?");
    $stmt->bind_param("i", $gradeId);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    if ($result->num_rows === 0) {
        respond('error', 'Grade not found');
    }

    $grade = $result->fetch_assoc();
    respond('success', $grade);
} else {
    $stmt = $conn->prepare("SELECT id, name FROM grades ORDER BY id ASC");
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    $grades = [];
    while ($row = $result->fetch_assoc()) {
        $grades[] = $row;
    }

    respond('success', $grades);
}
<?php

require_once '../config.php';

validateRequestMethod('GET');
requireAuth(['super_admin', 'admin', 'assistant']);

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$search = isset($_GET['search']) ? trim($_GET['search']) : null;

if ($id) {
    $stmt = $conn->prepare("SELECT * FROM announcements WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    if ($result->num_rows === 0) {
        respond('error', 'Announcement not found');
    }

    respond('success', $result->fetch_assoc());
}

if ($search) {
    $stmt = $conn->prepare("
        SELECT * 
        FROM announcements 
        WHERE title LIKE ? OR message LIKE ? 
        ORDER BY created_at DESC
    ");

    $searchParam = "%$search%";
    $stmt->bind_param("ss", $searchParam, $searchParam);
} else {
    $stmt = $conn->prepare("
        SELECT * 
        FROM announcements 
        ORDER BY created_at DESC
    ");
}

$stmt->execute();
$result = $stmt->get_result();

$announcements = [];

while ($row = $result->fetch_assoc()) {
    $announcements[] = $row;
}

$stmt->close();

respond('success', $announcements);
<?php

require_once '../config.php';

validateRequestMethod('GET');
requireAuth(['super_admin', 'admin']);

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;

if ($page < 1) $page = 1;
if ($limit > 100) $limit = 100;

$offset = ($page - 1) * $limit;

if ($id) {
    $stmt = $conn->prepare("SELECT * FROM payments WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        respond('error', 'Payment not found');
    }

    respond('success', $result->fetch_assoc());
}

$stmt = $conn->prepare("SELECT * FROM payments ORDER BY id DESC LIMIT ? OFFSET ?");
$stmt->bind_param("ii", $limit, $offset);
$stmt->execute();

$result = $stmt->get_result();

$payments = [];
while ($row = $result->fetch_assoc()) {
    $payments[] = $row;
}

respond('success', $payments);
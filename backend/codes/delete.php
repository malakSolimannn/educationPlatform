<?php

require_once '../config.php';

validateRequestMethod('POST');
$auth = requireAuth(['super_admin', 'admin']);

$input = requireParams(['id']);
$id = (int)$input['id'];

$stmt = $conn->prepare("SELECT * FROM codes WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    respond('error', 'Code not found');
}

$codeRow = $result->fetch_assoc();
$stmt->close();

if ((int)$codeRow['is_used'] === 1) {
    respond('error', 'Used codes cannot be deleted');
}

$stmt = $conn->prepare("DELETE FROM codes WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();

if ($stmt->affected_rows === 0) {
    $stmt->close();
    respond('error', 'Code was not deleted');
}

$stmt->close();

logAction(
    $auth['id'],
    'delete_code',
    'codes',
    $id,
    json_encode($codeRow)
);

respond('success', 'Code deleted successfully');
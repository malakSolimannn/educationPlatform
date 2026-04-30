<?php

require_once '../config.php';

validateRequestMethod('POST');
$auth = requireAuth(['super_admin']);

$input = requireParams(['id']);
$id = (int)$input['id'];

$stmt = $conn->prepare("DELETE FROM payments WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();

if ($stmt->affected_rows === 0) {
    $stmt->close();
    respond('error', 'Payment not found or not deleted');
}

$stmt->close();

logAction($auth['id'], 'delete_payment', 'payment', $id, "Deleted payment ID: $id");

respond('success', 'Payment deleted successfully');
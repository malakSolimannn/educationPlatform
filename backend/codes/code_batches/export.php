<?php

require_once '../../config.php';

validateRequestMethod('GET');
requireAuth(['super_admin', 'admin', 'assistant']);

$batchId = isset($_GET['batch_id']) ? (int)$_GET['batch_id'] : null;

if (!$batchId || $batchId <= 0) {
    respond('error', 'Valid batch_id is required');
}

$stmt = $conn->prepare("
    SELECT 
        c.id,
        c.code,
        c.code_type,
        c.wallet_amount,
        c.item_id,
        i.title AS item_title,
        c.is_used,
        c.used_by_student_id,
        s.full_name AS used_by_student_name,
        c.used_at,
        c.expires_at,
        c.created_at,
        cb.id AS batch_id,
        cb.center_id,
        centers.name AS center_name
    FROM codes c
    LEFT JOIN code_batches cb ON cb.id = c.batch_id
    LEFT JOIN centers ON centers.id = cb.center_id
    LEFT JOIN items i ON i.id = c.item_id
    LEFT JOIN students s ON s.id = c.used_by_student_id
    WHERE c.batch_id = ?
    ORDER BY c.id ASC
");

if (!$stmt) {
    respond('error', 'Failed to prepare export query');
}

$stmt->bind_param("i", $batchId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    respond('error', 'No codes found for this batch');
}

$filename = 'codes_batch_' . $batchId . '_' . date('Y-m-d_H-i-s') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

fputcsv($output, [
    'Batch ID',
    'Center ID',
    'Center Name',
    'Code ID',
    'Code',
    'Code Type',
    'Wallet Amount',
    'Item ID',
    'Item Title',
    'Is Used',
    'Used By Student ID',
    'Used By Student Name',
    'Used At',
    'Expires At',
    'Created At'
]);

while ($row = $result->fetch_assoc()) {
    fputcsv($output, [
        $row['batch_id'],
        $row['center_id'],
        $row['center_name'],
        $row['id'],
        $row['code'],
        $row['code_type'],
        $row['wallet_amount'],
        $row['item_id'],
        $row['item_title'],
        $row['is_used'],
        $row['used_by_student_id'],
        $row['used_by_student_name'],
        $row['used_at'],
        $row['expires_at'],
        $row['created_at']
    ]);
}

fclose($output);
$stmt->close();
exit;
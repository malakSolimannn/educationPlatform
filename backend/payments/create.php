<?php

require_once '../config.php';

function grantItemAccess($studentId, $itemId) {
    global $conn;

    $itemsToGrant = [$itemId];

    $stmt = $conn->prepare("
        SELECT grants_item_id
        FROM item_access_map
        WHERE item_id = ?
    ");

    $stmt->bind_param("i", $itemId);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $itemsToGrant[] = (int)$row['grants_item_id'];
    }

    $stmt->close();

    $itemsToGrant = array_unique($itemsToGrant);

    foreach ($itemsToGrant as $grantItemId) {
        $stmt = $conn->prepare("
            SELECT id, access_type, duration_days
            FROM items
            WHERE id = ?
        ");

        $stmt->bind_param("i", $grantItemId);
        $stmt->execute();
        $itemResult = $stmt->get_result();

        if ($itemResult->num_rows === 0) {
            $stmt->close();
            continue;
        }

        $item = $itemResult->fetch_assoc();
        $stmt->close();

        $endDate = null;

        if ($item['access_type'] === 'limited') {
            $durationDays = (int)$item['duration_days'];

            if ($durationDays <= 0) {
                throw new Exception('Limited item must have duration_days');
            }

            $endDate = date('Y-m-d H:i:s', strtotime("+$durationDays days"));
        }

        $stmt = $conn->prepare("
            INSERT INTO student_access
            (student_id, item_id, start_date, end_date, status)
            VALUES (?, ?, NOW(), ?, 'active')
            ON DUPLICATE KEY UPDATE
                start_date = VALUES(start_date),
                end_date = VALUES(end_date),
                status = 'active'
        ");

        $stmt->bind_param("iis", $studentId, $grantItemId, $endDate);
        $stmt->execute();
        $stmt->close();
    }
}

validateRequestMethod('POST');
$auth = requireAuth(['super_admin', 'admin']);

$input = requireParams(['payment_type', 'amount']);

$paymentType = trim($input['payment_type']);
$amount = (float)$input['amount'];

$studentId = isset($input['student_id']) ? (int)$input['student_id'] : null;
$centerId = isset($input['center_id']) ? (int)$input['center_id'] : null;
$itemId = isset($input['item_id']) ? (int)$input['item_id'] : null;
$batchId = isset($input['batch_id']) ? (int)$input['batch_id'] : null;
$paymentMethod = isset($input['payment_method']) ? trim($input['payment_method']) : null;
$status = isset($input['status']) ? trim($input['status']) : 'completed';
$notes = isset($input['notes']) ? trim($input['notes']) : null;

if ($amount <= 0) {
    respond('error', 'Invalid amount');
}

if ($paymentType === 'direct_item_purchase') {
    if (!$studentId || !$itemId) {
        respond('error', 'student_id and item_id are required');
    }
} elseif ($paymentType === 'center_batch_purchase') {
    if (!$centerId || !$batchId) {
        respond('error', 'center_id and batch_id are required');
    }
} else {
    respond('error', 'Invalid payment type');
}

$conn->begin_transaction();

try {
    $stmt = $conn->prepare("
        INSERT INTO payments
        (student_id, center_id, item_id, batch_id, payment_type, amount, payment_method, status, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "iiiisdsss",
        $studentId,
        $centerId,
        $itemId,
        $batchId,
        $paymentType,
        $amount,
        $paymentMethod,
        $status,
        $notes
    );

    $stmt->execute();

    $id = $stmt->insert_id;
    $stmt->close();

    if ($paymentType === 'direct_item_purchase' && $status === 'completed') {
        grantItemAccess($studentId, $itemId);
    }

    logAction($auth['id'], 'create_payment', 'payment', $id, json_encode([
        'payment_type' => $paymentType,
        'amount' => $amount,
        'status' => $status,
        'student_id' => $studentId,
        'center_id' => $centerId,
        'item_id' => $itemId,
        'batch_id' => $batchId
    ]));

    $conn->commit();

    respond('success', [
        'id' => $id,
        'message' => 'Payment created successfully'
    ]);

} catch (Exception $e) {
    $conn->rollback();
    respond('error', $e->getMessage());
}
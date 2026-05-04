<?php

require_once '../config.php';

validateRequestMethod('POST');
$auth = requireAuth(['student']);

$input = requireParams(['item_id', 'payment_method']);

$studentId = (int)$auth['id'];
$itemId = (int)$input['item_id'];
$paymentMethod = trim($input['payment_method']);
$notes = isset($input['notes']) ? trim($input['notes']) : null;

if ($itemId <= 0) {
    respond('error', 'Invalid item ID');
}

$allowedPaymentMethods = ['wallet', 'cash', 'instapay', 'vodafone_cash', 'card'];

if (!in_array($paymentMethod, $allowedPaymentMethods)) {
    respond('error', 'Invalid payment method');
}

function grantItemAccess($studentId, $itemId) {
    global $conn;

    $itemsToGrant = [$itemId];

    $stmt = $conn->prepare("
        SELECT grants_item_id
        FROM item_access_map
        WHERE item_id = ?
    ");

    if (!$stmt) {
        throw new Exception('Failed to prepare item access map query');
    }

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
            LIMIT 1
        ");

        if (!$stmt) {
            throw new Exception('Failed to prepare granted item query');
        }

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

        if (!$stmt) {
            throw new Exception('Failed to prepare student access query');
        }

        $stmt->bind_param("iis", $studentId, $grantItemId, $endDate);
        $stmt->execute();
        $stmt->close();
    }
}

$conn->begin_transaction();

try {
    $stmt = $conn->prepare("
        SELECT id, title, price, access_type, duration_days
        FROM items
        WHERE id = ?
        LIMIT 1
        FOR UPDATE
    ");

    if (!$stmt) {
        throw new Exception('Failed to prepare item query');
    }

    $stmt->bind_param("i", $itemId);
    $stmt->execute();
    $itemResult = $stmt->get_result();

    if ($itemResult->num_rows === 0) {
        $stmt->close();
        throw new Exception('Item not found');
    }

    $item = $itemResult->fetch_assoc();
    $stmt->close();

    $amount = (float)$item['price'];

    if ($amount <= 0) {
        throw new Exception('Invalid item price');
    }

    $stmt = $conn->prepare("
        SELECT id
        FROM student_access
        WHERE student_id = ?
        AND item_id = ?
        AND status = 'active'
        AND (end_date IS NULL OR end_date >= NOW())
        LIMIT 1
    ");

    if (!$stmt) {
        throw new Exception('Failed to prepare access check query');
    }

    $stmt->bind_param("ii", $studentId, $itemId);
    $stmt->execute();
    $accessResult = $stmt->get_result();

    if ($accessResult->num_rows > 0) {
        $stmt->close();
        throw new Exception('You already have access to this item');
    }

    $stmt->close();

    $paymentType = 'direct_item_purchase';

    if ($paymentMethod === 'wallet') {
        $stmt = $conn->prepare("
            SELECT wallet_balance
            FROM students
            WHERE id = ?
            LIMIT 1
            FOR UPDATE
        ");

        if (!$stmt) {
            throw new Exception('Failed to prepare student wallet query');
        }

        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        $studentResult = $stmt->get_result();

        if ($studentResult->num_rows === 0) {
            $stmt->close();
            throw new Exception('Student not found');
        }

        $student = $studentResult->fetch_assoc();
        $stmt->close();

        $walletBalance = (float)$student['wallet_balance'];

        if ($walletBalance < $amount) {
            throw new Exception('Insufficient wallet balance');
        }

        $stmt = $conn->prepare("
            UPDATE students
            SET wallet_balance = wallet_balance - ?
            WHERE id = ?
            AND wallet_balance >= ?
        ");

        if (!$stmt) {
            throw new Exception('Failed to prepare wallet deduction query');
        }

        $stmt->bind_param("did", $amount, $studentId, $amount);
        $stmt->execute();

        if ($stmt->affected_rows === 0) {
            $stmt->close();
            throw new Exception('Failed to deduct wallet balance');
        }

        $stmt->close();

        $status = 'completed';
    } else {
        $status = 'pending';
    }

    $centerId = null;
    $batchId = null;

    $stmt = $conn->prepare("
        INSERT INTO payments
        (student_id, center_id, item_id, batch_id, payment_type, amount, payment_method, status, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    if (!$stmt) {
        throw new Exception('Failed to prepare payment insert');
    }

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
    $paymentId = $stmt->insert_id;
    $stmt->close();

    if ($status === 'completed') {
        grantItemAccess($studentId, $itemId);
    }

    logAction($auth['id'], 'student_direct_purchase', 'payments', $paymentId, json_encode([
        'student_id' => $studentId,
        'item_id' => $itemId,
        'amount' => $amount,
        'payment_method' => $paymentMethod,
        'status' => $status
    ]));

    $conn->commit();

    respond('success', [
        'id' => $paymentId,
        'message' => $status === 'completed'
            ? 'Item purchased successfully'
            : 'Payment request created successfully',
        'item_id' => $itemId,
        'amount' => $amount,
        'payment_method' => $paymentMethod,
        'status' => $status
    ]);

} catch (Exception $e) {
    $conn->rollback();
    respond('error', $e->getMessage());
}
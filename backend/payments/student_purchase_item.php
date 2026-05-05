<?php

require_once '../config.php';
require_once '../helpers/access_helper.php';
require_once '../helpers/student_access.php';

validateRequestMethod('POST');

$auth = requireAuth('student');
$studentId = (int)$auth['id'];

$input = requireParams(['item_id']);

$itemId = (int)$input['item_id'];
$paymentMethod = isset($input['payment_method']) ? trim($input['payment_method']) : null;

if ($itemId <= 0) {
    respond('error', 'Invalid item ID');
}

$stmt = $conn->prepare("
    SELECT id, title, price, is_free, is_published
    FROM items
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $itemId);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$item) {
    respond('error', 'Item not found');
}

if ((int)$item['is_published'] !== 1) {
    respond('error', 'Item is not available');
}

if (studentHasDirectAccess($studentId, $itemId)) {
    respond('error', 'You already have access to this item');
}

$isFree = ((int)$item['is_free'] === 1 || (float)$item['price'] <= 0);
$price = (float)$item['price'];

if ($isFree) {
    try {
        grantItemAccess($studentId, $itemId);

        respond('success', [
            'message' => 'Free item unlocked successfully',
            'item_id' => $itemId,
            'is_free' => 1
        ]);
    } catch (Exception $e) {
        respond('error', $e->getMessage());
    }
}


if (!$paymentMethod) {
    respond('error', 'Payment method is required for paid items');
}

$conn->begin_transaction();

try {
    $stmt = $conn->prepare("
        SELECT wallet_balance
        FROM students
        WHERE id = ?
        AND status = 'active'
        LIMIT 1
        FOR UPDATE
    ");

    $stmt->bind_param("i", $studentId);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$student) {
        throw new Exception('Student not found or inactive');
    }

    $walletBalance = (float)$student['wallet_balance'];

    if ($walletBalance < $price) {
        throw new Exception('Insufficient wallet balance');
    }

    $newBalance = $walletBalance - $price;

    $stmt = $conn->prepare("
        UPDATE students
        SET wallet_balance = ?
        WHERE id = ?
    ");

    $stmt->bind_param("di", $newBalance, $studentId);
    $stmt->execute();
    $stmt->close();

    $paymentType = 'direct_item_purchase';
    $status = 'completed';
    $notes = 'Student purchased item using wallet';

    $stmt = $conn->prepare("
        INSERT INTO payments (
            student_id,
            item_id,
            payment_type,
            amount,
            payment_method,
            status,
            notes
        ) VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "iisdsss",
        $studentId,
        $itemId,
        $paymentType,
        $price,
        $paymentMethod,
        $status,
        $notes
    );

    $stmt->execute();
    $paymentId = $stmt->insert_id;
    $stmt->close();

    grantItemAccess($studentId, $itemId);

    $conn->commit();

    respond('success', [
        'message' => 'Item purchased successfully',
        'payment_id' => $paymentId,
        'item_id' => $itemId,
        'amount' => $price,
        'wallet_balance' => $newBalance
    ]);

} catch (Exception $e) {
    $conn->rollback();
    respond('error', $e->getMessage());
}
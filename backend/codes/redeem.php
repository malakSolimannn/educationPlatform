<?php

require_once '../config.php';
validateRequestMethod('POST');
$auth = requireAuth(['super_admin', 'admin', 'assistant']);

$input = requireParams(['id', 'student_id']);
$id = (int)$input['id'];
$studentId = (int)$input['student_id'];

function validateStudentExists($studentId) {
    global $conn;

    $stmt = $conn->prepare("SELECT id FROM students WHERE id = ? AND status = 'active'");
    $stmt->bind_param("i", $studentId);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    if ($result->num_rows === 0) {
        throw new Exception('Student not found or inactive');
    }
}

function validateItemExists($itemId) {
    global $conn;

    $stmt = $conn->prepare("SELECT id FROM items WHERE id = ?");
    $stmt->bind_param("i", $itemId);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    if ($result->num_rows === 0) {
        throw new Exception('Item not found');
    }
}

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

$conn->begin_transaction();

try {
    validateStudentExists($studentId);

    $stmt = $conn->prepare("SELECT * FROM codes WHERE id = ? FOR UPDATE");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();
        throw new Exception('Code not found');
    }

    $codeRow = $result->fetch_assoc();
    $stmt->close();

    if ((int)$codeRow['is_used'] === 1) {
        throw new Exception('Code already used');
    }

    if (!empty($codeRow['expires_at']) && strtotime($codeRow['expires_at']) < time()) {
        throw new Exception('Code is expired');
    }

    if ($codeRow['code_type'] === 'wallet') {
        $amount = (float)$codeRow['wallet_amount'];

        if ($amount <= 0) {
            throw new Exception('Invalid wallet amount');
        }

        $stmt = $conn->prepare("
            UPDATE students
            SET wallet_balance = wallet_balance + ?
            WHERE id = ?
        ");

        $stmt->bind_param("di", $amount, $studentId);
        $stmt->execute();
        $stmt->close();
    }

    if ($codeRow['code_type'] === 'item') {
        $itemId = (int)$codeRow['item_id'];

        if ($itemId <= 0) {
            throw new Exception('Invalid item ID');
        }

        validateItemExists($itemId);
        grantItemAccess($studentId, $itemId);
    }

    $stmt = $conn->prepare("
        UPDATE codes
        SET is_used = 1,
            used_by_student_id = ?,
            used_at = NOW()
        WHERE id = ? AND is_used = 0
    ");

    $stmt->bind_param("ii", $studentId, $id);
    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        $stmt->close();
        throw new Exception('Code was not redeemed');
    }

    $stmt->close();

    logAction(
        $auth['id'],
        'redeem_code',
        'codes',
        $id,
        json_encode([
            'student_id' => $studentId,
            'code' => $codeRow['code'],
            'code_type' => $codeRow['code_type']
        ])
    );

    $conn->commit();

    respond('success', 'Code redeemed successfully');

} catch (Exception $e) {
    $conn->rollback();
    respond('error', $e->getMessage());
}
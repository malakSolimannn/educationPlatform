<?php

require_once '../config.php';

validateRequestMethod('POST');

$auth = requireAuth(['super_admin', 'admin', 'assistant', 'student']);

$isStudent = $auth['auth_type'] === 'student';

$input = requireParams(['code']);

$code = trim($input['code']);

if ($code === '') {
    respond('error', 'Code is required');
}

if ($isStudent) {
    $studentId = (int)$auth['id'];
} else {
    if (!isset($input['student_id'])) {
        respond('error', 'student_id is required');
    }

    $studentId = (int)$input['student_id'];

    if ($studentId <= 0) {
        respond('error', 'Invalid student ID');
    }
}

function validateStudentExists($studentId) {
    global $conn;

    $stmt = $conn->prepare("
        SELECT id 
        FROM students 
        WHERE id = ? 
        AND status = 'active'
        LIMIT 1
    ");

    if (!$stmt) {
        throw new Exception('Failed to prepare student query');
    }

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

    $stmt = $conn->prepare("
        SELECT id 
        FROM items 
        WHERE id = ? AND is_published = 1
        LIMIT 1
    ");

    if (!$stmt) {
        throw new Exception('Failed to prepare item query');
    }

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
    validateStudentExists($studentId);

    $stmt = $conn->prepare("
        SELECT *
        FROM codes
        WHERE code = ?
        LIMIT 1
        FOR UPDATE
    ");

    if (!$stmt) {
        throw new Exception('Failed to prepare code query');
    }

    $stmt->bind_param("s", $code);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();
        throw new Exception('Invalid code');
    }

    $codeRow = $result->fetch_assoc();
    $stmt->close();

    $codeId = (int)$codeRow['id'];

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

        if (!$stmt) {
            throw new Exception('Failed to prepare wallet update');
        }

        $stmt->bind_param("di", $amount, $studentId);
        $stmt->execute();
        $stmt->close();

    } elseif ($codeRow['code_type'] === 'item') {
        $itemId = (int)$codeRow['item_id'];

        if ($itemId <= 0) {
            throw new Exception('Invalid item ID');
        }

        validateItemExists($itemId);
        grantItemAccess($studentId, $itemId);

    } else {
        throw new Exception('Invalid code type');
    }

    $stmt = $conn->prepare("
        UPDATE codes
        SET 
            is_used = 1,
            used_by_student_id = ?,
            used_at = NOW()
        WHERE id = ?
        AND is_used = 0
    ");

    if (!$stmt) {
        throw new Exception('Failed to prepare code update');
    }

    $stmt->bind_param("ii", $studentId, $codeId);
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
        $codeId,
        json_encode([
            'student_id' => $studentId,
            'code' => $codeRow['code'],
            'code_type' => $codeRow['code_type'],
            'redeemed_by_role' => $auth['auth_type']
        ])
    );

    $conn->commit();

    respond('success', [
        'message' => 'Code redeemed successfully',
        'code_type' => $codeRow['code_type']
    ]);

} catch (Exception $e) {
    $conn->rollback();
    respond('error', $e->getMessage());
}
<?php

require_once '../../config.php';

validateRequestMethod('POST');
$auth = requireAuth(['super_admin', 'admin']);
$input = requireParams(['center_id', 'code_type', 'quantity']);

$centerId = (int)$input['center_id'];
$codeType = trim($input['code_type']);
$walletAmount = isset($input['wallet_amount']) ? (float)$input['wallet_amount'] : null;
$itemId = isset($input['item_id']) ? (int)$input['item_id'] : null;
$expiresAt = isset($input['expires_at']) && $input['expires_at'] !== '' ? trim($input['expires_at']) : null;
$quantity = (int)$input['quantity'];
$prefix = isset($input['prefix']) ? trim($input['prefix']) : '';
$notes = isset($input['notes']) ? trim($input['notes']) : null;

if (!in_array($codeType, ['wallet', 'item'])) {
    respond('error', 'Invalid code type');
}

if ($quantity < 1) {
    respond('error', 'Quantity must be at least 1');
}

if ($quantity > 500) {
    respond('error', 'Maximum quantity is 500');
}

$stmt = $conn->prepare("SELECT id FROM centers WHERE id = ? AND status = 'active'");
$stmt->bind_param("i", $centerId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    respond('error', 'Center not found or inactive');
}

$stmt->close();

if ($codeType === 'wallet') {
    if ($walletAmount === null || $walletAmount <= 0) {
        respond('error', 'Wallet amount is required and must be greater than 0');
    }

    $itemId = null;
}

if ($codeType === 'item') {
    if (!$itemId) {
        respond('error', 'Item ID is required for item codes');
    }

    $walletAmount = null;

    $stmt = $conn->prepare("SELECT id FROM items WHERE id = ?");
    $stmt->bind_param("i", $itemId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();
        respond('error', 'Item not found');
    }

    $stmt->close();
}

function generateCode($prefix = '') {
    $cleanPrefix = strtoupper(preg_replace('/[^A-Z0-9]/', '', $prefix));
    $random = strtoupper(bin2hex(random_bytes(6)));

    return $cleanPrefix ? $cleanPrefix . '-' . $random : $random;
}

$createdCodes = [];

$conn->begin_transaction();

try {
    $stmt = $conn->prepare("
        INSERT INTO code_batches
        (center_id, created_by_admin_id, code_type, wallet_amount, item_id, quantity, expires_at, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "iisdiiss",
        $centerId,
        $auth['id'],
        $codeType,
        $walletAmount,
        $itemId,
        $quantity,
        $expiresAt,
        $notes
    );

    $stmt->execute();
    $batchId = $stmt->insert_id;
    $stmt->close();

    $stmt = $conn->prepare("
        INSERT INTO codes 
        (batch_id, code, code_type, wallet_amount, item_id, expires_at)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    for ($i = 0; $i < $quantity; $i++) {
        $code = generateCode($prefix);
        $attempts = 0;

        while ($attempts < 5) {
            $stmt->bind_param(
                "issdis",
                $batchId,
                $code,
                $codeType,
                $walletAmount,
                $itemId,
                $expiresAt
            );

            if ($stmt->execute()) {
                $createdCodes[] = [
                    'id' => $stmt->insert_id,
                    'batch_id' => $batchId,
                    'code' => $code,
                    'code_type' => $codeType,
                    'wallet_amount' => $walletAmount,
                    'item_id' => $itemId,
                    'expires_at' => $expiresAt
                ];
                break;
            }

            if ($conn->errno == 1062) {
                $attempts++;
                $code = generateCode($prefix);
                continue;
            }

            throw new Exception($conn->error);
        }

        if ($attempts >= 5) {
            throw new Exception('Failed to generate unique code');
        }
    }

    $stmt->close();

    logAction(
        $auth['id'],
        'create_code_batch',
        'code_batches',
        $batchId,
        json_encode([
            'batch_id' => $batchId,
            'center_id' => $centerId,
            'quantity' => count($createdCodes),
            'code_type' => $codeType
        ])
    );

    $conn->commit();

    respond('success', [
        'message' => 'Code batch created successfully',
        'batch_id' => $batchId,
        'quantity' => count($createdCodes),
        'codes' => $createdCodes
    ]);

} catch (Exception $e) {
    $conn->rollback();
    respond('error', $e->getMessage());
}
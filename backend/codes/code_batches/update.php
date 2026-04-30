<?php

require_once '../config.php';

validateRequestMethod('POST');
$auth = requireAuth(['super_admin', 'admin']);

$input = requireParams(['id']);
$batchId = (int)$input['id'];

$conn->begin_transaction();

try {
    $stmt = $conn->prepare("SELECT * FROM code_batches WHERE id = ? FOR UPDATE");
    $stmt->bind_param("i", $batchId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();
        throw new Exception('Batch not found');
    }

    $batchRow = $result->fetch_assoc();
    $stmt->close();

    $stmt = $conn->prepare("SELECT COUNT(*) AS used_count FROM codes WHERE batch_id = ? AND is_used = 1");

    $stmt->bind_param("i", $batchId);
    $stmt->execute();
    $usedResult = $stmt->get_result();
    $usedCount = (int)$usedResult->fetch_assoc()['used_count'];
    $stmt->close();

    if ($usedCount > 0) {
        throw new Exception('Batch cannot be updated because some codes are already used');
    }

    $newCenterId = isset($input['center_id']) ? (int)$input['center_id'] : (int)$batchRow['center_id'];
    $newCodeType = isset($input['code_type']) ? trim($input['code_type']) : $batchRow['code_type'];
    $newWalletAmount = array_key_exists('wallet_amount', $input) ? (float)$input['wallet_amount'] : $batchRow['wallet_amount'];
    $newItemId = array_key_exists('item_id', $input) ? (int)$input['item_id'] : $batchRow['item_id'];
    $newExpiresAt = array_key_exists('expires_at', $input)
        ? ($input['expires_at'] === null || trim((string)$input['expires_at']) === '' ? null : trim($input['expires_at']))
        : $batchRow['expires_at'];
    $newNotes = array_key_exists('notes', $input) ? trim((string)$input['notes']) : $batchRow['notes'];

    if ($newCenterId <= 0) {
        throw new Exception('Invalid center ID');
    }

    $stmt = $conn->prepare("SELECT id FROM centers WHERE id = ? AND status = 'active'");
    $stmt->bind_param("i", $newCenterId);
    $stmt->execute();
    $centerResult = $stmt->get_result();

    if ($centerResult->num_rows === 0) {
        $stmt->close();
        throw new Exception('Center not found or inactive');
    }

    $stmt->close();

    if (!in_array($newCodeType, ['wallet', 'item'])) {
        throw new Exception('Invalid code type');
    }

    if ($newCodeType === 'wallet') {
        if ($newWalletAmount === null || (float)$newWalletAmount <= 0) {
            throw new Exception('wallet_amount is required and must be greater than 0');
        }

        $newWalletAmount = (float)$newWalletAmount;
        $newItemId = null;
    }

    if ($newCodeType === 'item') {
        if (!$newItemId || (int)$newItemId <= 0) {
            throw new Exception('item_id is required for item batches');
        }

        $newWalletAmount = null;
        $newItemId = (int)$newItemId;

        $stmt = $conn->prepare("SELECT id FROM items WHERE id = ?");

        if (!$stmt) {
            throw new Exception('Failed to prepare item query');
        }

        $stmt->bind_param("i", $newItemId);
        $stmt->execute();
        $itemResult = $stmt->get_result();

        if ($itemResult->num_rows === 0) {
            $stmt->close();
            throw new Exception('Item not found');
        }

        $stmt->close();
    }

    $stmt = $conn->prepare("
        UPDATE code_batches
        SET center_id = ?,
            code_type = ?,
            wallet_amount = ?,
            item_id = ?,
            expires_at = ?,
            notes = ?
        WHERE id = ?
    ");

    if (!$stmt) {
        throw new Exception('Failed to prepare batch update query');
    }

    $stmt->bind_param(
        "isdissi",
        $newCenterId,
        $newCodeType,
        $newWalletAmount,
        $newItemId,
        $newExpiresAt,
        $newNotes,
        $batchId
    );

    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("
        UPDATE codes
        SET code_type = ?,
            wallet_amount = ?,
            item_id = ?,
            expires_at = ?
        WHERE batch_id = ? AND is_used = 0
    ");

    if (!$stmt) {
        throw new Exception('Failed to prepare codes update query');
    }

    $stmt->bind_param(
        "sdisi",
        $newCodeType,
        $newWalletAmount,
        $newItemId,
        $newExpiresAt,
        $batchId
    );

    $stmt->execute();
    $updatedCodes = $stmt->affected_rows;
    $stmt->close();

    logAction(
        $auth['id'],
        'update_code_batch',
        'code_batches',
        $batchId,
        json_encode([
            'center_id' => $newCenterId,
            'code_type' => $newCodeType,
            'wallet_amount' => $newWalletAmount,
            'item_id' => $newItemId,
            'expires_at' => $newExpiresAt,
            'notes' => $newNotes,
            'updated_codes' => $updatedCodes
        ])
    );

    $conn->commit();

    respond('success', [
        'message' => 'Code batch updated successfully',
        'updated_codes' => $updatedCodes
    ]);

} catch (Exception $e) {
    $conn->rollback();
    respond('error', $e->getMessage());
}
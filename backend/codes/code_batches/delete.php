<?php

require_once '../config.php';

validateRequestMethod('POST');
$auth = requireAuth(['super_admin', 'admin']);

$input = requireParams(['id']);
$batchId = (int)$input['id'];

if ($batchId <= 0) {
    respond('error', 'Invalid batch ID');
}

$conn->begin_transaction();

try {
    $stmt = $conn->prepare("SELECT * FROM code_batches WHERE id = ? FOR UPDATE");
    $stmt->bind_param("i", $batchId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();
        throw new Exception('Code batch not found');
    }

    $batchRow = $result->fetch_assoc();
    $stmt->close();

    $stmt = $conn->prepare("
        SELECT COUNT(*) AS used_count 
        FROM codes 
        WHERE batch_id = ? AND is_used = 1
    ");
    $stmt->bind_param("i", $batchId);
    $stmt->execute();
    $usedResult = $stmt->get_result();
    $usedCount = (int)$usedResult->fetch_assoc()['used_count'];
    $stmt->close();

    if ($usedCount > 0) {
        throw new Exception('Cannot delete batch because some codes are already used');
    }

    $stmt = $conn->prepare("DELETE FROM codes WHERE batch_id = ? AND is_used = 0");
    $stmt->bind_param("i", $batchId);
    $stmt->execute();
    $deletedCodes = $stmt->affected_rows;
    $stmt->close();

    $stmt = $conn->prepare("DELETE FROM code_batches WHERE id = ?");
    $stmt->bind_param("i", $batchId);
    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        $stmt->close();
        throw new Exception('Code batch was not deleted');
    }

    $stmt->close();

    logAction(
        $auth['id'],
        'delete_code_batch',
        'code_batches',
        $batchId,
        json_encode([
            'batch' => $batchRow,
            'deleted_codes' => $deletedCodes
        ])
    );

    $conn->commit();

    respond('success', [
        'message' => 'Code batch deleted successfully',
        'deleted_codes' => $deletedCodes
    ]);

} catch (Exception $e) {
    $conn->rollback();
    respond('error', $e->getMessage());
}
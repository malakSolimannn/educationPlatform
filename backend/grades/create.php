<?php

require_once '../config.php';

validateRequestMethod('POST');
$auth=requireAuth(['super_admin', 'admin']);
$input = requireParams(['name']);

$name = trim($input['name']);

$stmt = $conn->prepare("SELECT id FROM grades WHERE name = ?");
$stmt->bind_param("s", $name);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

if ($result->num_rows > 0) {
    respond('error', 'Grade name already exists');
}

$stmt = $conn->prepare("INSERT INTO grades (name) VALUES (?)");
$stmt->bind_param("s", $name);

if ($stmt->execute()) {
    $gradeId = $stmt->insert_id;
    $stmt->close();

    logAction($auth['id'], 'create_grade', 'grade', $gradeId, "Created grade: $name");

    respond('success', [
        'id' => $gradeId,
        'name' => $name
    ]);
} else {
    $stmt->close();
    respond('error', 'Failed to create grade');
}
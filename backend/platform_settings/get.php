<?php

require_once '../config.php';

validateRequestMethod('GET');

$stmt = $conn->prepare("SELECT * FROM platform_settings WHERE id = 1 LIMIT 1");
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

if ($result->num_rows === 0) {
    $defaultName = "Teacher Academy";

    $stmt = $conn->prepare("INSERT INTO platform_settings (id, academy_name) VALUES (1, ?)");
    $stmt->bind_param("s", $defaultName);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("SELECT * FROM platform_settings WHERE id = 1 LIMIT 1");
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
}

$settings = $result->fetch_assoc();

respond('success', $settings);
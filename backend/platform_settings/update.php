<?php

require_once '../config.php';
require_once '../helpers/upload_image.php';

validateRequestMethod('POST');
requireAuth(['super_admin', 'admin']);

$academy_name = isset($_POST['academy_name']) ? trim($_POST['academy_name']) : null;
$email = isset($_POST['email']) ? trim($_POST['email']) : null;
$phone = isset($_POST['phone']) ? trim($_POST['phone']) : null;
$domain_name = isset($_POST['domain_name']) ? trim($_POST['domain_name']) : null;
$primary_color = isset($_POST['primary_color']) ? trim($_POST['primary_color']) : null;
$secondary_color = isset($_POST['secondary_color']) ? trim($_POST['secondary_color']) : null;
$about_text = isset($_POST['about_text']) ? trim($_POST['about_text']) : null;

if (!$academy_name) {
    respond('error', 'Academy name is required');
}

$stmt = $conn->prepare("SELECT logo_path, banner_path FROM platform_settings WHERE id = 1 LIMIT 1");
$stmt->execute();
$result = $stmt->get_result();
$current = $result->fetch_assoc();
$stmt->close();

if (!$current) {
    $stmt = $conn->prepare("INSERT INTO platform_settings (id, academy_name) VALUES (1, ?)");
    $stmt->bind_param("s", $academy_name);
    $stmt->execute();
    $stmt->close();

    $current = [
        'logo_path' => null,
        'banner_path' => null
    ];
}

$logo_path = uploadImage('logo', 'platform', 3);
$banner_path = uploadImage('banner', 'platform', 5);

if (!$logo_path) {
    $logo_path = $current['logo_path'];
}

if (!$banner_path) {
    $banner_path = $current['banner_path'];
}

$stmt = $conn->prepare("
    UPDATE platform_settings SET
        academy_name = ?,
        email = ?,
        phone = ?,
        domain_name = ?,
        logo_path = ?,
        banner_path = ?,
        primary_color = ?,
        secondary_color = ?,
        about_text = ?
    WHERE id = 1
");

$stmt->bind_param(
    "sssssssss",
    $academy_name,
    $email,
    $phone,
    $domain_name,
    $logo_path,
    $banner_path,
    $primary_color,
    $secondary_color,
    $about_text
);

$stmt->execute();
$stmt->close();

respond('success', 'Platform settings updated successfully');
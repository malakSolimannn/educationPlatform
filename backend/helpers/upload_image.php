<?php

function uploadImage($fieldName, $folder, $maxSizeMB = 3)
{
    if (!isset($_FILES[$fieldName])) {
        return null; 
    }

    if ($_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) {
        respond('error', 'Error uploading image');
    }

    $uploadDir = "../uploads/$folder/";

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }

    $originalName = $_FILES[$fieldName]['name'];
    $tmpName = $_FILES[$fieldName]['tmp_name'];
    $fileSize = $_FILES[$fieldName]['size'];

    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];

    if (!in_array($extension, $allowedExtensions)) {
        respond('error', 'Invalid image type. Allowed: jpg, jpeg, png, webp');
    }

    if ($fileSize > $maxSizeMB * 1024 * 1024) {
        respond('error', "Image size must be less than {$maxSizeMB}MB");
    }

    $allowedMimes = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp'
    ];

    $mimeType = mime_content_type($tmpName);

    if (!isset($allowedMimes[$extension]) || $mimeType !== $allowedMimes[$extension]) {
        respond('error', 'Invalid image content');
    }

    $newFileName = uniqid($folder . '_', true) . '.' . $extension;
    $destination = $uploadDir . $newFileName;

    if (!move_uploaded_file($tmpName, $destination)) {
        respond('error', 'Failed to upload image');
    }

    return "uploads/$folder/" . $newFileName;
}
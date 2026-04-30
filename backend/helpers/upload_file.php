<?php

function uploadFile($fieldName, $folder, $allowedExtensions = ['pdf', 'doc', 'docx'], $maxSizeMB = 10)
{
    if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) {
        respond('error', 'File upload is required');
    }

    $uploadDir = "../uploads/$folder/";

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }

    $originalName = $_FILES[$fieldName]['name'];
    $tmpName = $_FILES[$fieldName]['tmp_name'];
    $fileSize = $_FILES[$fieldName]['size'];

    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    if (!in_array($extension, $allowedExtensions)) {
        respond('error', 'Invalid file type. Allowed: ' . implode(', ', $allowedExtensions));
    }

    if ($fileSize > $maxSizeMB * 1024 * 1024) {
        respond('error', "File size must be less than {$maxSizeMB}MB");
    }

    $allowedMimes = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ];

    $mimeType = mime_content_type($tmpName);

    if (isset($allowedMimes[$extension]) && $mimeType !== $allowedMimes[$extension]) {
        respond('error', 'Invalid file content');
    }

    $newFileName = uniqid($folder . '_', true) . '.' . $extension;
    $destination = $uploadDir . $newFileName;

    if (!move_uploaded_file($tmpName, $destination)) {
        respond('error', 'Failed to upload file');
    }

    return "uploads/$folder/" . $newFileName;
}
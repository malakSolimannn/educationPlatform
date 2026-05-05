<?php

function normalizeSafePath($relativePath)
{
    if (!$relativePath) {
        respond('error', 'File path is missing');
    }

    $relativePath = ltrim($relativePath, '/');

    if (strpos($relativePath, '..') !== false) {
        respond('error', 'Invalid file path');
    }

    $fullPath = realpath(__DIR__ . '/../' . $relativePath);

    if (!$fullPath || !file_exists($fullPath)) {
        respond('error', 'File not found');
    }

    return $fullPath;
}

function viewFile($relativePath)
{
    $fullPath = normalizeSafePath($relativePath);

    $mimeType = mime_content_type($fullPath);

    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: inline; filename="' . basename($fullPath) . '"');
    header('Content-Length: ' . filesize($fullPath));

    readfile($fullPath);
    exit;
}

function downloadFile($relativePath)
{
    $fullPath = normalizeSafePath($relativePath);

    $mimeType = mime_content_type($fullPath);

    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: attachment; filename="' . basename($fullPath) . '"');
    header('Content-Length: ' . filesize($fullPath));

    readfile($fullPath);
    exit;
}
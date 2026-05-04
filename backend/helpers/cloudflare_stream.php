<?php
require_once __DIR__ . '/../config.php';

function uploadVideoToCloudflare($fieldName = 'video')
{
    if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) {
        respond('error', 'Video file is required');
    }

    $tmpName = $_FILES[$fieldName]['tmp_name'];
    $originalName = $_FILES[$fieldName]['name'];

    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $allowed = ['mp4', 'mov', 'mkv', 'webm'];

    if (!in_array($extension, $allowed)) {
        respond('error', 'Invalid video type');
    }

    $url = "https://api.cloudflare.com/client/v4/accounts/" .
        CLOUDFLARE_ACCOUNT_ID .
        "/stream";

    $curl = curl_init();

    $postFields = [
        'file' => new CURLFile($tmpName, mime_content_type($tmpName), $originalName)
    ];

    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer " . CLOUDFLARE_STREAM_TOKEN
        ],
        CURLOPT_POSTFIELDS => $postFields
    ]);

    $response = curl_exec($curl);

    if ($response === false) {
        $error = curl_error($curl);
        curl_close($curl);
        respond('error', 'Cloudflare upload failed: ' . $error);
    }

    curl_close($curl);

    $data = json_decode($response, true);

    if (!isset($data['success']) || $data['success'] !== true) {
        respond('error', 'Cloudflare upload failed');
    }

    return [
        'uid' => $data['result']['uid'],
        'thumbnail' => $data['result']['thumbnail'] ?? null,
        'preview' => $data['result']['preview'] ?? null,
        'embed_url' => "https://iframe.videodelivery.net/" . $data['result']['uid']
    ];
}
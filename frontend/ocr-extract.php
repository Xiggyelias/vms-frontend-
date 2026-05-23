<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/middleware/security.php';
SecurityMiddleware::initialize();
requireAuth();

header('Content-Type: application/json');

function extractTextFromImage($imagePath) {
    $apiKey = $_ENV['OCR_API_KEY'] ?? ''; 
    if (empty($apiKey)) {
        return ['success' => false, 'error' => 'OCR API Key is not configured.'];
    }

    $url = 'https://api.ocr.space/parse/image';

    $imageData = curl_file_create($imagePath);
    $postFields = [
        'apikey' => $apiKey,
        'language' => 'eng',
        'isOverlayRequired' => 'false',
        'file' => $imageData
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        return ['success' => false, 'error' => (isDevelopment() ? curl_error($ch) : 'Network error during OCR processing')];
    }
    curl_close($ch);

    $result = json_decode($response, true);

    if (isset($result['IsErroredOnProcessing']) && $result['IsErroredOnProcessing'] == true) {
        return ['success' => false, 'error' => 'OCR validation failed on the provided image'];
    }

    $parsedText = $result['ParsedResults'][0]['ParsedText'] ?? '';
    return ['success' => true, 'text' => $parsedText];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $token = $_POST['_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!$token || !SecurityMiddleware::verifyCSRFToken($token)) {
        http_response_code(419);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }

    $tmpName = $_FILES['image']['tmp_name'];
    $fileSize = $_FILES['image']['size'];

    // 1. Validate file size (e.g., max 5MB)
    $maxSize = 5 * 1024 * 1024;
    if ($fileSize > $maxSize) {
        echo json_encode(['success' => false, 'error' => 'File size exceeds the 5MB limit.']);
        exit;
    }

    // 2. Validate MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $tmpName);
    finfo_close($finfo);

    $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($mimeType, $allowedMimeTypes, true)) {
        echo json_encode(['success' => false, 'error' => 'Invalid file type. Only JPG, PNG, and WebP are allowed.']);
        exit;
    }

    // Process valid image
    $result = extractTextFromImage($tmpName);
    echo json_encode($result);
    exit;
} else {
    echo json_encode(['success' => false, 'error' => 'No valid image uploaded']);
    exit;
}
?>

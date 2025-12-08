<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Not authorized']);
    exit();
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../service/image_utils.php';

// Accept either 'image' or 'memorial_photo' field for compatibility
$fileField = null;
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) $fileField = 'image';
elseif (isset($_FILES['memorial_photo']) && $_FILES['memorial_photo']['error'] === UPLOAD_ERR_OK) $fileField = 'memorial_photo';

if ($fileField === null) {
    echo json_encode(['ok' => false, 'message' => 'No file uploaded or upload error']);
    exit();
}

// Use safeProcessUpload to validate and store the file under a 'pages' subfolder
list($ok, $result) = safeProcessUpload($_FILES[$fileField], 'pages', 1600, 1600);
if (!$ok) {
    echo json_encode(['ok' => false, 'message' => 'Upload failed: ' . $result]);
    exit();
}

$uploadedWebPath = $result; // e.g. 'uploads/pages/abcd.jpg'

// Return the web path for insertion into editor (keep leading slash out so caller can decide)
echo json_encode(['ok' => true, 'path' => $uploadedWebPath, 'message' => 'Upload complete']);
exit();

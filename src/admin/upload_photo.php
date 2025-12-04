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

if (!isset($_FILES['memorial_photo']) || $_FILES['memorial_photo']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['ok' => false, 'message' => 'No file uploaded or upload error']);
    exit();
}

list($ok, $result) = safeProcessUpload($_FILES['memorial_photo'], 'memorial', 1200, 1200);
if (!$ok) {
    echo json_encode(['ok' => false, 'message' => 'Upload failed: ' . $result]);
    exit();
}

$uploadedWebPath = $result; // e.g. uploads/memorial/abcd.jpg

// Determine filesystem paths
$baseDir = realpath(__DIR__ . '/..');
if ($baseDir === false) $baseDir = __DIR__ . '/..';
$uploadedFsPath = rtrim($baseDir, '/\\') . '/' . ltrim($uploadedWebPath, '/\\');

// Always store the stable memorial photo as PNG
$stableWebPath = 'uploads/memorial/main.png';
$stableFsPath = rtrim($baseDir, '/\\') . '/' . ltrim($stableWebPath, '/\\');

// Ensure target dir exists
$stableDir = dirname($stableFsPath);
if (!is_dir($stableDir)) mkdir($stableDir, 0755, true);

// Convert the uploaded file to PNG and save as the stable filename
$converted = false;
if (file_exists($uploadedFsPath)) {
    $data = @file_get_contents($uploadedFsPath);
    if ($data !== false) {
        $img = @imagecreatefromstring($data);
        if ($img !== false) {
            // Ensure target dir exists
            $stableDir = dirname($stableFsPath);
            if (!is_dir($stableDir)) mkdir($stableDir, 0755, true);
            // Write PNG
            if (@imagepng($img, $stableFsPath)) {
                $converted = true;
            }
            imagedestroy($img);
        }
    }
}

if (!$converted) {
    echo json_encode(['ok' => false, 'message' => 'Upload succeeded but converting to PNG failed.']);
    exit();
}

@chmod($stableFsPath, 0644);
// Remove the original uploaded file if it still exists and is different
if (file_exists($uploadedFsPath) && realpath($uploadedFsPath) !== realpath($stableFsPath)) {
    @unlink($uploadedFsPath);
}

// Update config.php define for MEMORIAL_PHOTO (atomic write)
$configFile = __DIR__ . '/../config.php';
$cfg = file_get_contents($configFile);
if ($cfg === false) {
    echo json_encode(['ok' => false, 'message' => 'Failed to read config.php']);
    exit();
}

if (preg_match("/define\(\\'MEMORIAL_PHOTO\\',[^;]+;\)/", $cfg)) {
    $newcfg = preg_replace(
        "/define\(\\'MEMORIAL_PHOTO\\',[^;]+;\)/",
        "define('MEMORIAL_PHOTO', '" . addslashes($stableWebPath) . "');",
        $cfg
    );
} else {
    $newcfg = $cfg . "\n// Memorial photo\ndefine('MEMORIAL_PHOTO', '" . addslashes($stableWebPath) . "');\n";
}

$tmp = $configFile . '.tmp';
if (file_put_contents($tmp, $newcfg) === false || !rename($tmp, $configFile)) {
    echo json_encode(['ok' => false, 'message' => 'Failed to update config.php']);
    exit();
}

echo json_encode(['ok' => true, 'path' => $stableWebPath, 'message' => 'Upload complete']);
exit();

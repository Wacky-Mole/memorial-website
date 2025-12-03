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

$ext = strtolower(pathinfo($uploadedFsPath, PATHINFO_EXTENSION));
if ($ext === '') $ext = 'jpg';
$stableWebPath = 'uploads/memorial/main.' . $ext;
$stableFsPath = rtrim($baseDir, '/\\') . '/' . ltrim($stableWebPath, '/\\');

// Ensure target dir exists
$stableDir = dirname($stableFsPath);
if (!is_dir($stableDir)) mkdir($stableDir, 0755, true);

$moved = false;
if (file_exists($uploadedFsPath)) {
    if (@rename($uploadedFsPath, $stableFsPath)) {
        $moved = true;
    } elseif (@copy($uploadedFsPath, $stableFsPath)) {
        @unlink($uploadedFsPath);
        $moved = true;
    }
} else {
    $possibleSrc = __DIR__ . '/../' . ltrim($uploadedWebPath, '/\\');
    if (file_exists($possibleSrc) && (@copy($possibleSrc, $stableFsPath))) {
        $moved = true;
    }
}

if (!$moved) {
    echo json_encode(['ok' => false, 'message' => 'Upload succeeded but moving to stable filename failed.']);
    exit();
}

@chmod($stableFsPath, 0644);
// Normalize saved image to strip EXIF/orientation and ensure consistent format
@chmod($stableFsPath, 0644);

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

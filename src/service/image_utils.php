<?php
// Image upload and resizing utilities

function generateRandomFilename($extension) {
    try {
        $bytes = random_bytes(8);
        $rand = bin2hex($bytes);
    } catch (Exception $e) {
        $rand = uniqid('', true);
    }
    return $rand . '.' . $extension;
}

function detectMimeType($filePath) {
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $filePath);
        finfo_close($finfo);
        return $mime;
    }
    $info = getimagesize($filePath);
    return $info['mime'] ?? null;
}

function resizeImageIfNeeded($tmpPath, $destPath, $maxWidth = 1200, $maxHeight = 1200, $quality = 85) {
    $info = getimagesize($tmpPath);
    if ($info === false) return false;
    $width = $info[0];
    $height = $info[1];
    $mime = $info['mime'];

    // If image already within limits, move uploaded file
    if ($width <= $maxWidth && $height <= $maxHeight) {
        return move_uploaded_file($tmpPath, $destPath) || rename($tmpPath, $destPath);
    }

    // Create image resource from string (supports many formats)
    $data = file_get_contents($tmpPath);
    if ($data === false) return false;
    $srcImg = imagecreatefromstring($data);
    if ($srcImg === false) return false;

    // Calculate new size preserving aspect ratio
    $ratio = min($maxWidth / $width, $maxHeight / $height);
    $newW = (int)($width * $ratio);
    $newH = (int)($height * $ratio);

    $dstImg = imagecreatetruecolor($newW, $newH);
    // Preserve transparency for PNG/GIF
    if (in_array($mime, ['image/png', 'image/gif'])) {
        imagecolortransparent($dstImg, imagecolorallocatealpha($dstImg, 0, 0, 0, 127));
        imagealphablending($dstImg, false);
        imagesavealpha($dstImg, true);
    }

    imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $newW, $newH, $width, $height);

    // Determine output format by destPath extension
    $ext = strtolower(pathinfo($destPath, PATHINFO_EXTENSION));
    $ok = false;
    if ($ext === 'png') {
        $ok = imagepng($dstImg, $destPath);
    } elseif ($ext === 'gif') {
        $ok = imagegif($dstImg, $destPath);
    } elseif ($ext === 'webp' && function_exists('imagewebp')) {
        $ok = imagewebp($dstImg, $destPath, $quality);
    } else {
        // default to jpeg
        $ok = imagejpeg($dstImg, $destPath, $quality);
    }

    imagedestroy($srcImg);
    imagedestroy($dstImg);

    return $ok;
}

function safeProcessUpload($fileArray, $subDir = 'memorial', $maxWidth = 1200, $maxHeight = 1200) {
    // $fileArray is from $_FILES['...']
    if (!isset($fileArray) || $fileArray['error'] !== UPLOAD_ERR_OK) return [false, 'No file or upload error'];

    $tmp = $fileArray['tmp_name'];
    $mime = detectMimeType($tmp);
    if ($mime === null) return [false, 'Cannot detect MIME type'];

    if (!in_array($mime, ALLOWED_FILE_TYPES)) return [false, 'File type not allowed'];

    if ($fileArray['size'] > MAX_FILE_SIZE) return [false, 'File exceeds maximum size'];

    // map mime to extension
    $map = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/bmp' => 'bmp'
    ];
    $ext = $map[$mime] ?? 'jpg';

    $uploadDir = rtrim(UPLOAD_DIR, '/') . '/' . trim($subDir, '/') . '/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $filename = generateRandomFilename($ext);
    $destPath = $uploadDir . $filename;

    // Try to resize if necessary
    $resized = resizeImageIfNeeded($tmp, $destPath, $maxWidth, $maxHeight);
    if ($resized) {
        return [true, $destPath];
    }

    // Fallback: move uploaded file
    if (move_uploaded_file($tmp, $destPath) || rename($tmp, $destPath)) {
        return [true, $destPath];
    }

    return [false, 'Failed to save uploaded file'];
}

?>

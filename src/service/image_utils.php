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

function applyExifOrientation(string $filePath) {
    if (!function_exists('exif_read_data')) return;
    try {
        $exif = @exif_read_data($filePath);
    } catch (Exception $e) {
        return;
    }
    if (empty($exif) || empty($exif['Orientation'])) return;
    $orientation = (int)$exif['Orientation'];
    if ($orientation === 1) return; // normal

    $info = getimagesize($filePath);
    if ($info === false) return;
    $mime = $info['mime'] ?? '';
    // Only manipulate supported image formats
    if ($mime !== 'image/jpeg' && $mime !== 'image/jpg' && $mime !== 'image/png' && $mime !== 'image/gif') return;

    $data = file_get_contents($filePath);
    if ($data === false) return;
    $src = imagecreatefromstring($data);
    if ($src === false) return;

    $dst = $src;
    $needSave = false;

    switch ($orientation) {
        case 2: // flip horizontal
            if (function_exists('imageflip')) { imageflip($dst, IMG_FLIP_HORIZONTAL); $needSave = true; }
            break;
        case 3: // rotate 180
            $dst = imagerotate($src, 180, 0); $needSave = true; break;
        case 4: // flip vertical
            if (function_exists('imageflip')) { imageflip($dst, IMG_FLIP_VERTICAL); $needSave = true; }
            break;
        case 5: // transpose
            if (function_exists('imageflip')) { $dst = imagerotate($src, 270, 0); imageflip($dst, IMG_FLIP_HORIZONTAL); $needSave = true; }
            break;
        case 6: // rotate 90 CW
            $dst = imagerotate($src, -90, 0); $needSave = true; break;
        case 7: // transverse
            if (function_exists('imageflip')) { $dst = imagerotate($src, -90, 0); imageflip($dst, IMG_FLIP_HORIZONTAL); $needSave = true; }
            break;
        case 8: // rotate 270 CW (90 CCW)
            $dst = imagerotate($src, 90, 0); $needSave = true; break;
    }

    if ($needSave) {
        // Overwrite file in same format (prefer jpeg for photographic images)
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if (in_array($ext, ['png'])) {
            imagepng($dst, $filePath);
        } elseif (in_array($ext, ['gif'])) {
            imagegif($dst, $filePath);
        } else {
            imagejpeg($dst, $filePath, 90);
        }
        imagedestroy($dst);
        if ($src !== $dst) imagedestroy($src);
    } else {
        imagedestroy($src);
    }
}

/**
 * Normalize an image by re-encoding it with GD. This removes EXIF metadata
 * and ensures the file is a standard image (JPEG/PNG/GIF) written by GD.
 * Returns true on success.
 */


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

    // Autorotate based on EXIF orientation (JPEGs)
    if ($mime === 'image/jpeg' || $mime === 'image/jpg') {
        applyExifOrientation($tmp);
        // re-detect size/mime if needed
        $mime = detectMimeType($tmp) ?? $mime;
    }

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

    // Build an absolute filesystem path for the uploads directory (based on project src dir)
    $baseDir = realpath(__DIR__ . '/..');
    if ($baseDir === false) $baseDir = __DIR__ . '/..';

    $webUploadDir = rtrim(UPLOAD_DIR, '/') . '/' . trim($subDir, '/') . '/';
    $fsUploadDir = rtrim($baseDir, '/\\') . '/' . trim(UPLOAD_DIR, '/\\') . '/' . trim($subDir, '/\\') . '/';

    if (!is_dir($fsUploadDir)) mkdir($fsUploadDir, 0755, true);

    $filename = generateRandomFilename($ext);
    $destPathWeb = $webUploadDir . $filename; // path returned for use in HTML/config (relative to web root)
    $destPathFs = $fsUploadDir . $filename;   // absolute filesystem path used for moving/writing files

    // Try to resize if necessary (write to filesystem path)
    $resized = resizeImageIfNeeded($tmp, $destPathFs, $maxWidth, $maxHeight);
    if ($resized) {
        return [true, $destPathWeb];
    }

    // Fallback: move uploaded file to the filesystem path
    if (move_uploaded_file($tmp, $destPathFs) || rename($tmp, $destPathFs)) {
        return [true, $destPathWeb];
    }

    return [false, 'Failed to save uploaded file'];
}

?>

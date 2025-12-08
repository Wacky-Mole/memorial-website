<?php
// Lightweight helpers for video/media detection and caching

function ensure_videos_dir(): string {
    // Create uploads/videos directory under project root (if not present)
    $projectRoot = realpath(__DIR__ . '/../../');
    if ($projectRoot === false) $projectRoot = __DIR__ . '/../../';
    $uploadsDir = rtrim($projectRoot, '/\\') . '/uploads';
    $videosFs = $uploadsDir . '/videos';
    if (!is_dir($videosFs)) @mkdir($videosFs, 0755, true);
    return $videosFs;
}

function map_url_to_local_path(string $url): ?string {
    // Attempt to resolve a URL or relative path to a local filesystem path
    $projectRoot = realpath(__DIR__ . '/../../');
    if ($projectRoot === false) $projectRoot = __DIR__ . '/../../';

    // Normalize
    $trim = trim($url);
    if ($trim === '') return null;

    // If it's a full URL, extract path
    $parsed = parse_url($trim);
    $path = $parsed['path'] ?? $trim;

    // If path contains uploads/videos, map to project root
    if (stripos($path, '/uploads/videos/') !== false || stripos($path, 'uploads/videos/') === 0) {
        // ensure no leading slash or backslash when joining
        $rel = ltrim($path, '/\\');
        $fs = rtrim($projectRoot, '/\\') . '/' . $rel;
        return $fs;
    }

    return null;
}

function is_direct_media(string $url, ?string $cacheKey = null): bool {
    // Cache results for 24 hours to avoid frequent HEAD requests
    $cacheDir = __DIR__ . '/../data/video_meta';
    if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);
    $hash = $cacheKey ?? md5($url);
    $cacheFile = $cacheDir . '/' . $hash . '.json';

    if (file_exists($cacheFile)) {
        $j = json_decode(@file_get_contents($cacheFile), true);
        if (is_array($j) && isset($j['checked']) && (time() - intval($j['checked']) < 86400)) {
            return !empty($j['is_video']);
        }
    }

    // 1) If this maps to a local file, check mime via finfo (faster and safe)
    $local = map_url_to_local_path($url);
    if ($local !== null && file_exists($local)) {
        $finfo = null;
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $local);
            if ($finfo) finfo_close($finfo);
        } else {
            $mime = mime_content_type($local) ?: '';
        }
        $isVideo = false;
        if (!empty($mime)) {
            $m = strtolower(explode(';', $mime)[0]);
            if (strpos($m, 'video/') === 0 || strpos($m, 'audio/') === 0) $isVideo = true;
        }
        @file_put_contents($cacheFile, json_encode(['checked' => time(), 'is_video' => $isVideo, 'content_type' => $mime ?? '']));
        return $isVideo;
    }

    // 2) Fallback to remote HEAD request
    if (!function_exists('curl_init')) {
        // If curl is unavailable, we can't reliably determine; assume not direct media
        @file_put_contents($cacheFile, json_encode(['checked' => time(), 'is_video' => false]));
        return false;
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 6);
    curl_setopt($ch, CURLOPT_USERAGENT, 'MemorialSite/1.0 (+https://example.local/)');
    // don't fail on SSL cert issues for odd hosts; servers should configure properly
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);

    $contentType = $info['content_type'] ?? '';
    $isVideo = false;
    if (!empty($contentType)) {
        $parts = explode(';', $contentType);
        $mime = strtolower(trim($parts[0]));
        if (strpos($mime, 'video/') === 0 || strpos($mime, 'audio/') === 0) {
            $isVideo = true;
        }
    }

    @file_put_contents($cacheFile, json_encode(['checked' => time(), 'is_video' => $isVideo, 'content_type' => $contentType]));
    return $isVideo;
}

?>

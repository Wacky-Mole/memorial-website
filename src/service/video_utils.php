<?php
// Lightweight helpers for video/media detection and caching

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

    if (!function_exists('curl_init')) {
        // If curl is unavailable, we can't reliably determine; assume not direct media
        file_put_contents($cacheFile, json_encode(['checked' => time(), 'is_video' => false]));
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

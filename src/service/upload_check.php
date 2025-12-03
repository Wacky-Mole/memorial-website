<?php
// Helper functions to inspect PHP upload/post limits and convert human sizes

function iniSizeToBytes(string $size) : int {
    $size = trim($size);
    if ($size === '') return 0;
    $last = strtolower($size[strlen($size)-1]);
    $value = (int) $size;
    switch ($last) {
        case 'g':
            $value *= 1024 * 1024 * 1024; break;
        case 'm':
            $value *= 1024 * 1024; break;
        case 'k':
            $value *= 1024; break;
        default:
            // no suffix
            $value = (int)$size; break;
    }
    return (int)$value;
}

function humanFilesize(int $bytes): string {
    if ($bytes <= 0) return '0 B';
    $units = ['B','KB','MB','GB','TB'];
    $i = floor(log($bytes, 1024));
    $p = pow(1024, $i);
    $s = round($bytes / $p, ($i ? 1 : 0));
    return $s . ' ' . $units[$i];
}

function serverUploadLimits(): array {
    $upload = ini_get('upload_max_filesize') ?: '0';
    $post = ini_get('post_max_size') ?: '0';
    $mem = ini_get('memory_limit') ?: '0';

    $uploadBytes = iniSizeToBytes($upload);
    $postBytes = iniSizeToBytes($post);
    $memBytes = iniSizeToBytes($mem);

    // Effective limit: typically limited by the smaller of upload_max_filesize and post_max_size
    $effective = $uploadBytes > 0 && $postBytes > 0 ? min($uploadBytes, $postBytes) : max($uploadBytes, $postBytes);

    return [
        'upload_max_filesize' => $upload,
        'post_max_size' => $post,
        'memory_limit' => $mem,
        'upload_max_filesize_bytes' => $uploadBytes,
        'post_max_size_bytes' => $postBytes,
        'memory_limit_bytes' => $memBytes,
        'effective_bytes' => $effective,
        'effective_readable' => humanFilesize($effective)
    ];
}

?>
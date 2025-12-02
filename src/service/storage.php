<?php
session_start();

// Save a memorial entry. Returns true on success, false on failure.
function saveMemorialEntry($email, $contributorName, $message, $photoPath = '') {
    // Append entry to a simple log (can be replaced by real DB logic)
    $entry = [
        'memorial' => (defined('MEMORIAL_NAME') ? MEMORIAL_NAME : ''),
        'email' => $email,
        'contributor' => $contributorName,
        'message' => $message,
        'photo' => $photoPath,
        'created_at' => date('Y-m-d H:i:s')
    ];

    $logDir = __DIR__ . '/../data/';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $logFile = $logDir . 'memorial_entries.log';
    $res = file_put_contents($logFile, json_encode($entry) . PHP_EOL, FILE_APPEND);
    return $res !== false;
}

function getMemorialEntries() {
    $logFile = __DIR__ . '/../data/memorial_entries.log';
    if (!file_exists($logFile)) {
        return [];
    }
    $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $entries = array_map(function($l){ return json_decode($l, true); }, $lines);
    return $entries;
}
?>
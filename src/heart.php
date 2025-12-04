<?php
// Toggle heart for an entry (POST form-data: entry_id)
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/service/storage.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed']);
    exit();
}

$entryId = isset($_POST['entry_id']) ? intval($_POST['entry_id']) : 0;
if ($entryId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Missing entry_id']);
    exit();
}

$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
try {
    $pdo = getPDO();

    // Ensure entry exists
    $s = $pdo->prepare('SELECT id FROM entries WHERE id = :id LIMIT 1');
    $s->execute([':id' => $entryId]);
    $exists = $s->fetch(PDO::FETCH_ASSOC);
    if (!$exists) {
        echo json_encode(['ok' => false, 'message' => 'Entry not found']);
        exit();
    }

    // Check if this IP already hearted this entry
    $check = $pdo->prepare('SELECT COUNT(*) FROM hearts WHERE entry_id = :eid AND ip = :ip');
    $check->execute([':eid' => $entryId, ':ip' => $ip]);
    $has = intval($check->fetchColumn());

    if ($has > 0) {
        // remove
        $del = $pdo->prepare('DELETE FROM hearts WHERE entry_id = :eid AND ip = :ip');
        $del->execute([':eid' => $entryId, ':ip' => $ip]);
        $hearted = false;
    } else {
        // insert
        $ins = $pdo->prepare('INSERT INTO hearts (entry_id, ip, created_at) VALUES (:eid, :ip, CURRENT_TIMESTAMP)');
        $ins->execute([':eid' => $entryId, ':ip' => $ip]);
        $hearted = true;
    }

    $cnt = $pdo->prepare('SELECT COUNT(*) FROM hearts WHERE entry_id = :eid');
    $cnt->execute([':eid' => $entryId]);
    $newCount = intval($cnt->fetchColumn());

    echo json_encode(['ok' => true, 'hearted' => $hearted, 'count' => $newCount]);
} catch (Exception $e) {
    error_log('heart.php error: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'message' => 'db_error']);
}

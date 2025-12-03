<?php
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
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'Entry not found']);
        exit();
    }

    // Check if this IP has already hearted this entry
    $t = $pdo->prepare('SELECT id FROM hearts WHERE entry_id = :eid AND ip = :ip LIMIT 1');
    $t->execute([':eid' => $entryId, ':ip' => $ip]);
    $row = $t->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        // unheart
        $del = $pdo->prepare('DELETE FROM hearts WHERE id = :id');
        $del->execute([':id' => $row['id']]);
        $hearted = false;
    } else {
        // add heart
        $ins = $pdo->prepare('INSERT OR IGNORE INTO hearts (entry_id, ip, created_at) VALUES (:eid, :ip, :ts)');
        $ins->execute([':eid' => $entryId, ':ip' => $ip, ':ts' => date('Y-m-d H:i:s')]);
        $hearted = true;
    }

    // Return updated count
    $c = $pdo->prepare('SELECT COUNT(*) AS cnt FROM hearts WHERE entry_id = :eid');
    $c->execute([':eid' => $entryId]);
    $cnt = (int)$c->fetch(PDO::FETCH_ASSOC)['cnt'];

    echo json_encode(['ok' => true, 'hearted' => $hearted, 'count' => $cnt]);
    exit();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Server error']);
    exit();
}

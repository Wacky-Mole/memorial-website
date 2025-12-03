<?php
// SQLite-backed storage for memorial entries
// Functions exposed for compatibility with existing code:
// - saveMemorialEntry($email, $contributorName, $message, $photoPath='')
// - getApprovedEntries()
// - getEntries($status = null)
// - updateEntriesStatus(array $ids, $status)
// - deleteEntries(array $ids)

function getPDO() {
    $dbPath = defined('DB_PATH') ? DB_PATH : (__DIR__ . '/../data/memorial.db');
    $dir = dirname($dbPath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Ensure table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS entries (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        memorial TEXT,
        email TEXT,
        contributor TEXT,
        message TEXT,
        photo TEXT,
        created_at TEXT,
        status TEXT,
        ip TEXT
    )");

    return $pdo;
}

function saveMemorialEntry($email, $contributorName, $message, $photoPath = '') {
    try {
        $pdo = getPDO();

        // Determine initial status: if admin enabled auto-approve in settings store, mark as APPROVED
        $status = 'NOT_APPROVED';
        try {
            $s = $pdo->prepare("SELECT value FROM settings WHERE key = :k LIMIT 1");
            $s->execute([':k' => 'auto_approve']);
            $row = $s->fetch(PDO::FETCH_ASSOC);
            if ($row && isset($row['value']) && $row['value'] === '1') {
                $status = 'APPROVED';
            }
        } catch (Exception $e) {
            // settings table might not exist yet; default to NOT_APPROVED
        }

        $stmt = $pdo->prepare("INSERT INTO entries (memorial,email,contributor,message,photo,created_at,status,ip) VALUES (:memorial,:email,:contributor,:message,:photo,:created_at,:status,:ip)");
        $stmt->execute([
            ':memorial' => defined('MEMORIAL_NAME') ? MEMORIAL_NAME : '',
            ':email' => $email,
            ':contributor' => $contributorName,
            ':message' => $message,
            ':photo' => $photoPath,
            ':created_at' => date('Y-m-d H:i:s'),
            ':status' => $status,
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? ''
        ]);
        return true;
    } catch (Exception $e) {
        error_log('saveMemorialEntry error: ' . $e->getMessage());
        return false;
    }
}

function getEntries($status = null) {
    $pdo = getPDO();
    if ($status === null) {
        $stmt = $pdo->query("SELECT * FROM entries ORDER BY datetime(created_at) DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM entries WHERE status = :status ORDER BY datetime(created_at) DESC");
        $stmt->execute([':status' => $status]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

function getApprovedEntries() {
    return getEntries('APPROVED');
}

function updateEntriesStatus(array $ids, $status) {
    if (empty($ids)) return false;
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $pdo = getPDO();
    $stmt = $pdo->prepare("UPDATE entries SET status = ? WHERE id IN ($placeholders)");
    $params = array_merge([$status], $ids);
    try {
        return $stmt->execute($params);
    } catch (Exception $e) {
        error_log('updateEntriesStatus error: ' . $e->getMessage());
        return false;
    }
}

function deleteEntries(array $ids) {
    if (empty($ids)) return false;
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $pdo = getPDO();
    $stmt = $pdo->prepare("DELETE FROM entries WHERE id IN ($placeholders)");
    try {
        return $stmt->execute($ids);
    } catch (Exception $e) {
        error_log('deleteEntries error: ' . $e->getMessage());
        return false;
    }
}

?>
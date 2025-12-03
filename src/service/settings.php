<?php
// DB-backed settings helper
// Uses the same SQLite DB via getPDO() in service/storage.php

require_once __DIR__ . '/storage.php';

function ensure_settings_table() {
    $pdo = getPDO();
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        key TEXT PRIMARY KEY,
        value TEXT
    )");
}

function set_setting(string $key, $value): bool {
    try {
        ensure_settings_table();
        $pdo = getPDO();
        $stmt = $pdo->prepare("INSERT OR REPLACE INTO settings (key, value) VALUES (:k, :v)");
        return $stmt->execute([':k' => $key, ':v' => $value]);
    } catch (Exception $e) {
        error_log('set_setting error: ' . $e->getMessage());
        return false;
    }
}

function get_setting(string $key, $default = null) {
    try {
        ensure_settings_table();
        $pdo = getPDO();
        $stmt = $pdo->prepare("SELECT value FROM settings WHERE key = :k LIMIT 1");
        $stmt->execute([':k' => $key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && isset($row['value'])) return $row['value'];
        return $default;
    } catch (Exception $e) {
        error_log('get_setting error: ' . $e->getMessage());
        return $default;
    }
}

function get_all_settings(): array {
    try {
        ensure_settings_table();
        $pdo = getPDO();
        $stmt = $pdo->query("SELECT key, value FROM settings");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $r) {
            $out[$r['key']] = $r['value'];
        }
        return $out;
    } catch (Exception $e) {
        error_log('get_all_settings error: ' . $e->getMessage());
        return [];
    }
}

?>

<?php
// Lightweight app settings stored in DB
// Provides get_setting and set_setting helpers.
try {
    if (!isset($pdo)) {
        require_once __DIR__ . '/db.php';
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS app_settings (
            `setting_key` VARCHAR(100) NOT NULL PRIMARY KEY,
            `value` TEXT NOT NULL
        )'
    );
} catch (Throwable $e) {
    // ignore creation errors; get_setting will handle missing PDO
}

function get_setting(string $key, $default = null)
{
    global $pdo;
    if (!isset($pdo)) {
        return $default;
    }
    try {
        $stmt = $pdo->prepare('SELECT `value` FROM app_settings WHERE `setting_key` = ?');
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        if ($val === false) {
            return $default;
        }
        return $val;
    } catch (Throwable $e) {
        return $default;
    }
}

function set_setting(string $key, $value): bool
{
    global $pdo;
    if (!isset($pdo)) {
        return false;
    }
    try {
        $stmt = $pdo->prepare('INSERT INTO app_settings (`setting_key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)');
        $stmt->execute([$key, (string) $value]);
        return true;
    } catch (Throwable $e) {
        error_log('[app-settings] ' . $e->getMessage());
        return false;
    }
}

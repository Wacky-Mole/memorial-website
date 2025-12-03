<?php
session_start();

// Require admin login
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: login.php');
    exit();
}

require_once __DIR__ . '/../config.php';

$error = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = trim($_POST['current_password'] ?? '');
    $new = trim($_POST['new_password'] ?? '');
    $confirm = trim($_POST['confirm_password'] ?? '');

    if ($new === '') {
        $error = 'Please enter a new password.';
    } elseif ($new !== $confirm) {
        $error = 'New password and confirmation do not match.';
    } elseif (strlen($new) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        $ok = false;
        // Check current password against possible definitions
        if (!empty($current)) {
            if (defined('ADMIN_PASSWORD_PLAIN') && constant('ADMIN_PASSWORD_PLAIN') !== '') {
                if (hash_equals((string)constant('ADMIN_PASSWORD_PLAIN'), $current)) $ok = true;
            }
            if (!$ok && defined('ADMIN_PASSWORD_HASH') && constant('ADMIN_PASSWORD_HASH') !== '') {
                if (password_verify($current, constant('ADMIN_PASSWORD_HASH'))) $ok = true;
            }
        }

        if (!$ok) {
            $error = 'Current password is incorrect.';
        } else {
            // generate new hash and write to config.php atomically
            $configFile = __DIR__ . '/../config.php';
            if (!file_exists($configFile)) {
                $error = 'Configuration file not found.';
            } else {
                $cfg = file_get_contents($configFile);
                if ($cfg === false) {
                    $error = 'Failed to read configuration.';
                } else {
                    $newHash = password_hash($new, PASSWORD_DEFAULT);

                    // Remove any existing ADMIN_PASSWORD_PLAIN and ADMIN_PASSWORD_HASH definitions (robustly)
                    // Match patterns like: define('ADMIN_PASSWORD_PLAIN', '...'); or define('ADMIN_PASSWORD_HASH', password_hash(...));
                    $cfg = preg_replace("/define\\(\\s*'ADMIN_PASSWORD_PLAIN'\\s*,[^\\)]*\\)\\s*;\\s*/i", '', $cfg);
                    $cfg = preg_replace("/define\\(\\s*'ADMIN_PASSWORD_HASH'\\s*,[^\\)]*\\)\\s*;\\s*/i", '', $cfg);

                    // Insert new literal hash after the opening <?php (or at top if not found)
                    $insert = "\n// Admin password hash\ndefine('ADMIN_PASSWORD_HASH', " . var_export($newHash, true) . ");\n";
                    if (preg_match('/<\\?php\\s*/i', $cfg, $m, PREG_OFFSET_CAPTURE)) {
                        $pos = strpos($cfg, "\n", $m[0][1]);
                        if ($pos !== false) {
                            $cfg = substr_replace($cfg, $insert, $pos + 1, 0);
                        } else {
                            $cfg = $insert . $cfg;
                        }
                    } else {
                        $cfg = $insert . $cfg;
                    }

                    $tmp = $configFile . '.tmp';
                    // Write with exclusive lock then atomically rename
                    $written = file_put_contents($tmp, $cfg, LOCK_EX);
                    if ($written === false || !@rename($tmp, $configFile)) {
                        @unlink($tmp);
                        $error = 'Failed to write new configuration file (check file permissions).';
                    } else {
                        // Invalidate OPcache if present so new file is used by subsequent requests
                        if (function_exists('opcache_invalidate')) {
                            @opcache_invalidate($configFile, true);
                        }

                        $message = 'Password changed successfully.';
                        // Include (not require_once) the config so constants are available in this request
                        include $configFile;
                    }
                }
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Change Admin Password</title>
    <link rel="stylesheet" href="../styles/style.css">
</head>
<body>
    <h1>Change Admin Password</h1>
    <?php if ($error): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if ($message): ?>
        <div class="success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <form method="post" action="change_password.php">
        <label for="current_password">Current password:</label>
        <input type="password" id="current_password" name="current_password" required>

        <label for="new_password">New password:</label>
        <input type="password" id="new_password" name="new_password" required>

        <label for="confirm_password">Confirm new password:</label>
        <input type="password" id="confirm_password" name="confirm_password" required>

        <div style="margin-top:12px;"><button type="submit">Change Password</button></div>
    </form>

    <p><a href="settings.php">Back to Settings</a></p>
</body>
</html>

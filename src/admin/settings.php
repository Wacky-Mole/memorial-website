<?php
session_start();

// Check if the user is logged in as admin
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: login.php');
    exit();
}

// Include configuration file
require_once '../config.php';
require_once __DIR__ . '/../service/settings.php';

// Initialize variables for settings
$memorial_name = MEMORIAL_NAME;
$message = '';

// Load notification/SMTP settings from DB (fallback to config.php defaults)
$notify_on_submission = (get_setting('notify_on_submission', (defined('NOTIFY_ON_SUBMISSION') && NOTIFY_ON_SUBMISSION) ? '1' : '0') === '1');
$notify_email = get_setting('notify_email', defined('NOTIFY_EMAIL') ? NOTIFY_EMAIL : ADMIN_EMAIL);
$smtp_enabled = (get_setting('smtp_enabled', (defined('SMTP_ENABLED') && SMTP_ENABLED) ? '1' : '0') === '1');
$smtp_host = get_setting('smtp_host', defined('SMTP_HOST') ? SMTP_HOST : '');
$smtp_port = get_setting('smtp_port', defined('SMTP_PORT') ? SMTP_PORT : 25);
$smtp_username = get_setting('smtp_username', defined('SMTP_USERNAME') ? SMTP_USERNAME : '');
$smtp_password = get_setting('smtp_password', defined('SMTP_PASSWORD') ? SMTP_PASSWORD : '');
$smtp_secure = get_setting('smtp_secure', defined('SMTP_SECURE') ? SMTP_SECURE : 'none');

// Handle form submission: update memorial name and optional photo
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $memorial_name = trim($_POST['memorial_name'] ?? '');

    if (empty($memorial_name)) {
        $error = 'Please provide a memorial name.';
    } else {
        // Handle photo upload using safe processor (randomized filename, MIME check, resize)
        $photo_path = MEMORIAL_PHOTO;
        if (isset($_FILES['memorial_photo']) && $_FILES['memorial_photo']['error'] === UPLOAD_ERR_OK) {
            require_once __DIR__ . '/../service/image_utils.php';
            list($ok, $result) = safeProcessUpload($_FILES['memorial_photo'], 'memorial', 1200, 1200);
            if ($ok) {
                // store path relative to site root
                $photo_path = $result;
            } else {
                $error = 'Memorial photo upload failed: ' . htmlspecialchars($result);
            }
        }

        if (!isset($error)) {
            // Update config.php by replacing or adding defines
            $configFile = __DIR__ . '/../config.php';
            $cfg = file_get_contents($configFile);

            // Replace MEMORIAL_NAME
            if (preg_match("/define\(\'MEMORIAL_NAME\',[^;]+;\)/", $cfg)) {
                $cfg = preg_replace(
                    "/define\(\'MEMORIAL_NAME\',[^;]+;\)/",
                    "define('MEMORIAL_NAME', '" . addslashes($memorial_name) . "');",
                    $cfg
                );
            } else {
                $cfg .= "\n// Memorial name\ndefine('MEMORIAL_NAME', '" . addslashes($memorial_name) . "');\n";
            }

            // Replace MEMORIAL_PHOTO
            if (preg_match("/define\(\'MEMORIAL_PHOTO\',[^;]+;\)/", $cfg)) {
                $cfg = preg_replace(
                    "/define\(\'MEMORIAL_PHOTO\',[^;]+;\)/",
                    "define('MEMORIAL_PHOTO', '" . addslashes($photo_path) . "');",
                    $cfg
                );
            } else {
                $cfg .= "\n// Memorial photo\ndefine('MEMORIAL_PHOTO', '" . addslashes($photo_path) . "');\n";
            }

            // Update SITE_TITLE if desired
            if (preg_match("/define\(\'SITE_TITLE\',[^;]+;\)/", $cfg)) {
                $cfg = preg_replace(
                    "/define\(\'SITE_TITLE\',[^;]+;\)/",
                    "define('SITE_TITLE', 'In Memory of " . addslashes($memorial_name) . "');",
                    $cfg
                );
            } else {
                $cfg .= "\n// Site title\ndefine('SITE_TITLE', 'In Memory of " . addslashes($memorial_name) . "');\n";
            }

            // Write back
            if (file_put_contents($configFile, $cfg) === false) {
                $error = 'Failed to write configuration file.';
            } else {
                // reload config values in current request
                require_once $configFile;
                $message = 'Settings updated successfully.';
            }
        }
    }

    // Persist notification / SMTP settings to DB
    $notify_on_submission = isset($_POST['notify_on_submission']) ? '1' : '0';
    $notify_email = filter_var(trim($_POST['notify_email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $smtp_enabled = isset($_POST['smtp_enabled']) ? '1' : '0';
    $smtp_host = trim($_POST['smtp_host'] ?? '');
    $smtp_port = intval($_POST['smtp_port'] ?? 25);
    $smtp_username = trim($_POST['smtp_username'] ?? '');
    $smtp_password = trim($_POST['smtp_password'] ?? '');
    $smtp_secure = in_array($_POST['smtp_secure'] ?? 'none', ['none','tls','ssl']) ? $_POST['smtp_secure'] : 'none';

    set_setting('notify_on_submission', $notify_on_submission);
    set_setting('notify_email', $notify_email);
    set_setting('smtp_enabled', $smtp_enabled);
    set_setting('smtp_host', $smtp_host);
    set_setting('smtp_port', (string)$smtp_port);
    set_setting('smtp_username', $smtp_username);
    set_setting('smtp_password', $smtp_password);
    set_setting('smtp_secure', $smtp_secure);

    // Refresh local variables for form display
    $notify_on_submission = ($notify_on_submission === '1');
    $smtp_enabled = ($smtp_enabled === '1');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Settings</title>
    <link rel="stylesheet" href="../styles/style.css">
</head>
<body>
    <h1>Admin Settings</h1>

    <?php if (isset($error)): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if (isset($_GET['success'])): ?>
        <div class="success">Settings updated successfully!</div>
    <?php endif; ?>

    <form action="settings.php" method="post" enctype="multipart/form-data">
        <label for="memorial_name">Memorial Name:</label>
        <input type="text" id="memorial_name" name="memorial_name" value="<?php echo htmlspecialchars($memorial_name); ?>" required>

        <label for="memorial_photo">Memorial Photo (optional):</label>
        <input type="file" id="memorial_photo" name="memorial_photo" accept="image/*">

        <h3>Notifications</h3>
        <label>
            <input type="checkbox" name="notify_on_submission" value="1" <?php echo $notify_on_submission ? 'checked' : ''; ?>>
            Email admin on new submissions
        </label>

        <label for="notify_email">Notification Email:</label>
        <input type="email" id="notify_email" name="notify_email" value="<?php echo htmlspecialchars($notify_email); ?>">

        <h3>SMTP Settings (optional)</h3>
        <label>
            <input type="checkbox" name="smtp_enabled" value="1" <?php echo $smtp_enabled ? 'checked' : ''; ?>>
            Use SMTP to send notification emails
        </label>

        <label for="smtp_host">SMTP Host:</label>
        <input type="text" id="smtp_host" name="smtp_host" value="<?php echo htmlspecialchars($smtp_host); ?>">

        <label for="smtp_port">SMTP Port:</label>
        <input type="number" id="smtp_port" name="smtp_port" value="<?php echo htmlspecialchars($smtp_port); ?>">

        <label for="smtp_username">SMTP Username:</label>
        <input type="text" id="smtp_username" name="smtp_username" value="<?php echo htmlspecialchars($smtp_username); ?>">

        <label for="smtp_password">SMTP Password:</label>
        <input type="password" id="smtp_password" name="smtp_password" value="<?php echo htmlspecialchars($smtp_password); ?>">

        <label for="smtp_secure">SMTP Secure:</label>
        <select id="smtp_secure" name="smtp_secure">
            <option value="none" <?php echo ($smtp_secure === 'none') ? 'selected' : ''; ?>>None</option>
            <option value="tls" <?php echo ($smtp_secure === 'tls') ? 'selected' : ''; ?>>TLS (STARTTLS)</option>
            <option value="ssl" <?php echo ($smtp_secure === 'ssl') ? 'selected' : ''; ?>>SSL</option>
        </select>

        <div style="margin-top:12px;">
            <button type="submit">Save Settings</button>
        </div>
    </form>

    <a href="index.php">Back to Admin Dashboard</a>
</body>
</html>
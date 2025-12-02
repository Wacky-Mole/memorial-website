<?php
session_start();

// Check if the user is logged in as admin
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: login.php');
    exit();
}

// Include configuration file
require_once '../config.php';

// Initialize variables for settings
$memorial_name = MEMORIAL_NAME;
$message = '';

// Handle form submission: update memorial name and optional photo
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $memorial_name = trim($_POST['memorial_name'] ?? '');

    if (empty($memorial_name)) {
        $error = 'Please provide a memorial name.';
    } else {
        // Handle photo upload
        $photo_path = MEMORIAL_PHOTO;
        if (isset($_FILES['memorial_photo']) && $_FILES['memorial_photo']['error'] === UPLOAD_ERR_OK) {
            $allowed = ALLOWED_FILE_TYPES ?? ['image/jpeg','image/png','image/gif'];
            if (!in_array($_FILES['memorial_photo']['type'], $allowed)) {
                $error = 'Invalid image type for memorial photo.';
            } elseif ($_FILES['memorial_photo']['size'] > (MAX_FILE_SIZE ?? 2097152)) {
                $error = 'Memorial photo exceeds maximum allowed size.';
            } else {
                // Save uploaded file to upload dir
                $uploadDir = rtrim(UPLOAD_DIR, '/') . '/memorial/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                $ext = pathinfo($_FILES['memorial_photo']['name'], PATHINFO_EXTENSION);
                $filename = 'memorial_photo.' . $ext;
                $dest = $uploadDir . $filename;
                if (move_uploaded_file($_FILES['memorial_photo']['tmp_name'], $dest)) {
                    // store path relative to site root
                    $photo_path = $uploadDir . $filename;
                } else {
                    $error = 'Failed to save uploaded photo.';
                }
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

        <div style="margin-top:12px;">
            <button type="submit">Save Settings</button>
        </div>
    </form>

    <a href="index.php">Back to Admin Dashboard</a>
</body>
</html>
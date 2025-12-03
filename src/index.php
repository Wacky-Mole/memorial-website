<?php
session_start();

// Include configuration and necessary services
require_once 'config.php';
require_once 'service/navbar.php';

// Check if the installation is complete
if (!isConfigured()) {
    header('Location: install.php');
    exit();
}

// Display the homepage
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(defined('SITE_TITLE') ? SITE_TITLE : SITE_NAME); ?></title>
    <link rel="stylesheet" href="styles/style.css">
</head>
<body>
    <?php renderNavbar(isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in']); ?>
    <div class="container">
        <h1><?php echo htmlspecialchars(defined('SITE_TITLE') ? SITE_TITLE : SITE_NAME); ?></h1>
        <?php
            // Show memorial photo only if the file actually exists on disk (avoid broken requests)
            $photoPath = trim(MEMORIAL_PHOTO);
            $photoShown = false;
            if (!empty($photoPath)) {
                // Build filesystem path relative to this script
                $fsPath = __DIR__ . '/' . ltrim($photoPath, '/\\');
                if (file_exists($fsPath)) {
                    $photoShown = true;
                    // Append file modification time to bust caches and ensure latest orientation is used
                    $mtime = @filemtime($fsPath);
                    $cacheBust = $mtime ? ('?v=' . $mtime) : '';
                    echo '<div style="text-align:center; margin: 20px 0;">';
                    echo '<img src="' . htmlspecialchars($photoPath . $cacheBust) . '" alt="' . htmlspecialchars(MEMORIAL_NAME) . '" style="max-width:300px; border-radius:6px;" loading="lazy">';
                    echo '</div>';
                }
            }
            if (!$photoShown) {
                // lightweight placeholder to avoid layout jump and show a stable UI
                echo '<div style="text-align:center; margin: 20px 0; color:#666;">';
                echo '<div style="display:inline-block;width:200px;height:120px;border-radius:6px;background:#f0f0f0;line-height:120px;">No photo</div>';
                echo '</div>';
            }
        ?>
        <p>Here you can honor and remember <?php echo htmlspecialchars(!empty(MEMORIAL_NAME) ? MEMORIAL_NAME : 'your loved one'); ?>.</p>
        <a href="form.php" class="btn">Add a Memorial Entry</a>
    </div>
</body>
</html>
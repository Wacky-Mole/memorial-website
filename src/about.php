<?php
session_start();
require_once 'config.php';
require_once 'service/navbar.php';
require_once __DIR__ . '/service/settings.php';

// Check if configured
if (!isConfigured()) { header('Location: install.php'); exit(); }

$content = get_setting('page_about', '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?php echo htmlspecialchars(defined('SITE_TITLE') ? SITE_TITLE : SITE_NAME); ?> - About</title>
    <link rel="stylesheet" href="styles/style.css">
    <?php $favicon = get_setting('favicon',''); if (!empty($favicon)) echo '<link rel="icon" href="' . htmlspecialchars($favicon) . '">'; ?>
</head>
<body>
    <?php renderNavbar(isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in']); ?>
    <div class="container">
        <h1>About</h1>
        <div class="page-content">
            <?php echo $content; // admin-provided HTML ?>
        </div>
    </div>
</body>
</html>

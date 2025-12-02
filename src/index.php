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
        <?php if (!empty(MEMORIAL_PHOTO)) : ?>
            <div style="text-align:center; margin: 20px 0;">
                <img src="<?php echo htmlspecialchars(MEMORIAL_PHOTO); ?>" alt="<?php echo htmlspecialchars(MEMORIAL_NAME); ?>" style="max-width:300px; border-radius:6px;" />
            </div>
        <?php endif; ?>
        <p>Here you can honor and remember <?php echo htmlspecialchars(!empty(MEMORIAL_NAME) ? MEMORIAL_NAME : 'your loved one'); ?>.</p>
        <a href="form.php" class="btn">Add a Memorial Entry</a>
    </div>
</body>
</html>
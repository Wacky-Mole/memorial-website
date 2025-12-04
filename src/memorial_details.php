<?php
session_start();
require_once 'config.php';
require_once 'service/navbar.php';
require_once __DIR__ . '/service/settings.php';

if (!isConfigured()) { header('Location: install.php'); exit(); }

$content = get_setting('page_memorial_details', '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?php echo htmlspecialchars(defined('SITE_TITLE') ? SITE_TITLE : SITE_NAME); ?> - Memorial Details</title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars(asset_url('styles/style.css')); ?>">
    <?php $favicon = get_setting('favicon',''); if (!empty($favicon)) echo '<link rel="icon" href="' . htmlspecialchars(asset_url($favicon)) . '">'; ?>
</head>
<body>
    <?php renderNavbar(isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in']); ?>
    <div class="container">
        <h1>Memorial Details</h1>
        <div class="page-content">
            <?php echo $content; ?>
        </div>
    </div>
</body>
</html>

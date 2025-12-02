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
    <title>No Database Memorial</title>
    <link rel="stylesheet" href="styles/style.css">
</head>
<body>
    <?php include 'service/navbar.php'; ?>
    <div class="container">
        <h1>Welcome to the Memorial Website</h1>
        <p>Here you can honor and remember your loved ones.</p>
        <a href="form.php" class="btn">Add a Memorial Entry</a>
    </div>
</body>
</html>
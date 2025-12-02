<?php
// form.php

session_start();
require_once 'config.php';
require_once 'service/navbar.php';

// If admin is logged in, provide link to admin panel
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    // do nothing special here; admin panel available at /admin
}

// Initialize variables for contributor form
$email = $name = $message = "";
$email_err = $name_err = $message_err = "";

// Process form submission and forward to save.php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate email
    if (empty(trim($_POST["email"]))) {
        $email_err = "Please enter your email.";
    } elseif (!filter_var(trim($_POST["email"]), FILTER_VALIDATE_EMAIL)) {
        $email_err = "Invalid email format.";
    } else {
        $email = trim($_POST["email"]);
    }

    // Validate contributor name
    if (empty(trim($_POST["name"]))) {
        $name_err = "Please enter your name.";
    } else {
        $name = trim($_POST["name"]);
    }

    // Validate message
    if (empty(trim($_POST["message"]))) {
        $message_err = "Please enter your memory or message.";
    } else {
        $message = trim($_POST["message"]);
    }

    // If valid, post to save.php for processing
    if (empty($email_err) && empty($name_err) && empty($message_err)) {
        // Forward form data to save.php
        // we'll submit via POST using a small HTML form to preserve file uploads if needed later
        ?>
        <form id="forward" action="save.php" method="post" enctype="multipart/form-data">
            <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
            <input type="hidden" name="name" value="<?php echo htmlspecialchars($name); ?>">
            <input type="hidden" name="message" value="<?php echo htmlspecialchars($message); ?>">
        </form>
        <script>document.getElementById('forward').submit();</script>
        <?php
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles/style.css">
    <title>Memorial Entry Form</title>
</head>
<body>
    <?php renderNavbar(isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in']); ?>
    <div class="container">
        <h2>Share Your Memory of <?php echo htmlspecialchars(!empty(MEMORIAL_NAME) ? MEMORIAL_NAME : 'a loved one'); ?></h2>
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data">
            <div>
                <label for="email">Your Email:</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($email); ?>">
                <span><?php echo $email_err; ?></span>
            </div>
            <div>
                <label for="name">Your Name:</label>
                <input type="text" name="name" value="<?php echo htmlspecialchars($name); ?>">
                <span><?php echo $name_err; ?></span>
            </div>
            <div>
                <label for="message">Your Memory / Message:</label>
                <textarea name="message"><?php echo htmlspecialchars($message); ?></textarea>
                <span><?php echo $message_err; ?></span>
            </div>
            <div>
                <input type="submit" value="Submit">
            </div>
        </form>
    </div>
</body>
</html>
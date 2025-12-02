<?php
// form.php

session_start();

// Check if the user is already logged in (for admin access)
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: admin/index.php");
    exit;
}

// Initialize variables
$email = $name = $photo = "";
$email_err = $name_err = $photo_err = "";

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate email
    if (empty(trim($_POST["email"]))) {
        $email_err = "Please enter your email.";
    } elseif (!filter_var(trim($_POST["email"]), FILTER_VALIDATE_EMAIL)) {
        $email_err = "Invalid email format.";
    } else {
        $email = trim($_POST["email"]);
    }

    // Validate name
    if (empty(trim($_POST["name"]))) {
        $name_err = "Please enter the name of the deceased.";
    } else {
        $name = trim($_POST["name"]);
    }

    // Validate photo upload
    if (isset($_FILES["photo"]) && $_FILES["photo"]["error"] == 0) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($_FILES["photo"]["type"], $allowed_types)) {
            $photo_err = "Only JPG, PNG, and GIF files are allowed.";
        } else {
            $photo = $_FILES["photo"];
        }
    } else {
        $photo_err = "Please upload a photo.";
    }

    // Check for errors before saving
    if (empty($email_err) && empty($name_err) && empty($photo_err)) {
        // Save the data (this should be handled in save.php)
        // Redirect to a success page or display a success message
        header("Location: save.php?email=" . urlencode($email) . "&name=" . urlencode($name));
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
    <div class="container">
        <h2>Memorial Entry Form</h2>
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data">
            <div>
                <label for="email">Email:</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($email); ?>">
                <span><?php echo $email_err; ?></span>
            </div>
            <div>
                <label for="name">Name of the Deceased:</label>
                <input type="text" name="name" value="<?php echo htmlspecialchars($name); ?>">
                <span><?php echo $name_err; ?></span>
            </div>
            <div>
                <label for="photo">Upload Photo:</label>
                <input type="file" name="photo">
                <span><?php echo $photo_err; ?></span>
            </div>
            <div>
                <input type="submit" value="Submit">
            </div>
        </form>
    </div>
</body>
</html>
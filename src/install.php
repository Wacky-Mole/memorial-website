<?php
session_start();

// Include configuration and service files
require_once 'config.php';
require_once 'service/installer.php';

// Check if the website is already configured
if (isConfigured()) {
    header('Location: index.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $deceasedName = trim($_POST['deceased_name']);
    $photo = $_FILES['photo'];

    // Validate inputs
    if (empty($email) || empty($deceasedName) || $photo['error'] !== UPLOAD_ERR_OK) {
        $error = "Please fill in all fields and upload a valid photo.";
    } else {
        // Save the data and photo
        if (saveMemorialEntry($email, $deceasedName, $photo)) {
            $_SESSION['success'] = "Memorial entry created successfully!";
            header('Location: index.php');
            exit;
        } else {
            $error = "Failed to save the memorial entry. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install Memorial Website</title>
    <link rel="stylesheet" href="styles/style.css">
</head>
<body>
    <h1>Setup Your Memorial Website</h1>
    <?php if (isset($error)): ?>
        <p class="error"><?php echo $error; ?></p>
    <?php endif; ?>
    <form action="install.php" method="post" enctype="multipart/form-data">
        <label for="email">Your Email:</label>
        <input type="email" name="email" id="email" required>
        
        <label for="deceased_name">Name of the Deceased:</label>
        <input type="text" name="deceased_name" id="deceased_name" required>
        
        <label for="photo">Upload a Photo:</label>
        <input type="file" name="photo" id="photo" accept="image/*" required>
        
        <button type="submit">Submit</button>
    </form>
</body>
</html>
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
$username = '';
$password = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // Validate input
    if (!empty($username) && !empty($password)) {
        // Save new settings (this is a placeholder, implement actual saving logic)
        // For example, save to a configuration file or database
        // updateSettings($username, $password);

        // Redirect to the settings page with a success message
        header('Location: settings.php?success=1');
        exit();
    } else {
        $error = 'Please fill in all fields.';
    }
}

// Fetch current settings (this is a placeholder, implement actual fetching logic)
// $currentSettings = getCurrentSettings();
// $username = $currentSettings['username'];
// $password = $currentSettings['password'];
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

    <form action="settings.php" method="post">
        <label for="username">Username:</label>
        <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" required>

        <label for="password">Password:</label>
        <input type="password" id="password" name="password" required>

        <button type="submit">Save Settings</button>
    </form>

    <a href="index.php">Back to Admin Dashboard</a>
</body>
</html>
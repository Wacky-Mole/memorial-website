<?php
session_start();
require_once '../config.php';
require_once '../service/storage.php';

if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: login.php');
    exit();
}

$entryId = $_GET['id'] ?? null;
$entry = null;

if ($entryId) {
    $entry = getEntryById($entryId); // Function to retrieve entry data by ID
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $photo = $_FILES['photo'] ?? null;

    if ($entry) {
        updateEntry($entryId, $name, $email, $photo); // Function to update entry
        header('Location: index.php');
        exit();
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../styles/style.css">
    <title>Edit Memorial Entry</title>
</head>
<body>
    <div class="container">
        <h1>Edit Memorial Entry</h1>
        <form action="" method="POST" enctype="multipart/form-data">
            <label for="name">Name of the Deceased:</label>
            <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($entry['name'] ?? ''); ?>" required>

            <label for="email">Your Email:</label>
            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($entry['email'] ?? ''); ?>" required>

            <label for="photo">Upload Photo:</label>
            <input type="file" id="photo" name="photo" accept="image/*">

            <button type="submit">Update Entry</button>
        </form>
        <a href="index.php">Back to Admin Dashboard</a>
    </div>
</body>
</html>
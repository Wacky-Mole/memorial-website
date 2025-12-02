<?php
session_start();

// Check if the user is logged in as admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php'); // Redirect to login page if not logged in
    exit;
}

// Include necessary files
include_once '../config.php';
include_once '../service/navbar.php';

// Fetch memorial entries from the database or storage
$entries = []; // This should be replaced with actual data fetching logic

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="../styles/style.css">
</head>
<body>
    <?php include '../service/navbar.php'; ?>

    <div class="container">
        <h1>Admin Dashboard</h1>
        <h2>Manage Memorial Entries</h2>

        <table>
            <thead>
                <tr>
                    <th>Email</th>
                    <th>Name of Deceased</th>
                    <th>Photo</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($entries as $entry): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($entry['email']); ?></td>
                        <td><?php echo htmlspecialchars($entry['name']); ?></td>
                        <td><img src="../images/<?php echo htmlspecialchars($entry['photo']); ?>" alt="Photo" width="100"></td>
                        <td>
                            <a href="edit.php?id=<?php echo $entry['id']; ?>">Edit</a>
                            <a href="delete.php?id=<?php echo $entry['id']; ?>">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
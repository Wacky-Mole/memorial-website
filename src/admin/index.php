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

require_once __DIR__ . '/../service/storage.php';

$message = '';
// Handle moderation actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['ids'])) {
    $action = $_POST['action'];
    $ids = array_map('intval', $_POST['ids']);

    if ($action === 'approve') {
        if (updateEntriesStatus($ids, 'APPROVED')) {
            $message = 'Selected entries approved.';
        }
    } elseif ($action === 'bin') {
        if (updateEntriesStatus($ids, 'BIN')) {
            $message = 'Selected entries moved to bin.';
        }
    } elseif ($action === 'delete') {
        if (deleteEntries($ids)) {
            $message = 'Selected entries deleted.';
        }
    }
}

// Load entries according to filter
$filter = $_GET['status'] ?? 'NOT_APPROVED';
if ($filter === 'ALL') {
    $entries = getEntries(null);
} else {
    $entries = getEntries($filter);
}
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
    <?php renderNavbar(true); ?>

    <div class="container">
        <h1>Admin Dashboard - <?php echo htmlspecialchars(defined('SITE_TITLE') ? SITE_TITLE : SITE_NAME); ?></h1>
        <h2>Manage entries for <?php echo htmlspecialchars(!empty(MEMORIAL_NAME) ? MEMORIAL_NAME : 'the memorial'); ?></h2>

        <?php if (!empty($message)) : ?>
            <div class="success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <p>Filter: 
            <a href="?status=NOT_APPROVED">Not approved</a> |
            <a href="?status=APPROVED">Approved</a> |
            <a href="?status=BIN">Bin</a> |
            <a href="?status=ALL">All</a>
        </p>

        <form method="post" action="settings.php">
            <!-- placeholder to keep same-origin for file uploads in settings if used -->
        </form>

        <form method="post" action="index.php">
            <table border="1" cellpadding="6" cellspacing="0">
                <thead>
                    <tr>
                        <th></th>
                        <th>Email</th>
                        <th>Contributor</th>
                        <th>Message</th>
                        <th>Photo</th>
                        <th>Created At</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $filter = $_GET['status'] ?? 'NOT_APPROVED';
                    foreach ($entries as $entry):
                        if ($filter !== 'ALL' && $entry['status'] !== $filter) continue;
                    ?>
                        <tr>
                            <td><input type="checkbox" name="ids[]" value="<?php echo intval($entry['_id']); ?>"></td>
                            <td><?php echo htmlspecialchars($entry['email'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($entry['contributor'] ?? ''); ?></td>
                            <td style="max-width:400px; white-space:normal;"><?php echo nl2br(htmlspecialchars($entry['message'] ?? '')); ?></td>
                            <td>
                                <?php if (!empty($entry['photo'])): ?>
                                    <img src="<?php echo htmlspecialchars($entry['photo']); ?>" alt="photo" width="100">
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($entry['created_at'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($entry['status']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div style="margin-top:12px;">
                <button type="submit" name="action" value="approve">Approve selected</button>
                <button type="submit" name="action" value="bin">Move to bin</button>
                <button type="submit" name="action" value="delete" onclick="return confirm('Permanently delete selected entries?');">Delete selected</button>
            </div>
        </form>
    </div>
</body>
</html>
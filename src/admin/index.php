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
require_once __DIR__ . '/../service/settings.php';

// Compute app root prefix (same logic as navbar) so admin can build correct URLs
$scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
if ($scriptDir === '/' || $scriptDir === '.') $scriptDir = '';
$appRoot = $scriptDir;
if (substr($appRoot, -6) === '/admin') {
    $appRoot = rtrim(dirname($appRoot), '/\\');
}
$rootPrefix = ($appRoot === '' ? '' : $appRoot);

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
            $message = 'Selected entries marked rejected.';
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
    <?php
        $favicon = get_setting('favicon', '');
        if (!empty($favicon)) echo '<link rel="icon" href="' . htmlspecialchars(asset_url($favicon)) . '">';
    ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars(asset_url('styles/style.css')); ?>">
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
            <a href="?status=NOT_APPROVED">Pending Approval</a> |
            <a href="?status=APPROVED">Approved</a> |
            <a href="?status=BIN">Rejected</a> |
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
                        <th>Actions</th>
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
                                <td><input type="checkbox" name="ids[]" value="<?php echo intval($entry['id']); ?>"></td>
                                <td style="white-space:nowrap;">
                                    <button type="button" class="admin-row-action" data-action="approve" data-id="<?php echo intval($entry['id']); ?>" style="margin-right:6px;">✅</button>
                                    <button type="button" class="admin-row-action" data-action="bin" data-id="<?php echo intval($entry['id']); ?>" style="margin-right:6px;">🚫</button>
                                    <button type="button" class="admin-row-action" data-action="delete" data-id="<?php echo intval($entry['id']); ?>" style="color:#900;">🗑</button>
                                </td>
                            <td><?php echo htmlspecialchars($entry['email'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($entry['contributor'] ?? ''); ?></td>
                            <td style="max-width:400px; white-space:normal;"><?php echo nl2br(htmlspecialchars($entry['message'] ?? '')); ?></td>
                            <td>
                                <?php if (!empty($entry['photo'])):
                                    $photoField = $entry['photo'];
                                    $photos = [];
                                    // Detect JSON array (new format) or legacy string
                                    $trimmed = trim($photoField);
                                    if (strpos($trimmed, '[') === 0) {
                                        $decoded = json_decode($trimmed, true);
                                        if (is_array($decoded)) $photos = $decoded;
                                    } else {
                                        // legacy single path
                                        $photos = [['path' => $photoField, 'caption' => '']];
                                    }
                                    foreach ($photos as $ph):
                                        if (empty($ph['path'])) continue;
                                        // Build a root-aware URL for the image
                                        $rawPath = ltrim($ph['path'], '/');
                                        if (strpos($ph['path'], '/') === 0) {
                                            // already absolute from web root
                                            $imgUrl = $ph['path'];
                                        } else {
                                            $imgUrl = ($rootPrefix === '' ? '/' . $rawPath : $rootPrefix . '/' . $rawPath);
                                        }
                                        ?>
                                            <div style="margin-bottom:8px;">
                                                <img src="<?php echo htmlspecialchars($imgUrl); ?>" alt="photo" width="120" style="display:block; margin-bottom:6px;">
                                                <?php if (!empty($ph['caption'])): ?>
                                                    <div style="font-size:90%; color:#333;"><?php echo htmlspecialchars($ph['caption']); ?></div>
                                                <?php endif; ?>
                                            </div>
                                    <?php endforeach;
                                endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($entry['created_at'] ?? ''); ?></td>
                            <td>
                                <?php
                                $st = $entry['status'] ?? '';
                                if ($st === 'NOT_APPROVED') echo 'Pending Approval';
                                elseif ($st === 'APPROVED') echo 'Approved';
                                elseif ($st === 'BIN') echo 'Rejected';
                                else echo htmlspecialchars($st);
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div style="margin-top:12px;">
                <button type="submit" name="action" value="approve">Approve selected</button>
                <button type="submit" name="action" value="bin">Reject selected</button>
                <button type="submit" name="action" value="delete" onclick="return confirm('Permanently delete selected entries?');">Delete selected</button>
            </div>
        </form>
    </div>
    <script>
    (function(){
        // Attach click handlers to per-row admin action buttons (Approve / Reject / Delete)
        document.querySelectorAll('.admin-row-action').forEach(function(btn){
            btn.addEventListener('click', function(){
                var id = this.getAttribute('data-id');
                var action = this.getAttribute('data-action');
                if (!id || !action) return;
                if (action === 'delete' && !confirm('Permanently delete this entry?')) return;

                var params = new URLSearchParams();
                params.append('action', action);
                params.append('ids[]', id);

                fetch(window.location.pathname + window.location.search, {
                    method: 'POST',
                    body: params,
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                }).then(function(resp){
                    // reload page to show updated list and message
                    window.location.reload();
                }).catch(function(){
                    alert('Network error while performing action.');
                });
            });
        });
    })();
    </script>
</body>
</html>
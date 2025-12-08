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

// Prefer DB-backed memorial name when available (fallback to DEFAULT_MEMORIAL_NAME)
$memorialName = function_exists('get_setting') ? get_setting('memorial_name', (defined('DEFAULT_MEMORIAL_NAME') ? DEFAULT_MEMORIAL_NAME : '')) : (defined('DEFAULT_MEMORIAL_NAME') ? DEFAULT_MEMORIAL_NAME : '');

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
        } elseif ($action === 'allow-embed') {
            if (function_exists('updateEntriesEmbedAllowed') && updateEntriesEmbedAllowed($ids, 1)) {
                $message = 'Embed allowed for selected entries.';
            }
        } elseif ($action === 'disallow-embed') {
            if (function_exists('updateEntriesEmbedAllowed') && updateEntriesEmbedAllowed($ids, 0)) {
                $message = 'Embed disabled for selected entries.';
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
        <h2>Manage entries for <?php echo htmlspecialchars(!empty($memorialName) ? $memorialName : 'the memorial'); ?></h2>

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

        <form method="post" action="index.php?status=<?php echo urlencode($filter); ?>">
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
                                    <?php $embedState = isset($entry['embed_allowed']) && intval($entry['embed_allowed']) === 1 ? '1' : '0'; ?>
                                    <button type="button" class="admin-row-action" data-action="toggle-embed" data-id="<?php echo intval($entry['id']); ?>" data-embed="<?php echo $embedState; ?>" title="Toggle embed for this entry" style="margin-right:6px;">📺</button>
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
                                        // If this photo item is actually a video, label it clearly for admins
                                        if (!empty($ph['type']) && $ph['type'] === 'video') {
                                            $videoUrl = $ph['path'];
                                            // Try to show a cached thumbnail if available
                                            $thumbWeb = '';
                                            $thumbFs = __DIR__ . '/../data/video_thumbs/' . intval($entry['id']) . '.jpg';
                                            if (file_exists($thumbFs)) {
                                                $thumbWeb = ($rootPrefix === '' ? '/data/video_thumbs/' : $rootPrefix . '/data/video_thumbs/') . intval($entry['id']) . '.jpg';
                                            }
                                            ?>
                                            <div style="margin-bottom:8px; padding:6px; border:1px solid #eee; border-radius:6px; background:#fff;">
                                                <div style="font-weight:bold; margin-bottom:6px;">🎞️ Video</div>
                                                <?php if (!empty($thumbWeb)): ?>
                                                    <img src="<?php echo htmlspecialchars($thumbWeb); ?>" alt="video thumbnail" width="120" style="display:block; margin-bottom:6px;">
                                                <?php else: ?>
                                                    <div style="font-size:90%; color:#666; margin-bottom:6px;">No thumbnail available</div>
                                                <?php endif; ?>
                                                <a href="<?php echo htmlspecialchars($videoUrl); ?>" target="_blank" rel="noopener noreferrer">Open video</a>
                                                <?php if (!empty($ph['caption'])): ?>
                                                    <div style="font-size:90%; color:#333; margin-top:6px;"><?php echo htmlspecialchars($ph['caption']); ?></div>
                                                <?php endif; ?>
                                                <?php if (isset($entry['embed_allowed']) && intval($entry['embed_allowed']) === 1): ?>
                                                    <div style="font-size:90%; color:green; margin-top:6px;">Embed allowed</div>
                                                <?php else: ?>
                                                    <div style="font-size:90%; color:#999; margin-top:6px;">Embed disabled</div>
                                                <?php endif; ?>
                                            </div>
                                            <?php
                                            continue;
                                        }
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
                <button type="submit" name="action" value="allow-embed">Allow embed selected</button>
                <button type="submit" name="action" value="disallow-embed">Disable embed selected</button>
            </div>
        </form>
    </div>
    <script>
    (function(){
        // Attach click handlers to per-row admin action buttons (Approve / Reject / Delete / Toggle Embed)
        document.querySelectorAll('.admin-row-action').forEach(function(btn){
            btn.addEventListener('click', function(){
                var id = this.getAttribute('data-id');
                var action = this.getAttribute('data-action');
                if (!id || !action) return;
                if (action === 'delete' && !confirm('Permanently delete this entry?')) return;

                // If action is toggle-embed, decide whether to allow or disallow based on current data-embed
                if (action === 'toggle-embed') {
                    var current = this.getAttribute('data-embed');
                    action = (current === '1') ? 'disallow-embed' : 'allow-embed';
                }

                var params = new URLSearchParams();
                params.append('action', action);
                params.append('ids[]', id);

                // Post to the same path including current query string so we remain on the same status filter
                fetch(window.location.pathname + window.location.search, {
                    method: 'POST',
                    body: params,
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                }).then(function(resp){
                    // If this was a toggle embed action, update the button state in-place to avoid a full reload
                    if (action === 'allow-embed' || action === 'disallow-embed') {
                        try {
                            var btnEl = document.querySelector('.admin-row-action[data-id="' + id + '"][data-action="toggle-embed"]');
                            if (btnEl) {
                                var now = (action === 'allow-embed') ? '1' : '0';
                                btnEl.setAttribute('data-embed', now);
                                // optional visual cue: change title and small badge in the row if present
                                btnEl.title = (now === '1') ? 'Embed allowed (click to disable)' : 'Embed disabled (click to allow)';
                            }
                        } catch (e) { /* ignore DOM update errors */ }
                        // reload to reflect status badges/messages while staying on same filter
                        window.location.reload();
                    } else {
                        // For other actions, reload page and keep same status filter (query string preserved)
                        window.location.reload();
                    }
                }).catch(function(){
                    alert('Network error while performing action.');
                });
            });
        });
    })();
    </script>
</body>
</html>
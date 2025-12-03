<?php
session_start();

// Include configuration and necessary services
require_once 'config.php';
require_once 'service/navbar.php';
require_once __DIR__ . '/service/storage.php';
require_once __DIR__ . '/service/settings.php';

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
    <title><?php echo htmlspecialchars(defined('SITE_TITLE') ? SITE_TITLE : SITE_NAME); ?></title>
    <link rel="stylesheet" href="styles/style.css">
</head>
<body>
    <?php renderNavbar(isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in']); ?>
    <div class="container">
        <h1><?php echo htmlspecialchars(defined('SITE_TITLE') ? SITE_TITLE : SITE_NAME); ?></h1>
        <?php
            // Show memorial photo only if the file actually exists on disk (avoid broken requests)
            $photoPath = trim(MEMORIAL_PHOTO);
            $photoShown = false;
            if (!empty($photoPath)) {
                // Build filesystem path relative to this script
                $fsPath = __DIR__ . '/' . ltrim($photoPath, '/\\');
                if (file_exists($fsPath)) {
                    $photoShown = true;
                    // Append file modification time to bust caches and ensure latest orientation is used
                    $mtime = @filemtime($fsPath);
                    $cacheBust = $mtime ? ('?v=' . $mtime) : '';
                    echo '<div style="text-align:center; margin: 20px 0;">';
                    echo '<img src="' . htmlspecialchars($photoPath . $cacheBust) . '" alt="' . htmlspecialchars(MEMORIAL_NAME) . '" style="max-width:300px; border-radius:6px;" loading="lazy">';
                    echo '</div>';
                }
            }
            if (!$photoShown) {
                // lightweight placeholder to avoid layout jump and show a stable UI
                echo '<div style="text-align:center; margin: 20px 0; color:#666;">';
                echo '<div style="display:inline-block;width:200px;height:120px;border-radius:6px;background:#f0f0f0;line-height:120px;">No photo</div>';
                echo '</div>';
            }
        ?>
        <p>Here you can honor and remember <?php echo htmlspecialchars(!empty(MEMORIAL_NAME) ? MEMORIAL_NAME : 'your loved one'); ?>.</p>
        <a href="form.php" class="btn">Add a Memorial Entry</a>

        <section class="entries-section">
            <h2>Memories</h2>
            <?php
                // Fetch approved entries
                $entries = getApprovedEntries();

                // Compute root prefix like navbar to build correct image URLs when served from a subdirectory
                $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
                if ($scriptDir === '/' || $scriptDir === '.') $scriptDir = '';
                $appRoot = $scriptDir;
                if (substr($appRoot, -6) === '/admin') {
                    $appRoot = rtrim(dirname($appRoot), '/\\');
                }
                $rootPrefix = ($appRoot === '' ? '' : $appRoot);

                if (empty($entries)) {
                    echo '<p style="color:#666;">No entries have been posted yet.</p>';
                } else {
                    echo '<div class="entries-grid">';
                    foreach ($entries as $entry) {
                        // Only show APPROVED entries
                        if (($entry['status'] ?? '') !== 'APPROVED') continue;
                        $message = trim($entry['message'] ?? '');
                        $contributor = trim($entry['contributor'] ?? '');
                        $photoField = trim($entry['photo'] ?? '');

                        // Parse photos (JSON array of {path,caption}) or legacy single path
                        $photos = [];
                        if ($photoField !== '') {
                            $trimmed = ltrim($photoField);
                            if (strpos($trimmed, '[') === 0) {
                                $decoded = json_decode($trimmed, true);
                                if (is_array($decoded)) $photos = $decoded;
                            } else {
                                $photos = [['path' => $photoField, 'caption' => '']];
                            }
                        }

                        echo '<article class="entry">';

                        if (!empty($photos)) {
                            echo '<div class="entry-photos">';
                            foreach ($photos as $ph) {
                                $p = $ph['path'] ?? '';
                                $cap = $ph['caption'] ?? '';
                                if (empty($p)) continue;
                                // Build root-aware URL
                                $rawPath = ltrim($p, '/');
                                if (strpos($p, '/') === 0) {
                                    $imgUrl = $p;
                                } else {
                                    $imgUrl = ($rootPrefix === '' ? '/' . $rawPath : $rootPrefix . '/' . $rawPath);
                                }
                                // Try to append filemtime as cache-bust
                                $fsPath = __DIR__ . '/' . ltrim($p, '/\\');
                                $cache = '';
                                if (file_exists($fsPath)) { $mt = @filemtime($fsPath); if ($mt) $cache = '?v=' . $mt; }

                                // Image with hover title of contributor
                                $titleAttr = $contributor ? ('Submitted by: ' . $contributor) : '';
                                echo '<figure class="entry-photo">';
                                echo '<img src="' . htmlspecialchars($imgUrl . $cache) . '" alt="photo" title="' . htmlspecialchars($titleAttr) . '" loading="lazy">';
                                if (!empty($cap)) echo '<figcaption class="entry-caption">' . htmlspecialchars($cap) . '</figcaption>';
                                echo '</figure>';
                            }
                            echo '</div>'; // entry-photos
                            // Show message and author beneath photo block
                            echo '<div class="entry-message">' . nl2br(htmlspecialchars($message)) . '<div class="entry-author">— ' . htmlspecialchars($contributor) . '</div></div>';
                        } else {
                            // Message-only bubble
                            echo '<div class="entry-bubble">';
                            echo '<div class="entry-message">' . nl2br(htmlspecialchars($message)) . '</div>';
                            echo '<div class="entry-author">— ' . htmlspecialchars($contributor) . '</div>';
                            echo '</div>';
                        }

                        echo '</article>';
                    }
                    echo '</div>'; // entries-grid
                }
            ?>
        </section>
    </div>
    <?php
        // Render admin-configurable footer HTML (may contain basic markup)
        $footer_html = get_setting('footer_html', '');
        if (!empty($footer_html)) {
            echo '<footer class="site-footer">' . $footer_html . '</footer>';
        }
    ?>
</body>
</html>
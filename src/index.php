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
    <?php
        // Allow admin-configured title prefix and position when available
        $titlePrefix = function_exists('get_setting') ? get_setting('title_prefix', '') : '';
        $titlePos = function_exists('get_setting') ? get_setting('title_prefix_position', 'before') : 'before';
        if (!empty($titlePrefix) && !empty(MEMORIAL_NAME)) {
            if ($titlePos === 'after') {
                $displayTitle = MEMORIAL_NAME . ' ' . $titlePrefix;
            } else {
                $displayTitle = $titlePrefix . ' ' . MEMORIAL_NAME;
            }
        } else {
            $displayTitle = defined('SITE_TITLE') ? SITE_TITLE : SITE_NAME;
        }
    ?>
    <title><?php echo htmlspecialchars($displayTitle); ?></title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars(asset_url('styles/style.css')); ?>">
    <?php
        // Output configured favicon if present
        $favicon_path = get_setting('favicon', '');
        if (!empty($favicon_path)) {
            echo '<link rel="icon" href="' . htmlspecialchars($favicon_path) . '">';
        }
    ?>
</head>
<body>
    <?php renderNavbar(isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in']); ?>
    <main>
        <section class="hero">
            <div class="container">
                <h1><?php echo htmlspecialchars($displayTitle); ?></h1>
                <?php
                    // Show memorial photo and optional hero side photos
                    $photoPath = trim(MEMORIAL_PHOTO);
                    $photoShown = false;
                    if (!empty($photoPath)) {
                        $fsPath = __DIR__ . '/' . ltrim($photoPath, '/\\');
                        if (file_exists($fsPath)) {
                            $photoShown = true;
                        }
                    }

                    // Load hero side images from settings (JSON)
                    $heroLeft = function_exists('get_setting') ? json_decode(get_setting('hero_left','[]'), true) : [];
                    $heroRight = function_exists('get_setting') ? json_decode(get_setting('hero_right','[]'), true) : [];

                    $heroOnly = (empty($heroLeft) && empty($heroRight));
                    echo '<div class="hero-grid' . ($heroOnly ? ' hero-only' : '') . '">';
                    // Left column
                    echo '<div class="hero-side hero-left">';
                    if (!empty($heroLeft) && is_array($heroLeft)) {
                        foreach ($heroLeft as $hl) {
                            $p = $hl['path'] ?? '';
                            $u = $hl['url'] ?? '';
                            if (empty($p)) continue;
                            $imgUrl = (strpos($p, '/') === 0) ? $p : ('/' . ltrim($p, '/'));
                            $fs = __DIR__ . '/' . ltrim($p, '/\\');
                            $cache = (file_exists($fs) ? ('?v=' . @filemtime($fs)) : '');
                            if (!empty($u)) echo '<a href="' . htmlspecialchars($u) . '">';
                            echo '<img src="' . htmlspecialchars($imgUrl . $cache) . '" alt="" class="hero-side-img">';
                            if (!empty($u)) echo '</a>';
                        }
                    }
                    echo '</div>';

                    // Center column (main photo)
                    echo '<div class="hero-center">';
                    if ($photoShown) {
                        $mtime = @filemtime($fsPath);
                        $cacheBust = $mtime ? ('?v=' . $mtime) : '';
                        echo '<img class="lightbox-img hero-main" src="' . htmlspecialchars($photoPath . $cacheBust) . '" alt="' . htmlspecialchars(MEMORIAL_NAME) . '" loading="lazy">';
                    } else {
                        echo '<div class="hero-placeholder">No photo</div>';
                    }
                    echo '</div>';

                    // Right column
                    echo '<div class="hero-side hero-right">';
                    if (!empty($heroRight) && is_array($heroRight)) {
                        foreach ($heroRight as $hr) {
                            $p = $hr['path'] ?? '';
                            $u = $hr['url'] ?? '';
                            if (empty($p)) continue;
                            $imgUrl = (strpos($p, '/') === 0) ? $p : ('/' . ltrim($p, '/'));
                            $fs = __DIR__ . '/' . ltrim($p, '/\\');
                            $cache = (file_exists($fs) ? ('?v=' . @filemtime($fs)) : '');
                            if (!empty($u)) echo '<a href="' . htmlspecialchars($u) . '">';
                            echo '<img src="' . htmlspecialchars($imgUrl . $cache) . '" alt="" class="hero-side-img">';
                            if (!empty($u)) echo '</a>';
                        }
                    }
                    echo '</div>';

                    echo '</div>'; // hero-grid
                ?>
                <p class="lead">Here you can honor and remember <?php echo htmlspecialchars(!empty(MEMORIAL_NAME) ? MEMORIAL_NAME : 'your loved one'); ?>.</p>
                <a href="form.php" class="btn">Add a Memorial/Photos Entry</a>
            </div>
        </section>

        <div class="container">
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
                                echo '<img class="lightbox-img" src="' . htmlspecialchars($imgUrl . $cache) . '" alt="photo" title="' . htmlspecialchars($titleAttr) . '" loading="lazy" style="cursor:zoom-in;">';
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

                        // Heart button (show count and current state by checking hearts_count and per-IP lookup)
                        $heartCount = isset($entry['hearts_count']) ? intval($entry['hearts_count']) : 0;
                        $userIp = $_SERVER['REMOTE_ADDR'] ?? '';
                        $userHearted = false;
                        try {
                            $pdo_for_hearts = getPDO();
                            $qh = $pdo_for_hearts->prepare('SELECT id FROM hearts WHERE entry_id = :eid AND ip = :ip LIMIT 1');
                            $qh->execute([':eid' => $entry['id'], ':ip' => $userIp]);
                            if ($qh->fetch(PDO::FETCH_ASSOC)) $userHearted = true;
                        } catch (Exception $e) { /* ignore */ }

                        echo '<div class="entry-actions">';
                        echo '<button class="heart-btn' . ($userHearted ? ' hearted' : '') . '" data-entry-id="' . intval($entry['id']) . '" data-hearted="' . ($userHearted ? 'true' : 'false') . '">';
                        echo '❤ <span class="heart-count">' . $heartCount . '</span>';
                        echo '</button>';
                        echo '</div>';

                        echo '</article>';
                    }
                    echo '</div>'; // entries-grid
                }
            ?>
        </section>
    </div>
    </main>
    <?php
        // Render admin-configured footer page (managed via Pages admin)
        $pageFooter = get_setting('page_footer', '');
        if (!empty($pageFooter)) {
            echo '<footer class="site-footer">' . $pageFooter . '</footer>';
        }
    ?>
    
        <div id="lightboxOverlay" class="lightbox-overlay" role="dialog" aria-modal="true" style="display:none;">
            <div class="lightbox-close" id="lightboxClose" title="Close">✕</div>
            <div class="lightbox-content" id="lightboxContent"></div>
        </div>

        <script>
        (function(){
            var overlay = document.getElementById('lightboxOverlay');
            var content = document.getElementById('lightboxContent');
            var closeBtn = document.getElementById('lightboxClose');

            function openLightbox(src, alt, caption){
                content.innerHTML = '';
                var img = document.createElement('img');
                img.src = src;
                img.alt = alt || '';
                content.appendChild(img);
                if (caption) {
                    var cap = document.createElement('div');
                    cap.className = 'lightbox-caption';
                    cap.textContent = caption;
                    content.appendChild(cap);
                }
                overlay.style.display = 'flex';
                // small timeout to allow CSS transition
                setTimeout(function(){ overlay.classList.add('open'); }, 10);
                document.addEventListener('keydown', onKey);
            }

            function closeLightbox(){
                overlay.classList.remove('open');
                document.removeEventListener('keydown', onKey);
                setTimeout(function(){ overlay.style.display = 'none'; content.innerHTML = ''; }, 200);
            }

            function onKey(e){ if (e.key === 'Escape') closeLightbox(); }

            closeBtn.addEventListener('click', closeLightbox);
            overlay.addEventListener('click', function(e){ if (e.target === overlay) closeLightbox(); });

            // Attach to all images with class lightbox-img
            function attach(){
                var imgs = document.querySelectorAll('img.lightbox-img');
                imgs.forEach(function(i){
                    i.style.cursor = 'zoom-in';
                    if (!i.__lightboxAttached) {
                        i.addEventListener('click', function(e){ 
                            // Use the image title attribute as the caption per request
                            var caption = i.title || '';
                            openLightbox(i.src, i.alt, caption);
                        });
                        i.__lightboxAttached = true;
                    }
                });
            }

            // initial attach
            attach();
            // in case images are added later, observe DOM
            var obs = new MutationObserver(function(){ attach(); });
            obs.observe(document.body, { childList:true, subtree:true });
            // Heart button handling: toggle via AJAX POST to heart.php
            function initHearts(){
                document.querySelectorAll('.heart-btn').forEach(function(btn){
                    if (btn.__heartAttached) return;
                    btn.__heartAttached = true;
                    btn.addEventListener('click', function(){
                        var id = btn.getAttribute('data-entry-id');
                        var countEl = btn.querySelector('.heart-count');
                        // immediate micro-interaction: pop animation
                        btn.classList.remove('popping');
                        // trigger reflow to restart animation
                        // eslint-disable-next-line no-unused-expressions
                        void btn.offsetWidth;
                        btn.classList.add('popping');
                        countEl.classList.remove('pulse');
                        void countEl.offsetWidth;
                        countEl.classList.add('pulse');

                        var form = new FormData();
                        form.append('entry_id', id);
                        fetch('heart.php', { method: 'POST', body: form }).then(function(resp){
                            return resp.json();
                        }).then(function(json){
                            if (json && json.ok) {
                                countEl.textContent = json.count;
                                btn.setAttribute('data-hearted', json.hearted ? 'true' : 'false');
                                if (json.hearted) btn.classList.add('hearted'); else btn.classList.remove('hearted');
                            } else {
                                alert('Unable to toggle heart');
                            }
                        }).catch(function(){ alert('Network error'); })
                        .finally(function(){
                            // cleanup animation classes after finished
                            setTimeout(function(){ btn.classList.remove('popping'); countEl.classList.remove('pulse'); }, 420);
                        });
                    });
                });
            }
            initHearts();
            var obs2 = new MutationObserver(function(){ initHearts(); });
            obs2.observe(document.body, { childList:true, subtree:true });
        })();
        
        </script>
</body>
</html>
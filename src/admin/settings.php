<?php
session_start();

// Check if the user is logged in as admin
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: login.php');
    exit();
}

// Include configuration file
require_once '../config.php';
require_once __DIR__ . '/../service/navbar.php';
require_once __DIR__ . '/../service/settings.php';
require_once __DIR__ . '/../service/upload_check.php';

// Initialize variables for settings
$memorial_name = MEMORIAL_NAME;
$message = '';

// Title prefix (e.g., "In Memory of") stored in settings
$title_prefix = get_setting('title_prefix', 'In Memory of');
// Title prefix position: 'before' or 'after' the memorial name
$title_prefix_position = get_setting('title_prefix_position', 'before');

// Load notification/SMTP settings from DB (fallback to config.php defaults)
$notify_on_submission = (get_setting('notify_on_submission', (defined('NOTIFY_ON_SUBMISSION') && NOTIFY_ON_SUBMISSION) ? '1' : '0') === '1');
$notify_email = get_setting('notify_email', defined('NOTIFY_EMAIL') ? NOTIFY_EMAIL : ADMIN_EMAIL);
$smtp_enabled = (get_setting('smtp_enabled', (defined('SMTP_ENABLED') && SMTP_ENABLED) ? '1' : '0') === '1');
$smtp_host = get_setting('smtp_host', defined('SMTP_HOST') ? SMTP_HOST : '');
$smtp_port = get_setting('smtp_port', defined('SMTP_PORT') ? SMTP_PORT : 25);
$smtp_username = get_setting('smtp_username', defined('SMTP_USERNAME') ? SMTP_USERNAME : '');
$smtp_password = get_setting('smtp_password', defined('SMTP_PASSWORD') ? SMTP_PASSWORD : '');
$smtp_secure = get_setting('smtp_secure', defined('SMTP_SECURE') ? SMTP_SECURE : 'none');
// Auto-approve new submissions (stored in DB settings)
$auto_approve = (get_setting('auto_approve', '0') === '1');
// Current favicon setting
$current_favicon = get_setting('favicon', '');
// Hero side images (JSON arrays of {path, url})
$hero_left = json_decode(get_setting('hero_left', '[]'), true) ?: [];
$hero_right = json_decode(get_setting('hero_right', '[]'), true) ?: [];

// Handle form submission: update memorial name and optional photo
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Manual rotate action for existing stable image
    if (isset($_POST['rotate_direction']) && in_array($_POST['rotate_direction'], ['left','right'])) {
        $dir = $_POST['rotate_direction'];
        $baseDir = realpath(__DIR__ . '/..');
        if ($baseDir === false) $baseDir = __DIR__ . '/..';
        $currentWeb = trim(MEMORIAL_PHOTO);
        $fsPath = rtrim($baseDir, '/\\') . '/' . ltrim($currentWeb, '/\\');
        if (!file_exists($fsPath)) {
            $error = 'Cannot rotate: file not found: ' . htmlspecialchars($currentWeb);
        } else {
            // load
            $ext = strtolower(pathinfo($fsPath, PATHINFO_EXTENSION));
            $img = @imagecreatefromstring(file_get_contents($fsPath));
            if ($img === false) {
                $error = 'Failed to load image for rotation.';
            } else {
                $angle = ($dir === 'left') ? 90 : -90;
                $rot = @imagerotate($img, $angle, 0);
                if ($rot === false) {
                    $error = 'Rotation failed.';
                } else {
                    // write back according to extension
                    $saved = false;
                    if (in_array($ext, ['png'])) $saved = imagepng($rot, $fsPath);
                    elseif (in_array($ext, ['gif'])) $saved = imagegif($rot, $fsPath);
                    else $saved = imagejpeg($rot, $fsPath, 90);
                    imagedestroy($rot);
                    imagedestroy($img);
                    if ($saved) {
                        @chmod($fsPath, 0644);
                        $message = 'Image rotated ' . htmlspecialchars($dir) . '.';
                    } else {
                        $error = 'Failed to save rotated image.';
                    }
                }
            }
        }
    }
    $memorial_name = trim($_POST['memorial_name'] ?? '');

    if (empty($memorial_name)) {
        $error = 'Please provide a memorial name.';
    } else {
        // Handle photo upload using safe processor (randomized filename, MIME check, resize)
        $photo_path = MEMORIAL_PHOTO;
        if (isset($_FILES['memorial_photo']) && $_FILES['memorial_photo']['error'] === UPLOAD_ERR_OK) {
            require_once __DIR__ . '/../service/image_utils.php';
            list($ok, $result) = safeProcessUpload($_FILES['memorial_photo'], 'memorial', 1200, 1200);
            // Log upload attempt for debugging
            try {
                $logdir = defined('DATA_DIR') ? DATA_DIR : __DIR__ . '/../data/';
                if (!is_dir($logdir)) @mkdir($logdir, 0755, true);
                $logfile = rtrim($logdir, '/\\') . '/upload_debug.log';
                $entry = date('c') . " | upload attempt\n";
                $entry .= "FILES: " . json_encode(array_intersect_key($_FILES['memorial_photo'], ['name'=>1,'type'=>1,'size'=>1,'error'=>1])) . "\n";
                $entry .= "safeProcessUpload result: " . json_encode([$ok, $result]) . "\n\n";
                @file_put_contents($logfile, $entry, FILE_APPEND);
            } catch (Exception $e) {
                // ignore logging errors
            }
            if ($ok) {
                // store path relative to site root, but use a stable filename so config doesn't change on each upload
                $uploadedWebPath = $result; // e.g. 'uploads/memorial/abcdef123.jpg'

                // Compute filesystem paths based on project src dir
                $baseDir = realpath(__DIR__ . '/..');
                if ($baseDir === false) $baseDir = __DIR__ . '/..';
                $uploadedFsPath = rtrim($baseDir, '/\\') . '/' . ltrim($uploadedWebPath, '/\\');

                // We will always store the stable memorial photo as PNG: uploads/memorial/main.png
                $stableWebPath = 'uploads/memorial/main.png';
                $stableFsPath = rtrim($baseDir, '/\\') . '/' . $stableWebPath;

                // Convert the uploaded file to PNG and write to stable path
                $converted = false;
                if (file_exists($uploadedFsPath)) {
                    $data = @file_get_contents($uploadedFsPath);
                    if ($data !== false) {
                        $img = @imagecreatefromstring($data);
                        if ($img !== false) {
                            $stableDir = dirname($stableFsPath);
                            if (!is_dir($stableDir)) mkdir($stableDir, 0755, true);
                            if (@imagepng($img, $stableFsPath)) {
                                $converted = true;
                            }
                            imagedestroy($img);
                        }
                    }
                }

                if ($converted) {
                    @chmod($stableFsPath, 0644);
                    // remove original uploaded file if present
                    if (file_exists($uploadedFsPath) && realpath($uploadedFsPath) !== realpath($stableFsPath)) @unlink($uploadedFsPath);
                    $photo_path = $stableWebPath;
                } else {
                    $error = 'Memorial photo upload succeeded but could not be converted to PNG.';
                }
            } else {
                $error = 'Memorial photo upload failed: ' . htmlspecialchars($result);
            }
        }

        if (!isset($error)) {
            // Update config.php by replacing or adding defines
            $configFile = __DIR__ . '/../config.php';
            $cfg = file_get_contents($configFile);

            // Replace MEMORIAL_NAME
            if (preg_match("/define\(\'MEMORIAL_NAME\',[^;]+;\)/", $cfg)) {
                $cfg = preg_replace(
                    "/define\(\'MEMORIAL_NAME\',[^;]+;\)/",
                    "define('MEMORIAL_NAME', '" . addslashes($memorial_name) . "');",
                    $cfg
                );
            } else {
                $cfg .= "\n// Memorial name\ndefine('MEMORIAL_NAME', '" . addslashes($memorial_name) . "');\n";
            }

            // Replace MEMORIAL_PHOTO
            if (preg_match("/define\(\'MEMORIAL_PHOTO\',[^;]+;\)/", $cfg)) {
                $cfg = preg_replace(
                    "/define\(\'MEMORIAL_PHOTO\',[^;]+;\)/",
                    "define('MEMORIAL_PHOTO', '" . addslashes($photo_path) . "');",
                    $cfg
                );
            } else {
                $cfg .= "\n// Memorial photo\ndefine('MEMORIAL_PHOTO', '" . addslashes($photo_path) . "');\n";
            }

            // Update SITE_TITLE if desired
            // Use saved title prefix when updating SITE_TITLE
            $savedPrefix = get_setting('title_prefix', 'In Memory of');
            $titleText = $savedPrefix . ' ' . $memorial_name;
            if (preg_match("/define\(\'SITE_TITLE\',[^;]+;\)/", $cfg)) {
                $cfg = preg_replace(
                    "/define\(\'SITE_TITLE\',[^;]+;\)/",
                    "define('SITE_TITLE', '" . addslashes($titleText) . "');",
                    $cfg
                );
            } else {
                $cfg .= "\n// Site title\ndefine('SITE_TITLE', '" . addslashes($titleText) . "');\n";
            }

            // Write back
            if (file_put_contents($configFile, $cfg) === false) {
                $error = 'Failed to write configuration file.';
                // log write failure
                if (defined('DATA_DIR')) @file_put_contents(DATA_DIR . 'upload_debug.log', date('c') . " | config write failed\n", FILE_APPEND);
            } else {
                // reload config values in current request
                require_once $configFile;
                $message = 'Settings updated successfully.';
            }
        }
    }

    // Persist notification / SMTP settings to DB
    $notify_on_submission = isset($_POST['notify_on_submission']) ? '1' : '0';
    $auto_approve = isset($_POST['auto_approve']) ? '1' : '0';
    // Favicon choice handling
    $favicon_choice = $_POST['favicon_choice'] ?? '';
    // If admin selected one of the preset choices
    if ($favicon_choice === 'red_heart') {
        // Prefer ICO, then SVG, then PNG for the bundled red heart
        $baseDir = realpath(__DIR__ . '/..'); if ($baseDir === false) $baseDir = __DIR__ . '/..';
        $candidates = ['ico', 'svg', 'png'];
        $foundExt = '';
        $src = '';
        foreach ($candidates as $ext) {
            $candidate = rtrim($baseDir, '/\\') . '/data/favicon/red_heart.' . $ext;
            if (file_exists($candidate)) { $foundExt = $ext; $src = $candidate; break; }
        }
        if ($foundExt !== '') {
            $destDir = rtrim($baseDir, '/\\') . '/uploads/favicon';
            if (!is_dir($destDir)) @mkdir($destDir, 0755, true);
            $dest = $destDir . '/red_heart.' . $foundExt;
            if (file_exists($src) && (!file_exists($dest) || filemtime($src) > filemtime($dest))) {
                @copy($src, $dest);
                @chmod($dest, 0644);
            }
            $webPath = 'uploads/favicon/red_heart.' . $foundExt;
            set_setting('favicon', $webPath);
            $current_favicon = $webPath;
        } else {
            $error = 'Red heart favicon asset not found in data/favicon/ (looked for .ico .svg .png).';
        }
    } elseif ($favicon_choice === 'none') {
        set_setting('favicon', '');
        $current_favicon = '';
    }

    // Handle uploaded custom favicon if present
    if (isset($_FILES['favicon_file']) && $_FILES['favicon_file']['error'] === UPLOAD_ERR_OK) {
        $f = $_FILES['favicon_file'];
        $allowed = ['image/svg+xml', 'image/png', 'image/x-icon', 'image/vnd.microsoft.icon', 'image/jpeg'];
        if (!in_array($f['type'], $allowed)) {
            $error = 'Uploaded favicon must be an SVG, PNG, ICO, or JPG file.';
        } else {
            $baseDir = realpath(__DIR__ . '/..');
            if ($baseDir === false) $baseDir = __DIR__ . '/..';
            $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
            if ($ext === '') {
                // try to infer from mime
                $map = ['image/svg+xml'=>'svg','image/png'=>'png','image/x-icon'=>'ico','image/vnd.microsoft.icon'=>'ico','image/jpeg'=>'jpg'];
                $ext = $map[$f['type']] ?? 'png';
            }
            $destDir = rtrim($baseDir, '/\\') . '/uploads/favicon';
            if (!is_dir($destDir)) @mkdir($destDir, 0755, true);
            $stableName = 'custom.' . $ext;
            $destPath = $destDir . '/' . $stableName;
            if (@move_uploaded_file($f['tmp_name'], $destPath)) {
                @chmod($destPath, 0644);
                $webPath = 'uploads/favicon/' . $stableName;
                set_setting('favicon', $webPath);
                $current_favicon = $webPath;
            } else {
                $error = 'Failed to move uploaded favicon.';
            }
        }
    }
    $notify_email = filter_var(trim($_POST['notify_email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $smtp_enabled = isset($_POST['smtp_enabled']) ? '1' : '0';
    $smtp_host = trim($_POST['smtp_host'] ?? '');
    $smtp_port = intval($_POST['smtp_port'] ?? 25);
    $smtp_username = trim($_POST['smtp_username'] ?? '');
    $smtp_password = trim($_POST['smtp_password'] ?? '');
    $smtp_secure = in_array($_POST['smtp_secure'] ?? 'none', ['none','tls','ssl']) ? $_POST['smtp_secure'] : 'none';

    set_setting('notify_on_submission', $notify_on_submission);
    set_setting('auto_approve', $auto_approve);
    // Save title prefix and position
    $title_prefix_post = trim($_POST['title_prefix'] ?? '');
    if ($title_prefix_post === '') $title_prefix_post = 'In Memory of';
    set_setting('title_prefix', $title_prefix_post);
    $title_prefix_position_post = in_array($_POST['title_prefix_position'] ?? 'before', ['before','after']) ? $_POST['title_prefix_position'] : 'before';
    set_setting('title_prefix_position', $title_prefix_position_post);
    set_setting('notify_email', $notify_email);
    set_setting('smtp_enabled', $smtp_enabled);
    set_setting('smtp_host', $smtp_host);
    set_setting('smtp_port', (string)$smtp_port);
    set_setting('smtp_username', $smtp_username);
    set_setting('smtp_password', $smtp_password);
    set_setting('smtp_secure', $smtp_secure);

    // Handle hero side uploads (up to 2 per side). Inputs: hero_left_file_0, hero_left_url_0, hero_right_file_0, hero_right_url_0, etc.
    $maxPerSide = 2;
    $newLeft = [];
    $newRight = [];
    // Helper to process an uploaded file field name and return web path or empty
    function processHeroUpload($fieldName) {
        if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) return '';
        require_once __DIR__ . '/../service/image_utils.php';
        list($ok, $result) = safeProcessUpload($_FILES[$fieldName], 'hero', 1200, 1200);
        if ($ok) return $result; // web path
        return '';
    }
    for ($i = 0; $i < $maxPerSide; $i++) {
        $fileField = 'hero_left_file_' . $i;
        $urlField = 'hero_left_url_' . $i;
        $uploaded = processHeroUpload($fileField);
        $providedUrl = trim($_POST[$urlField] ?? '');
        // If admin provided an existing path input (string) allow it (in case they want to reference an existing upload)
        $existingPathField = 'hero_left_path_' . $i;
        $existingPath = trim($_POST[$existingPathField] ?? '');
        $pathToUse = $uploaded ?: $existingPath;
        if ($pathToUse !== '') {
            $newLeft[] = ['path' => $pathToUse, 'url' => $providedUrl];
        }
    }
    for ($i = 0; $i < $maxPerSide; $i++) {
        $fileField = 'hero_right_file_' . $i;
        $urlField = 'hero_right_url_' . $i;
        $uploaded = processHeroUpload($fileField);
        $providedUrl = trim($_POST[$urlField] ?? '');
        $existingPathField = 'hero_right_path_' . $i;
        $existingPath = trim($_POST[$existingPathField] ?? '');
        $pathToUse = $uploaded ?: $existingPath;
        if ($pathToUse !== '') {
            $newRight[] = ['path' => $pathToUse, 'url' => $providedUrl];
        }
    }
    set_setting('hero_left', json_encode($newLeft));
    set_setting('hero_right', json_encode($newRight));

    // Refresh local variables for form display
    $notify_on_submission = ($notify_on_submission === '1');
    $smtp_enabled = ($smtp_enabled === '1');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Settings</title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars(asset_url('styles/style.css')); ?>">
    <?php
        $favicon = get_setting('favicon', '');
        if (!empty($favicon)) echo '<link rel="icon" href="' . htmlspecialchars(asset_url($favicon)) . '">';
    ?>
</head>
<body>
    <h1>Admin Settings</h1>

    <?php if (isset($error)): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if (isset($_GET['success'])): ?>
        <div class="success">Settings updated successfully!</div>
    <?php endif; ?>

    <form action="settings.php" method="post" enctype="multipart/form-data">
        <label for="memorial_name">Memorial Name:</label>
        <input type="text" id="memorial_name" name="memorial_name" value="<?php echo htmlspecialchars($memorial_name); ?>" required>

        <label for="title_prefix">Title Prefix (e.g. "In Memory of"):</label>
        <input type="text" id="title_prefix" name="title_prefix" value="<?php echo htmlspecialchars($title_prefix); ?>" placeholder="In Memory of">
        <div style="margin-top:8px;">
            <label>Title Prefix Position:</label>
            <label style="margin-left:8px;"><input type="radio" name="title_prefix_position" value="before" <?php echo ($title_prefix_position === 'before') ? 'checked' : ''; ?>> Before name ("Prefix [NAME]")</label>
            <label style="margin-left:8px;"><input type="radio" name="title_prefix_position" value="after" <?php echo ($title_prefix_position === 'after') ? 'checked' : ''; ?>> After name ("[NAME] Prefix")</label>
        </div>

        <label for="memorial_photo">Memorial Photo (optional):</label>
        <!-- AJAX upload form -->
        <div id="upload-box">
            <input type="file" id="memorial_photo" name="memorial_photo" accept="image/*">
            <button type="button" id="upload-button">Upload Photo</button>
            <div id="upload-progress" style="display:none; margin-top:8px;">
                <div id="upload-progress-bar" style="width:0; height:10px; background:#4caf50;"></div>
                <div id="upload-progress-text" style="margin-top:4px; font-size:90%; color:#555;"></div>
            </div>
            <div id="upload-message" style="margin-top:8px;"></div>
            <?php
            // Warn if server PHP upload limits are lower than configured MAX_FILE_SIZE
            $limits = serverUploadLimits();
            if ($limits['effective_bytes'] > 0 && $limits['effective_bytes'] < MAX_FILE_SIZE) : ?>
                <div style="margin-top:8px; padding:10px; border-radius:6px; background:#fff4e5; color:#6b3b00;">
                    Warning: Server allows uploads up to <strong><?php echo htmlspecialchars($limits['effective_readable']); ?></strong> (upload_max_filesize=<?php echo htmlspecialchars($limits['upload_max_filesize']); ?>, post_max_size=<?php echo htmlspecialchars($limits['post_max_size']); ?>). Increase these in php.ini or reduce the configured max file size.
                </div>
            <?php endif; ?>
        </div>

        <div id="photo-preview" style="margin-top:12px;">
            <strong>Current Photo Preview:</strong>
            <div style="margin-top:8px;">
                <img id="preview-image" src="<?php echo htmlspecialchars(MEMORIAL_PHOTO); ?>" alt="Preview" style="max-width:300px; border-radius:6px; display:block;" data-webpath="<?php echo htmlspecialchars(MEMORIAL_PHOTO); ?>">
            </div>
        </div>
        <div style="margin-top:8px;">
            <button type="button" id="rotate-left-button" data-dir="left" style="display:inline-block; margin-right:8px;">Rotate Left</button>
            <button type="button" id="rotate-right-button" data-dir="right" style="display:inline-block;">Rotate Right</button>
        </div>

        <h3>Notifications</h3>
        <label>
            <input type="checkbox" name="notify_on_submission" value="1" <?php echo $notify_on_submission ? 'checked' : ''; ?>>
            Email admin on new submissions
        </label>

        <label style="display:block; margin-top:8px;">
            <input type="checkbox" name="auto_approve" value="1" <?php echo $auto_approve ? 'checked' : ''; ?>>
            Automatically approve new submissions (visible immediately)
        </label>

        <label for="notify_email">Notification Email:</label>
        <input type="email" id="notify_email" name="notify_email" value="<?php echo htmlspecialchars($notify_email); ?>">

        <h3>Favicon</h3>
        <p>Select a site favicon or upload your own. SVG/PNG/ICO recommended.</p>
        <div>
            <label>
                <input type="radio" name="favicon_choice" value="none" <?php echo ($current_favicon === '') ? 'checked' : ''; ?>> No favicon
            </label>
            <label style="margin-left:12px;">
                <input type="radio" name="favicon_choice" value="red_heart" <?php echo (strpos($current_favicon, 'red_heart') !== false) ? 'checked' : ''; ?>> Red heart
            </label>
        </div>
        <div style="margin-top:8px;">
            <label for="favicon_file">Upload custom favicon:</label>
            <input type="file" id="favicon_file" name="favicon_file" accept="image/*,.svg,.png,.ico">
        </div>
        <div style="margin-top:8px;">
            <strong>Current favicon preview:</strong>
            <div style="margin-top:6px;">
                <?php if (!empty($current_favicon)): 
                        $favUrl = asset_url($current_favicon);
                        $favFs = __DIR__ . '/../' . ltrim($current_favicon, '/\\');
                ?>
                    <?php if (file_exists($favFs)): ?>
                        <img src="<?php echo htmlspecialchars($favUrl); ?>" alt="favicon" style="width:32px;height:32px;vertical-align:middle;border:1px solid #ddd;padding:2px;background:#fff;">
                    <?php else: ?>
                        <div style="color:#b00;">Favicon not found at <code><?php echo htmlspecialchars($favUrl); ?></code></div>
                    <?php endif; ?>
                <?php else: ?>
                    <span style="color:#666;">(none)</span>
                <?php endif; ?>
            </div>
        </div>

        <h3>Hero Side Photos (left / right)</h3>
        <p>You can upload up to 2 photos for the left side and 2 photos for the right side of the main hero image. Optionally add a link URL for each photo (clickable).</p>
        <div style="display:flex;gap:20px;flex-wrap:wrap;">
            <div style="flex:1;min-width:260px;">
                <strong>Left side</strong>
                <?php for ($i=0;$i<2;$i++):
                    $existing = $hero_left[$i]['path'] ?? '';
                    $existingUrl = $hero_left[$i]['url'] ?? '';
                ?>
                    <div style="margin-top:8px;padding:8px;border:1px solid #eee;border-radius:6px;background:#fafafa;">
                        <label>Upload image (left <?php echo $i+1;?>):</label>
                        <input type="file" name="hero_left_file_<?php echo $i;?>" accept="image/*">
                        <div style="margin-top:6px;font-size:90%;color:#666;">Or use existing path:</div>
                        <input type="text" name="hero_left_path_<?php echo $i;?>" value="<?php echo htmlspecialchars($existing); ?>" placeholder="e.g. uploads/hero/left1.jpg">
                        <label style="display:block;margin-top:6px;">Link URL (optional):</label>
                        <input type="text" name="hero_left_url_<?php echo $i;?>" value="<?php echo htmlspecialchars($existingUrl); ?>" placeholder="https://example.com">
                    </div>
                <?php endfor; ?>
            </div>

            <div style="flex:1;min-width:260px;">
                <strong>Right side</strong>
                <?php for ($i=0;$i<2;$i++):
                    $existing = $hero_right[$i]['path'] ?? '';
                    $existingUrl = $hero_right[$i]['url'] ?? '';
                ?>
                    <div style="margin-top:8px;padding:8px;border:1px solid #eee;border-radius:6px;background:#fafafa;">
                        <label>Upload image (right <?php echo $i+1;?>):</label>
                        <input type="file" name="hero_right_file_<?php echo $i;?>" accept="image/*">
                        <div style="margin-top:6px;font-size:90%;color:#666;">Or use existing path:</div>
                        <input type="text" name="hero_right_path_<?php echo $i;?>" value="<?php echo htmlspecialchars($existing); ?>" placeholder="e.g. uploads/hero/right1.jpg">
                        <label style="display:block;margin-top:6px;">Link URL (optional):</label>
                        <input type="text" name="hero_right_url_<?php echo $i;?>" value="<?php echo htmlspecialchars($existingUrl); ?>" placeholder="https://example.com">
                    </div>
                <?php endfor; ?>
            </div>
        </div>

        <h3>SMTP Settings (optional)</h3>
        <label>
            <input type="checkbox" name="smtp_enabled" value="1" <?php echo $smtp_enabled ? 'checked' : ''; ?>>
            Use SMTP to send notification emails
        </label>

        <label for="smtp_host">SMTP Host:</label>
        <input type="text" id="smtp_host" name="smtp_host" value="<?php echo htmlspecialchars($smtp_host); ?>">

        <label for="smtp_port">SMTP Port:</label>
        <input type="number" id="smtp_port" name="smtp_port" value="<?php echo htmlspecialchars($smtp_port); ?>">

        <label for="smtp_username">SMTP Username:</label>
        <input type="text" id="smtp_username" name="smtp_username" value="<?php echo htmlspecialchars($smtp_username); ?>">

        <label for="smtp_password">SMTP Password:</label>
        <input type="password" id="smtp_password" name="smtp_password" value="<?php echo htmlspecialchars($smtp_password); ?>">

        <label for="smtp_secure">SMTP Secure:</label>
        <select id="smtp_secure" name="smtp_secure">
            <option value="none" <?php echo ($smtp_secure === 'none') ? 'selected' : ''; ?>>None</option>
            <option value="tls" <?php echo ($smtp_secure === 'tls') ? 'selected' : ''; ?>>TLS (STARTTLS)</option>
            <option value="ssl" <?php echo ($smtp_secure === 'ssl') ? 'selected' : ''; ?>>SSL</option>
        </select>

        <div style="margin-top:12px;">
            <button type="submit">Save Settings</button>
        </div>
    </form>

    <a href="index.php">Back to Admin Dashboard</a>
</body>
</html>

<script>
// AJAX upload with progress and preview
(function(){
    var input = document.getElementById('memorial_photo');
    var uploadBtn = document.getElementById('upload-button');
    var progress = document.getElementById('upload-progress');
    var bar = document.getElementById('upload-progress-bar');
    var ptext = document.getElementById('upload-progress-text');
    var msg = document.getElementById('upload-message');
    var preview = document.getElementById('preview-image');

    if (!input || !uploadBtn) return;

    uploadBtn.addEventListener('click', function(){
        msg.textContent = '';
        if (!input.files || input.files.length === 0) { msg.textContent = 'Please select a file first.'; return; }
        var file = input.files[0];
        var form = new FormData();
        form.append('memorial_photo', file);

        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'upload_photo.php', true);
        xhr.responseType = 'json';

        xhr.upload.onprogress = function(e){
            if (e.lengthComputable) {
                var pct = Math.round((e.loaded / e.total) * 100);
                progress.style.display = 'block';
                bar.style.width = pct + '%';
                ptext.textContent = pct + '% uploaded';
            }
        };

        xhr.onload = function(){
            progress.style.display = 'none';
            bar.style.width = '0%';
            ptext.textContent = '';
            var res = xhr.response;
            if (!res && xhr.responseText) {
                try { res = JSON.parse(xhr.responseText); } catch (e) { /* ignore */ }
            }
            if (!res) {
                msg.textContent = 'Upload failed (no response).';
                return;
            }
            if (res.ok) {
                msg.style.color = 'green';
                msg.textContent = res.message || 'Upload complete';
                // Compute a site-root-aware base URL so path resolves correctly from /admin/
                var basePath = window.location.pathname.replace(/\/admin\/.*$/, '/');
                if (!basePath) basePath = '/';
                // Ensure basePath ends with '/'
                if (basePath.slice(-1) !== '/') basePath += '/';
                // Remove any leading slash from response path to avoid '//' in URL
                var respPath = (res.path || '').replace(/^\/+/, '');
                var newPath = basePath + respPath + '?v=' + Date.now();
                preview.src = newPath;
            } else {
                msg.style.color = 'red';
                msg.textContent = res.message || 'Upload failed';
            }
        };

        xhr.onerror = function(){
            progress.style.display = 'none';
            msg.style.color = 'red';
            msg.textContent = 'Upload failed (network error).';
        };

        xhr.send(form);
    });

    // Attach click handlers to rotate buttons to POST via fetch and update preview
    function ajaxRotateDirection(dir){
        var data = new FormData();
        data.append('rotate_direction', dir);
        fetch('settings.php', { method: 'POST', body: data, credentials: 'same-origin' })
            .then(function(resp){
                // Build a site-root-aware preview URL from the stored data-webpath
                var basePath = window.location.pathname.replace(/\/admin\/.*$/, '/');
                if (!basePath) basePath = '/';
                if (basePath.slice(-1) !== '/') basePath += '/';
                var respPath = (preview.dataset && preview.dataset.webpath) ? preview.dataset.webpath.replace(/^\/+/, '') : preview.src.split('?')[0].replace(/^\/+/, '');
                var newPath = basePath + respPath + '?v=' + Date.now();
                preview.src = newPath;
                return resp.text();
            }).catch(function(){ /* ignore */ });
    }

    var btnLeft = document.getElementById('rotate-left-button');
    var btnRight = document.getElementById('rotate-right-button');
    if (btnLeft) btnLeft.addEventListener('click', function(e){ e.preventDefault(); ajaxRotateDirection('left'); });
    if (btnRight) btnRight.addEventListener('click', function(e){ e.preventDefault(); ajaxRotateDirection('right'); });
})();
</script>
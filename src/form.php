<?php
// form.php

session_start();
require_once 'config.php';
require_once 'service/navbar.php';
require_once __DIR__ . '/service/upload_check.php';
require_once __DIR__ . '/service/settings.php';

// If admin is logged in, provide link to admin panel
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    // do nothing special here; admin panel available at /admin
}

// Initialize variables for contributor form
$email = $name = $message = "";
$email_err = $name_err = $message_err = "";

// Process form submission and forward to save.php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate email (optional)
    $rawEmail = trim($_POST["email"] ?? '');
    if ($rawEmail === '') {
        $email = '';
    } elseif (!filter_var($rawEmail, FILTER_VALIDATE_EMAIL)) {
        $email_err = "Invalid email format.";
    } else {
        $email = $rawEmail;
    }

    // Validate contributor name
    if (empty(trim($_POST["name"]))) {
        $name_err = "Please enter your name.";
    } else {
        $name = trim($_POST["name"]);
    }

    // Validate message
    if (empty(trim($_POST["message"]))) {
        $message_err = "Please enter your memory or message.";
    } else {
        $message = trim($_POST["message"]);
    }

    // If valid, allow POST to save.php (form includes file upload now)
    if (empty($email_err) && empty($name_err) && empty($message_err)) {
        // proceed to rendering the form which will submit to save.php
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles/style.css">
    <?php
        $favicon = get_setting('favicon', '');
        if (!empty($favicon)) echo '<link rel="icon" href="' . htmlspecialchars($favicon) . '">';
    ?>
    <title>Memorial Entry Form</title>
</head>
<body>
    <?php renderNavbar(isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in']); ?>
    <div class="container">
        <h2>Share Your Memory of <?php echo htmlspecialchars(!empty(MEMORIAL_NAME) ? MEMORIAL_NAME : 'a loved one'); ?></h2>
        <form action="save.php" method="post" enctype="multipart/form-data">
            <div>
                <label for="email">Your Email (optional):</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($email); ?>">
                <span><?php echo $email_err; ?></span>
            </div>
            <div>
                <label for="name">Your Name:</label>
                <input type="text" name="name" value="<?php echo htmlspecialchars($name); ?>">
                <span><?php echo $name_err; ?></span>
            </div>
            <div>
                <label for="message">Your Memory / Message:</label>
                <textarea name="message" id="message-box"><?php echo htmlspecialchars($message); ?></textarea>
                <span><?php echo $message_err; ?></span>
            </div>
            <div>
                <label for="photo">Attach photos (optional):</label>
                <input type="file" name="photo[]" id="photo-input" accept="image/*" multiple>
                <div id="photo-hint" style="font-size:90%; color:#666; margin-top:6px;">Max file size <?php echo (int)(MAX_FILE_SIZE/1024/1024); ?>MB per photo; large images will be resized automatically.</div>
                <?php
                // Show server-side upload limit warning if lower than MAX_FILE_SIZE
                $limits = serverUploadLimits();
                if ($limits['effective_bytes'] > 0 && $limits['effective_bytes'] < MAX_FILE_SIZE) : ?>
                    <div style="margin-top:8px; padding:10px; border-radius:6px; background:#fff4e5; color:#6b3b00;">
                        Server PHP limits may prevent uploads larger than <strong><?php echo htmlspecialchars($limits['effective_readable']); ?></strong>.
                        Current PHP settings: upload_max_filesize=<?php echo htmlspecialchars($limits['upload_max_filesize']); ?>, post_max_size=<?php echo htmlspecialchars($limits['post_max_size']); ?>.
                        Please increase these values (php.ini) or select smaller images.
                    </div>
                <?php endif; ?>
                <div id="photo-preview" style="margin-top:8px; display:none;">
                    <div id="photo-preview-list" style="display:flex; gap:12px; flex-wrap:wrap;"></div>
                </div>
            </div>
            <div>
                <input type="submit" value="Submit">
            </div>
        </form>
        <script>
        (function(){
            const input = document.getElementById('photo-input');
            const previewBox = document.getElementById('photo-preview');
            const previewList = document.getElementById('photo-preview-list');
            if (!input) return;

            // Keep an in-memory list of selected files and captions so
            // users can select files multiple times and still see all previews.
            let storedFiles = [];
            let storedCaptions = [];

            function syncInputFiles() {
                // Try to set input.files using DataTransfer (modern browsers)
                try {
                    const dt = new DataTransfer();
                    storedFiles.forEach(f => dt.items.add(f));
                    input.files = dt.files;
                } catch (e) {
                    // Not critical; submit will still include the last selection in some older browsers.
                }
            }

            function renderPreviews() {
                previewList.innerHTML = '';
                if (!storedFiles.length) { previewBox.style.display='none'; return; }

                storedFiles.forEach((file, idx) => {
                    if (!file || !file.type || !file.type.startsWith('image/')) return;

                    const reader = new FileReader();

                    const thumb = document.createElement('img');
                    thumb.style.width = '160px';
                    thumb.style.height = '160px';
                    thumb.style.borderRadius = '6px';
                    thumb.style.objectFit = 'cover';
                    thumb.alt = 'Preview';

                    const caption = document.createElement('textarea');
                    caption.name = 'photo_caption[]';
                    caption.placeholder = 'Caption for this photo (optional)';
                    caption.style.width = '160px';
                    caption.style.height = '60px';
                    caption.style.marginTop = '6px';
                    caption.style.resize = 'vertical';
                    caption.value = storedCaptions[idx] || '';
                    caption.addEventListener('input', function(){ storedCaptions[idx] = caption.value; });

                    const removeBtn = document.createElement('button');
                    removeBtn.type = 'button';
                    removeBtn.textContent = 'Remove';
                    removeBtn.style.marginTop = '6px';
                    removeBtn.addEventListener('click', function(){
                        storedFiles.splice(idx, 1);
                        storedCaptions.splice(idx, 1);
                        syncInputFiles();
                        renderPreviews();
                    });

                    reader.onload = function(e){ thumb.src = e.target.result; };
                    reader.readAsDataURL(file);

                    const wrap = document.createElement('div');
                    wrap.style.display = 'flex';
                    wrap.style.flexDirection = 'column';
                    wrap.style.alignItems = 'center';
                    wrap.style.width = '160px';
                    wrap.style.marginBottom = '8px';
                    wrap.appendChild(thumb);
                    wrap.appendChild(caption);
                    wrap.appendChild(removeBtn);
                    previewList.appendChild(wrap);
                });

                previewBox.style.display = previewList.children.length ? 'block' : 'none';
            }

            input.addEventListener('change', function(){
                const newFiles = Array.from(input.files || []);
                // Append non-duplicate files
                newFiles.forEach(f => {
                    const exists = storedFiles.some(sf => sf.name === f.name && sf.size === f.size && sf.lastModified === f.lastModified);
                    if (!exists) {
                        storedFiles.push(f);
                        storedCaptions.push('');
                    }
                });

                syncInputFiles();
                renderPreviews();
            });
        })();
        </script>
    </div>
</body>
</html>
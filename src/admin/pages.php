<?php
session_start();

// Require admin login
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: login.php');
    exit();
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../service/navbar.php';
require_once __DIR__ . '/../service/settings.php';

function sanitize_html($html) {
    // Fallback sanitizer: conservative allowed tags and attribute cleaning.
    $allowed = '<p><a><br><strong><b><em><i><u><ul><ol><li><img><video><source><h1><h2><h3><h4><h5><h6><blockquote><pre><code>';
    $clean = strip_tags($html, $allowed);
    // Remove event handler attributes like onclick, onmouseover, etc.
    $clean = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $clean);
    // Neutralize javascript: in href/src attributes and ensure attributes are quoted safely
    $clean = preg_replace_callback("/(href|src)\s*=\s*(\"([^\"]*)\"|'([^']*)'|([^\s>]+))/i", function($m){
        $attr = $m[1];
        $val = isset($m[3]) && $m[3] !== '' ? $m[3] : (isset($m[4]) && $m[4] !== '' ? $m[4] : $m[5]);
        if (preg_match('/^\s*javascript\:/i', $val)) {
            return $attr . '="#"';
        }
        return $attr . '="' . htmlspecialchars($val, ENT_QUOTES) . '"';
    }, $clean);
    return $clean;
}

// Available page keys and labels
$pages = [
    'page_footer' => 'Footer',
    'page_about' => 'About',
    'page_memorial_details' => 'Memorial Details'
];

$selected = $_GET['page'] ?? array_key_first($pages);
if (!isset($pages[$selected])) $selected = array_key_first($pages);

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sel = $_POST['page_key'] ?? $selected;
    $content = $_POST['page_html'] ?? '';
    if (!isset($pages[$sel])) {
        $error = 'Invalid page selected.';
    } else {
        // Sanitize HTML before saving
        $sanitized = sanitize_html($content);
        if (set_setting($sel, $sanitized)) {
            $message = $pages[$sel] . ' saved.';
            // note if sanitization changed the content (only for admin awareness)
            if ($sanitized !== $content) {
                $message .= ' (content sanitized)';
            }
            $selected = $sel;
        } else {
            $error = 'Failed to save.';
        }
    }
}

$current = get_setting($selected, '');
// Migrate legacy footer_html into page_footer if present and page_footer is empty
if ($selected === 'page_footer') {
    $existing = get_setting('page_footer', '');
    if (empty($existing)) {
        $legacy = get_setting('footer_html', null);
        if ($legacy !== null && $legacy !== '') {
            // copy into new key
            set_setting('page_footer', $legacy);
            // clear legacy key
            set_setting('footer_html', '');
            $current = $legacy;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Edit Pages</title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars(asset_url('styles/style.css')); ?>">
    <!-- Quill WYSIWYG -->
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <!-- Google Fonts for editor font choices -->
    <link href="https://fonts.googleapis.com/css2?family=Merriweather:wght@400;700&family=Roboto:wght@400;700&family=Source+Code+Pro:wght@400;700&display=swap" rel="stylesheet">
    <?php $favicon = get_setting('favicon',''); if (!empty($favicon)) echo '<link rel="icon" href="' . htmlspecialchars(asset_url($favicon)) . '">'; ?>
    <style>
        .editor-toolbar button { margin-right:6px; }
        .editor-area { border:1px solid #ccc; padding:10px; min-height:120px; border-radius:4px; background:#fff; }
        /* Quill editor height */
        .ql-container { min-height: 180px; }
        /* Simple font mappings for Quill font picker (mapped to Google Fonts) */
        .ql-snow .ql-picker.ql-font .ql-picker-label:before,
        .ql-snow .ql-picker.ql-font .ql-picker-item[data-value="serif"]:before { font-family: 'Merriweather', serif; }
        .ql-snow .ql-picker.ql-font .ql-picker-item[data-value="sans"]:before { font-family: 'Roboto', Arial, Helvetica, sans-serif; }
        .ql-snow .ql-picker.ql-font .ql-picker-item[data-value="monospace"]:before { font-family: 'Source Code Pro', Menlo, Monaco, monospace; }
        .ql-font-serif { font-family: 'Merriweather', serif; }
        .ql-font-sans { font-family: 'Roboto', Arial, Helvetica, sans-serif; }
        .ql-font-monospace { font-family: 'Source Code Pro', Menlo, Monaco, monospace; }
        .editor-wrap { max-width:900px; }
        .preview { margin-top:16px; padding:12px; border-radius:6px; background:#f9f9f9; border:1px solid #eee; }
    </style>
</head>
<body>
    <h1>Pages</h1>

    <?php if ($message): ?><div class="success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <form method="post" action="pages.php" onsubmit="return prepareAndSubmit();">
        <label for="page_key">Select page:</label>
        <select id="page_key" name="page_key" onchange="onPageChange(this.value);">
            <?php foreach ($pages as $k=>$lbl): ?>
                <option value="<?php echo htmlspecialchars($k); ?>" <?php if ($k === $selected) echo 'selected'; ?>><?php echo htmlspecialchars($lbl); ?></option>
            <?php endforeach; ?>
        </select>

        <div style="margin-top:12px;" class="editor-wrap">
            <!-- Quill toolbar -->
            <div id="quill-toolbar">
                <span class="ql-formats">
                    <select class="ql-font">
                        <option selected></option>
                        <option value="serif">Serif</option>
                        <option value="sans">Sans</option>
                        <option value="monospace">Monospace</option>
                    </select>
                    <select class="ql-size">
                        <option value="small"></option>
                        <option selected></option>
                        <option value="large"></option>
                        <option value="huge"></option>
                    </select>
                    <button class="ql-bold"></button>
                    <button class="ql-italic"></button>
                    <button class="ql-list" value="bullet"></button>
                    <button class="ql-link"></button>
                    <button class="ql-image"></button>
                    <select class="ql-color"></select>
                </span>
                <span class="ql-formats">
                    <button class="ql-align" value=""></button>
                    <button class="ql-align" value="center"></button>
                    <button class="ql-align" value="right"></button>
                    <button class="ql-align" value="justify"></button>
                </span>
            </div>

            <div id="editor" class="editor-area"><?php echo $current; ?></div>
            <textarea id="page_html" name="page_html" style="display:none;"></textarea>

            <div style="margin-top:12px;">
                <button type="submit">Save Page</button>
                <a href="index.php" style="margin-left:12px;">Back to Admin</a>
            </div>
        </div>
    </form>

    <h3>Preview</h3>
    <div id="preview" class="preview"><?php echo $current; ?></div>

<!-- Quill JS -->
<script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
<script>
// Initialize Quill editor with image upload handler
var editorEl = document.getElementById('editor');
var initialHtml = <?php echo json_encode($current); ?>;
var quill = new Quill('#editor', {
    modules: {
        toolbar: { container: '#quill-toolbar', handlers: { 'image': imageHandler } }
    },
    theme: 'snow'
});

// Register a small font whitelist so Quill will apply correct classes
try {
    var Font = Quill.import('formats/font');
    Font.whitelist = ['serif', 'sans', 'monospace'];
    Quill.register(Font, true);
} catch (e) {
    // ignore if import fails in older Quill
}

// Load initial HTML into Quill
if (initialHtml) {
    quill.clipboard.dangerouslyPasteHTML(initialHtml);
    document.getElementById('preview').innerHTML = initialHtml;
}

// Image handler: open file picker, upload to server, insert image
function imageHandler() {
    var input = document.createElement('input');
    input.setAttribute('type', 'file');
    input.setAttribute('accept', 'image/*');
    input.click();
    input.onchange = function() {
        var file = input.files[0];
        if (!file) return;
        var fd = new FormData();
        // Editor image upload: send to upload_image.php using field name 'image'
        fd.append('image', file);

        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'upload_image.php', true);
        xhr.responseType = 'json';
        xhr.onload = function() {
            if (xhr.status === 200 && xhr.response && xhr.response.ok) {
                var url = '/' + xhr.response.path.replace(/^\/+/, '');
                var range = quill.getSelection(true);
                quill.insertEmbed(range.index, 'image', url);
                quill.setSelection(range.index + 1);
                // update preview
                document.getElementById('preview').innerHTML = quill.root.innerHTML;
            } else {
                alert('Image upload failed: ' + (xhr.response && xhr.response.message ? xhr.response.message : 'Server error'));
            }
        };
        xhr.onerror = function() { alert('Image upload failed due to network error.'); };
        xhr.send(fd);
    };
}

// Update preview whenever editor contents change
quill.on('text-change', function() { document.getElementById('preview').innerHTML = quill.root.innerHTML; });

function prepareAndSubmit(){
    var hidden = document.getElementById('page_html');
    hidden.value = quill.root.innerHTML;
    return true;
}

function onPageChange(key){
    // simple client redirect to reload the selected page
    window.location = 'pages.php?page=' + encodeURIComponent(key);
}
</script>
</body>
</html>

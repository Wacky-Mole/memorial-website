<?php
session_start();

// Require admin login
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: login.php');
    exit();
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../service/settings.php';

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
        if (set_setting($sel, $content)) {
            $message = $pages[$sel] . ' saved.';
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
    <link rel="stylesheet" href="../styles/style.css">
    <?php $favicon = get_setting('favicon',''); if (!empty($favicon)) echo '<link rel="icon" href="' . htmlspecialchars($favicon) . '">'; ?>
    <style>
        .editor-toolbar button { margin-right:6px; }
        .editor-area { border:1px solid #ccc; padding:10px; min-height:120px; border-radius:4px; background:#fff; }
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
            <div class="editor-toolbar">
                <button type="button" data-cmd="bold">Bold</button>
                <button type="button" data-cmd="italic">Italic</button>
                <button type="button" data-cmd="insertUnorderedList">Bulleted List</button>
                <button type="button" data-cmd="createLink">Insert Link</button>
                <button type="button" id="view-html">View HTML</button>
            </div>

            <div id="editor" class="editor-area" contenteditable="true"><?php echo $current; ?></div>
            <textarea id="page_html" name="page_html" style="display:none;"></textarea>

            <div style="margin-top:12px;">
                <button type="submit">Save Page</button>
                <a href="index.php" style="margin-left:12px;">Back to Admin</a>
            </div>
        </div>
    </form>

    <h3>Preview</h3>
    <div id="preview" class="preview"><?php echo $current; ?></div>

<script>
(function(){
    var toolbar = document.querySelector('.editor-toolbar');
    toolbar.addEventListener('click', function(e){
        var cmd = e.target.getAttribute('data-cmd');
        if (!cmd) return;
        if (cmd === 'createLink') {
            var url = prompt('Enter URL (include http:// or https://):');
            if (url) document.execCommand('createLink', false, url);
        } else {
            document.execCommand(cmd, false, null);
        }
        updatePreview();
    });

    var editor = document.getElementById('editor');
    var preview = document.getElementById('preview');
    var htmlView = false;
    document.getElementById('view-html').addEventListener('click', function(){
        if (!htmlView) {
            editor.textContent = editor.innerHTML;
            this.textContent = 'View WYSIWYG';
            htmlView = true;
        } else {
            editor.innerHTML = editor.textContent;
            this.textContent = 'View HTML';
            htmlView = false;
        }
    });

    editor.addEventListener('input', updatePreview);
    function updatePreview(){ if (htmlView) return; preview.innerHTML = editor.innerHTML; }

})();

function prepareAndSubmit(){
    var editor = document.getElementById('editor');
    var hidden = document.getElementById('page_html');
    hidden.value = editor.innerHTML;
    return true;
}

function onPageChange(key){
    // simple client redirect to reload the selected page
    window.location = 'pages.php?page=' + encodeURIComponent(key);
}
</script>
</body>
</html>

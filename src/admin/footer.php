<?php
session_start();

// Require admin login
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: login.php');
    exit();
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../service/settings.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Save submitted footer HTML
    $footer = $_POST['footer_html'] ?? '';
    // Simple trim; admin controls content. If you want sanitization, add it here.
    if (set_setting('footer_html', $footer)) {
        $message = 'Footer saved.';
    } else {
        $error = 'Failed to save footer.';
    }
}

$current = get_setting('footer_html', '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Edit Footer</title>
    <link rel="stylesheet" href="../styles/style.css">
    <?php
        $favicon = get_setting('favicon', '');
        if (!empty($favicon)) echo '<link rel="icon" href="' . htmlspecialchars($favicon) . '">';
    ?>
    <style>
        .editor-toolbar button { margin-right:6px; }
        .editor-area { border:1px solid #ccc; padding:10px; min-height:80px; border-radius:4px; background:#fff; }
        .editor-wrap { max-width:900px; }
        .site-footer { margin-top:24px; padding:16px; background:#f8f8f8; color:#333; border-top:1px solid #e6e6e6; }
    </style>
</head>
<body>
    <h1>Edit Footer</h1>

    <?php if ($message): ?>
        <div class="success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <p>Use the editor below to edit the HTML content that appears in the site's footer. Basic formatting tools are available.</p>

    <div class="editor-wrap">
        <div class="editor-toolbar">
            <button type="button" data-cmd="bold">Bold</button>
            <button type="button" data-cmd="italic">Italic</button>
            <button type="button" data-cmd="insertUnorderedList">Bulleted List</button>
            <button type="button" data-cmd="createLink">Insert Link</button>
            <button type="button" id="view-html">View HTML</button>
        </div>

        <form method="post" action="footer.php" onsubmit="return prepareAndSubmit();">
            <div id="editor" class="editor-area" contenteditable="true"><?php echo $current; ?></div>
            <textarea id="footer_html" name="footer_html" style="display:none;"></textarea>

            <div style="margin-top:12px;">
                <button type="submit">Save Footer</button>
                <a href="index.php" style="margin-left:12px;">Back to Admin</a>
            </div>
        </form>

        <h3>Preview</h3>
        <div id="preview" class="site-footer"><?php echo $current; ?></div>
    </div>

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
            // show raw HTML in editor
            editor.textContent = editor.innerHTML;
            this.textContent = 'View WYSIWYG';
            htmlView = true;
        } else {
            // restore HTML rendering
            editor.innerHTML = editor.textContent;
            this.textContent = 'View HTML';
            htmlView = false;
        }
    });

    editor.addEventListener('input', updatePreview);
    function updatePreview(){
        // if in raw-html mode, don't update
        if (htmlView) return;
        preview.innerHTML = editor.innerHTML;
    }

})();

function prepareAndSubmit(){
    var editor = document.getElementById('editor');
    var hidden = document.getElementById('footer_html');
    // If the editor is showing raw HTML via textContent, prefer that value; otherwise use innerHTML
    hidden.value = editor.innerHTML;
    return true;
}
</script>
</body>
</html>

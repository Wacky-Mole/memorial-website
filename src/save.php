<?php
// save.php

// Include configuration and storage files
require_once 'config.php';
require_once 'service/storage.php';
require_once __DIR__ . '/service/email_utils.php';
require_once __DIR__ . '/service/settings.php';

// Check if the form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate input (email is optional)
    $rawEmail = trim($_POST['email'] ?? '');
    $email = '';
    if ($rawEmail !== '') {
        $san = filter_var($rawEmail, FILTER_SANITIZE_EMAIL);
        if (!filter_var($san, FILTER_VALIDATE_EMAIL)) {
            die('Invalid email.');
        }
        $email = $san;
    }

    $contributorName = trim($_POST['name'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (empty($contributorName)) {
        die('Please provide your name.');
    }

    // (message validation moved below after processing uploaded photos)

    // Optional photo upload (contributors may add one)
    $photoPath = '';
    $photoPaths = [];
    if (isset($_FILES['photo'])) {
        require_once __DIR__ . '/service/image_utils.php';
        // Support multiple uploaded files (photo[])
        if (is_array($_FILES['photo']['name'])) {
            $count = count($_FILES['photo']['name']);
            for ($i = 0; $i < $count; $i++) {
                if (!isset($_FILES['photo']['error'][$i]) || $_FILES['photo']['error'][$i] !== UPLOAD_ERR_OK) continue;
                $file = [
                    'name' => $_FILES['photo']['name'][$i],
                    'type' => $_FILES['photo']['type'][$i],
                    'tmp_name' => $_FILES['photo']['tmp_name'][$i],
                    'error' => $_FILES['photo']['error'][$i],
                    'size' => $_FILES['photo']['size'][$i]
                ];
                list($ok, $result) = safeProcessUpload($file, 'contributor', 1200, 1200);
                if ($ok) {
                    $photoPaths[] = $result;
                } else {
                    // For public submissions, fail gracefully and inform user
                    die('Photo upload failed: ' . htmlspecialchars($result));
                }
            }
        } else {
            // Single file
            if ($_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                list($ok, $result) = safeProcessUpload($_FILES['photo'], 'contributor', 1200, 1200);
                if ($ok) $photoPaths[] = $result;
                else die('Photo upload failed: ' . htmlspecialchars($result));
            }
        }
    }

    if (!empty($photoPaths)) {
        // Pair uploaded paths with optional captions submitted from the form
        $captions = [];
        if (!empty($_POST['photo_caption']) && is_array($_POST['photo_caption'])) {
            // sanitize captions
            foreach ($_POST['photo_caption'] as $c) {
                $captions[] = trim(filter_var($c, FILTER_SANITIZE_FULL_SPECIAL_CHARS));
            }
        }

        $entries = [];
        for ($i = 0; $i < count($photoPaths); $i++) {
            $p = $photoPaths[$i];
            $cap = $captions[$i] ?? '';
            $entries[] = ['path' => $p, 'caption' => $cap];
        }

        // Store as JSON array of objects: [{path:..., caption:...}, ...]
        $photoPath = json_encode($entries);
    }

    // Require a message if no photos were successfully uploaded
    if (empty($message) && empty($photoPaths)) {
        die('Please provide a memory or message, or upload a photo.');
    }

    // Save entry via storage helper (returns true/false)
    $ok = saveMemorialEntry($email, $contributorName, $message, $photoPath);
    if ($ok) {
        // Send admin notification if configured
        if (defined('NOTIFY_ON_SUBMISSION') && NOTIFY_ON_SUBMISSION) {
            $entry = [
                'contributor' => $contributorName,
                'email' => $email,
                'message' => $message,
                'photo' => $photoPath,
            ];
            $sent = send_notification_email($entry);
            if (!$sent) {
                error_log('Failed to send notification email for new submission.');
            }
        }

        // Show a friendly HTML confirmation and redirect back to the homepage after 5 seconds
        $safeName = htmlspecialchars($contributorName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeMsg = nl2br(htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        $safeHome = 'index.php';
        // Compute a base path relative to this script so redirect works in subdirectories
        $basePath = htmlspecialchars(dirname($_SERVER['SCRIPT_NAME']));
        if ($basePath === '/' || $basePath === '.') $basePath = '';
        $homeUrl = $basePath . '/' . $safeHome;
        // Normalize slashes
        $homeUrl = preg_replace('#/+#','/',$homeUrl);

        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html><head><meta charset="utf-8"><title>Thank you</title>';
        // Insert favicon link if configured
        $fav = get_setting('favicon', '');
        if (!empty($fav)) echo '<link rel="icon" href="' . htmlspecialchars(asset_url($fav)) . '">';
        echo '<meta http-equiv="refresh" content="5;url=' . htmlspecialchars($homeUrl) . '">';
        echo '<style>body{font-family:Arial,Helvetica,sans-serif;padding:24px;} .success{background:#e6ffed;border:1px solid #b7f0c9;padding:16px;border-radius:6px;} .note{margin-top:12px;color:#666}</style>';
        echo '</head><body>';
        echo '<div class="success"><h2>Thank you, ' . $safeName . '.</h2><p>Your memorial entry has been saved.</p></div>';
        echo '<div class="note">You will be redirected to the homepage in 5 seconds. If you are not redirected, <a href="' . htmlspecialchars($homeUrl) . '">click here</a>.</div>';
        echo '<script>setTimeout(function(){ window.location.href = ' . json_encode($homeUrl) . '; }, 5000);</script>';
        echo '</body></html>';
    } else {
        echo 'There was an error saving your entry.';
    }
} else {
    die('Invalid request method.');
}
?>
<?php
// save.php

// Include configuration and storage files
require_once 'config.php';
require_once 'service/storage.php';
require_once __DIR__ . '/service/email_utils.php';

// Check if the form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate input
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $contributorName = trim($_POST['name'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        die('Invalid email.');
    }

    if (empty($contributorName)) {
        die('Please provide your name.');
    }

    if (empty($message)) {
        die('Please provide a memory or message.');
    }

    // Optional photo upload (contributors may add one)
    $photoPath = '';
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        require_once __DIR__ . '/service/image_utils.php';
        list($ok, $result) = safeProcessUpload($_FILES['photo'], 'contributor', 1200, 1200);
        if ($ok) {
            $photoPath = $result;
        } else {
            // For public submissions, fail gracefully and inform user
            die('Photo upload failed: ' . htmlspecialchars($result));
        }
    }

    // Save entry via storage helper (returns true/false)
    $ok = saveMemorialEntry($email, $contributorName, $message, $photoPath);
    if ($ok) {
        echo 'Memorial entry saved successfully.';

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
    } else {
        echo 'There was an error saving your entry.';
    }
} else {
    die('Invalid request method.');
}
?>
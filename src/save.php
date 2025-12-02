<?php
// save.php

// Include configuration and storage files
require_once 'config.php';
require_once 'service/storage.php';

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
        $uploadDir = rtrim(UPLOAD_DIR, '/') . '/memorial_photos/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $safeName = basename($_FILES['photo']['name']);
        $uploadFile = $uploadDir . $safeName;
        if (move_uploaded_file($_FILES['photo']['tmp_name'], $uploadFile)) {
            $photoPath = $uploadFile;
        }
    }

    // Save entry via storage helper (returns true/false)
    $ok = saveMemorialEntry($email, $contributorName, $message, $photoPath);
    if ($ok) {
        echo 'Memorial entry saved successfully.';
    } else {
        echo 'There was an error saving your entry.';
    }
} else {
    die('Invalid request method.');
}
?>
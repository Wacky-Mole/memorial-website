<?php
// save.php

// Include configuration and storage files
require_once 'config.php';
require_once 'service/storage.php';

// Check if the form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate input
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $deceasedName = trim($_POST['deceased_name']);
    $photo = $_FILES['photo'];

    // Check for valid email and deceased name
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL) || empty($deceasedName)) {
        die('Invalid input. Please provide a valid email and the name of the deceased.');
    }

    // Handle photo upload
    $uploadDir = 'images/memorial_photos/';
    $uploadFile = $uploadDir . basename($photo['name']);
    
    // Check if the upload directory exists, if not create it
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Move the uploaded file to the designated directory
    if (move_uploaded_file($photo['tmp_name'], $uploadFile)) {
        // Save the entry to storage
        saveMemorialEntry($email, $deceasedName, $uploadFile);
        echo 'Memorial entry saved successfully.';
    } else {
        die('Error uploading photo. Please try again.');
    }
} else {
    die('Invalid request method.');
}

// Function to save memorial entry
function saveMemorialEntry($email, $deceasedName, $photoPath) {
    // Here you would typically save the entry to a database or file
    // For demonstration, we will just log the data
    $entry = [
        'email' => $email,
        'deceased_name' => $deceasedName,
        'photo_path' => $photoPath,
        'created_at' => date('Y-m-d H:i:s')
    ];

    // Log the entry (this could be replaced with a database insert)
    file_put_contents('memorial_entries.log', json_encode($entry) . PHP_EOL, FILE_APPEND);
}
?>
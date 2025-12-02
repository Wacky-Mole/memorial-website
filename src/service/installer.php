<?php
session_start();

function checkInstallation() {
    // Check if the configuration file exists
    return file_exists(__DIR__ . '/../config.php');
}

function promptInstallation() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        $deceasedName = htmlspecialchars(trim($_POST['deceased_name']));
        $photo = $_FILES['photo'];

        // Validate inputs
        if (empty($email) || empty($deceasedName) || $photo['error'] !== UPLOAD_ERR_OK) {
            return "Please fill in all fields and upload a valid photo.";
        }

        // Process the uploaded photo
        $uploadDir = __DIR__ . '/../images/';
        $uploadFile = $uploadDir . basename($photo['name']);

        if (move_uploaded_file($photo['tmp_name'], $uploadFile)) {
            // Save the configuration or data as needed
            // This is where you would typically save to a database or configuration file
            return "Installation successful! Memorial entry created for $deceasedName.";
        } else {
            return "Failed to upload photo.";
        }
    }

    return '';
}

if (!checkInstallation()) {
    $message = promptInstallation();
    include __DIR__ . '/../form.php'; // Include the form for installation
} else {
    header('Location: ../index.php'); // Redirect to the main page if already installed
    exit;
}
?>
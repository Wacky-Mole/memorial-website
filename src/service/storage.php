<?php
session_start();

function saveMemorialEntry($email, $deceasedName, $photo) {
    // Define the path to store the uploaded photo
    $targetDir = "../images/memorial_photos/";
    $targetFile = $targetDir . basename($photo["name"]);
    $uploadOk = 1;
    $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));

    // Check if the image file is a actual image or fake image
    $check = getimagesize($photo["tmp_name"]);
    if ($check === false) {
        echo "File is not an image.";
        $uploadOk = 0;
    }

    // Check file size (limit to 2MB)
    if ($photo["size"] > 2000000) {
        echo "Sorry, your file is too large.";
        $uploadOk = 0;
    }

    // Allow certain file formats
    if (!in_array($imageFileType, ['jpg', 'png', 'jpeg', 'gif'])) {
        echo "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
        $uploadOk = 0;
    }

    // Check if $uploadOk is set to 0 by an error
    if ($uploadOk == 0) {
        echo "Sorry, your file was not uploaded.";
    } else {
        // If everything is ok, try to upload file
        if (move_uploaded_file($photo["tmp_name"], $targetFile)) {
            // Here you would typically save the entry to a database
            // For demonstration, we'll just return the data
            return [
                'email' => $email,
                'deceasedName' => $deceasedName,
                'photoPath' => $targetFile
            ];
        } else {
            echo "Sorry, there was an error uploading your file.";
        }
    }
    return null;
}

function getMemorialEntries() {
    // This function would typically retrieve entries from a database
    // For demonstration, we'll return an empty array
    return [];
}
?>
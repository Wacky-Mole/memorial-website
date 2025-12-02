<?php
session_start();

// Clear the session data
$_SESSION = [];

// Destroy the session
session_destroy();

// Redirect to the login page
header("Location: index.php");
exit();
?>
<?php
session_start();

// Clear the session data
$_SESSION = [];

// Destroy the session
session_destroy();
// Redirect to the home page (handle apps served from subdirectories)
$scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
if ($scriptDir === '/' || $scriptDir === '.') $scriptDir = '';
// If current dir ends with /admin, app root is parent
$appRoot = $scriptDir;
if (substr($appRoot, -6) === '/admin') {
	$appRoot = rtrim(dirname($appRoot), '/\\');
}
// Build absolute target
if ($appRoot === '') {
	$target = '/index.php';
} else {
	$target = $appRoot . '/index.php';
}
header('Location: ' . $target);
exit();
?>
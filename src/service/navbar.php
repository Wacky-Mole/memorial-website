<?php
function renderNavbar($isAdmin = false) {
    // Build links relative to the current script to avoid 404s when app is served from a subdirectory
    $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    if ($scriptDir === '/' || $scriptDir === '.') $scriptDir = '';

    // If the current script dir ends with '/admin', the application root is the parent directory.
    $appRoot = $scriptDir;
    if (substr($appRoot, -6) === '/admin') {
        $appRoot = rtrim(dirname($appRoot), '/\\');
    }

    // Normalize to empty or '/prefix'
    if ($appRoot === '' ) {
        $rootPrefix = '';
    } else {
        $rootPrefix = $appRoot; // begins with '/'
    }

    // Build absolute links based on the application root to avoid relative duplication
    $homeHref = ($rootPrefix === '' ? '/index.php' : $rootPrefix . '/index.php');
    $adminHrefBase = ($rootPrefix === '' ? '/admin' : $rootPrefix . '/admin');

    echo '<nav>';
    echo '<ul>';
    echo '<li><a href="' . $homeHref . '">Home</a></li>';

    if ($isAdmin) {
        echo '<li><a href="' . $adminHrefBase . '/index.php">Admin Dashboard</a></li>';
        echo '<li><a href="' . $adminHrefBase . '/settings.php">Settings</a></li>';
        echo '<li><a href="' . $adminHrefBase . '/change_password.php">Change Password</a></li>';
        echo '<li><a href="' . $adminHrefBase . '/logout.php">Logout</a></li>';
    } else {
        echo '<li><a href="' . ($rootPrefix === '' ? '/form.php' : $rootPrefix . '/form.php') . '">Submit Memorial</a></li>';
    }

    echo '</ul>';
    echo '</nav>';
}
?>
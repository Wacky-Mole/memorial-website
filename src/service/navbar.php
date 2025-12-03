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

    echo '<nav class="navbar">';
    echo '<div class="nav-inner container">';
    // brand
    echo '<div class="nav-brand"><a class="nav-link" href="' . $homeHref . '">' . htmlspecialchars(defined('SITE_TITLE') ? SITE_TITLE : SITE_NAME) . '</a></div>';
    echo '<ul class="nav-list">';
    if ($isAdmin) {
        echo '<li class="nav-item"><a class="nav-link" href="' . $adminHrefBase . '/index.php">Dashboard</a></li>';
        echo '<li class="nav-item"><a class="nav-link" href="' . $adminHrefBase . '/settings.php">Settings</a></li>';
        echo '<li class="nav-item"><a class="nav-link" href="' . $adminHrefBase . '/pages.php">Pages</a></li>';
        echo '<li class="nav-item"><a class="nav-link" href="' . $adminHrefBase . '/change_password.php">Change Password</a></li>';
        echo '<li class="nav-item"><a class="nav-link" href="' . $adminHrefBase . '/logout.php">Logout</a></li>';
    } else {
        echo '<li class="nav-item"><a class="nav-link" href="' . ($rootPrefix === '' ? '/form.php' : $rootPrefix . '/form.php') . '">Submit Memorial</a></li>';
        echo '<li class="nav-item"><a class="nav-link" href="' . ($rootPrefix === '' ? '/about.php' : $rootPrefix . '/about.php') . '">About</a></li>';
    }
    echo '</ul>';
    echo '</div>'; // nav-inner
    echo '</nav>';
}
?>
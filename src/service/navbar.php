<?php
// Return the application root prefix ('' or '/prefix') for building root-aware URLs
function getRootPrefix(): string {
    $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    if ($scriptDir === '/' || $scriptDir === '.') $scriptDir = '';
    $appRoot = $scriptDir;
    if (substr($appRoot, -6) === '/admin') {
        $appRoot = rtrim(dirname($appRoot), '/\\');
    }
    return ($appRoot === '' ? '' : $appRoot);
}

// Build an asset URL (root-aware) and append version query string for cache-busting
function asset_url(string $path): string {
    $rootPrefix = getRootPrefix();
    $clean = ltrim($path, '/');
    $url = ($rootPrefix === '' ? '/' . $clean : $rootPrefix . '/' . $clean);
    if (defined('ASSET_VERSION')) {
        $url .= '?v=' . ASSET_VERSION;
    }
    return $url;
}

function renderNavbar($isAdmin = false) {
    // Load settings helper so we can show public page links only when content exists
    $settingsFile = __DIR__ . '/settings.php';
    if (file_exists($settingsFile)) {
        require_once $settingsFile;
    }
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
    // brand: prefer DB-backed memorial name (with title prefix) and fall back to SITE_TITLE or SITE_NAME
    $brandTitle = '';
    if (function_exists('get_setting')) {
        $memName = get_setting('memorial_name', (defined('DEFAULT_MEMORIAL_NAME') ? DEFAULT_MEMORIAL_NAME : 'Memorial'));
        $prefix = get_setting('title_prefix', 'In Memory of');
        $pos = get_setting('title_prefix_position', 'before');
        if ($pos === 'after') {
            $brandTitle = trim($memName . ' ' . $prefix);
        } else {
            $brandTitle = trim($prefix . ' ' . $memName);
        }
    } else {
        $brandTitle = (defined('SITE_TITLE') ? SITE_TITLE : SITE_NAME);
    }
    echo '<div class="nav-brand"><a class="nav-link" href="' . $homeHref . '">' . htmlspecialchars($brandTitle) . '</a></div>';
    echo '<ul class="nav-list">';
    if ($isAdmin) {
        echo '<li class="nav-item"><a class="nav-link" href="' . $adminHrefBase . '/index.php">Dashboard</a></li>';
        echo '<li class="nav-item"><a class="nav-link" href="' . $adminHrefBase . '/settings.php">Settings</a></li>';
        // Admins: also show public pages for quick viewing
        echo '<li class="nav-item"><a class="nav-link" href="' . ($rootPrefix === '' ? '/about.php' : $rootPrefix . '/about.php') . '" target="_blank">About (view)</a></li>';
        echo '<li class="nav-item"><a class="nav-link" href="' . ($rootPrefix === '' ? '/memorial_details.php' : $rootPrefix . '/memorial_details.php') . '" target="_blank">Memorial Details (view)</a></li>';
        echo '<li class="nav-item"><a class="nav-link" href="' . $adminHrefBase . '/pages.php">Pages</a></li>';
        echo '<li class="nav-item"><a class="nav-link" href="' . $adminHrefBase . '/change_password.php">Change Password</a></li>';
        echo '<li class="nav-item"><a class="nav-link" href="' . $adminHrefBase . '/logout.php">Logout</a></li>';
    } else {
        echo '<li class="nav-item"><a class="nav-link" href="' . ($rootPrefix === '' ? '/form.php' : $rootPrefix . '/form.php') . '">Submit Memorial</a></li>';
        // Show About link only if content exists
        $aboutContent = function_exists('get_setting') ? get_setting('page_about', '') : '';
        if (!empty($aboutContent)) {
            echo '<li class="nav-item"><a class="nav-link" href="' . ($rootPrefix === '' ? '/about.php' : $rootPrefix . '/about.php') . '">About</a></li>';
        }
        // Show Memorial Details link only if content exists
        $mdContent = function_exists('get_setting') ? get_setting('page_memorial_details', '') : '';
        if (!empty($mdContent)) {
            echo '<li class="nav-item"><a class="nav-link" href="' . ($rootPrefix === '' ? '/memorial_details.php' : $rootPrefix . '/memorial_details.php') . '">Memorial Details</a></li>';
        }
    }
    echo '</ul>';
    echo '</div>'; // nav-inner
    echo '</nav>';
}
?>
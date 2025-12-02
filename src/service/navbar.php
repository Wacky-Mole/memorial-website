<?php
function renderNavbar($isAdmin = false) {
    echo '<nav>';
    echo '<ul>';
    echo '<li><a href="index.php">Home</a></li>';
    
    if ($isAdmin) {
        echo '<li><a href="admin/index.php">Admin Dashboard</a></li>';
        echo '<li><a href="admin/settings.php">Settings</a></li>';
        echo '<li><a href="admin/logout.php">Logout</a></li>';
    } else {
        echo '<li><a href="form.php">Submit Memorial</a></li>';
    }
    
    echo '</ul>';
    echo '</nav>';
}
?>
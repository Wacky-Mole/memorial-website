<?php
// Configuration settings for the memorial website

// Database connection settings
define('DB_HOST', 'localhost');
define('DB_NAME', 'memorial_website');
define('DB_USER', 'root');
define('DB_PASS', '');

// Site-specific settings
define('SITE_NAME', 'Memorial Website');
define('ADMIN_EMAIL', 'admin@example.com');

// Memorial-specific settings (can be updated via admin settings)
// Name of the person being remembered
if (!defined('MEMORIAL_NAME')) {
	define('MEMORIAL_NAME', '');
}

// Path to memorial photo (relative to site root)
if (!defined('MEMORIAL_PHOTO')) {
	define('MEMORIAL_PHOTO', '');
}

// Site title (falls back to SITE_NAME or "In Memory of [NAME]")
if (!defined('SITE_TITLE')) {
	if (!empty(MEMORIAL_NAME)) {
		define('SITE_TITLE', 'In Memory of ' . MEMORIAL_NAME);
	} else {
		define('SITE_TITLE', SITE_NAME);
	}
}

// Timezone
if (!defined('TIMEZONE')) {
	define('TIMEZONE', 'UTC');
}
date_default_timezone_set(TIMEZONE);

// File upload settings
define('UPLOAD_DIR', 'uploads/');
define('MAX_FILE_SIZE', 2097152); // 2MB in bytes
define('ALLOWED_FILE_TYPES', ['image/jpeg', 'image/png', 'image/gif']);
?>
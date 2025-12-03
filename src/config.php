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
// Data directory and SQLite DB path (used for entries storage)
if (!defined('DATA_DIR')) {
	define('DATA_DIR', __DIR__ . '/data/');
}

if (!is_dir(DATA_DIR)) {
	@mkdir(DATA_DIR, 0755, true);
}

if (!defined('DB_PATH')) {
	define('DB_PATH', DATA_DIR . 'memorial.db');
}
define('MAX_FILE_SIZE', 4194304); // 4MB in bytes
define('ALLOWED_FILE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp']);
?>
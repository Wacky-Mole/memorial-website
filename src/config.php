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
	define('MEMORIAL_NAME', 'Joe Shimer');
}

// Path to memorial photo (relative to site root)
if (!defined('MEMORIAL_PHOTO')) {
	// Default memorial photo path. Updated to the most recent uploaded image.
	define('MEMORIAL_PHOTO', 'uploads/memorial/2fd8c292fd56a8af.jpg');
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

// Backwards-compatible installer check used by index.php
if (!function_exists('isConfigured')) {
	function isConfigured(): bool {
		// If a DB path exists and the file is present, consider configured
		if (defined('DB_PATH') && !empty(DB_PATH) && file_exists(DB_PATH)) return true;
		// Older installer created the DB at src/memorial.db (different path); check that too
		$legacyDb = __DIR__ . '/memorial.db';
		if (file_exists($legacyDb)) return true;
			// If an admin password hash or a temporary plaintext password is defined, assume configured
			if (defined('ADMIN_PASSWORD_HASH') && constant('ADMIN_PASSWORD_HASH') !== '') return true;
			if (defined('ADMIN_PASSWORD_PLAIN') && constant('ADMIN_PASSWORD_PLAIN') !== '') return true;
		return false;
	}
}

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

// Notification settings (admin can enable/disable and set email)
if (!defined('NOTIFY_ON_SUBMISSION')) {
	define('NOTIFY_ON_SUBMISSION', false);
}

if (!defined('NOTIFY_EMAIL')) {
	define('NOTIFY_EMAIL', ADMIN_EMAIL);
}

// SMTP settings (basic)
if (!defined('SMTP_ENABLED')) {
	define('SMTP_ENABLED', false);
}

if (!defined('SMTP_HOST')) {
	define('SMTP_HOST', '');
}

if (!defined('SMTP_PORT')) {
	define('SMTP_PORT', 25);
}

if (!defined('SMTP_USERNAME')) {
	define('SMTP_USERNAME', '');
}

if (!defined('SMTP_PASSWORD')) {
	define('SMTP_PASSWORD', '');
}

// 'none' | 'tls' | 'ssl'
if (!defined('SMTP_SECURE')) {
	define('SMTP_SECURE', 'none');
}

// Temporary admin plaintext password (only used as a fallback during recovery).
// Set to 'admin' per your request. REMOVE this line after converting to a hashed password.
// Define an admin password hash. By default the installer sets a safe hash for 'admin'.
if (!defined('ADMIN_PASSWORD_HASH')) {
	// Generate a password hash for the initial admin password 'admin'.
	// This uses PHP's password_hash() at runtime; on first use you should change it via the admin UI.
	define('ADMIN_PASSWORD_HASH', password_hash('admin', PASSWORD_DEFAULT));
}

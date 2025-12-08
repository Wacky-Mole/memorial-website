<?php
session_start();

class Installer
{
    private const CONFIG_FILE = __DIR__ . '/../config.php';
    private const DB_PATH = __DIR__ . '/../memorial.db';
    private const UPLOAD_DIR = __DIR__ . '/../images/memorial/';

    public function isInstalled(): bool
    {
        return file_exists(self::CONFIG_FILE) && file_exists(self::DB_PATH);
    }

    public function install(array $data): bool|string
    {
        try {
            // Create upload directory
            if (!is_dir(self::UPLOAD_DIR)) {
                mkdir(self::UPLOAD_DIR, 0755, true);
            }

            // Handle photo upload
            $photoPath = null;
            if (!empty($data['memorial_photo']) && $data['memorial_photo']['error'] === UPLOAD_ERR_OK) {
                $photoPath = $this->handlePhotoUpload($data['memorial_photo']);
                if ($photoPath === false) {
                    return 'Failed to upload memorial photo';
                }
            }

            // Create configuration
            $config = $this->generateConfig($data, $photoPath);
            if (!file_put_contents(self::CONFIG_FILE, $config)) {
                return 'Failed to create configuration file';
            }

            // Initialize database
            if (!$this->initializeDatabase($data)) {
                return 'Failed to initialize database';
            }

            return true;
        } catch (Exception $e) {
            return 'Installation failed: ' . $e->getMessage();
        }
    }

    private function handlePhotoUpload(array $file): string|false
    {
        // Use shared image_utils for validation, random filename and resizing
        require_once __DIR__ . '/image_utils.php';
        list($ok, $result) = safeProcessUpload($file, 'memorial', 1200, 1200);
        if ($ok) {
            // result is path like uploads/memorial/xxxxx.ext
            return $result;
        }
        return false;
    }

    private function generateConfig(array $data, ?string $photoPath): string
    {
        $config = "<?php\n";
        $config .= "// Memorial Website Configuration\n";
        $config .= "// Generated on " . date('Y-m-d H:i:s') . "\n\n";
        
        // Do not write MEMORIAL_NAME into config.php; prefer DB-backed settings
        // The installer will still store memorial_name in the settings table during DB initialization.
        $config .= "define('SITE_TITLE', " . var_export($data['site_title'], true) . ");\n";
        $config .= "define('MEMORIAL_PHOTO', " . var_export($photoPath ?? '', true) . ");\n";
        $config .= "define('ADMIN_EMAIL', " . var_export($data['admin_email'], true) . ");\n";
        $config .= "define('TIMEZONE', " . var_export($data['timezone'], true) . ");\n";
        $config .= "define('ADMIN_PASSWORD_HASH', " . var_export(password_hash($data['admin_password'], PASSWORD_DEFAULT), true) . ");\n\n";
        
        // Database configuration (leave DB_PASS empty by default for SQLite/local setups)
        $config .= "// Database configuration\n";
        $config .= "define('DB_HOST', 'localhost');\n";
        $config .= "define('DB_NAME', 'memorial_website');\n";
        $config .= "define('DB_USER', 'root');\n";
        $config .= "define('DB_PASS', '');\n\n";
        $config .= "define('DB_PATH', __DIR__ . '/memorial.db');\n\n";
        
        $config .= "// Set timezone\n";
        $config .= "date_default_timezone_set(TIMEZONE);\n";
        
        return $config;
    }

    private function initializeDatabase(array $data): bool
    {
        try {
            $db = new PDO('sqlite:' . self::DB_PATH);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Create tables
            $db->exec("
                CREATE TABLE IF NOT EXISTS entries (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT NOT NULL,
                    email TEXT,
                    message TEXT NOT NULL,
                    created_at TEXT NOT NULL,
                    status TEXT NOT NULL DEFAULT 'NOT_APPROVED',
                    ip_address TEXT
                )
            ");

            $db->exec("
                CREATE TABLE IF NOT EXISTS login_attempts (
                    ip_address TEXT PRIMARY KEY,
                    attempts INTEGER DEFAULT 0,
                    last_attempt TEXT
                )
            ");

            $db->exec("
                CREATE TABLE IF NOT EXISTS settings (
                    key TEXT PRIMARY KEY,
                    value TEXT
                )
            ");

            // Create hearts table for tracking hearts per entry by IP
            $db->exec("CREATE TABLE IF NOT EXISTS hearts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                entry_id INTEGER NOT NULL,
                ip TEXT NOT NULL,
                created_at TEXT,
                UNIQUE(entry_id, ip)
            )");

            // Insert initial settings
            $stmt = $db->prepare("INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)");
            $stmt->execute(['memorial_name', $data['memorial_name']]);
            $stmt->execute(['site_title', $data['site_title']]);
            $stmt->execute(['admin_email', $data['admin_email']]);
            $stmt->execute(['timezone', $data['timezone']]);
            $stmt->execute(['installed_at', date('Y-m-d H:i:s')]);

            return true;
        } catch (PDOException $e) {
            error_log("Database initialization error: " . $e->getMessage());
            return false;
        }
    }
}

function checkInstallation() {
    // Check if the configuration file exists
    return file_exists(__DIR__ . '/../config.php');
}

function promptInstallation() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        $deceasedName = htmlspecialchars(trim($_POST['deceased_name']));
        $photo = $_FILES['photo'];

        // Validate inputs
        if (empty($email) || empty($deceasedName) || $photo['error'] !== UPLOAD_ERR_OK) {
            return "Please fill in all fields and upload a valid photo.";
        }

        // Process the uploaded photo using image_utils
        require_once __DIR__ . '/image_utils.php';
        list($ok, $result) = safeProcessUpload($photo, 'memorial', 1200, 1200);
        if ($ok) {
            return "Installation successful! Memorial entry created for $deceasedName.";
        } else {
            return "Failed to upload photo: " . htmlspecialchars($result);
        }
    }

    return '';
}

if (!checkInstallation()) {
    $message = promptInstallation();
    include __DIR__ . '/../form.php'; // Include the form for installation
} else {
    header('Location: ../index.php'); // Redirect to the main page if already installed
    exit;
}
?>
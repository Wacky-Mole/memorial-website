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
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $maxSize = 5 * 1024 * 1024; // 5MB

        if (!in_array($file['type'], $allowedTypes)) {
            return false;
        }

        if ($file['size'] > $maxSize) {
            return false;
        }

        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'memorial_photo.' . $extension;
        $destination = self::UPLOAD_DIR . $filename;

        if (move_uploaded_file($file['tmp_name'], $destination)) {
            return 'images/memorial/' . $filename;
        }

        return false;
    }

    private function generateConfig(array $data, ?string $photoPath): string
    {
        $config = "<?php\n";
        $config .= "// Memorial Website Configuration\n";
        $config .= "// Generated on " . date('Y-m-d H:i:s') . "\n\n";
        
        $config .= "define('MEMORIAL_NAME', " . var_export($data['memorial_name'], true) . ");\n";
        $config .= "define('SITE_TITLE', " . var_export($data['site_title'], true) . ");\n";
        $config .= "define('MEMORIAL_PHOTO', " . var_export($photoPath ?? '', true) . ");\n";
        $config .= "define('ADMIN_EMAIL', " . var_export($data['admin_email'], true) . ");\n";
        $config .= "define('TIMEZONE', " . var_export($data['timezone'], true) . ");\n";
        $config .= "define('ADMIN_PASSWORD_HASH', " . var_export(password_hash($data['admin_password'], PASSWORD_DEFAULT), true) . ");\n\n";
        
        $config .= "// Database configuration\n";
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

        // Process the uploaded photo
        $uploadDir = __DIR__ . '/../images/';
        $uploadFile = $uploadDir . basename($photo['name']);

        if (move_uploaded_file($photo['tmp_name'], $uploadFile)) {
            // Save the configuration or data as needed
            // This is where you would typically save to a database or configuration file
            return "Installation successful! Memorial entry created for $deceasedName.";
        } else {
            return "Failed to upload photo.";
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
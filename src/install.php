<?php
session_start();

// Include configuration and service files
require_once 'config.php';
require_once 'service/installer.php';
require_once __DIR__ . '/service/settings.php';

$installer = new Installer();

// Check if already installed
if ($installer->isInstalled()) {
    header('Location: index.php');
    exit;
}

$errors = [];
$success = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'admin_email' => trim($_POST['admin_email'] ?? ''),
        'admin_password' => $_POST['admin_password'] ?? '',
        'admin_password_confirm' => $_POST['admin_password_confirm'] ?? '',
        'memorial_name' => trim($_POST['memorial_name'] ?? ''),
        'memorial_photo' => $_FILES['memorial_photo'] ?? null,
        'site_title' => trim($_POST['site_title'] ?? ''),
        'timezone' => $_POST['timezone'] ?? 'UTC'
    ];

    // Validation
    if (empty($data['admin_email']) || !filter_var($data['admin_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email address is required';
    }
    if (empty($data['admin_password']) || strlen($data['admin_password']) < 6) {
        $errors[] = 'Password must be at least 6 characters';
    }
    if ($data['admin_password'] !== $data['admin_password_confirm']) {
        $errors[] = 'Passwords do not match';
    }
    if (empty($data['memorial_name'])) {
        $errors[] = 'Memorial name is required (the person being remembered)';
    }
    if (empty($data['site_title'])) {
        $data['site_title'] = 'In Memory of ' . $data['memorial_name'];
    }

    if (empty($errors)) {
        $result = $installer->install($data);
        if ($result === true) {
            $success = true;
        } else {
            $errors[] = $result;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Memorial Website Installation</title>
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/theme.css">
    <?php
        $favicon = get_setting('favicon', '');
        if (!empty($favicon)) echo '<link rel="icon" href="' . htmlspecialchars($favicon) . '">';
    ?>
    <style>
        .install-container {
            max-width: 600px;
            margin: 50px auto;
            padding: 30px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .install-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="password"],
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .form-group input[type="file"] {
            width: 100%;
        }
        .form-help {
            font-size: 0.9em;
            color: #666;
            margin-top: 5px;
        }
        .error-messages {
            background: #fee;
            border: 1px solid #fcc;
            color: #c33;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .success-message {
            background: #efe;
            border: 1px solid #cfc;
            color: #3c3;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .install-button {
            width: 100%;
            padding: 12px;
            background: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
        }
        .install-button:hover {
            background: #45a049;
        }
    </style>
</head>
<body>
    <div class="install-container">
        <div class="install-header">
            <h1>Memorial Website Installation</h1>
            <p>Set up your memorial website in a few simple steps</p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="error-messages">
                <strong>Please correct the following errors:</strong>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success-message">
                <strong>Installation Complete!</strong>
                <p>Your memorial website has been set up successfully.</p>
                <p><a href="admin/">Click here to log in to the admin panel</a></p>
                <p><a href="index.php">Or view the public memorial page</a></p>
            </div>
        <?php else: ?>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="memorial_name">Who is this memorial for? *</label>
                    <input type="text" id="memorial_name" name="memorial_name" 
                           value="<?= htmlspecialchars($_POST['memorial_name'] ?? '') ?>" required>
                    <div class="form-help">Enter the full name of your loved one</div>
                </div>

                <div class="form-group">
                    <label for="site_title">Website Title (optional)</label>
                    <input type="text" id="site_title" name="site_title" 
                           value="<?= htmlspecialchars($_POST['site_title'] ?? '') ?>">
                    <div class="form-help">Leave blank to use "In Memory of [Name]"</div>
                </div>

                <div class="form-group">
                    <label for="memorial_photo">Memorial Photo (optional)</label>
                    <input type="file" id="memorial_photo" name="memorial_photo" accept="image/*">
                    <div class="form-help">A photo of your loved one (JPG, PNG, GIF - max 5MB)</div>
                </div>

                <hr style="margin: 30px 0;">

                <div class="form-group">
                    <label for="admin_email">Administrator Email *</label>
                    <input type="email" id="admin_email" name="admin_email" 
                           value="<?= htmlspecialchars($_POST['admin_email'] ?? '') ?>" required>
                    <div class="form-help">Your email address for login and notifications</div>
                </div>

                <div class="form-group">
                    <label for="admin_password">Administrator Password *</label>
                    <input type="password" id="admin_password" name="admin_password" required>
                    <div class="form-help">At least 6 characters</div>
                </div>

                <div class="form-group">
                    <label for="admin_password_confirm">Confirm Password *</label>
                    <input type="password" id="admin_password_confirm" name="admin_password_confirm" required>
                </div>

                <div class="form-group">
                    <label for="timezone">Timezone *</label>
                    <select id="timezone" name="timezone" required>
                        <?php
                        $timezones = DateTimeZone::listIdentifiers();
                        $selected = $_POST['timezone'] ?? 'America/New_York';
                        foreach ($timezones as $tz) {
                            $sel = ($tz === $selected) ? 'selected' : '';
                            echo "<option value=\"$tz\" $sel>$tz</option>";
                        }
                        ?>
                    </select>
                    <div class="form-help">Select your local timezone</div>
                </div>

                <button type="submit" class="install-button">Complete Installation</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
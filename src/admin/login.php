<?php
session_start();

// If already logged in, go to dashboard
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../service/settings.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pw = trim($_POST['password'] ?? '');
    if ($pw === '') {
        $error = 'Please enter a password.';
    } else {
        $ok = false;

        // If ADMIN_PASSWORD_PLAIN is defined, allow direct match (temporary override)
        if (defined('ADMIN_PASSWORD_PLAIN') && constant('ADMIN_PASSWORD_PLAIN') !== '') {
            if (hash_equals((string)constant('ADMIN_PASSWORD_PLAIN'), $pw)) {
                $ok = true;
            }
        }

        // Otherwise or additionally check the stored hash
        if (!$ok && defined('ADMIN_PASSWORD_HASH') && constant('ADMIN_PASSWORD_HASH') !== '') {
            if (password_verify($pw, constant('ADMIN_PASSWORD_HASH'))) {
                $ok = true;
            }
        }

        if ($ok) {
            $_SESSION['admin_logged_in'] = true;
            header('Location: index.php');
            exit;
        } else {
            $error = 'Invalid password.';
        }
    }
}

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Admin Login</title>
    <link rel="stylesheet" href="../styles/style.css">
    <style> .login-box{max-width:420px;margin:40px auto;padding:18px;border:1px solid #ddd;border-radius:6px} .hint{font-size:0.9em;color:#666;margin-top:8px;} </style>
    <?php
        $favicon = get_setting('favicon', '');
        if (!empty($favicon)) echo '<link rel="icon" href="' . htmlspecialchars($favicon) . '">';
    ?>
</head>
<body>
    <div class="login-box">
        <h2>Admin Login</h2>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="post" action="login.php">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>
            <div style="margin-top:12px;"><button type="submit">Log in</button></div>
        </form>

        <div class="hint">
            Forgot the password? You can temporarily set a plaintext admin password in <code>src/config.php</code> by adding:
            <pre>define('ADMIN_PASSWORD_PLAIN', 'YourTempPassword');</pre>
            After logging in, remove that line and set a proper hashed password (see installer or use PHP's <code>password_hash()</code>).
        </div>
    </div>
</body>
</html>

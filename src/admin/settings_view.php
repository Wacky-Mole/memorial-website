<?php
session_start();

// Require admin login
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: login.php');
    exit();
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../service/settings.php';
require_once __DIR__ . '/../service/email_utils.php';

$message = '';
$error = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // If this is a test-email action, send a test and return (JSON for XHR)
    if (isset($_POST['action']) && $_POST['action'] === 'test_email') {
        $to = get_setting('notify_email', ADMIN_EMAIL);
        $subject = 'Test email from ' . (defined('SITE_TITLE') ? SITE_TITLE : 'Memorial Website');
        $body = "This is a test notification email from your Memorial Website installation.\n\nIf you receive this, email sending is configured correctly.";
        $sent = send_email($to, $subject, $body);
        // If request is AJAX, return JSON
        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

        if ($isAjax) {
            header('Content-Type: application/json');
            if ($sent) {
                echo json_encode(['ok' => true, 'message' => 'Test email sent (check inbox/spam).']);
            } else {
                echo json_encode(['ok' => false, 'message' => 'Test email failed — check SMTP settings and server logs.']);
            }
            exit();
        }

        if ($sent) {
            $message = 'Test email sent (check inbox/spam).';
        } else {
            $error = 'Test email failed — check SMTP settings and server logs.';
        }
    }
    // Convert plaintext admin password to hash in config.php
    elseif (isset($_POST['action']) && $_POST['action'] === 'convert_password') {
        $configFile = __DIR__ . '/../config.php';
        if (!file_exists($configFile)) {
            $error = 'Configuration file not found.';
        } else {
            // Ensure ADMIN_PASSWORD_PLAIN exists in runtime
            if (!defined('ADMIN_PASSWORD_PLAIN') || constant('ADMIN_PASSWORD_PLAIN') === '') {
                $error = 'No plaintext admin password defined in config.php.';
            } else {
                $plain = constant('ADMIN_PASSWORD_PLAIN');
                $hash = password_hash($plain, PASSWORD_DEFAULT);
                if ($hash === false) {
                    $error = 'Failed to generate password hash.';
                } else {
                    $cfg = file_get_contents($configFile);
                    if ($cfg === false) {
                        $error = 'Failed to read config.php.';
                    } else {
                        // Remove the ADMIN_PASSWORD_PLAIN line
                        $pattern = "/define\(\s*'ADMIN_PASSWORD_PLAIN'\s*,[^;]+;?/";
                        $new = preg_replace($pattern, '', $cfg, 1);
                        if ($new === null) {
                            $error = 'Failed to process config.php.';
                        } else {
                            // Insert hashed define after opening PHP tag
                            $insert = "\n// Admin password hash (converted via admin UI)\ndefine('ADMIN_PASSWORD_HASH', " . var_export($hash, true) . ");\n";
                            if (preg_match('/<\?php\s*/', $new, $m, PREG_OFFSET_CAPTURE)) {
                                $pos = strpos($new, "\n", $m[0][1]);
                                if ($pos !== false) {
                                    $new = substr_replace($new, $insert, $pos + 1, 0);
                                } else {
                                    $new = $insert . $new;
                                }
                            } else {
                                $new = $insert . $new;
                            }

                            // Write atomically
                            $tmp = $configFile . '.tmp';
                            if (file_put_contents($tmp, $new) === false) {
                                $error = 'Failed to write temporary config file.';
                            } elseif (!rename($tmp, $configFile)) {
                                $error = 'Failed to install new config file.';
                            } else {
                                $message = 'Admin password converted to secure hash; plaintext removed from config.php.';
                            }
                        }
                    }
                }
            }
        }
        // If AJAX, return JSON
        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
        if ($isAjax) {
            header('Content-Type: application/json');
            if ($error !== '') echo json_encode(['ok' => false, 'message' => $error]);
            else echo json_encode(['ok' => true, 'message' => $message]);
            exit();
        }
    } else {
    // Save posted settings
    $notify_on_submission = isset($_POST['notify_on_submission']) ? '1' : '0';
    $auto_approve = isset($_POST['auto_approve']) ? '1' : '0';
    $notify_email = filter_var(trim($_POST['notify_email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $smtp_enabled = isset($_POST['smtp_enabled']) ? '1' : '0';
    $smtp_host = trim($_POST['smtp_host'] ?? '');
    $smtp_port = intval($_POST['smtp_port'] ?? 25);
    $smtp_username = trim($_POST['smtp_username'] ?? '');
    $smtp_password = trim($_POST['smtp_password'] ?? '');
    $smtp_secure = in_array($_POST['smtp_secure'] ?? 'none', ['none','tls','ssl']) ? $_POST['smtp_secure'] : 'none';

    $ok = true;
    $ok = $ok && set_setting('notify_on_submission', $notify_on_submission);
    $ok = $ok && set_setting('auto_approve', $auto_approve);
    $ok = $ok && set_setting('notify_email', $notify_email);
    $ok = $ok && set_setting('smtp_enabled', $smtp_enabled);
    $ok = $ok && set_setting('smtp_host', $smtp_host);
    $ok = $ok && set_setting('smtp_port', (string)$smtp_port);
    $ok = $ok && set_setting('smtp_username', $smtp_username);
    $ok = $ok && set_setting('smtp_password', $smtp_password);
    $ok = $ok && set_setting('smtp_secure', $smtp_secure);

    if ($ok) {
        $message = 'Settings saved.';
    } else {
        $error = 'Failed to save settings.';
    }
}

    }

    $all = get_all_settings();

// helpers for display
function val($k, $d = '') {
    $v = get_setting($k, $d);
    return htmlspecialchars((string)$v);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>View Settings</title>
    <link rel="stylesheet" href="../styles/style.css">
</head>
<body>
    <h1>Saved Settings</h1>

    <?php if ($message): ?>
        <div class="success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <h2>All stored keys</h2>
    <table border="1" cellpadding="6">
        <tr><th>Key</th><th>Value</th></tr>
        <?php foreach ($all as $k => $v): ?>
            <tr>
                <td><?php echo htmlspecialchars($k); ?></td>
                <td><?php echo htmlspecialchars($v); ?></td>
            </tr>
        <?php endforeach; ?>
    </table>

    <h2>Edit Notification / SMTP</h2>
    <form method="post" action="settings_view.php">
        <label>
            <input type="checkbox" name="notify_on_submission" value="1" <?php if (val('notify_on_submission','0') === '1') echo 'checked'; ?>>
            Notify on new submission
        </label>
        <br>
        <label>
            <input type="checkbox" name="auto_approve" value="1" <?php if (val('auto_approve','0') === '1') echo 'checked'; ?>> Auto-approve new submissions
        </label>
        <br>
        <label for="notify_email">Notification email:</label>
        <input type="email" id="notify_email" name="notify_email" value="<?php echo val('notify_email', ADMIN_EMAIL); ?>">
        <br><br>

        <label>
            <input type="checkbox" name="smtp_enabled" value="1" <?php if (val('smtp_enabled','0') === '1') echo 'checked'; ?>> Use SMTP
        </label>
        <br>
        <label for="smtp_host">SMTP host:</label>
        <input type="text" id="smtp_host" name="smtp_host" value="<?php echo val('smtp_host'); ?>">
        <br>
        <label for="smtp_port">SMTP port:</label>
        <input type="number" id="smtp_port" name="smtp_port" value="<?php echo val('smtp_port','25'); ?>">
        <br>
        <label for="smtp_username">SMTP username:</label>
        <input type="text" id="smtp_username" name="smtp_username" value="<?php echo val('smtp_username'); ?>">
        <br>
        <label for="smtp_password">SMTP password:</label>
        <input type="password" id="smtp_password" name="smtp_password" value="<?php echo val('smtp_password'); ?>">
        <br>
        <label for="smtp_secure">SMTP secure:</label>
        <select id="smtp_secure" name="smtp_secure">
            <option value="none" <?php if (val('smtp_secure','none') === 'none') echo 'selected'; ?>>None</option>
            <option value="tls" <?php if (val('smtp_secure') === 'tls') echo 'selected'; ?>>TLS</option>
            <option value="ssl" <?php if (val('smtp_secure') === 'ssl') echo 'selected'; ?>>SSL</option>
        </select>

        <div style="margin-top:12px;">
            <button type="submit">Save</button>
        </div>
    </form>

    <h3>Send Test Email</h3>
    <form id="test-email-form" method="post" action="settings_view.php">
        <input type="hidden" name="action" value="test_email">
        <div style="margin-top:12px;">
            <button id="test-email-button" type="submit">Send Test Email</button>
            <span id="test-result" style="margin-left:12px;"></span>
        </div>
    </form>

    <h3>Convert Admin Password (one-click)</h3>
    <p>If you have `ADMIN_PASSWORD_PLAIN` defined in `src/config.php`, this will convert it to a secure `ADMIN_PASSWORD_HASH` and remove the plaintext define. Use immediately and then verify login.</p>
    <form id="convert-password-form" method="post" action="settings_view.php">
        <input type="hidden" name="action" value="convert_password">
        <div style="margin-top:12px;">
            <button id="convert-password-button" type="submit">Convert Plain Password to Hash</button>
            <span id="convert-result" style="margin-left:12px;"></span>
        </div>
    </form>

    <script>
    (function(){
        var form = document.getElementById('convert-password-form');
        var button = document.getElementById('convert-password-button');
        var result = document.getElementById('convert-result');
        if (!form) return;
        form.addEventListener('submit', function(e){
            if (!confirm('Convert stored plaintext admin password into a secure hash and remove the plaintext from config.php?')) {
                e.preventDefault();
                return;
            }
            e.preventDefault();
            result.textContent = 'Converting...';
            button.disabled = true;
            var data = new FormData(form);
            fetch(form.action, {
                method: 'POST',
                body: data,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            }).then(function(resp){ return resp.json(); }).then(function(json){
                if (json && json.ok) {
                    result.style.color = 'green';
                    result.textContent = json.message || 'Converted.';
                } else {
                    result.style.color = 'red';
                    result.textContent = (json && json.message) ? json.message : 'Conversion failed.';
                }
            }).catch(function(err){
                result.style.color = 'red';
                result.textContent = 'Request failed: ' + (err && err.message ? err.message : 'network error');
            }).finally(function(){ button.disabled = false; });
        });
    })();
    </script>

    <script>
    (function(){
        var form = document.getElementById('test-email-form');
        var button = document.getElementById('test-email-button');
        var result = document.getElementById('test-result');

        if (!form) return;

        form.addEventListener('submit', function(e){
            e.preventDefault();
            result.textContent = 'Sending...';
            button.disabled = true;

            var data = new FormData(form);

            fetch(form.action, {
                method: 'POST',
                body: data,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            }).then(function(resp){
                return resp.json();
            }).then(function(json){
                if (json && json.ok) {
                    result.style.color = 'green';
                    result.textContent = json.message || 'Test email sent.';
                } else {
                    result.style.color = 'red';
                    result.textContent = (json && json.message) ? json.message : 'Test failed.';
                }
            }).catch(function(err){
                result.style.color = 'red';
                result.textContent = 'Request failed: ' + (err && err.message ? err.message : 'network error');
            }).finally(function(){
                button.disabled = false;
            });
        });
    })();
    </script>

    <p><a href="settings.php">Back to Admin Settings</a></p>
</body>
</html>

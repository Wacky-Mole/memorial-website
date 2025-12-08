<?php
// Minimal email utility: supports SMTP (basic) or PHP mail() as fallback

// Attempt to load DB-backed settings (overrides config.php constants)
$__EMAIL_UTILS_SETTINGS_LOADED = false;
if (file_exists(__DIR__ . '/settings.php')) {
    require_once __DIR__ . '/settings.php';
    $__EMAIL_UTILS_SETTINGS_LOADED = true;
}

function _email_setting($key, $default = null) {
    global $__EMAIL_UTILS_SETTINGS_LOADED;
    if ($__EMAIL_UTILS_SETTINGS_LOADED) {
        $v = get_setting($key, null);
        if ($v !== null) return $v;
    }
    return $default;
}

function send_notification_email(array $entry): bool {
    $to = defined('NOTIFY_EMAIL') ? NOTIFY_EMAIL : (defined('ADMIN_EMAIL') ? ADMIN_EMAIL : '');
    if (empty($to)) return false;

    // Prefer DB-backed memorial name when available, otherwise use DEFAULT_MEMORIAL_NAME or 'Memorial'
    $memName = 'Memorial';
    if (function_exists('get_setting')) {
        $memName = get_setting('memorial_name', (defined('DEFAULT_MEMORIAL_NAME') ? DEFAULT_MEMORIAL_NAME : 'Memorial'));
    } else {
        $memName = (defined('DEFAULT_MEMORIAL_NAME') ? DEFAULT_MEMORIAL_NAME : 'Memorial');
    }
    $subject = 'New memorial submission: ' . $memName;
    $body = "A new submission was received:\n\n";
    $body .= "Contributor: " . ($entry['contributor'] ?? '') . "\n";
    $body .= "Email: " . ($entry['email'] ?? '') . "\n";
    $body .= "Message:\n" . ($entry['message'] ?? '') . "\n\n";
    if (!empty($entry['photo'])) {
        $body .= "Photo: " . $entry['photo'] . "\n\n";
    }
    $body .= "View entries in admin panel to moderate.\n";

    return send_email($to, $subject, $body);
}

function send_email(string $to, string $subject, string $body): bool {
    // Determine SMTP settings preference: DB-backed settings override config constants when present
    $smtp_enabled_cfg = _email_setting('smtp_enabled', null);
    $smtp_enabled = ($smtp_enabled_cfg !== null) ? ($smtp_enabled_cfg === '1') : (defined('SMTP_ENABLED') && SMTP_ENABLED);
    $smtp_host = _email_setting('smtp_host', (defined('SMTP_HOST') ? SMTP_HOST : ''));

    if ($smtp_enabled && !empty($smtp_host)) {
        return send_via_smtp($to, $subject, $body);
    }

    // Use PHP mail as fallback
    $from = defined('NOTIFY_EMAIL') ? NOTIFY_EMAIL : (defined('ADMIN_EMAIL') ? ADMIN_EMAIL : 'no-reply@localhost');
    $headers = 'From: ' . $from . "\r\n";
    $headers .= 'Reply-To: ' . (defined('NOTIFY_EMAIL') ? NOTIFY_EMAIL : '') . "\r\n";
    $headers .= 'X-Mailer: PHP/' . phpversion();
    return mail($to, $subject, $body, $headers);
}

function send_via_smtp(string $to, string $subject, string $body): bool {
    // Allow DB-backed settings to override constants
    $host = _email_setting('smtp_host', (defined('SMTP_HOST') ? SMTP_HOST : ''));
    $port = intval(_email_setting('smtp_port', (defined('SMTP_PORT') ? SMTP_PORT : 25)));
    $username = _email_setting('smtp_username', (defined('SMTP_USERNAME') ? SMTP_USERNAME : ''));
    $password = _email_setting('smtp_password', (defined('SMTP_PASSWORD') ? SMTP_PASSWORD : ''));
    $secure = _email_setting('smtp_secure', (defined('SMTP_SECURE') ? SMTP_SECURE : 'none'));

    $from = defined('NOTIFY_EMAIL') ? NOTIFY_EMAIL : (defined('ADMIN_EMAIL') ? ADMIN_EMAIL : 'no-reply@localhost');

    $recipients = is_string($to) ? array_map('trim', explode(',', $to)) : (array)$to;

    $socket = null;
    $remote = ($secure === 'ssl') ? 'ssl://' . $host : $host;
    $errno = 0; $errstr = '';
    $timeout = 10;

    $socket = @stream_socket_client($remote . ':' . $port, $errno, $errstr, $timeout);
    if (!$socket) {
        error_log("SMTP connect failed: $errno $errstr");
        return false;
    }

    $res = smtp_get_code($socket);
    if ($res === false) { fclose($socket); return false; }

    $hostToSay = $host ?: 'localhost';
    smtp_command($socket, "EHLO $hostToSay");

    if ($secure === 'tls') {
        // Attempt STARTTLS
        smtp_command($socket, "STARTTLS");
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            error_log('SMTP STARTTLS failed');
            fclose($socket);
            return false;
        }
        smtp_command($socket, "EHLO $hostToSay");
    }

    if (!empty($username)) {
        smtp_command($socket, "AUTH LOGIN");
        smtp_command($socket, base64_encode($username));
        smtp_command($socket, base64_encode($password));
    }

    smtp_command($socket, "MAIL FROM:<$from>");
    foreach ($recipients as $rcpt) {
        smtp_command($socket, "RCPT TO:<$rcpt>");
    }

    smtp_command($socket, "DATA");

    $headers = [];
    $headers[] = 'From: ' . $from;
    $headers[] = 'To: ' . implode(',', $recipients);
    $headers[] = 'Subject: ' . $subject;
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: text/plain; charset=UTF-8';
    $headers[] = '';

    $data = implode("\r\n", $headers) . "\r\n" . $body . "\r\n.";
    smtp_command($socket, $data);

    smtp_command($socket, "QUIT");
    fclose($socket);
    return true;
}

function smtp_get_code($socket) {
    $line = fgets($socket, 515);
    if ($line === false) return false;
    return intval(substr($line, 0, 3));
}

function smtp_command($socket, $cmd) {
    fwrite($socket, $cmd . "\r\n");
    // read multiline response
    $res = '';
    while (($line = fgets($socket, 515)) !== false) {
        $res .= $line;
        // last line starts with code + space
        if (isset($line[3]) && $line[3] === ' ') break;
    }
    return $res;
}

?>
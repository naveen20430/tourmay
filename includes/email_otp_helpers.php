<?php
/**
 * Email OTP helpers (SMTP via Hostinger).
 * Mobile/WhatsApp OTP remains in whatsapp_otp_helpers.php (commented out in UI).
 */

require_once __DIR__ . '/whatsapp_otp_helpers.php';

function ensureEmailOtpSchema() {
    global $db;
    static $ready = false;
    if ($ready) {
        return;
    }

    $db->getConnection()->exec("CREATE TABLE IF NOT EXISTS email_login_otps (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL,
        otp_hash VARCHAR(255) NOT NULL,
        expires_at DATETIME NOT NULL,
        attempts INT NOT NULL DEFAULT 0,
        send_count INT NOT NULL DEFAULT 1,
        verified TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_email_created (email, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $ready = true;
}

function normalizeEmailAddress($email) {
    $email = strtolower(trim((string) $email));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return '';
    }
    return $email;
}

function getSmtpSettings() {
    $host = trim((string) getSetting('smtp_host'));
    $port = (int) getSetting('smtp_port');
    $encryption = strtolower(trim((string) getSetting('smtp_encryption')));
    $username = trim((string) getSetting('smtp_username'));
    $password = (string) getSetting('smtp_password');
    $fromEmail = trim((string) getSetting('smtp_from_email'));
    $fromName = trim((string) getSetting('smtp_from_name'));

    if ($host === '') {
        $host = 'smtp.hostinger.com';
    }
    if ($port < 1) {
        $port = 465;
    }
    if ($encryption === '') {
        $encryption = 'ssl';
    }
    if ($username === '') {
        $username = 'noreply@theworldjourney.in';
    }
    if ($fromEmail === '') {
        $fromEmail = $username !== '' ? $username : 'noreply@theworldjourney.in';
    }
    if ($fromName === '') {
        $fromName = getSetting('site_name') ?: 'The World Journey';
    }

    return [
        'host' => $host,
        'port' => $port,
        'encryption' => $encryption,
        'username' => $username,
        'password' => $password,
        'from_email' => $fromEmail,
        'from_name' => $fromName,
    ];
}

function emailOtpIsConfigured() {
    $smtp = getSmtpSettings();
    return $smtp['host'] !== ''
        && $smtp['username'] !== ''
        && $smtp['password'] !== ''
        && $smtp['from_email'] !== '';
}

function emailOtpSmtpRead($fp, &$log) {
    $data = '';
    while (($line = fgets($fp, 515)) !== false) {
        $data .= $line;
        $log[] = '← ' . rtrim($line);
        if (isset($line[3]) && $line[3] === ' ') {
            break;
        }
    }
    return $data;
}

function emailOtpSmtpWrite($fp, $cmd, &$log, $mask = false) {
    $log[] = '→ ' . ($mask ? '[credentials hidden]' : rtrim($cmd));
    fwrite($fp, $cmd . "\r\n");
}

/**
 * Build OTP email HTML matching invoice page / PDF brand design.
 */
function buildEmailOtpHtml($otp, $purpose = 'login') {
    $siteName = getSetting('site_name') ?: 'The World Journey';
    $siteTagline = getSetting('site_tagline') ?: 'Travel & Tour Booking Agency';
    $siteAddress = trim((string) getSetting('site_address'));
    $contactEmail = trim((string) (getSetting('contact_email') ?: getSetting('site_email') ?: ''));
    $contactPhone = trim((string) (getSetting('contact_phone') ?: getSetting('site_phone') ?: ''));
    $logoUrl = rtrim(BASE_URL, '/') . '/assets/images/logonew.png';
    $homeUrl = rtrim(BASE_URL, '/') . '/';

    $title = $purpose === 'register' ? 'Verify your email' : 'Login verification';
    $intro = $purpose === 'register'
        ? 'Use this code to verify your email and complete registration.'
        : 'Use this code to sign in to your account.';

    $otpEsc = htmlspecialchars((string) $otp, ENT_QUOTES, 'UTF-8');
    $siteEsc = htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8');
    $tagEsc = htmlspecialchars($siteTagline, ENT_QUOTES, 'UTF-8');
    $addrEsc = htmlspecialchars($siteAddress, ENT_QUOTES, 'UTF-8');
    $titleEsc = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $introEsc = htmlspecialchars($intro, ENT_QUOTES, 'UTF-8');
    $emailEsc = htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8');
    $phoneEsc = htmlspecialchars($contactPhone, ENT_QUOTES, 'UTF-8');
    $logoEsc = htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8');
    $homeEsc = htmlspecialchars($homeUrl, ENT_QUOTES, 'UTF-8');

    $addressRow = $siteAddress !== ''
        ? '<p style="margin:4px 0 0;font-size:12px;line-height:1.35;color:rgba(255,255,255,0.9);">' . $addrEsc . '</p>'
        : '';

    $contactBits = array_filter([$emailEsc, $phoneEsc]);
    $footerContact = $contactBits !== [] ? implode(' | ', $contactBits) : '';

    return '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>' . $titleEsc . ' - ' . $siteEsc . '</title>
</head>
<body style="margin:0;padding:0;background:#f4f6fb;font-family:Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#0f172a;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#f4f6fb;padding:28px 12px;">
  <tr>
    <td align="center">
      <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="max-width:600px;width:100%;background:#ffffff;border:1px solid #dde3ef;border-radius:4px;overflow:hidden;box-shadow:0 4px 24px rgba(15,23,42,0.08);">
        <tr>
          <td style="background:linear-gradient(135deg,#764ba2 0%,#667eea 100%);padding:18px 24px;">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
              <tr>
                <td valign="middle" style="width:70%;">
                  <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                    <tr>
                      <td valign="middle" style="background:#ffffff;border-radius:4px;padding:8px 10px;width:88px;height:60px;text-align:center;">
                        <img src="' . $logoEsc . '" alt="' . $siteEsc . '" width="72" height="48" style="display:block;max-width:72px;max-height:48px;width:auto;height:auto;margin:0 auto;border:0;">
                      </td>
                      <td valign="middle" style="padding-left:14px;">
                        <h1 style="margin:0 0 2px;font-size:20px;line-height:1.2;font-weight:700;color:#ffffff;">' . $siteEsc . '</h1>
                        <p style="margin:0;font-size:13px;line-height:1.35;color:rgba(255,255,255,0.92);">' . $tagEsc . '</p>
                        ' . $addressRow . '
                      </td>
                    </tr>
                  </table>
                </td>
                <td valign="middle" align="right" style="width:30%;text-align:right;">
                  <div style="font-size:11px;text-transform:uppercase;letter-spacing:0.12em;font-weight:700;color:rgba(255,255,255,0.9);">Security</div>
                  <div style="margin-top:2px;font-size:18px;font-weight:700;color:#ffffff;">OTP Code</div>
                </td>
              </tr>
            </table>
          </td>
        </tr>
        <tr>
          <td style="padding:28px 24px 8px;">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#f8f9ff;border:1px solid #e9ecef;border-radius:4px;">
              <tr>
                <td style="padding:16px 18px;">
                  <div style="font-size:12px;text-transform:uppercase;letter-spacing:0.08em;color:#64748b;font-weight:700;">' . $titleEsc . '</div>
                  <div style="margin-top:6px;font-size:15px;line-height:1.5;color:#0f172a;">' . $introEsc . '</div>
                </td>
              </tr>
            </table>
          </td>
        </tr>
        <tr>
          <td style="padding:8px 24px 20px;" align="center">
            <div style="display:inline-block;margin:12px 0;padding:18px 28px;background:linear-gradient(135deg,#764ba2 0%,#667eea 100%);border-radius:4px;color:#ffffff;font-size:32px;font-weight:700;letter-spacing:0.35em;line-height:1;">' . $otpEsc . '</div>
            <p style="margin:8px 0 0;font-size:13px;color:#64748b;">Valid for <strong style="color:#0f172a;">10 minutes</strong>. Do not share this code.</p>
          </td>
        </tr>
        <tr>
          <td style="padding:0 24px 24px;">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border-top:1px solid #e9ecef;">
              <tr>
                <td style="padding-top:16px;font-size:12px;line-height:1.5;color:#64748b;">
                  If you did not request this code, you can safely ignore this email.
                  <br>
                  Thank you for choosing ' . $siteEsc . '.
                  ' . ($footerContact !== '' ? '<br>' . $footerContact : '') . '
                </td>
              </tr>
              <tr>
                <td style="padding-top:14px;">
                  <a href="' . $homeEsc . '" style="display:inline-block;background:#667eea;color:#ffffff;text-decoration:none;font-size:13px;font-weight:600;padding:10px 16px;border-radius:4px;">Visit Website</a>
                </td>
              </tr>
            </table>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
</body>
</html>';
}

/**
 * Send email via configured SMTP (plain text + optional HTML, invoice-style).
 */
function sendSmtpMail($toEmail, $subject, $bodyText, $bodyHtml = null) {
    $smtp = getSmtpSettings();
    $toEmail = normalizeEmailAddress($toEmail);
    if ($toEmail === '') {
        throw new Exception('Invalid recipient email');
    }
    if ($smtp['password'] === '') {
        throw new Exception('Email OTP is not configured. Please set SMTP password in Admin → Settings.');
    }

    $log = [];
    $host = $smtp['host'];
    $port = (int) $smtp['port'];
    $enc = $smtp['encryption'];
    $user = $smtp['username'];
    $pass = $smtp['password'];
    $from = $smtp['from_email'];
    $fromName = $smtp['from_name'];
    $timeout = 25;

    $remote = ($enc === 'ssl') ? ('ssl://' . $host . ':' . $port) : ('tcp://' . $host . ':' . $port);
    $ctx = stream_context_create([
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'allow_self_signed' => false,
            'SNI_enabled' => true,
            'peer_name' => $host,
        ],
    ]);

    $fp = @stream_socket_client($remote, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) {
        throw new Exception('SMTP connect failed: ' . ($errstr ?: 'unable to reach mail server'));
    }
    stream_set_timeout($fp, $timeout);

    $banner = emailOtpSmtpRead($fp, $log);
    if ((int) substr($banner, 0, 3) !== 220) {
        fclose($fp);
        throw new Exception('SMTP greeting failed');
    }

    $ehloHost = 'theworldjourney.in';
    emailOtpSmtpWrite($fp, 'EHLO ' . $ehloHost, $log);
    $ehlo = emailOtpSmtpRead($fp, $log);
    if ((int) substr($ehlo, 0, 3) !== 250) {
        fclose($fp);
        throw new Exception('SMTP EHLO failed');
    }

    if ($enc === 'tls') {
        emailOtpSmtpWrite($fp, 'STARTTLS', $log);
        $tlsResp = emailOtpSmtpRead($fp, $log);
        if ((int) substr($tlsResp, 0, 3) !== 220) {
            fclose($fp);
            throw new Exception('SMTP STARTTLS rejected');
        }
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($fp);
            throw new Exception('SMTP TLS handshake failed');
        }
        emailOtpSmtpWrite($fp, 'EHLO ' . $ehloHost, $log);
        $ehlo2 = emailOtpSmtpRead($fp, $log);
        if ((int) substr($ehlo2, 0, 3) !== 250) {
            fclose($fp);
            throw new Exception('SMTP EHLO after TLS failed');
        }
    }

    emailOtpSmtpWrite($fp, 'AUTH LOGIN', $log);
    if ((int) substr(emailOtpSmtpRead($fp, $log), 0, 3) !== 334) {
        fclose($fp);
        throw new Exception('SMTP AUTH not accepted');
    }
    emailOtpSmtpWrite($fp, base64_encode($user), $log, true);
    if ((int) substr(emailOtpSmtpRead($fp, $log), 0, 3) !== 334) {
        fclose($fp);
        throw new Exception('SMTP username rejected');
    }
    emailOtpSmtpWrite($fp, base64_encode($pass), $log, true);
    if ((int) substr(emailOtpSmtpRead($fp, $log), 0, 3) !== 235) {
        fclose($fp);
        throw new Exception('SMTP authentication failed. Check mailbox password in Admin → Settings.');
    }

    emailOtpSmtpWrite($fp, 'MAIL FROM:<' . $from . '>', $log);
    if ((int) substr(emailOtpSmtpRead($fp, $log), 0, 3) !== 250) {
        fclose($fp);
        throw new Exception('SMTP MAIL FROM rejected');
    }

    emailOtpSmtpWrite($fp, 'RCPT TO:<' . $toEmail . '>', $log);
    $rcptCode = (int) substr(emailOtpSmtpRead($fp, $log), 0, 3);
    if ($rcptCode !== 250 && $rcptCode !== 251) {
        fclose($fp);
        throw new Exception('SMTP recipient rejected');
    }

    emailOtpSmtpWrite($fp, 'DATA', $log);
    if ((int) substr(emailOtpSmtpRead($fp, $log), 0, 3) !== 354) {
        fclose($fp);
        throw new Exception('SMTP DATA rejected');
    }

    $date = date('r');
    $msgId = sprintf('<%s@%s>', bin2hex(random_bytes(12)), 'theworldjourney.in');
    $encodedName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

    $dotStuff = static function ($content) {
        return str_replace(["\r\n.", "\n."], ["\r\n..", "\n.."], $content);
    };

    $headers = [
        'Date: ' . $date,
        'From: ' . $encodedName . ' <' . $from . '>',
        'To: <' . $toEmail . '>',
        'Subject: ' . $encodedSubject,
        'Message-ID: ' . $msgId,
        'MIME-Version: 1.0',
        'X-Mailer: TWJ-Email-OTP',
    ];

    if ($bodyHtml !== null && trim($bodyHtml) !== '') {
        $boundary = 'twj_' . bin2hex(random_bytes(8));
        $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
        $body = '--' . $boundary . "\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: 8bit\r\n\r\n"
            . $bodyText . "\r\n\r\n"
            . '--' . $boundary . "\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: 8bit\r\n\r\n"
            . $bodyHtml . "\r\n\r\n"
            . '--' . $boundary . "--\r\n";
    } else {
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        $headers[] = 'Content-Transfer-Encoding: 8bit';
        $body = $bodyText;
    }

    $payload = implode("\r\n", $headers) . "\r\n\r\n" . $dotStuff($body) . "\r\n.";
    fwrite($fp, $payload . "\r\n");
    $sendResp = emailOtpSmtpRead($fp, $log);
    if ((int) substr($sendResp, 0, 3) !== 250) {
        fclose($fp);
        throw new Exception('SMTP message not accepted');
    }

    emailOtpSmtpWrite($fp, 'QUIT', $log);
    emailOtpSmtpRead($fp, $log);
    fclose($fp);

    return ['ok' => true, 'to' => $toEmail];
}

function sendEmailOtpMessage($email, $otp, $purpose = 'login') {
    $email = normalizeEmailAddress($email);
    if ($email === '') {
        throw new Exception('Please enter a valid email address');
    }
    if (!emailOtpIsConfigured()) {
        throw new Exception('Email OTP is not configured yet. Please set SMTP settings in Admin.');
    }

    $siteName = getSetting('site_name') ?: 'The World Journey';
    $subject = $siteName . ' verification code';
    $bodyText = $siteName . " verification code: {$otp}\n\n"
        . "This code is valid for 10 minutes.\n"
        . "If you did not request this, you can ignore this email.\n";
    $bodyHtml = buildEmailOtpHtml($otp, $purpose);

    $result = sendSmtpMail($email, $subject, $bodyText, $bodyHtml);
    if (function_exists('otpSendLog')) {
        otpSendLog('email_smtp', $email, 'sent', 'to=' . $email);
    }
    return $result;
}

function findUserByEmail($email) {
    global $db;
    $email = normalizeEmailAddress($email);
    if ($email === '') {
        return null;
    }
    return $db->fetch("SELECT * FROM users WHERE email = ? AND status = 'active' LIMIT 1", [$email]);
}

function emailBelongsToAnotherUser($email, $excludeUserId = 0) {
    global $db;
    $email = normalizeEmailAddress($email);
    if ($email === '') {
        return false;
    }
    $user = $db->fetch('SELECT id FROM users WHERE email = ? LIMIT 1', [$email]);
    if (!$user) {
        return false;
    }
    return (int) $user['id'] !== (int) $excludeUserId;
}

function setVerifiedEmailSession($email, $purpose) {
    $_SESSION['email_otp_verified'] = [
        'email' => normalizeEmailAddress($email),
        'purpose' => $purpose,
        'verified_at' => time(),
    ];
}

function isVerifiedEmailSession($purpose, $email) {
    $data = $_SESSION['email_otp_verified'] ?? null;
    if (!is_array($data)) {
        return false;
    }
    if (($data['purpose'] ?? '') !== $purpose) {
        return false;
    }
    if (normalizeEmailAddress($email) !== ($data['email'] ?? '')) {
        return false;
    }
    return (time() - (int) ($data['verified_at'] ?? 0)) <= 900;
}

function consumeVerifiedEmailSession($purpose, $email) {
    if (!isVerifiedEmailSession($purpose, $email)) {
        return false;
    }
    unset($_SESSION['email_otp_verified']);
    return true;
}

function createOtpForEmail($email, $purpose = 'login') {
    global $db;
    ensureEmailOtpSchema();

    $purpose = in_array($purpose, ['login', 'register'], true) ? $purpose : 'login';
    $normalized = normalizeEmailAddress($email);
    if ($normalized === '') {
        throw new Exception('Please enter a valid email address');
    }

    if (!emailOtpIsConfigured()) {
        throw new Exception('Email OTP login is not configured yet');
    }

    if ($purpose === 'login') {
        $user = findUserByEmail($normalized);
        if (!$user) {
            throw new Exception('No account found with this email. Please register first.');
        }
    }

    if ($purpose === 'register' && emailBelongsToAnotherUser($normalized)) {
        throw new Exception('This email is already registered. Please login instead.');
    }

    $recent = $db->fetch(
        "SELECT COUNT(*) AS total FROM email_login_otps
         WHERE email = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)",
        [$normalized]
    );
    if ((int) ($recent['total'] ?? 0) >= 5) {
        throw new Exception('Too many OTP requests. Please try again after 15 minutes.');
    }

    $otp = str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);
    $otpHash = password_hash($otp, PASSWORD_DEFAULT);
    $expiresAt = date('Y-m-d H:i:s', time() + 600);

    $db->execute(
        'INSERT INTO email_login_otps (email, otp_hash, expires_at) VALUES (?, ?, ?)',
        [$normalized, $otpHash, $expiresAt]
    );
    $otpId = (int) $db->lastInsertId();

    try {
        sendEmailOtpMessage($normalized, $otp, $purpose);
    } catch (Exception $e) {
        if ($otpId > 0) {
            $db->execute('DELETE FROM email_login_otps WHERE id = ?', [$otpId]);
        }
        if (function_exists('otpSendLog')) {
            otpSendLog('email_smtp', $normalized, 'failed', 'error=' . $e->getMessage());
        }
        throw $e;
    }

    return [
        'email' => $normalized,
        'expires_at' => $expiresAt,
    ];
}

function verifyEmailOtpCodeOnly($email, $otp) {
    global $db;
    ensureEmailOtpSchema();

    $normalized = normalizeEmailAddress($email);
    $otp = str_pad(trim((string) $otp), 5, '0', STR_PAD_LEFT);
    if ($normalized === '' || !preg_match('/^\d{5}$/', $otp)) {
        throw new Exception('Invalid email or OTP');
    }

    $record = $db->fetch(
        "SELECT * FROM email_login_otps
         WHERE email = ? AND verified = 0
         ORDER BY id DESC
         LIMIT 1",
        [$normalized]
    );

    if (!$record) {
        throw new Exception('No active OTP found. Please request a new code.');
    }
    if (strtotime((string) $record['expires_at']) < time()) {
        throw new Exception('OTP has expired. Please request a new code.');
    }
    if ((int) $record['attempts'] >= 5) {
        throw new Exception('Too many invalid attempts. Please request a new OTP.');
    }
    if (!password_verify($otp, (string) $record['otp_hash'])) {
        $db->execute(
            'UPDATE email_login_otps SET attempts = attempts + 1 WHERE id = ?',
            [(int) $record['id']]
        );
        throw new Exception('Invalid OTP. Please try again.');
    }

    $db->execute(
        'UPDATE email_login_otps SET verified = 1 WHERE id = ?',
        [(int) $record['id']]
    );

    return $normalized;
}

function verifyOtpForEmailLogin($email, $otp) {
    $normalized = verifyEmailOtpCodeOnly($email, $otp);
    $user = findUserByEmail($normalized);
    if (!$user) {
        throw new Exception('No account found with this email.');
    }
    return $user;
}

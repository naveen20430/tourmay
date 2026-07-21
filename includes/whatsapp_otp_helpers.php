<?php
/**
 * WhatsApp OTP login helpers (Twilio)
 */

function ensureWhatsAppOtpSchema() {
    global $db;
    static $ready = false;
    if ($ready) {
        return;
    }

    $db->getConnection()->exec("CREATE TABLE IF NOT EXISTS whatsapp_login_otps (
        id INT AUTO_INCREMENT PRIMARY KEY,
        phone VARCHAR(20) NOT NULL,
        otp_hash VARCHAR(255) NOT NULL,
        expires_at DATETIME NOT NULL,
        attempts INT NOT NULL DEFAULT 0,
        send_count INT NOT NULL DEFAULT 1,
        verified TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_phone_created (phone, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $ready = true;
}

function twilioIsConfigured() {
    $creds = twilioGetCredentials();
    $from = trim((string) getSetting('twilio_whatsapp_from'));
    return $creds['account_sid'] !== '' && $creds['auth_user'] !== '' && $creds['auth_pass'] !== '' && $from !== '';
}

/**
 * Fast2SMS API key configured for SMS OTP.
 * @see https://docs.fast2sms.com/reference/authorization
 */
function fast2smsIsConfigured() {
    return trim((string) getSetting('fast2sms_api_key')) !== '';
}

/**
 * Mobile OTP available via Fast2SMS (SMS) and/or Twilio (WhatsApp).
 */
function mobileOtpIsConfigured() {
    return fast2smsIsConfigured() || twilioIsConfigured();
}

/**
 * Append a line to logs/otp.log for every OTP send attempt.
 */
function otpSendLog($channel, $phone, $status, $detail = '') {
    $dir = dirname(__DIR__) . '/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
        @file_put_contents($dir . '/.htaccess', "Require all denied\n");
    }
    $line = sprintf(
        "[%s] channel=%s phone=%s status=%s %s\n",
        date('Y-m-d H:i:s'),
        $channel,
        preg_replace('/\d(?=\d{4})/', '*', (string) $phone),
        $status,
        trim((string) $detail)
    );
    @file_put_contents($dir . '/otp.log', $line, FILE_APPEND | LOCK_EX);
    error_log('OTP ' . trim($line));
}

/**
 * Convert any phone format to a 10-digit Indian mobile for Fast2SMS.
 */
function phoneToFast2SmsMobile($phone) {
    $digits = preg_replace('/\D+/', '', (string) $phone);
    if ($digits === '') {
        return '';
    }
    if (strlen($digits) >= 10) {
        return substr($digits, -10);
    }
    return '';
}

/**
 * Resolve Twilio Account SID (for API URL) and auth username/password.
 * Supports Account SID + Auth Token, or Account SID + API Key SID + Secret.
 */
function twilioGetCredentials() {
    $accountSid = trim((string) getSetting('twilio_account_sid'));
    $apiKeySid = trim((string) getSetting('twilio_api_key_sid'));
    $authPass = trim((string) getSetting('twilio_auth_token'));

    // API Key SID (SK...) authenticates; Account SID (AC...) is still required in the URL.
    $authUser = $apiKeySid !== '' ? $apiKeySid : $accountSid;

    return [
        'account_sid' => $accountSid,
        'auth_user' => $authUser,
        'auth_pass' => $authPass,
    ];
}

function twilioIsSandboxMode() {
    $from = preg_replace('/\D+/', '', (string) getSetting('twilio_whatsapp_from'));
    return str_contains($from, '14155238886');
}

function getWhatsAppSandboxInstructions() {
    if (!twilioIsSandboxMode()) {
        return '';
    }

    $join = trim((string) getSetting('twilio_whatsapp_sandbox_join'));
    $from = trim((string) getSetting('twilio_whatsapp_from'));
    $fromDisplay = preg_replace('/^whatsapp:/i', '', $from);
    if ($join === '') {
        $join = 'your-sandbox-code';
    }

    return 'Each new WhatsApp number must opt in first: open WhatsApp and send '
        . '<strong>join ' . htmlspecialchars($join) . '</strong> to '
        . '<strong>' . htmlspecialchars($fromDisplay) . '</strong>, then request OTP again.';
}

function formatTwilioWhatsAppError(array $data) {
    $code = (int) ($data['code'] ?? 0);
    $message = (string) ($data['message'] ?? ($data['error_message'] ?? 'Unable to send WhatsApp OTP'));

    if ($code === 63015 || $code === 63007 || stripos($message, 'sandbox') !== false) {
        $plain = strip_tags(str_replace(['<strong>', '</strong>'], '', getWhatsAppSandboxInstructions()));
        return $plain !== '' ? $plain : 'This WhatsApp number is not allowed yet. Ask the user to join the Twilio sandbox first, then try again.';
    }

    if ($code === 21211 || stripos($message, 'not a valid') !== false) {
        return 'This phone number is not a valid WhatsApp number. Please check the country code and number.';
    }

    if ($code === 21608 || stripos($message, 'unsubscribed') !== false) {
        return 'This number has blocked or unsubscribed from WhatsApp messages.';
    }

    return $message;
}

function normalizePhoneE164($phone) {
    $digits = preg_replace('/\D+/', '', (string) $phone);
    if ($digits === '') {
        return '';
    }

    if (strlen($digits) === 10) {
        return '+91' . $digits;
    }

    if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
        return '+' . $digits;
    }

    if (str_starts_with($digits, '00')) {
        $digits = substr($digits, 2);
    }

    return '+' . $digits;
}

function establishUserSession(array $user) {
    global $db;

    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['user_name'] = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
    $_SESSION['user_email'] = $user['email'] ?? '';
    $_SESSION['user_first_name'] = $user['first_name'] ?? '';

    $db->execute('UPDATE users SET updated_at = NOW() WHERE id = ?', [(int) $user['id']]);
}

function findUserByPhone($phone) {
    global $db;

    $normalized = normalizePhoneE164($phone);
    if ($normalized === '') {
        return null;
    }

    $digits = preg_replace('/\D+/', '', $normalized);
    $last10 = strlen($digits) >= 10 ? substr($digits, -10) : $digits;

    return $db->fetch(
        "SELECT * FROM users
         WHERE status = 'active'
           AND (
                phone = ?
             OR phone = ?
             OR REPLACE(REPLACE(REPLACE(phone, '+', ''), ' ', ''), '-', '') = ?
             OR REPLACE(REPLACE(REPLACE(phone, '+', ''), ' ', ''), '-', '') LIKE ?
           )
         ORDER BY id ASC
         LIMIT 1",
        [$normalized, $digits, $digits, '%' . $last10]
    );
}

function phoneBelongsToAnotherUser($phone, $excludeUserId = 0) {
    global $db;

    $user = findUserByPhone($phone);
    if (!$user) {
        return false;
    }

    return (int) $user['id'] !== (int) $excludeUserId;
}

function setVerifiedPhoneSession($phone, $purpose) {
    $_SESSION['wa_otp_verified'] = [
        'phone' => normalizePhoneE164($phone),
        'purpose' => $purpose,
        'verified_at' => time(),
    ];
}

function isVerifiedPhoneSession($purpose, $phone) {
    $data = $_SESSION['wa_otp_verified'] ?? null;
    if (!is_array($data)) {
        return false;
    }

    if (($data['purpose'] ?? '') !== $purpose) {
        return false;
    }

    if (normalizePhoneE164($phone) !== ($data['phone'] ?? '')) {
        return false;
    }

    return (time() - (int) ($data['verified_at'] ?? 0)) <= 900;
}

function consumeVerifiedPhoneSession($purpose, $phone) {
    if (!isVerifiedPhoneSession($purpose, $phone)) {
        return false;
    }

    unset($_SESSION['wa_otp_verified']);
    return true;
}

function formatPhoneDisplay($phone) {
    $normalized = normalizePhoneE164($phone);
    if ($normalized === '') {
        return '';
    }

    $digits = preg_replace('/\D+/', '', $normalized);
    if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
        return '+91 ' . substr($digits, 2);
    }

    return $normalized;
}

function updateUserWhatsAppPhone($userId, $phone) {
    global $db;

    $normalized = normalizePhoneE164($phone);
    if ($normalized === '') {
        throw new Exception('Please enter a valid WhatsApp number');
    }

    if (phoneBelongsToAnotherUser($normalized, $userId)) {
        throw new Exception('This WhatsApp number is already linked to another account');
    }

    $db->execute(
        'UPDATE users SET phone = ?, updated_at = NOW() WHERE id = ?',
        [$normalized, (int) $userId]
    );

    return $normalized;
}

function findOrCreateUserByPhone($phone) {
    global $db;

    $user = findUserByPhone($phone);
    if ($user) {
        return $user;
    }

    $normalized = normalizePhoneE164($phone);
    $last10 = substr(preg_replace('/\D+/', '', $normalized), -10);
    $email = 'wa_' . $last10 . '@whatsapp.local';
    $password = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);

    $userId = $db->execute(
        "INSERT INTO users (first_name, last_name, email, password, phone, status, created_at)
         VALUES (?, ?, ?, ?, ?, 'active', NOW())",
        ['WhatsApp', 'User', $email, $password, $normalized]
    );

    return $db->fetch('SELECT * FROM users WHERE id = ?', [(int) $userId]);
}

function twilioApiHost() {
    return 'api.twilio.com';
}

function twilioResolveHostIp($host) {
    static $cache = [];
    if (isset($cache[$host])) {
        return $cache[$host];
    }

    $ip = @gethostbyname($host);
    if (!is_string($ip) || $ip === '' || $ip === $host) {
        $cache[$host] = null;
        return null;
    }

    $cache[$host] = $ip;
    return $ip;
}

/**
 * POST to Twilio API — works around shared-hosting getaddrinfo() thread limits.
 */
function twilioHttpPost($url, array $postFields, $sid, $token) {
    $host = twilioApiHost();
    $body = http_build_query($postFields);
    $resolvedIp = twilioResolveHostIp($host);
    $lastError = 'Unable to connect to Twilio';

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_USERPWD => $sid . ':' . $token,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_NOSIGNAL => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        ];

        if (defined('CURL_IPRESOLVE_V4')) {
            $options[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;
        }
        if ($resolvedIp && defined('CURLOPT_RESOLVE')) {
            $options[CURLOPT_RESOLVE] = [$host . ':443:' . $resolvedIp];
        }

        curl_setopt_array($ch, $options);
        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response !== false) {
            return ['response' => $response, 'http_code' => $httpCode];
        }

        $lastError = $curlError !== '' ? $curlError : $lastError;
        $retryable = stripos($lastError, 'getaddrinfo') !== false
            || stripos($lastError, 'resolve') !== false
            || stripos($lastError, 'thread failed') !== false;

        if (!$retryable) {
            throw new Exception('WhatsApp gateway error: ' . $lastError);
        }
    }

    if (!ini_get('allow_url_fopen')) {
        throw new Exception('WhatsApp gateway error: ' . $lastError);
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", [
                'Authorization: Basic ' . base64_encode($sid . ':' . $token),
                'Content-Type: application/x-www-form-urlencoded',
                'Content-Length: ' . strlen($body),
                'Connection: close',
            ]),
            'content' => $body,
            'timeout' => 30,
            'ignore_errors' => true,
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'SNI_enabled' => true,
            'peer_name' => $host,
        ],
    ]);

    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        throw new Exception('WhatsApp gateway error: Unable to reach Twilio right now. Please try again in a moment.');
    }

    $httpCode = 0;
    if (!empty($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $matches)) {
        $httpCode = (int) $matches[1];
    }

    return ['response' => $response, 'http_code' => $httpCode];
}

function sendWhatsAppTextMessage($phone, $body) {
    $creds = twilioGetCredentials();
    $from = trim((string) getSetting('twilio_whatsapp_from'));

    if ($creds['account_sid'] === '' || $creds['auth_user'] === '' || $creds['auth_pass'] === '' || $from === '') {
        throw new Exception('WhatsApp login is not configured. Please contact support.');
    }

    $to = 'whatsapp:' . normalizePhoneE164($phone);

    if (!str_starts_with($from, 'whatsapp:')) {
        $fromDigits = preg_replace('/\D+/', '', $from);
        $from = 'whatsapp:+' . $fromDigits;
    }

    $postFields = [
        'To' => $to,
        'From' => $from,
        'Body' => trim((string) $body),
    ];

    $url = 'https://' . twilioApiHost() . '/2010-04-01/Accounts/' . rawurlencode($creds['account_sid']) . '/Messages.json';
    $result = twilioHttpPost($url, $postFields, $creds['auth_user'], $creds['auth_pass']);
    $response = $result['response'];
    $httpCode = (int) $result['http_code'];

    $data = json_decode($response, true);
    if (!is_array($data)) {
        $data = [];
    }
    if ($httpCode >= 400) {
        throw new Exception(formatTwilioWhatsAppError($data));
    }

    $status = strtolower((string) ($data['status'] ?? ''));
    if (in_array($status, ['failed', 'undelivered', 'canceled'], true)) {
        throw new Exception('WhatsApp message could not be delivered to this number.');
    }

    return $data;
}

function sendWhatsAppOtpMessage($phone, $otp) {
    $contentSid = trim((string) getSetting('twilio_whatsapp_content_sid'));
    $useTemplate = trim((string) getSetting('twilio_whatsapp_use_template')) === '1';

    if ($contentSid !== '' && $useTemplate) {
        $creds = twilioGetCredentials();
        $from = trim((string) getSetting('twilio_whatsapp_from'));
        $to = 'whatsapp:' . normalizePhoneE164($phone);

        if (!str_starts_with($from, 'whatsapp:')) {
            $fromDigits = preg_replace('/\D+/', '', $from);
            $from = 'whatsapp:+' . $fromDigits;
        }

        $postFields = [
            'To' => $to,
            'From' => $from,
            'ContentSid' => $contentSid,
            'ContentVariables' => json_encode([
                '1' => (string) $otp,
                '2' => '10 minutes',
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ];

        $url = 'https://' . twilioApiHost() . '/2010-04-01/Accounts/' . rawurlencode($creds['account_sid']) . '/Messages.json';
        $result = twilioHttpPost($url, $postFields, $creds['auth_user'], $creds['auth_pass']);
        $data = json_decode($result['response'], true);
        if (!is_array($data)) {
            $data = [];
        }
        if ((int) $result['http_code'] >= 400) {
            throw new Exception(formatTwilioWhatsAppError($data));
        }

        return $data;
    }

    $siteName = getSetting('site_name') ?: 'The World Journey';
    return sendWhatsAppTextMessage(
        $phone,
        $siteName . ' verification code: ' . $otp . '. Valid for 10 minutes. Do not share this code.'
    );
}

/**
 * HTTP JSON helper for Fast2SMS APIs.
 */
function fast2smsHttpJson($method, $path, array $payload = []) {
    $apiKey = trim((string) getSetting('fast2sms_api_key'));
    if ($apiKey === '') {
        throw new Exception('SMS OTP is not configured. Please contact support.');
    }

    $url = 'https://www.fast2sms.com' . $path;
    if (strtoupper($method) === 'GET' && !empty($payload)) {
        $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($payload);
    }

    $headers = [
        'Authorization: ' . $apiKey,
        'Accept: application/json',
    ];

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_NOSIGNAL => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
        ];
        if (strtoupper($method) === 'POST') {
            $headers[] = 'Content-Type: application/json';
            $options[CURLOPT_HTTPHEADER] = $headers;
            $options[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        if (defined('CURL_IPRESOLVE_V4')) {
            $options[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;
        }
        curl_setopt_array($ch, $options);
        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new Exception('SMS gateway error: ' . ($curlError !== '' ? $curlError : 'Unable to reach Fast2SMS'));
        }

        return ['response' => $response, 'http_code' => $httpCode];
    }

    $contextHeaders = implode("\r\n", $headers);
    $opts = [
        'http' => [
            'method' => strtoupper($method),
            'header' => $contextHeaders,
            'timeout' => 30,
            'ignore_errors' => true,
        ],
    ];
    if (strtoupper($method) === 'POST') {
        $opts['http']['header'] .= "\r\nContent-Type: application/json";
        $opts['http']['content'] = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
    $response = @file_get_contents($url, false, stream_context_create($opts));
    if ($response === false) {
        throw new Exception('SMS gateway error: Unable to reach Fast2SMS');
    }
    $httpCode = 0;
    if (!empty($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
        $httpCode = (int) $m[1];
    }
    return ['response' => $response, 'http_code' => $httpCode];
}

/**
 * Send OTP SMS via Fast2SMS.
 * Uses OTP API when otp_id is set; otherwise Quick SMS (route=q).
 * @see https://docs.fast2sms.com/reference/send-otp
 * @see https://docs.fast2sms.com/reference/authorization
 */
function sendFast2SmsOtpMessage($phone, $otp) {
    $mobile = phoneToFast2SmsMobile($phone);
    if ($mobile === '' || !preg_match('/^[6-9]\d{9}$/', $mobile)) {
        throw new Exception('Please enter a valid 10-digit Indian mobile number');
    }

    $otpId = trim((string) getSetting('fast2sms_otp_id'));
    $siteName = getSetting('site_name') ?: 'The World Journey';

    if ($otpId !== '') {
        $result = fast2smsHttpJson('POST', '/dev/otp/send', [
            'mobile' => $mobile,
            'otp_id' => $otpId,
            'otp' => (string) $otp,
            'otp_length' => strlen((string) $otp),
            'otp_expiry' => 10,
        ]);
    } else {
        $message = $siteName . ' verification code: ' . $otp . '. Valid for 10 minutes. Do not share this code.';
        $result = fast2smsHttpJson('GET', '/dev/bulkV2', [
            'route' => 'q',
            'message' => $message,
            'numbers' => $mobile,
            'flash' => '0',
        ]);
    }

    $data = json_decode($result['response'], true);
    if (!is_array($data)) {
        $data = [];
    }

    $ok = !empty($data['return']) || ((int) ($data['status_code'] ?? 0) === 200);
    if ((int) $result['http_code'] >= 400 || !$ok) {
        $msg = (string) ($data['message'] ?? '');
        if (is_array($data['message'] ?? null)) {
            $msg = implode(' ', $data['message']);
        }
        if ($msg === '') {
            $msg = 'Unable to send SMS OTP right now. Please try again.';
        }
        throw new Exception($msg);
    }

    return $data;
}

/**
 * Deliver OTP: prefer Fast2SMS SMS, fall back to Twilio WhatsApp.
 */
function sendMobileOtpMessage($phone, $otp) {
    $errors = [];

    if (fast2smsIsConfigured()) {
        try {
            $data = sendFast2SmsOtpMessage($phone, $otp);
            otpSendLog('fast2sms', $phone, 'sent', 'response=' . json_encode($data, JSON_UNESCAPED_SLASHES));
            return $data;
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
            otpSendLog('fast2sms', $phone, 'failed', 'error=' . $e->getMessage());
            if (!twilioIsConfigured()) {
                throw $e;
            }
        }
    }

    if (twilioIsConfigured()) {
        try {
            $data = sendWhatsAppOtpMessage($phone, $otp);
            otpSendLog('twilio_whatsapp', $phone, 'sent', 'sid=' . (string) ($data['sid'] ?? ''));
            return $data;
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
            otpSendLog('twilio_whatsapp', $phone, 'failed', 'error=' . $e->getMessage());
        }
    }

    throw new Exception($errors[0] ?? 'Mobile OTP is not configured yet');
}

function sendWhatsAppMediaMessage($phone, $body, $mediaUrl) {
    $creds = twilioGetCredentials();
    $from = trim((string) getSetting('twilio_whatsapp_from'));

    if ($creds['account_sid'] === '' || $creds['auth_user'] === '' || $creds['auth_pass'] === '' || $from === '') {
        throw new Exception('WhatsApp is not configured. Please contact support.');
    }

    $to = 'whatsapp:' . normalizePhoneE164($phone);
    $mediaUrl = trim((string) $mediaUrl);

    if ($mediaUrl === '' || !preg_match('#^https://#i', $mediaUrl)) {
        throw new Exception('A public HTTPS media URL is required for WhatsApp delivery.');
    }

    if (!str_starts_with($from, 'whatsapp:')) {
        $fromDigits = preg_replace('/\D+/', '', $from);
        $from = 'whatsapp:+' . $fromDigits;
    }

    $postFields = [
        'To' => $to,
        'From' => $from,
        'Body' => trim((string) $body),
        'MediaUrl' => $mediaUrl,
    ];

    $url = 'https://' . twilioApiHost() . '/2010-04-01/Accounts/' . rawurlencode($creds['account_sid']) . '/Messages.json';
    $result = twilioHttpPost($url, $postFields, $creds['auth_user'], $creds['auth_pass']);
    $response = $result['response'];
    $httpCode = (int) $result['http_code'];

    $data = json_decode($response, true);
    if (!is_array($data)) {
        $data = [];
    }
    if ($httpCode >= 400) {
        throw new Exception(formatTwilioWhatsAppError($data));
    }

    $status = strtolower((string) ($data['status'] ?? ''));
    if (in_array($status, ['failed', 'undelivered', 'canceled'], true)) {
        throw new Exception('WhatsApp message could not be delivered to this number.');
    }

    return $data;
}

function createOtpForPhone($phone, $purpose = 'login', $excludeUserId = 0) {
    global $db;
    ensureWhatsAppOtpSchema();

    $purpose = in_array($purpose, ['login', 'register', 'update_phone'], true) ? $purpose : 'login';
    $normalized = normalizePhoneE164($phone);
    if ($normalized === '' || strlen(preg_replace('/\D+/', '', $normalized)) < 10) {
        throw new Exception('Please enter a valid mobile number');
    }

    if (!mobileOtpIsConfigured()) {
        throw new Exception('Mobile OTP login is not configured yet');
    }

    if ($purpose === 'register' && phoneBelongsToAnotherUser($normalized)) {
        throw new Exception('This mobile number is already registered. Please login instead.');
    }

    if ($purpose === 'update_phone' && phoneBelongsToAnotherUser($normalized, $excludeUserId)) {
        throw new Exception('This mobile number is already linked to another account');
    }

    $recent = $db->fetch(
        "SELECT COUNT(*) AS total FROM whatsapp_login_otps
         WHERE phone = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)",
        [$normalized]
    );
    if ((int) ($recent['total'] ?? 0) >= 5) {
        throw new Exception('Too many OTP requests. Please try again after 15 minutes.');
    }

    $otp = str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);
    $otpHash = password_hash($otp, PASSWORD_DEFAULT);
    $expiresAt = date('Y-m-d H:i:s', time() + 600);

    $db->execute(
        'INSERT INTO whatsapp_login_otps (phone, otp_hash, expires_at) VALUES (?, ?, ?)',
        [$normalized, $otpHash, $expiresAt]
    );
    $otpId = (int) $db->lastInsertId();

    try {
        sendMobileOtpMessage($normalized, $otp);
    } catch (Exception $e) {
        if ($otpId > 0) {
            $db->execute('DELETE FROM whatsapp_login_otps WHERE id = ?', [$otpId]);
        }
        throw $e;
    }

    return [
        'phone' => $normalized,
        'expires_at' => $expiresAt,
    ];
}

function verifyOtpCodeOnly($phone, $otp) {
    global $db;
    ensureWhatsAppOtpSchema();

    $normalized = normalizePhoneE164($phone);
    $otp = trim((string) $otp);

    $otp = str_pad($otp, 5, '0', STR_PAD_LEFT);
    if ($normalized === '' || !preg_match('/^\d{5}$/', $otp)) {
        throw new Exception('Invalid phone number or OTP');
    }

    $record = $db->fetch(
        "SELECT * FROM whatsapp_login_otps
         WHERE phone = ? AND verified = 0
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
            'UPDATE whatsapp_login_otps SET attempts = attempts + 1 WHERE id = ?',
            [(int) $record['id']]
        );
        throw new Exception('Invalid OTP. Please try again.');
    }

    $db->execute(
        'UPDATE whatsapp_login_otps SET verified = 1 WHERE id = ?',
        [(int) $record['id']]
    );

    return $normalized;
}

function verifyOtpForPhone($phone, $otp) {
    $normalized = verifyOtpCodeOnly($phone, $otp);
    return findOrCreateUserByPhone($normalized);
}

<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/whatsapp_otp_helpers.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$phone = trim((string) ($payload['phone'] ?? ''));
$otp = trim((string) ($payload['otp'] ?? ''));
$purpose = trim((string) ($payload['purpose'] ?? 'login'));
$redirect = trim((string) ($payload['redirect'] ?? ($_GET['redirect'] ?? BASE_URL)));

try {
    if ($purpose === 'register') {
        $normalized = verifyOtpCodeOnly($phone, $otp);

        if (phoneBelongsToAnotherUser($normalized)) {
            throw new Exception('This WhatsApp number is already registered. Please login instead.');
        }

        setVerifiedPhoneSession($normalized, 'register');

        echo json_encode([
            'success' => true,
            'message' => 'WhatsApp number verified. You can now create your account.',
            'phone' => $normalized,
            'purpose' => 'register',
        ]);
        exit;
    }

    if ($purpose === 'update_phone') {
        if (!isUserLoggedIn()) {
            throw new Exception('Login required to update WhatsApp number');
        }

        $normalized = verifyOtpCodeOnly($phone, $otp);
        $updatedPhone = updateUserWhatsAppPhone((int) $_SESSION['user_id'], $normalized);

        echo json_encode([
            'success' => true,
            'message' => 'WhatsApp number updated successfully',
            'phone' => $updatedPhone,
            'phone_display' => formatPhoneDisplay($updatedPhone),
            'purpose' => 'update_phone',
        ]);
        exit;
    }

    $user = verifyOtpForPhone($phone, $otp);
    establishUserSession($user);

    if ($redirect === '' || strpos($redirect, '//') !== false || preg_match('#/api/?$#i', $redirect)) {
        $redirect = BASE_URL;
    } elseif (strpos($redirect, BASE_URL) !== 0 && strpos($redirect, '/') !== 0) {
        $redirect = BASE_URL;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'redirect_url' => $redirect,
        'purpose' => 'login',
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

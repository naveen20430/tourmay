<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/email_otp_helpers.php';

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

$email = trim((string) ($payload['email'] ?? ''));
$otp = trim((string) ($payload['otp'] ?? ''));
$purpose = trim((string) ($payload['purpose'] ?? 'login'));
$redirect = trim((string) ($payload['redirect'] ?? ($_GET['redirect'] ?? BASE_URL)));

try {
    if ($purpose === 'register') {
        $normalized = verifyEmailOtpCodeOnly($email, $otp);

        if (emailBelongsToAnotherUser($normalized)) {
            throw new Exception('This email is already registered. Please login instead.');
        }

        setVerifiedEmailSession($normalized, 'register');

        echo json_encode([
            'success' => true,
            'message' => 'Email verified. You can now create your account.',
            'email' => $normalized,
            'purpose' => 'register',
        ]);
        exit;
    }

    if ($purpose === 'checkout') {
        $normalized = verifyEmailOtpCodeOnly($email, $otp);
        $user = findUserByEmail($normalized);
        $loggedIn = false;

        if ($user) {
            establishUserSession($user);
            $loggedIn = true;
        } else {
            setVerifiedEmailSession($normalized, 'checkout');
        }

        echo json_encode([
            'success' => true,
            'message' => $loggedIn
                ? 'Email verified. You are signed in and can continue checkout.'
                : 'Email verified. You can continue checkout.',
            'email' => $normalized,
            'logged_in' => $loggedIn,
            'purpose' => 'checkout',
        ]);
        exit;
    }

    $user = verifyOtpForEmailLogin($email, $otp);
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

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
$purpose = trim((string) ($payload['purpose'] ?? 'login'));
$excludeUserId = isUserLoggedIn() ? (int) $_SESSION['user_id'] : 0;

if ($purpose === 'update_phone' && !isUserLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Login required to update WhatsApp number']);
    exit;
}

try {
    $result = createOtpForPhone($phone, $purpose, $excludeUserId);
    echo json_encode([
        'success' => true,
        'message' => fast2smsIsConfigured() ? 'OTP sent by SMS' : 'OTP sent on WhatsApp',
        'phone' => $result['phone'],
        'expires_at' => $result['expires_at'],
        'purpose' => $purpose,
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

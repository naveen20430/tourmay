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
$purpose = trim((string) ($payload['purpose'] ?? 'login'));

try {
    $result = createOtpForEmail($email, $purpose);
    echo json_encode([
        'success' => true,
        'message' => 'OTP sent to your email',
        'email' => $result['email'],
        'expires_at' => $result['expires_at'],
        'purpose' => $purpose,
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

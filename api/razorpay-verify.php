<?php
require_once '../config/config.php';
require_once '../includes/checkout_helpers.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isUserLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Login required']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$invoiceNumber = trim((string) ($payload['invoice_number'] ?? ''));
$paymentId = trim((string) ($payload['razorpay_payment_id'] ?? ''));
$orderId = trim((string) ($payload['razorpay_order_id'] ?? ''));
$signature = trim((string) ($payload['razorpay_signature'] ?? ''));

try {
    if ($invoiceNumber === '' || $paymentId === '' || $orderId === '' || $signature === '') {
        throw new Exception('Invalid payment response');
    }

    $invoice = getInvoiceByNumber($invoiceNumber);
    if (!$invoice || !userCanViewInvoice($invoice)) {
        throw new Exception('Invoice not found');
    }

    verifyRazorpayPayment($invoice, $paymentId, $orderId, $signature);

    echo json_encode([
        'success' => true,
        'message' => 'Payment successful',
        'redirect_url' => invoiceUrl($invoice['invoice_number']),
    ]);
} catch (Exception $e) {
    if (function_exists('twjCheckoutLog')) {
        twjCheckoutLog('razorpay_verify_fail', [
            'force' => true,
            'invoice_number' => $invoiceNumber ?? '',
            'message' => $e->getMessage(),
        ]);
    }
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

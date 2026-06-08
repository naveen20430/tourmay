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
if ($invoiceNumber === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invoice number is required']);
    exit;
}

try {
    $invoice = getInvoiceByNumber($invoiceNumber);
    if (!$invoice || !userCanViewInvoice($invoice)) {
        throw new Exception('Invoice not found');
    }
    if (($invoice['payment_method'] ?? '') !== 'razorpay') {
        throw new Exception('This invoice is not payable online');
    }
    if (($invoice['payment_status'] ?? '') === 'paid') {
        throw new Exception('This invoice is already paid');
    }
    if (!razorpayIsConfigured()) {
        throw new Exception('Razorpay is not configured');
    }

    $order = !empty($invoice['razorpay_order_id'])
        ? ['id' => $invoice['razorpay_order_id'], 'amount' => (int) round(((float) $invoice['total_amount']) * 100), 'currency' => 'INR']
        : createRazorpayOrderForInvoice($invoice);

    echo json_encode([
        'success' => true,
        'key' => trim((string) getSetting('razorpay_key_id')),
        'order_id' => $order['id'],
        'amount' => (int) ($order['amount'] ?? round(((float) $invoice['total_amount']) * 100)),
        'currency' => $order['currency'] ?? 'INR',
        'invoice_number' => $invoice['invoice_number'],
        'site_name' => getSetting('site_name') ?: 'The World Journey',
        'customer' => [
            'name' => $invoice['guest_name'],
            'email' => $invoice['guest_email'],
            'phone' => $invoice['guest_phone'],
        ],
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

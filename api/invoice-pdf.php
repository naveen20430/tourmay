<?php
require_once '../config/config.php';
require_once '../includes/checkout_helpers.php';
require_once '../includes/invoice_pdf_helpers.php';

$invoiceNumber = trim((string) ($_GET['invoice'] ?? ''));
if ($invoiceNumber === '') {
    http_response_code(400);
    echo 'Invoice required';
    exit;
}

$invoice = getInvoiceByNumber($invoiceNumber);
if (!$invoice) {
    http_response_code(404);
    echo 'Invoice not found';
    exit;
}

try {
    $bookings = getInvoiceBookings((int) $invoice['id']);
    $pdf = generateInvoicePdfFile($invoice, $bookings);

    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . invoicePdfFilename($invoiceNumber) . '"');
    header('Content-Length: ' . filesize($pdf['path']));
    header('Cache-Control: public, max-age=3600');
    readfile($pdf['path']);
} catch (Exception $e) {
    http_response_code(500);
    echo 'Unable to generate PDF';
}

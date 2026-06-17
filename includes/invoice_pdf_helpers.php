<?php
/**
 * Invoice PDF generation and WhatsApp delivery
 */

require_once __DIR__ . '/invoice_pdf_builder.php';
require_once __DIR__ . '/whatsapp_otp_helpers.php';
require_once __DIR__ . '/checkout_helpers.php';

function invoicePdfStorageDir() {
    $dir = UPLOAD_PATH . 'invoices/';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return $dir;
}

function invoicePdfFilename($invoiceNumber) {
    $safe = preg_replace('/[^A-Za-z0-9\-_]/', '', (string) $invoiceNumber);
    return $safe . '.pdf';
}

function invoicePdfFilePath($invoiceNumber) {
    return invoicePdfStorageDir() . invoicePdfFilename($invoiceNumber);
}

function invoicePdfPublicUrl($invoiceNumber) {
    return UPLOAD_URL . 'invoices/' . rawurlencode(invoicePdfFilename($invoiceNumber));
}

function invoicePdfDeliveryUrl($invoiceNumber) {
    return BASE_URL . 'api/invoice-pdf.php?invoice=' . rawurlencode((string) $invoiceNumber);
}

function formatPdfMoney($amount) {
    return 'INR ' . number_format((float) $amount, 0);
}

function generateInvoicePdfFile(array $invoice, array $bookings) {
    $siteName = getSetting('site_name') ?: 'The World Journey';
    $tagline = getSetting('site_tagline') ?: 'Travel & Tour Booking Agency';
    $address = trim((string) (getSetting('site_address') ?: ''));
    $contactPhone = trim((string) (getSetting('contact_phone') ?: getSetting('site_phone') ?: ''));
    $contactEmail = trim((string) (getSetting('contact_email') ?: getSetting('site_email') ?: ''));
    $logoPath = BASE_PATH . 'assets/images/logonew.png';

    $paymentStatus = (string) ($invoice['payment_status'] ?? 'pending');
    $paymentMethod = (string) ($invoice['payment_method'] ?? 'cash');
    $methodLabel = $paymentMethod === 'razorpay' ? 'Razorpay (Online)' : 'Cash / Manual';

    $pdf = new InvoicePdfBuilder();
    $pdf->drawHeader(
        $siteName,
        $tagline,
        $address,
        (string) ($invoice['invoice_number'] ?? ''),
        date('M d, Y', strtotime($invoice['created_at'] ?? 'now')),
        $paymentStatus,
        is_file($logoPath) ? $logoPath : null
    );

    $pdf->drawMetaCards([
        [
            'label' => 'Bill To',
            'title' => (string) ($invoice['guest_name'] ?? ''),
            'lines' => [
                (string) ($invoice['guest_email'] ?? ''),
                (string) ($invoice['guest_phone'] ?? ''),
            ],
        ],
        [
            'label' => 'Payment Method',
            'title' => $methodLabel,
            'lines' => [
                $paymentMethod === 'razorpay'
                    ? 'Secure online payment via Razorpay'
                    : 'Manual / cash payment on arrival',
            ],
        ],
        [
            'label' => 'Amount Due',
            'title' => formatPdfMoney($invoice['total_amount'] ?? 0),
            'lines' => $contactPhone !== '' ? ['Contact: ' . $contactPhone] : [],
        ],
    ]);

    if ($paymentMethod === 'cash' && $paymentStatus !== 'paid') {
        $cashNote = trim((string) getSetting('cash_payment_note'));
        if ($cashNote === '') {
            $cashNote = 'Cash payment pending. Please pay at our office or to the assigned tour guide before travel.';
        }
        $pdf->drawAlert('Cash payment pending. ' . $cashNote, 'warning');
    }

    if (!empty($invoice['special_requirements'])) {
        $pdf->drawAlert('Special requirements: ' . $invoice['special_requirements'], 'info');
    }

    $tableRows = [];
    foreach ($bookings as $booking) {
        $lineAmount = (float) ($booking['total_with_cab'] ?: ($booking['total_amount'] + (float) ($booking['cab_price'] ?? 0)));
        $tableRows[] = [
            'cells' => [
                (string) ($booking['tour_title'] ?? 'Tour'),
                date('M d, Y', strtotime($booking['tour_date'] ?? 'now')),
                (string) (int) ($booking['number_of_people'] ?? 0),
                (string) ($booking['booking_number'] ?? ''),
                formatPdfMoney($lineAmount),
            ],
            'sub' => !empty($booking['destination_name']) ? (string) $booking['destination_name'] : '',
        ];
    }

    $pdf->drawTable(
        ['Tour', 'Travel Date', 'People', 'Booking #', 'Amount'],
        $tableRows,
        [168, 78, 42, 88, 72]
    );

    $pdf->drawTotals(
        (float) ($invoice['subtotal'] ?? 0),
        (float) ($invoice['cab_total'] ?? 0),
        (float) ($invoice['total_amount'] ?? 0)
    );

    $pdf->drawSectionTitle('Tour Itinerary');

    foreach ($bookings as $booking) {
        $itinerary = getInvoiceTourItineraryDays($booking);
        $pdf->drawItineraryTour(
            (string) ($booking['tour_title'] ?? 'Tour'),
            (string) ($booking['short_description'] ?? ''),
            $itinerary
        );
    }

    $footerParts = ['Thank you for booking with ' . $siteName . '.'];
    if ($contactEmail !== '') {
        $footerParts[] = $contactEmail;
    }
    $openingHours = trim((string) getSetting('opening_hours'));
    if ($openingHours !== '') {
        $footerParts[] = $openingHours;
    }
    $pdf->drawFooter(implode('  |  ', $footerParts));

    $path = invoicePdfFilePath($invoice['invoice_number']);
    if (!$pdf->save($path)) {
        throw new Exception('Unable to generate invoice PDF');
    }

    return [
        'path' => $path,
        'url' => invoicePdfPublicUrl($invoice['invoice_number']),
    ];
}

function getWhatsAppPhoneForInvoice(array $invoice) {
    global $db;

    if (!empty($invoice['user_id'])) {
        $user = $db->fetch('SELECT phone FROM users WHERE id = ?', [(int) $invoice['user_id']]);
        if ($user && !empty($user['phone'])) {
            return normalizePhoneE164($user['phone']);
        }
    }

    $guestPhone = trim((string) ($invoice['guest_phone'] ?? ''));
    if ($guestPhone !== '') {
        return normalizePhoneE164($guestPhone);
    }

    return '';
}

function sendInvoicePdfViaWhatsApp($invoiceNumber) {
    if (!twilioIsConfigured()) {
        return false;
    }

    $invoice = getInvoiceByNumber($invoiceNumber);
    if (!$invoice) {
        throw new Exception('Invoice not found');
    }

    $phone = getWhatsAppPhoneForInvoice($invoice);
    if ($phone === '') {
        throw new Exception('No WhatsApp number found for this booking');
    }

    $bookings = getInvoiceBookings((int) $invoice['id']);
    $pdf = generateInvoicePdfFile($invoice, $bookings);

    $siteName = getSetting('site_name') ?: 'The World Journey';
    $pdfUrl = invoicePdfDeliveryUrl($invoice['invoice_number']);
    $message = $siteName . ' booking confirmed. Invoice ' . $invoice['invoice_number']
        . ' with tour itinerary is attached. Total: ' . formatPdfMoney($invoice['total_amount']);

    try {
        sendWhatsAppMediaMessage($phone, $message, $pdfUrl);
    } catch (Exception $e) {
        sendWhatsAppTextMessage($phone, $message . ' Download PDF: ' . $pdfUrl);
    }

    return true;
}

function notifyInvoiceViaWhatsApp($invoiceNumber) {
    try {
        return sendInvoicePdfViaWhatsApp($invoiceNumber);
    } catch (Exception $e) {
        error_log('WhatsApp invoice PDF failed [' . $invoiceNumber . ']: ' . $e->getMessage());
        return false;
    }
}

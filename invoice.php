<?php
require_once 'config/config.php';
require_once 'includes/checkout_helpers.php';
require_once 'includes/invoice_pdf_helpers.php';

$invoiceNumber = trim((string) ($_GET['number'] ?? ''));
if ($invoiceNumber === '') {
    header('Location: ' . navUrl('cart'));
    exit;
}

$invoice = getInvoiceByNumber($invoiceNumber);
if (!$invoice) {
    http_response_code(404);
    $page_title = 'Invoice Not Found - ' . getSetting('site_name');
    include 'includes/header.php';
    echo '<section class="page-header"><div class="container"><h1>Invoice Not Found</h1></div></section>';
    echo '<section style="padding:60px 0;"><div class="container"><div class="alert alert-danger">This invoice does not exist.</div></div></section>';
    include 'includes/footer.php';
    exit;
}

$bookings = getInvoiceBookings((int) $invoice['id']);
$page_title = 'Invoice ' . htmlspecialchars($invoice['invoice_number']) . ' - ' . getSetting('site_name');
$current_page = 'invoice';
$siteName = getSetting('site_name') ?: 'The World Journey';
$siteTagline = getSetting('site_tagline') ?: 'Travel & Tour Booking Agency';
$logoUrl = BASE_URL . 'assets/images/logonew.png';
$pdfUrl = invoicePdfDeliveryUrl($invoice['invoice_number']);
$extra_css = cssWithCache('assets/css/invoice-page.css');

include 'includes/header.php';

$paymentStatus = $invoice['payment_status'] ?? 'pending';
$statusClass = $paymentStatus === 'paid' ? 'status-paid' : ($paymentStatus === 'failed' ? 'status-failed' : 'status-pending');
$paymentLabel = ucfirst($paymentStatus);
$methodLabel = ($invoice['payment_method'] ?? 'cash') === 'razorpay' ? 'Razorpay (Online)' : 'Cash / Manual';
?>

<section class="invoice-page-header">
    <div class="container">
        <h1>Booking Invoice</h1>
        <ul class="travhub-breadcrumb list-unstyled">
            <li><a href="<?php echo navUrl('home'); ?>">Home</a></li>
            <li><?php echo htmlspecialchars($invoice['invoice_number']); ?></li>
        </ul>
    </div>
</section>

<section class="invoice-wrap">
    <div class="container invoice-shell">
        <div class="invoice-card" id="invoicePrintArea">
            <div class="invoice-brand-bar">
                <div class="invoice-brand-inner">
                    <div class="invoice-brand-left">
                        <div class="invoice-logo-wrap">
                            <img src="<?php echo htmlspecialchars($logoUrl); ?>" alt="<?php echo htmlspecialchars($siteName); ?>" class="invoice-logo">
                        </div>
                        <div class="invoice-brand-text">
                            <h2><?php echo htmlspecialchars($siteName); ?></h2>
                            <p><?php echo htmlspecialchars($siteTagline); ?></p>
                            <?php if (getSetting('site_address')): ?>
                                <p class="inv-address"><?php echo htmlspecialchars(getSetting('site_address')); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="invoice-brand-right">
                        <div class="invoice-label">Tax Invoice</div>
                        <div class="invoice-number"><?php echo htmlspecialchars($invoice['invoice_number']); ?></div>
                        <div class="invoice-date">Issued: <?php echo formatDate($invoice['created_at']); ?></div>
                        <span class="status-badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($paymentLabel); ?></span>
                    </div>
                </div>
            </div>

            <div class="invoice-body">
                <div class="invoice-meta">
                    <div class="invoice-meta-card">
                        <span>Bill To</span>
                        <strong><?php echo htmlspecialchars($invoice['guest_name']); ?></strong>
                        <div class="sub"><?php echo htmlspecialchars($invoice['guest_email']); ?></div>
                        <div class="sub"><?php echo htmlspecialchars($invoice['guest_phone']); ?></div>
                        <?php if (!empty($invoice['gst_number'])): ?>
                            <div class="sub"><strong>GSTIN:</strong> <?php echo htmlspecialchars($invoice['gst_number']); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="invoice-meta-card">
                        <span>Payment Method</span>
                        <strong><?php echo htmlspecialchars($methodLabel); ?></strong>
                        <div class="sub"><?php echo ($invoice['payment_method'] ?? '') === 'razorpay' ? 'Secure online payment via Razorpay' : 'Manual / cash payment on arrival'; ?></div>
                    </div>
                    <div class="invoice-meta-card">
                        <span>Amount Due</span>
                        <strong><?php echo formatPriceINR((float) $invoice['total_amount']); ?></strong>
                        <div class="sub">Contact: <?php echo htmlspecialchars(getSetting('contact_phone') ?: getSetting('site_phone')); ?></div>
                    </div>
                </div>

                <?php if (($invoice['payment_method'] ?? '') === 'cash' && $paymentStatus !== 'paid'): ?>
                    <div class="invoice-alert invoice-alert-warning">
                        <strong>Cash payment pending.</strong>
                        <?php echo htmlspecialchars(getSetting('cash_payment_note') ?: 'Please pay at our office or to the assigned tour guide before travel. Keep this invoice for reference.'); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($invoice['special_requirements'])): ?>
                    <div class="invoice-alert invoice-alert-info">
                        <strong>Special requirements:</strong> <?php echo nl2br(htmlspecialchars($invoice['special_requirements'])); ?>
                    </div>
                <?php endif; ?>

                <div class="invoice-table-wrap">
                    <table class="invoice-table">
                        <thead>
                            <tr>
                                <th>Tour</th>
                                <th>Travel Date</th>
                                <th>People</th>
                                <th>Booking #</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bookings as $booking): ?>
                                <?php $lineAmount = (float) ($booking['total_with_cab'] ?: ($booking['total_amount'] + (float) ($booking['cab_price'] ?? 0))); ?>
                                <tr>
                                    <td>
                                        <div class="tour-name"><?php echo htmlspecialchars($booking['tour_title']); ?></div>
                                        <?php if (!empty($booking['destination_name'])): ?>
                                            <div class="tour-sub"><?php echo htmlspecialchars($booking['destination_name']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo formatDate($booking['tour_date']); ?></td>
                                    <td><?php echo (int) $booking['number_of_people']; ?></td>
                                    <td><?php echo htmlspecialchars($booking['booking_number']); ?></td>
                                    <td class="amount-cell"><?php echo formatPriceINR($lineAmount); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="invoice-total">
                    <div class="invoice-total-box">
                        <div class="invoice-total-head">Payment Summary</div>
                        <div class="invoice-total-body">
                            <div class="invoice-total-row"><span>Subtotal</span><span><?php echo formatPriceINR((float) $invoice['subtotal']); ?></span></div>
                            <?php if ((float) $invoice['cab_total'] > 0): ?>
                                <div class="invoice-total-row"><span>Cab charges</span><span><?php echo formatPriceINR((float) $invoice['cab_total']); ?></span></div>
                            <?php endif; ?>
                            <div class="invoice-total-row grand"><span>Total Payable</span><span><?php echo formatPriceINR((float) $invoice['total_amount']); ?></span></div>
                        </div>
                    </div>
                </div>

                <div class="itinerary-block">
                    <div class="itinerary-head">
                        <i class="fas fa-route"></i>
                        <h3>Tour Itinerary</h3>
                    </div>
                    <?php foreach ($bookings as $booking): ?>
                        <?php $itinerary = getInvoiceTourItineraryDays($booking); ?>
                        <div class="itinerary-tour">
                            <h4><?php echo htmlspecialchars($booking['tour_title']); ?></h4>
                            <?php if (!empty($booking['short_description'])): ?>
                                <p class="tour-desc"><?php echo htmlspecialchars($booking['short_description']); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($itinerary)): ?>
                                <?php foreach ($itinerary as $day): ?>
                                    <?php $isSection = !empty($day['is_section']); ?>
                                    <div class="itinerary-day<?php echo $isSection ? ' itinerary-day--section' : ''; ?>">
                                        <?php if (!$isSection): ?>
                                        <div class="itinerary-day-badge">
                                            Day
                                            <span><?php echo htmlspecialchars((string) ($day['day'] ?? '')); ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <div class="itinerary-day-content">
                                            <strong><?php echo htmlspecialchars((string) ($day['title'] ?? 'Schedule')); ?></strong>
                                            <?php if (!empty($day['description'])): ?>
                                                <p><?php echo nl2br(htmlspecialchars($day['description'])); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="tour-desc">Itinerary details will be shared by our team before departure.</p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="invoice-footer-note">
                    Thank you for booking with <?php echo htmlspecialchars($siteName); ?>.
                    <?php echo htmlspecialchars(getSetting('contact_email') ?: getSetting('site_email')); ?>
                    <?php if (getSetting('opening_hours')): ?> | <?php echo htmlspecialchars(getSetting('opening_hours')); ?><?php endif; ?>
                </div>
            </div>
        </div>

        <div class="invoice-actions">
            <a href="<?php echo htmlspecialchars($pdfUrl); ?>" class="btn btn-primary" target="_blank" rel="noopener"><i class="fas fa-file-pdf"></i> Download PDF</a>
            <button type="button" class="btn btn-outline-primary" onclick="window.print()"><i class="fas fa-print"></i> Print Invoice</button>
            <?php if (($invoice['payment_method'] ?? '') === 'razorpay' && $paymentStatus !== 'paid'): ?>
                <a href="<?php echo payInvoiceUrl($invoice['invoice_number']); ?>" class="btn btn-primary"><i class="fas fa-lock"></i> Pay with Razorpay</a>
            <?php endif; ?>
            <a href="<?php echo navUrl('tours'); ?>" class="btn btn-outline-secondary">Browse More Tours</a>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>

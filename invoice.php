<?php
require_once 'config/config.php';
require_once 'includes/checkout_helpers.php';

requireUserLogin();

$invoiceNumber = trim((string) ($_GET['number'] ?? ''));
if ($invoiceNumber === '') {
    header('Location: ' . navUrl('cart'));
    exit;
}

$invoice = getInvoiceByNumber($invoiceNumber);
if (!$invoice || !userCanViewInvoice($invoice)) {
    http_response_code(404);
    $page_title = 'Invoice Not Found - ' . getSetting('site_name');
    include 'includes/header.php';
    echo '<section class="page-header"><div class="container"><h1>Invoice Not Found</h1></div></section>';
    echo '<section style="padding:60px 0;"><div class="container"><div class="alert alert-danger">This invoice does not exist or you do not have access to view it.</div></div></section>';
    include 'includes/footer.php';
    exit;
}

$bookings = getInvoiceBookings((int) $invoice['id']);
$page_title = 'Invoice ' . htmlspecialchars($invoice['invoice_number']) . ' - ' . getSetting('site_name');
$current_page = 'invoice';
$siteName = getSetting('site_name') ?: 'The World Journey';
$siteTagline = getSetting('site_tagline') ?: 'Travel & Tour Booking Agency';
$logoUrl = BASE_URL . 'assets/images/logonew.png';

$extra_css = '<style>
:root{
  --inv-primary:#667eea;
  --inv-secondary:#764ba2;
  --inv-gradient:linear-gradient(135deg,#764ba2 0%,#667eea 100%);
  --inv-text:#0f172a;
  --inv-muted:#64748b;
  --inv-border:#e9ecef;
  --inv-soft:#f8f9ff;
}
.invoice-wrap{padding:60px 0;background:linear-gradient(180deg,#f8f9ff 0%,#ffffff 100%)}
.invoice-card{background:#fff;border-radius:20px;box-shadow:0 18px 50px rgba(118,75,162,.12);overflow:hidden;border:1px solid rgba(102,126,234,.08)}
.invoice-brand-bar{background:var(--inv-gradient);padding:28px 32px;color:#fff;position:relative;overflow:hidden}
.invoice-brand-bar::before{content:"";position:absolute;top:-40px;right:-40px;width:180px;height:180px;background:rgba(255,255,255,.08);border-radius:50%}
.invoice-brand-bar::after{content:"";position:absolute;bottom:-60px;left:-30px;width:220px;height:220px;background:rgba(255,255,255,.05);border-radius:50%}
.invoice-brand-inner{display:flex;justify-content:space-between;align-items:center;gap:24px;flex-wrap:wrap;position:relative;z-index:1}
.invoice-brand-left{display:flex;align-items:center;gap:18px;min-width:0}
.invoice-logo-wrap{background:rgba(255,255,255,.95);border-radius:16px;padding:10px 14px;box-shadow:0 8px 24px rgba(15,23,42,.12);flex-shrink:0}
.invoice-logo{width:88px;height:auto;display:block}
.invoice-brand-text h2{margin:0 0 4px;font-size:1.55rem;font-weight:800;color:#fff;line-height:1.2}
.invoice-brand-text p{margin:0;color:rgba(255,255,255,.88);font-size:.92rem}
.invoice-brand-right{text-align:right}
.invoice-label{font-size:.78rem;text-transform:uppercase;letter-spacing:.12em;font-weight:700;opacity:.85}
.invoice-number{font-size:1.35rem;font-weight:800;margin-top:4px}
.invoice-date{margin-top:6px;opacity:.9;font-size:.92rem}
.invoice-body{padding:28px 32px 32px}
.invoice-meta{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:16px;margin-bottom:24px}
.invoice-meta-card{background:var(--inv-soft);border:1px solid rgba(102,126,234,.12);border-radius:14px;padding:16px 18px;position:relative;overflow:hidden}
.invoice-meta-card::before{content:"";position:absolute;top:0;left:0;width:4px;height:100%;background:var(--inv-gradient)}
.invoice-meta-card span{display:block;font-size:.74rem;text-transform:uppercase;color:var(--inv-primary);font-weight:700;letter-spacing:.06em;margin-bottom:6px}
.invoice-meta-card strong{display:block;color:var(--inv-text);font-size:1rem;line-height:1.35}
.invoice-meta-card .sub{color:var(--inv-muted);font-size:.88rem;margin-top:4px;line-height:1.45}
.invoice-alert{border-radius:14px;padding:14px 16px;margin-bottom:20px;border:1px solid transparent}
.invoice-alert-warning{background:linear-gradient(135deg,#fff7ed 0%,#ffedd5 100%);border-color:#fdba74;color:#9a3412}
.invoice-alert-info{background:linear-gradient(135deg,#eef2ff 0%,#e0e7ff 100%);border-color:#a5b4fc;color:#3730a3}
.invoice-table-wrap{overflow:auto;border:1px solid var(--inv-border);border-radius:14px;margin-bottom:24px}
.invoice-table{width:100%;border-collapse:collapse;min-width:640px}
.invoice-table thead tr{background:var(--inv-gradient);color:#fff}
.invoice-table th{padding:14px 12px;text-align:left;font-size:.78rem;text-transform:uppercase;letter-spacing:.05em;font-weight:700}
.invoice-table td{padding:14px 12px;border-bottom:1px solid var(--inv-border);vertical-align:top;color:#334155}
.invoice-table tbody tr:nth-child(even){background:#fafbff}
.invoice-table tbody tr:last-child td{border-bottom:0}
.invoice-table .tour-name{font-weight:700;color:var(--inv-text)}
.invoice-table .tour-sub{color:var(--inv-muted);font-size:.86rem;margin-top:3px}
.invoice-table .amount-cell{font-weight:700;color:var(--inv-secondary);white-space:nowrap}
.invoice-total{display:flex;justify-content:flex-end;margin-bottom:28px}
.invoice-total-box{min-width:300px;border-radius:16px;overflow:hidden;border:1px solid rgba(102,126,234,.15);box-shadow:0 8px 24px rgba(102,126,234,.08)}
.invoice-total-head{background:var(--inv-gradient);color:#fff;padding:12px 18px;font-weight:700;text-transform:uppercase;font-size:.82rem;letter-spacing:.06em}
.invoice-total-body{padding:16px 18px;background:#fff}
.invoice-total-row{display:flex;justify-content:space-between;gap:16px;margin-bottom:8px;color:var(--inv-muted)}
.invoice-total-row.grand{font-size:1.15rem;font-weight:800;color:var(--inv-text);border-top:1px dashed rgba(102,126,234,.25);padding-top:12px;margin-top:10px;margin-bottom:0}
.invoice-total-row.grand span:last-child{color:var(--inv-secondary)}
.status-badge{display:inline-block;padding:7px 14px;border-radius:999px;font-size:.74rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;margin-top:10px}
.status-paid{background:#dcfce7;color:#166534}
.status-pending{background:#fef3c7;color:#92400e}
.status-failed{background:#fee2e2;color:#991b1b}
.itinerary-block{margin-top:8px}
.itinerary-head{display:flex;align-items:center;gap:10px;margin-bottom:18px}
.itinerary-head h3{margin:0;font-size:1.25rem;color:var(--inv-text)}
.itinerary-head i{color:var(--inv-primary)}
.itinerary-tour{margin-bottom:20px;padding:20px;border:1px solid rgba(102,126,234,.12);border-radius:16px;background:linear-gradient(180deg,#ffffff 0%,#f8f9ff 100%)}
.itinerary-tour h4{margin:0 0 8px;color:var(--inv-secondary);font-size:1.05rem}
.itinerary-day{display:grid;grid-template-columns:72px 1fr;gap:14px;padding:14px 0;border-top:1px dashed rgba(102,126,234,.18)}
.itinerary-day:first-of-type{border-top:0;padding-top:0}
.itinerary-day-badge{background:var(--inv-gradient);color:#fff;border-radius:12px;padding:10px 8px;text-align:center;font-size:.72rem;font-weight:800;line-height:1.25;text-transform:uppercase}
.itinerary-day-badge span{display:block;font-size:1rem;margin-top:2px}
.itinerary-day-content strong{display:block;color:var(--inv-text);margin-bottom:4px}
.itinerary-day-content p{margin:0;color:var(--inv-muted);font-size:.92rem;line-height:1.6}
.invoice-footer-note{margin-top:24px;padding-top:18px;border-top:1px solid var(--inv-border);color:var(--inv-muted);font-size:.88rem;text-align:center}
.invoice-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:24px;justify-content:center}
.invoice-actions .btn-primary{background:var(--inv-gradient)!important;border:0!important;box-shadow:0 8px 20px rgba(102,126,234,.28)!important}
.invoice-actions .btn-outline-primary{color:var(--inv-primary)!important;border-color:var(--inv-primary)!important}
@media (max-width:767px){
  .invoice-brand-bar,.invoice-body{padding:22px 18px}
  .invoice-brand-inner,.invoice-brand-right{text-align:left}
  .invoice-brand-right{width:100%}
  .itinerary-day{grid-template-columns:1fr}
}
@media print{
  .page-header,.invoice-actions,.main-header,.main-footer,.topbar-one{display:none!important}
  .invoice-wrap{padding:0;background:#fff}
  .invoice-card{box-shadow:none;border:1px solid #ddd}
  .invoice-brand-bar,.invoice-table thead tr,.invoice-total-head,.itinerary-day-badge{-webkit-print-color-adjust:exact;print-color-adjust:exact}
}
</style>';

include 'includes/header.php';

$paymentStatus = $invoice['payment_status'] ?? 'pending';
$statusClass = $paymentStatus === 'paid' ? 'status-paid' : ($paymentStatus === 'failed' ? 'status-failed' : 'status-pending');
$paymentLabel = ucfirst($paymentStatus);
$methodLabel = ($invoice['payment_method'] ?? 'cash') === 'razorpay' ? 'Razorpay (Online)' : 'Cash / Manual';
?>

<section class="page-header">
    <div class="container">
        <h1>Booking Invoice</h1>
        <ul class="travhub-breadcrumb list-unstyled">
            <li><a href="<?php echo navUrl('home'); ?>">Home</a></li>
            <li><a href="<?php echo navUrl('cart'); ?>">Cart</a></li>
            <li><?php echo htmlspecialchars($invoice['invoice_number']); ?></li>
        </ul>
    </div>
</section>

<section class="invoice-wrap">
    <div class="container">
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
                            <p style="margin-top:8px;font-size:.85rem;opacity:.92;">
                                <?php echo htmlspecialchars(getSetting('site_address') ?: ''); ?>
                            </p>
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
                    <?php $itinerary = decodeTourItinerary($booking['itinerary'] ?? ''); ?>
                    <div class="itinerary-tour">
                        <h4><?php echo htmlspecialchars($booking['tour_title']); ?></h4>
                        <?php if (!empty($booking['short_description'])): ?>
                            <p class="sub" style="margin-bottom:12px;color:var(--inv-muted);"><?php echo htmlspecialchars($booking['short_description']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($itinerary)): ?>
                            <?php foreach ($itinerary as $day): ?>
                                <div class="itinerary-day">
                                    <div class="itinerary-day-badge">
                                        Day
                                        <span><?php echo htmlspecialchars((string) ($day['day'] ?? '')); ?></span>
                                    </div>
                                    <div class="itinerary-day-content">
                                        <strong><?php echo htmlspecialchars((string) ($day['title'] ?? 'Schedule')); ?></strong>
                                        <?php if (!empty($day['description'])): ?>
                                            <p><?php echo nl2br(htmlspecialchars($day['description'])); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="sub" style="color:var(--inv-muted);">Itinerary details will be shared by our team before departure.</p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="invoice-footer-note">
                Thank you for booking with <?php echo htmlspecialchars($siteName); ?>.
                <?php echo htmlspecialchars(getSetting('contact_email') ?: getSetting('site_email')); ?>
                <?php if (getSetting('opening_hours')): ?> • <?php echo htmlspecialchars(getSetting('opening_hours')); ?><?php endif; ?>
            </div>
            </div>
        </div>

        <div class="invoice-actions">
            <button type="button" class="btn btn-outline-primary" onclick="window.print()"><i class="fas fa-print"></i> Print Invoice</button>
            <?php if (($invoice['payment_method'] ?? '') === 'razorpay' && $paymentStatus !== 'paid'): ?>
                <a href="<?php echo payInvoiceUrl($invoice['invoice_number']); ?>" class="btn btn-primary"><i class="fas fa-lock"></i> Pay with Razorpay</a>
            <?php endif; ?>
            <a href="<?php echo navUrl('tours'); ?>" class="btn btn-outline-secondary">Browse More Tours</a>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>

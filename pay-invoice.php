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
    header('Location: ' . navUrl('cart'));
    exit;
}

if (($invoice['payment_status'] ?? '') === 'paid') {
    header('Location: ' . invoiceUrl($invoice['invoice_number']));
    exit;
}

if (($invoice['payment_method'] ?? '') !== 'razorpay') {
    header('Location: ' . invoiceUrl($invoice['invoice_number']));
    exit;
}

if (!razorpayIsConfigured()) {
    $_SESSION['cart_flash'] = ['type' => 'error', 'message' => 'Online payment is not configured. Please contact support.'];
    header('Location: ' . invoiceUrl($invoice['invoice_number']));
    exit;
}

$page_title = 'Pay Invoice - ' . getSetting('site_name');
$current_page = 'pay-invoice';
$bookings = getInvoiceBookings((int) $invoice['id']);
$siteName = getSetting('site_name') ?: 'The World Journey';
$logoUrl = BASE_URL . 'assets/images/logonew.png';

$extra_css = '<style>
.pay-wrap{padding:60px 0;background:linear-gradient(180deg,#f8f9ff 0%,#ffffff 100%)}
.pay-card{background:#fff;border-radius:20px;box-shadow:0 18px 50px rgba(118,75,162,.12);max-width:760px;margin:0 auto;overflow:hidden;border:1px solid rgba(102,126,234,.08)}
.pay-brand{background:linear-gradient(135deg,#764ba2 0%,#667eea 100%);padding:22px 28px;color:#fff;display:flex;align-items:center;gap:16px}
.pay-brand img{width:72px;height:auto;background:#fff;border-radius:12px;padding:8px 10px}
.pay-brand h3{margin:0;font-size:1.2rem;font-weight:800;color:#fff}
.pay-brand p{margin:4px 0 0;opacity:.9;font-size:.88rem}
.pay-body{padding:28px}
.pay-summary{background:#f8f9ff;border:1px solid rgba(102,126,234,.12);border-radius:14px;padding:16px;margin-bottom:20px}
.pay-line{display:flex;justify-content:space-between;gap:12px;padding:8px 0;border-bottom:1px dashed rgba(102,126,234,.18)}
.pay-line:last-child{border-bottom:0}
.pay-total{font-size:1.1rem;font-weight:800;color:#764ba2;padding-top:12px}
.pay-body .btn-primary{background:linear-gradient(135deg,#764ba2 0%,#667eea 100%)!important;border:0!important;box-shadow:0 8px 20px rgba(102,126,234,.28)!important}
</style>';

include 'includes/header.php';
?>

<section class="page-header">
    <div class="container">
        <h1>Complete Payment</h1>
        <ul class="travhub-breadcrumb list-unstyled">
            <li><a href="<?php echo navUrl('home'); ?>">Home</a></li>
            <li><a href="<?php echo invoiceUrl($invoice['invoice_number']); ?>">Invoice</a></li>
            <li>Payment</li>
        </ul>
    </div>
</section>

<section class="pay-wrap">
    <div class="container">
        <div class="pay-card">
            <div class="pay-brand">
                <img src="<?php echo htmlspecialchars($logoUrl); ?>" alt="<?php echo htmlspecialchars($siteName); ?>">
                <div>
                    <h3>Complete Payment</h3>
                    <p>Invoice <?php echo htmlspecialchars($invoice['invoice_number']); ?> • <?php echo htmlspecialchars($siteName); ?></p>
                </div>
            </div>
            <div class="pay-body">
            <p class="cart-help" style="margin-bottom:18px;">Pay securely using Razorpay (UPI, cards, net banking, wallets).</p>

            <div class="pay-summary">
                <?php foreach ($bookings as $booking): ?>
                    <?php $lineAmount = (float) ($booking['total_with_cab'] ?: ($booking['total_amount'] + (float) ($booking['cab_price'] ?? 0))); ?>
                    <div class="pay-line">
                        <span><?php echo htmlspecialchars($booking['tour_title']); ?></span>
                        <strong><?php echo formatPriceINR($lineAmount); ?></strong>
                    </div>
                <?php endforeach; ?>
                <div class="pay-line pay-total">
                    <span>Total Payable</span>
                    <span><?php echo formatPriceINR((float) $invoice['total_amount']); ?></span>
                </div>
            </div>

            <button type="button" class="btn btn-primary w-100" id="razorpayPayBtn">
                <i class="fas fa-lock"></i> Pay <?php echo formatPriceINR((float) $invoice['total_amount']); ?> with Razorpay
            </button>
            <div class="cart-help" style="margin-top:12px;text-align:center;">You will receive a printable invoice with full tour itinerary after successful payment.</div>
            </div>
        </div>
    </div>
</section>

<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
(function() {
    var payBtn = document.getElementById('razorpayPayBtn');
    if (!payBtn) return;

    payBtn.addEventListener('click', function() {
        payBtn.disabled = true;
        payBtn.textContent = 'Preparing payment...';

        fetch('<?php echo BASE_URL; ?>api/razorpay-create-order.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ invoice_number: <?php echo json_encode($invoice['invoice_number']); ?> })
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (!data.success) {
                throw new Error(data.message || 'Unable to start payment');
            }

            var options = {
                key: data.key,
                amount: data.amount,
                currency: data.currency,
                name: data.site_name,
                description: 'Invoice ' + data.invoice_number,
                order_id: data.order_id,
                prefill: {
                    name: data.customer.name,
                    email: data.customer.email,
                    contact: data.customer.phone
                },
                theme: { color: '#667eea' },
                handler: function(response) {
                    fetch('<?php echo BASE_URL; ?>api/razorpay-verify.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({
                            invoice_number: data.invoice_number,
                            razorpay_payment_id: response.razorpay_payment_id,
                            razorpay_order_id: response.razorpay_order_id,
                            razorpay_signature: response.razorpay_signature
                        })
                    })
                    .then(function(res) { return res.json(); })
                    .then(function(result) {
                        if (!result.success) {
                            throw new Error(result.message || 'Payment verification failed');
                        }
                        window.location.href = result.redirect_url;
                    })
                    .catch(function(err) {
                        alert(err.message || 'Payment verification failed');
                        payBtn.disabled = false;
                        payBtn.innerHTML = '<i class="fas fa-lock"></i> Pay <?php echo formatPriceINR((float) $invoice['total_amount']); ?> with Razorpay';
                    });
                },
                modal: {
                    ondismiss: function() {
                        payBtn.disabled = false;
                        payBtn.innerHTML = '<i class="fas fa-lock"></i> Pay <?php echo formatPriceINR((float) $invoice['total_amount']); ?> with Razorpay';
                    }
                }
            };

            var rzp = new Razorpay(options);
            rzp.open();
        })
        .catch(function(err) {
            alert(err.message || 'Unable to start payment');
            payBtn.disabled = false;
            payBtn.innerHTML = '<i class="fas fa-lock"></i> Pay <?php echo formatPriceINR((float) $invoice['total_amount']); ?> with Razorpay';
        });
    });
})();
</script>

<?php include 'includes/footer.php'; ?>

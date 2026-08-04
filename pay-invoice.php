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
.pay-line{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;padding:8px 0;border-bottom:1px dashed rgba(102,126,234,.18)}
.pay-line:last-child{border-bottom:0}
.pay-line > span:last-child,
.pay-line > strong:last-child{text-align:right;white-space:nowrap;min-width:110px}
.pay-line-main{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;width:100%}
.pay-line-meta{font-size:.82rem;color:#64748b;margin-top:4px;text-align:right;width:100%}
.pay-item-title{font-weight:600;color:#0f172a;text-align:left;padding-right:12px}
.pay-sub{color:#475569;font-weight:500}
.pay-total{font-size:1.1rem;font-weight:800;color:#764ba2;padding-top:12px}
.pay-gst-note{margin:10px 0 0;font-size:.8rem;color:#64748b;text-align:right}
.pay-body .btn-primary{background:linear-gradient(135deg,#764ba2 0%,#667eea 100%)!important;border:0!important;box-shadow:0 8px 20px rgba(102,126,234,.28)!important}
</style>';

$gstRate = 0.05;
$invoiceTotal = (float) ($invoice['total_amount'] ?? 0);
$invoiceBase = round($invoiceTotal / (1 + $gstRate), 2);
$invoiceGst = round($invoiceTotal - $invoiceBase, 2);

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
                    <?php
                    $lineAmount = (float) ($booking['total_with_cab'] ?: ($booking['total_amount'] + (float) ($booking['cab_price'] ?? 0)));
                    $lineBase = round($lineAmount / (1 + $gstRate), 2);
                    $lineGst = round($lineAmount - $lineBase, 2);
                    ?>
                    <div class="pay-line" style="flex-direction:column;align-items:stretch;">
                        <div class="pay-line-main">
                            <span class="pay-item-title"><?php echo htmlspecialchars($booking['tour_title']); ?></span>
                            <strong><?php echo formatPriceINR($lineAmount); ?></strong>
                        </div>
                        <div class="pay-line-meta">
                            Price <?php echo formatPriceINR($lineBase); ?>
                            + GST 5% <?php echo formatPriceINR($lineGst); ?>
                            = Total <?php echo formatPriceINR($lineAmount); ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="pay-line pay-sub">
                    <span>Price (before GST)</span>
                    <span><?php echo formatPriceINR($invoiceBase); ?></span>
                </div>
                <div class="pay-line pay-sub">
                    <span>GST (5%)</span>
                    <span><?php echo formatPriceINR($invoiceGst); ?></span>
                </div>
                <div class="pay-line pay-total">
                    <span>Total Payable</span>
                    <span><?php echo formatPriceINR($invoiceTotal); ?></span>
                </div>
                <p class="pay-gst-note">Total Price = Price + 5% GST</p>
            </div>

            <button type="button" class="btn btn-primary w-100" id="razorpayPayBtn">
                <i class="fas fa-lock"></i> Pay <?php echo formatPriceINR($invoiceTotal); ?> with Razorpay
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

    var createOrderUrl = <?php echo json_encode(BASE_URL . 'api/razorpay-create-order.php'); ?>;
    var verifyUrl = <?php echo json_encode(BASE_URL . 'api/razorpay-verify.php'); ?>;
    var invoiceNumber = <?php echo json_encode($invoice['invoice_number']); ?>;
    var payLabel = <?php echo json_encode('Pay ' . formatPriceINR($invoiceTotal) . ' with Razorpay'); ?>;
    var debugOn = <?php echo TWJ_DEBUG ? 'true' : 'false'; ?>;

    function resetPayBtn() {
        payBtn.disabled = false;
        payBtn.innerHTML = '<i class="fas fa-lock"></i> ' + payLabel;
    }

    function parseJsonResponse(res) {
        return res.text().then(function(text) {
            var data = null;
            try {
                data = text ? JSON.parse(text) : null;
            } catch (err) {
                if (debugOn) {
                    console.error('Payment API non-JSON response', res.status, text);
                }
                throw new Error('Payment server returned an invalid response (HTTP ' + res.status + '). Check logs/checkout.log');
            }
            if (!res.ok || !data || data.success === false) {
                var msg = (data && data.message) ? data.message : ('Payment request failed (HTTP ' + res.status + ')');
                throw new Error(msg);
            }
            return data;
        });
    }

    payBtn.addEventListener('click', function() {
        if (typeof Razorpay === 'undefined') {
            alert('Razorpay checkout failed to load. Please disable ad-blockers and retry.');
            return;
        }

        payBtn.disabled = true;
        payBtn.textContent = 'Preparing payment...';

        fetch(createOrderUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
            body: JSON.stringify({ invoice_number: invoiceNumber })
        })
        .then(parseJsonResponse)
        .then(function(data) {
            if (debugOn) {
                console.log('Razorpay order ready', data.order_id, data.amount);
            }

            var options = {
                key: data.key,
                amount: data.amount,
                currency: data.currency,
                name: data.site_name,
                description: 'Invoice ' + data.invoice_number,
                order_id: data.order_id,
                prefill: {
                    name: (data.customer && data.customer.name) || '',
                    email: (data.customer && data.customer.email) || '',
                    contact: (data.customer && data.customer.phone) || ''
                },
                theme: { color: '#667eea' },
                handler: function(response) {
                    fetch(verifyUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
                        body: JSON.stringify({
                            invoice_number: data.invoice_number,
                            razorpay_payment_id: response.razorpay_payment_id,
                            razorpay_order_id: response.razorpay_order_id,
                            razorpay_signature: response.razorpay_signature
                        })
                    })
                    .then(parseJsonResponse)
                    .then(function(result) {
                        window.location.href = result.redirect_url;
                    })
                    .catch(function(err) {
                        alert(err.message || 'Payment verification failed');
                        resetPayBtn();
                    });
                },
                modal: {
                    ondismiss: function() {
                        resetPayBtn();
                    }
                }
            };

            var rzp = new Razorpay(options);
            rzp.on('payment.failed', function(resp) {
                var failMsg = (resp && resp.error && resp.error.description)
                    ? resp.error.description
                    : 'Payment failed. Please try again.';
                if (debugOn) {
                    console.error('Razorpay payment.failed', resp);
                }
                alert(failMsg);
                resetPayBtn();
            });
            rzp.open();
        })
        .catch(function(err) {
            alert(err.message || 'Unable to start payment');
            resetPayBtn();
        });
    });
})();
</script>

<?php include 'includes/footer.php'; ?>

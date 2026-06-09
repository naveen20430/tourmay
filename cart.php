<?php
require_once 'config/config.php';
require_once 'includes/checkout_helpers.php';

$current_page = 'cart';
$page_title = 'Cart - ' . getSetting('site_name');
$extra_css = cssWithCache('assets/css/checkout-page.css');

if (!isset($_SESSION['tour_cart']) || !is_array($_SESSION['tour_cart'])) {
    $_SESSION['tour_cart'] = [];
}

$cab_functionality_enabled = false;
$availableCabs = [];
try {
    if (file_exists('includes/cab_options.php')) {
        require_once 'includes/cab_options.php';
        $db->fetch("SELECT COUNT(*) as count FROM cab_types LIMIT 1");
        $cab_functionality_enabled = true;
        $cabOptions = new CabOptions($db);
        $availableCabs = $cabOptions->getCabOptionsForDropdown();
    }
} catch (Exception $e) {
    $cab_functionality_enabled = false;
    $availableCabs = [];
}

$flash = $_SESSION['cart_flash'] ?? null;
unset($_SESSION['cart_flash']);

$redirectTo = function ($default) {
    $returnUrl = $_POST['return_url'] ?? '';
    if (is_string($returnUrl) && $returnUrl !== '') {
        if (strpos($returnUrl, BASE_URL) === 0) {
            header('Location: ' . $returnUrl);
            exit;
        }
        if (strpos($returnUrl, '/') === 0 && strpos($returnUrl, '//') !== 0) {
            header('Location: ' . $returnUrl);
            exit;
        }
    }
    header('Location: ' . $default);
    exit;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if (in_array($action, ['add', 'update', 'remove', 'checkout'], true) && !isUserLoggedIn()) {
        $_SESSION['cart_flash'] = ['type' => 'error', 'message' => 'Please log in to manage your cart.'];
        $returnTo = $_POST['return_url'] ?? (navUrl('cart'));
        if (is_string($returnTo) && $returnTo !== '' && (strpos($returnTo, BASE_URL) === 0 || (strpos($returnTo, '/') === 0 && strpos($returnTo, '//') !== 0))) {
            header('Location: ' . loginUrl($returnTo));
        } else {
            header('Location: ' . loginUrl(navUrl('cart')));
        }
        exit;
    }

    if ($action === 'add') {
        $tourId = filter_var($_POST['tour_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$tourId) {
            $_SESSION['cart_flash'] = ['type' => 'error', 'message' => 'Invalid tour selection'];
            $redirectTo(navUrl('cart'));
        }

        $tour = $db->fetch("SELECT id, min_people, max_people FROM tours WHERE id = ? AND status = 'active'", [$tourId]);
        if (!$tour) {
            $_SESSION['cart_flash'] = ['type' => 'error', 'message' => 'Tour not found'];
            $redirectTo(navUrl('cart'));
        }

        $tourDate = trim((string)($_POST['tour_date'] ?? ''));
        if ($tourDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tourDate)) {
            $tourDate = '';
        }
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        if ($tourDate === '' || $tourDate <= date('Y-m-d')) {
            $tourDate = $tomorrow;
        }

        $people = trim((string)($_POST['people'] ?? ''));
        $peopleInt = null;
        if ($people !== '') {
            $peopleInt = filter_var($people, FILTER_VALIDATE_INT);
            if ($peopleInt === false) {
                $peopleInt = null;
            }
        }

        $cabType = trim((string)($_POST['cab_type'] ?? ''));
        if (!$cab_functionality_enabled) {
            $cabType = '';
        }

        $_SESSION['tour_cart'][(string)$tourId] = [
            'tour_date' => $tourDate,
            'people' => $peopleInt,
            'cab_type' => $cabType,
        ];

        $_SESSION['cart_flash'] = ['type' => 'success', 'message' => 'Added to cart'];
        $redirectTo(navUrl('cart'));
    }

    if ($action === 'remove') {
        $tourId = filter_var($_POST['tour_id'] ?? null, FILTER_VALIDATE_INT);
        if ($tourId) {
            unset($_SESSION['tour_cart'][(string)$tourId]);
        }
        $_SESSION['cart_flash'] = ['type' => 'success', 'message' => 'Removed from cart'];
        $redirectTo(navUrl('cart'));
    }

    if ($action === 'clear') {
        $_SESSION['tour_cart'] = [];
        $_SESSION['cart_flash'] = ['type' => 'success', 'message' => 'Cart cleared'];
        $redirectTo(navUrl('cart'));
    }

    if ($action === 'checkout') {
        $cartItems = $_SESSION['tour_cart'];
        if (empty($cartItems)) {
            $_SESSION['cart_flash'] = ['type' => 'error', 'message' => 'Your cart is empty'];
            $redirectTo(navUrl('cart'));
        }

        $guestName = trim((string)($_POST['guest_name'] ?? ''));
        $guestEmail = trim((string)($_POST['guest_email'] ?? ''));
        $guestPhone = trim((string)($_POST['guest_phone'] ?? ''));
        $specialRequirements = trim((string)($_POST['special_requirements'] ?? ''));
        $paymentMethod = trim((string)($_POST['payment_method'] ?? 'cash'));

        $errors = [];
        if ($guestName === '') $errors[] = 'Your name is required';
        if ($guestEmail === '' || !filter_var($guestEmail, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required';
        if ($guestPhone === '') $errors[] = 'Your phone number is required';
        if (!in_array($paymentMethod, ['cash', 'razorpay'], true)) {
            $errors[] = 'Please select a valid payment method';
        }
        if ($paymentMethod === 'razorpay' && !razorpayIsConfigured()) {
            $errors[] = 'Online payment is not available right now. Please choose cash payment.';
        }
        if (empty($_POST['accept_terms'])) {
            $errors[] = 'You must accept the Terms of Service and Privacy Policy to continue';
        }

        if (!empty($errors)) {
            $_SESSION['cart_flash'] = ['type' => 'error', 'message' => implode(' | ', $errors)];
            $redirectTo(navUrl('cart'));
        }

        try {
            $result = createInvoiceFromCart($cartItems, [
                'name' => $guestName,
                'email' => $guestEmail,
                'phone' => $guestPhone,
                'special_requirements' => $specialRequirements,
            ], $paymentMethod, $cab_functionality_enabled);

            $_SESSION['tour_cart'] = [];

            if ($paymentMethod === 'razorpay') {
                header('Location: ' . payInvoiceUrl($result['invoice_number']));
                exit;
            }

            require_once 'includes/invoice_pdf_helpers.php';
            notifyInvoiceViaWhatsApp($result['invoice_number']);

            $_SESSION['cart_flash'] = ['type' => 'success', 'message' => 'Invoice generated. Your tour PDF has been sent on WhatsApp. Please pay cash as per instructions on the invoice.'];
            header('Location: ' . invoiceUrl($result['invoice_number']));
            exit;
        } catch (Exception $e) {
            $_SESSION['cart_flash'] = ['type' => 'error', 'message' => $e->getMessage()];
            $redirectTo(navUrl('cart'));
        }
    }

    $_SESSION['cart_flash'] = ['type' => 'error', 'message' => 'Invalid action'];
    $redirectTo(navUrl('cart'));
}

$cartItems = $_SESSION['tour_cart'];
$tourIds = array_values(array_filter(array_map('intval', array_keys($cartItems))));
$tours = [];
$toursById = [];
if (!empty($tourIds)) {
    $placeholders = implode(',', array_fill(0, count($tourIds), '?'));
    $tours = $db->fetchAll("
        SELECT t.*, d.name as destination_name
        FROM tours t
        LEFT JOIN destinations d ON t.destination_id = d.id
        WHERE t.status = 'active' AND t.id IN ($placeholders)
    ", $tourIds);
    foreach ($tours as $tour) {
        $toursById[(string)$tour['id']] = $tour;
    }
}

$cartSummary = validateCartForCheckout($cartItems, $cab_functionality_enabled);
$checkoutUser = null;
if (isUserLoggedIn()) {
    try {
        $checkoutUser = $db->fetch("SELECT first_name, last_name, email, phone FROM users WHERE id = ?", [(int) $_SESSION['user_id']]);
    } catch (Exception $e) {
        $checkoutUser = null;
    }
}
$prefillName = trim(($checkoutUser['first_name'] ?? '') . ' ' . ($checkoutUser['last_name'] ?? ''));
$prefillEmail = $checkoutUser['email'] ?? ($_SESSION['user_email'] ?? '');
$prefillPhone = $checkoutUser['phone'] ?? '';
$razorpayEnabled = razorpayIsConfigured();

include 'includes/header.php';
?>

<section class="page-header">
    <div class="container">
        <h1>Your Cart</h1>
        <ul class="travhub-breadcrumb list-unstyled">
            <li><a href="<?php echo navUrl('home'); ?>">Home</a></li>
            <li>Cart</li>
        </ul>
    </div>
</section>

<section class="cart-wrapper">
    <div class="container">
        <?php if ($flash && isset($flash['message'])): ?>
            <div class="alert <?php echo ($flash['type'] ?? '') === 'success' ? 'alert-success' : 'alert-danger'; ?>" style="margin-bottom: 20px;">
                <?php echo htmlspecialchars((string)$flash['message']); ?>
            </div>
        <?php endif; ?>

        <?php if (empty($cartItems)): ?>
            <div class="cart-empty">
                <h3 style="margin-bottom:10px;">Cart is empty</h3>
                <p class="cart-help" style="margin-bottom:18px;">Add tours from the Tours page or a Tour Details page.</p>
                <a href="<?php echo navUrl('tours'); ?>" class="btn btn-primary">Browse Tours</a>
            </div>
        <?php else: ?>
            <div class="cart-grid">
                <div class="cart-card">
                    <div class="cart-actions" style="justify-content:space-between;align-items:center;margin-bottom:12px;">
                        <div class="cart-help">Update dates/people, then checkout once for all tours.</div>
                        <form method="POST" action="<?php echo navUrl('cart'); ?>">
                            <input type="hidden" name="action" value="clear">
                            <button type="submit" class="btn btn-outline-danger">Clear Cart</button>
                        </form>
                    </div>

                    <div style="overflow:auto;">
                        <table class="cart-table">
                            <thead>
                                <tr>
                                    <th>Tour</th>
                                    <th>Date</th>
                                    <th>People</th>
                                    <?php if ($cab_functionality_enabled && !empty($availableCabs)): ?>
                                        <th>Cab</th>
                                    <?php endif; ?>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cartItems as $tourIdStr => $item): ?>
                                    <?php $tour = $toursById[$tourIdStr] ?? null; ?>
                                    <?php if (!$tour) continue; ?>
                                    <tr>
                                        <td>
                                            <?php
                                            $cartTourImage = BASE_URL . 'assets/images/tours/default-tour.jpg';
                                            if (!empty($tour['featured_image']) && file_exists($tour['featured_image'])) {
                                                $cartTourImage = BASE_URL . $tour['featured_image'];
                                            }
                                            ?>
                                            <div class="cart-tour-cell">
                                                <a href="<?php echo tourUrl($tour['slug']); ?>" class="cart-tour-thumb">
                                                    <img src="<?php echo htmlspecialchars($cartTourImage); ?>"
                                                         alt="<?php echo htmlspecialchars($tour['title']); ?>"
                                                         onerror="this.src='<?php echo BASE_URL; ?>assets/images/tours/default-tour.jpg'">
                                                </a>
                                                <div class="cart-tour-info">
                                                    <div class="cart-row-title">
                                                        <a href="<?php echo tourUrl($tour['slug']); ?>" style="color:inherit;text-decoration:none;">
                                                            <?php echo htmlspecialchars($tour['title']); ?>
                                                        </a>
                                                    </div>
                                                    <div class="cart-help">
                                                        <?php echo htmlspecialchars($tour['destination_name'] ?? ''); ?>
                                                        <?php if (!empty($tour['duration_days'])): ?>
                                                            • <?php echo (int)$tour['duration_days']; ?> days
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <form method="POST" action="<?php echo navUrl('cart'); ?>">
                                                <input type="hidden" name="action" value="add">
                                                <input type="hidden" name="tour_id" value="<?php echo (int)$tour['id']; ?>">
                                                <input type="date" name="tour_date" class="form-control cart-input" min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" value="<?php echo htmlspecialchars((string)($item['tour_date'] ?? '')); ?>" required>
                                        </td>
                                        <td>
                                                <select name="people" class="form-control cart-input-sm" required>
                                                    <option value="">Select</option>
                                                    <?php for ($i = (int)$tour['min_people']; $i <= (int)$tour['max_people']; $i++): ?>
                                                        <option value="<?php echo $i; ?>" <?php echo ((int)($item['people'] ?? 0) === $i) ? 'selected' : ''; ?>>
                                                            <?php echo $i; ?>
                                                        </option>
                                                    <?php endfor; ?>
                                                </select>
                                        </td>
                                        <?php if ($cab_functionality_enabled && !empty($availableCabs)): ?>
                                            <td>
                                                <select name="cab_type" class="form-control cart-input">
                                                    <option value="">No cab</option>
                                                    <?php foreach ($availableCabs as $cab): ?>
                                                        <option value="<?php echo htmlspecialchars($cab['value']); ?>" <?php echo ((string)($item['cab_type'] ?? '') === (string)$cab['value']) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($cab['text']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                        <?php endif; ?>
                                        <td style="white-space:nowrap;">
                                                <button type="submit" class="btn btn-outline-primary" style="margin-right:10px;">Update</button>
                                            </form>
                                            <form method="POST" action="<?php echo navUrl('cart'); ?>" style="display:inline;">
                                                <input type="hidden" name="action" value="remove">
                                                <input type="hidden" name="tour_id" value="<?php echo (int)$tour['id']; ?>">
                                                <button type="submit" class="cart-remove-btn" title="Remove">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="cart-form-card">
                    <h3 style="margin-bottom:14px;">Checkout</h3>
                    <p class="cart-help" style="margin-bottom:18px;">Choose payment method and complete your booking details.</p>

                    <div class="cart-total-box">
                        <div class="cart-help">Order total</div>
                        <div class="amount"><?php echo formatPriceINR($cartSummary['total']); ?></div>
                    </div>

                    <form method="POST" action="<?php echo navUrl('cart'); ?>" id="cartCheckoutForm">
                        <input type="hidden" name="action" value="checkout">
                        <input type="hidden" name="return_url" value="<?php echo htmlspecialchars(navUrl('cart')); ?>">

                        <div class="cart-field">
                            <label class="cart-form-label" for="cartGuestName"><i class="fas fa-user"></i> Name</label>
                            <input type="text" name="guest_name" id="cartGuestName" class="form-control" value="<?php echo htmlspecialchars($prefillName); ?>" required>
                        </div>

                        <div class="cart-field">
                            <label class="cart-form-label" for="cartGuestEmail"><i class="fas fa-envelope"></i> Email</label>
                            <input type="email" name="guest_email" id="cartGuestEmail" class="form-control" value="<?php echo htmlspecialchars($prefillEmail); ?>" required>
                        </div>

                        <div class="cart-field">
                            <label class="cart-form-label" for="cartGuestPhone"><i class="fas fa-phone"></i> Phone</label>
                            <input type="text" name="guest_phone" id="cartGuestPhone" class="form-control" value="<?php echo htmlspecialchars($prefillPhone); ?>" required>
                        </div>

                        <div class="cart-field">
                            <label class="cart-form-label" for="cartSpecialRequirements"><i class="fas fa-comment-dots"></i> Special Requirements</label>
                            <textarea name="special_requirements" id="cartSpecialRequirements" class="form-control" rows="3" placeholder="Optional"></textarea>
                        </div>

                        <div class="cart-field cart-payment-block">
                            <label class="cart-form-label"><i class="fas fa-credit-card"></i> Payment Method</label>
                            <div class="payment-options">
                                <label class="payment-option is-active">
                                    <input type="radio" name="payment_method" value="cash" checked>
                                    <div>
                                        <strong>Cash / Manual Payment</strong>
                                        <span>Pay in cash at our office or to the tour guide. Invoice will be generated instantly.</span>
                                    </div>
                                </label>
                                <label class="payment-option<?php echo $razorpayEnabled ? '' : ' is-disabled'; ?>">
                                    <input type="radio" name="payment_method" value="razorpay"<?php echo $razorpayEnabled ? '' : ' disabled'; ?>>
                                    <div>
                                        <strong>Pay Online with Razorpay</strong>
                                        <span class="razorpay-badge">
                                            <img src="https://razorpay.com/assets/razorpay-logo.svg" alt="Razorpay">
                                            UPI, cards, net banking, and wallets
                                        </span>
                                        <?php if (!$razorpayEnabled): ?>
                                            <span class="payment-setup-note">Online payment is not active yet. Add Razorpay Key ID and Secret in Admin → Settings → Payment Settings.</span>
                                        <?php endif; ?>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <div class="cart-terms-check">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="accept_terms" id="cartAcceptTerms" value="1" required>
                                <label class="form-check-label" for="cartAcceptTerms">
                                    I agree to the <a href="<?php echo navUrl('terms-conditions'); ?>" target="_blank" rel="noopener">Terms of Service</a> and <a href="<?php echo navUrl('privacy-policy'); ?>" target="_blank" rel="noopener">Privacy Policy</a>
                                </label>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">Book Now</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
<script>
document.querySelectorAll('.payment-option input[type="radio"]').forEach(function(input) {
    input.addEventListener('change', function() {
        document.querySelectorAll('.payment-option').forEach(function(option) {
            option.classList.remove('is-active');
        });
        if (input.closest('.payment-option')) {
            input.closest('.payment-option').classList.add('is-active');
        }
    });
});
</script>

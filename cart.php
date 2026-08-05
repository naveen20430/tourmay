<?php
require_once 'config/config.php';
require_once 'includes/checkout_helpers.php';
require_once 'includes/email_otp_helpers.php';

$current_page = 'cart';
$page_title = 'Cart - ' . getSetting('site_name');
$extra_css = cssWithCache('assets/css/checkout-page.css');

if (!isset($_SESSION['tour_cart']) || !is_array($_SESSION['tour_cart'])) {
    $_SESSION['tour_cart'] = [];
}

$cab_functionality_enabled = false;
$availableCabs = [];
$pickupPlaces = ['Hotel', 'Lift Parking', 'Others'];
try {
    if (file_exists('includes/cab_options.php')) {
        require_once 'includes/cab_options.php';
        $db->fetch("SELECT COUNT(*) as count FROM cab_types LIMIT 1");
        $cab_functionality_enabled = true;
        $cabOptions = new CabOptions($db);
        $availableCabs = $cabOptions->getCabOptionsForDropdown();
        $pickupPlaces = getActivityPickupPlaces();
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_GET['add_tour'])) {
    $tourId = filter_var($_GET['add_tour'], FILTER_VALIDATE_INT);
    if (!$tourId) {
        $_SESSION['cart_flash'] = ['type' => 'error', 'message' => 'Invalid tour selection'];
        header('Location: ' . navUrl('cart'));
        exit;
    }

    $result = addTourToSessionCart((int) $tourId);
    $_SESSION['cart_flash'] = [
        'type' => $result['ok'] ? 'success' : 'error',
        'message' => $result['message'],
    ];
    header('Location: ' . navUrl('cart'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    $processCartCheckout = function (array $guest, string $paymentMethod) use ($cab_functionality_enabled) {
        $cartItems = $_SESSION['tour_cart'] ?? [];
        if (empty($cartItems)) {
            throw new Exception('Your cart is empty');
        }

        $result = createInvoiceFromCart($cartItems, $guest, $paymentMethod, $cab_functionality_enabled);

        if (function_exists('twjCheckoutLog')) {
            twjCheckoutLog('checkout_invoice_created', [
                'invoice_number' => $result['invoice_number'] ?? '',
                'total_amount' => $result['total_amount'] ?? 0,
                'payment_method' => $paymentMethod,
                'cab_enabled' => (bool) $cab_functionality_enabled,
            ]);
        }

        $_SESSION['tour_cart'] = [];
        unset($_SESSION['cart_checkout_draft'], $_SESSION['cart_checkout_pending']);

        if ($paymentMethod === 'razorpay') {
            if (function_exists('twjCheckoutLog')) {
                twjCheckoutLog('checkout_redirect_pay', [
                    'invoice_number' => $result['invoice_number'] ?? '',
                    'url' => payInvoiceUrl($result['invoice_number']),
                ]);
            }
            header('Location: ' . payInvoiceUrl($result['invoice_number']));
            exit;
        }

        require_once 'includes/invoice_pdf_helpers.php';
        notifyInvoiceViaWhatsApp($result['invoice_number']);

        $_SESSION['cart_flash'] = ['type' => 'success', 'message' => 'Invoice generated. Your tour PDF has been sent on WhatsApp. Please pay cash as per instructions on the invoice.'];
        header('Location: ' . invoiceUrl($result['invoice_number']));
        exit;
    };

    if ($action === 'add') {
        $tourId = filter_var($_POST['tour_id'] ?? null, FILTER_VALIDATE_INT);
        $isAjax = (
            (isset($_POST['ajax']) && (string)$_POST['ajax'] === '1')
            || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || (isset($_SERVER['HTTP_ACCEPT']) && str_contains((string)$_SERVER['HTTP_ACCEPT'], 'application/json'))
        );

        if (!$tourId) {
            if ($isAjax) {
                header('Content-Type: application/json');
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid tour selection']);
                exit;
            }
            $_SESSION['cart_flash'] = ['type' => 'error', 'message' => 'Invalid tour selection'];
            $redirectTo(navUrl('cart'));
        }

        $result = addTourToSessionCart((int) $tourId, [
            'tour_date' => $_POST['tour_date'] ?? '',
            'people' => $_POST['people'] ?? null,
            'cab_type' => $cab_functionality_enabled ? ($_POST['cab_type'] ?? '') : '',
            'pickup_place' => $_POST['pickup_place'] ?? '',
            'pickup_detail' => $_POST['pickup_detail'] ?? '',
            'pickup_address' => $_POST['pickup_address'] ?? '',
            'pickup_time' => $_POST['pickup_time'] ?? '',
        ]);

        if ($isAjax) {
            $summary = validateCartForCheckout($_SESSION['tour_cart'] ?? [], $cab_functionality_enabled);
            $lineTotal = 0.0;
            foreach (($summary['lines'] ?? []) as $line) {
                if ((int) ($line['tour_id'] ?? 0) === (int) $tourId) {
                    $lineTotal = (float) ($line['line_total'] ?? 0);
                    break;
                }
            }
            $savedDate = (string) ($_SESSION['tour_cart'][(string) $tourId]['tour_date'] ?? '');
            header('Content-Type: application/json');
            echo json_encode([
                'success' => !empty($result['ok']),
                'message' => $result['message'] ?? '',
                'tour_id' => (int) $tourId,
                'tour_date' => $savedDate,
                'line_total' => $lineTotal,
                'order_total' => (float) ($summary['total'] ?? 0),
                'errors' => $summary['errors'] ?? [],
            ]);
            exit;
        }

        $_SESSION['cart_flash'] = [
            'type' => $result['ok'] ? 'success' : 'error',
            'message' => $result['message'],
        ];
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

        // Apply latest tour/cab/pickup values from the checkout form (source of truth),
        // so payment does not depend on a prior AJAX save that may have failed or raced.
        $cartSyncRaw = (string) ($_POST['cart_sync'] ?? '');
        if ($cartSyncRaw !== '') {
            $cartSync = json_decode($cartSyncRaw, true);
            if (is_array($cartSync)) {
                $syncErrors = [];
                foreach ($cartSync as $syncItem) {
                    if (!is_array($syncItem)) {
                        continue;
                    }
                    $syncTourId = filter_var($syncItem['tour_id'] ?? null, FILTER_VALIDATE_INT);
                    if (!$syncTourId || !isset($_SESSION['tour_cart'][(string) $syncTourId])) {
                        continue;
                    }
                    $syncResult = addTourToSessionCart((int) $syncTourId, [
                        'tour_date' => $syncItem['tour_date'] ?? ($_SESSION['tour_cart'][(string) $syncTourId]['tour_date'] ?? ''),
                        'people' => $syncItem['people'] ?? ($_SESSION['tour_cart'][(string) $syncTourId]['people'] ?? null),
                        'cab_type' => $cab_functionality_enabled ? ($syncItem['cab_type'] ?? '') : '',
                        'pickup_place' => $syncItem['pickup_place'] ?? '',
                        'pickup_detail' => $syncItem['pickup_detail'] ?? '',
                        'pickup_address' => $syncItem['pickup_address'] ?? '',
                        'pickup_time' => $syncItem['pickup_time'] ?? '',
                    ]);
                    if (empty($syncResult['ok'])) {
                        $syncErrors[] = (string) ($syncResult['message'] ?? 'Unable to update cart item');
                    }
                }
                $cartItems = $_SESSION['tour_cart'];
                if (!empty($syncErrors)) {
                    $_SESSION['cart_checkout_draft'] = [
                        'guest_name' => trim((string) ($_POST['guest_name'] ?? '')),
                        'guest_email' => trim((string) ($_POST['guest_email'] ?? '')),
                        'guest_phone' => trim((string) ($_POST['guest_phone'] ?? '')),
                        'special_requirements' => trim((string) ($_POST['special_requirements'] ?? '')),
                        'claim_gst' => !empty($_POST['claim_gst']),
                        'gst_number' => strtoupper(preg_replace('/\s+/', '', trim((string) ($_POST['gst_number'] ?? '')))),
                        'payment_method' => trim((string) ($_POST['payment_method'] ?? 'razorpay')),
                        'accept_terms' => !empty($_POST['accept_terms']),
                    ];
                    $_SESSION['cart_flash'] = [
                        'type' => 'error',
                        'message' => implode(' | ', array_values(array_unique($syncErrors))),
                    ];
                    $redirectTo(navUrl('cart'));
                }
            }
        }

        $guestName = trim((string)($_POST['guest_name'] ?? ''));
        $guestEmail = trim((string)($_POST['guest_email'] ?? ''));
        $guestPhone = trim((string)($_POST['guest_phone'] ?? ''));
        $specialRequirements = trim((string)($_POST['special_requirements'] ?? ''));
        $claimGst = !empty($_POST['claim_gst']);
        $gstNumber = strtoupper(preg_replace('/\s+/', '', trim((string) ($_POST['gst_number'] ?? ''))));
        $paymentMethod = trim((string)($_POST['payment_method'] ?? 'razorpay'));

        $errors = [];
        if ($guestName === '') $errors[] = 'Your name is required';
        if ($guestEmail === '' || !filter_var($guestEmail, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required';
        if ($guestPhone === '') $errors[] = 'Your phone number is required';
        if ($claimGst) {
            if ($gstNumber === '') {
                $errors[] = 'Please enter your GST number';
            } elseif (!preg_match('/^[0-9A-Z]{15}$/', $gstNumber)) {
                $errors[] = 'Please enter a valid 15-character GSTIN';
            }
        } else {
            $gstNumber = '';
        }
        if ($paymentMethod !== 'razorpay') {
            $errors[] = 'Please select online payment';
        }
        if (!razorpayIsConfigured()) {
            $errors[] = 'Online payment is not available right now. Please try again later.';
        }
        if (empty($_POST['accept_terms'])) {
            $errors[] = 'You must accept the Terms of Service and Privacy Policy to continue';
        }

        if (!isUserLoggedIn()) {
            $normalizedGuestEmail = normalizeEmailAddress($guestEmail);
            if (!isVerifiedEmailSession('checkout', $normalizedGuestEmail)) {
                $errors[] = 'Please verify your email with OTP before proceeding to payment';
            }
        }

        if (!empty($errors)) {
            $_SESSION['cart_checkout_draft'] = [
                'guest_name' => $guestName,
                'guest_email' => $guestEmail,
                'guest_phone' => $guestPhone,
                'special_requirements' => $specialRequirements,
                'claim_gst' => $claimGst,
                'gst_number' => $gstNumber,
                'payment_method' => $paymentMethod,
                'accept_terms' => !empty($_POST['accept_terms']),
                'email_verified' => isVerifiedEmailSession('checkout', normalizeEmailAddress($guestEmail)),
            ];
            $_SESSION['cart_flash'] = ['type' => 'error', 'message' => implode(' | ', $errors)];
            $redirectTo(navUrl('cart'));
        }

        if (!isUserLoggedIn()) {
            try {
                $user = findOrCreateUserForCheckout($guestEmail, $guestName, $guestPhone);
                establishUserSession($user);
                consumeVerifiedEmailSession('checkout', normalizeEmailAddress($guestEmail));
            } catch (Exception $e) {
                $_SESSION['cart_checkout_draft'] = [
                    'guest_name' => $guestName,
                    'guest_email' => $guestEmail,
                    'guest_phone' => $guestPhone,
                    'special_requirements' => $specialRequirements,
                    'claim_gst' => $claimGst,
                    'gst_number' => $gstNumber,
                    'payment_method' => $paymentMethod,
                    'accept_terms' => !empty($_POST['accept_terms']),
                ];
                $_SESSION['cart_flash'] = ['type' => 'error', 'message' => $e->getMessage()];
                $redirectTo(navUrl('cart'));
            }
        }

        try {
            $processCartCheckout([
                'name' => $guestName,
                'email' => $guestEmail,
                'phone' => $guestPhone,
                'special_requirements' => $specialRequirements,
                'gst_number' => $gstNumber,
            ], $paymentMethod);
        } catch (Exception $e) {
            if (function_exists('twjCheckoutLog')) {
                twjCheckoutLog('checkout_exception', [
                    'force' => true,
                    'message' => $e->getMessage(),
                    'payment_method' => $paymentMethod,
                    'guest_email' => $guestEmail,
                    'cart_count' => is_array($_SESSION['tour_cart'] ?? null) ? count($_SESSION['tour_cart']) : 0,
                ]);
            }
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

// Default / fix cab so it exists and can fit the selected people count.
if ($cab_functionality_enabled && !empty($cartItems)) {
    foreach ($cartItems as $tourIdStr => $item) {
        $tourId = (int) $tourIdStr;
        if ($tourId <= 0 || empty($toursById[$tourIdStr])) {
            continue;
        }
        $tourCabs = getCabOptionsForTour($tourId, $db);
        if (empty($tourCabs)) {
            continue;
        }
        $peopleCount = (int) ($item['people'] ?? 0);
        $tourRow = $toursById[$tourIdStr];
        if ($peopleCount < (int) ($tourRow['min_people'] ?? 1)) {
            $peopleCount = (int) ($tourRow['min_people'] ?? 1);
        }
        $currentCab = (string) ($item['cab_type'] ?? '');
        $currentFits = false;
        foreach ($tourCabs as $cab) {
            if ($currentCab === (string) $cab['value']
                && (int) ($cab['max_passengers'] ?? 0) >= $peopleCount) {
                $currentFits = true;
                break;
            }
        }
        if (!$currentFits) {
            $fallbackCab = '';
            foreach ($tourCabs as $cab) {
                if ((int) ($cab['max_passengers'] ?? 0) >= $peopleCount) {
                    $fallbackCab = (string) ($cab['value'] ?? '');
                    break;
                }
            }
            if ($fallbackCab === '') {
                $fallbackCab = (string) ($tourCabs[0]['value'] ?? '');
            }
            $_SESSION['tour_cart'][$tourIdStr]['cab_type'] = $fallbackCab;
        }
    }
    $cartItems = $_SESSION['tour_cart'];
}

$cartSummary = validateCartForCheckout($cartItems, $cab_functionality_enabled);
$cartLinesByTourId = [];
foreach ($cartSummary['lines'] ?? [] as $line) {
    $cartLinesByTourId[(string) $line['tour_id']] = $line;
}
$checkoutUser = null;
if (isUserLoggedIn()) {
    try {
        $checkoutUser = $db->fetch("SELECT first_name, last_name, email, phone FROM users WHERE id = ?", [(int) $_SESSION['user_id']]);
    } catch (Exception $e) {
        $checkoutUser = null;
    }
}

// After login, complete any pending guest checkout automatically.
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isUserLoggedIn() && !empty($_SESSION['cart_checkout_pending'])) {
    $pendingCheckout = $_SESSION['cart_checkout_pending'];
    unset($_SESSION['cart_checkout_pending']);

    try {
        $pendingCartItems = $_SESSION['tour_cart'] ?? [];
        if (empty($pendingCartItems)) {
            throw new Exception('Your cart is empty');
        }

        $paymentMethod = 'razorpay';

        $result = createInvoiceFromCart($pendingCartItems, [
            'name' => (string) ($pendingCheckout['name'] ?? ''),
            'email' => (string) ($pendingCheckout['email'] ?? ''),
            'phone' => (string) ($pendingCheckout['phone'] ?? ''),
            'special_requirements' => (string) ($pendingCheckout['special_requirements'] ?? ''),
            'gst_number' => (string) ($pendingCheckout['gst_number'] ?? ''),
        ], $paymentMethod, $cab_functionality_enabled);

        $_SESSION['tour_cart'] = [];
        unset($_SESSION['cart_checkout_draft']);

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
        $_SESSION['cart_checkout_draft'] = [
            'guest_name' => (string) ($pendingCheckout['name'] ?? ''),
            'guest_email' => (string) ($pendingCheckout['email'] ?? ''),
            'guest_phone' => (string) ($pendingCheckout['phone'] ?? ''),
            'special_requirements' => (string) ($pendingCheckout['special_requirements'] ?? ''),
            'claim_gst' => !empty($pendingCheckout['gst_number']),
            'gst_number' => (string) ($pendingCheckout['gst_number'] ?? ''),
            'payment_method' => (string) ($pendingCheckout['payment_method'] ?? 'razorpay'),
            'accept_terms' => true,
        ];
        $_SESSION['cart_flash'] = ['type' => 'error', 'message' => $e->getMessage()];
    }
}

$checkoutDraft = $_SESSION['cart_checkout_draft'] ?? [];
$prefillName = trim(($checkoutUser['first_name'] ?? '') . ' ' . ($checkoutUser['last_name'] ?? ''));
if ($prefillName === '' && !empty($checkoutDraft['guest_name'])) {
    $prefillName = (string) $checkoutDraft['guest_name'];
}
$prefillEmail = $checkoutUser['email'] ?? ($_SESSION['user_email'] ?? '');
if ($prefillEmail === '' && !empty($checkoutDraft['guest_email'])) {
    $prefillEmail = (string) $checkoutDraft['guest_email'];
}
$prefillPhone = $checkoutUser['phone'] ?? '';
if ($prefillPhone === '' && !empty($checkoutDraft['guest_phone'])) {
    $prefillPhone = (string) $checkoutDraft['guest_phone'];
}
$prefillSpecialRequirements = (string) ($checkoutDraft['special_requirements'] ?? '');
$prefillGstNumber = (string) ($checkoutDraft['gst_number'] ?? '');
$prefillClaimGst = !empty($checkoutDraft['claim_gst']) || $prefillGstNumber !== '';
$prefillAcceptTerms = !empty($checkoutDraft['accept_terms']);
$razorpayEnabled = razorpayIsConfigured();
$emailOtpEnabled = emailOtpIsConfigured();
$isLoggedIn = isUserLoggedIn();
$prefillEmailVerified = $isLoggedIn
    || (!empty($checkoutDraft['email_verified']) && isVerifiedEmailSession('checkout', $prefillEmail))
    || isVerifiedEmailSession('checkout', $prefillEmail);
$checkoutButtonLabel = $isLoggedIn ? 'Book Now' : 'Proceed to Payment';
$checkoutHelpText = $isLoggedIn
    ? 'Complete your booking details and pay online.'
    : ($emailOtpEnabled
        ? 'Fill the form below. When you proceed to payment, an OTP will be sent to your email to verify.'
        : 'Fill your details below. Email OTP must be configured to checkout as a guest.');

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
        <?php
        $cartValidationErrors = array_values(array_unique($cartSummary['errors'] ?? []));
        $flashMessage = ($flash && isset($flash['message'])) ? trim((string) $flash['message']) : '';
        $flashLooksLikeCartValidation = $flashMessage !== '' && !empty($cartValidationErrors) && (
            $flashMessage === implode(' | ', $cartValidationErrors)
            || str_contains($flashMessage, 'pickup point')
            || str_contains($flashMessage, 'pickup time')
            || str_contains($flashMessage, 'cab option')
        );
        ?>
        <?php if ($flashMessage !== '' && !$flashLooksLikeCartValidation): ?>
            <?php
            $flashType = (string) ($flash['type'] ?? 'error');
            $flashClass = $flashType === 'success' ? 'alert-success' : ($flashType === 'info' ? 'alert-info' : 'alert-danger');
            ?>
            <div class="alert <?php echo $flashClass; ?>" style="margin-bottom: 20px;">
                <?php echo htmlspecialchars($flashMessage); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($cartValidationErrors)): ?>
            <div class="alert alert-danger cart-validation-alert d-none" id="cartValidationAlert" style="margin-bottom: 20px;">
                <strong>Please complete these tour details:</strong>
                <ul class="mb-0 mt-2">
                    <?php foreach ($cartValidationErrors as $cartError): ?>
                        <li><?php echo htmlspecialchars($cartError); ?></li>
                    <?php endforeach; ?>
                </ul>
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
                    <div class="cart-actions cart-actions--toolbar">
                        <div class="cart-actions__left">
                            <a href="<?php echo navUrl('tours'); ?>" class="btn btn-primary cart-btn-add-tour">
                                <i class="fas fa-plus me-1"></i> Add more tour
                            </a>
                            <span class="cart-help cart-actions__hint">Changes save automatically. Checkout once for all tours.</span>
                        </div>
                        <form method="POST" action="<?php echo navUrl('cart'); ?>" class="cart-actions__clear" onsubmit="return confirm('Clear all tours from your cart?');">
                            <input type="hidden" name="action" value="clear">
                            <button type="submit" class="btn btn-danger cart-btn-clear">
                                <i class="fas fa-trash-alt me-1"></i> Clear Cart
                            </button>
                        </form>
                    </div>

                    <?php $cartHasCabs = $cab_functionality_enabled; ?>
                    <div class="cart-tour-cards">
                        <?php foreach ($cartItems as $tourIdStr => $item): ?>
                            <?php $tour = $toursById[$tourIdStr] ?? null; ?>
                            <?php if (!$tour) continue; ?>
                            <?php
                            $tourCabs = $cab_functionality_enabled ? getCabOptionsForTour((int) $tour['id'], $db) : [];
                            $selectedCab = (string)($item['cab_type'] ?? '');
                            $selectedPickup = (string)($item['pickup_place'] ?? '');
                            $selectedPickupDetail = (string)($item['pickup_detail'] ?? '');
                            $selectedPickupAddress = (string)($item['pickup_address'] ?? '');
                            $selectedPickupTime = (string)($item['pickup_time'] ?? '');
                            if (preg_match('/^(\d{2}:\d{2})/', $selectedPickupTime, $m)) {
                                $selectedPickupTime = $m[1];
                            }
                            $pickupTimeOptions = getPickupTimeOptions((int) $tour['id']);
                            if ($selectedPickupTime !== '' && !isValidPickupTime($selectedPickupTime, (int) $tour['id'])) {
                                $selectedPickupTime = '';
                            }
                            $showPickup = $selectedCab !== '';
                            $needsPickupDetail = in_array($selectedPickup, ['Hotel', 'Others'], true);
                            $needsHotelAddress = $selectedPickup === 'Hotel';
                            $line = $cartLinesByTourId[$tourIdStr] ?? null;
                            $peopleValue = (int)($item['people'] ?? 0);
                            if ($peopleValue < (int)$tour['min_people'] || $peopleValue === 0) {
                                $peopleValue = (int)$tour['min_people'];
                            }
                            $durationDays = (int)($tour['duration_days'] ?? 0);
                            $durationLabel = $durationDays === 1 ? '1 day' : $durationDays . ' days';
                            $startsFrom = $tour['discount_price'] ? (float)$tour['discount_price'] : (float)$tour['price'];
                            $totalStatusText = 'Select a cab to see price';
                            $displayCabTotal = 0.0;
                            if ($selectedCab !== '') {
                                $cabLabel = function_exists('getCabDisplayName') ? getCabDisplayName($selectedCab) : $selectedCab;
                                $displayCabTotal = (float)($line['cab_price'] ?? $line['line_total'] ?? 0);
                                if ($displayCabTotal <= 0) {
                                    foreach ($tourCabs as $cabOpt) {
                                        if ((string)$cabOpt['value'] === $selectedCab) {
                                            $displayCabTotal = (float)($cabOpt['price'] ?? 0);
                                            break;
                                        }
                                    }
                                }
                                $totalStatusText = $cabLabel;
                            }
                            $tourMissingPickup = $cab_functionality_enabled && $selectedCab !== '' && $selectedPickup === '';
                            $tourMissingPickupTime = $cab_functionality_enabled && $selectedCab !== '' && $selectedPickupTime === '';
                            ?>
                            <article class="cart-tour-item"
                                     data-cart-tour-item
                                     data-tour-id="<?php echo (int) $tour['id']; ?>"
                                     data-duration-days="<?php echo max(1, $durationDays); ?>"
                                     data-tour-title="<?php echo htmlspecialchars($tour['title']); ?>">
                                <form method="POST" action="<?php echo navUrl('cart'); ?>" class="cart-tour-item__form" data-cart-tour-form>
                                    <input type="hidden" name="tour_id" value="<?php echo (int)$tour['id']; ?>">
                                    <input type="hidden" name="action" value="add" data-cart-action>
                                    <input type="hidden" name="ajax" value="1" data-cart-ajax>

                                    <header class="cart-tour-item__header">
                                        <div class="cart-tour-item__icon" aria-hidden="true">
                                            <i class="fas fa-map-marker-alt"></i>
                                        </div>
                                        <div class="cart-tour-item__heading">
                                            <h3 class="cart-tour-item__title">
                                                <a href="<?php echo tourUrl($tour['slug']); ?>">
                                                    <?php echo htmlspecialchars($tour['title']); ?>
                                                </a>
                                            </h3>
                                            <p class="cart-tour-item__meta">
                                                <?php echo htmlspecialchars($tour['destination_name'] ?? ''); ?>
                                                <?php if ($durationDays > 0): ?>
                                                    · <?php echo $durationLabel; ?>
                                                <?php endif; ?>
                                                <?php if ($startsFrom > 0): ?>
                                                    · Starts from <?php echo formatPriceINR($startsFrom); ?>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                    </header>

                                    <div class="cart-tour-item__fields-row">
                                        <div class="cart-field-group cart-field-group--date">
                                            <label class="cart-field-label" for="tour_date_<?php echo (int)$tour['id']; ?>">Date</label>
                                            <div class="cart-date-wrap">
                                                <input type="date"
                                                       id="tour_date_<?php echo (int)$tour['id']; ?>"
                                                       name="tour_date"
                                                       class="form-control cart-date-input"
                                                       min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>"
                                                       value="<?php echo htmlspecialchars((string)($item['tour_date'] ?? '')); ?>"
                                                       required>
                                                <i class="fas fa-calendar-alt cart-date-icon" aria-hidden="true"></i>
                                            </div>
                                        </div>

                                        <div class="cart-field-group cart-field-group--people">
                                            <label class="cart-field-label">People</label>
                                            <div class="cart-people-stepper"
                                                 data-people-stepper
                                                 data-min="<?php echo (int)$tour['min_people']; ?>"
                                                 data-max="<?php echo (int)$tour['max_people']; ?>">
                                                <button type="button" class="cart-stepper-btn" data-step="-1" aria-label="Decrease people">−</button>
                                                <span class="cart-stepper-value" data-people-display><?php echo $peopleValue > 0 ? $peopleValue : (int)$tour['min_people']; ?></span>
                                                <select name="people" class="cart-people-select" data-people-select required>
                                                    <?php for ($i = (int)$tour['min_people']; $i <= (int)$tour['max_people']; $i++): ?>
                                                        <option value="<?php echo $i; ?>" <?php echo $peopleValue === $i ? 'selected' : ''; ?>>
                                                            <?php echo $i; ?>
                                                        </option>
                                                    <?php endfor; ?>
                                                </select>
                                                <button type="button" class="cart-stepper-btn" data-step="1" aria-label="Increase people">+</button>
                                            </div>
                                        </div>
                                    </div>

                                    <?php if ($cartHasCabs && !empty($tourCabs)): ?>
                                        <div class="cart-field-group cart-field-group--cab">
                                            <label class="cart-field-label">Choose your vehicle</label>
                                            <?php /* Hidden field keeps FormData/AJAX stable; radios use unique names per tour so they don't conflict. */ ?>
                                            <input type="hidden"
                                                   name="cab_type"
                                                   value="<?php echo htmlspecialchars($selectedCab); ?>"
                                                   data-cart-cab-hidden>
                                            <div class="cart-cab-grid" data-cart-cab-picker>
                                                <?php foreach ($tourCabs as $cab): ?>
                                                    <?php
                                                    $cabCapacity = (int) ($cab['max_passengers'] ?? 0);
                                                    $cabFitsPeople = $cabCapacity <= 0 || $cabCapacity >= $peopleValue;
                                                    ?>
                                                    <label class="cart-cab-option<?php echo $cabFitsPeople ? '' : ' is-unavailable'; ?>"
                                                           title="<?php echo htmlspecialchars($cab['text'] . ($cabCapacity > 0 ? ' · up to ' . $cabCapacity . ' people' : '')); ?>">
                                                        <input type="radio"
                                                               name="cab_choice_<?php echo (int) $tour['id']; ?>"
                                                               value="<?php echo htmlspecialchars($cab['value']); ?>"
                                                               data-cart-cab-radio
                                                               data-cab-label="<?php echo htmlspecialchars($cab['display_name'] ?? $cab['value']); ?>"
                                                               data-cab-price="<?php echo (float)($cab['price'] ?? 0); ?>"
                                                               data-cab-capacity="<?php echo $cabCapacity; ?>"
                                                               <?php echo !$cabFitsPeople ? 'disabled' : ''; ?>
                                                               <?php echo ($cabFitsPeople && $selectedCab === (string)$cab['value']) ? 'checked' : ''; ?>>
                                                        <span class="cart-cab-option__body">
                                                            <span class="cart-cab-option__thumb">
                                                                <img src="<?php echo htmlspecialchars($cab['image_url']); ?>"
                                                                     alt=""
                                                                     onerror="this.src='<?php echo BASE_URL; ?>assets/images/tours/default-tour.jpg'">
                                                            </span>
                                                            <span class="cart-cab-option__text">
                                                                <span class="cart-cab-option__name"><?php echo htmlspecialchars($cab['display_name'] ?? $cab['value']); ?></span>
                                                                <span class="cart-cab-option__price">₹<?php echo number_format((float)($cab['price'] ?? 0), 0); ?></span>
                                                                <?php if ($cabCapacity > 0): ?>
                                                                    <span class="cart-cab-option__capacity">Up to <?php echo $cabCapacity; ?> people</span>
                                                                <?php endif; ?>
                                                            </span>
                                                        </span>
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>

                                            <div class="cart-pickup-fields" data-cart-pickup-wrap <?php echo $showPickup ? '' : 'hidden'; ?>>
                                                <label class="cart-field-label" for="pickup_place_<?php echo (int)$tour['id']; ?>">Pickup point <span class="text-danger">*</span></label>
                                                <select name="pickup_place"
                                                        id="pickup_place_<?php echo (int)$tour['id']; ?>"
                                                        class="form-control cart-pickup-place"
                                                        data-cart-pickup-place
                                                        <?php echo $showPickup ? 'required' : ''; ?>>
                                                    <option value="">Select pickup point</option>
                                                    <?php foreach ($pickupPlaces as $place): ?>
                                                        <option value="<?php echo htmlspecialchars($place); ?>" <?php echo $selectedPickup === $place ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($place); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <div class="cart-pickup-detail-wrap" data-cart-pickup-detail-wrap <?php echo $needsPickupDetail ? '' : 'hidden'; ?>>
                                                    <input type="text"
                                                           name="pickup_detail"
                                                           class="form-control cart-pickup-detail"
                                                           data-cart-pickup-detail
                                                           value="<?php echo htmlspecialchars($selectedPickupDetail); ?>"
                                                           placeholder="<?php echo $selectedPickup === 'Hotel' ? 'Hotel name' : ($selectedPickup === 'Others' ? 'Enter location details' : 'Enter details'); ?>"
                                                           <?php echo $needsPickupDetail ? 'required' : ''; ?>>
                                                    <textarea
                                                           name="pickup_address"
                                                           class="form-control cart-pickup-address"
                                                           data-cart-pickup-address
                                                           rows="3"
                                                           placeholder="Full address with location"
                                                           <?php echo $needsHotelAddress ? '' : 'hidden'; ?>
                                                           <?php echo $needsHotelAddress ? 'required' : ''; ?>><?php echo htmlspecialchars($selectedPickupAddress); ?></textarea>
                                                </div>
                                                <label class="cart-field-label" for="pickup_time_<?php echo (int)$tour['id']; ?>">Pickup time <span class="text-danger">*</span></label>
                                                <select name="pickup_time"
                                                        id="pickup_time_<?php echo (int)$tour['id']; ?>"
                                                        class="form-control cart-pickup-time"
                                                        data-cart-pickup-time
                                                        <?php echo $showPickup ? 'required' : ''; ?>>
                                                    <option value="">Select time</option>
                                                    <?php foreach ($pickupTimeOptions as $timeOpt): ?>
                                                        <option value="<?php echo htmlspecialchars($timeOpt['value']); ?>"
                                                            <?php echo $selectedPickupTime === $timeOpt['value'] ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($timeOpt['label']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <div class="cart-item-field-error d-none" data-cart-pickup-error></div>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <footer class="cart-tour-item__footer">
                                        <div class="cart-tour-item__total">
                                            <span class="cart-tour-item__total-label">Price (from cab)</span>
                                            <strong class="cart-tour-item__total-value" data-cart-total-status>
                                                <?php echo htmlspecialchars($totalStatusText); ?>
                                            </strong>
                                            <?php if ($selectedCab !== '' && $displayCabTotal > 0): ?>
                                                <span class="cart-tour-item__total-amount" data-cart-total-amount>
                                                    <?php echo formatPriceINR($displayCabTotal); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="cart-tour-item__total-amount" data-cart-total-amount hidden></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="cart-tour-item__footer-actions">
                                            <button type="submit" class="cart-delete-btn" data-cart-remove formnovalidate title="Remove from cart" aria-label="Remove from cart">
                                                <i class="fas fa-trash" aria-hidden="true"></i>
                                            </button>
                                        </div>
                                    </footer>
                                </form>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="cart-form-card">
                    <h3 style="margin-bottom:14px;">Checkout</h3>
                    <p class="cart-help" style="margin-bottom:18px;"><?php echo htmlspecialchars($checkoutHelpText); ?></p>

                    <div class="cart-total-box">
                        <div class="cart-help">Total Cab Price including GST</div>
                        <div class="amount" data-cart-order-total><?php echo formatPriceINR($cartSummary['total']); ?></div>
                    </div>

                    <form method="POST" action="<?php echo navUrl('cart'); ?>" id="cartCheckoutForm">
                        <input type="hidden" name="action" value="checkout">
                        <input type="hidden" name="cart_sync" id="cartSyncPayload" value="">
                        <input type="hidden" name="return_url" value="<?php echo htmlspecialchars(navUrl('cart')); ?>">

                        <div class="cart-field">
                            <label class="cart-form-label" for="cartGuestName"><i class="fas fa-user"></i> Name</label>
                            <input type="text" name="guest_name" id="cartGuestName" class="form-control" value="<?php echo htmlspecialchars($prefillName); ?>" required>
                        </div>

                        <div class="cart-field">
                            <label class="cart-form-label" for="cartGuestEmail"><i class="fas fa-envelope"></i> Email</label>
                            <input type="email" name="guest_email" id="cartGuestEmail" class="form-control" value="<?php echo htmlspecialchars($prefillEmail); ?>" required
                                   <?php echo ($isLoggedIn || $prefillEmailVerified) ? 'readonly' : ''; ?>>
                            <?php if (!$isLoggedIn && $emailOtpEnabled): ?>
                                <small class="cart-help d-block mt-2" id="cartEmailOtpHelp">
                                    OTP will come to this email when you proceed to payment.
                                </small>
                                <div id="cartEmailVerifiedBadge" class="cart-email-verified<?php echo $prefillEmailVerified ? '' : ' d-none'; ?>" data-email-verified="<?php echo $prefillEmailVerified ? '1' : '0'; ?>">
                                    <span class="badge bg-success"><i class="fas fa-check me-1"></i> Email verified</span>
                                </div>
                            <?php elseif (!$isLoggedIn): ?>
                                <small class="cart-help text-warning d-block mt-2">Guest checkout requires email OTP. Please contact support.</small>
                            <?php endif; ?>
                        </div>

                        <div class="cart-field">
                            <label class="cart-form-label" for="cartGuestPhone"><i class="fas fa-phone"></i> Phone</label>
                            <input type="text" name="guest_phone" id="cartGuestPhone" class="form-control" value="<?php echo htmlspecialchars($prefillPhone); ?>" required>
                        </div>

                        <div class="cart-field">
                            <label class="cart-form-label" for="cartSpecialRequirements"><i class="fas fa-comment-dots"></i> Special Requirements</label>
                            <textarea name="special_requirements" id="cartSpecialRequirements" class="form-control" rows="3" placeholder="Optional"><?php echo htmlspecialchars($prefillSpecialRequirements); ?></textarea>
                        </div>

                        <div class="cart-field cart-gst-block">
                            <div class="cart-gst-check">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="claim_gst" id="cartClaimGst" value="1" <?php echo $prefillClaimGst ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="cartClaimGst">
                                        <i class="fas fa-file-invoice"></i> Claim GST <span class="cart-gst-optional">(optional)</span>
                                    </label>
                                </div>
                            </div>
                            <div class="cart-gst-number-wrap<?php echo $prefillClaimGst ? '' : ' d-none'; ?>" id="cartGstNumberWrap">
                                <label class="cart-form-label" for="cartGstNumber"><i class="fas fa-hashtag"></i> GST Number</label>
                                <input type="text" name="gst_number" id="cartGstNumber" class="form-control"
                                       maxlength="15" placeholder="Enter 15-digit GSTIN"
                                       value="<?php echo htmlspecialchars($prefillGstNumber); ?>"
                                       <?php echo $prefillClaimGst ? 'required' : ''; ?>
                                       autocomplete="off" style="text-transform:uppercase;">
                                <small class="cart-help">Enter your 15-character GSTIN to claim GST on this invoice.</small>
                            </div>
                        </div>

                        <div class="cart-field cart-payment-block">
                            <label class="cart-form-label"><i class="fas fa-credit-card"></i> Payment Method</label>
                            <div class="payment-options">
                                <label class="payment-option<?php echo $razorpayEnabled ? ' is-active' : ' is-disabled'; ?>">
                                    <input type="radio" name="payment_method" value="razorpay"<?php echo $razorpayEnabled ? ' checked' : ' disabled'; ?>>
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
                                <input class="form-check-input" type="checkbox" name="accept_terms" id="cartAcceptTerms" value="1" <?php echo $prefillAcceptTerms ? 'checked' : ''; ?> required>
                                <label class="form-check-label" for="cartAcceptTerms">
                                    I agree to the <a href="<?php echo navUrl('terms-conditions'); ?>" target="_blank" rel="noopener">Terms of Service</a> and <a href="<?php echo navUrl('privacy-policy'); ?>" target="_blank" rel="noopener">Privacy Policy</a>
                                </label>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100" id="cartCheckoutSubmitBtn"><?php echo htmlspecialchars($checkoutButtonLabel); ?></button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php if (!$isLoggedIn && $emailOtpEnabled && !empty($cartItems)): ?>
<div class="cart-otp-modal" id="cartOtpModal" hidden>
    <div class="cart-otp-modal__backdrop" data-cart-otp-close></div>
    <div class="cart-otp-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="cartOtpModalTitle">
        <button type="button" class="cart-otp-modal__close" data-cart-otp-close aria-label="Close">&times;</button>
        <h3 id="cartOtpModalTitle">Verify email OTP</h3>
        <p class="cart-otp-modal__text" id="cartOtpModalText">Enter the 5-digit OTP sent to your email to continue to payment.</p>
        <div id="cartEmailOtpAlert" class="alert d-none" role="alert"></div>
        <label class="cart-form-label" for="cartEmailOtp"><i class="fas fa-key"></i> Enter OTP</label>
        <input type="text" id="cartEmailOtp" class="form-control auth-otp-input otp-input mb-3"
               maxlength="5" pattern="[0-9]{5}" placeholder="5-digit code" inputmode="numeric" autocomplete="one-time-code">
        <div class="cart-otp-modal__actions">
            <button type="button" class="btn btn-primary w-100" id="cartVerifyEmailOtpBtn">
                <i class="fas fa-check-circle me-1"></i> Verify &amp; Continue to Payment
            </button>
            <button type="button" class="btn btn-outline-secondary w-100" id="cartResendEmailOtpBtn">
                Resend OTP
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
<script>
window.__cartEmailOtpEnabled = <?php echo (!$isLoggedIn && $emailOtpEnabled && !empty($cartItems)) ? 'true' : 'false'; ?>;
window.__cartEmailVerified = <?php echo $prefillEmailVerified ? 'true' : 'false'; ?>;
window.__cartFinishCheckout = null;

<?php if (!$isLoggedIn && $emailOtpEnabled && !empty($cartItems)): ?>
(function() {
    var sendUrl = <?php echo json_encode(BASE_URL . 'api/email-send-otp.php'); ?>;
    var verifyUrl = <?php echo json_encode(BASE_URL . 'api/email-verify-otp.php'); ?>;
    var emailInput = document.getElementById('cartGuestEmail');
    var otpInput = document.getElementById('cartEmailOtp');
    var alertBox = document.getElementById('cartEmailOtpAlert');
    var verifiedBadge = document.getElementById('cartEmailVerifiedBadge');
    var modal = document.getElementById('cartOtpModal');
    var modalText = document.getElementById('cartOtpModalText');
    var submitBtn = document.getElementById('cartCheckoutSubmitBtn');
    var emailVerified = <?php echo $prefillEmailVerified ? 'true' : 'false'; ?>;
    var activeEmail = emailVerified ? (emailInput ? emailInput.value.trim() : '') : '';
    var sending = false;
    var verifying = false;

    function showAlert(type, message) {
        if (!alertBox) return;
        alertBox.className = 'alert alert-' + type;
        alertBox.textContent = message;
        alertBox.classList.remove('d-none');
    }

    function hideAlert() {
        if (alertBox) alertBox.classList.add('d-none');
    }

    function openModal() {
        if (!modal) return;
        modal.hidden = false;
        document.body.classList.add('cart-otp-modal-open');
        if (otpInput) {
            otpInput.value = '';
            setTimeout(function() { otpInput.focus(); }, 50);
        }
    }

    function closeModal() {
        if (!modal) return;
        modal.hidden = true;
        document.body.classList.remove('cart-otp-modal-open');
        hideAlert();
        if (submitBtn) {
            submitBtn.disabled = false;
            if (submitBtn.dataset.originalText) {
                submitBtn.textContent = submitBtn.dataset.originalText;
            }
        }
    }

    function markVerified(email, loggedIn) {
        emailVerified = true;
        window.__cartEmailVerified = true;
        activeEmail = email;
        if (verifiedBadge) {
            verifiedBadge.classList.remove('d-none');
            verifiedBadge.setAttribute('data-email-verified', '1');
        }
        if (emailInput) emailInput.readOnly = true;
        if (submitBtn) submitBtn.textContent = loggedIn ? 'Book Now' : 'Proceed to Payment';
    }

    function resetVerification() {
        emailVerified = false;
        window.__cartEmailVerified = false;
        activeEmail = '';
        if (verifiedBadge) {
            verifiedBadge.classList.add('d-none');
            verifiedBadge.setAttribute('data-email-verified', '0');
        }
        if (emailInput) emailInput.readOnly = false;
        if (otpInput) otpInput.value = '';
        if (submitBtn) submitBtn.textContent = 'Proceed to Payment';
        hideAlert();
    }

    function sendOtp(options) {
        options = options || {};
        hideAlert();
        var email = emailInput ? emailInput.value.trim() : '';
        if (!email || email.indexOf('@') < 1) {
            showAlert('danger', 'Please enter a valid email address first');
            if (typeof options.onError === 'function') options.onError(new Error('Invalid email'));
            return Promise.reject(new Error('Invalid email'));
        }
        if (sending) return Promise.resolve();
        sending = true;

        var resendBtn = document.getElementById('cartResendEmailOtpBtn');
        if (resendBtn && options.fromResend) {
            resendBtn.disabled = true;
            resendBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Sending...';
        }

        return fetch(sendUrl, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ email: email, purpose: 'checkout' })
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (!data.success) throw new Error(data.message || 'Unable to send OTP');
            activeEmail = data.email || email;
            if (modalText) {
                modalText.textContent = 'We sent a 5-digit OTP to ' + activeEmail + '. Enter it below to continue to payment.';
            }
            showAlert('success', data.message || 'OTP sent to your email');
            if (typeof options.onSuccess === 'function') options.onSuccess(data);
            return data;
        })
        .catch(function(err) {
            showAlert('danger', err.message || 'Unable to send OTP');
            if (typeof options.onError === 'function') options.onError(err);
            throw err;
        })
        .finally(function() {
            sending = false;
            if (resendBtn) {
                resendBtn.disabled = false;
                resendBtn.innerHTML = 'Resend OTP';
            }
        });
    }

    function verifyOtp() {
        hideAlert();
        var otp = (otpInput && otpInput.value ? otpInput.value : '').replace(/\D/g, '');
        var email = activeEmail || (emailInput ? emailInput.value.trim() : '');
        if (!email || otp.length !== 5) {
            showAlert('danger', 'Please enter the 5-digit verification code');
            return;
        }
        if (verifying) return;
        verifying = true;

        var btn = document.getElementById('cartVerifyEmailOtpBtn');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Verifying...';
        }

        fetch(verifyUrl, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ email: email, otp: otp, purpose: 'checkout' })
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (!data.success) throw new Error(data.message || 'OTP verification failed');
            markVerified(data.email || email, !!data.logged_in);
            closeModal();
            if (typeof window.__cartFinishCheckout === 'function') {
                window.__cartFinishCheckout();
            }
        })
        .catch(function(err) {
            showAlert('danger', err.message || 'OTP verification failed');
        })
        .finally(function() {
            verifying = false;
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check-circle me-1"></i> Verify & Continue to Payment';
            }
        });
    }

    window.__cartStartEmailOtp = function() {
        openModal();
        return sendOtp({});
    };

    window.__cartIsEmailVerified = function() {
        return !!emailVerified || window.__cartEmailVerified === true;
    };

    if (emailInput) {
        emailInput.addEventListener('input', function() {
            var current = emailInput.value.trim().toLowerCase();
            if (emailVerified && activeEmail && current !== activeEmail.toLowerCase()) {
                resetVerification();
            }
        });
    }

    document.querySelectorAll('[data-cart-otp-close]').forEach(function(el) {
        el.addEventListener('click', closeModal);
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal && !modal.hidden) closeModal();
    });

    var verifyBtn = document.getElementById('cartVerifyEmailOtpBtn');
    var resendBtn = document.getElementById('cartResendEmailOtpBtn');
    if (verifyBtn) verifyBtn.addEventListener('click', verifyOtp);
    if (resendBtn) resendBtn.addEventListener('click', function() { sendOtp({ fromResend: true }); });
    if (otpInput) {
        otpInput.addEventListener('keyup', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                verifyOtp();
            }
        });
    }
})();
<?php endif; ?>
(function() {
    var claimGst = document.getElementById('cartClaimGst');
    var gstWrap = document.getElementById('cartGstNumberWrap');
    var gstInput = document.getElementById('cartGstNumber');
    if (!claimGst || !gstWrap || !gstInput) return;

    function syncGstField() {
        var on = !!claimGst.checked;
        gstWrap.classList.toggle('d-none', !on);
        gstInput.required = on;
        if (!on) {
            gstInput.value = '';
        }
    }

    claimGst.addEventListener('change', syncGstField);
    gstInput.addEventListener('input', function() {
        gstInput.value = gstInput.value.toUpperCase().replace(/[^0-9A-Z]/g, '').slice(0, 15);
    });
    syncGstField();
})();

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

function formatInr(amount) {
    try {
        return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 0 }).format(amount);
    } catch (e) {
        return '₹' + Math.round(amount).toLocaleString('en-IN');
    }
}

function updateOrderTotalDisplay(total) {
    const el = document.querySelector('[data-cart-order-total]');
    if (!el) return;
    el.textContent = formatInr(Number(total) || 0);
}

function recalculateOrderTotalFromCards() {
    let total = 0;
    document.querySelectorAll('[data-cart-tour-item]').forEach(function(card) {
        const selected = card.querySelector('[data-cart-cab-radio]:checked:not(:disabled)');
        if (selected && selected.value !== '') {
            total += parseFloat(selected.getAttribute('data-cab-price') || '0') || 0;
        }
    });
    updateOrderTotalDisplay(total);
}

document.querySelectorAll('[data-people-stepper]').forEach(function(stepper) {
    const select = stepper.querySelector('[data-people-select]');
    const display = stepper.querySelector('[data-people-display]');
    const min = parseInt(stepper.getAttribute('data-min') || '1', 10);
    const max = parseInt(stepper.getAttribute('data-max') || '99', 10);

    function syncPeopleDisplay() {
        if (!select || !display) return;
        display.textContent = select.value;
    }

    function setPeople(value) {
        if (!select) return;
        const next = Math.min(max, Math.max(min, value));
        select.value = String(next);
        syncPeopleDisplay();
        select.dispatchEvent(new Event('change', { bubbles: true }));
    }

    stepper.querySelectorAll('[data-step]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const step = parseInt(btn.getAttribute('data-step') || '0', 10);
            setPeople(parseInt(select.value || String(min), 10) + step);
        });
    });

    if (select) {
        select.addEventListener('change', syncPeopleDisplay);
    }
    syncPeopleDisplay();
});

document.querySelectorAll('[data-cart-cab-picker]').forEach(function(picker) {
    const card = picker.closest('[data-cart-tour-item]');
    const pickupWrap = card ? card.querySelector('[data-cart-pickup-wrap]') : null;
    const pickupSel = card ? card.querySelector('[data-cart-pickup-place]') : null;
    const detailWrap = card ? card.querySelector('[data-cart-pickup-detail-wrap]') : null;
    const detailInp = card ? card.querySelector('[data-cart-pickup-detail]') : null;
    const addressInp = card ? card.querySelector('[data-cart-pickup-address]') : null;
    const pickupTimeInp = card ? card.querySelector('[data-cart-pickup-time]') : null;
    const totalStatus = card ? card.querySelector('[data-cart-total-status]') : null;
    const totalAmount = card ? card.querySelector('[data-cart-total-amount]') : null;
    const peopleSelect = card ? card.querySelector('[data-people-select]') : null;
    const form = card ? card.querySelector('[data-cart-tour-form]') : null;
    const cabHidden = form ? form.querySelector('[data-cart-cab-hidden]') : null;

    function selectedCabRadio() {
        return picker.querySelector('[data-cart-cab-radio]:checked:not(:disabled)');
    }

    function syncCabHidden() {
        if (!cabHidden) return;
        const selected = selectedCabRadio();
        cabHidden.value = selected && selected.value ? selected.value : '';
    }

    function syncTotalStatus() {
        const selected = selectedCabRadio();
        if (!totalStatus) return;
        if (!selected || selected.value === '') {
            totalStatus.textContent = 'Select a cab to see price';
            if (totalAmount) {
                totalAmount.textContent = '';
                totalAmount.setAttribute('hidden', '');
            }
            recalculateOrderTotalFromCards();
            return;
        }
        const label = selected.getAttribute('data-cab-label') || 'Cab selected';
        const price = parseFloat(selected.getAttribute('data-cab-price') || '0');
        totalStatus.textContent = label;
        if (totalAmount) {
            totalAmount.textContent = formatInr(price);
            totalAmount.removeAttribute('hidden');
        }
        recalculateOrderTotalFromCards();
    }

    function syncPickupDetail() {
        if (!pickupSel || !detailWrap || !detailInp) return;
        const place = pickupSel.value;
        const needsDetail = place === 'Hotel' || place === 'Others';
        const needsAddress = place === 'Hotel';
        if (needsDetail) {
            detailWrap.removeAttribute('hidden');
            detailInp.placeholder = place === 'Hotel' ? 'Hotel name' : 'Enter location details';
            detailInp.setAttribute('required', 'required');
        } else {
            detailWrap.setAttribute('hidden', '');
            detailInp.removeAttribute('required');
            // Keep Lift Parking clean; only clear Hotel/Others detail when leaving those options
            if (place !== 'Hotel' && place !== 'Others') {
                detailInp.value = '';
                detailInp.placeholder = '';
            }
        }
        if (addressInp) {
            if (needsAddress) {
                addressInp.removeAttribute('hidden');
                addressInp.setAttribute('required', 'required');
            } else {
                addressInp.setAttribute('hidden', '');
                addressInp.removeAttribute('required');
                addressInp.value = '';
            }
        }
    }

    function syncCabSelection() {
        const selected = selectedCabRadio();
        const hasCab = !!(selected && selected.value !== '');
        syncCabHidden();
        if (!pickupWrap) return;
        if (hasCab) {
            pickupWrap.removeAttribute('hidden');
            pickupWrap.classList.add('is-visible');
            syncPickupDetail();
            if (pickupSel) pickupSel.setAttribute('required', 'required');
            if (pickupTimeInp) pickupTimeInp.setAttribute('required', 'required');
            return;
        }
        pickupWrap.setAttribute('hidden', '');
        pickupWrap.classList.remove('is-visible');
        if (pickupSel) {
            pickupSel.value = '';
            pickupSel.removeAttribute('required');
            pickupSel.classList.remove('is-invalid');
        }
        if (detailInp) {
            detailInp.value = '';
            detailInp.removeAttribute('required');
            detailInp.classList.remove('is-invalid');
        }
        if (addressInp) {
            addressInp.value = '';
            addressInp.setAttribute('hidden', '');
            addressInp.removeAttribute('required');
            addressInp.classList.remove('is-invalid');
        }
        if (detailWrap) detailWrap.setAttribute('hidden', '');
        if (pickupTimeInp) {
            pickupTimeInp.value = '';
            pickupTimeInp.removeAttribute('required');
            pickupTimeInp.classList.remove('is-invalid');
        }
    }

    function syncCabCapacity() {
        const people = peopleSelect ? (parseInt(peopleSelect.value || '0', 10) || 0) : 0;
        const radios = Array.from(picker.querySelectorAll('[data-cart-cab-radio]'));
        let selected = selectedCabRadio();
        let changed = false;

        radios.forEach(function(radio) {
            const capacity = parseInt(radio.getAttribute('data-cab-capacity') || '0', 10) || 0;
            const fits = capacity <= 0 || people <= 0 || capacity >= people;
            const label = radio.closest('.cart-cab-option');
            radio.disabled = !fits;
            if (label) {
                label.classList.toggle('is-unavailable', !fits);
            }
            if (!fits && radio.checked) {
                radio.checked = false;
                selected = null;
                changed = true;
            }
        });

        if (!selected || selected.disabled) {
            const next = radios.find(function(radio) { return !radio.disabled; });
            if (next) {
                next.checked = true;
                changed = true;
            }
        }

        syncCabSelection();
        syncTotalStatus();

        if (changed && form) {
            form.dispatchEvent(new CustomEvent('cart-cab-capacity-changed', { bubbles: true }));
        }
    }

    picker.querySelectorAll('[data-cart-cab-radio]').forEach(function(radio) {
        radio.addEventListener('change', function() {
            syncCabSelection();
            syncTotalStatus();
        });
    });
    if (pickupSel) {
        pickupSel.addEventListener('change', syncPickupDetail);
    }
    if (peopleSelect) {
        peopleSelect.addEventListener('change', syncCabCapacity);
    }
    syncCabCapacity();
});

document.querySelectorAll('[data-cart-tour-form]').forEach(function(form) {
    let saveTimer = null;
    let savePromise = Promise.resolve();
    const actionInput = form.querySelector('[data-cart-action]');
    const ajaxInput = form.querySelector('[data-cart-ajax]');
    const removeBtn = form.querySelector('[data-cart-remove]');
    const card = form.closest('[data-cart-tour-item]');
    const totalAmount = card ? card.querySelector('[data-cart-total-amount]') : null;

    function saveCartItem() {
        if (saveTimer) {
            clearTimeout(saveTimer);
            saveTimer = null;
        }
        if (actionInput) actionInput.value = 'add';
        if (ajaxInput) ajaxInput.value = '1';

        // Keep hidden cab_type in sync (radios use unique names per tour)
        const checkedCab = form.querySelector('[data-cart-cab-radio]:checked:not(:disabled)');
        const cabHidden = form.querySelector('[data-cart-cab-hidden]');
        if (cabHidden) {
            cabHidden.value = checkedCab && checkedCab.value ? checkedCab.value : String(cabHidden.value || '');
        }

        const body = new FormData(form);
        savePromise = fetch(form.action, {
            method: 'POST',
            body: body,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            credentials: 'same-origin'
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (!data || !data.success) {
                const dateInput = form.querySelector('[name="tour_date"]');
                if (dateInput && data && data.tour_date) {
                    dateInput.value = data.tour_date;
                }
                const msg = (data && data.message) ? data.message : 'Unable to update cart';
                let alertBox = document.getElementById('cartValidationAlert');
                if (!alertBox) {
                    alertBox = document.createElement('div');
                    alertBox.id = 'cartValidationAlert';
                    alertBox.className = 'alert alert-danger cart-validation-alert';
                    alertBox.style.marginBottom = '20px';
                    const wrap = document.querySelector('.cart-wrapper .container');
                    if (wrap) wrap.insertBefore(alertBox, wrap.firstChild);
                }
                alertBox.innerHTML = '<strong>Cannot use this date:</strong><ul class="mb-0 mt-2"><li>' +
                    String(msg).replace(/</g, '&lt;') + '</li></ul>';
                alertBox.classList.remove('d-none');
                alertBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return data;
            }
            const okAlert = document.getElementById('cartValidationAlert');
            if (okAlert && /same day|already have|already booked/i.test(okAlert.textContent || '')) {
                okAlert.classList.add('d-none');
                okAlert.innerHTML = '';
            }
            if (typeof data.line_total === 'number' && totalAmount) {
                if (data.line_total > 0) {
                    totalAmount.textContent = formatInr(data.line_total);
                    totalAmount.removeAttribute('hidden');
                } else {
                    totalAmount.textContent = '';
                    totalAmount.setAttribute('hidden', '');
                }
            }
            if (typeof data.order_total === 'number') {
                updateOrderTotalDisplay(data.order_total);
            } else {
                recalculateOrderTotalFromCards();
            }
            return data;
        })
        .catch(function() {
            recalculateOrderTotalFromCards();
            return null;
        });
        return savePromise;
    }

    form._saveCartItem = saveCartItem;
    form._getSavePromise = function() { return savePromise; };

    function saveCartItemDebounced() {
        if (saveTimer) clearTimeout(saveTimer);
        saveTimer = setTimeout(saveCartItem, 500);
    }

    const dateInput = form.querySelector('[name="tour_date"]');
    if (dateInput) {
        dateInput.dataset.lastValidDate = dateInput.value || '';
        dateInput.addEventListener('change', function() {
            const conflict = findCartDateConflictForCard(card, dateInput.value);
            if (conflict) {
                showCartDateConflictAlert(conflict);
                dateInput.value = dateInput.dataset.lastValidDate || '';
                return;
            }
            saveCartItem().then(function(data) {
                if (data && data.success) {
                    dateInput.dataset.lastValidDate = dateInput.value || '';
                }
            });
        });
    }

    const peopleSelect = form.querySelector('[data-people-select]');
    if (peopleSelect) peopleSelect.addEventListener('change', saveCartItem);

    form.querySelectorAll('[data-cart-cab-radio]').forEach(function(radio) {
        radio.addEventListener('change', function() {
            const hidden = form.querySelector('[data-cart-cab-hidden]');
            if (hidden) hidden.value = radio.checked ? radio.value : (hidden.value || '');
            saveCartItem();
            if (window.__cartSubmitAttempted) setTimeout(refreshCartValidationAlert, 0);
        });
    });

    form.addEventListener('cart-cab-capacity-changed', function() {
        saveCartItem();
        if (window.__cartSubmitAttempted) setTimeout(refreshCartValidationAlert, 0);
    });

    const pickupPlace = form.querySelector('[data-cart-pickup-place]');
    if (pickupPlace) pickupPlace.addEventListener('change', function() {
        saveCartItem();
        if (window.__cartSubmitAttempted) refreshCartValidationAlert();
    });

    const pickupTime = form.querySelector('[data-cart-pickup-time]');
    if (pickupTime) pickupTime.addEventListener('change', function() {
        saveCartItem();
        if (window.__cartSubmitAttempted) refreshCartValidationAlert();
    });

    form.querySelectorAll('[data-cart-pickup-detail], [data-cart-pickup-address]').forEach(function(input) {
        input.addEventListener('input', function() {
            saveCartItemDebounced();
            if (window.__cartSubmitAttempted) refreshCartValidationAlert();
        });
        input.addEventListener('blur', saveCartItem);
    });

    if (removeBtn) {
        removeBtn.addEventListener('click', function() {
            // Bypass pickup/location required validation so delete always works
            form.setAttribute('novalidate', 'novalidate');
            form.querySelectorAll('[required]').forEach(function(el) {
                el.removeAttribute('required');
            });
            if (actionInput) actionInput.value = 'remove';
            if (ajaxInput) ajaxInput.value = '0';
        });
    }
});

function cartDateRange(startDate, durationDays) {
    if (!startDate || !/^\d{4}-\d{2}-\d{2}$/.test(startDate)) return null;
    const days = Math.max(1, parseInt(durationDays || '1', 10) || 1);
    const start = new Date(startDate + 'T00:00:00');
    if (isNaN(start.getTime())) return null;
    const end = new Date(start.getTime());
    end.setDate(end.getDate() + days - 1);
    const pad = function(n) { return String(n).padStart(2, '0'); };
    const endStr = end.getFullYear() + '-' + pad(end.getMonth() + 1) + '-' + pad(end.getDate());
    return { start: startDate, end: endStr };
}

function cartRangesOverlap(a, b) {
    return !!(a && b && a.start <= b.end && b.start <= a.end);
}

function findCartDateConflictForCard(card, nextDate) {
    if (!card || !nextDate) return '';
    const selfId = card.getAttribute('data-tour-id') || '';
    const selfDays = card.getAttribute('data-duration-days') || '1';
    const selfTitle = card.getAttribute('data-tour-title') || 'this tour';
    const selfRange = cartDateRange(nextDate, selfDays);
    if (!selfRange) return '';

    let message = '';
    document.querySelectorAll('[data-cart-tour-item]').forEach(function(other) {
        if (message) return;
        if (other === card) return;
        const otherId = other.getAttribute('data-tour-id') || '';
        if (selfId && otherId && selfId === otherId) return;
        const otherDateInput = other.querySelector('[name="tour_date"]');
        const otherDate = otherDateInput ? otherDateInput.value.trim() : '';
        const otherRange = cartDateRange(otherDate, other.getAttribute('data-duration-days') || '1');
        if (!cartRangesOverlap(selfRange, otherRange)) return;
        const otherTitle = other.getAttribute('data-tour-title') || 'another tour';
        message = 'You already selected "' + otherTitle + '" on ' + otherDate +
            '. Multiple tours are allowed, but not on the same date. Please choose a different date for "' + selfTitle + '".';
    });
    return message;
}

function showCartDateConflictAlert(message) {
    let alertBox = document.getElementById('cartValidationAlert');
    if (!alertBox) {
        alertBox = document.createElement('div');
        alertBox.id = 'cartValidationAlert';
        alertBox.className = 'alert alert-danger cart-validation-alert';
        alertBox.style.marginBottom = '20px';
        const wrap = document.querySelector('.cart-wrapper .container');
        if (wrap) wrap.insertBefore(alertBox, wrap.firstChild);
    }
    alertBox.innerHTML = '<strong>Date not available:</strong><ul class="mb-0 mt-2"><li>' +
        String(message || '').replace(/</g, '&lt;') + '</li></ul>';
    alertBox.classList.remove('d-none');
    alertBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function collectCartSyncPayload() {
    const items = [];
    document.querySelectorAll('[data-cart-tour-form]').forEach(function(form) {
        const tourIdInput = form.querySelector('input[name="tour_id"]');
        const tourId = tourIdInput ? parseInt(tourIdInput.value || '0', 10) : 0;
        if (!tourId) return;
        const cabChecked = form.querySelector('[data-cart-cab-radio]:checked:not(:disabled)');
        const cabHidden = form.querySelector('[data-cart-cab-hidden]');
        const dateInput = form.querySelector('[name="tour_date"]');
        const peopleSelect = form.querySelector('[name="people"], [data-people-select]');
        const pickupSel = form.querySelector('[data-cart-pickup-place]');
        const pickupTime = form.querySelector('[data-cart-pickup-time]');
        const detailInp = form.querySelector('[data-cart-pickup-detail]');
        const addressInp = form.querySelector('[data-cart-pickup-address]');
        const cabType = cabChecked && cabChecked.value
            ? cabChecked.value
            : (cabHidden ? String(cabHidden.value || '').trim() : '');
        items.push({
            tour_id: tourId,
            tour_date: dateInput ? dateInput.value : '',
            people: peopleSelect ? peopleSelect.value : '',
            cab_type: cabType,
            pickup_place: pickupSel ? pickupSel.value : '',
            pickup_detail: detailInp ? detailInp.value : '',
            pickup_address: addressInp ? addressInp.value : '',
            pickup_time: pickupTime ? pickupTime.value : ''
        });
    });
    return items;
}

function validateCartTourDetails() {
    const errors = [];
    let firstInvalid = null;

    document.querySelectorAll('[data-cart-tour-item]').forEach(function(card) {
        const form = card.querySelector('[data-cart-tour-form]');
        if (!form) return;

        const title = card.getAttribute('data-tour-title') || 'this tour';
        const cabChecked = form.querySelector('[data-cart-cab-radio]:checked:not(:disabled)');
        const cabHidden = form.querySelector('[data-cart-cab-hidden]');
        const pickupSel = form.querySelector('[data-cart-pickup-place]');
        const pickupTime = form.querySelector('[data-cart-pickup-time]');
        const detailInp = form.querySelector('[data-cart-pickup-detail]');
        const addressInp = form.querySelector('[data-cart-pickup-address]');
        const dateInp = form.querySelector('[name="tour_date"]');
        const errorBox = form.querySelector('[data-cart-pickup-error]');
        const pickupWrap = form.querySelector('[data-cart-pickup-wrap]');
        let cardError = '';
        const cabValue = cabChecked && cabChecked.value
            ? cabChecked.value
            : (cabHidden ? String(cabHidden.value || '').trim() : '');

        card.classList.remove('is-incomplete');
        if (pickupSel) pickupSel.classList.remove('is-invalid');
        if (pickupTime) pickupTime.classList.remove('is-invalid');
        if (detailInp) detailInp.classList.remove('is-invalid');
        if (addressInp) addressInp.classList.remove('is-invalid');
        if (dateInp) dateInp.classList.remove('is-invalid');
        if (errorBox) {
            errorBox.textContent = '';
            errorBox.classList.add('d-none');
        }

        const dateConflict = findCartDateConflictForCard(card, dateInp ? dateInp.value.trim() : '');
        if (dateConflict) {
            cardError = dateConflict;
            if (dateInp) dateInp.classList.add('is-invalid');
        } else if (!cabValue) {
            cardError = 'Please select a cab option for: ' + title;
        } else {
            // Cab selected → pickup fields are required
            if (pickupWrap) {
                pickupWrap.removeAttribute('hidden');
                pickupWrap.classList.add('is-visible');
            }
            if (cabHidden) cabHidden.value = cabValue;
            const place = pickupSel ? pickupSel.value.trim() : '';
            const time = pickupTime ? pickupTime.value.trim() : '';
            if (!place) {
                cardError = 'Please select a pickup point for: ' + title;
                if (pickupSel) pickupSel.classList.add('is-invalid');
            } else if (place === 'Hotel') {
                if (detailInp && !detailInp.value.trim()) {
                    cardError = 'Please enter the hotel name for: ' + title;
                    detailInp.classList.add('is-invalid');
                } else if (addressInp && !addressInp.value.trim()) {
                    cardError = 'Please enter the hotel full address with location for: ' + title;
                    addressInp.classList.add('is-invalid');
                }
            } else if (place === 'Others' && detailInp && !detailInp.value.trim()) {
                cardError = 'Please enter pickup details for: ' + title;
                detailInp.classList.add('is-invalid');
            }
            if (!cardError && !time) {
                cardError = 'Please select a pickup time for: ' + title;
                if (pickupTime) pickupTime.classList.add('is-invalid');
            }
        }

        if (cardError) {
            errors.push(cardError);
            card.classList.add('is-incomplete');
            if (errorBox) {
                errorBox.textContent = cardError;
                errorBox.classList.remove('d-none');
            }
            if (!firstInvalid) firstInvalid = card;
        }
    });

    return { errors: errors, firstInvalid: firstInvalid };
}

function refreshCartValidationAlert() {
    const result = validateCartTourDetails();
    let alertBox = document.getElementById('cartValidationAlert');
    if (!result.errors.length) {
        if (alertBox) {
            alertBox.classList.add('d-none');
            alertBox.innerHTML = '';
        }
        return result;
    }
    if (!alertBox) {
        alertBox = document.createElement('div');
        alertBox.id = 'cartValidationAlert';
        alertBox.className = 'alert alert-danger cart-validation-alert';
        alertBox.style.marginBottom = '20px';
        const wrap = document.querySelector('.cart-wrapper .container');
        if (wrap) wrap.insertBefore(alertBox, wrap.firstChild);
    }
    alertBox.innerHTML = '<strong>Please complete these tour details:</strong><ul class="mb-0 mt-2">' +
        result.errors.map(function(err) { return '<li>' + err.replace(/</g, '&lt;') + '</li>'; }).join('') +
        '</ul>';
    alertBox.classList.remove('d-none');
    return result;
}

(function() {
    const checkoutForm = document.getElementById('cartCheckoutForm');
    if (!checkoutForm) return;
    const syncInput = document.getElementById('cartSyncPayload');
    const submitBtn = document.getElementById('cartCheckoutSubmitBtn');

    function finishCheckout() {
        const syncItems = collectCartSyncPayload();
        if (syncInput) {
            syncInput.value = JSON.stringify(syncItems);
        }

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.dataset.originalText = submitBtn.dataset.originalText || submitBtn.textContent || '';
            submitBtn.textContent = 'Processing...';
        }

        const forms = Array.from(document.querySelectorAll('[data-cart-tour-form]'));
        const saves = forms.map(function(form) {
            if (typeof form._saveCartItem === 'function') {
                return form._saveCartItem();
            }
            return Promise.resolve();
        });

        Promise.all(saves).then(function() {
            HTMLFormElement.prototype.submit.call(checkoutForm);
        }).catch(function() {
            HTMLFormElement.prototype.submit.call(checkoutForm);
        });
    }

    window.__cartFinishCheckout = finishCheckout;

    checkoutForm.addEventListener('submit', function(e) {
        e.preventDefault();
        window.__cartSubmitAttempted = true;

        if (!checkoutForm.checkValidity()) {
            checkoutForm.reportValidity();
            return;
        }

        const result = refreshCartValidationAlert();
        if (result.errors.length) {
            if (result.firstInvalid) {
                const pickupWrap = result.firstInvalid.querySelector('[data-cart-pickup-wrap]');
                if (pickupWrap) {
                    pickupWrap.removeAttribute('hidden');
                    pickupWrap.classList.add('is-visible', 'is-attention');
                }
                const badField = result.firstInvalid.querySelector('.is-invalid, [data-cart-pickup-place], [data-cart-pickup-time], [data-cart-pickup-wrap]');
                if (badField && typeof badField.focus === 'function') {
                    try { badField.focus(); } catch (err) {}
                }
                result.firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
            } else {
                const alertBox = document.getElementById('cartValidationAlert');
                if (alertBox) alertBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            return;
        }

        const needsOtp = !!window.__cartEmailOtpEnabled && !(
            (typeof window.__cartIsEmailVerified === 'function' && window.__cartIsEmailVerified())
            || window.__cartEmailVerified === true
        );

        if (needsOtp) {
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.dataset.originalText = submitBtn.textContent || '';
                submitBtn.textContent = 'Sending OTP...';
            }
            if (typeof window.__cartStartEmailOtp === 'function') {
                window.__cartStartEmailOtp().catch(function() {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = submitBtn.dataset.originalText || 'Proceed to Payment';
                    }
                });
            }
            return;
        }

        finishCheckout();
    });
})();

(function() {
    const incomplete = document.querySelector('#cartValidationAlert:not(.d-none)');
    if (incomplete) {
        setTimeout(function() {
            incomplete.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, 200);
    }
})();

recalculateOrderTotalFromCards();
</script>

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

        $_SESSION['tour_cart'] = [];
        unset($_SESSION['cart_checkout_draft'], $_SESSION['cart_checkout_pending']);

        if ($paymentMethod === 'razorpay') {
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
            header('Content-Type: application/json');
            echo json_encode([
                'success' => !empty($result['ok']),
                'message' => $result['message'] ?? '',
                'tour_id' => (int) $tourId,
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

        $guestName = trim((string)($_POST['guest_name'] ?? ''));
        $guestEmail = trim((string)($_POST['guest_email'] ?? ''));
        $guestPhone = trim((string)($_POST['guest_phone'] ?? ''));
        $specialRequirements = trim((string)($_POST['special_requirements'] ?? ''));
        $paymentMethod = trim((string)($_POST['payment_method'] ?? 'razorpay'));

        $errors = [];
        if ($guestName === '') $errors[] = 'Your name is required';
        if ($guestEmail === '' || !filter_var($guestEmail, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required';
        if ($guestPhone === '') $errors[] = 'Your phone number is required';
        if ($paymentMethod !== 'razorpay') {
            $errors[] = 'Please select online payment';
        }
        if (!razorpayIsConfigured()) {
            $errors[] = 'Online payment is not available right now. Please try again later.';
        }
        if (empty($_POST['accept_terms'])) {
            $errors[] = 'You must accept the Terms of Service and Privacy Policy to continue';
        }

        if (!empty($errors)) {
            $_SESSION['cart_checkout_draft'] = [
                'guest_name' => $guestName,
                'guest_email' => $guestEmail,
                'guest_phone' => $guestPhone,
                'special_requirements' => $specialRequirements,
                'payment_method' => $paymentMethod,
                'accept_terms' => !empty($_POST['accept_terms']),
            ];
            $_SESSION['cart_flash'] = ['type' => 'error', 'message' => implode(' | ', $errors)];
            $redirectTo(navUrl('cart'));
        }

        // Guests can fill checkout details; login is required only to proceed with payment.
        if (!isUserLoggedIn()) {
            $_SESSION['cart_checkout_pending'] = [
                'name' => $guestName,
                'email' => $guestEmail,
                'phone' => $guestPhone,
                'special_requirements' => $specialRequirements,
                'payment_method' => $paymentMethod,
            ];
            unset($_SESSION['cart_checkout_draft']);
            $_SESSION['cart_flash'] = ['type' => 'info', 'message' => 'Please log in to proceed with payment. Your booking details have been saved.'];
            header('Location: ' . loginUrl(navUrl('cart')));
            exit;
        }

        try {
            $processCartCheckout([
                'name' => $guestName,
                'email' => $guestEmail,
                'phone' => $guestPhone,
                'special_requirements' => $specialRequirements,
            ], $paymentMethod);
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

// Default to first cab when none selected so cart always has a payable price.
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
        $currentCab = (string) ($item['cab_type'] ?? '');
        $valid = false;
        foreach ($tourCabs as $cab) {
            if ($currentCab === (string) $cab['value']) {
                $valid = true;
                break;
            }
        }
        if (!$valid) {
            $_SESSION['tour_cart'][$tourIdStr]['cab_type'] = (string) ($tourCabs[0]['value'] ?? '');
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
$prefillAcceptTerms = !empty($checkoutDraft['accept_terms']);
$razorpayEnabled = razorpayIsConfigured();
$isLoggedIn = isUserLoggedIn();
$checkoutButtonLabel = $isLoggedIn ? 'Book Now' : 'Proceed to Payment';
$checkoutHelpText = $isLoggedIn
    ? 'Complete your booking details and pay online.'
    : 'Fill your details below. You will be asked to log in only when you proceed to payment.';

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
            <?php
            $flashType = (string) ($flash['type'] ?? 'error');
            $flashClass = $flashType === 'success' ? 'alert-success' : ($flashType === 'info' ? 'alert-info' : 'alert-danger');
            ?>
            <div class="alert <?php echo $flashClass; ?>" style="margin-bottom: 20px;">
                <?php echo htmlspecialchars((string)$flash['message']); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($cartSummary['errors'])): ?>
            <div class="alert alert-warning" style="margin-bottom: 20px;">
                <?php echo htmlspecialchars(implode(' | ', $cartSummary['errors'])); ?>
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
                        <div class="cart-help">Changes save automatically. Checkout once for all tours.</div>
                        <form method="POST" action="<?php echo navUrl('cart'); ?>">
                            <input type="hidden" name="action" value="clear">
                            <button type="submit" class="btn btn-outline-danger">Clear Cart</button>
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
                            $selectedPickupTime = (string)($item['pickup_time'] ?? '');
                            if (preg_match('/^(\d{2}:\d{2})/', $selectedPickupTime, $m)) {
                                $selectedPickupTime = $m[1];
                            }
                            $pickupTimeOptions = getPickupTimeOptions();
                            if ($selectedPickupTime !== '' && !isValidPickupTime($selectedPickupTime)) {
                                $selectedPickupTime = '';
                            }
                            $showPickup = $selectedCab !== '';
                            $needsPickupDetail = in_array($selectedPickup, ['Hotel', 'Others'], true);
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
                            ?>
                            <article class="cart-tour-item" data-cart-tour-item data-duration-days="<?php echo max(1, $durationDays); ?>">
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
                                            <label class="cart-field-label">Cab</label>
                                            <div class="cart-cab-grid" data-cart-cab-picker>
                                                <?php foreach ($tourCabs as $cab): ?>
                                                    <label class="cart-cab-option" title="<?php echo htmlspecialchars($cab['text']); ?>">
                                                        <input type="radio"
                                                               name="cab_type"
                                                               value="<?php echo htmlspecialchars($cab['value']); ?>"
                                                               data-cab-label="<?php echo htmlspecialchars($cab['display_name'] ?? $cab['value']); ?>"
                                                               data-cab-price="<?php echo (float)($cab['price'] ?? 0); ?>"
                                                               data-cab-capacity="<?php echo (int)($cab['max_passengers'] ?? 0); ?>"
                                                               <?php echo $selectedCab === (string)$cab['value'] ? 'checked' : ''; ?>>
                                                        <span class="cart-cab-option__body">
                                                            <span class="cart-cab-option__thumb">
                                                                <img src="<?php echo htmlspecialchars($cab['image_url']); ?>"
                                                                     alt=""
                                                                     onerror="this.src='<?php echo BASE_URL; ?>assets/images/tours/default-tour.jpg'">
                                                            </span>
                                                            <span class="cart-cab-option__text">
                                                                <span class="cart-cab-option__name"><?php echo htmlspecialchars($cab['display_name'] ?? $cab['value']); ?></span>
                                                                <span class="cart-cab-option__price">₹<?php echo number_format((float)($cab['price'] ?? 0), 0); ?></span>
                                                            </span>
                                                        </span>
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>

                                            <div class="cart-pickup-fields" data-cart-pickup-wrap <?php echo $showPickup ? '' : 'hidden'; ?>>
                                                <label class="cart-field-label" for="pickup_place_<?php echo (int)$tour['id']; ?>">Pickup point</label>
                                                <select name="pickup_place"
                                                        id="pickup_place_<?php echo (int)$tour['id']; ?>"
                                                        class="form-control cart-pickup-place"
                                                        data-cart-pickup-place>
                                                    <option value="">Select pickup point</option>
                                                    <?php foreach ($pickupPlaces as $place): ?>
                                                        <option value="<?php echo htmlspecialchars($place); ?>" <?php echo $selectedPickup === $place ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($place); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <div class="cart-pickup-detail-wrap" data-cart-pickup-detail-wrap <?php echo $needsPickupDetail ? '' : 'hidden'; ?>>
                                                    <textarea
                                                           name="pickup_detail"
                                                           class="form-control cart-pickup-detail"
                                                           data-cart-pickup-detail
                                                           rows="3"
                                                           placeholder="<?php echo $selectedPickup === 'Hotel' ? 'Enter hotel name with full address with location' : ($selectedPickup === 'Others' ? 'Enter location details' : 'Enter details'); ?>"
                                                           <?php echo $needsPickupDetail ? 'required' : ''; ?>><?php echo htmlspecialchars($selectedPickupDetail); ?></textarea>
                                                </div>
                                                <label class="cart-field-label" for="pickup_time_<?php echo (int)$tour['id']; ?>">Pickup time</label>
                                                <select name="pickup_time"
                                                        id="pickup_time_<?php echo (int)$tour['id']; ?>"
                                                        class="form-control cart-pickup-time"
                                                        data-cart-pickup-time>
                                                    <option value="">Select time (9 AM – 6 PM)</option>
                                                    <?php foreach ($pickupTimeOptions as $timeOpt): ?>
                                                        <option value="<?php echo htmlspecialchars($timeOpt['value']); ?>"
                                                            <?php echo $selectedPickupTime === $timeOpt['value'] ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($timeOpt['label']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
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
                                            <button type="submit" class="cart-delete-btn" data-cart-remove title="Remove from cart" aria-label="Remove from cart">
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
                        <div class="cart-help">Order total (cab pricing)</div>
                        <div class="amount" data-cart-order-total><?php echo formatPriceINR($cartSummary['total']); ?></div>
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
                            <textarea name="special_requirements" id="cartSpecialRequirements" class="form-control" rows="3" placeholder="Optional"><?php echo htmlspecialchars($prefillSpecialRequirements); ?></textarea>
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

                        <button type="submit" class="btn btn-primary w-100"><?php echo htmlspecialchars($checkoutButtonLabel); ?></button>
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
        const selected = card.querySelector('input[name="cab_type"]:checked');
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
    const pickupTimeInp = card ? card.querySelector('[data-cart-pickup-time]') : null;
    const totalStatus = card ? card.querySelector('[data-cart-total-status]') : null;
    const totalAmount = card ? card.querySelector('[data-cart-total-amount]') : null;

    function syncTotalStatus() {
        const selected = picker.querySelector('input[name="cab_type"]:checked');
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
        if (needsDetail) {
            detailWrap.removeAttribute('hidden');
            detailInp.placeholder = place === 'Hotel' ? 'Enter hotel name with full address with location' : 'Enter location details';
            detailInp.setAttribute('required', 'required');
        } else {
            detailWrap.setAttribute('hidden', '');
            detailInp.value = '';
            detailInp.removeAttribute('required');
            detailInp.placeholder = '';
        }
    }

    function syncCabSelection() {
        const selected = picker.querySelector('input[name="cab_type"]:checked');
        const hasCab = selected && selected.value !== '';
        if (!pickupWrap) return;
        if (hasCab) {
            pickupWrap.removeAttribute('hidden');
            syncPickupDetail();
            if (pickupTimeInp) pickupTimeInp.setAttribute('required', 'required');
            return;
        }
        pickupWrap.setAttribute('hidden', '');
        if (pickupSel) pickupSel.value = '';
        if (detailInp) detailInp.value = '';
        if (detailWrap) detailWrap.setAttribute('hidden', '');
        if (detailInp) detailInp.removeAttribute('required');
        if (pickupTimeInp) {
            pickupTimeInp.value = '';
            pickupTimeInp.removeAttribute('required');
        }
    }

    picker.querySelectorAll('input[name="cab_type"]').forEach(function(radio) {
        radio.addEventListener('change', function() {
            syncCabSelection();
            syncTotalStatus();
        });
    });
    if (pickupSel) {
        pickupSel.addEventListener('change', syncPickupDetail);
    }
    syncCabSelection();
    syncTotalStatus();
});

document.querySelectorAll('[data-cart-tour-form]').forEach(function(form) {
    let saveTimer = null;
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

        const body = new FormData(form);
        fetch(form.action, {
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
                return;
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
        })
        .catch(function() {
            recalculateOrderTotalFromCards();
        });
    }

    function saveCartItemDebounced() {
        if (saveTimer) clearTimeout(saveTimer);
        saveTimer = setTimeout(saveCartItem, 500);
    }

    const dateInput = form.querySelector('[name="tour_date"]');
    if (dateInput) dateInput.addEventListener('change', saveCartItem);

    const peopleSelect = form.querySelector('[data-people-select]');
    if (peopleSelect) peopleSelect.addEventListener('change', saveCartItem);

    form.querySelectorAll('input[name="cab_type"]').forEach(function(radio) {
        radio.addEventListener('change', saveCartItem);
    });

    const pickupPlace = form.querySelector('[data-cart-pickup-place]');
    if (pickupPlace) pickupPlace.addEventListener('change', saveCartItem);

    const pickupTime = form.querySelector('[data-cart-pickup-time]');
    if (pickupTime) pickupTime.addEventListener('change', saveCartItem);

    form.querySelectorAll('[data-cart-pickup-detail], [data-cart-pickup-address]').forEach(function(input) {
        input.addEventListener('input', saveCartItemDebounced);
        input.addEventListener('blur', saveCartItem);
    });

    if (removeBtn) {
        removeBtn.addEventListener('click', function() {
            if (actionInput) actionInput.value = 'remove';
            if (ajaxInput) ajaxInput.value = '0';
        });
    }
});

recalculateOrderTotalFromCards();
</script>

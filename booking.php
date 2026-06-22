<?php
require_once 'config/config.php';
require_once 'includes/checkout_helpers.php';

// Try to include cab options, but handle gracefully if not available
$cab_functionality_enabled = false;
try {
    if (file_exists('includes/cab_options.php')) {
        require_once 'includes/cab_options.php';
        // Test if cab_types table exists
        $db->fetch("SELECT COUNT(*) as count FROM cab_types LIMIT 1");
        $cab_functionality_enabled = true;
    }
} catch (Exception $e) {
    // Cab functionality not available, continue without it
    $cab_functionality_enabled = false;
}

$errors = [];
$razorpayEnabled = razorpayIsConfigured();
$checkoutUser = null;
if (isUserLoggedIn()) {
    try {
        $checkoutUser = $db->fetch("SELECT first_name, last_name, email, phone FROM users WHERE id = ?", [(int) $_SESSION['user_id']]);
    } catch (Exception $e) {
        $checkoutUser = null;
    }
}

if ($_POST) {
    if (!isUserLoggedIn()) {
        $loginRedirect = BASE_URL . 'login?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? navUrl('booking'));
        header('Location: ' . $loginRedirect);
        exit;
    }

    $tour_id = $_POST['tour_id'] ?? '';
    $tour_date = $_POST['tour_date'] ?? '';
    $people = $_POST['people'] ?? '';
    $guest_name = trim($_POST['guest_name'] ?? '');
    $guest_email = trim($_POST['guest_email'] ?? '');
    $guest_phone = trim($_POST['guest_phone'] ?? '');
    $special_requirements = trim($_POST['special_requirements'] ?? '');
    $cab_type = trim($_POST['cab_type'] ?? '');
    $paymentMethod = trim($_POST['payment_method'] ?? 'cash');

    if (empty($tour_id)) $errors[] = 'Tour selection is required';
    if (empty($tour_date)) $errors[] = 'Tour date is required';
    if (!empty($tour_date) && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tour_date) || $tour_date <= date('Y-m-d'))) {
        $errors[] = 'Tour date must be tomorrow or later';
    }
    if (empty($people)) $errors[] = 'Number of people is required';
    if ($guest_name === '') $errors[] = 'Your name is required';
    if ($guest_email === '' || !filter_var($guest_email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required';
    if ($guest_phone === '') $errors[] = 'Your phone number is required';
    if (!in_array($paymentMethod, ['cash', 'razorpay'], true)) {
        $errors[] = 'Please select a valid payment method';
    }
    if ($paymentMethod === 'razorpay' && !razorpayIsConfigured()) {
        $errors[] = 'Online payment is not available right now. Please choose cash payment.';
    }
    if (empty($_POST['accept_terms'])) {
        $errors[] = 'You must accept the Terms of Service and Privacy Policy to continue';
    }

    if (empty($errors)) {
        try {
            $result = createInvoiceFromBooking([
                'tour_id' => $tour_id,
                'tour_date' => $tour_date,
                'people' => $people,
                'cab_type' => $cab_type,
            ], [
                'name' => $guest_name,
                'email' => $guest_email,
                'phone' => $guest_phone,
                'special_requirements' => $special_requirements,
            ], $paymentMethod, $cab_functionality_enabled);

            if ($paymentMethod === 'razorpay') {
                header('Location: ' . payInvoiceUrl($result['invoice_number']));
                exit;
            }

            require_once 'includes/invoice_pdf_helpers.php';
            notifyInvoiceViaWhatsApp($result['invoice_number']);
            header('Location: ' . invoiceUrl($result['invoice_number']));
            exit;
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
    }
}

// Get tour details if tour_id is provided
$tour = null;
if (!empty($_GET['tour_id']) || !empty($_POST['tour_id'])) {
    $tour_id = $_GET['tour_id'] ?? $_POST['tour_id'];
    $tour = $db->fetch("
        SELECT t.*, d.name as destination_name, d.country 
        FROM tours t 
        LEFT JOIN destinations d ON t.destination_id = d.id 
        WHERE t.id = ? AND t.status = 'active'
    ", [$tour_id]);
}

// Initialize cab options for form if available
$availableCabs = [];
if ($cab_functionality_enabled) {
    try {
        $cabOptions = new CabOptions($db);
        $availableCabs = $cabOptions->getCabOptionsForDropdown();
    } catch (Exception $e) {
        $availableCabs = [];
        $cab_functionality_enabled = false;
    }
}

$prefillName = trim($_POST['guest_name'] ?? (($checkoutUser['first_name'] ?? '') . ' ' . ($checkoutUser['last_name'] ?? '')));
$prefillEmail = $_POST['guest_email'] ?? ($checkoutUser['email'] ?? ($_SESSION['user_email'] ?? ''));
$prefillPhone = $_POST['guest_phone'] ?? ($checkoutUser['phone'] ?? '');
$minPeople = (int) ($tour['min_people'] ?? 1);
$maxPeople = (int) ($tour['max_people'] ?? 10);
$available_tours = $db->fetchAll("SELECT id, title, price, discount_price, duration_days, min_people, max_people FROM tours WHERE status = 'active' ORDER BY title");
$pricePerPerson = $tour ? (float) ($tour['discount_price'] ?: $tour['price']) : 0;
$tourPackagePrice = $pricePerPerson;
$durationDays = $tour ? (int) $tour['duration_days'] : 1;

$page_title = 'Book Your Tour - ' . getSetting('site_name');
$current_page = 'booking';
$extra_css = cssWithCache('assets/css/checkout-page.css');

include 'includes/header.php';
?>

<section class="page-header">
    <div class="container">
        <h1>Book Your Tour</h1>
        <ul class="travhub-breadcrumb list-unstyled">
            <li><a href="<?php echo navUrl('home'); ?>">Home</a></li>
            <li><a href="<?php echo navUrl('tours'); ?>">Tours</a></li>
            <li>Book Now</li>
        </ul>
    </div>
</section>

<section class="booking-wrapper">
    <div class="container">
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger" style="margin-bottom: 20px;">
                <ul class="mb-0 ps-3">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" id="bookingForm">
            <div class="booking-grid">
                <div class="booking-tour-card">
                    <h3 class="section-title"><i class="fas fa-map-marked-alt me-2" style="color:#667eea;"></i>Tour Details</h3>

                    <?php if ($tour): ?>
                        <h4 class="booking-tour-title"><?php echo htmlspecialchars($tour['title']); ?></h4>
                        <div class="booking-tour-location">
                            <i class="fas fa-map-marker-alt me-1"></i>
                            <?php echo htmlspecialchars(trim(($tour['destination_name'] ?? '') . ', ' . ($tour['country'] ?? ''), ', ')); ?>
                        </div>
                        <div class="booking-tour-badges">
                            <span class="booking-tour-badge"><i class="far fa-clock"></i><?php echo (int) $tour['duration_days']; ?> Days</span>
                            <span class="booking-tour-badge"><i class="fas fa-users"></i><?php echo $minPeople; ?>–<?php echo $maxPeople; ?> People</span>
                            <?php if (!empty($tour['difficulty_level'])): ?>
                                <span class="booking-tour-badge"><i class="fas fa-mountain"></i><?php echo ucfirst($tour['difficulty_level']); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="booking-price-line">
                            <?php if (!empty($tour['discount_price'])): ?>
                                <span class="original">₹<?php echo number_format($tour['price'], 0); ?></span>
                            <?php endif; ?>
                            <span class="price">₹<?php echo number_format($tour['discount_price'] ?: $tour['price'], 0); ?></span>
                            <span class="unit">per tour</span>
                        </div>
                        <input type="hidden" name="tour_id" value="<?php echo (int) $tour['id']; ?>">
                    <?php else: ?>
                        <div class="cart-field">
                            <label class="cart-form-label" for="bookingTourSelect"><i class="fas fa-route"></i> Select Tour</label>
                            <select name="tour_id" id="bookingTourSelect" class="form-select" required>
                                <option value="">Choose a tour...</option>
                                <?php foreach ($available_tours as $t): ?>
                                    <?php $tourPrice = (float) ($t['discount_price'] ?: $t['price']); ?>
                                    <option value="<?php echo (int) $t['id']; ?>"
                                            data-price="<?php echo $tourPrice; ?>"
                                            data-duration="<?php echo (int) $t['duration_days']; ?>"
                                            data-min="<?php echo (int) $t['min_people']; ?>"
                                            data-max="<?php echo (int) $t['max_people']; ?>"
                                            <?php echo (($_POST['tour_id'] ?? '') == $t['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($t['title']); ?> - ₹<?php echo number_format($tourPrice, 0); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="cart-field">
                                <label class="cart-form-label" for="bookingTourDate"><i class="fas fa-calendar-alt"></i> Tour Date</label>
                                <input type="date" name="tour_date" id="bookingTourDate" class="form-control" required
                                       min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>"
                                       value="<?php echo htmlspecialchars($_POST['tour_date'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="cart-field">
                                <label class="cart-form-label" for="bookingPeople"><i class="fas fa-users"></i> Number of People</label>
                                <select name="people" id="bookingPeople" class="form-select" required>
                                    <option value="">Select...</option>
                                    <?php for ($i = $minPeople; $i <= $maxPeople; $i++): ?>
                                        <option value="<?php echo $i; ?>" <?php echo (($_POST['people'] ?? '') == $i) ? 'selected' : ''; ?>>
                                            <?php echo $i; ?> Person<?php echo $i > 1 ? 's' : ''; ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                                <small class="cart-help mt-1 d-block">For group size information only — does not change the tour price.</small>
                            </div>
                        </div>
                    </div>

                    <?php if ($cab_functionality_enabled && !empty($availableCabs)): ?>
                        <div class="cart-field">
                            <label class="cart-form-label" for="cabTypeSelect"><i class="fas fa-car"></i> Cab Type</label>
                            <select name="cab_type" class="form-select" id="cabTypeSelect">
                                <option value="">No cab</option>
                                <?php foreach ($availableCabs as $cab): ?>
                                    <option value="<?php echo htmlspecialchars($cab['value']); ?>"
                                            data-price="<?php echo (float) $cab['price']; ?>"
                                            data-max-passengers="<?php echo (int) $cab['max_passengers']; ?>"
                                            <?php echo (($_POST['cab_type'] ?? '') == $cab['value']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cab['text']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="cart-help mt-1 d-block">Cab will be provided for the entire tour duration</small>
                        </div>
                    <?php endif; ?>

                    <div class="cart-total-box" id="totalAmount" style="display:none;">
                        <div class="cart-help">Order total</div>
                        <div class="amount" id="totalPrice">₹0</div>
                        <div class="breakdown">
                            <span><span>Tour cost</span><strong id="tourPrice">₹0</strong></span>
                            <span><span>Cab cost</span><strong id="cabPrice">₹0</strong></span>
                        </div>
                    </div>
                </div>

                <div class="cart-form-card">
                    <h3 style="margin-bottom:14px;">Checkout</h3>
                    <p class="cart-help" style="margin-bottom:18px;">Choose payment method and complete your booking details.</p>

                    <?php if (!isUserLoggedIn()): ?>
                        <div class="login-notice">
                            <i class="fas fa-user-lock me-2"></i>
                            Please <a href="<?php echo navUrl('login'); ?>?redirect=<?php echo urlencode($_SERVER['REQUEST_URI'] ?? navUrl('booking')); ?>">login</a>
                            or <a href="<?php echo navUrl('register'); ?>">create an account</a> to complete booking and payment.
                        </div>
                    <?php endif; ?>

                    <div class="cart-field">
                        <label class="cart-form-label" for="bookingGuestName"><i class="fas fa-user"></i> Name</label>
                        <input type="text" name="guest_name" id="bookingGuestName" class="form-control"
                               value="<?php echo htmlspecialchars($prefillName); ?>" required>
                    </div>

                    <div class="cart-field">
                        <label class="cart-form-label" for="bookingGuestEmail"><i class="fas fa-envelope"></i> Email</label>
                        <input type="email" name="guest_email" id="bookingGuestEmail" class="form-control"
                               value="<?php echo htmlspecialchars($prefillEmail); ?>" required>
                    </div>

                    <div class="cart-field">
                        <label class="cart-form-label" for="bookingGuestPhone"><i class="fas fa-phone"></i> Phone</label>
                        <input type="tel" name="guest_phone" id="bookingGuestPhone" class="form-control"
                               value="<?php echo htmlspecialchars($prefillPhone); ?>" required>
                    </div>

                    <div class="cart-field">
                        <label class="cart-form-label" for="bookingSpecialRequirements"><i class="fas fa-comment-dots"></i> Special Requirements</label>
                        <textarea name="special_requirements" id="bookingSpecialRequirements" class="form-control" rows="3" placeholder="Optional"><?php echo htmlspecialchars($_POST['special_requirements'] ?? ''); ?></textarea>
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
                            <input class="form-check-input" type="checkbox" name="accept_terms" id="bookingAcceptTerms" value="1" required>
                            <label class="form-check-label" for="bookingAcceptTerms">
                                I agree to the <a href="<?php echo navUrl('terms-conditions'); ?>" target="_blank" rel="noopener">Terms of Service</a> and <a href="<?php echo navUrl('privacy-policy'); ?>" target="_blank" rel="noopener">Privacy Policy</a>
                            </label>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">Book Now</button>
                    <a href="<?php echo navUrl('tours'); ?>" class="btn btn-outline-secondary w-100 mt-2">Back to Tours</a>
                </div>
            </div>
        </form>
    </div>
</section>

<?php
$extra_js = '<script>
(function() {
    var tourPackagePrice = ' . json_encode($tourPackagePrice) . ';
    var durationDays = ' . json_encode($durationDays) . ';
    var tourSelect = document.getElementById("bookingTourSelect");
    var peopleSelect = document.getElementById("bookingPeople");
    var cabSelect = document.getElementById("cabTypeSelect");

    function rebuildPeopleOptions(minPeople, maxPeople, selected) {
        if (!peopleSelect) return;
        peopleSelect.innerHTML = "<option value=\"\">Select...</option>";
        for (var i = minPeople; i <= maxPeople; i++) {
            var option = document.createElement("option");
            option.value = String(i);
            option.textContent = i + (i > 1 ? " People" : " Person");
            if (String(selected) === String(i)) option.selected = true;
            peopleSelect.appendChild(option);
        }
    }

    function getPricing() {
        if (tourSelect && tourSelect.value) {
            var option = tourSelect.options[tourSelect.selectedIndex];
            return {
                price: parseFloat(option.dataset.price || "0") || 0,
                duration: parseInt(option.dataset.duration || "1", 10) || 1,
                min: parseInt(option.dataset.min || "1", 10) || 1,
                max: parseInt(option.dataset.max || "10", 10) || 10
            };
        }
        return {
            price: parseFloat(tourPackagePrice) || 0,
            duration: parseInt(durationDays, 10) || 1,
            min: parseInt(' . json_encode($minPeople) . ', 10) || 1,
            max: parseInt(' . json_encode($maxPeople) . ', 10) || 10
        };
    }

    function updateTotal() {
        var pricing = getPricing();
        var people = peopleSelect ? peopleSelect.value : "";
        var cabPricePerDay = 0;
        var maxPassengers = 0;

        if (cabSelect && cabSelect.value) {
            var cabOption = cabSelect.options[cabSelect.selectedIndex];
            cabPricePerDay = parseFloat(cabOption.dataset.price || "0") || 0;
            maxPassengers = parseInt(cabOption.dataset.maxPassengers || "0", 10) || 0;
        }

        var totalBox = document.getElementById("totalAmount");
        if (!totalBox) return;

        if (pricing.price) {
            var tourTotal = pricing.price;
            var cabTotal = cabPricePerDay * pricing.duration;
            var grandTotal = tourTotal + cabTotal;

            document.getElementById("tourPrice").textContent = "₹" + tourTotal.toLocaleString("en-IN");
            document.getElementById("cabPrice").textContent = cabTotal > 0 ? "₹" + cabTotal.toLocaleString("en-IN") : "₹0";
            document.getElementById("totalPrice").textContent = "₹" + grandTotal.toLocaleString("en-IN");
            totalBox.style.display = "block";

            var warningDiv = document.getElementById("cabWarning");
            if (cabSelect && cabSelect.value && people && parseInt(people, 10) > maxPassengers) {
                if (!warningDiv) {
                    warningDiv = document.createElement("div");
                    warningDiv.id = "cabWarning";
                    warningDiv.className = "alert alert-warning mt-2";
                    warningDiv.innerHTML = "<i class=\"fas fa-exclamation-triangle me-2\"></i>Selected cab can accommodate maximum " + maxPassengers + " passengers.";
                    cabSelect.parentNode.appendChild(warningDiv);
                }
            } else if (warningDiv) {
                warningDiv.remove();
            }
        } else {
            totalBox.style.display = "none";
        }
    }

    if (tourSelect) {
        tourSelect.addEventListener("change", function() {
            var pricing = getPricing();
            rebuildPeopleOptions(pricing.min, pricing.max, "");
            updateTotal();
        });
    }

    if (peopleSelect) peopleSelect.addEventListener("change", updateTotal);
    if (cabSelect) cabSelect.addEventListener("change", updateTotal);
    updateTotal();

    document.querySelectorAll(".payment-option input[type=\"radio\"]").forEach(function(input) {
        input.addEventListener("change", function() {
            document.querySelectorAll(".payment-option").forEach(function(option) {
                option.classList.remove("is-active");
            });
            if (input.closest(".payment-option")) {
                input.closest(".payment-option").classList.add("is-active");
            }
        });
    });
})();
</script>';

include 'includes/footer.php';

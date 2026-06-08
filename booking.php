<?php
require_once 'config/config.php';

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
$success = false;
$booking_number = '';

if ($_POST) {
    $tour_id = $_POST['tour_id'] ?? '';
    $tour_date = $_POST['tour_date'] ?? '';
    $people = $_POST['people'] ?? '';
    $guest_name = $_POST['guest_name'] ?? '';
    $guest_email = $_POST['guest_email'] ?? '';
    $guest_phone = $_POST['guest_phone'] ?? '';
    $special_requirements = $_POST['special_requirements'] ?? '';
    $cab_type = $_POST['cab_type'] ?? '';
    
    // Validation
    if (empty($tour_id)) $errors[] = 'Tour selection is required';
    if (empty($tour_date)) $errors[] = 'Tour date is required';
    if (!empty($tour_date) && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tour_date) || $tour_date <= date('Y-m-d'))) {
        $errors[] = 'Tour date must be tomorrow or later';
    }
    if (empty($people)) $errors[] = 'Number of people is required';
    if (empty($guest_name)) $errors[] = 'Your name is required';
    if (empty($guest_email)) $errors[] = 'Your email is required';
    if (empty($guest_phone)) $errors[] = 'Your phone number is required';
    // Cab type is optional
    
    // Get tour details
    $tour = $db->fetch("SELECT * FROM tours WHERE id = ? AND status = 'active'", [$tour_id]);
    if (!$tour) {
        $errors[] = 'Invalid tour selection';
    }
    
    if (empty($errors)) {
        // Calculate total amount
        $price_per_person = $tour['discount_price'] ?: $tour['price'];
        $total_amount = $price_per_person * $people;
        
        // Initialize cab-related variables
        $cab_price = 0;
        $total_with_cab = $total_amount;
        
        // Handle cab functionality if enabled
        if ($cab_functionality_enabled && !empty($cab_type)) {
            try {
                $cabOptions = new CabOptions($db);
                
                // Validate cab selection can accommodate the number of people
                if (!$cabOptions->canAccommodate($cab_type, $people)) {
                    $errors[] = 'Selected cab type cannot accommodate ' . $people . ' people';
                } else {
                    // Calculate cab charges
                    $cab_price = $cabOptions->calculateCabPrice($cab_type, $tour['duration_days']);
                    $total_with_cab = $total_amount + $cab_price;
                }
            } catch (Exception $e) {
                // Fallback: continue without cab functionality
                $cab_type = null;
                $cab_price = 0;
                $total_with_cab = $total_amount;
            }
        }
        
        // Generate booking number
        $booking_number = 'TH' . date('Y') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        try {
            // Use different SQL based on whether cab columns exist
            if ($cab_functionality_enabled) {
                $booking_id = $db->execute(
                    "INSERT INTO bookings (booking_number, tour_id, guest_name, guest_email, guest_phone, number_of_people, tour_date, total_amount, cab_type, cab_price, special_requirements, booking_status, payment_status, created_at) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', NOW())",
                    [$booking_number, $tour_id, $guest_name, $guest_email, $guest_phone, $people, $tour_date, $total_amount, $cab_type, $cab_price, $special_requirements]
                );
            } else {
                $booking_id = $db->execute(
                    "INSERT INTO bookings (booking_number, tour_id, guest_name, guest_email, guest_phone, number_of_people, tour_date, total_amount, special_requirements, booking_status, payment_status, created_at) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', NOW())",
                    [$booking_number, $tour_id, $guest_name, $guest_email, $guest_phone, $people, $tour_date, $total_amount, $special_requirements]
                );
            }
            
            if ($booking_id) {
                $success = true;
                // In a real application, you'd send confirmation email here
            } else {
                $errors[] = 'Failed to create booking. Please try again.';
            }
        } catch (Exception $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Your Tour - <?php echo getSetting('site_name'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
    --primary-orange: #ff6a00;
    --primary-orange-hover: #e85d00;
    --text-dark: #111;
    --text-muted: #666;
    --border-color: #e5e7eb;
    --light-bg: #f5f7fa;
}

/* PAGE */
body {
    background: var(--light-bg);
    min-height: 100vh;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

/* WRAPPER */
.booking-wrapper {
    padding: 40px 0;
}

/* CARD */
.booking-card {
    background: #ffffff;
    max-width: 900px;
    margin: auto;
    border-radius: 10px;
    box-shadow: 12px 12px 30px rgba(0, 0, 0, 0.08);
    overflow: hidden;
}

/* HEADER */
.card-header-section {
    background: linear-gradient(135deg, #6d28d9 0%, #7c3aed 100%) !important;/* same as button color */
    color: #ffffff;
    padding: 28px 30px;
    text-align: center;
    border-bottom: none;
}

.card-header-section h2 {
    color: #ffffff;
    font-size: 26px;
    font-weight: 700;
    margin-bottom: 6px;
    margin-top:6px;
}

.card-header-section p {
    color: rgba(255, 255, 255, 0.9);
    font-size: 14px;
}


/* BODY */
.card-body {
    padding: 30px;
}

/* INFO BAR */
.required-hint {
    background: #fff7ed;
    color: #9a3412;
    padding: 12px 16px;
    border-radius: 6px;
    font-size: 14px;
    margin-bottom: 25px;
}

.required-hint i {
    margin-right: 6px;
}

/* LABELS */
.form-label {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-dark);
    margin-bottom: 6px;
}

.form-label.required::after {
    content: " *";
    color: #dc2626;
}

/* INPUTS */
.form-control,
.form-select {
    height: 48px;
    border-radius: 6px;
    border: 1px solid var(--border-color);
    font-size: 14px;
    padding: 10px 14px;
}

textarea.form-control {
    height: auto;
}

.form-control:focus,
.form-select:focus {
    border-color: var(--primary-orange);
    box-shadow: 0 0 0 2px rgba(255, 106, 0, 0.15);
}

/* TOUR SUMMARY */
.tour-summary {
    background: #f9fafb;
    border-radius: 8px;
    padding: 18px;
    margin-bottom: 25px;
    border: 1px solid var(--border-color);
}

.tour-title {
    font-size: 18px;
    font-weight: 700;
    color: var(--text-dark);
}

.tour-location {
    font-size: 13px;
    color: var(--text-muted);
}

.tour-details {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 10px;
}

.tour-detail-badge {
    background: #ffffff;
    border: 1px solid var(--border-color);
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 12px;
}

/* PRICE */
.price-section {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 12px;
}

.price-display {
    font-size: 22px;
    font-weight: 700;
    background: linear-gradient(135deg, #6d28d9 0%, #7c3aed 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    color: transparent;
}

.original-price {
    font-size: 14px;
    color: var(--text-muted);
    text-decoration: line-through;
    margin-right: 8px;
}

/* TOTAL */
.total-section {
    background: #f8fbff;
    border-radius: 8px;
    padding: 18px;
    border: 1px solid #dbeafe;
    margin-top: 25px;
}

.total-row {
    display: flex;
    justify-content: space-between;
    font-size: 14px;
    margin-bottom: 8px;
}

.total-amount {
    font-size: 22px;
    font-weight: 700;
    background: linear-gradient(135deg, #6d28d9 0%, #7c3aed 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    color: transparent;
}

/* BUTTONS */
.btn-submit {
    background: linear-gradient(135deg, #6d28d9 0%, #7c3aed 100%) !important;
    color: #fff;
    height: 52px;
    font-size: 15px;
    font-weight: 600;
    border-radius: 6px;
    border: none;
    box-shadow: 0 6px 18px rgba(255, 106, 0, 0.35);
    padding : 14px;
}

.btn-submit:hover {
    background: var(--primary-orange-hover);
}

.btn-back {
    background: #ffffff;
    border: 1px solid var(--border-color);
    height: 52px;
    font-size: 15px;
    border-radius: 6px;
    padding : 14px;
}

/* ALERTS */
.alert {
    border-radius: 6px;
    font-size: 14px;
}

/* SUCCESS */
.success-card {
    text-align: center;
    padding: 40px 20px;
}

.success-icon {
    font-size: 64px;
    background: linear-gradient(135deg, #6d28d9 0%, #7c3aed 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    color: transparent;
}

/* MOBILE */
@media (max-width: 768px) {
    .card-body {
        padding: 20px;
    }

    .booking-wrapper {
        padding: 20px 10px;
    }
}

    </style>
</head>
<body>
    <div class="booking-wrapper">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="booking-card">
                        <!-- Card Header Section -->
                        <div class="card-header-section">
                            <h2><i class="fas fa-calendar-alt me-2"></i>Book Your Tour</h2>
                            <p>Fill in the details below to reserve your spot</p>
                        </div>
                        
                        <div class="card-body">
                            <?php if ($success): ?>
                                <!-- Success Message -->
                                <div class="success-card">
                                    <div class="success-icon">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                    <h3 class="text-success mb-4">Booking Confirmed!</h3>
                                    <p>We've received your booking request. Our team will contact you within 24 hours to confirm your booking and payment details.</p>
                                    
                                    <div class="booking-number">
                                        Booking Number: <?php echo $booking_number; ?>
                                    </div>
                                    
                                    <?php if (!empty($cab_type)): ?>
                                        <div class="total-section">
                                            <div class="total-row">
                                                <span>Selected Cab:</span>
                                                <span><strong><?php echo getCabDisplayName($cab_type); ?></strong></span>
                                            </div>
                                            <div class="total-row">
                                                <span>Total Amount:</span>
                                                <span class="total-amount">₹<?php echo number_format($total_with_cab, 0); ?></span>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="d-grid gap-2 mt-4">
                                        <a href="index" class="btn btn-submit">Back to Home</a>
                                        <a href="tours" class="btn btn-back">Browse More Tours</a>
                                    </div>
                                </div>
                            <?php else: ?>
                                <!-- Required fields hint -->
                                <div class="required-hint">
                                    <i class="fas fa-info-circle"></i>
                                    Please fill in all required fields marked with an asterisk (*)
                                </div>

                                <?php if (!empty($errors)): ?>
                                    <div class="alert alert-danger mb-4">
                                        <ul class="mb-0 ps-3">
                                            <?php foreach ($errors as $error): ?>
                                                <li><?php echo htmlspecialchars($error); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>

                                <!-- Tour Summary -->
                                <?php if ($tour): ?>
                                    <div class="tour-summary">
                                        <h3 class="tour-title"><?php echo htmlspecialchars($tour['title']); ?></h3>
                                        <div class="tour-location">
                                            <i class="fas fa-map-marker-alt me-1"></i>
                                            <?php echo htmlspecialchars($tour['destination_name'] . ', ' . $tour['country']); ?>
                                        </div>
                                        
                                        <div class="tour-details">
                                            <span class="tour-detail-badge">
                                                <i class="far fa-clock me-1"></i><?php echo $tour['duration_days']; ?> Days
                                            </span>
                                            <span class="tour-detail-badge">
                                                <i class="fas fa-users me-1"></i>Max <?php echo $tour['max_people']; ?> People
                                            </span>
                                            <span class="tour-detail-badge">
                                                <i class="fas fa-mountain me-1"></i><?php echo ucfirst($tour['difficulty_level']); ?>
                                            </span>
                                        </div>
                                        
                                        <div class="price-section">
                                            <div>
                                                <div class="price-display">
                                                    <?php if ($tour['discount_price']): ?>
                                                        <span class="original-price">₹<?php echo number_format($tour['price'], 0); ?></span>
                                                        ₹<?php echo number_format($tour['discount_price'], 0); ?>
                                                    <?php else: ?>
                                                        ₹<?php echo number_format($tour['price'], 0); ?>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="price-label">per person</div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- Booking Form -->
                                <form method="POST" id="bookingForm">
                                    <?php if ($tour): ?>
                                        <input type="hidden" name="tour_id" value="<?php echo $tour['id']; ?>">
                                    <?php else: ?>
                                        <div class="mb-4">
                                            <label class="form-label required">Select Tour</label>
                                            <select name="tour_id" class="form-select" required>
                                                <option value="">Choose a tour...</option>
                                                <?php
                                                $available_tours = $db->fetchAll("SELECT id, title, price, discount_price FROM tours WHERE status = 'active' ORDER BY title");
                                                foreach ($available_tours as $t):
                                                ?>
                                                    <option value="<?php echo $t['id']; ?>" <?php echo ($_POST['tour_id'] ?? '') == $t['id'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($t['title']); ?> - ₹<?php echo number_format($t['discount_price'] ?: $t['price'], 0); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    <?php endif; ?>

                                    <div class="row mb-4">
                                        <div class="col-md-6">
                                            <label class="form-label required">Tour Date</label>
                                            <input type="date" name="tour_date" class="form-control" required 
                                                   min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>"
                                                   value="<?php echo htmlspecialchars($_POST['tour_date'] ?? ''); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label required">Number of People</label>
                                            <select name="people" class="form-select" required>
                                                <option value="">Select...</option>
                                                <?php for ($i = 1; $i <= ($tour['max_people'] ?? 10); $i++): ?>
                                                    <option value="<?php echo $i; ?>" <?php echo ($_POST['people'] ?? '') == $i ? 'selected' : ''; ?>>
                                                        <?php echo $i; ?> Person<?php echo $i > 1 ? 's' : ''; ?>
                                                    </option>
                                                <?php endfor; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="row mb-4">
                                        <div class="col-md-6">
                                            <label class="form-label required">Full Name</label>
                                            <input type="text" name="guest_name" class="form-control" required
                                                   value="<?php echo htmlspecialchars($_POST['guest_name'] ?? ''); ?>"
                                                   placeholder="Your full name">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label required">Email Address</label>
                                            <input type="email" name="guest_email" class="form-control" required
                                                   value="<?php echo htmlspecialchars($_POST['guest_email'] ?? ''); ?>"
                                                   placeholder="Your email address">
                                        </div>
                                    </div>

                                    <div class="mb-4">
                                        <label class="form-label required">Phone Number</label>
                                        <input type="tel" name="guest_phone" class="form-control" required
                                               value="<?php echo htmlspecialchars($_POST['guest_phone'] ?? ''); ?>"
                                               placeholder="Your phone number">
                                    </div>

                                    <!-- Cab Selection -->
                                    <?php if ($cab_functionality_enabled && !empty($availableCabs)): ?>
                                        <div class="mb-4">
                                            <label class="form-label">Cab Type</label>
                                            <select name="cab_type" class="form-select" id="cabTypeSelect">
                                                <option value="">Select cab type...</option>
                                                <?php foreach ($availableCabs as $cab): ?>
                                                    <option value="<?php echo $cab['value']; ?>" 
                                                            data-price="<?php echo $cab['price']; ?>"
                                                            data-max-passengers="<?php echo $cab['max_passengers']; ?>"
                                                            <?php echo ($_POST['cab_type'] ?? '') == $cab['value'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($cab['text']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <small class="form-text text-muted">Cab will be provided for the entire tour duration</small>
                                        </div>
                                    <?php endif; ?>

                                    <div class="mb-4">
                                        <label class="form-label">Special Requirements (Optional)</label>
                                        <textarea name="special_requirements" class="form-control" rows="3"
                                                  placeholder="Any dietary restrictions, accessibility needs, or special requests..."><?php echo htmlspecialchars($_POST['special_requirements'] ?? ''); ?></textarea>
                                    </div>

                                    <!-- Total Amount Display -->
                                    <div id="totalAmount" class="total-section" style="display: none;">
                                        <div class="total-row">
                                            <span>Tour Cost:</span>
                                            <span id="tourPrice">₹0</span>
                                        </div>
                                        <div class="total-row">
                                            <span>Cab Cost:</span>
                                            <span id="cabPrice">₹0</span>
                                        </div>
                                        <hr>
                                        <div class="total-row">
                                            <span><strong>Total Amount:</strong></span>
                                            <span class="total-amount" id="totalPrice">₹0</span>
                                        </div>
                                        <small class="text-muted">Final amount will be confirmed by our team</small>
                                    </div>

                                    <!-- Form Buttons -->
                                    <div class="d-grid gap-2 mt-4">
                                        <button type="submit" class="btn btn-submit">
                                            <i class="fas fa-calendar-check me-2"></i>Submit Booking Request
                                        </button>
                                        <a href="tours" class="btn btn-back">
                                            <i class="fas fa-arrow-left me-2"></i>Back to Tours
                                        </a>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Calculate total amount dynamically
        function updateTotal() {
            const people = document.querySelector('select[name="people"]').value;
            const pricePerPerson = <?php echo $tour['discount_price'] ?: $tour['price'] ?: 0; ?>;
            const durationDays = <?php echo $tour['duration_days'] ?? 1; ?>;
            
            const cabSelect = document.getElementById('cabTypeSelect');
            let cabPricePerDay = 0;
            let maxPassengers = 0;
            
            if (cabSelect) {
                const selectedCabOption = cabSelect.options[cabSelect.selectedIndex];
                cabPricePerDay = selectedCabOption.dataset.price || 0;
                maxPassengers = selectedCabOption.dataset.maxPassengers || 0;
            }
            
            if (people && pricePerPerson) {
                const tourTotal = people * pricePerPerson;
                const cabTotal = cabPricePerDay * durationDays;
                const grandTotal = tourTotal + cabTotal;
                
                document.getElementById('tourPrice').textContent = '₹' + tourTotal.toLocaleString();
                document.getElementById('cabPrice').textContent = cabTotal > 0 ? '₹' + cabTotal.toLocaleString() : '₹0';
                document.getElementById('totalPrice').textContent = '₹' + grandTotal.toLocaleString();
                
                // Show warning if cab can't accommodate people
                const warningDiv = document.getElementById('cabWarning');
                if (cabSelect && cabSelect.value && people > maxPassengers) {
                    if (!warningDiv) {
                        const warning = document.createElement('div');
                        warning.id = 'cabWarning';
                        warning.className = 'alert alert-warning mt-2';
                        warning.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>Selected cab can accommodate maximum ' + maxPassengers + ' passengers. Please select a different cab or reduce number of people.';
                        cabSelect.parentNode.appendChild(warning);
                    }
                } else if (warningDiv) {
                    warningDiv.remove();
                }
                
                document.getElementById('totalAmount').style.display = 'block';
            } else {
                document.getElementById('totalAmount').style.display = 'none';
            }
        }

        // Update total when people count or cab type changes
        document.addEventListener('DOMContentLoaded', function() {
            const peopleSelect = document.querySelector('select[name="people"]');
            const cabSelect = document.getElementById('cabTypeSelect');
            
            if (peopleSelect) {
                peopleSelect.addEventListener('change', updateTotal);
            }
            
            if (cabSelect) {
                cabSelect.addEventListener('change', updateTotal);
            }
            
            updateTotal(); // Initial calculation
        });
    </script>
</body>
</html>

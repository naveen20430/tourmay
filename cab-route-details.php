<?php
require_once 'config/config.php';

// Set page variables
$page_title = 'Cab Route Details - ' . getSetting('site_name');
$current_page = 'cab';

// Get route ID from URL
$route_id = $_GET['route_id'] ?? null;

if (!$route_id) {
    header('Location: index.php');
    exit;
}

// Fetch route details
$route = $db->fetch("SELECT * FROM cab_routes WHERE id = ? AND status = 'active'", [$route_id]);

if (!$route) {
    header('Location: index.php');
    exit;
}

// Fetch all cab pricing options for this route
$pricing_options = $db->fetchAll("
    SELECT crp.*, ct.display_name, ct.description as cab_description, ct.features, ct.max_passengers,
           COALESCE(crp.max_persons, ct.max_passengers) as max_persons
    FROM cab_route_pricing crp
    INNER JOIN cab_types ct ON crp.cab_type_id = ct.id
    WHERE crp.route_id = ? AND crp.status = 'active' AND ct.status = 'active'
    ORDER BY crp.one_way_price ASC
", [$route_id]);

// Handle booking form submission
$errors = [];
$success = false;
$booking_number = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $guest_name = trim($_POST['guest_name'] ?? '');
    $guest_email = trim($_POST['guest_email'] ?? '');
    $guest_phone = trim($_POST['guest_phone'] ?? '');
    $travel_date = $_POST['travel_date'] ?? '';
    $trip_type = $_POST['trip_type'] ?? '';
    $selected_pricing_id = $_POST['pricing_id'] ?? '';
    $num_passengers = intval($_POST['num_passengers'] ?? 0);
    $special_requirements = trim($_POST['special_requirements'] ?? '');
    
    // Validation
    if (empty($guest_name)) $errors[] = 'Your name is required';
    if (empty($guest_email)) $errors[] = 'Your email is required';
    if (empty($guest_phone)) $errors[] = 'Your phone number is required';
    if (empty($travel_date)) $errors[] = 'Travel date is required';
    if (empty($trip_type)) $errors[] = 'Trip type is required';
    if (empty($selected_pricing_id)) $errors[] = 'Please select a cab type';
    if ($num_passengers < 1) $errors[] = 'Number of passengers is required';
    
    if (empty($errors)) {
        // Get selected pricing details
        $selected_pricing = $db->fetch("
            SELECT crp.*, ct.display_name, ct.max_passengers
            FROM cab_route_pricing crp
            INNER JOIN cab_types ct ON crp.cab_type_id = ct.id
            WHERE crp.id = ? AND crp.route_id = ?
        ", [$selected_pricing_id, $route_id]);
        
        if ($selected_pricing) {
            // Check passenger capacity
            if ($num_passengers > $selected_pricing['max_passengers']) {
                $errors[] = 'Selected cab can only accommodate ' . $selected_pricing['max_passengers'] . ' passengers';
            } else {
                // Calculate price based on trip type
                $trip_price = ($trip_type === 'one_way') 
                    ? $selected_pricing['one_way_price'] 
                    : $selected_pricing['round_trip_price'];
                
                // Generate booking number
                $booking_number = 'CR' . date('Y') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                
                try {
                    // Insert booking
                    $booking_id = $db->execute("
                        INSERT INTO cab_bookings 
                        (booking_number, route_id, pricing_id, customer_name, customer_email, 
                         customer_phone, travel_date, trip_type, num_passengers, 
                         total_price, special_requirements, status, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
                    ", [
                        $booking_number,
                        $route_id,
                        $selected_pricing_id,
                        $guest_name,
                        $guest_email,
                        $guest_phone,
                        $travel_date,
                        $trip_type,
                        $num_passengers,
                        $trip_price,
                        $special_requirements
                    ]);
                    
                    if ($booking_id) {
                        $success = true;
                    } else {
                        $errors[] = 'Failed to create booking. Please try again.';
                    }
                } catch (Exception $e) {
                    $errors[] = 'Database error: ' . $e->getMessage();
                }
            }
        } else {
            $errors[] = 'Invalid cab type selection';
        }
    }
}

$extra_css = '<style>
.page-header{padding:44px 0 32px !important}
.header-section__inner p{margin-bottom:12px !important}
.route-info-bar{padding:14px 0 !important;margin-bottom:22px !important}
.booking-section{padding:0 0 26px 0 !important}
.booking-section .route-info-card{padding:16px !important}
.booking-section .route-info-card h3{margin-bottom:12px !important;font-size:1.15rem !important}
.booking-section .mb-3{margin-bottom:10px !important}
.trip-type-selector{gap:10px !important;margin-bottom:8px !important}
.trip-type-option{padding:10px 12px !important}
.pricing-card{padding:12px !important;margin-bottom:10px !important}
.pricing-card h5{font-size:1rem !important;margin-bottom:6px !important}
.booking-submit-btn{margin-top:10px !important;padding:10px 12px !important}
@media (max-width: 991px){
  .route-info-bar{padding:10px 0 !important;margin-bottom:16px !important}
  .booking-section .route-info-card{padding:12px !important}
}
</style>';
?>

<?php
// Include header after processing to avoid CSS/markup conflicts
include 'includes/header.php';
?>

<?php if ($success): ?>
    <div class="container my-5">
        <div class="route-info-card text-center">
            <i class="fas fa-check-circle text-success" style="font-size: 4em; margin-bottom: 20px;"></i>
            <h2 class="text-success mb-4">Booking Request Submitted!</h2>
            <div class="alert alert-success">
                <h4>Booking Number: <strong><?php echo htmlspecialchars($booking_number); ?></strong></h4>
                <p class="mb-0">Thank you for your booking! Our team will contact you within 24 hours to confirm your booking and provide payment details.</p>
            </div>
            <div class="mt-4">
                <a href="index.php" class="btn btn-primary me-3">Back to Home</a>
                <a href="cab-route-details.php?route_id=<?php echo $route_id; ?>" class="btn btn-outline-primary">Book Another Cab</a>
            </div>
        </div>
    </div>
<?php else: ?>

<!-- Page Header -->
<section class="page-header">
    <div class="container">
        <div class="header-section__inner">
            <h1 class="text-white"><?php echo htmlspecialchars($route['route_name']); ?></h1>
            <p class="text-white mb-4"><?php echo htmlspecialchars($route['description']); ?></p>
            <ul class="travhub-breadcrumb list-unstyled">
                <li><a href="<?php echo navUrl('home'); ?>">Home</a></li>
                <li><a href="<?php echo navUrl('index'); ?>#cab-booking">Cabs</a></li>
                <li>Booking</li>
            </ul>
        </div>
    </div>
</section>

<!-- Route Info Bar -->
<div class="route-info-bar bg-white shadow-sm py-4 mb-5">
    <div class="container">
        <div class="row align-items-center justify-content-center">
            <div class="col-md-8">
                <div class="d-flex justify-content-center align-items-center flex-wrap gap-4">
                    <div class="d-flex align-items-center">
                        <span class="badge bg-primary rounded-pill px-3 py-2 me-2"><?php echo htmlspecialchars($route['from_location']); ?></span>
                        <i class="fas fa-arrow-right text-muted mx-2"></i>
                        <span class="badge bg-primary rounded-pill px-3 py-2 ms-2"><?php echo htmlspecialchars($route['to_location']); ?></span>
                    </div>
                    <?php if ($route['distance_km'] > 0): ?>
                        <div class="text-muted"><i class="fas fa-road me-1"></i> <?php echo $route['distance_km']; ?> km</div>
                    <?php endif; ?>
                    <?php if (!empty($route['estimated_duration'])): ?>
                        <div class="text-muted"><i class="fas fa-clock me-1"></i> <?php echo htmlspecialchars($route['estimated_duration']); ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Main Content -->
<div class="booking-section">
    <div class="container">
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" id="bookingForm">
            <div class="row">
                <!-- Left Column - Booking Form -->
                <div class="col-lg-6">
                    <div class="route-info-card">
                        <h3>
                            <i class="fas fa-user"></i> Your Details
                        </h3>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Name of Lead Person *</label>
                                <input type="text" name="guest_name" class="form-control" required 
                                       value="<?php echo htmlspecialchars($_POST['guest_name'] ?? ''); ?>">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">PH no (wats app no) *</label>
                                <input type="tel" name="guest_phone" class="form-control" required 
                                       value="<?php echo htmlspecialchars($_POST['guest_phone'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Mail id *</label>
                            <input type="email" name="guest_email" class="form-control" required 
                                   value="<?php echo htmlspecialchars($_POST['guest_email'] ?? ''); ?>">
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Travel Date *</label>
                                <input type="date" name="travel_date" class="form-control" required 
                                       min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>"
                                       value="<?php echo htmlspecialchars($_POST['travel_date'] ?? ''); ?>">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Number of Passengers *</label>
                                <input type="number" name="num_passengers" id="numPassengers" class="form-control" 
                                       min="1" max="15" required 
                                       value="<?php echo htmlspecialchars($_POST['num_passengers'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Note</label>
                            <textarea name="special_requirements" class="form-control" rows="3" 
                                      placeholder="Any special requests or notes..."><?php echo htmlspecialchars($_POST['special_requirements'] ?? ''); ?></textarea>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="vehicle_terms" id="vehicleTerms" 
                                       value="1" <?php echo (isset($_POST['vehicle_terms']) && $_POST['vehicle_terms'] == '1') ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="vehicleTerms">
                                    Vehicle are for 8 Hours Or 80 Kms per day, No meals included
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column - Cab Selection -->
                <div class="col-lg-6">
                    <div class="route-info-card">
                        <h3>
                            <i class="fas fa-car"></i> Select Cab & Trip Type
                        </h3>

                        <!-- Trip Type Selection -->
                        <label class="form-label">Trip Type *</label>
                        <div class="trip-type-selector">
                            <label class="trip-type-option <?php echo ($_POST['trip_type'] ?? '') === 'one_way' ? 'active' : ''; ?>">
                                <input type="radio" name="trip_type" value="one_way" required 
                                       <?php echo ($_POST['trip_type'] ?? '') === 'one_way' ? 'checked' : ''; ?>>
                                <div style="font-weight: 600; font-size: 1.1rem;">
                                    <i class="fas fa-arrow-right"></i> One Way
                                </div>
                            </label>
                            <label class="trip-type-option <?php echo ($_POST['trip_type'] ?? '') === 'round_trip' ? 'active' : ''; ?>">
                                <input type="radio" name="trip_type" value="round_trip" required 
                                       <?php echo ($_POST['trip_type'] ?? '') === 'round_trip' ? 'checked' : ''; ?>>
                                <div style="font-weight: 600; font-size: 1.1rem;">
                                    <i class="fas fa-arrows-alt-h"></i> Round Trip
                                </div>
                            </label>
                        </div>

                        <!-- Cab Options -->
                        <label class="form-label mt-3">Select Cab *</label>
                        <div id="cabPricingOptions">
                            <?php foreach ($pricing_options as $pricing): 
                                $features = json_decode($pricing['features'], true) ?: [];
                            ?>
                                <div class="pricing-card <?php echo ($_POST['pricing_id'] ?? '') == $pricing['id'] ? 'selected' : ''; ?>" 
                                     data-pricing-id="<?php echo $pricing['id']; ?>"
                                     data-max-passengers="<?php echo $pricing['max_persons']; ?>"
                                     data-one-way="<?php echo $pricing['one_way_price']; ?>"
                                     data-round-trip="<?php echo $pricing['round_trip_price']; ?>">
                                    
                                    <input type="radio" name="pricing_id" value="<?php echo $pricing['id']; ?>" 
                                           <?php echo ($_POST['pricing_id'] ?? '') == $pricing['id'] ? 'checked' : ''; ?>>
                                    
                                    <h5 style="margin-bottom: 8px; font-weight: 600;">
                                        <?php echo htmlspecialchars($pricing['display_name']); ?>
                                    </h5>
                                    
                                    <p style="color: #6c757d; font-size: 0.9rem; margin-bottom: 10px;">
                                        <?php echo htmlspecialchars($pricing['cab_description']); ?>
                                    </p>
                                    
                                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px; font-size: 0.85rem; color: #6c757d;">
                                        <span>
                                            <i class="fas fa-users" style="color: #667eea;"></i> 
                                            Up to <?php echo $pricing['max_persons']; ?> passengers
                                        </span>
                                    </div>
                                    
                                    <?php if (!empty($features)): ?>
                                        <div style="margin-bottom: 12px;">
                                            <?php foreach (array_slice($features, 0, 3) as $feature): ?>
                                                <span class="badge bg-secondary me-1" style="font-size: 0.7rem;">
                                                    <?php echo htmlspecialchars($feature); ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 12px; border-top: 1px solid #e0e0e0;">
                                        <div>
                                            <div style="font-size: 0.75rem; color: #667eea; text-transform: uppercase;">One Way</div>
                                            <div style="font-size: 1.3rem; font-weight: 700; color: #667eea;" class="one-way-price">
                                                <?php echo formatPriceINR($pricing['one_way_price']); ?>
                                            </div>
                                        </div>
                                        <div class="text-end">
                                            <div style="font-size: 0.75rem; color: #667eea; text-transform: uppercase;">Round Trip</div>
                                            <div style="font-size: 1.3rem; font-weight: 700; color: #667eea;" class="round-trip-price">
                                                <?php echo formatPriceINR($pricing['round_trip_price']); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <button type="submit" class="btn w-100 mt-3 booking-submit-btn">
                            <i class="fas fa-check-circle"></i> Confirm Booking
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Pricing card selection
    document.querySelectorAll('.pricing-card').forEach(function(card) {
        card.addEventListener('click', function() {
            document.querySelectorAll('.pricing-card').forEach(c => c.classList.remove('selected'));
            this.classList.add('selected');
            this.querySelector('input[type="radio"]').checked = true;
        });
    });
    
    // Trip type selection
    document.querySelectorAll('.trip-type-option').forEach(function(option) {
        option.addEventListener('click', function() {
            document.querySelectorAll('.trip-type-option').forEach(o => o.classList.remove('active'));
            this.classList.add('active');
            this.querySelector('input[type="radio"]').checked = true;
        });
    });
    
    // Passenger capacity validation
    document.getElementById('numPassengers').addEventListener('input', function() {
        const numPassengers = parseInt(this.value) || 0;
        
        document.querySelectorAll('.pricing-card').forEach(function(card) {
            const maxPassengers = parseInt(card.dataset.maxPassengers);
            
            if (numPassengers > maxPassengers) {
                card.style.opacity = '0.5';
                card.style.pointerEvents = 'none';
                card.classList.remove('selected');
                card.querySelector('input[type="radio"]').checked = false;
            } else {
                card.style.opacity = '1';
                card.style.pointerEvents = 'auto';
            }
        });
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>

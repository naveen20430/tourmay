<?php
require_once 'config/config.php';
require_once 'includes/cab_options.php';

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
    if (empty($cab_type)) $errors[] = 'Cab type selection is required';
    
    // Get tour details
    $tour = $db->fetch("SELECT * FROM tours WHERE id = ?", [$tour_id]);
    if (!$tour) {
        $errors[] = 'Invalid tour selection';
    }
    
    if (empty($errors)) {
        // Initialize cab options helper
        $cabOptions = new CabOptions($db);
        
        // Validate cab selection can accommodate the number of people
        if (!$cabOptions->canAccommodate($cab_type, $people)) {
            $errors[] = 'Selected cab type cannot accommodate ' . $people . ' people';
        }
        
        // Calculate total amount
        // For this demo, using a base tour price
        $base_tour_price = 5000; // You can get this from tour table
        $total_amount = $base_tour_price * $people;
        
        // Calculate cab charges - try to get tour-specific pricing first
        $cab_price = getCabPriceForTour($tour['title'], $cab_type, $db);
        $total_with_cab = $total_amount + $cab_price;
        
        // Generate booking number
        $booking_number = 'TH' . date('Y') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        try {
            $booking_id = $db->execute(
                "INSERT INTO bookings (tour_id, name, email, contact, date_of_travel, no_of_pax, cab_type, cab_price, total_with_cab, address, status, created_at) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())",
                [$tour_id, $guest_name, $guest_email, $guest_phone, $tour_date, $people, $cab_type, $cab_price, $total_with_cab, $special_requirements]
            );
            
            if ($booking_id) {
                $success = true;
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
    $tour = $db->fetch("SELECT * FROM tours WHERE id = ?", [$tour_id]);
}

// Initialize cab options for form
$cabOptions = new CabOptions($db);
$availableCabs = $cabOptions->getCabOptionsForDropdown();

// Get all tours for dropdown
$all_tours = $db->fetchAll("SELECT * FROM tours ORDER BY title");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Your Tour with Cab Options</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .booking-container { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 50px 0; }
        .booking-card { background: white; border-radius: 15px; box-shadow: 0 20px 40px rgba(0,0,0,0.1); }
        .tour-info { background: #f8f9fa; border-radius: 10px; padding: 20px; margin-bottom: 30px; }
        .cab-option { border: 2px solid #dee2e6; border-radius: 10px; padding: 15px; margin: 10px 0; cursor: pointer; transition: all 0.3s ease; }
        .cab-option:hover { border-color: #007bff; background: #f8f9ff; }
        .cab-option.selected { border-color: #28a745; background: #d4edda; }
        .cab-price { font-size: 1.2em; font-weight: bold; color: #28a745; }
        .cab-features { font-size: 0.9em; color: #6c757d; }
        .price-summary { background: #e7f3ff; border-radius: 10px; padding: 20px; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="booking-container">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="booking-card p-5">
                        <?php if ($success): ?>
                            <div class="text-center">
                                <div class="mb-4">
                                    <i class="fas fa-check-circle text-success" style="font-size: 4em;"></i>
                                </div>
                                <h2 class="text-success mb-4">Booking Confirmed!</h2>
                                <div class="alert alert-success">
                                    <h4>Booking Number: <strong><?php echo $booking_number; ?></strong></h4>
                                    <p class="mb-0">We've received your booking request. Our team will contact you within 24 hours to confirm your booking and payment details.</p>
                                    
                                    <?php if (!empty($cab_type)): ?>
                                        <hr>
                                        <div class="row mt-3">
                                            <div class="col-md-6">
                                                <strong>Selected Cab:</strong> <?php echo getCabDisplayName($cab_type); ?>
                                            </div>
                                            <div class="col-md-6">
                                                <strong>Total Amount:</strong> ₹<?php echo number_format($total_with_cab, 0); ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="mt-4">
                                    <a href="index.php" class="btn btn-primary me-3">Back to Home</a>
                                    <a href="#" class="btn btn-outline-primary">Browse More Tours</a>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="text-center mb-4">
                                <h2><i class="fas fa-calendar-plus me-2"></i>Book Your Tour</h2>
                                <p class="text-muted">Fill in the details below to reserve your spot</p>
                            </div>

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
                                    <div class="col-md-6">
                                        <!-- Personal Information -->
                                        <h4><i class="fas fa-user me-2"></i>Personal Information</h4>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Full Name *</label>
                                            <input type="text" name="guest_name" class="form-control" required 
                                                   value="<?php echo htmlspecialchars($_POST['guest_name'] ?? ''); ?>">
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Email Address *</label>
                                            <input type="email" name="guest_email" class="form-control" required 
                                                   value="<?php echo htmlspecialchars($_POST['guest_email'] ?? ''); ?>">
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Phone Number *</label>
                                            <input type="tel" name="guest_phone" class="form-control" required 
                                                   value="<?php echo htmlspecialchars($_POST['guest_phone'] ?? ''); ?>">
                                        </div>
                                        
                                        <!-- Tour Selection -->
                                        <h4><i class="fas fa-map me-2"></i>Tour Details</h4>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Select Tour *</label>
                                            <select name="tour_id" class="form-select" required id="tourSelect">
                                                <option value="">Choose a tour...</option>
                                                <?php foreach ($all_tours as $t): ?>
                                                    <option value="<?php echo $t['id']; ?>" 
                                                            data-title="<?php echo htmlspecialchars($t['title']); ?>"
                                                            <?php echo ($_POST['tour_id'] ?? $_GET['tour_id'] ?? '') == $t['id'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($t['title']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Tour Date *</label>
                                                <input type="date" name="tour_date" class="form-control" required 
                                                       min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>"
                                                       value="<?php echo htmlspecialchars($_POST['tour_date'] ?? ''); ?>">
                                            </div>
                                            
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Number of People *</label>
                                                <input type="number" name="people" class="form-control" min="1" max="15" required 
                                                       value="<?php echo htmlspecialchars($_POST['people'] ?? ''); ?>" id="peopleCount">
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Special Requirements</label>
                                            <textarea name="special_requirements" class="form-control" rows="3" 
                                                    placeholder="Any special requirements or notes..."><?php echo htmlspecialchars($_POST['special_requirements'] ?? ''); ?></textarea>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <!-- Cab Selection -->
                                        <h4><i class="fas fa-car me-2"></i>Select Your Cab</h4>
                                        <p class="text-muted mb-3">Choose the best cab option for your group</p>
                                        
                                        <input type="hidden" name="cab_type" id="selectedCabType" value="<?php echo htmlspecialchars($_POST['cab_type'] ?? ''); ?>">
                                        
                                        <div id="cabOptions">
                                            <?php foreach ($availableCabs as $cab): ?>
                                                <div class="cab-option" data-cab-type="<?php echo $cab['value']; ?>" 
                                                     data-max-passengers="<?php echo $cab['max_passengers']; ?>"
                                                     data-base-price="<?php echo $cab['price']; ?>">
                                                    <div class="row align-items-center">
                                                        <div class="col-md-8">
                                                            <h5 class="mb-1"><?php echo $cab['text']; ?></h5>
                                                            <p class="mb-1 cab-features">
                                                                <i class="fas fa-users me-1"></i>Up to <?php echo $cab['max_passengers']; ?> passengers
                                                            </p>
                                                            <p class="mb-0 cab-features"><?php echo $cab['description']; ?></p>
                                                        </div>
                                                        <div class="col-md-4 text-end">
                                                            <div class="cab-price" data-tour-name="">
                                                                ₹<span class="price-value"><?php echo number_format($cab['price'], 0); ?></span>
                                                            </div>
                                                            <small class="text-muted">per day</small>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        
                                        <!-- Price Summary -->
                                        <div class="price-summary" id="priceSummary" style="display: none;">
                                            <h5><i class="fas fa-calculator me-2"></i>Price Summary</h5>
                                            <div class="d-flex justify-content-between mb-2">
                                                <span>Tour Cost:</span>
                                                <span id="tourCost">₹0</span>
                                            </div>
                                            <div class="d-flex justify-content-between mb-2">
                                                <span>Cab Charges:</span>
                                                <span id="cabCharges">₹0</span>
                                            </div>
                                            <hr>
                                            <div class="d-flex justify-content-between">
                                                <strong>Total Amount:</strong>
                                                <strong id="totalAmount">₹0</strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="text-center mt-4">
                                    <button type="submit" class="btn btn-success btn-lg px-5">
                                        <i class="fas fa-check me-2"></i>Confirm Booking
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Tour-specific pricing data (you can fetch this via AJAX or embed from PHP)
        const tourPricing = <?php 
            $tour_pricing_data = [];
            $all_tour_pricing = $db->fetchAll("SELECT * FROM tour_cab_pricing");
            foreach ($all_tour_pricing as $tp) {
                $tour_pricing_data[$tp['tour_name']] = [
                    'sedan' => $tp['sedan_price'],
                    'ertiga' => $tp['ertiga_price'], 
                    'innova' => $tp['innova_price'],
                    'tempo_traveller' => $tp['tempo_traveller_price']
                ];
            }
            echo json_encode($tour_pricing_data);
        ?>;

        const baseTourPrice = 5000; // Base tour price per person

        // Cab selection handling
        document.querySelectorAll('.cab-option').forEach(function(option) {
            option.addEventListener('click', function() {
                // Remove selected class from all options
                document.querySelectorAll('.cab-option').forEach(function(opt) {
                    opt.classList.remove('selected');
                });
                
                // Add selected class to clicked option
                this.classList.add('selected');
                
                // Update hidden input
                const cabType = this.dataset.cabType;
                document.getElementById('selectedCabType').value = cabType;
                
                // Update pricing
                updatePricing();
            });
        });

        // Tour selection change
        document.getElementById('tourSelect').addEventListener('change', function() {
            updateCabPricing();
            updatePricing();
        });

        // People count change
        document.getElementById('peopleCount').addEventListener('input', function() {
            filterCabsByCapacity();
            updatePricing();
        });

        function updateCabPricing() {
            const selectedTour = document.getElementById('tourSelect');
            const tourName = selectedTour.options[selectedTour.selectedIndex].dataset.title;
            
            if (tourPricing[tourName]) {
                // Update cab prices with tour-specific pricing
                document.querySelectorAll('.cab-option').forEach(function(option) {
                    const cabType = option.dataset.cabType;
                    const priceElement = option.querySelector('.price-value');
                    
                    if (tourPricing[tourName][cabType]) {
                        priceElement.textContent = new Intl.NumberFormat('en-IN').format(tourPricing[tourName][cabType]);
                        option.dataset.currentPrice = tourPricing[tourName][cabType];
                    } else {
                        // Use base price
                        priceElement.textContent = new Intl.NumberFormat('en-IN').format(option.dataset.basePrice);
                        option.dataset.currentPrice = option.dataset.basePrice;
                    }
                });
            } else {
                // Use base pricing
                document.querySelectorAll('.cab-option').forEach(function(option) {
                    const priceElement = option.querySelector('.price-value');
                    priceElement.textContent = new Intl.NumberFormat('en-IN').format(option.dataset.basePrice);
                    option.dataset.currentPrice = option.dataset.basePrice;
                });
            }
        }

        function filterCabsByCapacity() {
            const peopleCount = parseInt(document.getElementById('peopleCount').value) || 0;
            
            document.querySelectorAll('.cab-option').forEach(function(option) {
                const maxPassengers = parseInt(option.dataset.maxPassengers);
                
                if (peopleCount > maxPassengers) {
                    option.style.opacity = '0.5';
                    option.style.pointerEvents = 'none';
                    option.classList.remove('selected');
                    
                    // Clear selection if this cab was selected
                    if (document.getElementById('selectedCabType').value === option.dataset.cabType) {
                        document.getElementById('selectedCabType').value = '';
                    }
                } else {
                    option.style.opacity = '1';
                    option.style.pointerEvents = 'auto';
                }
            });
        }

        function updatePricing() {
            const peopleCount = parseInt(document.getElementById('peopleCount').value) || 0;
            const selectedCab = document.querySelector('.cab-option.selected');
            
            if (peopleCount > 0 && selectedCab) {
                const tourCost = baseTourPrice * peopleCount;
                const cabCharges = parseFloat(selectedCab.dataset.currentPrice || selectedCab.dataset.basePrice);
                const totalAmount = tourCost + cabCharges;
                
                document.getElementById('tourCost').textContent = '₹' + new Intl.NumberFormat('en-IN').format(tourCost);
                document.getElementById('cabCharges').textContent = '₹' + new Intl.NumberFormat('en-IN').format(cabCharges);
                document.getElementById('totalAmount').textContent = '₹' + new Intl.NumberFormat('en-IN').format(totalAmount);
                
                document.getElementById('priceSummary').style.display = 'block';
            } else {
                document.getElementById('priceSummary').style.display = 'none';
            }
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            // Set selected cab if coming back from form submission
            const selectedCabType = document.getElementById('selectedCabType').value;
            if (selectedCabType) {
                const cabOption = document.querySelector(`[data-cab-type="${selectedCabType}"]`);
                if (cabOption) {
                    cabOption.classList.add('selected');
                }
            }
            
            updateCabPricing();
            filterCabsByCapacity();
            updatePricing();
        });
    </script>
</body>
</html>

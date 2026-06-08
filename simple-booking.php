<?php
require_once 'config/config.php';

$errors = [];
$success = false;
$booking_number = '';

// Get tour details if tour_id is provided
$tour = null;
if (!empty($_GET['tour_id']) || !empty($_POST['tour_id'])) {
    $tour_id = $_GET['tour_id'] ?? $_POST['tour_id'];
    try {
        $tour = $db->fetch("
            SELECT t.*, d.name as destination_name, d.country 
            FROM tours t 
            LEFT JOIN destinations d ON t.destination_id = d.id 
            WHERE t.id = ? AND t.status = 'active'
        ", [$tour_id]);
    } catch (Exception $e) {
        $errors[] = 'Error loading tour details.';
    }
}

if ($_POST) {
    $tour_id = $_POST['tour_id'] ?? '';
    $tour_date = $_POST['tour_date'] ?? '';
    $people = $_POST['people'] ?? '';
    $guest_name = $_POST['guest_name'] ?? '';
    $guest_email = $_POST['guest_email'] ?? '';
    $guest_phone = $_POST['guest_phone'] ?? '';
    $special_requirements = $_POST['special_requirements'] ?? '';
    
    // Validation
    if (empty($tour_id)) $errors[] = 'Tour selection is required';
    if (empty($tour_date)) $errors[] = 'Tour date is required';
    if (empty($people)) $errors[] = 'Number of people is required';
    if (empty($guest_name)) $errors[] = 'Your name is required';
    if (empty($guest_email)) $errors[] = 'Your email is required';
    if (empty($guest_phone)) $errors[] = 'Your phone number is required';
    
    // Get tour details
    if (!empty($tour_id) && empty($errors)) {
        try {
            $tour = $db->fetch("SELECT * FROM tours WHERE id = ? AND status = 'active'", [$tour_id]);
            if (!$tour) {
                $errors[] = 'Invalid tour selection';
            }
        } catch (Exception $e) {
            $errors[] = 'Error validating tour.';
        }
    }
    
    if (empty($errors)) {
        // Calculate total amount
        $price_per_person = $tour['discount_price'] ?: $tour['price'];
        $total_amount = $price_per_person * $people;
        
        // Generate booking number
        $booking_number = 'TH' . date('Y') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        try {
            $result = $db->execute(
                "INSERT INTO bookings (booking_number, tour_id, guest_name, guest_email, guest_phone, number_of_people, tour_date, total_amount, special_requirements, booking_status, payment_status, created_at) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', NOW())",
                [$booking_number, $tour_id, $guest_name, $guest_email, $guest_phone, $people, $tour_date, $total_amount, $special_requirements]
            );
            
            if ($result >= 0) {
                $success = true;
            } else {
                $errors[] = 'Failed to create booking. Please try again.';
            }
        } catch (Exception $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

$page_title = 'Book Your Tour - ' . getSetting('site_name');
$current_page = 'booking';

include 'includes/header.php';
?>

<div class="page-wrapper">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <?php if ($success): ?>
                        <div class="card-body text-center py-5">
                            <div class="mb-4">
                                <i class="fas fa-check-circle text-success" style="font-size: 4em;"></i>
                            </div>
                            <h2 class="text-success mb-4">Booking Confirmed!</h2>
                            <div class="alert alert-success">
                                <h4>Booking Number: <strong><?php echo $booking_number; ?></strong></h4>
                                <p class="mb-0">We've received your booking request. Our team will contact you within 24 hours to confirm your booking and payment details.</p>
                                
                                <hr>
                                <div class="row mt-3">
                                    <div class="col-md-6">
                                        <strong>Total Amount:</strong> ₹<?php echo number_format($total_amount, 0); ?>
                                    </div>
                                    <div class="col-md-6">
                                        <strong>People:</strong> <?php echo $people; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-4">
                                <a href="<?php echo navUrl('home'); ?>" class="btn btn-primary me-3">Back to Home</a>
                                <a href="<?php echo navUrl('tours'); ?>" class="btn btn-outline-primary">Browse More Tours</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="card-header">
                            <h2 class="mb-0"><i class="fas fa-calendar-plus me-2"></i>Book Your Tour</h2>
                        </div>
                        <div class="card-body">
                            
                            <?php if (!empty($errors)): ?>
                                <div class="alert alert-danger">
                                    <ul class="mb-0">
                                        <?php foreach ($errors as $error): ?>
                                            <li><?php echo htmlspecialchars($error); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>

                            <?php if ($tour): ?>
                                <div class="alert alert-info mb-4">
                                    <div class="row align-items-center">
                                        <div class="col-md-8">
                                            <h4 class="mb-1"><?php echo htmlspecialchars($tour['title']); ?></h4>
                                            <p class="text-muted mb-2">
                                                <i class="fas fa-map-marker-alt me-1"></i>
                                                <?php echo htmlspecialchars($tour['destination_name'] . ', ' . $tour['country']); ?>
                                            </p>
                                            <p class="mb-0">
                                                <span class="badge bg-primary me-2"><?php echo $tour['duration_days']; ?> Days</span>
                                                <span class="badge bg-secondary me-2">Max <?php echo $tour['max_people']; ?> People</span>
                                                <span class="badge bg-info"><?php echo ucfirst($tour['difficulty_level']); ?></span>
                                            </p>
                                        </div>
                                        <div class="col-md-4 text-md-end">
                                            <div class="h3 text-primary">
                                                <?php if ($tour['discount_price']): ?>
                                                    <small class="text-decoration-line-through text-muted">₹<?php echo number_format($tour['price'], 0); ?></small>
                                                    ₹<?php echo number_format($tour['discount_price'], 0); ?>
                                                <?php else: ?>
                                                    ₹<?php echo number_format($tour['price'], 0); ?>
                                                <?php endif; ?>
                                            </div>
                                            <small class="text-muted">per person</small>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <form method="POST" id="bookingForm">
                                <?php if ($tour): ?>
                                    <input type="hidden" name="tour_id" value="<?php echo $tour['id']; ?>">
                                <?php else: ?>
                                    <div class="mb-3">
                                        <label class="form-label">Select Tour *</label>
                                        <select name="tour_id" class="form-select" required>
                                            <option value="">Choose a tour...</option>
                                            <?php
                                            try {
                                                $available_tours = $db->fetchAll("SELECT id, title, price, discount_price FROM tours WHERE status = 'active' ORDER BY title");
                                                foreach ($available_tours as $t):
                                            ?>
                                                <option value="<?php echo $t['id']; ?>" <?php echo ($_POST['tour_id'] ?? '') == $t['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($t['title']); ?> - ₹<?php echo number_format($t['discount_price'] ?: $t['price'], 0); ?>
                                                </option>
                                            <?php 
                                                endforeach;
                                            } catch (Exception $e) {
                                                echo '<option value="">Error loading tours</option>';
                                            }
                                            ?>
                                        </select>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Tour Date *</label>
                                        <input type="date" name="tour_date" class="form-control" 
                                               value="<?php echo $_POST['tour_date'] ?? ''; ?>" 
                                               min="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Number of People *</label>
                                        <input type="number" name="people" class="form-control" 
                                               value="<?php echo $_POST['people'] ?? ''; ?>" 
                                               min="1" max="<?php echo $tour ? $tour['max_people'] : '20'; ?>" required>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Your Name *</label>
                                        <input type="text" name="guest_name" class="form-control" 
                                               value="<?php echo htmlspecialchars($_POST['guest_name'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Your Email *</label>
                                        <input type="email" name="guest_email" class="form-control" 
                                               value="<?php echo htmlspecialchars($_POST['guest_email'] ?? ''); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Your Phone Number *</label>
                                    <input type="tel" name="guest_phone" class="form-control" 
                                           value="<?php echo htmlspecialchars($_POST['guest_phone'] ?? ''); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Special Requirements (Optional)</label>
                                    <textarea name="special_requirements" class="form-control" rows="3" 
                                              placeholder="Any special requests, dietary requirements, accessibility needs, etc."><?php echo htmlspecialchars($_POST['special_requirements'] ?? ''); ?></textarea>
                                </div>
                                
                                <?php if ($tour): ?>
                                    <div class="alert alert-light mb-4">
                                        <h6>Booking Summary:</h6>
                                        <div class="row">
                                            <div class="col-sm-6">
                                                <strong>Price per person:</strong> 
                                                ₹<?php echo number_format($tour['discount_price'] ?: $tour['price'], 0); ?>
                                            </div>
                                            <div class="col-sm-6">
                                                <strong>Total will be calculated</strong> based on number of people
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="fas fa-check me-2"></i>Submit Booking Request
                                    </button>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Form enhancements
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('bookingForm');
    const peopleInput = document.querySelector('input[name="people"]');
    
    // Update total calculation if tour is selected
    <?php if ($tour): ?>
        if (peopleInput) {
            peopleInput.addEventListener('input', function() {
                const people = parseInt(this.value) || 0;
                const pricePerPerson = <?php echo $tour['discount_price'] ?: $tour['price']; ?>;
                const total = people * pricePerPerson;
                
                // Update summary if exists
                const summaryDiv = document.querySelector('.alert-light');
                if (summaryDiv && people > 0) {
                    summaryDiv.innerHTML = `
                        <h6>Booking Summary:</h6>
                        <div class="row">
                            <div class="col-sm-6">
                                <strong>Price per person:</strong> ₹${pricePerPerson.toLocaleString()}
                            </div>
                            <div class="col-sm-6">
                                <strong>Total for ${people} people:</strong> ₹${total.toLocaleString()}
                            </div>
                        </div>
                    `;
                }
            });
        }
    <?php endif; ?>
    
    // Form validation
    form.addEventListener('submit', function(e) {
        const requiredFields = form.querySelectorAll('[required]');
        let hasErrors = false;
        
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                field.classList.add('is-invalid');
                hasErrors = true;
            } else {
                field.classList.remove('is-invalid');
            }
        });
        
        if (hasErrors) {
            e.preventDefault();
            alert('Please fill in all required fields.');
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>
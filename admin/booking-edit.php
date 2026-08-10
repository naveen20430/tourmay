<?php
require_once '../config/config.php';
require_once '../includes/booking_driver_mail.php';
requireLogin();

ensureBookingDriverSchema();

$success_message = '';
$error_message = '';
$booking_id = (int)($_GET['id'] ?? 0);

if (!$booking_id) {
    header('Location: bookings.php');
    exit;
}

// Get booking details
try {
    $booking = $db->fetch("
        SELECT b.*, t.title as tour_title, d.name as destination_name, d.country 
        FROM bookings b 
        LEFT JOIN tours t ON b.tour_id = t.id 
        LEFT JOIN destinations d ON t.destination_id = d.id 
        WHERE b.id = ?
    ", [$booking_id]);
    
    if (!$booking) {
        $error_message = 'Booking not found.';
    }
} catch (Exception $e) {
    $error_message = 'Error loading booking: ' . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $booking) {
    $previous_status = $booking['booking_status'] ?? '';
    $tour_id = (int)($_POST['tour_id'] ?? $booking['tour_id']);
    $guest_name = trim($_POST['guest_name'] ?? '');
    $guest_email = trim($_POST['guest_email'] ?? '');
    $guest_phone = trim($_POST['guest_phone'] ?? '');
    $number_of_people = (int)($_POST['number_of_people'] ?? 1);
    $tour_date = $_POST['tour_date'] ?? '';
    $total_amount = (float)($_POST['total_amount'] ?? 0);
    $paid_amount = (float)($_POST['paid_amount'] ?? 0);
    $payment_status = $_POST['payment_status'] ?? 'pending';
    $payment_method = $_POST['payment_method'] ?? '';
    $booking_status = $_POST['booking_status'] ?? 'pending';
    $special_requirements = trim($_POST['special_requirements'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $driver_name = trim($_POST['driver_name'] ?? '');
    $vehicle_number = trim($_POST['vehicle_number'] ?? '');
    $driver_contact = trim($_POST['driver_contact'] ?? '');
    $send_driver_email = !empty($_POST['send_driver_email']);
    
    // Validation
    $errors = [];
    
    if (!$tour_id) {
        $errors[] = 'Please select a tour';
    }
    
    if (empty($guest_name)) {
        $errors[] = 'Guest name is required';
    }
    
    if (empty($guest_email)) {
        $errors[] = 'Guest email is required';
    } elseif (!filter_var($guest_email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address';
    }
    
    if ($number_of_people < 1) {
        $errors[] = 'Number of people must be at least 1';
    }
    
    if (empty($tour_date)) {
        $errors[] = 'Tour date is required';
    }
    
    if ($total_amount <= 0) {
        $errors[] = 'Total amount must be greater than 0';
    }

    if ($booking_status === 'confirmed') {
        if ($driver_name === '') {
            $errors[] = 'Driver name is required when confirming a booking';
        }
        if ($vehicle_number === '') {
            $errors[] = 'Vehicle number is required when confirming a booking';
        }
        if ($driver_contact === '') {
            $errors[] = 'Driver contact number is required when confirming a booking';
        }
    }
    
    if (empty($errors)) {
        try {
            // Update booking
            $result = $db->execute("
                UPDATE bookings SET 
                    tour_id = ?, guest_name = ?, guest_email = ?, guest_phone = ?,
                    number_of_people = ?, tour_date = ?, total_amount = ?, paid_amount = ?,
                    payment_status = ?, payment_method = ?, booking_status = ?,
                    special_requirements = ?, notes = ?,
                    driver_name = ?, vehicle_number = ?, driver_contact = ?,
                    updated_at = NOW()
                WHERE id = ?
            ", [
                $tour_id, $guest_name, $guest_email, $guest_phone,
                $number_of_people, $tour_date, $total_amount, $paid_amount,
                $payment_status, $payment_method, $booking_status,
                $special_requirements, $notes,
                $driver_name !== '' ? $driver_name : null,
                $vehicle_number !== '' ? $vehicle_number : null,
                $driver_contact !== '' ? $driver_contact : null,
                $booking_id
            ]);
            
            if ($result >= 0) {
                $success_message = 'Booking updated successfully!';
                // Refresh booking data
                $booking = $db->fetch("
                    SELECT b.*, t.title as tour_title, d.name as destination_name, d.country 
                    FROM bookings b 
                    LEFT JOIN tours t ON b.tour_id = t.id 
                    LEFT JOIN destinations d ON t.destination_id = d.id 
                    WHERE b.id = ?
                ", [$booking_id]);

                $shouldEmail = $booking_status === 'confirmed' && (
                    $send_driver_email || $previous_status !== 'confirmed'
                );
                if ($shouldEmail && $booking) {
                    $mailResult = sendBookingDriverDetailsEmail($booking);
                    if (!empty($mailResult['ok'])) {
                        markBookingDriverDetailsSent($booking_id);
                        $success_message .= ' Driver details emailed to ' . htmlspecialchars($mailResult['to']) . '.';
                    } else {
                        $error_message = 'Booking saved, but driver email failed: ' . htmlspecialchars($mailResult['error'] ?? 'Unknown error');
                    }
                }
            } else {
                $error_message = 'Failed to update booking. Please try again.';
            }
            
        } catch (Exception $e) {
            $error_message = 'Error updating booking: ' . $e->getMessage();
        }
    } else {
        $error_message = implode('<br>', $errors);
    }
}

// Get tours for dropdown
try {
    $tours = $db->fetchAll("
        SELECT t.*, d.name as destination_name, d.country 
        FROM tours t 
        LEFT JOIN destinations d ON t.destination_id = d.id 
        WHERE t.status = 'active' 
        ORDER BY t.title
    ");
} catch (Exception $e) {
    $tours = [];
}

$page_title = 'Edit Booking';
include 'includes/header.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Edit Booking</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="bookings.php">Bookings</a></li>
                        <li class="breadcrumb-item active">Edit</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle mr-2"></i>
                    <?php echo $success_message; ?>
                    <button type="button" class="close" data-dismiss="alert">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    <?php echo $error_message; ?>
                    <button type="button" class="close" data-dismiss="alert">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>

            <?php if ($booking): ?>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-edit mr-2"></i>
                            Edit Booking #<?php echo htmlspecialchars($booking['booking_number']); ?>
                        </h3>
                    </div>
                    
                    <form method="POST" action="">
<?php echo function_exists('csrfField') ? csrfField() : ''; ?>
                        <div class="card-body">
                            <div class="row">
                                <!-- Tour Selection -->
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="tour_id">Select Tour <span class="text-danger">*</span></label>
                                        <select class="form-control" id="tour_id" name="tour_id" required>
                                            <option value="">Select a tour...</option>
                                            <?php foreach ($tours as $tour): ?>
                                                <option value="<?php echo $tour['id']; ?>" 
                                                        data-price="<?php echo $tour['discount_price'] ?: $tour['price']; ?>"
                                                        <?php echo $booking['tour_id'] == $tour['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($tour['title']); ?> 
                                                    (<?php echo htmlspecialchars($tour['destination_name'] . ', ' . $tour['country']); ?>) 
                                                    - ₹<?php echo number_format($tour['discount_price'] ?: $tour['price'], 0); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <!-- Number of People -->
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="number_of_people">Number of People <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control" id="number_of_people" name="number_of_people" 
                                               value="<?php echo htmlspecialchars($booking['number_of_people']); ?>" min="1" max="50" required>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <!-- Guest Name -->
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="guest_name">Guest Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="guest_name" name="guest_name" 
                                               value="<?php echo htmlspecialchars($booking['guest_name']); ?>" required>
                                    </div>
                                </div>
                                
                                <!-- Guest Email -->
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="guest_email">Guest Email <span class="text-danger">*</span></label>
                                        <input type="email" class="form-control" id="guest_email" name="guest_email" 
                                               value="<?php echo htmlspecialchars($booking['guest_email']); ?>" required>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <!-- Guest Phone -->
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="guest_phone">Guest Phone</label>
                                        <input type="tel" class="form-control" id="guest_phone" name="guest_phone" 
                                               value="<?php echo htmlspecialchars($booking['guest_phone']); ?>">
                                    </div>
                                </div>
                                
                                <!-- Tour Date -->
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="tour_date">Tour Date <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" id="tour_date" name="tour_date" 
                                               value="<?php echo htmlspecialchars($booking['tour_date']); ?>" required>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <!-- Total Amount -->
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="total_amount">Total Amount (₹) <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control" id="total_amount" name="total_amount" 
                                               value="<?php echo htmlspecialchars($booking['total_amount']); ?>" min="0" step="0.01" required>
                                    </div>
                                </div>
                                
                                <!-- Paid Amount -->
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="paid_amount">Paid Amount (₹)</label>
                                        <input type="number" class="form-control" id="paid_amount" name="paid_amount" 
                                               value="<?php echo htmlspecialchars($booking['paid_amount']); ?>" min="0" step="0.01">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <!-- Payment Status -->
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="payment_status">Payment Status</label>
                                        <select class="form-control" id="payment_status" name="payment_status">
                                            <option value="pending" <?php echo $booking['payment_status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="partial" <?php echo $booking['payment_status'] == 'partial' ? 'selected' : ''; ?>>Partial</option>
                                            <option value="paid" <?php echo $booking['payment_status'] == 'paid' ? 'selected' : ''; ?>>Paid</option>
                                            <option value="refunded" <?php echo $booking['payment_status'] == 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <!-- Payment Method -->
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="payment_method">Payment Method</label>
                                        <select class="form-control" id="payment_method" name="payment_method">
                                            <option value="">Select method...</option>
                                            <option value="cash" <?php echo $booking['payment_method'] == 'cash' ? 'selected' : ''; ?>>Cash</option>
                                            <option value="bank_transfer" <?php echo $booking['payment_method'] == 'bank_transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                                            <option value="credit_card" <?php echo $booking['payment_method'] == 'credit_card' ? 'selected' : ''; ?>>Credit Card</option>
                                            <option value="debit_card" <?php echo $booking['payment_method'] == 'debit_card' ? 'selected' : ''; ?>>Debit Card</option>
                                            <option value="upi" <?php echo $booking['payment_method'] == 'upi' ? 'selected' : ''; ?>>UPI</option>
                                            <option value="cheque" <?php echo $booking['payment_method'] == 'cheque' ? 'selected' : ''; ?>>Cheque</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <!-- Booking Status -->
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="booking_status">Booking Status</label>
                                        <select class="form-control" id="booking_status" name="booking_status">
                                            <option value="pending" <?php echo $booking['booking_status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="confirmed" <?php echo $booking['booking_status'] == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                            <option value="cancelled" <?php echo $booking['booking_status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                            <option value="completed" <?php echo $booking['booking_status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="card card-outline card-success mb-3" id="driverDetailsCard">
                                <div class="card-header">
                                    <h3 class="card-title">
                                        <i class="fas fa-car mr-2"></i>Driver details
                                    </h3>
                                    <div class="card-tools">
                                        <span class="badge badge-success">Required when confirmed</span>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <p class="text-muted mb-3">
                                        When status is <strong>Confirmed</strong>, these details are emailed to the guest.
                                    </p>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="driver_name">Driver name <span class="text-danger driver-required-mark">*</span></label>
                                                <input type="text" class="form-control" id="driver_name" name="driver_name"
                                                       value="<?php echo htmlspecialchars($booking['driver_name'] ?? ''); ?>"
                                                       placeholder="e.g. Rajesh Kumar">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="vehicle_number">Vehicle number <span class="text-danger driver-required-mark">*</span></label>
                                                <input type="text" class="form-control" id="vehicle_number" name="vehicle_number"
                                                       value="<?php echo htmlspecialchars($booking['vehicle_number'] ?? ''); ?>"
                                                       placeholder="e.g. HP 03 AB 1234">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="driver_contact">Driver contact number <span class="text-danger driver-required-mark">*</span></label>
                                                <input type="tel" class="form-control" id="driver_contact" name="driver_contact"
                                                       value="<?php echo htmlspecialchars($booking['driver_contact'] ?? ''); ?>"
                                                       placeholder="e.g. 9876543210">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="send_driver_email" name="send_driver_email" value="1"
                                            <?php echo ($booking['booking_status'] ?? '') === 'confirmed' ? '' : 'checked'; ?>>
                                        <label class="form-check-label" for="send_driver_email">
                                            Email driver details to guest
                                            <small class="text-muted d-block">Always sent the first time status is set to Confirmed. Check again to resend after edits.</small>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <!-- Special Requirements -->
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="special_requirements">Special Requirements</label>
                                        <textarea class="form-control" id="special_requirements" name="special_requirements" rows="4" 
                                                  placeholder="Any special requirements or requests..."><?php echo htmlspecialchars($booking['special_requirements']); ?></textarea>
                                    </div>
                                </div>
                                
                                <!-- Admin Notes -->
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="notes">Admin Notes</label>
                                        <textarea class="form-control" id="notes" name="notes" rows="4" 
                                                  placeholder="Internal notes for admin reference..."><?php echo htmlspecialchars($booking['notes']); ?></textarea>
                                    </div>
                                </div>
                            </div>

                            <!-- Booking Info Display -->
                            <div class="row">
                                <div class="col-12">
                                    <div class="alert alert-info">
                                        <h6><i class="fas fa-info-circle mr-2"></i>Booking Information</h6>
                                        <div class="row">
                                            <div class="col-md-4">
                                                <strong>Booking Number:</strong> <?php echo htmlspecialchars($booking['booking_number']); ?>
                                            </div>
                                            <div class="col-md-4">
                                                <strong>Created:</strong> <?php echo date('M d, Y H:i', strtotime($booking['created_at'])); ?>
                                            </div>
                                            <div class="col-md-4">
                                                <strong>Last Updated:</strong> <?php echo date('M d, Y H:i', strtotime($booking['updated_at'])); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save mr-1"></i> Update Booking
                            </button>
                            <a href="bookings.php" class="btn btn-secondary ml-2">
                                <i class="fas fa-arrow-left mr-1"></i> Back to Bookings
                            </a>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-body text-center">
                        <i class="fas fa-exclamation-triangle fa-3x text-muted mb-3"></i>
                        <h4>Booking Not Found</h4>
                        <p class="text-muted">The requested booking could not be found.</p>
                        <a href="bookings.php" class="btn btn-primary">
                            <i class="fas fa-arrow-left mr-1"></i> Back to Bookings
                        </a>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const tourSelect = document.getElementById('tour_id');
    const totalAmountInput = document.getElementById('total_amount');
    const statusSelect = document.getElementById('booking_status');
    const sendEmailCheckbox = document.getElementById('send_driver_email');
    const driverFields = [
        document.getElementById('driver_name'),
        document.getElementById('vehicle_number'),
        document.getElementById('driver_contact')
    ];

    function updateTotalAmount() {
        const selectedOption = tourSelect.options[tourSelect.selectedIndex];
        const basePrice = parseFloat(selectedOption.dataset.price) || 0;
        if (basePrice > 0) {
            totalAmountInput.value = basePrice.toFixed(2);
        }
    }

    function syncDriverRequired() {
        const confirmed = statusSelect && statusSelect.value === 'confirmed';
        driverFields.forEach(function(field) {
            if (field) field.required = !!confirmed;
        });
        document.querySelectorAll('.driver-required-mark').forEach(function(el) {
            el.style.display = confirmed ? '' : 'none';
        });
        if (confirmed && sendEmailCheckbox && statusSelect.dataset.prev !== 'confirmed') {
            sendEmailCheckbox.checked = true;
        }
        if (statusSelect) {
            statusSelect.dataset.prev = statusSelect.value;
        }
    }

    if (tourSelect) {
        tourSelect.addEventListener('change', updateTotalAmount);
    }
    if (statusSelect) {
        statusSelect.dataset.prev = statusSelect.value;
        statusSelect.addEventListener('change', syncDriverRequired);
        syncDriverRequired();
    }
});
</script>

<?php include 'includes/footer.php'; ?>
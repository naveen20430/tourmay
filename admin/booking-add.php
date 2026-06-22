<?php
require_once '../config/config.php';
requireLogin();

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $tour_id = (int)($_POST['tour_id'] ?? 0);
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
    
    if (empty($errors)) {
        try {
            // Generate booking number
            $booking_number = 'BK' . date('Y') . sprintf('%06d', rand(1, 999999));
            
            // Check if booking number already exists
            while ($db->fetch("SELECT id FROM bookings WHERE booking_number = ?", [$booking_number])) {
                $booking_number = 'BK' . date('Y') . sprintf('%06d', rand(1, 999999));
            }
            
            // Insert booking
            $result = $db->execute("
                INSERT INTO bookings (
                    booking_number, tour_id, guest_name, guest_email, guest_phone,
                    number_of_people, tour_date, total_amount, paid_amount,
                    payment_status, payment_method, booking_status,
                    special_requirements, notes, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ", [
                $booking_number, $tour_id, $guest_name, $guest_email, $guest_phone,
                $number_of_people, $tour_date, $total_amount, $paid_amount,
                $payment_status, $payment_method, $booking_status,
                $special_requirements, $notes
            ]);
            
            if ($result > 0) {
                $success_message = "Booking created successfully! Booking Number: $booking_number";
                // Clear form data
                $tour_id = $guest_name = $guest_email = $guest_phone = '';
                $number_of_people = 1;
                $tour_date = $total_amount = $paid_amount = '';
                $payment_status = $booking_status = 'pending';
                $payment_method = $special_requirements = $notes = '';
            } else {
                $error_message = 'Failed to create booking. Please try again.';
            }
            
        } catch (Exception $e) {
            $error_message = 'Error creating booking: ' . $e->getMessage();
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

$page_title = 'Add New Booking';
include 'includes/header.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Add New Booking</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="bookings.php">Bookings</a></li>
                        <li class="breadcrumb-item active">Add New</li>
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

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Booking Details</h3>
                </div>
                
                <form method="POST" action="">
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
                                                    <?php echo ($tour_id ?? '') == $tour['id'] ? 'selected' : ''; ?>>
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
                                           value="<?php echo htmlspecialchars($number_of_people ?? 1); ?>" min="1" max="50" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <!-- Guest Name -->
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="guest_name">Guest Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="guest_name" name="guest_name" 
                                           value="<?php echo htmlspecialchars($guest_name ?? ''); ?>" required>
                                </div>
                            </div>
                            
                            <!-- Guest Email -->
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="guest_email">Guest Email <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="guest_email" name="guest_email" 
                                           value="<?php echo htmlspecialchars($guest_email ?? ''); ?>" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <!-- Guest Phone -->
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="guest_phone">Guest Phone</label>
                                    <input type="tel" class="form-control" id="guest_phone" name="guest_phone" 
                                           value="<?php echo htmlspecialchars($guest_phone ?? ''); ?>">
                                </div>
                            </div>
                            
                            <!-- Tour Date -->
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="tour_date">Tour Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="tour_date" name="tour_date" 
                                           value="<?php echo htmlspecialchars($tour_date ?? ''); ?>" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <!-- Total Amount -->
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="total_amount">Total Amount (₹) <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="total_amount" name="total_amount" 
                                           value="<?php echo htmlspecialchars($total_amount ?? ''); ?>" min="0" step="0.01" required>
                                </div>
                            </div>
                            
                            <!-- Paid Amount -->
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="paid_amount">Paid Amount (₹)</label>
                                    <input type="number" class="form-control" id="paid_amount" name="paid_amount" 
                                           value="<?php echo htmlspecialchars($paid_amount ?? '0'); ?>" min="0" step="0.01">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <!-- Payment Status -->
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="payment_status">Payment Status</label>
                                    <select class="form-control" id="payment_status" name="payment_status">
                                        <option value="pending" <?php echo ($payment_status ?? 'pending') == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="partial" <?php echo ($payment_status ?? '') == 'partial' ? 'selected' : ''; ?>>Partial</option>
                                        <option value="paid" <?php echo ($payment_status ?? '') == 'paid' ? 'selected' : ''; ?>>Paid</option>
                                        <option value="refunded" <?php echo ($payment_status ?? '') == 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Payment Method -->
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="payment_method">Payment Method</label>
                                    <select class="form-control" id="payment_method" name="payment_method">
                                        <option value="">Select method...</option>
                                        <option value="cash" <?php echo ($payment_method ?? '') == 'cash' ? 'selected' : ''; ?>>Cash</option>
                                        <option value="bank_transfer" <?php echo ($payment_method ?? '') == 'bank_transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                                        <option value="credit_card" <?php echo ($payment_method ?? '') == 'credit_card' ? 'selected' : ''; ?>>Credit Card</option>
                                        <option value="debit_card" <?php echo ($payment_method ?? '') == 'debit_card' ? 'selected' : ''; ?>>Debit Card</option>
                                        <option value="upi" <?php echo ($payment_method ?? '') == 'upi' ? 'selected' : ''; ?>>UPI</option>
                                        <option value="cheque" <?php echo ($payment_method ?? '') == 'cheque' ? 'selected' : ''; ?>>Cheque</option>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Booking Status -->
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="booking_status">Booking Status</label>
                                    <select class="form-control" id="booking_status" name="booking_status">
                                        <option value="pending" <?php echo ($booking_status ?? 'pending') == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="confirmed" <?php echo ($booking_status ?? '') == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                        <option value="cancelled" <?php echo ($booking_status ?? '') == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                        <option value="completed" <?php echo ($booking_status ?? '') == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <!-- Special Requirements -->
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="special_requirements">Special Requirements</label>
                                    <textarea class="form-control" id="special_requirements" name="special_requirements" rows="4" 
                                              placeholder="Any special requirements or requests..."><?php echo htmlspecialchars($special_requirements ?? ''); ?></textarea>
                                </div>
                            </div>
                            
                            <!-- Admin Notes -->
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="notes">Admin Notes</label>
                                    <textarea class="form-control" id="notes" name="notes" rows="4" 
                                              placeholder="Internal notes for admin reference..."><?php echo htmlspecialchars($notes ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save mr-1"></i> Create Booking
                        </button>
                        <a href="bookings.php" class="btn btn-secondary ml-2">
                            <i class="fas fa-times mr-1"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>

        </div>
    </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const tourSelect = document.getElementById('tour_id');
    const peopleInput = document.getElementById('number_of_people');
    const totalAmountInput = document.getElementById('total_amount');
    
    function updateTotalAmount() {
        const selectedOption = tourSelect.options[tourSelect.selectedIndex];
        const basePrice = parseFloat(selectedOption.dataset.price) || 0;

        if (basePrice > 0) {
            totalAmountInput.value = basePrice.toFixed(2);
        }
    }

    tourSelect.addEventListener('change', updateTotalAmount);
});
</script>

<?php include 'includes/footer.php'; ?>
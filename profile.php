<?php
require_once 'config/config.php';
// require_once 'includes/whatsapp_otp_helpers.php'; // Mobile OTP commented — email OTP login only
require_once 'includes/email_otp_helpers.php';

requireUserLogin();

$user = getCurrentUser();
$user_data = $db->fetch('SELECT * FROM users WHERE id = ?', [(int) $user['id']]);
// $whatsappEnabled = mobileOtpIsConfigured();
// $whatsappSandboxNotice = getWhatsAppSandboxInstructions();
// $otpChannelLabel = fast2smsIsConfigured() ? 'SMS' : 'WhatsApp';
$page_title = 'My Profile - ' . getSetting('site_name');
$current_page = 'profile';

$errors = [];
$success_message = '';

// Handle profile update
if ($_POST && isset($_POST['action'])) {
    if ($_POST['action'] == 'update_profile') {
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $address = trim($_POST['address'] ?? '');
        
        // Validation
        if (empty($first_name)) {
            $errors[] = 'First name is required';
        }
        if (empty($last_name)) {
            $errors[] = 'Last name is required';
        }
        
        if (empty($errors)) {
            try {
                $db->execute("UPDATE users SET first_name = ?, last_name = ?, address = ?, updated_at = NOW() WHERE id = ?",
                    [$first_name, $last_name, $address, $user['id']]);
                
                // Update session
                $_SESSION['user_name'] = $first_name . ' ' . $last_name;
                $_SESSION['user_first_name'] = $first_name;
                
                $success_message = 'Profile updated successfully!';
                
                // Refresh user data
                $user = $db->fetch("SELECT * FROM users WHERE id = ?", [$user['id']]);
            } catch (Exception $e) {
                $errors[] = 'Failed to update profile. Please try again.';
            }
        }
    } elseif ($_POST['action'] == 'change_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Validation
        if (empty($current_password)) {
            $errors[] = 'Current password is required';
        }
        if (empty($new_password)) {
            $errors[] = 'New password is required';
        }
        if (strlen($new_password) < 6) {
            $errors[] = 'New password must be at least 6 characters long';
        }
        if ($new_password !== $confirm_password) {
            $errors[] = 'New password confirmation does not match';
        }
        
        if (empty($errors)) {
            try {
                $user_data = $db->fetch("SELECT password FROM users WHERE id = ?", [$user['id']]);
                
                if (password_verify($current_password, $user_data['password'])) {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $db->execute("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?", 
                        [$hashed_password, $user['id']]);
                    
                    $success_message = 'Password changed successfully!';
                } else {
                    $errors[] = 'Current password is incorrect';
                }
            } catch (Exception $e) {
                $errors[] = 'Failed to change password. Please try again.';
            }
        }
    }
}

// Get user's bookings
$bookings = [];
try {
    $bookings = $db->fetchAll("
        SELECT b.*, t.title as tour_title, t.featured_image, d.name as destination_name, d.country 
        FROM bookings b 
        LEFT JOIN tours t ON b.tour_id = t.id 
        LEFT JOIN destinations d ON t.destination_id = d.id 
        WHERE b.guest_email = ? 
        ORDER BY b.created_at DESC 
        LIMIT 10", [$user['email']]);
} catch (Exception $e) {
    // Handle error silently
}

include 'includes/header.php';
?>

<div class="page-wrapper">
    <div class="container py-5">
        <div class="row">
            <div class="col-lg-3 mb-4">
                <div class="card shadow-sm">
                    <div class="card-body text-center">
                        <div class="avatar mb-3">
                            <i class="fas fa-user-circle fa-5x text-primary"></i>
                        </div>
                        <h5 class="card-title"><?php echo htmlspecialchars($user['name']); ?></h5>
                        <p class="text-muted"><?php echo htmlspecialchars($user['email']); ?></p>
                        <small class="text-muted">Member since <?php echo date('M Y', strtotime($user_data['created_at'] ?? 'now')); ?></small>
                    </div>
                </div>
                
                <div class="list-group mt-3">
                    <a href="#profile" class="list-group-item list-group-item-action active">
                        <i class="fas fa-user me-2"></i> Profile
                    </a>
                    <a href="#bookings" class="list-group-item list-group-item-action">
                        <i class="fas fa-calendar-check me-2"></i> My Bookings
                    </a>
                    <?php /* Mobile / WhatsApp OTP update — commented out
                    <a href="#whatsapp" class="list-group-item list-group-item-action">
                        <i class="fab fa-whatsapp me-2"></i> WhatsApp
                    </a>
                    */ ?>
                    <a href="#security" class="list-group-item list-group-item-action">
                        <i class="fas fa-lock me-2"></i> Security
                    </a>
                    <a href="logout.php" class="list-group-item list-group-item-action text-danger">
                        <i class="fas fa-sign-out-alt me-2"></i> Logout
                    </a>
                </div>
            </div>
            
            <div class="col-lg-9">
                <!-- Success/Error Messages -->
                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <!-- Profile Section -->
                <div class="card shadow-sm mb-4" id="profile">
                    <div class="card-header">
                        <h5><i class="fas fa-user me-2"></i> Profile Information</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_profile">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">First Name</label>
                                    <input type="text" name="first_name" class="form-control" 
                                           value="<?php echo htmlspecialchars($user_data['first_name'] ?? $user['first_name']); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Last Name</label>
                                    <input type="text" name="last_name" class="form-control" 
                                           value="<?php echo htmlspecialchars($user_data['last_name'] ?? ''); ?>" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                                <small class="text-muted">Email cannot be changed</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">WhatsApp Number</label>
                                <input type="text" class="form-control"
                                       value="<?php echo htmlspecialchars(formatPhoneDisplay($user_data['phone'] ?? '') ?: 'Not linked yet'); ?>" readonly>
                                <small class="text-muted">Update your WhatsApp number below with OTP verification.</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Address</label>
                                <textarea name="address" class="form-control" rows="3"><?php echo htmlspecialchars($user_data['address'] ?? ''); ?></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i> Update Profile
                            </button>
                        </form>
                    </div>
                </div>

                <?php /*
                <!-- WhatsApp / Mobile OTP Number Update (commented — email OTP only) -->
                <div class="card shadow-sm mb-4" id="whatsapp">...</div>
                */ ?>

                <!-- My Bookings Section -->
                <div class="card shadow-sm mb-4" id="bookings">
                    <div class="card-header">
                        <h5><i class="fas fa-calendar-check me-2"></i> My Bookings</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($bookings)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                <p class="text-muted">You haven't made any bookings yet.</p>
                                <a href="<?php echo navUrl('tours'); ?>" class="btn btn-primary">
                                    <i class="fas fa-map-marked-alt me-2"></i> Explore Tours
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($bookings as $booking): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="card">
                                            <div class="card-body">
                                                <h6 class="card-title"><?php echo htmlspecialchars($booking['tour_title'] ?: 'Tour'); ?></h6>
                                                <p class="card-text small text-muted">
                                                    <i class="fas fa-map-marker-alt me-1"></i>
                                                    <?php echo htmlspecialchars($booking['destination_name'] . ', ' . $booking['country']); ?>
                                                </p>
                                                <p class="card-text small">
                                                    <strong>Date:</strong> <?php echo date('M d, Y', strtotime($booking['tour_date'])); ?><br>
                                                    <strong>People:</strong> <?php echo $booking['number_of_people']; ?><br>
                                                    <strong>Amount:</strong> ₹<?php echo number_format($booking['total_amount'], 2); ?><br>
                                                    <span class="badge badge-<?php echo $booking['booking_status'] == 'confirmed' ? 'success' : 'warning'; ?>">
                                                        <?php echo ucfirst($booking['booking_status']); ?>
                                                    </span>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="text-center mt-3">
                                <a href="<?php echo userBookingsUrl(); ?>" class="btn btn-outline-primary">
                                    View All Bookings
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Security Section -->
                <div class="card shadow-sm" id="security">
                    <div class="card-header">
                        <h5><i class="fas fa-lock me-2"></i> Change Password</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="change_password">
                            <div class="mb-3">
                                <label class="form-label">Current Password</label>
                                <input type="password" name="current_password" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">New Password</label>
                                <input type="password" name="new_password" class="form-control" required minlength="6">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Confirm New Password</label>
                                <input type="password" name="confirm_password" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-key me-2"></i> Change Password
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Smooth scrolling for navigation
document.querySelectorAll('.list-group-item[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
            
            // Update active state
            document.querySelectorAll('.list-group-item').forEach(item => {
                item.classList.remove('active');
            });
            this.classList.add('active');
        }
    });
});

/* Mobile OTP profile JS commented out — email OTP login only */

// Auto-hide success messages
setTimeout(function() {
    const alerts = document.querySelectorAll('.alert-success');
    alerts.forEach(function(alert) {
        if (typeof bootstrap !== 'undefined') {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }
    });
}, 5000);
</script>

<?php include 'includes/footer.php'; ?>
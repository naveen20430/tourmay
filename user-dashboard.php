<?php
require_once 'config/config.php';

// Set page variables
$page_title = 'User Dashboard - ' . getSetting('site_name');
$current_page = 'dashboard';

// Include header
include 'includes/header.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$user_id = $_SESSION['user_id'];
$errors = [];
$success_message = '';

// Get user data
try {
    $user = $db->fetch("SELECT * FROM users WHERE id = ?", [$user_id]);
    if (!$user) {
        header('Location: logout.php');
        exit;
    }
} catch (Exception $e) {
    die('Error loading user data');
}

// Get user's bookings
try {
    $bookings = $db->fetchAll("
        SELECT b.*, t.title as tour_title, t.slug as tour_slug, t.featured_image, 
               d.name as destination_name, d.country
        FROM bookings b 
        JOIN tours t ON b.tour_id = t.id 
        LEFT JOIN destinations d ON t.destination_id = d.id 
        WHERE b.guest_email = ? 
        ORDER BY b.created_at DESC
    ", [$user['email']]);
} catch (Exception $e) {
    $bookings = [];
}

// Handle profile update
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'update_profile') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $date_of_birth = $_POST['date_of_birth'] ?? '';
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $country = trim($_POST['country'] ?? '');
    
    // Validation
    if (empty($first_name)) $errors[] = 'First name is required';
    if (empty($last_name)) $errors[] = 'Last name is required';
    
    // Update profile if no errors
    if (empty($errors)) {
        try {
            $updated = $db->execute("
                UPDATE users SET 
                first_name = ?, last_name = ?, phone = ?, date_of_birth = ?, 
                address = ?, city = ?, country = ?, updated_at = NOW() 
                WHERE id = ?
            ", [$first_name, $last_name, $phone, $date_of_birth ?: null, $address, $city, $country, $user_id]);
            
            if ($updated) {
                $success_message = 'Profile updated successfully!';
                // Update session data
                $_SESSION['user_name'] = $first_name . ' ' . $last_name;
                $_SESSION['user_first_name'] = $first_name;
                // Refresh user data
                $user = $db->fetch("SELECT * FROM users WHERE id = ?", [$user_id]);
            }
        } catch (Exception $e) {
            $errors[] = 'Failed to update profile. Please try again.';
        }
    }
}

// Handle password change
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'change_password') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($current_password)) $errors[] = 'Current password is required';
    if (empty($new_password)) $errors[] = 'New password is required';
    if (strlen($new_password) < 6) $errors[] = 'New password must be at least 6 characters';
    if ($new_password !== $confirm_password) $errors[] = 'New passwords do not match';
    
    // Verify current password
    if (empty($errors) && !password_verify($current_password, $user['password'])) {
        $errors[] = 'Current password is incorrect';
    }
    
    // Update password if no errors
    if (empty($errors)) {
        try {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $updated = $db->execute("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?", [$hashed_password, $user_id]);
            
            if ($updated) {
                $success_message = 'Password changed successfully!';
            }
        } catch (Exception $e) {
            $errors[] = 'Failed to change password. Please try again.';
        }
    }
}
?>

<style>
    .dashboard-sidebar {
        background: #f8f9fa;
        border-radius: 10px;
        padding: 20px;
        height: fit-content;
    }
    .dashboard-content {
        background: white;
        border-radius: 10px;
        padding: 30px;
        box-shadow: 0 2px 15px rgba(0,0,0,0.1);
    }
    .booking-card {
        border: 1px solid #e9ecef;
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 15px;
        transition: transform 0.2s;
    }
    .booking-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    }
    .status-badge {
        font-size: 0.8em;
        padding: 4px 12px;
        border-radius: 20px;
    }
    .nav-pills .nav-link.active {
        background: #1bbc9b;
    }
</style>

<div class="container my-5">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-lg-3 mb-4">
            <div class="dashboard-sidebar">
                <div class="text-center mb-4">
                    <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center" 
                         style="width: 80px; height: 80px; font-size: 2rem;">
                        <?php echo strtoupper(substr($user['first_name'], 0, 1)); ?>
                    </div>
                    <h5 class="mt-3 mb-1"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h5>
                    <p class="text-muted small"><?php echo htmlspecialchars($user['email']); ?></p>
                </div>
                
                <ul class="nav nav-pills flex-column" id="dashboard-tabs">
                    <li class="nav-item mb-1">
                        <a class="nav-link active" id="overview-tab" data-bs-toggle="pill" href="#overview">
                            <i class="fas fa-tachometer-alt me-2"></i>Overview
                        </a>
                    </li>
                    <li class="nav-item mb-1">
                        <a class="nav-link" id="bookings-tab" data-bs-toggle="pill" href="#bookings">
                            <i class="fas fa-calendar-check me-2"></i>My Bookings
                        </a>
                    </li>
                    <li class="nav-item mb-1">
                        <a class="nav-link" id="profile-tab" data-bs-toggle="pill" href="#profile">
                            <i class="fas fa-user-edit me-2"></i>Edit Profile
                        </a>
                    </li>
                    <li class="nav-item mb-1">
                        <a class="nav-link" id="password-tab" data-bs-toggle="pill" href="#password">
                            <i class="fas fa-lock me-2"></i>Change Password
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="logout.php" class="nav-link text-danger">
                            <i class="fas fa-sign-out-alt me-2"></i>Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="col-lg-9">
            <div class="dashboard-content">
                <!-- Messages -->
                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <h6><i class="fas fa-exclamation-triangle me-2"></i>Please fix the following errors:</h6>
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <div class="tab-content">
                    <!-- Overview Tab -->
                    <div class="tab-pane fade show active" id="overview">
                        <h4 class="mb-4">
                            <i class="fas fa-tachometer-alt me-2 text-primary"></i>Dashboard Overview
                        </h4>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <div class="card text-center border-primary">
                                    <div class="card-body">
                                        <i class="fas fa-calendar-check fa-2x text-primary mb-2"></i>
                                        <h3 class="text-primary"><?php echo count($bookings); ?></h3>
                                        <p class="mb-0">Total Bookings</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="card text-center border-success">
                                    <div class="card-body">
                                        <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                                        <h3 class="text-success">
                                            <?php echo count(array_filter($bookings, fn($b) => $b['booking_status'] === 'confirmed')); ?>
                                        </h3>
                                        <p class="mb-0">Confirmed Tours</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="card text-center border-info">
                                    <div class="card-body">
                                        <i class="fas fa-map-marked-alt fa-2x text-info mb-2"></i>
                                        <h3 class="text-info">
                                            <?php echo count(array_unique(array_column($bookings, 'destination_name'))); ?>
                                        </h3>
                                        <p class="mb-0">Destinations Visited</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mt-4">
                            <div class="col-12">
                                <h5>Account Information</h5>
                                <div class="card">
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <p><strong>Member Since:</strong> <?php echo date('M d, Y', strtotime($user['created_at'])); ?></p>
                                                <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
                                                <p><strong>Phone:</strong> <?php echo htmlspecialchars($user['phone'] ?: 'Not provided'); ?></p>
                                            </div>
                                            <div class="col-md-6">
                                                <p><strong>Country:</strong> <?php echo htmlspecialchars($user['country'] ?: 'Not specified'); ?></p>
                                                <p><strong>Status:</strong> 
                                                    <span class="badge bg-success">Active</span>
                                                    <?php if ($user['email_verified']): ?>
                                                        <span class="badge bg-primary">Verified</span>
                                                    <?php endif; ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Bookings Tab -->
                    <div class="tab-pane fade" id="bookings">
                        <h4 class="mb-4">
                            <i class="fas fa-calendar-check me-2 text-primary"></i>My Bookings
                        </h4>
                        
                        <?php if (empty($bookings)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-calendar-times fa-4x text-muted mb-3"></i>
                                <h5 class="text-muted">No Bookings Yet</h5>
                                <p class="text-muted">Start exploring our amazing tours and make your first booking!</p>
                                <a href="tours.php" class="btn btn-primary">
                                    <i class="fas fa-search me-2"></i>Browse Tours
                                </a>
                            </div>
                        <?php else: ?>
                            <?php foreach ($bookings as $booking): ?>
                                <div class="booking-card">
                                    <div class="row align-items-center">
                                        <div class="col-md-3">
                                            <?php if ($booking['featured_image']): ?>
                                                <img src="<?php echo $booking['featured_image']; ?>" 
                                                     class="img-fluid rounded" alt="<?php echo htmlspecialchars($booking['tour_title']); ?>">
                                            <?php else: ?>
                                                <div class="bg-light rounded p-4 text-center">
                                                    <i class="fas fa-image fa-2x text-muted"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-6">
                                            <h6 class="mb-1">
                                                <a href="tour/<?php echo $booking['tour_slug']; ?>" class="text-decoration-none">
                                                    <?php echo htmlspecialchars($booking['tour_title']); ?>
                                                </a>
                                            </h6>
                                            <p class="text-muted mb-1">
                                                <i class="fas fa-map-marker-alt me-1"></i>
                                                <?php echo htmlspecialchars($booking['destination_name'] . ', ' . $booking['country']); ?>
                                            </p>
                                            <p class="mb-1">
                                                <small>
                                                    <strong>Date:</strong> <?php echo date('M d, Y', strtotime($booking['tour_date'])); ?> |
                                                    <strong>People:</strong> <?php echo $booking['number_of_people']; ?> |
                                                    <strong>Total:</strong> ₹<?php echo number_format($booking['total_amount'], 0); ?>
                                                </small>
                                            </p>
                                        </div>
                                        <div class="col-md-3 text-end">
                                            <span class="status-badge 
                                                <?php 
                                                    echo $booking['booking_status'] === 'confirmed' ? 'bg-success text-white' :
                                                         ($booking['booking_status'] === 'pending' ? 'bg-warning text-dark' :
                                                          ($booking['booking_status'] === 'cancelled' ? 'bg-danger text-white' : 'bg-secondary text-white'));
                                                ?>">
                                                <?php echo ucfirst($booking['booking_status']); ?>
                                            </span>
                                            <p class="small text-muted mt-2 mb-0">
                                                Booking #<?php echo $booking['booking_number']; ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Profile Tab -->
                    <div class="tab-pane fade" id="profile">
                        <h4 class="mb-4">
                            <i class="fas fa-user-edit me-2 text-primary"></i>Edit Profile
                        </h4>
                        
                        <form method="POST">
                            <input type="hidden" name="action" value="update_profile">
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">First Name *</label>
                                    <input type="text" name="first_name" class="form-control" required
                                           value="<?php echo htmlspecialchars($user['first_name']); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Last Name *</label>
                                    <input type="text" name="last_name" class="form-control" required
                                           value="<?php echo htmlspecialchars($user['last_name']); ?>">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Phone Number</label>
                                    <input type="tel" name="phone" class="form-control"
                                           value="<?php echo htmlspecialchars($user['phone']); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Date of Birth</label>
                                    <input type="date" name="date_of_birth" class="form-control"
                                           value="<?php echo $user['date_of_birth']; ?>">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Address</label>
                                <textarea name="address" class="form-control" rows="3"><?php echo htmlspecialchars($user['address']); ?></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">City</label>
                                    <input type="text" name="city" class="form-control"
                                           value="<?php echo htmlspecialchars($user['city']); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Country</label>
                                    <input type="text" name="country" class="form-control"
                                           value="<?php echo htmlspecialchars($user['country']); ?>">
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Update Profile
                            </button>
                        </form>
                    </div>
                    
                    <!-- Password Tab -->
                    <div class="tab-pane fade" id="password">
                        <h4 class="mb-4">
                            <i class="fas fa-lock me-2 text-primary"></i>Change Password
                        </h4>
                        
                        <form method="POST">
                            <input type="hidden" name="action" value="change_password">
                            
                            <div class="mb-3">
                                <label class="form-label">Current Password *</label>
                                <input type="password" name="current_password" class="form-control" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">New Password *</label>
                                <input type="password" name="new_password" class="form-control" required minlength="6">
                                <small class="text-muted">Minimum 6 characters</small>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Confirm New Password *</label>
                                <input type="password" name="confirm_password" class="form-control" required minlength="6">
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-key me-2"></i>Change Password
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

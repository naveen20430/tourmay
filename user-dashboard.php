<?php
require_once 'config/config.php';
require_once 'includes/checkout_helpers.php';

requireUserLogin();

$user_id = (int) $_SESSION['user_id'];
$page_title = 'User Dashboard - ' . getSetting('site_name');
$current_page = 'dashboard';
$extra_css = cssWithCache('assets/css/dashboard-pages.css');
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

ensureCheckoutSchema();

/**
 * Whether the logged-in user may cancel this booking.
 */
function userCanCancelBooking(array $booking): bool {
    $status = strtolower(trim((string) ($booking['booking_status'] ?? '')));
    if (!in_array($status, ['pending', 'confirmed'], true)) {
        return false;
    }

    $tourDate = trim((string) ($booking['tour_date'] ?? ''));
    if ($tourDate === '') {
        return false;
    }

    // Allow cancel on or before the travel date
    return $tourDate >= date('Y-m-d');
}

// Handle cancel booking (before fetching list / rendering)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel_booking') {
    $bookingId = (int) ($_POST['booking_id'] ?? 0);
    $redirectBase = rtrim(userDashboardUrl(), '/') . '#bookings';

    if ($bookingId <= 0) {
        $_SESSION['dashboard_flash'] = ['type' => 'error', 'message' => 'Invalid booking selected.'];
        header('Location: ' . $redirectBase);
        exit;
    }

    try {
        $booking = $db->fetch(
            "SELECT * FROM bookings
             WHERE id = ?
               AND (user_id = ? OR LOWER(guest_email) = LOWER(?))
             LIMIT 1",
            [$bookingId, $user_id, $user['email']]
        );

        if (!$booking) {
            $_SESSION['dashboard_flash'] = ['type' => 'error', 'message' => 'Booking not found.'];
            header('Location: ' . $redirectBase);
            exit;
        }

        if (!userCanCancelBooking($booking)) {
            $_SESSION['dashboard_flash'] = [
                'type' => 'error',
                'message' => 'This booking can no longer be cancelled. Please contact support if you need help.',
            ];
            header('Location: ' . $redirectBase);
            exit;
        }

        $db->execute(
            "UPDATE bookings
             SET booking_status = 'cancelled',
                 updated_at = NOW()
             WHERE id = ?
               AND (user_id = ? OR LOWER(guest_email) = LOWER(?))",
            [$bookingId, $user_id, $user['email']]
        );

        // If every booking on the invoice is cancelled, mark the invoice cancelled too
        $invoiceId = (int) ($booking['invoice_id'] ?? 0);
        if ($invoiceId > 0) {
            $openCount = $db->fetch(
                "SELECT COUNT(*) AS c FROM bookings
                 WHERE invoice_id = ?
                   AND LOWER(COALESCE(booking_status, '')) NOT IN ('cancelled', 'canceled')",
                [$invoiceId]
            );
            if ((int) ($openCount['c'] ?? 0) === 0) {
                $db->execute(
                    "UPDATE invoices
                     SET payment_status = 'cancelled', updated_at = NOW()
                     WHERE id = ? AND payment_status IN ('pending', 'failed')",
                    [$invoiceId]
                );
            }
        }

        $wasPaid = strtolower((string) ($booking['payment_status'] ?? '')) === 'paid';
        $message = 'Booking #' . ($booking['booking_number'] ?? $bookingId) . ' has been cancelled.';
        if ($wasPaid) {
            $message .= ' For paid bookings, please contact support regarding any refund.';
        }

        $_SESSION['dashboard_flash'] = ['type' => 'success', 'message' => $message];
    } catch (Exception $e) {
        $_SESSION['dashboard_flash'] = ['type' => 'error', 'message' => 'Could not cancel booking. Please try again.'];
    }

    header('Location: ' . $redirectBase);
    exit;
}

if (!empty($_SESSION['dashboard_flash']) && is_array($_SESSION['dashboard_flash'])) {
    $flash = $_SESSION['dashboard_flash'];
    unset($_SESSION['dashboard_flash']);
    if (($flash['type'] ?? '') === 'success') {
        $success_message = (string) ($flash['message'] ?? '');
    } else {
        $errors[] = (string) ($flash['message'] ?? 'Something went wrong.');
    }
}

// Get user's bookings
try {
    $bookings = $db->fetchAll("
        SELECT b.*, t.title as tour_title, t.slug as tour_slug, t.featured_image,
               d.name as destination_name, d.country,
               i.invoice_number, i.payment_status as invoice_payment_status, i.payment_method as invoice_payment_method
        FROM bookings b
        JOIN tours t ON b.tour_id = t.id
        LEFT JOIN destinations d ON t.destination_id = d.id
        LEFT JOIN invoices i ON b.invoice_id = i.id
        WHERE b.user_id = ? OR b.guest_email = ?
        ORDER BY b.created_at DESC
    ", [$user_id, $user['email']]);
} catch (Exception $e) {
    $bookings = [];
}

include 'includes/header.php';

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
$confirmedBookings = count(array_filter($bookings, static function ($booking) {
    return ($booking['booking_status'] ?? '') === 'confirmed';
}));
$destinationCount = count(array_unique(array_filter(array_column($bookings, 'destination_name'))));
?>

<section class="page-header">
    <div class="container">
        <h1>My Dashboard</h1>
        <ul class="travhub-breadcrumb list-unstyled">
            <li><a href="<?php echo navUrl('home'); ?>">Home</a></li>
            <li>Dashboard</li>
        </ul>
    </div>
</section>

<section class="dashboard-section">
    <div class="container">
        <div class="dashboard-layout">
            <aside class="dashboard-sidebar">
                <div class="dashboard-user">
                    <div class="dashboard-avatar">
                        <?php echo strtoupper(substr($user['first_name'], 0, 1)); ?>
                    </div>
                    <h5><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h5>
                    <p><?php echo htmlspecialchars($user['email']); ?></p>
                </div>

                <ul class="nav nav-pills flex-column dashboard-nav" id="dashboard-tabs">
                    <li class="nav-item">
                        <a class="nav-link active" id="overview-tab" data-bs-toggle="pill" href="#overview">
                            <i class="fas fa-tachometer-alt"></i> Overview
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="bookings-tab" data-bs-toggle="pill" href="#bookings">
                            <i class="fas fa-calendar-check"></i> My Bookings
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="profile-tab" data-bs-toggle="pill" href="#profile">
                            <i class="fas fa-user-edit"></i> Edit Profile
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="password-tab" data-bs-toggle="pill" href="#password">
                            <i class="fas fa-lock"></i> Change Password
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo navUrl('logout'); ?>" class="nav-link logout-link">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </li>
                </ul>
            </aside>

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
                    <div class="tab-pane fade show active" id="overview">
                        <div class="dashboard-panel-head">
                            <div>
                                <h4 class="dashboard-panel-title">
                                    <i class="fas fa-tachometer-alt"></i>Dashboard Overview
                                </h4>
                                <p class="dashboard-panel-subtitle">Welcome back, <?php echo htmlspecialchars($user['first_name']); ?>. Here is a quick summary of your account.</p>
                            </div>
                        </div>

                        <div class="dashboard-stats">
                            <div class="dashboard-stat-card">
                                <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
                                <h3><?php echo count($bookings); ?></h3>
                                <p>Total Bookings</p>
                            </div>
                            <div class="dashboard-stat-card">
                                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                                <h3><?php echo $confirmedBookings; ?></h3>
                                <p>Confirmed Tours</p>
                            </div>
                            <div class="dashboard-stat-card">
                                <div class="stat-icon"><i class="fas fa-map-marked-alt"></i></div>
                                <h3><?php echo $destinationCount; ?></h3>
                                <p>Destinations</p>
                            </div>
                        </div>

                        <div class="dashboard-info-card">
                            <h5>Account Information</h5>
                            <div class="dashboard-info-grid">
                                <p><strong>Member Since:</strong> <?php echo date('M d, Y', strtotime($user['created_at'])); ?></p>
                                <p><strong>Country:</strong> <?php echo htmlspecialchars($user['country'] ?: 'Not specified'); ?></p>
                                <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
                                <p><strong>Status:</strong>
                                    <span class="badge bg-success">Active</span>
                                    <?php if (!empty($user['email_verified'])): ?>
                                        <span class="badge bg-primary">Verified</span>
                                    <?php endif; ?>
                                </p>
                                <p><strong>Phone:</strong> <?php echo htmlspecialchars($user['phone'] ?: 'Not provided'); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="tab-pane fade" id="bookings">
                        <div class="dashboard-panel-head">
                            <div>
                                <h4 class="dashboard-panel-title">
                                    <i class="fas fa-calendar-check"></i>My Bookings
                                </h4>
                                <p class="dashboard-panel-subtitle">Track your tour reservations, payment status, and invoices.</p>
                            </div>
                            <?php if (!empty($bookings)): ?>
                                <span class="dashboard-panel-count"><?php echo count($bookings); ?> booking<?php echo count($bookings) === 1 ? '' : 's'; ?></span>
                            <?php endif; ?>
                        </div>

                        <?php if (empty($bookings)): ?>
                            <div class="dashboard-empty">
                                <i class="fas fa-calendar-times d-block"></i>
                                <h5>No Bookings Yet</h5>
                                <p class="text-muted">Start exploring our amazing tours and make your first booking.</p>
                                <a href="<?php echo navUrl('tours'); ?>" class="btn btn-primary">
                                    <i class="fas fa-search me-2"></i>Browse Tours
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="dashboard-bookings-list">
                            <?php foreach ($bookings as $booking): ?>
                                <?php
                                $statusClass = 'is-default';
                                if (($booking['booking_status'] ?? '') === 'confirmed') {
                                    $statusClass = 'is-confirmed';
                                } elseif (($booking['booking_status'] ?? '') === 'pending') {
                                    $statusClass = 'is-pending';
                                } elseif (($booking['booking_status'] ?? '') === 'cancelled') {
                                    $statusClass = 'is-cancelled';
                                }
                                $bookingTotal = (float) ($booking['total_with_cab'] ?? $booking['total_amount'] ?? 0);
                                if ($bookingTotal <= 0) {
                                    $bookingTotal = (float) $booking['total_amount'] + (float) ($booking['cab_price'] ?? 0);
                                }
                                $thumbUrl = '';
                                if (!empty($booking['featured_image'])) {
                                    $thumbUrl = preg_match('#^https?://#i', $booking['featured_image'])
                                        ? $booking['featured_image']
                                        : BASE_URL . ltrim($booking['featured_image'], '/');
                                }
                                $locationLabel = trim(($booking['destination_name'] ?? '') . ', ' . ($booking['country'] ?? ''), ', ');
                                ?>
                                <article class="dashboard-booking-card">
                                    <div class="dashboard-booking-card__media">
                                        <?php if ($thumbUrl): ?>
                                            <img src="<?php echo htmlspecialchars($thumbUrl); ?>"
                                                 alt="<?php echo htmlspecialchars($booking['tour_title']); ?>">
                                        <?php else: ?>
                                            <div class="dashboard-booking-card__placeholder">
                                                <i class="fas fa-mountain"></i>
                                            </div>
                                        <?php endif; ?>
                                        <span class="dashboard-status-badge <?php echo $statusClass; ?>">
                                            <?php echo ucfirst($booking['booking_status'] ?? 'pending'); ?>
                                        </span>
                                    </div>

                                    <div class="dashboard-booking-card__body">
                                        <div class="dashboard-booking-card__top">
                                            <div>
                                                <h3 class="dashboard-booking-card__title">
                                                    <a href="<?php echo tourUrl($booking['tour_slug']); ?>">
                                                        <?php echo htmlspecialchars($booking['tour_title']); ?>
                                                    </a>
                                                </h3>
                                                <?php if ($locationLabel): ?>
                                                    <p class="dashboard-booking-card__location">
                                                        <i class="fas fa-map-marker-alt"></i>
                                                        <?php echo htmlspecialchars($locationLabel); ?>
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                            <span class="dashboard-booking-card__ref">
                                                #<?php echo htmlspecialchars($booking['booking_number']); ?>
                                            </span>
                                        </div>

                                        <div class="dashboard-booking-card__facts">
                                            <div class="dashboard-booking-fact">
                                                <span>Travel Date</span>
                                                <strong><?php echo date('M d, Y', strtotime($booking['tour_date'])); ?></strong>
                                            </div>
                                            <div class="dashboard-booking-fact">
                                                <span>Travelers</span>
                                                <strong><?php echo (int) $booking['number_of_people']; ?> People</strong>
                                            </div>
                                            <div class="dashboard-booking-fact">
                                                <span>Total Amount</span>
                                                <strong>₹<?php echo number_format($bookingTotal, 0); ?></strong>
                                            </div>
                                        </div>

                                        <div class="dashboard-booking-card__footer">
                                            <div class="dashboard-booking-card__links">
                                            <?php if (!empty($booking['invoice_number'])): ?>
                                                <a href="<?php echo invoiceUrl($booking['invoice_number']); ?>" class="dashboard-invoice-link">
                                                    <i class="fas fa-file-invoice"></i>
                                                    View Invoice
                                                    <?php if (($booking['invoice_payment_status'] ?? '') === 'pending'): ?>
                                                        <span class="dashboard-invoice-pill">Payment Pending</span>
                                                    <?php endif; ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="dashboard-booking-card__note">Invoice will be available after confirmation.</span>
                                            <?php endif; ?>
                                            </div>

                                            <div class="dashboard-booking-card__actions">
                                                <?php if (userCanCancelBooking($booking)): ?>
                                                    <form method="POST" class="dashboard-cancel-form" onsubmit="return confirm('Cancel this booking? This cannot be undone.');">
                                                        <input type="hidden" name="action" value="cancel_booking">
                                                        <input type="hidden" name="booking_id" value="<?php echo (int) $booking['id']; ?>">
                                                        <button type="submit" class="dashboard-cancel-btn">
                                                            <i class="fas fa-times-circle"></i>
                                                            Cancel Booking
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                <a href="<?php echo tourUrl($booking['tour_slug']); ?>" class="dashboard-booking-card__cta">
                                                    View Tour
                                                    <i class="fas fa-arrow-right"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="tab-pane fade" id="profile">
                        <div class="dashboard-panel-head">
                            <div>
                                <h4 class="dashboard-panel-title">
                                    <i class="fas fa-user-edit"></i>Edit Profile
                                </h4>
                                <p class="dashboard-panel-subtitle">Update your personal details and contact information.</p>
                            </div>
                        </div>
                        
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
                    
                    <div class="tab-pane fade" id="password">
                        <div class="dashboard-panel-head">
                            <div>
                                <h4 class="dashboard-panel-title">
                                    <i class="fas fa-lock"></i>Change Password
                                </h4>
                                <p class="dashboard-panel-subtitle">Choose a strong password to keep your account secure.</p>
                            </div>
                        </div>
                        
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
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var hash = window.location.hash;
    if (!hash) {
        return;
    }

    var tabTrigger = document.querySelector('.dashboard-nav a[href="' + hash + '"]');
    if (tabTrigger && window.bootstrap && window.bootstrap.Tab) {
        window.bootstrap.Tab.getOrCreateInstance(tabTrigger).show();
    }
});
</script>

<?php include 'includes/footer.php'; ?>

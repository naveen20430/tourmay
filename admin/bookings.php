<?php
require_once '../config/config.php';
require_once '../includes/booking_driver_mail.php';
requireLogin();

ensureBookingDriverSchema();

// Handle booking status updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'update_status') {
        $booking_id = (int)($_POST['booking_id'] ?? 0);
        $new_status = $_POST['new_status'] ?? '';
        $driver_name = trim($_POST['driver_name'] ?? '');
        $vehicle_number = trim($_POST['vehicle_number'] ?? '');
        $driver_contact = trim($_POST['driver_contact'] ?? '');
        $send_driver_email = !empty($_POST['send_driver_email']);
        
        if ($booking_id && $new_status) {
            try {
                $existing = $db->fetch("SELECT booking_status FROM bookings WHERE id = ?", [$booking_id]);
                $previous_status = $existing['booking_status'] ?? '';

                if ($new_status === 'confirmed') {
                    if ($driver_name === '' || $vehicle_number === '' || $driver_contact === '') {
                        throw new Exception('Driver name, vehicle number, and contact are required when confirming.');
                    }
                }

                $db->execute(
                    "UPDATE bookings SET booking_status = ?, driver_name = ?, vehicle_number = ?, driver_contact = ?, updated_at = NOW() WHERE id = ?",
                    [
                        $new_status,
                        $driver_name !== '' ? $driver_name : null,
                        $vehicle_number !== '' ? $vehicle_number : null,
                        $driver_contact !== '' ? $driver_contact : null,
                        $booking_id
                    ]
                );
                $success_message = "Booking status updated successfully!";

                $shouldEmail = $new_status === 'confirmed' && (
                    $send_driver_email || $previous_status !== 'confirmed'
                );
                if ($shouldEmail) {
                    $bookingRow = $db->fetch("
                        SELECT b.*, t.title as tour_title
                        FROM bookings b
                        LEFT JOIN tours t ON b.tour_id = t.id
                        WHERE b.id = ?
                    ", [$booking_id]);
                    if ($bookingRow) {
                        $mailResult = sendBookingDriverDetailsEmail($bookingRow);
                        if (!empty($mailResult['ok'])) {
                            markBookingDriverDetailsSent($booking_id);
                            $success_message .= " Driver details emailed to " . htmlspecialchars($mailResult['to']) . ".";
                        } else {
                            $error_message = "Status saved, but driver email failed: " . htmlspecialchars($mailResult['error'] ?? 'Unknown error');
                        }
                    }
                }
            } catch (Exception $e) {
                $error_message = "Error updating booking: " . $e->getMessage();
            }
        }
    }
    
    if ($_POST['action'] == 'bulk_delete') {
        if (isset($_POST['selected_bookings']) && is_array($_POST['selected_bookings'])) {
            $ids = array_map('intval', $_POST['selected_bookings']);
            $placeholders = str_repeat('?,', count($ids) - 1) . '?';
            try {
                $db->execute("DELETE FROM bookings WHERE id IN ($placeholders)", $ids);
                $success_message = count($ids) . " booking(s) deleted successfully!";
            } catch (Exception $e) {
                $error_message = "Error deleting bookings: " . $e->getMessage();
            }
        }
    }
}

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Get filter parameters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$needs_driver_filter = !empty($_GET['needs_driver']);

// Build query conditions
$where_conditions = [];
$params = [];

if ($search) {
    $where_conditions[] = "(b.guest_name LIKE ? OR b.guest_email LIKE ? OR b.booking_number LIKE ? OR t.title LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
}

if ($status_filter) {
    $where_conditions[] = "b.booking_status = ?";
    $params[] = $status_filter;
}

if ($needs_driver_filter) {
    $where_conditions[] = "b.booking_status != 'cancelled'
        AND b.driver_details_sent_at IS NULL
        AND (b.payment_status = 'paid' OR COALESCE(b.paid_amount, 0) > 0)";
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM bookings b LEFT JOIN tours t ON b.tour_id = t.id $where_clause";
$total_result = $db->fetch($count_query, $params);
$total_bookings = $total_result['total'];
$total_pages = ceil($total_bookings / $per_page);

// Get bookings with tour information
$bookings_query = "
    SELECT b.*, t.title as tour_title, t.duration_days, d.name as destination_name, d.country
    FROM bookings b 
    LEFT JOIN tours t ON b.tour_id = t.id 
    LEFT JOIN destinations d ON t.destination_id = d.id
    $where_clause
    ORDER BY
        CASE
            WHEN b.booking_status != 'cancelled'
             AND b.driver_details_sent_at IS NULL
             AND (b.payment_status = 'paid' OR COALESCE(b.paid_amount, 0) > 0)
            THEN 0 ELSE 1
        END,
        b.created_at DESC
    LIMIT $per_page OFFSET $offset
";

try {
    $bookings = $db->fetchAll($bookings_query, $params);
} catch (Exception $e) {
    $bookings = [];
    $error_message = "Error fetching bookings: " . $e->getMessage();
}

// Get booking statistics
try {
    $stats = [
        'total' => $db->fetch("SELECT COUNT(*) as count FROM bookings")['count'] ?? 0,
        'pending' => $db->fetch("SELECT COUNT(*) as count FROM bookings WHERE booking_status = 'pending'")['count'] ?? 0,
        'confirmed' => $db->fetch("SELECT COUNT(*) as count FROM bookings WHERE booking_status = 'confirmed'")['count'] ?? 0,
        'cancelled' => $db->fetch("SELECT COUNT(*) as count FROM bookings WHERE booking_status = 'cancelled'")['count'] ?? 0,
        'completed' => $db->fetch("SELECT COUNT(*) as count FROM bookings WHERE booking_status = 'completed'")['count'] ?? 0,
        'needs_driver' => countBookingsNeedingDriverDetails(),
    ];
} catch (Exception $e) {
    $stats = ['total' => 0, 'pending' => 0, 'confirmed' => 0, 'cancelled' => 0, 'completed' => 0, 'needs_driver' => 0];
}

$page_title = 'Bookings Management';
include 'includes/header.php';

$statusBadgeClass = static function ($status) {
    switch ($status) {
        case 'confirmed':
            return 'bg-success';
        case 'cancelled':
            return 'bg-danger';
        case 'completed':
            return 'bg-primary';
        default:
            return 'bg-warning text-dark';
    }
};
?>

<style>
.bookings-stat-card {
    border: 0;
    border-radius: 12px;
    color: #fff;
    overflow: hidden;
}
.bookings-stat-card .card-body { padding: 1.1rem 1.25rem; }
.bookings-stat-card .stat-label { opacity: .9; margin: 0; font-size: .95rem; }
.bookings-stat-card .stat-value { font-size: 1.85rem; font-weight: 700; margin: 0 0 .35rem; line-height: 1.1; }
.bookings-stat-card a { color: rgba(255,255,255,.92); text-decoration: none; font-size: .85rem; }
.bookings-stat-card a:hover { color: #fff; text-decoration: underline; }
.bookings-stat-total { background: linear-gradient(135deg, #0ea5e9, #0284c7); }
.bookings-stat-pending { background: linear-gradient(135deg, #f59e0b, #d97706); }
.bookings-stat-confirmed { background: linear-gradient(135deg, #10b981, #059669); }
.bookings-stat-cancelled { background: linear-gradient(135deg, #ef4444, #dc2626); }
.bookings-stat-driver { background: linear-gradient(135deg, #f97316, #ea580c); }
.bookings-table thead th {
    background: #1e293b;
    color: #fff !important;
    border-color: #1e293b;
    font-weight: 600;
    vertical-align: middle;
    white-space: nowrap;
}
.bookings-table tbody td {
    vertical-align: middle;
    color: #0f172a;
    background: #fff;
}
.bookings-table tbody tr.needs-driver-row > td {
    background: #fff7ed;
}
.bookings-table tbody tr.needs-driver-row:hover > td {
    background: #ffedd5;
}
.bookings-table .badge {
    font-weight: 600;
    padding: .4em .7em;
}
.driver-action-btn { white-space: nowrap; }
</style>

<div class="p-3 p-md-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0">Bookings Management</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Bookings</li>
            </ol>
        </nav>
    </div>

    <?php if (isset($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo $success_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($stats['needs_driver'])): ?>
        <div class="alert alert-warning alert-persist border-0 shadow-sm d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div>
                <strong><i class="fas fa-car me-2"></i><?php echo (int)$stats['needs_driver']; ?> paid booking(s) need driver details</strong>
                <div class="small mb-0 mt-1">Payment received — send driver name, vehicle number, and contact to the guest email.</div>
            </div>
            <a href="?needs_driver=1" class="btn btn-warning btn-sm fw-semibold">
                <i class="fas fa-bell me-1"></i> View &amp; send now
            </a>
        </div>
    <?php endif; ?>

    <div class="row mb-3 g-3">
        <div class="col-lg-3 col-md-6">
            <div class="card bookings-stat-card bookings-stat-total">
                <div class="card-body">
                    <div class="stat-value"><?php echo (int)$stats['total']; ?></div>
                    <p class="stat-label">Total Bookings</p>
                    <a href="bookings.php">View all <i class="fas fa-arrow-right ms-1"></i></a>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card bookings-stat-card bookings-stat-pending">
                <div class="card-body">
                    <div class="stat-value"><?php echo (int)$stats['pending']; ?></div>
                    <p class="stat-label">Pending</p>
                    <a href="?status=pending">View pending <i class="fas fa-arrow-right ms-1"></i></a>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card bookings-stat-card bookings-stat-confirmed">
                <div class="card-body">
                    <div class="stat-value"><?php echo (int)$stats['confirmed']; ?></div>
                    <p class="stat-label">Confirmed</p>
                    <a href="?status=confirmed">View confirmed <i class="fas fa-arrow-right ms-1"></i></a>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card bookings-stat-card <?php echo !empty($stats['needs_driver']) ? 'bookings-stat-driver' : 'bookings-stat-cancelled'; ?>">
                <div class="card-body">
                    <?php if (!empty($stats['needs_driver'])): ?>
                        <div class="stat-value"><?php echo (int)$stats['needs_driver']; ?></div>
                        <p class="stat-label">Send Driver Details</p>
                        <a href="?needs_driver=1">Action needed <i class="fas fa-arrow-right ms-1"></i></a>
                    <?php else: ?>
                        <div class="stat-value"><?php echo (int)$stats['cancelled']; ?></div>
                        <p class="stat-label">Cancelled</p>
                        <a href="?status=cancelled">View cancelled <i class="fas fa-arrow-right ms-1"></i></a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">
            <h3 class="card-title mb-0 h5">Filter Bookings</h3>
        </div>
        <div class="card-body">
            <form method="GET" action="">
                <div class="row g-2">
                    <div class="col-md-3">
                        <select name="status" class="form-select">
                            <option value="">All Status</option>
                            <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="confirmed" <?php echo $status_filter == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                            <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <input type="text" name="search" class="form-control" placeholder="Search bookings..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-4 d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search me-1"></i> Filter
                        </button>
                        <?php if ($needs_driver_filter): ?>
                            <input type="hidden" name="needs_driver" value="1">
                        <?php endif; ?>
                        <a href="?needs_driver=1" class="btn btn-outline-warning">
                            <i class="fas fa-car me-1"></i> Need driver
                        </a>
                        <a href="bookings.php" class="btn btn-secondary">
                            <i class="fas fa-times me-1"></i> Clear
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="mb-3">
        <a href="booking-add.php" class="btn btn-success">
            <i class="fas fa-plus me-1"></i> Add New Booking
        </a>
        <?php if ($needs_driver_filter): ?>
            <span class="badge bg-warning text-dark ms-2">Showing paid bookings needing driver details</span>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title mb-0 h5">Bookings (<?php echo (int)$total_bookings; ?> total)</h3>
        </div>
        
        <form method="POST" action="">
<?php echo function_exists('csrfField') ? csrfField() : ''; ?>
            <input type="hidden" name="action" value="bulk_delete">
            
            <div class="card-body table-responsive p-0">
                <table class="table table-hover bookings-table mb-0">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="select-all"></th>
                            <th>Booking #</th>
                            <th>Guest</th>
                            <th>Tour</th>
                            <th>Date</th>
                            <th>People</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($bookings)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-4">
                                    <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                    <p class="text-muted mb-0">No bookings found.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($bookings as $booking): ?>
                                <?php
                                $needsDriver = bookingNeedsDriverDetailsNotice($booking);
                                $statusClass = $statusBadgeClass($booking['booking_status'] ?? 'pending');
                                ?>
                                <tr class="<?php echo $needsDriver ? 'needs-driver-row' : ''; ?>">
                                    <td>
                                        <input type="checkbox" name="selected_bookings[]" value="<?php echo (int)$booking['id']; ?>">
                                    </td>
                                    <td>
                                        <strong>#<?php echo htmlspecialchars($booking['booking_number']); ?></strong>
                                        <?php if ($needsDriver): ?>
                                            <div class="mt-1"><span class="badge bg-warning text-dark">Send driver details</span></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($booking['guest_name']); ?></strong>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($booking['guest_email']); ?></small>
                                        <?php if ($booking['guest_phone']): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($booking['guest_phone']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($booking['tour_title'] ?: 'Unknown Tour'); ?></strong>
                                        <?php if ($booking['destination_name']): ?>
                                            <br><small class="text-muted">
                                                <i class="fas fa-map-marker-alt"></i>
                                                <?php echo htmlspecialchars($booking['destination_name'] . ', ' . $booking['country']); ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo date('M d, Y', strtotime($booking['tour_date'])); ?></strong>
                                        <?php if ($booking['duration_days']): ?>
                                            <br><small class="text-muted"><?php echo (int)$booking['duration_days']; ?> days</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-info text-dark"><?php echo (int)$booking['number_of_people']; ?> people</span>
                                    </td>
                                    <td>
                                        <strong>₹<?php echo number_format((float)$booking['total_amount'], 2); ?></strong>
                                        <?php if ((float)$booking['paid_amount'] > 0): ?>
                                            <br><small class="text-success fw-semibold">Paid: ₹<?php echo number_format((float)$booking['paid_amount'], 2); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $statusClass; ?>">
                                            <?php echo htmlspecialchars(ucfirst((string)$booking['booking_status'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button type="button" class="btn btn-info text-white" data-bs-toggle="modal" data-bs-target="#viewModal<?php echo (int)$booking['id']; ?>" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <a href="booking-edit.php?id=<?php echo (int)$booking['id']; ?>" class="btn btn-primary" title="Edit Booking">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#statusModal<?php echo (int)$booking['id']; ?>" title="Update Status / Send Driver">
                                                <i class="fas fa-tasks"></i>
                                            </button>
                                            <?php if ($needsDriver): ?>
                                                <button type="button" class="btn btn-orange btn-danger driver-action-btn" data-bs-toggle="modal" data-bs-target="#statusModal<?php echo (int)$booking['id']; ?>" title="Send driver details">
                                                    <i class="fas fa-car"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>

                                <!-- View Modal -->
                                <div class="modal fade" id="viewModal<?php echo (int)$booking['id']; ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Booking Details - #<?php echo htmlspecialchars($booking['booking_number']); ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <?php if ($needsDriver): ?>
                                                    <div class="alert alert-warning">
                                                        <strong><i class="fas fa-bell me-1"></i> Action needed:</strong>
                                                        Payment received. Send driver details to the guest.
                                                    </div>
                                                <?php endif; ?>
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <h6>Guest Information</h6>
                                                        <p><strong>Name:</strong> <?php echo htmlspecialchars($booking['guest_name']); ?></p>
                                                        <p><strong>Email:</strong> <?php echo htmlspecialchars($booking['guest_email']); ?></p>
                                                        <p><strong>Phone:</strong> <?php echo htmlspecialchars($booking['guest_phone']); ?></p>
                                                        
                                                        <h6 class="mt-3">Booking Information</h6>
                                                        <p><strong>Booking Number:</strong> <?php echo htmlspecialchars($booking['booking_number']); ?></p>
                                                        <p><strong>Tour Date:</strong> <?php echo date('M d, Y', strtotime($booking['tour_date'])); ?></p>
                                                        <p><strong>Number of People:</strong> <?php echo (int)$booking['number_of_people']; ?></p>
                                                        <p><strong>Status:</strong> <?php echo htmlspecialchars(ucfirst((string)$booking['booking_status'])); ?></p>
                                                        <?php if (!empty($booking['driver_name']) || !empty($booking['vehicle_number']) || !empty($booking['driver_contact'])): ?>
                                                            <h6 class="mt-3">Driver details</h6>
                                                            <?php if (!empty($booking['driver_name'])): ?>
                                                                <p><strong>Driver:</strong> <?php echo htmlspecialchars($booking['driver_name']); ?></p>
                                                            <?php endif; ?>
                                                            <?php if (!empty($booking['vehicle_number'])): ?>
                                                                <p><strong>Vehicle:</strong> <?php echo htmlspecialchars($booking['vehicle_number']); ?></p>
                                                            <?php endif; ?>
                                                            <?php if (!empty($booking['driver_contact'])): ?>
                                                                <p><strong>Contact:</strong> <?php echo htmlspecialchars($booking['driver_contact']); ?></p>
                                                            <?php endif; ?>
                                                            <?php if (!empty($booking['driver_details_sent_at'])): ?>
                                                                <p><strong>Emailed:</strong> <?php echo date('M d, Y H:i', strtotime($booking['driver_details_sent_at'])); ?></p>
                                                            <?php endif; ?>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <h6>Tour Information</h6>
                                                        <p><strong>Tour:</strong> <?php echo htmlspecialchars($booking['tour_title']); ?></p>
                                                        <p><strong>Destination:</strong> <?php echo htmlspecialchars(($booking['destination_name'] ?? '') . ', ' . ($booking['country'] ?? '')); ?></p>
                                                        
                                                        <h6 class="mt-3">Payment Information</h6>
                                                        <p><strong>Total Amount:</strong> ₹<?php echo number_format((float)$booking['total_amount'], 2); ?></p>
                                                        <p><strong>Paid Amount:</strong> ₹<?php echo number_format((float)$booking['paid_amount'], 2); ?></p>
                                                        <p><strong>Payment Status:</strong> <?php echo htmlspecialchars(ucfirst((string)$booking['payment_status'])); ?></p>
                                                        <?php if ($booking['payment_method']): ?>
                                                            <p><strong>Payment Method:</strong> <?php echo htmlspecialchars($booking['payment_method']); ?></p>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                
                                                <?php if ($booking['special_requirements']): ?>
                                                    <hr>
                                                    <h6>Special Requirements</h6>
                                                    <p><?php echo nl2br(htmlspecialchars($booking['special_requirements'])); ?></p>
                                                <?php endif; ?>
                                                
                                                <?php if ($booking['notes']): ?>
                                                    <hr>
                                                    <h6>Admin Notes</h6>
                                                    <p><?php echo nl2br(htmlspecialchars($booking['notes'])); ?></p>
                                                <?php endif; ?>
                                            </div>
                                            <div class="modal-footer">
                                                <?php if ($needsDriver): ?>
                                                    <button type="button" class="btn btn-warning" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#statusModal<?php echo (int)$booking['id']; ?>">
                                                        <i class="fas fa-car me-1"></i> Send Driver Details
                                                    </button>
                                                <?php endif; ?>
                                                <a href="mailto:<?php echo htmlspecialchars($booking['guest_email']); ?>" class="btn btn-primary">
                                                    <i class="fas fa-envelope me-1"></i> Send Email
                                                </a>
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Status / Driver Modal -->
                                <div class="modal fade" id="statusModal<?php echo (int)$booking['id']; ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="POST" action="">
<?php echo function_exists('csrfField') ? csrfField() : ''; ?>
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="booking_id" value="<?php echo (int)$booking['id']; ?>">
                                                
                                                <div class="modal-header">
                                                    <h5 class="modal-title"><?php echo $needsDriver ? 'Send Driver Details' : 'Update Booking Status'; ?></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <?php if ($needsDriver): ?>
                                                        <div class="alert alert-warning py-2">
                                                            Guest has paid. Confirm booking and email driver details.
                                                        </div>
                                                    <?php endif; ?>
                                                    <div class="mb-3">
                                                        <label class="form-label">Booking Status</label>
                                                        <select name="new_status" class="form-select js-booking-status" required data-target="driverFields<?php echo (int)$booking['id']; ?>">
                                                            <option value="pending" <?php echo $booking['booking_status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                            <option value="confirmed" <?php echo ($booking['booking_status'] == 'confirmed' || $needsDriver) ? 'selected' : ''; ?>>Confirmed</option>
                                                            <option value="cancelled" <?php echo $booking['booking_status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                            <option value="completed" <?php echo $booking['booking_status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                        </select>
                                                    </div>
                                                    <div id="driverFields<?php echo (int)$booking['id']; ?>" class="driver-fields-block border rounded p-3 bg-light" style="<?php echo (($booking['booking_status'] ?? '') === 'confirmed' || $needsDriver) ? '' : 'display:none;'; ?>">
                                                        <p class="mb-2"><strong>Driver details</strong> <small class="text-muted">(emailed to guest)</small></p>
                                                        <div class="mb-3">
                                                            <label class="form-label">Driver name <span class="text-danger">*</span></label>
                                                            <input type="text" name="driver_name" class="form-control js-driver-field"
                                                                   value="<?php echo htmlspecialchars($booking['driver_name'] ?? ''); ?>"
                                                                   placeholder="Driver full name" <?php echo $needsDriver ? 'required' : ''; ?>>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Vehicle number <span class="text-danger">*</span></label>
                                                            <input type="text" name="vehicle_number" class="form-control js-driver-field"
                                                                   value="<?php echo htmlspecialchars($booking['vehicle_number'] ?? ''); ?>"
                                                                   placeholder="Vehicle registration number" <?php echo $needsDriver ? 'required' : ''; ?>>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Driver contact number <span class="text-danger">*</span></label>
                                                            <input type="tel" name="driver_contact" class="form-control js-driver-field"
                                                                   value="<?php echo htmlspecialchars($booking['driver_contact'] ?? ''); ?>"
                                                                   placeholder="Mobile number" <?php echo $needsDriver ? 'required' : ''; ?>>
                                                        </div>
                                                        <div class="form-check">
                                                            <input type="checkbox" class="form-check-input" name="send_driver_email" value="1" id="sendDriverEmail<?php echo (int)$booking['id']; ?>" checked>
                                                            <label class="form-check-label" for="sendDriverEmail<?php echo (int)$booking['id']; ?>">Email driver details to guest</label>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="submit" class="btn btn-primary">
                                                        <?php echo $needsDriver ? 'Save &amp; Email Driver Details' : 'Update Status'; ?>
                                                    </button>
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if (!empty($bookings)): ?>
                <div class="card-footer">
                    <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete selected bookings?')">
                        <i class="fas fa-trash me-1"></i> Delete Selected
                    </button>
                </div>
            <?php endif; ?>
        </form>
    </div>

    <?php
    $pageQuery = '';
    if (!empty($status_filter)) {
        $pageQuery .= '&status=' . urlencode($status_filter);
    }
    if (!empty($search)) {
        $pageQuery .= '&search=' . urlencode($search);
    }
    if ($needs_driver_filter) {
        $pageQuery .= '&needs_driver=1';
    }
    ?>
    <?php if ($total_pages > 1): ?>
        <div class="d-flex justify-content-center mt-3">
            <nav aria-label="Bookings pagination">
                <ul class="pagination">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo $pageQuery; ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?><?php echo $pageQuery; ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo $pageQuery; ?>">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<script>
document.getElementById('select-all')?.addEventListener('change', function() {
    document.querySelectorAll('input[name="selected_bookings[]"]').forEach(checkbox => {
        checkbox.checked = this.checked;
    });
});

document.querySelectorAll('input[name="selected_bookings[]"]').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('input[name="selected_bookings[]"]');
        const selectAll = document.getElementById('select-all');
        if (selectAll) {
            selectAll.checked = Array.from(checkboxes).every(cb => cb.checked);
        }
    });
});

function syncStatusDriverFields(selectEl) {
    const targetId = selectEl.getAttribute('data-target');
    const block = targetId ? document.getElementById(targetId) : null;
    if (!block) return;
    const confirmed = selectEl.value === 'confirmed';
    block.style.display = confirmed ? '' : 'none';
    block.querySelectorAll('.js-driver-field').forEach(function(field) {
        field.required = confirmed;
    });
    const emailCb = block.querySelector('input[name="send_driver_email"]');
    if (confirmed && emailCb && selectEl.dataset.prev !== 'confirmed') {
        emailCb.checked = true;
    }
    selectEl.dataset.prev = selectEl.value;
}

document.querySelectorAll('.js-booking-status').forEach(function(selectEl) {
    selectEl.dataset.prev = selectEl.value;
    selectEl.addEventListener('change', function() {
        syncStatusDriverFields(selectEl);
    });
    syncStatusDriverFields(selectEl);
});
</script>

<?php include 'includes/footer.php'; ?>

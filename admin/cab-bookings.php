<?php
session_start();
require_once '../config/config.php';

// Check authentication
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'includes/header.php';

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_status' && isset($_POST['booking_id'])) {
        $booking_id = intval($_POST['booking_id']);
        $status = $_POST['status'] ?? 'pending';
        
        try {
            $db->execute(
                "UPDATE cab_bookings SET status = ?, updated_at = NOW() WHERE id = ?",
                [$status, $booking_id]
            );
            $success = "Booking status updated successfully!";
        } catch (Exception $e) {
            $error = "Error updating status: " . $e->getMessage();
        }
    } elseif ($_POST['action'] === 'update_payment' && isset($_POST['booking_id'])) {
        $booking_id = intval($_POST['booking_id']);
        $payment_status = $_POST['payment_status'] ?? 'pending';
        
        try {
            $db->execute(
                "UPDATE cab_bookings SET payment_status = ?, updated_at = NOW() WHERE id = ?",
                [$payment_status, $booking_id]
            );
            $success = "Payment status updated successfully!";
        } catch (Exception $e) {
            $error = "Error updating payment status: " . $e->getMessage();
        }
    } elseif ($_POST['action'] === 'delete' && isset($_POST['booking_id'])) {
        $booking_id = intval($_POST['booking_id']);
        
        try {
            $db->execute("DELETE FROM cab_bookings WHERE id = ?", [$booking_id]);
            $success = "Booking deleted successfully!";
        } catch (Exception $e) {
            $error = "Error deleting booking: " . $e->getMessage();
        }
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$payment_filter = $_GET['payment'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$where = [];
$params = [];

if (!empty($status_filter)) {
    $where[] = "cb.status = ?";
    $params[] = $status_filter;
}

if (!empty($payment_filter)) {
    $where[] = "cb.payment_status = ?";
    $params[] = $payment_filter;
}

if (!empty($search)) {
    $where[] = "(cb.booking_number LIKE ? OR cb.customer_name LIKE ? OR cb.customer_email LIKE ? OR cb.customer_phone LIKE ?)";
    $search_term = "%{$search}%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Fetch bookings
$bookings = $db->fetchAll("
    SELECT 
        cb.*,
        cr.route_name,
        cr.from_location,
        cr.to_location,
        ct.display_name as cab_type_name
    FROM cab_bookings cb
    LEFT JOIN cab_routes cr ON cb.route_id = cr.id
    LEFT JOIN cab_route_pricing crp ON cb.pricing_id = crp.id
    LEFT JOIN cab_types ct ON crp.cab_type_id = ct.id
    {$where_clause}
    ORDER BY cb.created_at DESC
", $params);

// Get statistics
$stats = [
    'total' => $db->fetch("SELECT COUNT(*) as count FROM cab_bookings")['count'],
    'pending' => $db->fetch("SELECT COUNT(*) as count FROM cab_bookings WHERE status = 'pending'")['count'],
    'confirmed' => $db->fetch("SELECT COUNT(*) as count FROM cab_bookings WHERE status = 'confirmed'")['count'],
    'completed' => $db->fetch("SELECT COUNT(*) as count FROM cab_bookings WHERE status = 'completed'")['count'],
    'revenue' => $db->fetch("SELECT SUM(total_price) as total FROM cab_bookings WHERE payment_status = 'paid'")['total'] ?? 0
];
?>

<div class="container-fluid p-4">
    <?php if (isset($success)): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?php echo htmlspecialchars($success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card stats-card">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-list"></i> Total Bookings</h5>
                    <h2><?php echo $stats['total']; ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stats-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-clock"></i> Pending</h5>
                    <h2><?php echo $stats['pending']; ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stats-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-check-circle"></i> Confirmed</h5>
                    <h2><?php echo $stats['confirmed']; ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stats-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-rupee-sign"></i> Total Revenue</h5>
                    <h2><?php echo formatPriceINR($stats['revenue']); ?></h2>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Booking #, Name, Email..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Booking Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                        <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Payment Status</label>
                    <select name="payment" class="form-select">
                        <option value="">All Payment Status</option>
                        <option value="pending" <?php echo $payment_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="paid" <?php echo $payment_filter === 'paid' ? 'selected' : ''; ?>>Paid</option>
                        <option value="refunded" <?php echo $payment_filter === 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                    <a href="cab-bookings.php" class="btn btn-secondary">
                        <i class="fas fa-redo"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Bookings Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Booking #</th>
                            <th>Customer</th>
                            <th>Route</th>
                            <th>Cab Type</th>
                            <th>Travel Date</th>
                            <th>Trip Type</th>
                            <th>Passengers</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($bookings)): ?>
                            <tr>
                                <td colspan="11" class="text-center text-muted py-4">
                                    <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                                    No bookings found
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($bookings as $booking): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($booking['booking_number']); ?></strong>
                                        <br>
                                        <small class="text-muted"><?php echo date('M d, Y', strtotime($booking['created_at'])); ?></small>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($booking['customer_name']); ?></strong><br>
                                        <small><?php echo htmlspecialchars($booking['customer_email']); ?></small><br>
                                        <small><?php echo htmlspecialchars($booking['customer_phone']); ?></small>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($booking['route_name']); ?></strong><br>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($booking['from_location']); ?> → 
                                            <?php echo htmlspecialchars($booking['to_location']); ?>
                                        </small>
                                    </td>
                                    <td><?php echo htmlspecialchars($booking['cab_type_name']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($booking['travel_date'])); ?></td>
                                    <td>
                                        <?php if ($booking['trip_type'] === 'one_way'): ?>
                                            <span class="badge bg-info">One Way</span>
                                        <?php else: ?>
                                            <span class="badge bg-primary">Round Trip</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $booking['num_passengers']; ?></td>
                                    <td><strong><?php echo formatPriceINR($booking['total_price']); ?></strong></td>
                                    <td>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Update status?');">
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                                                <option value="pending" <?php echo $booking['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                <option value="confirmed" <?php echo $booking['status'] === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                                <option value="completed" <?php echo $booking['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                <option value="cancelled" <?php echo $booking['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                            </select>
                                        </form>
                                    </td>
                                    <td>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Update payment status?');">
                                            <input type="hidden" name="action" value="update_payment">
                                            <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                            <select name="payment_status" class="form-select form-select-sm" onchange="this.form.submit()">
                                                <option value="pending" <?php echo $booking['payment_status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                <option value="paid" <?php echo $booking['payment_status'] === 'paid' ? 'selected' : ''; ?>>Paid</option>
                                                <option value="refunded" <?php echo $booking['payment_status'] === 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                                            </select>
                                        </form>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" 
                                                data-bs-target="#viewModal<?php echo $booking['id']; ?>">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this booking?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>

                                <!-- View Details Modal -->
                                <div class="modal fade" id="viewModal<?php echo $booking['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Booking Details - <?php echo htmlspecialchars($booking['booking_number']); ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <h6>Customer Information</h6>
                                                <p>
                                                    <strong>Name:</strong> <?php echo htmlspecialchars($booking['customer_name']); ?><br>
                                                    <strong>Email:</strong> <?php echo htmlspecialchars($booking['customer_email']); ?><br>
                                                    <strong>Phone:</strong> <?php echo htmlspecialchars($booking['customer_phone']); ?>
                                                </p>
                                                
                                                <h6>Trip Details</h6>
                                                <p>
                                                    <strong>Route:</strong> <?php echo htmlspecialchars($booking['route_name']); ?><br>
                                                    <strong>From:</strong> <?php echo htmlspecialchars($booking['from_location']); ?><br>
                                                    <strong>To:</strong> <?php echo htmlspecialchars($booking['to_location']); ?><br>
                                                    <strong>Cab Type:</strong> <?php echo htmlspecialchars($booking['cab_type_name']); ?><br>
                                                    <strong>Trip Type:</strong> <?php echo ucfirst(str_replace('_', ' ', $booking['trip_type'])); ?><br>
                                                    <strong>Travel Date:</strong> <?php echo date('F d, Y', strtotime($booking['travel_date'])); ?><br>
                                                    <strong>Passengers:</strong> <?php echo $booking['num_passengers']; ?>
                                                </p>
                                                
                                                <?php if (!empty($booking['special_requirements'])): ?>
                                                    <h6>Special Requirements</h6>
                                                    <p><?php echo nl2br(htmlspecialchars($booking['special_requirements'])); ?></p>
                                                <?php endif; ?>
                                                
                                                <h6>Payment Information</h6>
                                                <p>
                                                    <strong>Total Amount:</strong> <?php echo formatPriceINR($booking['total_price']); ?><br>
                                                    <strong>Payment Status:</strong> 
                                                    <span class="badge bg-<?php echo $booking['payment_status'] === 'paid' ? 'success' : ($booking['payment_status'] === 'refunded' ? 'warning' : 'secondary'); ?>">
                                                        <?php echo ucfirst($booking['payment_status']); ?>
                                                    </span>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

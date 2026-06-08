<?php
require_once '../config/config.php';
requireLogin();

// Handle booking status updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'update_status') {
        $booking_id = (int)($_POST['booking_id'] ?? 0);
        $new_status = $_POST['new_status'] ?? '';
        
        if ($booking_id && $new_status) {
            try {
                $db->execute("UPDATE bookings SET booking_status = ?, updated_at = NOW() WHERE id = ?", [$new_status, $booking_id]);
                $success_message = "Booking status updated successfully!";
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
    ORDER BY b.created_at DESC
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
        'completed' => $db->fetch("SELECT COUNT(*) as count FROM bookings WHERE booking_status = 'completed'")['count'] ?? 0
    ];
} catch (Exception $e) {
    $stats = ['total' => 0, 'pending' => 0, 'confirmed' => 0, 'cancelled' => 0, 'completed' => 0];
}

$page_title = 'Bookings Management';
include 'includes/header.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Bookings Management</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Bookings</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle mr-2"></i>
                    <?php echo $success_message; ?>
                    <button type="button" class="close" data-dismiss="alert">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    <?php echo $error_message; ?>
                    <button type="button" class="close" data-dismiss="alert">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-info">
                        <div class="inner">
                            <h3><?php echo $stats['total']; ?></h3>
                            <p>Total Bookings</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <a href="?" class="small-box-footer">
                            View All <i class="fas fa-arrow-circle-right"></i>
                        </a>
                    </div>
                </div>
                
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-warning">
                        <div class="inner">
                            <h3><?php echo $stats['pending']; ?></h3>
                            <p>Pending</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <a href="?status=pending" class="small-box-footer">
                            View Pending <i class="fas fa-arrow-circle-right"></i>
                        </a>
                    </div>
                </div>
                
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-success">
                        <div class="inner">
                            <h3><?php echo $stats['confirmed']; ?></h3>
                            <p>Confirmed</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <a href="?status=confirmed" class="small-box-footer">
                            View Confirmed <i class="fas fa-arrow-circle-right"></i>
                        </a>
                    </div>
                </div>
                
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-danger">
                        <div class="inner">
                            <h3><?php echo $stats['cancelled']; ?></h3>
                            <p>Cancelled</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <a href="?status=cancelled" class="small-box-footer">
                            View Cancelled <i class="fas fa-arrow-circle-right"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Filter Bookings</h3>
                </div>
                <div class="card-body">
                    <form method="GET" action="">
                        <div class="row">
                            <div class="col-md-3">
                                <select name="status" class="form-control">
                                    <option value="">All Status</option>
                                    <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="confirmed" <?php echo $status_filter == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                    <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <input type="text" name="search" class="form-control" placeholder="Search bookings..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search mr-1"></i> Filter
                                </button>
                                <a href="bookings.php" class="btn btn-secondary ml-1">
                                    <i class="fas fa-times mr-1"></i> Clear
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Add New Booking Button -->
            <div class="row mb-3">
                <div class="col-12">
                    <a href="booking-add.php" class="btn btn-success">
                        <i class="fas fa-plus mr-1"></i> Add New Booking
                    </a>
                </div>
            </div>

            <!-- Bookings Table -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Bookings (<?php echo $total_bookings; ?> total)</h3>
                </div>
                
                <form method="POST" action="">
                    <input type="hidden" name="action" value="bulk_delete">
                    
                    <div class="card-body table-responsive p-0">
                        <table class="table table-hover text-nowrap">
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
                                            <p class="text-muted">No bookings found.</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($bookings as $booking): ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" name="selected_bookings[]" value="<?php echo $booking['id']; ?>">
                                            </td>
                                            <td>
                                                <strong>#<?php echo htmlspecialchars($booking['booking_number']); ?></strong>
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
                                                    <br><small class="text-muted"><?php echo $booking['duration_days']; ?> days</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge badge-info"><?php echo $booking['number_of_people']; ?> people</span>
                                            </td>
                                            <td>
                                                <strong>₹<?php echo number_format($booking['total_amount'], 2); ?></strong>
                                                <?php if ($booking['paid_amount'] > 0): ?>
                                                    <br><small class="text-success">Paid: ₹<?php echo number_format($booking['paid_amount'], 2); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php 
                                                    echo $booking['booking_status'] == 'confirmed' ? 'success' : 
                                                         ($booking['booking_status'] == 'cancelled' ? 'danger' : 
                                                         ($booking['booking_status'] == 'completed' ? 'primary' : 'warning')); 
                                                ?>">
                                                    <?php echo ucfirst($booking['booking_status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button type="button" class="btn btn-info btn-sm" data-toggle="modal" data-target="#viewModal<?php echo $booking['id']; ?>" title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <a href="booking-edit.php?id=<?php echo $booking['id']; ?>" class="btn btn-primary btn-sm" title="Edit Booking">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-warning btn-sm" data-toggle="modal" data-target="#statusModal<?php echo $booking['id']; ?>" title="Update Status">
                                                        <i class="fas fa-tasks"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>

                                        <!-- View Modal -->
                                        <div class="modal fade" id="viewModal<?php echo $booking['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog modal-lg">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h4 class="modal-title">Booking Details - #<?php echo htmlspecialchars($booking['booking_number']); ?></h4>
                                                        <button type="button" class="close" data-dismiss="modal">
                                                            <span>&times;</span>
                                                        </button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <h6>Guest Information</h6>
                                                                <p><strong>Name:</strong> <?php echo htmlspecialchars($booking['guest_name']); ?></p>
                                                                <p><strong>Email:</strong> <?php echo htmlspecialchars($booking['guest_email']); ?></p>
                                                                <p><strong>Phone:</strong> <?php echo htmlspecialchars($booking['guest_phone']); ?></p>
                                                                
                                                                <h6 class="mt-3">Booking Information</h6>
                                                                <p><strong>Booking Number:</strong> <?php echo htmlspecialchars($booking['booking_number']); ?></p>
                                                                <p><strong>Tour Date:</strong> <?php echo date('M d, Y', strtotime($booking['tour_date'])); ?></p>
                                                                <p><strong>Number of People:</strong> <?php echo $booking['number_of_people']; ?></p>
                                                                <p><strong>Status:</strong> <?php echo ucfirst($booking['booking_status']); ?></p>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <h6>Tour Information</h6>
                                                                <p><strong>Tour:</strong> <?php echo htmlspecialchars($booking['tour_title']); ?></p>
                                                                <p><strong>Destination:</strong> <?php echo htmlspecialchars($booking['destination_name'] . ', ' . $booking['country']); ?></p>
                                                                
                                                                <h6 class="mt-3">Payment Information</h6>
                                                                <p><strong>Total Amount:</strong> ₹<?php echo number_format($booking['total_amount'], 2); ?></p>
                                                                <p><strong>Paid Amount:</strong> ₹<?php echo number_format($booking['paid_amount'], 2); ?></p>
                                                                <p><strong>Payment Status:</strong> <?php echo ucfirst($booking['payment_status']); ?></p>
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
                                                        <a href="mailto:<?php echo htmlspecialchars($booking['guest_email']); ?>" class="btn btn-primary">
                                                            <i class="fas fa-envelope mr-1"></i> Send Email
                                                        </a>
                                                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Status Update Modal -->
                                        <div class="modal fade" id="statusModal<?php echo $booking['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <form method="POST" action="">
                                                        <input type="hidden" name="action" value="update_status">
                                                        <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                        
                                                        <div class="modal-header">
                                                            <h4 class="modal-title">Update Booking Status</h4>
                                                            <button type="button" class="close" data-dismiss="modal">
                                                                <span>&times;</span>
                                                            </button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="form-group">
                                                                <label>Booking Status</label>
                                                                <select name="new_status" class="form-control" required>
                                                                    <option value="pending" <?php echo $booking['booking_status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                                    <option value="confirmed" <?php echo $booking['booking_status'] == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                                                    <option value="cancelled" <?php echo $booking['booking_status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                                    <option value="completed" <?php echo $booking['booking_status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="submit" class="btn btn-primary">Update Status</button>
                                                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
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
                                <i class="fas fa-trash mr-1"></i> Delete Selected
                            </button>
                        </div>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="d-flex justify-content-center mt-3">
                    <nav aria-label="Bookings pagination">
                        <ul class="pagination">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                </li>
                            <?php endif; ?>

                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>

        </div>
    </section>
</div>

<script>
// Select all functionality
document.getElementById('select-all').addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('input[name="selected_bookings[]"]');
    checkboxes.forEach(checkbox => {
        checkbox.checked = this.checked;
    });
});

// Auto-check select-all when all items are selected
document.querySelectorAll('input[name="selected_bookings[]"]').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('input[name="selected_bookings[]"]');
        const selectAll = document.getElementById('select-all');
        selectAll.checked = Array.from(checkboxes).every(cb => cb.checked);
    });
});
</script>

<?php include 'includes/footer.php'; ?>
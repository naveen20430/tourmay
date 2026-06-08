<?php
session_start();
require_once '../config/config.php';

// Check authentication
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'includes/header.php';

// Get date range from filters
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$end_date = $_GET['end_date'] ?? date('Y-m-d'); // Today

// Overview Statistics
$total_bookings = $db->fetch("
    SELECT COUNT(*) as count 
    FROM cab_bookings 
    WHERE DATE(created_at) BETWEEN ? AND ?
", [$start_date, $end_date])['count'];

$total_revenue = $db->fetch("
    SELECT SUM(total_price) as total 
    FROM cab_bookings 
    WHERE payment_status = 'paid' AND DATE(created_at) BETWEEN ? AND ?
", [$start_date, $end_date])['total'] ?? 0;

$pending_bookings = $db->fetch("
    SELECT COUNT(*) as count 
    FROM cab_bookings 
    WHERE status = 'pending' AND DATE(created_at) BETWEEN ? AND ?
", [$start_date, $end_date])['count'];

$confirmed_bookings = $db->fetch("
    SELECT COUNT(*) as count 
    FROM cab_bookings 
    WHERE status = 'confirmed' AND DATE(created_at) BETWEEN ? AND ?
", [$start_date, $end_date])['count'];

// Bookings by Route
$bookings_by_route = $db->fetchAll("
    SELECT 
        cr.route_name,
        cr.from_location,
        cr.to_location,
        COUNT(cb.id) as booking_count,
        SUM(cb.total_price) as total_revenue,
        SUM(CASE WHEN cb.payment_status = 'paid' THEN cb.total_price ELSE 0 END) as paid_revenue
    FROM cab_bookings cb
    INNER JOIN cab_routes cr ON cb.route_id = cr.id
    WHERE DATE(cb.created_at) BETWEEN ? AND ?
    GROUP BY cb.route_id, cr.route_name, cr.from_location, cr.to_location
    ORDER BY booking_count DESC
", [$start_date, $end_date]);

// Bookings by Cab Type
$bookings_by_cab = $db->fetchAll("
    SELECT 
        ct.display_name,
        COUNT(cb.id) as booking_count,
        SUM(cb.total_price) as total_revenue
    FROM cab_bookings cb
    INNER JOIN cab_route_pricing crp ON cb.pricing_id = crp.id
    INNER JOIN cab_types ct ON crp.cab_type_id = ct.id
    WHERE DATE(cb.created_at) BETWEEN ? AND ?
    GROUP BY ct.id, ct.display_name
    ORDER BY booking_count DESC
", [$start_date, $end_date]);

// Daily bookings trend
$daily_bookings = $db->fetchAll("
    SELECT 
        DATE(created_at) as booking_date,
        COUNT(*) as count,
        SUM(total_price) as revenue
    FROM cab_bookings
    WHERE DATE(created_at) BETWEEN ? AND ?
    GROUP BY DATE(created_at)
    ORDER BY booking_date DESC
    LIMIT 30
", [$start_date, $end_date]);

// Payment status breakdown
$payment_breakdown = $db->fetchAll("
    SELECT 
        payment_status,
        COUNT(*) as count,
        SUM(total_price) as total
    FROM cab_bookings
    WHERE DATE(created_at) BETWEEN ? AND ?
    GROUP BY payment_status
", [$start_date, $end_date]);

// Trip type breakdown
$trip_type_breakdown = $db->fetchAll("
    SELECT 
        trip_type,
        COUNT(*) as count,
        SUM(total_price) as total
    FROM cab_bookings
    WHERE DATE(created_at) BETWEEN ? AND ?
    GROUP BY trip_type
", [$start_date, $end_date]);
?>

<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-chart-line"></i> Cab Bookings Reports</h2>
        <button onclick="window.print()" class="btn btn-primary">
            <i class="fas fa-print"></i> Print Report
        </button>
    </div>

    <!-- Date Filter -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="start_date" class="form-control" value="<?php echo $start_date; ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">End Date</label>
                    <input type="date" name="end_date" class="form-control" value="<?php echo $end_date; ?>">
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Apply Filter
                    </button>
                    <a href="cab-reports.php" class="btn btn-secondary">
                        <i class="fas fa-redo"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Overview Statistics -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card stats-card">
                <div class="card-body text-center">
                    <i class="fas fa-list fa-2x mb-2"></i>
                    <h5>Total Bookings</h5>
                    <h2><?php echo $total_bookings; ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stats-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                <div class="card-body text-center">
                    <i class="fas fa-rupee-sign fa-2x mb-2"></i>
                    <h5>Total Revenue</h5>
                    <h2><?php echo formatPriceINR($total_revenue); ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stats-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                <div class="card-body text-center">
                    <i class="fas fa-clock fa-2x mb-2"></i>
                    <h5>Pending</h5>
                    <h2><?php echo $pending_bookings; ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stats-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                <div class="card-body text-center">
                    <i class="fas fa-check-circle fa-2x mb-2"></i>
                    <h5>Confirmed</h5>
                    <h2><?php echo $confirmed_bookings; ?></h2>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <!-- Bookings by Route -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-route"></i> Bookings by Route</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Route</th>
                                    <th class="text-center">Bookings</th>
                                    <th class="text-end">Revenue</th>
                                    <th class="text-end">Paid</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($bookings_by_route)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted">No data available</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($bookings_by_route as $route): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($route['route_name']); ?></strong><br>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($route['from_location']); ?> → 
                                                    <?php echo htmlspecialchars($route['to_location']); ?>
                                                </small>
                                            </td>
                                            <td class="text-center"><?php echo $route['booking_count']; ?></td>
                                            <td class="text-end"><?php echo formatPriceINR($route['total_revenue']); ?></td>
                                            <td class="text-end text-success"><?php echo formatPriceINR($route['paid_revenue']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bookings by Cab Type -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-car"></i> Bookings by Cab Type</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Cab Type</th>
                                    <th class="text-center">Bookings</th>
                                    <th class="text-end">Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($bookings_by_cab)): ?>
                                    <tr>
                                        <td colspan="3" class="text-center text-muted">No data available</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($bookings_by_cab as $cab): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($cab['display_name']); ?></td>
                                            <td class="text-center"><?php echo $cab['booking_count']; ?></td>
                                            <td class="text-end"><?php echo formatPriceINR($cab['total_revenue']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <!-- Payment Status Breakdown -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0"><i class="fas fa-credit-card"></i> Payment Status</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <tbody>
                            <?php foreach ($payment_breakdown as $payment): ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $payment['payment_status'] === 'paid' ? 'success' : 
                                                ($payment['payment_status'] === 'refunded' ? 'warning' : 'secondary'); 
                                        ?>">
                                            <?php echo ucfirst($payment['payment_status']); ?>
                                        </span>
                                    </td>
                                    <td class="text-center"><?php echo $payment['count']; ?></td>
                                    <td class="text-end"><?php echo formatPriceINR($payment['total']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Trip Type Breakdown -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-arrows-alt-h"></i> Trip Type</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <tbody>
                            <?php foreach ($trip_type_breakdown as $trip): ?>
                                <tr>
                                    <td>
                                        <?php if ($trip['trip_type'] === 'one_way'): ?>
                                            <i class="fas fa-arrow-right"></i> One Way
                                        <?php else: ?>
                                            <i class="fas fa-arrows-alt-h"></i> Round Trip
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center"><?php echo $trip['count']; ?></td>
                                    <td class="text-end"><?php echo formatPriceINR($trip['total']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Daily Bookings -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="fas fa-calendar-day"></i> Recent Daily Stats</h5>
                </div>
                <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                    <table class="table table-sm">
                        <tbody>
                            <?php foreach (array_slice($daily_bookings, 0, 10) as $day): ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($day['booking_date'])); ?></td>
                                    <td class="text-center"><?php echo $day['count']; ?></td>
                                    <td class="text-end"><?php echo formatPriceINR($day['revenue']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    .sidebar, .navbar, .btn, form { display: none !important; }
    .main-content { margin: 0 !important; padding: 0 !important; }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

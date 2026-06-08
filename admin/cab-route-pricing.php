<?php
require_once '../config/config.php';
requireLogin();

// Get route_id from URL
$route_id = isset($_GET['route_id']) ? intval($_GET['route_id']) : 0;

if (!$route_id) {
    header('Location: cab-routes.php');
    exit;
}

// Get route details
$route = $db->fetch("SELECT * FROM cab_routes WHERE id = ?", [$route_id]);

if (!$route) {
    $_SESSION['error'] = "Route not found";
    header('Location: cab-routes.php');
    exit;
}

// Handle pricing update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_pricing'])) {
    foreach ($_POST['pricing'] as $pricing_id => $data) {
        $one_way = floatval($data['one_way']);
        $round_trip = floatval($data['round_trip']);
        $price = $one_way; // Default price is one-way price
        $status = $data['status'];
        
        $db->query("
            UPDATE cab_route_pricing 
            SET one_way_price = ?, round_trip_price = ?, price = ?, status = ?
            WHERE id = ?
        ", [$one_way, $round_trip, $price, $status, $pricing_id]);
    }
    
    $_SESSION['success'] = "Pricing updated successfully!";
    header('Location: cab-route-pricing.php?route_id=' . $route_id);
    exit;
}

// Get all pricing for this route with cab type details
$pricing_data = $db->fetchAll("
    SELECT 
        crp.*,
        ct.display_name as cab_name,
        ct.max_passengers,
        ct.name as cab_type_name
    FROM cab_route_pricing crp
    JOIN cab_types ct ON crp.cab_type_id = ct.id
    WHERE crp.route_id = ?
    ORDER BY ct.base_price ASC
", [$route_id]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set Route Pricing - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar { background: #2c3e50; min-height: 100vh; }
        .sidebar .nav-link { color: #bdc3c7; padding: 15px 20px; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { color: #fff; background: #34495e; }
        .main-content { background: #ecf0f1; min-height: 100vh; }
        .card { border: none; border-radius: 15px; box-shadow: 0 2px 15px rgba(0,0,0,0.08); }
        .pricing-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        .pricing-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.2);
        }
        .cab-icon {
            width: 70px;
            height: 70px;
            background: #1bbc9b;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
        }
        .input-group-text {
            background: #1bbc9b;
            color: white;
            border: none;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 px-0 sidebar">
                <div class="p-3 text-center">
                    <h4 class="text-white"><?php echo getSetting('site_name'); ?></h4>
                    <small class="text-muted">Admin Panel</small>
                </div>
                <nav class="nav flex-column">
                    <a class="nav-link" href="index.php"><i class="fas fa-tachometer-alt me-2"></i> Dashboard</a>
                    <a class="nav-link" href="tours.php"><i class="fas fa-map-marked-alt me-2"></i> Tours</a>
                    <a class="nav-link" href="destinations.php"><i class="fas fa-globe me-2"></i> Destinations</a>
                    <a class="nav-link" href="bookings.php"><i class="fas fa-calendar-check me-2"></i> Bookings</a>
                    <a class="nav-link active" href="cab-routes.php"><i class="fas fa-route me-2"></i> Cab Routes</a>
                    <a class="nav-link" href="cab_pricing.php"><i class="fas fa-car me-2"></i> Cab Pricing</a>
                    <a class="nav-link" href="settings.php"><i class="fas fa-cog me-2"></i> Settings</a>
                    <a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
                </nav>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-10 main-content">
                <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
                    <div class="container-fluid">
                        <span class="navbar-brand mb-0 h1">Set Pricing for Route</span>
                        <div class="navbar-nav ms-auto">
                            <a href="cab-routes.php" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left"></i> Back to Routes</a>
                        </div>
                    </div>
                </nav>

                <div class="p-4">
                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Route Info Card -->
                    <div class="card mb-4" style="background: #1bbc9b; color: white;">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-3">
                                <i class="fas fa-route fa-3x"></i>
                                <div>
                                    <h3 class="mb-1"><?php echo htmlspecialchars($route['route_name']); ?></h3>
                                    <p class="mb-0 opacity-75">
                                        <span class="badge bg-white text-primary"><?php echo htmlspecialchars($route['from_location']); ?></span>
                                        <i class="fas fa-arrow-right mx-2"></i>
                                        <span class="badge bg-white text-success"><?php echo htmlspecialchars($route['to_location']); ?></span>
                                        <span class="ms-3"><i class="fas fa-road"></i> <?php echo $route['distance_km']; ?> km</span>
                                        <span class="ms-3"><i class="fas fa-clock"></i> <?php echo htmlspecialchars($route['estimated_duration']); ?></span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pricing Form -->
                    <form method="POST" action="">
                        <input type="hidden" name="update_pricing" value="1">
                        
                        <?php foreach ($pricing_data as $pricing): ?>
                        <div class="pricing-card">
                            <div class="row align-items-center">
                                <!-- Cab Icon & Info -->
                                <div class="col-md-3">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="cab-icon">
                                            <i class="fas fa-car"></i>
                                        </div>
                                        <div>
                                            <h5 class="mb-0"><?php echo htmlspecialchars($pricing['cab_name']); ?></h5>
                                            <small class="text-muted"><i class="fas fa-users"></i> <?php echo $pricing['max_passengers']; ?> Passengers</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- One Way Price -->
                                <div class="col-md-3">
                                    <label class="form-label fw-bold">One Way Price</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-rupee-sign"></i></span>
                                        <input type="number" 
                                               name="pricing[<?php echo $pricing['id']; ?>][one_way]" 
                                               class="form-control" 
                                               value="<?php echo $pricing['one_way_price']; ?>" 
                                               step="0.01" 
                                               min="0"
                                               required
                                               placeholder="0.00">
                                    </div>
                                </div>
                                
                                <!-- Round Trip Price -->
                                <div class="col-md-3">
                                    <label class="form-label fw-bold">Round Trip Price</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-rupee-sign"></i></span>
                                        <input type="number" 
                                               name="pricing[<?php echo $pricing['id']; ?>][round_trip]" 
                                               class="form-control" 
                                               value="<?php echo $pricing['round_trip_price']; ?>" 
                                               step="0.01" 
                                               min="0"
                                               required
                                               placeholder="0.00">
                                    </div>
                                    <small class="text-muted">Usually 1.8x - 2x of one-way</small>
                                </div>
                                
                                <!-- Status -->
                                <div class="col-md-3">
                                    <label class="form-label fw-bold">Status</label>
                                    <select name="pricing[<?php echo $pricing['id']; ?>][status]" class="form-select">
                                        <option value="active" <?php echo $pricing['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="inactive" <?php echo $pricing['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <?php if (empty($pricing_data)): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle"></i> No cab types found for this route. Please add cab types first.
                            </div>
                        <?php endif; ?>
                        
                        <div class="text-center mt-4">
                            <button type="submit" class="btn btn-primary btn-lg" <?php echo empty($pricing_data) ? 'disabled' : ''; ?>>
                                <i class="fas fa-save"></i> Save All Pricing
                            </button>
                            <a href="cab-routes.php" class="btn btn-secondary btn-lg ms-2">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </form>

                    <!-- Pricing Tips -->
                    <div class="card mt-4">
                        <div class="card-header bg-info text-white">
                            <h6 class="mb-0"><i class="fas fa-lightbulb"></i> Pricing Tips</h6>
                        </div>
                        <div class="card-body">
                            <ul class="mb-0">
                                <li>Round trip prices are usually 1.8x to 2x of one-way prices (not exactly double due to driver convenience)</li>
                                <li>Consider distance, terrain difficulty, and toll charges when setting prices</li>
                                <li>Premium vehicles (Innova) typically cost 20-30% more than standard SUVs</li>
                                <li>All prices are in INR (₹)</li>
                                <li>Set status to "Inactive" to temporarily hide a cab option from customers</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

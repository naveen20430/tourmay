<?php
require_once '../config/config.php';
requireLogin();

// Handle delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = intval($_GET['delete']);
    try {
        $db->query("DELETE FROM cab_routes WHERE id = ?", [$id]);
        $_SESSION['success'] = "Route deleted successfully";
    } catch (Exception $e) {
        $_SESSION['error'] = "Failed to delete route: " . $e->getMessage();
    }
    header('Location: cab-routes.php');
    exit;
}

// Handle add/edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $from_location = trim($_POST['from_location']);
    $to_location = trim($_POST['to_location']);
    $distance_km = floatval($_POST['distance_km']);
    $estimated_duration = trim($_POST['estimated_duration']);
    $description = trim($_POST['description']);
    $status = $_POST['status'];
    
    // Auto-generate route name
    $route_name = $from_location . ' to ' . $to_location;
    
    if (isset($_POST['route_id']) && !empty($_POST['route_id'])) {
        // Update existing route
        $id = intval($_POST['route_id']);
        try {
            $db->query("
                UPDATE cab_routes SET 
                    route_name = ?,
                    from_location = ?,
                    to_location = ?,
                    distance_km = ?,
                    estimated_duration = ?,
                    description = ?,
                    status = ?
                WHERE id = ?
            ", [$route_name, $from_location, $to_location, $distance_km, $estimated_duration, $description, $status, $id]);
            $_SESSION['success'] = "Route updated successfully";
        } catch (Exception $e) {
            $_SESSION['error'] = "Failed to update route: " . $e->getMessage();
        }
    } else {
        // Add new route
        try {
            // Get next display order
            $max_order = $db->fetch("SELECT MAX(display_order) as max_order FROM cab_routes");
            $display_order = ($max_order['max_order'] ?? 0) + 1;
            
            $db->query("
                INSERT INTO cab_routes (route_name, from_location, to_location, distance_km, estimated_duration, description, status, display_order)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ", [$route_name, $from_location, $to_location, $distance_km, $estimated_duration, $description, $status, $display_order]);
            
            $route_id = $db->lastInsertId();
            
            // Add default pricing for all cab types
            $cab_types = $db->fetchAll("SELECT id FROM cab_types WHERE status = 'active'");
            foreach ($cab_types as $cab) {
                $db->query("
                    INSERT INTO cab_route_pricing (route_id, cab_type_id, price, one_way_price, round_trip_price, status)
                    VALUES (?, ?, 0, 0, 0, 'active')
                ", [$route_id, $cab['id']]);
            }
            
            $_SESSION['success'] = "Route added successfully! Don't forget to set pricing.";
        } catch (Exception $e) {
            $_SESSION['error'] = "Failed to add route: " . $e->getMessage();
        }
    }
    header('Location: cab-routes.php');
    exit;
}

// Get all routes
$routes = $db->fetchAll("SELECT * FROM cab_routes ORDER BY display_order ASC");

// Get route for editing
$edit_route = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_route = $db->fetch("SELECT * FROM cab_routes WHERE id = ?", [intval($_GET['edit'])]);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Cab Routes - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar { background: #2c3e50; min-height: 100vh; }
        .sidebar .nav-link { color: #bdc3c7; padding: 15px 20px; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { color: #fff; background: #34495e; }
        .main-content { background: #ecf0f1; min-height: 100vh; }
        .card { border: none; border-radius: 15px; box-shadow: 0 2px 15px rgba(0,0,0,0.08); }
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
                    <a class="nav-link" href="search-queries.php"><i class="fas fa-search me-2"></i> Search Queries</a>
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
                        <span class="navbar-brand mb-0 h1">Manage Cab Routes</span>
                        <div class="navbar-nav ms-auto">
                            <span class="nav-link">Welcome, <?php echo $_SESSION['admin_name']; ?>!</span>
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

                    <!-- Add/Edit Route Form -->
                    <div class="card mb-4">
                        <div class="card-header bg-white">
                            <h5 class="mb-0"><?php echo $edit_route ? 'Edit Route' : 'Add New Route'; ?></h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <?php if ($edit_route): ?>
                                    <input type="hidden" name="route_id" value="<?php echo $edit_route['id']; ?>">
                                <?php endif; ?>
                                
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label class="form-label">From Location <span class="text-danger">*</span></label>
                                            <input type="text" name="from_location" class="form-control" required 
                                                   value="<?php echo $edit_route ? htmlspecialchars($edit_route['from_location']) : ''; ?>"
                                                   placeholder="e.g., Chandigarh">
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label class="form-label">To Location <span class="text-danger">*</span></label>
                                            <input type="text" name="to_location" class="form-control" required 
                                                   value="<?php echo $edit_route ? htmlspecialchars($edit_route['to_location']) : ''; ?>"
                                                   placeholder="e.g., Shimla">
                                            <small class="text-muted">Route name will be: From - To</small>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-2">
                                        <div class="mb-3">
                                            <label class="form-label">Distance (km)</label>
                                            <input type="number" step="0.01" name="distance_km" class="form-control" 
                                                   value="<?php echo $edit_route ? $edit_route['distance_km'] : '0'; ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-2">
                                        <div class="mb-3">
                                            <label class="form-label">Duration</label>
                                            <input type="text" name="estimated_duration" class="form-control" 
                                                   value="<?php echo $edit_route ? htmlspecialchars($edit_route['estimated_duration']) : ''; ?>"
                                                   placeholder="e.g., 3-4 hours">
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-2">
                                        <div class="mb-3">
                                            <label class="form-label">Status</label>
                                            <select name="status" class="form-select">
                                                <option value="active" <?php echo ($edit_route && $edit_route['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                                                <option value="inactive" <?php echo ($edit_route && $edit_route['status'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Description</label>
                                    <textarea name="description" class="form-control" rows="2"><?php echo $edit_route ? htmlspecialchars($edit_route['description']) : ''; ?></textarea>
                                </div>
                                
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> <?php echo $edit_route ? 'Update Route' : 'Add Route'; ?>
                                    </button>
                                    <?php if ($edit_route): ?>
                                        <a href="cab-routes.php" class="btn btn-secondary">Cancel</a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Routes List -->
                    <div class="card">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">All Routes (<?php echo count($routes); ?>)</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Route Name</th>
                                            <th>From → To</th>
                                            <th>Distance</th>
                                            <th>Duration</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($routes)): ?>
                                            <tr>
                                                <td colspan="6" class="text-center py-4">
                                                    <i class="fas fa-route fa-3x text-muted mb-3"></i>
                                                    <p class="text-muted">No routes found. Add your first route above.</p>
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($routes as $route): ?>
                                                <tr>
                                                    <td><strong><?php echo htmlspecialchars($route['route_name']); ?></strong></td>
                                                    <td>
                                                        <span class="badge bg-primary"><?php echo htmlspecialchars($route['from_location']); ?></span>
                                                        <i class="fas fa-arrow-right mx-1"></i>
                                                        <span class="badge bg-success"><?php echo htmlspecialchars($route['to_location']); ?></span>
                                                    </td>
                                                    <td><?php echo $route['distance_km']; ?> km</td>
                                                    <td><?php echo htmlspecialchars($route['estimated_duration']); ?></td>
                                                    <td>
                                                        <span class="badge <?php echo $route['status'] == 'active' ? 'bg-success' : 'bg-secondary'; ?>">
                                                            <?php echo $route['status']; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <a href="cab-route-pricing.php?route_id=<?php echo $route['id']; ?>" class="btn btn-sm btn-info" title="Set Pricing">
                                                            <i class="fas fa-dollar-sign"></i>
                                                        </a>
                                                        <a href="?edit=<?php echo $route['id']; ?>" class="btn btn-sm btn-warning">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <a href="?delete=<?php echo $route['id']; ?>" class="btn btn-sm btn-danger" 
                                                           onclick="return confirm('Delete this route and all its pricing?')">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                    </td>
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
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

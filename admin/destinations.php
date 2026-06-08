<?php
require_once '../config/config.php';
requireLogin();

// Handle delete action
if ($_GET['action'] ?? '' === 'delete' && $_GET['id']) {
    $destination_id = $_GET['id'];
    
    // Get destination data to delete images
    $destination = $db->fetch("SELECT featured_image, gallery FROM destinations WHERE id = ?", [$destination_id]);
    
    if ($destination) {
        // Delete featured image
        if ($destination['featured_image'] && file_exists('../' . $destination['featured_image'])) {
            unlink('../' . $destination['featured_image']);
        }
        
        // Delete gallery images
        if ($destination['gallery']) {
            $gallery = json_decode($destination['gallery'], true);
            if ($gallery) {
                foreach ($gallery as $image) {
                    if (file_exists('../' . $image)) {
                        unlink('../' . $image);
                    }
                }
            }
        }
        
        // Check if destination is used by any tours
        $tour_count = $db->fetchColumn("SELECT COUNT(*) FROM tours WHERE destination_id = ?", [$destination_id]);
        
        if ($tour_count > 0) {
            // Don't delete, just set to inactive and show warning
            $db->execute("UPDATE destinations SET status = 'inactive' WHERE id = ?", [$destination_id]);
            header('Location: destinations.php?msg=deactivated&tours=' . $tour_count);
        } else {
            // Delete destination from database
            $db->execute("DELETE FROM destinations WHERE id = ?", [$destination_id]);
            header('Location: destinations.php?msg=deleted');
        }
        exit;
    }
}

// Get all destinations with tour count
$destinations = $db->fetchAll("
    SELECT d.*, 
           COUNT(t.id) as tour_count
    FROM destinations d 
    LEFT JOIN tours t ON d.id = t.destination_id 
    GROUP BY d.id 
    ORDER BY d.created_at DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Destinations - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar { background: #2c3e50; min-height: 100vh; }
        .sidebar .nav-link { color: #bdc3c7; padding: 15px 20px; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { color: #fff; background: #34495e; }
        .main-content { background: #ecf0f1; min-height: 100vh; }
        .destination-image { width: 60px; height: 60px; object-fit: cover; border-radius: 8px; }
        .status-badge.active { background: #28a745; }
        .status-badge.inactive { background: #dc3545; }
        .status-badge { color: white; padding: 4px 8px; border-radius: 12px; font-size: 0.8em; }
        .popular-badge { background: #ffc107; color: #000; padding: 2px 6px; border-radius: 10px; font-size: 0.75em; }
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
                    <a class="nav-link" href="index.php">
                        <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                    </a>
                    <a class="nav-link" href="tours.php">
                        <i class="fas fa-map-marked-alt me-2"></i> Tours
                    </a>
                    <a class="nav-link active" href="destinations.php">
                        <i class="fas fa-globe me-2"></i> Destinations
                    </a>
                    <a class="nav-link" href="bookings.php">
                        <i class="fas fa-calendar-check me-2"></i> Bookings
                    </a>
                    <a class="nav-link" href="blog.php">
                        <i class="fas fa-blog me-2"></i> Blog Posts
                    </a>
                    <a class="nav-link" href="users.php">
                        <i class="fas fa-users me-2"></i> Users
                    </a>
                    <a class="nav-link" href="settings.php">
                        <i class="fas fa-cog me-2"></i> Settings
                    </a>
                    <a class="nav-link" href="logout.php">
                        <i class="fas fa-sign-out-alt me-2"></i> Logout
                    </a>
                </nav>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-10 main-content">
                <!-- Header -->
                <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
                    <div class="container-fluid">
                        <span class="navbar-brand mb-0 h1">Destinations Management</span>
                        <div class="navbar-nav ms-auto">
                            <span class="nav-link">Welcome, <?php echo $_SESSION['admin_name']; ?>!</span>
                        </div>
                    </div>
                </nav>
                
                <!-- Content -->
                <div class="p-4">
                    <?php if ($_GET['msg'] ?? '' === 'deleted'): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="fas fa-check-circle me-2"></i>Destination deleted successfully!
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php elseif ($_GET['msg'] ?? '' === 'deactivated'): ?>
                        <div class="alert alert-warning alert-dismissible fade show">
                            <i class="fas fa-exclamation-triangle me-2"></i>Destination has been deactivated instead of deleted because it's used by <?php echo $_GET['tours']; ?> tour(s).
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php elseif ($_GET['msg'] ?? '' === 'added'): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="fas fa-check-circle me-2"></i>Destination added successfully!
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php elseif ($_GET['msg'] ?? '' === 'updated'): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="fas fa-check-circle me-2"></i>Destination updated successfully!
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2>All Destinations</h2>
                        <a href="destination-add.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Add New Destination
                        </a>
                    </div>
                    
                    <div class="card">
                        <div class="card-body">
                            <?php if (empty($destinations)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-globe fa-4x text-muted mb-3"></i>
                                    <h4 class="text-muted">No Destinations Found</h4>
                                    <p class="text-muted">Start by adding your first destination</p>
                                    <a href="destination-add.php" class="btn btn-primary">
                                        <i class="fas fa-plus me-2"></i>Add New Destination
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead class="table-dark">
                                            <tr>
                                                <th>Image</th>
                                                <th>Destination Name</th>
                                                <th>Country/City</th>
                                                <th>Best Time</th>
                                                <th>Tours</th>
                                                <th>Status</th>
                                                <th>Popular</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($destinations as $destination): ?>
                                                <tr>
                                                    <td>
                                                        <img src="../<?php echo $destination['featured_image'] ?: 'assets/images/destinations/default.jpg'; ?>" 
                                                             class="destination-image" alt="<?php echo htmlspecialchars($destination['name']); ?>">
                                                    </td>
                                                    <td>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($destination['name']); ?></strong>
                                                            <br>
                                                            <small class="text-muted">
                                                                <?php echo substr(htmlspecialchars($destination['short_description']), 0, 50); ?>...
                                                            </small>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <?php echo htmlspecialchars($destination['country']); ?>
                                                        <?php if ($destination['city']): ?>
                                                            <br><small class="text-muted"><?php echo htmlspecialchars($destination['city']); ?></small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <small><?php echo htmlspecialchars($destination['best_time_to_visit'] ?: 'Not specified'); ?></small>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-info"><?php echo $destination['tour_count']; ?> tours</span>
                                                    </td>
                                                    <td>
                                                        <span class="status-badge <?php echo $destination['status']; ?>">
                                                            <?php echo ucfirst($destination['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php if ($destination['popular']): ?>
                                                            <span class="popular-badge">Popular</span>
                                                        <?php else: ?>
                                                            <small class="text-muted">Regular</small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm">
                                                            <a href="../destination/<?php echo $destination['slug']; ?>" class="btn btn-outline-info" target="_blank" title="View">
                                                                <i class="fas fa-eye"></i>
                                                            </a>
                                                            <a href="destination-edit.php?id=<?php echo $destination['id']; ?>" class="btn btn-outline-primary" title="Edit">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                            <a href="?action=delete&id=<?php echo $destination['id']; ?>" 
                                                               class="btn btn-outline-danger" 
                                                               title="Delete"
                                                               onclick="return confirm('Are you sure you want to delete this destination? <?php echo $destination['tour_count'] > 0 ? 'This destination has ' . $destination['tour_count'] . ' tour(s) associated with it and will be deactivated instead of deleted.' : 'This action cannot be undone.'; ?>')">
                                                                <i class="fas fa-trash"></i>
                                                            </a>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="row mt-4">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">Quick Stats</h5>
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="text-center">
                                                <h3 class="text-primary"><?php echo count($destinations); ?></h3>
                                                <small>Total Destinations</small>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="text-center">
                                                <h3 class="text-success"><?php echo count(array_filter($destinations, fn($d) => $d['status'] === 'active')); ?></h3>
                                                <small>Active Destinations</small>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="text-center">
                                                <h3 class="text-warning"><?php echo count(array_filter($destinations, fn($d) => $d['popular'])); ?></h3>
                                                <small>Popular Destinations</small>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="text-center">
                                                <?php 
                                                $total_tours = array_sum(array_column($destinations, 'tour_count'));
                                                ?>
                                                <h3 class="text-info"><?php echo $total_tours; ?></h3>
                                                <small>Total Tours</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
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

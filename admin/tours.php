<?php
require_once '../config/config.php';
require_once '../includes/tour_destinations.php';
requireLogin();

ensureTourDestinationsSchema();

// Handle delete action
if ($_GET['action'] ?? '' === 'delete' && $_GET['id']) {
    $tour_id = $_GET['id'];
    
    // Get tour data to delete images
    $tour = $db->fetch("SELECT featured_image, gallery FROM tours WHERE id = ?", [$tour_id]);
    
    if ($tour) {
        // Delete featured image
        if ($tour['featured_image'] && file_exists('../' . $tour['featured_image'])) {
            unlink('../' . $tour['featured_image']);
        }
        
        // Delete gallery images
        if ($tour['gallery']) {
            $gallery = json_decode($tour['gallery'], true);
            if ($gallery) {
                foreach ($gallery as $image) {
                    if (file_exists('../' . $image)) {
                        unlink('../' . $image);
                    }
                }
            }
        }
        
        // Delete tour relations then tour
        $db->execute("DELETE FROM tour_destinations WHERE tour_id = ?", [$tour_id]);
        $db->execute("DELETE FROM tours WHERE id = ?", [$tour_id]);
        header('Location: tours.php?msg=deleted');
        exit;
    }
}

// Get all tours
$tours = $db->fetchAll("
    SELECT t.*,
           COALESCE(
               NULLIF(GROUP_CONCAT(DISTINCT d.name ORDER BY td.sort_order ASC, d.name ASC SEPARATOR ', '), ''),
               d_primary.name
           ) AS destination_name
    FROM tours t
    LEFT JOIN tour_destinations td ON t.id = td.tour_id
    LEFT JOIN destinations d ON td.destination_id = d.id
    LEFT JOIN destinations d_primary ON t.destination_id = d_primary.id
    GROUP BY t.id
    ORDER BY t.created_at DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Tours - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar { background: #2c3e50; min-height: 100vh; }
        .sidebar .nav-link { color: #bdc3c7; padding: 15px 20px; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { color: #fff; background: #34495e; }
        .main-content { background: #ecf0f1; min-height: 100vh; }
        .tour-image { width: 60px; height: 60px; object-fit: cover; border-radius: 8px; }
        .status-badge.active { background: #28a745; }
        .status-badge.inactive { background: #dc3545; }
        .status-badge { color: white; padding: 4px 8px; border-radius: 12px; font-size: 0.8em; }
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
                    <a class="nav-link active" href="tours.php">
                        <i class="fas fa-map-marked-alt me-2"></i> Tours
                    </a>
                    <a class="nav-link" href="destinations.php">
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
                        <span class="navbar-brand mb-0 h1">Tours Management</span>
                        <div class="navbar-nav ms-auto">
                            <span class="nav-link">Welcome, <?php echo $_SESSION['admin_name']; ?>!</span>
                        </div>
                    </div>
                </nav>
                
                <!-- Content -->
                <div class="p-4">
                    <?php if ($_GET['msg'] ?? '' === 'deleted'): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="fas fa-check-circle me-2"></i>Tour deleted successfully!
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php elseif ($_GET['msg'] ?? '' === 'added'): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="fas fa-check-circle me-2"></i>Tour added successfully!
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php elseif ($_GET['msg'] ?? '' === 'updated'): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="fas fa-check-circle me-2"></i>Tour updated successfully!
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2>All Tours</h2>
                        <a href="tour-add.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Add New Tour
                        </a>
                    </div>
                    
                    <div class="card">
                        <div class="card-body">
                            <?php if (empty($tours)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-map-marked-alt fa-4x text-muted mb-3"></i>
                                    <h4 class="text-muted">No Tours Found</h4>
                                    <p class="text-muted">Start by adding your first tour</p>
                                    <a href="tour-add.php" class="btn btn-primary">
                                        <i class="fas fa-plus me-2"></i>Add New Tour
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead class="table-dark">
                                            <tr>
                                                <th>Image</th>
                                                <th>Tour Title</th>
                                                <th>Destination</th>
                                                <th>Price</th>
                                                <th>Duration</th>
                                                <th>Status</th>
                                                <th>Featured</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($tours as $tour): ?>
                                                <tr>
                                                    <td>
                                                        <img src="../<?php echo $tour['featured_image'] ?: 'assets/images/tours/default-tour.jpg'; ?>" 
                                                             class="tour-image" alt="<?php echo htmlspecialchars($tour['title']); ?>">
                                                    </td>
                                                    <td>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($tour['title']); ?></strong>
                                                            <br>
                                                            <small class="text-muted">
                                                                <i class="fas fa-users me-1"></i>Max <?php echo $tour['max_people']; ?> people
                                                            </small>
                                                        </div>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($tour['destination_name'] ?: 'No Destination'); ?></td>
                                                    <td>
                                                        <?php if ($tour['discount_price']): ?>
                                                            <span class="text-decoration-line-through text-muted">₹<?php echo number_format($tour['price'], 0); ?></span><br>
                                                            <strong class="text-success">₹<?php echo number_format($tour['discount_price'], 0); ?></strong>
                                                        <?php else: ?>
                                                            <strong>₹<?php echo number_format($tour['price'], 0); ?></strong>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php echo $tour['duration_days']; ?> Days<br>
                                                        <small class="text-muted"><?php echo $tour['duration_nights']; ?> Nights</small>
                                                    </td>
                                                    <td>
                                                        <span class="status-badge <?php echo $tour['status']; ?>">
                                                            <?php echo ucfirst($tour['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php if ($tour['featured']): ?>
                                                            <i class="fas fa-star text-warning" title="Featured"></i>
                                                        <?php else: ?>
                                                            <i class="far fa-star text-muted" title="Not Featured"></i>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm">
                                                            <a href="../tour/<?php echo $tour['slug']; ?>" class="btn btn-outline-info" target="_blank" title="View">
                                                                <i class="fas fa-eye"></i>
                                                            </a>
                                                            <a href="tour-edit.php?id=<?php echo $tour['id']; ?>" class="btn btn-outline-primary" title="Edit">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                            <a href="?action=delete&id=<?php echo $tour['id']; ?>" 
                                                               class="btn btn-outline-danger" 
                                                               title="Delete"
                                                               onclick="return confirm('Are you sure you want to delete this tour? This action cannot be undone.')">
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
                                                <h3 class="text-primary"><?php echo count($tours); ?></h3>
                                                <small>Total Tours</small>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="text-center">
                                                <h3 class="text-success"><?php echo count(array_filter($tours, fn($t) => $t['status'] === 'active')); ?></h3>
                                                <small>Active Tours</small>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="text-center">
                                                <h3 class="text-warning"><?php echo count(array_filter($tours, fn($t) => $t['featured'])); ?></h3>
                                                <small>Featured Tours</small>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="text-center">
                                                <?php 
                                                $avg_price = count($tours) > 0 ? array_sum(array_column($tours, 'price')) / count($tours) : 0;
                                                ?>
                                                <h3 class="text-info">$<?php echo number_format($avg_price, 0); ?></h3>
                                                <small>Average Price</small>
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

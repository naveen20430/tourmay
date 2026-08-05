<?php
// Ensure this is included after database connection and authentication
if (!function_exists('getSetting')) {
    die('This file must be included after config.php');
}

// Get contact message statistics for sidebar badge
try {
    $newContacts = $db->fetch("SELECT COUNT(*) as count FROM contact_inquiries WHERE status = 'new'")['count'] ?? 0;
} catch (Exception $e) {
    $newContacts = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - <?php echo getSetting('site_name'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar { background: #2c3e50; min-height: 100vh; }
        .sidebar .nav-link { color: #bdc3c7; padding: 15px 20px; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { color: #fff; background: #34495e; }
        .main-content { background: #ecf0f1; min-height: 100vh; }
        .stats-card { 
            background: #1bbc9b; 
            color: white; 
            border-radius: 15px; 
            transition: transform 0.2s;
            border: none;
        }
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        .navbar-brand { font-weight: bold; }
        .card { 
            border: none; 
            border-radius: 15px; 
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
        }
        .btn { border-radius: 10px; }
        .list-group-item { 
            border: none; 
            border-radius: 8px !important; 
            margin-bottom: 2px;
        }
        .list-group-item:hover {
            background: #f8f9fa;
            transform: translateX(5px);
            transition: all 0.2s;
        }
        .badge { border-radius: 20px; }
        .form-control, .form-select { border-radius: 8px; }
        .table { background: white; border-radius: 10px; overflow: hidden; }
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
                    <a class="nav-link" href="tour-slider.php">
                        <i class="fas fa-sliders-h me-2"></i> Tour Slider
                    </a>
                    <a class="nav-link" href="destinations.php">
                        <i class="fas fa-globe me-2"></i> Destinations
                    </a>
                    <a class="nav-link" href="bookings.php">
                        <i class="fas fa-calendar-check me-2"></i> Bookings
                    </a>
                    <a class="nav-link" href="cab-routes.php">
                        <i class="fas fa-route me-2"></i> Cab Routes
                    </a>
                    <a class="nav-link" href="cab-bookings.php">
                        <i class="fas fa-taxi me-2"></i> Cab Bookings
                    </a>
                    <a class="nav-link" href="cab-reports.php">
                        <i class="fas fa-chart-line me-2"></i> Cab Reports
                    </a>
                    <a class="nav-link<?php echo basename($_SERVER['PHP_SELF']) === 'pickup-times.php' ? ' active' : ''; ?>" href="pickup-times.php">
                        <i class="fas fa-clock me-2"></i> Pickup Times
                    </a>
                    <a class="nav-link" href="blog.php">
                        <i class="fas fa-blog me-2"></i> Blog Posts
                    </a>
                    <a class="nav-link" href="users.php">
                        <i class="fas fa-users me-2"></i> Users
                    </a>
                    <a class="nav-link" href="contacts.php">
                        <i class="fas fa-envelope me-2"></i> Contact Messages
                        <?php if ($newContacts > 0): ?>
                            <span class="badge bg-danger ms-1"><?php echo $newContacts; ?></span>
                        <?php endif; ?>
                    </a>
                    <a class="nav-link" href="hero-images.php">
                        <i class="fas fa-image me-2"></i> Hero Images
                    </a>
                    <a class="nav-link<?php echo basename($_SERVER['PHP_SELF']) === 'logs.php' ? ' active' : ''; ?>" href="logs.php">
                        <i class="fas fa-file-alt me-2"></i> Logs
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
                        <span class="navbar-brand mb-0 h1">
                            <?php
                            $currentPage = basename($_SERVER['PHP_SELF'], '.php');
                            switch($currentPage) {
                                case 'index': echo 'Dashboard'; break;
                                case 'tours': echo 'Tours Management'; break;
                                case 'tour-add': echo 'Add New Tour'; break;
                                case 'tour-edit': echo 'Edit Tour'; break;
                                case 'tour-slider': echo 'Tour Slider Management'; break;
                                case 'destinations': echo 'Destinations'; break;
                                case 'bookings': echo 'Bookings Management'; break;
                                case 'booking-add': echo 'Add New Booking'; break;
                                case 'booking-edit': echo 'Edit Booking'; break;
                                case 'cab-routes': echo 'Cab Routes Management'; break;
                                case 'cab-route-pricing': echo 'Cab Route Pricing'; break;
                                case 'cab-bookings': echo 'Cab Bookings Management'; break;
                                case 'cab-reports': echo 'Cab Bookings Reports'; break;
                                case 'pickup-times': echo 'Pickup Times'; break;
                                case 'blog': echo 'Blog Management'; break;
                                case 'users': echo 'Users Management'; break;
                                case 'contacts': echo 'Contact Messages'; break;
                                case 'hero-images': echo 'Hero Images'; break;
                                case 'logs': echo 'System Logs'; break;
                                case 'settings': echo 'Settings'; break;
                                default: echo 'Admin Panel';
                            }
                            ?>
                        </span>
                        <div class="navbar-nav ms-auto">
                            <span class="nav-link">Welcome, <?php echo $_SESSION['admin_name'] ?? 'Admin'; ?>!</span>
                            <a class="nav-link" href="../index.php" target="_blank">
                                <i class="fas fa-external-link-alt me-1"></i> View Site
                            </a>
                        </div>
                    </div>
                </nav>
                
                <!-- Content Area -->

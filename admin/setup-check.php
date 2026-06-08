<?php
require_once '../config/config.php';
requireLogin();

// Check which tables exist and which are missing
function checkTables($db) {
    $requiredTables = [
        'admin_users',
        'site_settings', 
        'destinations',
        'tours',
        'tour_categories',
        'tour_category_relations',
        'bookings',
        'users',
        'blog_categories',
        'blog_posts'
    ];
    
    $existingTables = [];
    $missingTables = [];
    
    // Get existing tables
    try {
        $result = $db->fetchAll("SHOW TABLES");
        foreach ($result as $row) {
            $existingTables[] = array_values($row)[0];
        }
    } catch (Exception $e) {
        echo "<div class='alert alert-danger'>Error checking tables: " . $e->getMessage() . "</div>";
        return ['existing' => [], 'missing' => $requiredTables];
    }
    
    // Find missing tables
    foreach ($requiredTables as $table) {
        if (!in_array($table, $existingTables)) {
            $missingTables[] = $table;
        }
    }
    
    return ['existing' => $existingTables, 'missing' => $missingTables];
}

$tableStatus = checkTables($db);
$hasErrors = !empty($tableStatus['missing']);

// Sample data counts
$counts = [];
if (!$hasErrors) {
    try {
        $counts['tours'] = $db->fetch("SELECT COUNT(*) as count FROM tours")['count'] ?? 0;
        $counts['destinations'] = $db->fetch("SELECT COUNT(*) as count FROM destinations")['count'] ?? 0;
        $counts['bookings'] = $db->fetch("SELECT COUNT(*) as count FROM bookings")['count'] ?? 0;
        $counts['users'] = $db->fetch("SELECT COUNT(*) as count FROM users")['count'] ?? 0;
        $counts['blog_posts'] = $db->fetch("SELECT COUNT(*) as count FROM blog_posts")['count'] ?? 0;
        $counts['admin_users'] = $db->fetch("SELECT COUNT(*) as count FROM admin_users")['count'] ?? 0;
    } catch (Exception $e) {
        // Ignore errors for missing tables
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup Check - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar { background: #2c3e50; min-height: 100vh; }
        .sidebar .nav-link { color: #bdc3c7; padding: 15px 20px; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { color: #fff; background: #34495e; }
        .main-content { background: #ecf0f1; min-height: 100vh; }
        .status-good { color: #28a745; }
        .status-warning { color: #ffc107; }
        .status-error { color: #dc3545; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 px-0 sidebar">
                <div class="p-3 text-center">
                    <h4 class="text-white"><?php echo getSetting('site_name') ?: 'Travel Hub'; ?></h4>
                    <small class="text-muted">Admin Panel</small>
                </div>
                <nav class="nav flex-column">
                    <a class="nav-link" href="index.php">
                        <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                    </a>
                    <a class="nav-link active" href="setup-check.php">
                        <i class="fas fa-database me-2"></i> Setup Check
                    </a>
                    <a class="nav-link" href="tours.php">
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
                        <span class="navbar-brand mb-0 h1">Database Setup Check</span>
                        <div class="navbar-nav ms-auto">
                            <a href="index.php" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                            </a>
                        </div>
                    </div>
                </nav>
                
                <!-- Content -->
                <div class="p-4">
                    <?php if ($hasErrors): ?>
                        <div class="alert alert-danger">
                            <h5><i class="fas fa-exclamation-triangle me-2"></i>Database Setup Issues Found!</h5>
                            <p>Some required database tables are missing. Please run the installation script to fix this.</p>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-success">
                            <h5><i class="fas fa-check-circle me-2"></i>Database Setup Complete!</h5>
                            <p>All required tables are present and the system is ready to use.</p>
                        </div>
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-header">
                                    <h5><i class="fas fa-table me-2"></i>Database Table Status</h5>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Table Name</th>
                                                    <th>Status</th>
                                                    <th>Records</th>
                                                    <th>Description</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                $tableDescriptions = [
                                                    'admin_users' => 'Admin login accounts',
                                                    'site_settings' => 'Website configuration',
                                                    'destinations' => 'Tour destinations',
                                                    'tours' => 'Tour packages',
                                                    'tour_categories' => 'Tour categories',
                                                    'tour_category_relations' => 'Tour-category links',
                                                    'bookings' => 'Customer bookings',
                                                    'users' => 'Customer accounts',
                                                    'blog_categories' => 'Blog categories',
                                                    'blog_posts' => 'Blog articles'
                                                ];
                                                
                                                $allTables = array_unique(array_merge($tableStatus['existing'], $tableStatus['missing']));
                                                sort($allTables);
                                                
                                                foreach ($allTables as $table):
                                                    $exists = in_array($table, $tableStatus['existing']);
                                                    $count = $exists ? ($counts[$table] ?? 'N/A') : 'N/A';
                                                ?>
                                                    <tr>
                                                        <td><code><?php echo $table; ?></code></td>
                                                        <td>
                                                            <?php if ($exists): ?>
                                                                <span class="badge bg-success"><i class="fas fa-check me-1"></i>Exists</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-danger"><i class="fas fa-times me-1"></i>Missing</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($exists && $count !== 'N/A'): ?>
                                                                <span class="badge bg-info"><?php echo $count; ?></span>
                                                            <?php else: ?>
                                                                <span class="text-muted">-</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <small class="text-muted"><?php echo $tableDescriptions[$table] ?? 'System table'; ?></small>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5><i class="fas fa-tools me-2"></i>Quick Actions</h5>
                                </div>
                                <div class="card-body">
                                    <?php if ($hasErrors): ?>
                                        <div class="alert alert-warning">
                                            <small><i class="fas fa-exclamation-triangle me-1"></i>
                                            Missing <?php echo count($tableStatus['missing']); ?> table(s). Run installation to fix.</small>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="d-grid gap-2">
                                        <a href="../install.php" class="btn btn-primary" target="_blank">
                                            <i class="fas fa-database me-2"></i>Run Full Installation
                                        </a>
                                        
                                        <a href="upload-test.php" class="btn btn-info">
                                            <i class="fas fa-upload me-2"></i>Test File Upload
                                        </a>
                                        
                                        <a href="index.php" class="btn btn-success">
                                            <i class="fas fa-tachometer-alt me-2"></i>Go to Dashboard
                                        </a>
                                        
                                        <?php if (!$hasErrors): ?>
                                            <a href="tours.php" class="btn btn-outline-primary">
                                                <i class="fas fa-map-marked-alt me-2"></i>Manage Tours
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card mt-3">
                                <div class="card-header">
                                    <h6><i class="fas fa-info-circle me-2"></i>System Information</h6>
                                </div>
                                <div class="card-body">
                                    <small>
                                        <strong>PHP Version:</strong> <?php echo PHP_VERSION; ?><br>
                                        <strong>Database:</strong> <?php echo $hasErrors ? 'Incomplete' : 'Ready'; ?><br>
                                        <strong>Upload Status:</strong> <?php echo ini_get('file_uploads') ? 'Enabled' : 'Disabled'; ?><br>
                                        <strong>Max Upload:</strong> <?php echo ini_get('upload_max_filesize'); ?><br>
                                    </small>
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

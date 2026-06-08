<?php
require_once '../config/config.php';
requireLogin();

// Handle user status change
if ($_GET['action'] ?? '' === 'toggle_status' && $_GET['id']) {
    $user_id = $_GET['id'];
    $user = $db->fetch("SELECT status FROM users WHERE id = ?", [$user_id]);
    
    if ($user) {
        $new_status = $user['status'] === 'active' ? 'inactive' : 'active';
        $db->execute("UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?", [$new_status, $user_id]);
        header('Location: users.php?msg=status_updated');
        exit;
    }
}

// Handle user deletion
if ($_GET['action'] ?? '' === 'delete' && $_GET['id']) {
    $user_id = $_GET['id'];
    
    // Delete user and their bookings
    $db->execute("DELETE FROM bookings WHERE guest_email = (SELECT email FROM users WHERE id = ?)", [$user_id]);
    $db->execute("DELETE FROM users WHERE id = ?", [$user_id]);
    
    header('Location: users.php?msg=deleted');
    exit;
}

// Get search filters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$country_filter = $_GET['country'] ?? '';

// Build query
$where_conditions = [];
$params = [];

if ($search) {
    $where_conditions[] = "(first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status_filter) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
}

if ($country_filter) {
    $where_conditions[] = "country = ?";
    $params[] = $country_filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get users with booking count
$users = $db->fetchAll("
    SELECT u.*, 
           COUNT(b.id) as booking_count,
           SUM(b.total_amount) as total_spent
    FROM users u 
    LEFT JOIN bookings b ON u.email = b.guest_email 
    $where_clause
    GROUP BY u.id 
    ORDER BY u.created_at DESC
", $params);

// Get unique countries for filter
$countries = $db->fetchAll("SELECT DISTINCT country FROM users WHERE country IS NOT NULL AND country != '' ORDER BY country");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar { background: #2c3e50; min-height: 100vh; }
        .sidebar .nav-link { color: #bdc3c7; padding: 15px 20px; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { color: #fff; background: #34495e; }
        .main-content { background: #ecf0f1; min-height: 100vh; }
        .status-badge.active { background: #28a745; }
        .status-badge.inactive { background: #dc3545; }
        .status-badge { color: white; padding: 4px 8px; border-radius: 12px; font-size: 0.8em; }
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #1bbc9b;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
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
                    <a class="nav-link" href="index.php">
                        <i class="fas fa-tachometer-alt me-2"></i> Dashboard
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
                    <a class="nav-link active" href="users.php">
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
                        <span class="navbar-brand mb-0 h1">User Management</span>
                        <div class="navbar-nav ms-auto">
                            <span class="nav-link">Welcome, <?php echo $_SESSION['admin_name']; ?>!</span>
                        </div>
                    </div>
                </nav>
                
                <!-- Content -->
                <div class="p-4">
                    <!-- Success Messages -->
                    <?php if ($_GET['msg'] ?? '' === 'status_updated'): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="fas fa-check-circle me-2"></i>User status updated successfully!
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php elseif ($_GET['msg'] ?? '' === 'deleted'): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="fas fa-check-circle me-2"></i>User deleted successfully!
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Filters -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <form method="GET" class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Search Users</label>
                                    <input type="text" name="search" class="form-control" 
                                           placeholder="Search by name or email..." 
                                           value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Status</label>
                                    <select name="status" class="form-select">
                                        <option value="">All Status</option>
                                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Country</label>
                                    <select name="country" class="form-select">
                                        <option value="">All Countries</option>
                                        <?php foreach ($countries as $country): ?>
                                            <option value="<?php echo htmlspecialchars($country['country']); ?>" 
                                                    <?php echo $country_filter === $country['country'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($country['country']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-search me-2"></i>Filter
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2>All Users (<?php echo count($users); ?>)</h2>
                        <div class="btn-group">
                            <a href="../register.php" class="btn btn-primary" target="_blank">
                                <i class="fas fa-user-plus me-2"></i>Test Registration
                            </a>
                            <a href="../user-dashboard.php" class="btn btn-outline-info" target="_blank">
                                <i class="fas fa-tachometer-alt me-2"></i>User Dashboard
                            </a>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-body">
                            <?php if (empty($users)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-users fa-4x text-muted mb-3"></i>
                                    <h4 class="text-muted">No Users Found</h4>
                                    <p class="text-muted">No users match your search criteria</p>
                                    <a href="users.php" class="btn btn-primary">
                                        <i class="fas fa-refresh me-2"></i>Show All Users
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead class="table-dark">
                                            <tr>
                                                <th>User</th>
                                                <th>Contact</th>
                                                <th>Location</th>
                                                <th>Bookings</th>
                                                <th>Status</th>
                                                <th>Joined</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($users as $user): ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="user-avatar me-3">
                                                                <?php echo strtoupper(substr($user['first_name'], 0, 1)); ?>
                                                            </div>
                                                            <div>
                                                                <strong><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></strong>
                                                                <br>
                                                                <small class="text-muted"><?php echo htmlspecialchars($user['email']); ?></small>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div>
                                                            <?php if ($user['phone']): ?>
                                                                <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($user['phone']); ?><br>
                                                            <?php endif; ?>
                                                            <?php if ($user['date_of_birth']): ?>
                                                                <small class="text-muted">
                                                                    <i class="fas fa-birthday-cake me-1"></i>
                                                                    <?php echo date('M d, Y', strtotime($user['date_of_birth'])); ?>
                                                                </small>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <?php if ($user['city'] || $user['country']): ?>
                                                            <i class="fas fa-map-marker-alt me-1"></i>
                                                            <?php 
                                                                $location = array_filter([$user['city'], $user['country']]);
                                                                echo htmlspecialchars(implode(', ', $location));
                                                            ?>
                                                        <?php else: ?>
                                                            <span class="text-muted">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-center">
                                                        <div>
                                                            <strong class="text-primary"><?php echo $user['booking_count']; ?></strong>
                                                            <small class="text-muted d-block">bookings</small>
                                                        </div>
                                                        <?php if ($user['total_spent']): ?>
                                                            <small class="text-success">
                                                                ₹<?php echo number_format($user['total_spent'], 0); ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <span class="status-badge <?php echo $user['status']; ?>">
                                                            <?php echo ucfirst($user['status']); ?>
                                                        </span>
                                                        <?php if ($user['email_verified']): ?>
                                                            <br><small class="badge bg-primary mt-1">Verified</small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php echo date('M d, Y', strtotime($user['created_at'])); ?>
                                                        <br>
                                                        <small class="text-muted"><?php echo date('g:i A', strtotime($user['created_at'])); ?></small>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm">
                                                            <a href="?action=toggle_status&id=<?php echo $user['id']; ?>" 
                                                               class="btn <?php echo $user['status'] === 'active' ? 'btn-warning' : 'btn-success'; ?>" 
                                                               title="<?php echo $user['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>"
                                                               onclick="return confirm('Are you sure you want to <?php echo $user['status'] === 'active' ? 'deactivate' : 'activate'; ?> this user?')">
                                                                <i class="fas <?php echo $user['status'] === 'active' ? 'fa-pause' : 'fa-play'; ?>"></i>
                                                            </a>
                                                            <a href="?action=delete&id=<?php echo $user['id']; ?>" 
                                                               class="btn btn-outline-danger" 
                                                               title="Delete User"
                                                               onclick="return confirm('Are you sure you want to delete this user? This will also delete their bookings.')">
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
                    
                    <!-- Quick Stats -->
                    <div class="row mt-4">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">User Statistics</h5>
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="text-center">
                                                <h3 class="text-primary"><?php echo count($users); ?></h3>
                                                <small>Total Users</small>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="text-center">
                                                <h3 class="text-success">
                                                    <?php echo count(array_filter($users, fn($u) => $u['status'] === 'active')); ?>
                                                </h3>
                                                <small>Active Users</small>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="text-center">
                                                <h3 class="text-info">
                                                    <?php echo count(array_filter($users, fn($u) => $u['booking_count'] > 0)); ?>
                                                </h3>
                                                <small>Users with Bookings</small>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="text-center">
                                                <?php 
                                                $new_users = count(array_filter($users, fn($u) => strtotime($u['created_at']) > strtotime('-30 days')));
                                                ?>
                                                <h3 class="text-warning"><?php echo $new_users; ?></h3>
                                                <small>New Users (30 days)</small>
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

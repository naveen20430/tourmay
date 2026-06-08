<?php
require_once '../config/config.php';
requireLogin();

// Handle delete action
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = intval($_GET['delete']);
    try {
        $db->query("DELETE FROM search_queries WHERE id = ?", [$id]);
        $_SESSION['success'] = "Search query deleted successfully";
    } catch (Exception $e) {
        $_SESSION['error'] = "Failed to delete search query: " . $e->getMessage();
    }
    header('Location: search-queries.php');
    exit;
}

// Pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Filters
$filterDestination = isset($_GET['destination']) ? $_GET['destination'] : '';
$filterPhone = isset($_GET['phone']) ? trim($_GET['phone']) : '';
$filterDateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$filterDateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build query
$whereConditions = [];
$params = [];

if ($filterDestination) {
    $whereConditions[] = "destination_slug = ?";
    $params[] = $filterDestination;
}

if ($filterPhone) {
    $whereConditions[] = "phone LIKE ?";
    $params[] = "%{$filterPhone}%";
}

if ($filterDateFrom) {
    $whereConditions[] = "DATE(created_at) >= ?";
    $params[] = $filterDateFrom;
}

if ($filterDateTo) {
    $whereConditions[] = "DATE(created_at) <= ?";
    $params[] = $filterDateTo;
}

$whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

// Get total count
try {
    $totalQueries = $db->fetch("SELECT COUNT(*) as count FROM search_queries $whereClause", $params)['count'] ?? 0;
} catch (Exception $e) {
    $totalQueries = 0;
}

$totalPages = ceil($totalQueries / $perPage);

// Get search queries
try {
    $queries = $db->fetchAll("
        SELECT sq.*, d.name as destination_name 
        FROM search_queries sq
        LEFT JOIN destinations d ON sq.destination_slug = d.slug
        $whereClause
        ORDER BY sq.created_at DESC 
        LIMIT ? OFFSET ?
    ", array_merge($params, [$perPage, $offset]));
} catch (Exception $e) {
    $queries = [];
    $error = $e->getMessage();
}

// Get all destinations for filter dropdown
try {
    $destinations = $db->fetchAll("SELECT slug, name FROM destinations WHERE status = 'active' ORDER BY name");
} catch (Exception $e) {
    $destinations = [];
}

// Get statistics
try {
    $stats = [
        'total' => $db->fetch("SELECT COUNT(*) as count FROM search_queries")['count'] ?? 0,
        'today' => $db->fetch("SELECT COUNT(*) as count FROM search_queries WHERE DATE(created_at) = CURDATE()")['count'] ?? 0,
        'this_week' => $db->fetch("SELECT COUNT(*) as count FROM search_queries WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")['count'] ?? 0,
        'this_month' => $db->fetch("SELECT COUNT(*) as count FROM search_queries WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")['count'] ?? 0,
    ];
} catch (Exception $e) {
    $stats = ['total' => 0, 'today' => 0, 'this_week' => 0, 'this_month' => 0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Queries - Admin Panel</title>
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
        .card { 
            border: none; 
            border-radius: 15px; 
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
        }
        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
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
                    <a class="nav-link active" href="search-queries.php">
                        <i class="fas fa-search me-2"></i> Search Queries
                        <?php if ($stats['today'] > 0): ?>
                            <span class="badge bg-success ms-1"><?php echo $stats['today']; ?></span>
                        <?php endif; ?>
                    </a>
                    <a class="nav-link" href="cab_pricing.php">
                        <i class="fas fa-car me-2"></i> Cab Pricing
                    </a>
                    <a class="nav-link" href="blog.php">
                        <i class="fas fa-blog me-2"></i> Blog Posts
                    </a>
                    <a class="nav-link" href="users.php">
                        <i class="fas fa-users me-2"></i> Users
                    </a>
                    <a class="nav-link" href="contacts.php">
                        <i class="fas fa-envelope me-2"></i> Contact Messages
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
                <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
                    <div class="container-fluid">
                        <span class="navbar-brand mb-0 h1">Search Queries</span>
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

                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <!-- Statistics Cards -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card stats-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-search fa-2x mb-2"></i>
                                    <h3><?php echo $stats['total']; ?></h3>
                                    <p class="mb-0">Total Queries</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stats-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                                <div class="card-body text-center">
                                    <i class="fas fa-calendar-day fa-2x mb-2"></i>
                                    <h3><?php echo $stats['today']; ?></h3>
                                    <p class="mb-0">Today</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stats-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                                <div class="card-body text-center">
                                    <i class="fas fa-calendar-week fa-2x mb-2"></i>
                                    <h3><?php echo $stats['this_week']; ?></h3>
                                    <p class="mb-0">This Week</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stats-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                                <div class="card-body text-center">
                                    <i class="fas fa-calendar-alt fa-2x mb-2"></i>
                                    <h3><?php echo $stats['this_month']; ?></h3>
                                    <p class="mb-0">This Month</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Filters -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <form method="GET" action="">
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <label class="form-label">Destination</label>
                                        <select name="destination" class="form-select">
                                            <option value="">All Destinations</option>
                                            <?php foreach ($destinations as $dest): ?>
                                                <option value="<?php echo htmlspecialchars($dest['slug']); ?>" 
                                                    <?php echo $filterDestination === $dest['slug'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($dest['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Phone Number</label>
                                        <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($filterPhone); ?>" placeholder="Search by phone">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Date From</label>
                                        <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($filterDateFrom); ?>">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Date To</label>
                                        <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($filterDateTo); ?>">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">&nbsp;</label>
                                        <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter"></i> Filter</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Search Queries Table -->
                    <div class="card">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Search Queries (<?php echo $totalQueries; ?> total)</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>ID</th>
                                            <th>From</th>
                                            <th>To</th>
                                            <th>Travel Date</th>
                                            <th>Return Date</th>
                                            <th>Phone</th>
                                            <th>IP Address</th>
                                            <th>Created At</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($queries)): ?>
                                            <tr>
                                                <td colspan="9" class="text-center py-4">
                                                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                                    <p class="text-muted">No search queries found</p>
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($queries as $query): ?>
                                                <tr>
                                                    <td><?php echo $query['id']; ?></td>
                                                    <td><?php echo htmlspecialchars($query['from_location'] ?: '-'); ?></td>
                                                    <td><?php echo htmlspecialchars($query['destination_name'] ?: '-'); ?></td>
                                                    <td><?php echo $query['travel_date'] ? date('M d, Y', strtotime($query['travel_date'])) : '-'; ?></td>
                                                    <td><?php echo $query['return_date'] ? date('M d, Y', strtotime($query['return_date'])) : '-'; ?></td>
                                                    <td>
                                                        <a href="tel:<?php echo htmlspecialchars($query['phone']); ?>" class="text-decoration-none">
                                                            <i class="fas fa-phone"></i> <?php echo htmlspecialchars($query['phone']); ?>
                                                        </a>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($query['ip_address']); ?></td>
                                                    <td><?php echo date('M d, Y H:i', strtotime($query['created_at'])); ?></td>
                                                    <td>
                                                        <a href="?delete=<?php echo $query['id']; ?>" 
                                                           class="btn btn-sm btn-danger"
                                                           onclick="return confirm('Are you sure you want to delete this query?')">
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
                        
                        <?php if ($totalPages > 1): ?>
                            <div class="card-footer bg-white">
                                <nav>
                                    <ul class="pagination justify-content-center mb-0">
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo $filterDestination ? '&destination=' . urlencode($filterDestination) : ''; ?><?php echo $filterPhone ? '&phone=' . urlencode($filterPhone) : ''; ?>">Previous</a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo $filterDestination ? '&destination=' . urlencode($filterDestination) : ''; ?><?php echo $filterPhone ? '&phone=' . urlencode($filterPhone) : ''; ?>"><?php echo $i; ?></a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <?php if ($page < $totalPages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo $filterDestination ? '&destination=' . urlencode($filterDestination) : ''; ?><?php echo $filterPhone ? '&phone=' . urlencode($filterPhone) : ''; ?>">Next</a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
require_once 'config/config.php';

// Get search and filter parameters
$search = $_GET['search'] ?? '';
$country = $_GET['country'] ?? '';
$popular = $_GET['popular'] ?? '';

// Build query
$where_conditions = ['status = "active"'];
$params = [];

if ($search) {
    $where_conditions[] = '(name LIKE ? OR description LIKE ? OR country LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($country) {
    $where_conditions[] = 'country = ?';
    $params[] = $country;
}

if ($popular) {
    $where_conditions[] = 'popular = 1';
}

$where_clause = implode(' AND ', $where_conditions);

// Get destinations
$destinations_query = "
    SELECT * FROM destinations 
    WHERE $where_clause
    ORDER BY popular DESC, created_at DESC
";

$destinations = $db->fetchAll($destinations_query, $params);

// Get countries for filter
$countries = $db->fetchAll("SELECT DISTINCT country FROM destinations WHERE status = 'active' ORDER BY country");

// Set page variables
$page_title = 'Destinations - ' . getSetting('site_name');
$current_page = 'destinations';

// Include header
include 'includes/header.php';
?>

    <!-- Page Header -->
    <section class="page-header">
        <div class="page-header__bg"></div>
        <div class="container">
            <h2 class="page-header__title">Travel Destinations</h2>
            <ul class="travhub-breadcrumb list-unstyled">
                <li><a href="<?php echo navUrl('home'); ?>">Home</a></li>
                <li>Destinations</li>
            </ul>
        </div>
    </section>

    <!-- Filters Section -->
    <section class="filter-section" style="background: #f8f9fa; padding: 30px 0; margin-bottom: 40px;">
        <div class="container">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <input type="text" class="form-control" name="search" placeholder="Search destinations..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-3">
                    <select name="country" class="form-control">
                        <option value="">All Countries</option>
                        <?php foreach ($countries as $countryOption): ?>
                            <option value="<?php echo htmlspecialchars($countryOption['country']); ?>" <?php echo $country == $countryOption['country'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($countryOption['country']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="popular" class="form-control">
                        <option value="">All</option>
                        <option value="1" <?php echo $popular == '1' ? 'selected' : ''; ?>>Popular Only</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
                <div class="col-md-1">
                    <a href="<?php echo navUrl('destinations'); ?>" class="btn btn-outline-secondary w-100">Clear</a>
                </div>
            </form>
        </div>
    </section>

    <!-- Destinations Grid -->
    <section class="destinations-grid section-space">
        <div class="container">
            <div class="row">
                <div class="col-lg-12">
                    <div class="section-title text-center">
                        <span class="section-title__tagline">Explore The World</span>
                        <h2 class="section-title__title">Amazing Destinations</h2>
                        <p class="section-title__text">
                            Discover breathtaking destinations around the globe and create unforgettable memories
                        </p>
                    </div>
                </div>
            </div>
            
            <?php if (empty($destinations)): ?>
                <div class="row">
                    <div class="col-12 text-center">
                        <div class="alert alert-info">
                            <h4>No destinations found</h4>
                            <p>Try adjusting your search criteria or browse all destinations.</p>
                            <a href="<?php echo navUrl('destinations'); ?>" class="btn btn-primary">View All Destinations</a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($destinations as $destination): ?>
                        <div class="col-lg-4 col-md-6 mb-5 scroll-reveal" style="transition-delay: <?php echo array_search($destination, $destinations) * 0.15; ?>s;">
                            <div class="card h-100" style="border: none; border-radius: 25px; overflow: hidden; box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1); transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); position: relative; background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);">
                                <!-- Light hover overlay -->
                                <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: linear-gradient(135deg, rgba(102, 126, 234, 0.08) 0%, rgba(118, 75, 162, 0.08) 100%); opacity: 0; transition: all 0.3s ease; z-index: 1; border-radius: 25px; display: flex; align-items: center; justify-content: center;">
                                    <div style="text-align: center; color: #667eea; transform: translateY(20px); transition: all 0.3s ease;">
                                        <i class="fas fa-plane" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>
                                        <p style="font-weight: 600; margin: 0;">Explore Destination</p>
                                    </div>
                                </div>
                                
                                <div class="position-relative" style="overflow: hidden;">
                                    <img src="<?php echo $destination['featured_image'] ?: 'assets/images/destinations/default.jpg'; ?>" 
                                         class="card-img-top" style="height: 280px; object-fit: cover; transition: transform 0.4s ease;" 
                                         alt="<?php echo htmlspecialchars($destination['name']); ?>">
                                    
                                    <!-- Gradient overlay for better text readability -->
                                    <div style="position: absolute; bottom: 0; left: 0; right: 0; height: 120px; background: linear-gradient(transparent, rgba(0,0,0,0.8)); z-index: 2;"></div>
                                    
                                    <?php if ($destination['popular']): ?>
                                        <div class="position-absolute top-0 start-0 m-3" style="z-index: 2;">
                                            <span class="badge" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 6px 12px; border-radius: 15px; font-weight: 500; box-shadow: 0 4px 15px rgba(118, 75, 162, 0.4);">✨ Popular</span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="position-absolute bottom-0 start-0 end-0 p-3" style="z-index: 3;">
                                        <div style="display: flex; justify-content: space-between; align-items: end;">
                                            <div>
                                                <h5 class="text-white mb-1" style="font-weight: 700; font-size: 1.3rem; text-shadow: 0 2px 10px rgba(0,0,0,0.5);"><?php echo htmlspecialchars($destination['name']); ?></h5>
                                                <small class="text-light" style="font-size: 0.9rem; opacity: 0.9;">
                                                    <i class="fas fa-map-marker-alt me-2" style="color: #28a745;"></i>
                                                    <?php echo htmlspecialchars($destination['country']); ?>
                                                </small>
                                            </div>
                                            <div>
                                                <span class="badge" style="background: rgba(255, 255, 255, 0.2); backdrop-filter: blur(10px); color: white; padding: 5px 10px; border-radius: 15px; font-size: 0.75rem;">🌍 Destination</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="card-body d-flex flex-column" style="padding: 25px; position: relative; z-index: 2; background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%); border-radius: 0 0 25px 25px;">
                                    <p class="card-text flex-grow-1" style="color: #6c757d; font-size: 0.95rem; line-height: 1.6; margin-bottom: 20px; height: 60px; overflow: hidden;">
                                        <?php echo substr(htmlspecialchars($destination['short_description']), 0, 85); ?>...
                                    </p>
                                    
                                    <?php if ($destination['best_time_to_visit']): ?>
                                        <div class="mb-3" style="display: flex; align-items: center; padding: 10px; background: rgba(40, 167, 69, 0.1); border-radius: 10px;">
                                            <i class="fas fa-calendar me-2" style="color: #28a745;"></i>
                                            <small style="color: #28a745; font-weight: 500;">
                                                Best Time: <?php echo htmlspecialchars($destination['best_time_to_visit']); ?>
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="d-grid gap-2 mt-auto">
                                        <a href="<?php echo destinationUrl($destination['slug']); ?>" class="travhub-btn" style="padding: 12px; font-size: 0.95rem; font-weight: 600;">
                                            <span>👁️ View Details</span>
                                        </a>
                                        <a href="<?php echo toursUrl(['destination' => $destination['slug']]); ?>" class="btn" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; border: none; border-radius: 15px; padding: 10px; font-weight: 500; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(40, 167, 69, 0.2);">
                                            <span>🗺️ View Tours</span>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="row mt-5">
                    <div class="col-12 text-center">
                        <p class="text-muted">Showing <?php echo count($destinations); ?> destinations</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <style>
    .card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 30px rgba(0,0,0,0.1) !important;
    }
    .filter-section {
        border-radius: 10px;
    }
    .destinations-grid .card {
        border: none;
    }
    </style>

<?php include 'includes/footer.php'; ?>

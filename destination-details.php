<?php
require_once 'config/config.php';

// Get destination slug from URL
$slug = $_GET['slug'] ?? '';

if (!$slug) {
    header('Location: destinations.php');
    exit;
}

// Get destination details
$destination = $db->fetch("
    SELECT * FROM destinations 
    WHERE slug = ? AND status = 'active'
", [$slug]);

if (!$destination) {
    header('HTTP/1.0 404 Not Found');
    include '404.php';
    exit;
}

// Get tours for this destination
$destination_tours = $db->fetchAll("
    SELECT t.*, d.name as destination_name 
    FROM tours t
    LEFT JOIN destinations d ON t.destination_id = d.id
    WHERE t.destination_id = ? AND t.status = 'active'
    ORDER BY t.featured DESC, t.created_at DESC
    LIMIT 6
", [$destination['id']]);

// Set page variables
$page_title = htmlspecialchars($destination['name']) . ' - ' . getSetting('site_name');
$current_page = 'destinations';

// Include header
include 'includes/header.php';
?>

<div class="container py-5">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo navUrl('home'); ?>">Home</a></li>
            <li class="breadcrumb-item"><a href="<?php echo navUrl('destinations'); ?>">Destinations</a></li>
            <li class="breadcrumb-item active"><?php echo htmlspecialchars($destination['name']); ?></li>
        </ol>
    </nav>

    <!-- Destination Hero -->
    <div class="row mb-5">
        <div class="col-12">
            <div class="position-relative rounded overflow-hidden" style="height: 400px;">
                <?php if ($destination['featured_image']): ?>
                    <img src="<?php echo htmlspecialchars($destination['featured_image']); ?>" 
                         class="w-100 h-100" style="object-fit: cover;" 
                         alt="<?php echo htmlspecialchars($destination['name']); ?>">
                <?php else: ?>
                    <div class="bg-gradient-primary w-100 h-100 d-flex align-items-center justify-content-center">
                        <h2 class="text-white"><?php echo htmlspecialchars($destination['name']); ?></h2>
                    </div>
                <?php endif; ?>
                
                <div class="position-absolute bottom-0 start-0 end-0 p-4" 
                     style="background: linear-gradient(transparent, rgba(0,0,0,0.8));">
                    <h1 class="text-white mb-2"><?php echo htmlspecialchars($destination['name']); ?></h1>
                    <p class="text-light mb-0">
                        <i class="fas fa-map-marker-alt me-2"></i>
                        <?php echo htmlspecialchars($destination['country']); ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8 mb-5">
            <!-- Destination Description -->
            <div class="mb-5">
                <h3 class="mb-3">About <?php echo htmlspecialchars($destination['name']); ?></h3>
                <?php if ($destination['short_description']): ?>
                    <p class="lead text-muted"><?php echo htmlspecialchars($destination['short_description']); ?></p>
                <?php endif; ?>
                
                <?php if ($destination['description']): ?>
                    <div class="destination-description">
                        <?php echo nl2br(htmlspecialchars($destination['description'])); ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Tours in This Destination -->
            <?php if (!empty($destination_tours)): ?>
                <div class="destination-tours">
                    <h3 class="mb-4">Tours in <?php echo htmlspecialchars($destination['name']); ?></h3>
                    <div class="row">
                        <?php foreach ($destination_tours as $tour): ?>
                            <div class="col-md-6 mb-4">
                                <div class="card h-100 border-0 shadow-sm">
                                    <div class="position-relative">
                                        <img src="<?php echo $tour['featured_image'] ?: 'assets/images/tours/default.jpg'; ?>" 
                                             class="card-img-top" 
                                             style="height: 200px; object-fit: cover;"
                                             alt="<?php echo htmlspecialchars($tour['title']); ?>">
                                        
                                        <?php if ($tour['featured']): ?>
                                            <div class="position-absolute top-0 start-0 m-3">
                                                <span class="badge bg-danger">Featured</span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="position-absolute top-0 end-0 m-3">
                                            <span class="badge bg-primary">
                                                ₹<?php echo number_format($tour['price'], 0); ?>
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="card-body">
                                        <h6 class="card-title">
                                            <a href="<?php echo tourUrl($tour['slug']); ?>" class="text-decoration-none">
                                                <?php echo htmlspecialchars($tour['title']); ?>
                                            </a>
                                        </h6>
                                        <p class="card-text text-muted small">
                                            <?php echo substr(htmlspecialchars($tour['short_description']), 0, 100); ?>...
                                        </p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small class="text-muted">
                                                <i class="fas fa-clock me-1"></i>
                                                <?php echo $tour['duration_days']; ?> Days
                                            </small>
                                            <small class="text-muted">
                                                <i class="fas fa-users me-1"></i>
                                                Max <?php echo $tour['max_people']; ?>
                                            </small>
                                        </div>
                                    </div>
                                    <div class="card-footer bg-transparent border-top-0 pt-0">
                                        <a href="<?php echo tourUrl($tour['slug']); ?>" class="btn btn-outline-primary btn-sm w-100">
                                            View Details
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="text-center mt-4">
                        <a href="<?php echo toursUrl(['destination' => $destination['slug']]); ?>" class="btn btn-primary">
                            View All Tours in <?php echo htmlspecialchars($destination['name']); ?>
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-lg-4">
            <!-- Destination Info -->
            <div class="card mb-4">
                <div class="card-body">
                    <h6 class="card-title">Destination Info</h6>
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2">
                            <i class="fas fa-globe me-2 text-primary"></i>
                            <strong>Country:</strong> <?php echo htmlspecialchars($destination['country']); ?>
                        </li>
                        <?php if ($destination['best_time_to_visit']): ?>
                            <li class="mb-2">
                                <i class="fas fa-calendar me-2 text-primary"></i>
                                <strong>Best Time:</strong> <?php echo htmlspecialchars($destination['best_time_to_visit']); ?>
                            </li>
                        <?php endif; ?>
                        <?php if ($destination['currency']): ?>
                            <li class="mb-2">
                                <i class="fas fa-money-bill me-2 text-primary"></i>
                                <strong>Currency:</strong> <?php echo htmlspecialchars($destination['currency']); ?>
                            </li>
                        <?php endif; ?>
                        <?php if ($destination['language']): ?>
                            <li class="mb-2">
                                <i class="fas fa-language me-2 text-primary"></i>
                                <strong>Language:</strong> <?php echo htmlspecialchars($destination['language']); ?>
                            </li>
                        <?php endif; ?>
                        <li class="mb-2">
                            <i class="fas fa-map me-2 text-primary"></i>
                            <strong>Available Tours:</strong> <?php echo count($destination_tours); ?>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Popular Destinations -->
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title">Popular Destinations</h6>
                    <?php
                    $popular_destinations = $db->fetchAll("
                        SELECT name, slug, featured_image FROM destinations 
                        WHERE popular = 1 AND status = 'active' AND id != ?
                        ORDER BY created_at DESC LIMIT 5
                    ", [$destination['id']]);
                    
                    if ($popular_destinations):
                    ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($popular_destinations as $popular): ?>
                                <a href="<?php echo destinationUrl($popular['slug']); ?>" 
                                   class="list-group-item list-group-item-action border-0 px-0">
                                    <div class="d-flex align-items-center">
                                        <?php if ($popular['featured_image']): ?>
                                            <img src="<?php echo htmlspecialchars($popular['featured_image']); ?>" 
                                                 class="rounded me-3" 
                                                 style="width: 50px; height: 50px; object-fit: cover;"
                                                 alt="<?php echo htmlspecialchars($popular['name']); ?>">
                                        <?php endif; ?>
                                        <div>
                                            <?php echo htmlspecialchars($popular['name']); ?>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

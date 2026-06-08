<!-- Destinations & Cab Facilities Section -->
<!-- Destinations & Cab Facilities Section -->
<section id="destinations-cab-section" class="destinations-cab-section" style="background: #f8f9fa;">
    <div class="container">
        <div class="row destinations-cab-layout">
            <!-- 80% - Destinations & Tours -->
            <div class="col-lg-6 col-md-12 mb-4 destinations-cab-main">
                <?php 
                // DEBUG: Check total destinations
                $total_destinations = $db->fetchAll("
                    SELECT COUNT(*) as total 
                    FROM destinations 
                    WHERE status = 'active'
                ");
                // echo "<!-- Total Active Destinations: " . $total_destinations[0]['total'] . " -->";
                
                // Fetch only popular active destinations with tour count
                $destinations_list = $db->fetchAll("
                    SELECT d.*, COUNT(t.id) as tour_count
                    FROM destinations d
                    LEFT JOIN tours t ON d.id = t.destination_id AND t.status = 'active'
                    WHERE d.status = 'active' AND d.popular = 1
                    GROUP BY d.id
                    HAVING tour_count > 0
                    ORDER BY 
                        CASE 
                            WHEN LOWER(d.name) LIKE '%shila%' OR LOWER(d.name) LIKE '%shimla%' THEN 1
                            WHEN LOWER(d.name) LIKE '%manali%' THEN 2
                            WHEN LOWER(d.name) LIKE '%dharmashala%' OR LOWER(d.name) LIKE '%dharamshala%' THEN 3
                            ELSE 4
                        END,
                        d.created_at DESC
                ");

                // DEBUG: Output what we found
                // echo "<!-- Destinations with tours found: " . count($destinations_list) . " -->";
                
                if (empty($destinations_list)) {
                    echo "<div style='padding: 40px; text-align: center; background: white; border-radius: 10px;'>";
                    echo "<p style='color:#888; font-size: 1.1rem;'>No destinations with active tours found.</p>";
                    echo "</div>";
                } else {
                    // Display destination count for debugging
                    // echo "<!-- Showing " . count($destinations_list) . " destinations -->";
                }

                foreach ($destinations_list as $index => $dest):
                    // DEBUG: Output current destination
                    // echo "<!-- Processing: " . htmlspecialchars($dest['name']) . " (ID: " . $dest['id'] . ") -->";
                    
                    // Fetch active tours for each destination
                    $destination_tours = $db->fetchAll("
                        SELECT t.* 
                        FROM tours t 
                        WHERE t.destination_id = ? AND t.status = 'active'
                        ORDER BY t.featured DESC, t.popular DESC, t.created_at DESC 
                        LIMIT 2
                    ", [$dest['id']]);

                    // DEBUG: Output tour count
                    // echo "<!-- Tours found for " . htmlspecialchars($dest['name']) . ": " . count($destination_tours) . " -->";
                    
                    // Skip destinations with no active tours (shouldn't happen now due to HAVING clause)
                    if (empty($destination_tours)) {
                        // echo "<!-- Skipping " . htmlspecialchars($dest['name']) . " - No tours -->";
                        continue;
                    }
                ?>
                
                <!-- Destination Section -->
                <div class="destination-section mb-5" style="<?php echo $index > 0 ? 'margin-top: 50px;' : ''; ?>">
                    <!-- Destination Header -->
                   <div class="destination-header mb-3" style="display: flex; justify-content: space-between; align-items: center; gap: 20px;">
                        <div style="display: flex; gap: 10px; align-items: center; justify-content: flex-start;">
                            <i class="fas fa-map-marker-alt" style="color: #667eea; font-size: 1.8rem;"></i>
                            <h1 style="font-size: 2rem; font-weight: 700; color: #333; margin: 0;">
                                <?php echo htmlspecialchars($dest['name']); ?> Tours
                                <small style="font-size: 0.6em; color: #999; font-weight: 400;">(<?php echo count($destination_tours); ?> tours)</small>
                            </h1>
                        </div>
                        <a href="<?php echo toursUrl(['destination' => $dest['slug']]); ?>" style="background: linear-gradient(135deg, #764ba2 0%, #667eea 100%); color: #fff; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; padding: 10px 16px; border-radius: 8px; line-height: 1; white-space: nowrap;">
                            View all <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                    
                    <!-- Tours Slider -->
                    <div class="owl-carousel owl-theme tours-carousel-<?php echo $dest['id']; ?>">
                            <?php foreach ($destination_tours as $tour): ?>
                            <div class="item">
                                <div class="card" style="border: none; border-radius: 0; overflow: hidden; box-shadow: 0 5px 20px rgba(0,0,0,0.1); transition: all 0.3s ease; position: relative;">
                                    
                                    <!-- Featured Badge -->
                                    <?php if (!empty($tour['featured']) && $tour['featured'] == 1): ?>
                                    <div style="position: absolute; top: 15px; left: 15px; z-index: 10;">
                                        <span class="badge" style="background: linear-gradient(135deg, #f09433 0%, #e6683c 100%); color: white; padding: 8px 15px; border-radius: 20px; font-size: 0.75rem; font-weight: 600;">Featured</span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <!-- Price Badge -->
                                    <div style="position: absolute; top: 15px; right: 15px; z-index: 10;">
                                        <div style="background: rgba(0,0,0,0.8); color: white; padding: 8px 15px; border-radius: 15px; font-weight: 600;">
                                            <?php if (!empty($tour['discount_price']) && $tour['discount_price'] < $tour['price']): ?>
                                                <div style="font-size: 0.7rem; text-decoration: line-through; opacity: 0.7;"><?php echo formatPriceINR($tour['price']); ?></div>
                                                <div style="font-size: 0.95rem;"><?php echo formatPriceINR($tour['discount_price']); ?></div>
                                            <?php else: ?>
                                                <div style="font-size: 0.95rem;"><?php echo formatPriceINR($tour['price']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <!-- Tour Image -->
                                    <img src="<?php echo BASE_URL . ($tour['featured_image'] ?: 'assets/images/tours/default.jpg'); ?>" class="card-img-top" style="height: 220px; object-fit: cover;" alt="<?php echo htmlspecialchars($tour['title']); ?>">
                                    
                                    <!-- Card Body -->
                                    <div class="card-body" style="padding: 20px;">
                                        <p style="color: #667eea; font-size: 0.85rem; margin-bottom: 8px; display: flex; align-items: center; gap: 5px;">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <?php echo htmlspecialchars($dest['name']); ?>
                                        </p>
                                        
                                        <h5 class="card-title" style="font-weight: 700; margin-bottom: 10px; font-size: 1.1rem; color: #333; min-height: 50px;">
                                            <?php echo htmlspecialchars($tour['title']); ?>
                                        </h5>
                                        
                                        <p class="card-text" style="color: #6c757d; font-size: 0.85rem; margin-bottom: 15px; line-height: 1.5; min-height: 40px;">
                                            <?php echo htmlspecialchars(substr($tour['short_description'], 0, 80)); ?>...
                                        </p>
                                        
                                        <div style="display: flex; gap: 10px; margin-bottom: 15px; flex-wrap: wrap;">
                                            <span style="background: #f0f4ff; color: #667eea; padding: 5px 12px; border-radius: 15px; font-size: 0.75rem; font-weight: 600;">
                                                <i class="fas fa-clock"></i> <?php echo (int)$tour['duration_days']; ?> Days
                                            </span>
                                            <?php if (!empty($tour['difficulty_level'])): ?>
                                            <span style="background: #f0f4ff; color: #667eea; padding: 5px 12px; border-radius: 15px; font-size: 0.75rem; font-weight: 600;">
                                                <?php echo htmlspecialchars($tour['difficulty_level']); ?>
                                            </span>
                                            <?php endif; ?>
                                            <?php if (!empty($tour['tour_type'])): ?>
                                            <span style="background: #f0f4ff; color: #667eea; padding: 5px 12px; border-radius: 15px; font-size: 0.75rem; font-weight: 600;">
                                                <?php echo htmlspecialchars($tour['tour_type']); ?>
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <a href="<?php echo tourUrl($tour['slug']); ?>" class="btn w-100" style="background: linear-gradient(135deg, #764ba2 0%, #667eea 100%) !important; color: white; border: none; border-radius: 0; padding: 12px; font-weight: 600;">
                                            View  Tour Details
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                    </div>
                    
                    <!-- View All Tours Link -->
                   
                </div>
                
                <?php endforeach; ?>
            </div>
            
            <!-- 20% - Cab Routes Sidebar -->
           <div class="col-lg-3 col-md-12 destinations-cab-sidebar">
                <?php include 'cab_sidebar.php'; ?>
            </div>
        </div>
    </div>
</section>

<!-- OwlCarousel CSS -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.carousel.min.css" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.theme.default.min.css" />

<style>
/* Keep Tours + Transport Facilities side-by-side on desktop */
.destinations-cab-section .destinations-cab-layout {
    display: flex !important;
    flex-wrap: wrap;
    align-items: flex-start;
}

@media (min-width: 992px) {
    .destinations-cab-section .destinations-cab-layout {
        flex-wrap: nowrap;
    }

    .destinations-cab-section .destinations-cab-main {
        flex: 0 0 75%;
        max-width: 75%;
    }

    .destinations-cab-section .destinations-cab-sidebar {
        flex: 0 0 25%;
        max-width: 25%;
    }
}

/* OwlCarousel Container */
.destinations-cab-section .owl-carousel {
   display: flex;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 24px;
    margin: 0;
}

.destinations-cab-section .owl-carousel .item {
    padding: 0;
}

@media (max-width: 991px) {
    .destinations-cab-section .owl-carousel {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 575px) {
    .destinations-cab-section .owl-carousel {
        grid-template-columns: 1fr;
    }
}

.destinations-cab-section .owl-carousel .card {
    display: flex !important;
    flex-direction: column !important;
    border: none !important;
    border-radius: 0 !important;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1) !important;
    transition: all 0.3s ease !important;
    overflow: hidden !important;
    background: white !important;
    height: 100%;
}

.destinations-cab-section .owl-carousel .card:hover {
    transform: translateY(-5px) !important;
    box-shadow: 0 10px 30px rgba(102, 126, 234, 0.2) !important;
}

.destinations-cab-section .owl-carousel .card .card-body {
    flex: 1 !important;
    display: flex !important;
    flex-direction: column !important;
}

.destinations-cab-section .owl-carousel .card .btn {
    margin-top: auto !important;
}

/* Dots */
.owl-theme .owl-dots {
    margin-top: 30px;
}

.owl-theme .owl-dots .owl-dot span {
    background: #667eea;
}

.owl-theme .owl-dots .owl-dot.active span {
    background: #764ba2;
}

/* Navigation - Hidden */
.owl-theme .owl-nav {
    display: none !important;
}

@media (max-width: 991px) {
    .cab-routes-sidebar {
        position: relative !important;
        top: 0 !important;
        margin-top: 30px;
    }
}

/* Cab Route Button Hover Effect */
.cab-route-btn:hover {
    background: linear-gradient(135deg, #1bbc9b 0%, #17a689 100%) !important;
    transform: translateY(-2px) scale(1.02) !important;
    box-shadow: 0 5px 15px rgba(27, 188, 155, 0.4) !important;
}
</style>

<!-- jQuery (required for OwlCarousel) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- OwlCarousel JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/owl.carousel.min.js"></script>

<script>
jQuery(document).ready(function($) {
    console.log('Initializing OwlCarousel sliders...');
    
    // Wait for all images to load before initializing carousels
    $(window).on('load', function() {
        console.log('All images loaded, initializing carousels...');
        return;
        
        <?php foreach ($destinations_list as $dest_init): ?>
        var carousel<?php echo $dest_init['id']; ?> = $('.tours-carousel-<?php echo $dest_init['id']; ?>');
        
        if (carousel<?php echo $dest_init['id']; ?>.length) {
            console.log('Found carousel for destination <?php echo $dest_init['id']; ?>');
            
            carousel<?php echo $dest_init['id']; ?>.owlCarousel({
                loop: true,
                margin: 20,
                nav: false,
                dots: true,
                autoplay: true,
                autoplayTimeout: 4000,
                autoplayHoverPause: true,
                autoHeight: false,
                responsive: {
                    0: {
                        items: 1
                    },
                    768: {
                        items: 2
                    },
                    1024: {
                        items: 3
                    }
                },
                onInitialized: function() {
                    console.log('Carousel <?php echo $dest_init['id']; ?> initialized!');
                },
                onRefreshed: function() {
                    console.log('Carousel <?php echo $dest_init['id']; ?> refreshed!');
                }
            });
        } else {
            console.error('Carousel not found for destination <?php echo $dest_init['id']; ?>');
        }
        <?php endforeach; ?>
    });
});
</script>


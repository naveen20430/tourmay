<?php
/**
 * Tour Slider Helper Functions
 * Replaces hero section with dynamic tour slider
 */

/**
 * Enable tour slider CSS for the current page
 * Call this function before including header.php to load slider styles
 */
function enableTourSliderCSS() {
    global $extra_css;
    $extra_css = '<link rel="stylesheet" href="' . BASE_URL . 'assets/css/tour-slider.css">';
}

/**
 * Get featured tours for slider
 * @param int $limit Number of tours to fetch
 * @return array Tour data for slider
 */
function getTourSliderData($limit = 5) {
    global $db;
    
    try {
        // Get slider count from settings
        $slider_count_setting = $db->fetch("SELECT setting_value FROM site_settings WHERE setting_key = 'slider_slides_count'");
        if ($slider_count_setting) {
            $limit = intval($slider_count_setting['setting_value']);
        }
        
        // First try to get manually selected tours for slider
        $tours = $db->fetchAll("
            SELECT t.*, d.name as destination_name, d.country, d.city
            FROM tours t 
            LEFT JOIN destinations d ON t.destination_id = d.id 
            WHERE t.status = 'active' AND t.in_slider = 1
            ORDER BY t.slider_order ASC, t.featured DESC, t.popular DESC, t.created_at DESC 
            LIMIT ?
        ", [$limit]);
        
        // If no manually selected tours, get featured/popular tours
        if (empty($tours)) {
            $tours = $db->fetchAll("
                SELECT t.*, d.name as destination_name, d.country, d.city
                FROM tours t 
                LEFT JOIN destinations d ON t.destination_id = d.id 
                WHERE t.status = 'active' AND (t.featured = 1 OR t.popular = 1)
                ORDER BY t.featured DESC, t.popular DESC, t.created_at DESC 
                LIMIT ?
            ", [$limit]);
        }
        
        // If still no tours, get recent active tours
        if (empty($tours)) {
            $tours = $db->fetchAll("
                SELECT t.*, d.name as destination_name, d.country, d.city
                FROM tours t 
                LEFT JOIN destinations d ON t.destination_id = d.id 
                WHERE t.status = 'active'
                ORDER BY t.created_at DESC 
                LIMIT ?
            ", [$limit]);
        }
        
        return $tours;
    } catch (Exception $e) {
        // Return sample data if database error
        return getSampleTourData();
    }
}

/**
 * Get sample tour data for fallback
 * @return array Sample tour data
 */
function getSampleTourData() {
    return [
        [
            'id' => 1,
            'title' => 'Amazing Shimla Tour',
            'description' => 'Experience the beauty of Shimla with our premium tour package',
            'price' => 5000.00,
            'duration_days' => 3,
            'featured_image' => 'assets/images/tours/default.jpg',
            'destination_name' => 'Shimla',
            'country' => 'India'
        ],
        [
            'id' => 2,
            'title' => 'Manali Adventure',
            'description' => 'Thrilling adventure activities in the beautiful Manali',
            'price' => 7000.00,
            'duration_days' => 4,
            'featured_image' => 'assets/images/tours/default.jpg',
            'destination_name' => 'Manali',
            'country' => 'India'
        ],
        [
            'id' => 3,
            'title' => 'Dharamshala Retreat',
            'description' => 'Peaceful spiritual retreat in the mountains',
            'price' => 4500.00,
            'duration_days' => 3,
            'featured_image' => 'assets/images/tours/default.jpg',
            'destination_name' => 'Dharamshala',
            'country' => 'India'
        ]
    ];
}

/**
 * Render tour slider HTML
 * @param array $tours Tour data
 * @return string Tour slider HTML
 */
function renderTourSlider($tours) {
    if (empty($tours)) {
        return '';
    }
    
    global $db;
    
    // Get slider settings from database
    $slider_settings = [];
    try {
        $settings_result = $db->fetchAll("SELECT setting_key, setting_value FROM site_settings WHERE setting_key LIKE 'slider_%'");
        foreach ($settings_result as $setting) {
            $slider_settings[$setting['setting_key']] = $setting['setting_value'];
        }
    } catch (Exception $e) {
        // Use defaults if database error
    }
    
    // Set defaults
    $show_arrows = isset($slider_settings['slider_show_arrows']) ? ($slider_settings['slider_show_arrows'] == '1') : true;
    $show_dots = isset($slider_settings['slider_show_dots']) ? ($slider_settings['slider_show_dots'] == '1') : true;
    
    ob_start();
    ?>
    <!-- Tour Slider Section -->
    <section class="tour-slider-section">
        <div class="tour-slider-container">
            <div class="tour-slider-wrapper">
                <div class="owl-carousel owl-theme tour-slider" id="tourSlider">
                    <?php foreach ($tours as $tour): 
                        $image_path = !empty($tour['featured_image']) ? $tour['featured_image'] : 'assets/images/tours/default.jpg';
                        $price = isset($tour['price']) ? formatPriceINR($tour['price']) : 'Contact Us';
                        $duration = isset($tour['duration_days']) ? $tour['duration_days'] . ' Days' : '';
                        $destination = $tour['destination_name'] ?? $tour['city'] ?? 'Amazing Destination';
                    ?>
                    <div class="tour-slide-item">
                        <div class="tour-slide-bg" style="background-image: url('<?php echo BASE_URL . $image_path; ?>');">
                            <div class="tour-slide-overlay"></div>
                            <div class="tour-slide-content">
                                <div class="container">
                                    <div class="row align-items-center min-vh-100">
                                        <div class="col-lg-8 col-xl-7">
                                            <div class="tour-slide-text">
                                                <span class="tour-slide-category">
                                                    <i class="flaticon-pin-1"></i>
                                                    <?php echo htmlspecialchars($destination); ?>
                                                </span>
                                                
                                                <h1 class="tour-slide-title">
                                                    <?php echo htmlspecialchars($tour['title']); ?>
                                                </h1>
                                                
                                                <?php if (!empty($tour['description']) || !empty($tour['short_description'])): ?>
                                                <p class="tour-slide-description">
                                                    <?php 
                                                    $description = $tour['short_description'] ?? $tour['description'] ?? '';
                                                    echo htmlspecialchars(substr($description, 0, 150) . (strlen($description) > 150 ? '...' : ''));
                                                    ?>
                                                </p>
                                                <?php endif; ?>
                                                
                                                <div class="tour-slide-meta">
                                                    <?php if ($duration): ?>
                                                    <span class="tour-slide-duration">
                                                        <i class="flaticon-three-o-clock-clock"></i>
                                                        <?php echo $duration; ?>
                                                    </span>
                                                    <?php endif; ?>
                                                    
                                                    <span class="tour-slide-price">
                                                        <i class="flaticon-price-tag"></i>
                                                        Starting from <?php echo $price; ?>
                                                    </span>
                                                </div>
                                                
                                                <div class="tour-slide-buttons">
                                                    <a href="<?php echo BASE_URL; ?>tour-details.php?id=<?php echo $tour['id']; ?>" class="travhub-btn travhub-btn--primary">
                                                        <span>View Details</span>
                                                    </a>
                                                    <a href="<?php echo bookingUrl((int) $tour['id']); ?>" class="travhub-btn travhub-btn--outline">
                                                        <span>Book Now</span>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Navigation arrows -->
                <?php if ($show_arrows): ?>
           
                <?php endif; ?>
                
                <!-- Dots navigation -->
                <?php if ($show_dots): ?>
                <div class="tour-slider-dots">
                    <?php for($i = 0; $i < count($tours); $i++): ?>
                    <span class="tour-slider-dot <?php echo $i === 0 ? 'active' : ''; ?>" data-slide="<?php echo $i; ?>"></span>
                    <?php endfor; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
    <?php
    return ob_get_clean();
}

/**
 * Easy function to add tour slider to any page
 * Call this function after header to add tour slider section
 */
function displayTourSlider($limit = 5) {
    $tours = getTourSliderData($limit);
    echo renderTourSlider($tours);
}

/**
 * Display tour carousel with 3 slides visible at once
 * Perfect for homepage tour showcase
 */
function displayTourCarousel($limit = 9) {
    $tours = getTourSliderData($limit);
    if (empty($tours)) {
        return;
    }
    
    ?>
    <style>
    /* Tour Carousel Fixed Styles */
    .tour-carousel-container {
        position: relative;
        padding: 0 60px;
        overflow: hidden;
    }
    
    #tourCarousel {
        margin: 0;
    }
    
    .tour-carousel .owl-stage-outer {
        overflow: hidden;
        position: relative;
    }
    
    .tour-carousel .owl-stage {
        display: flex;
        align-items: stretch;
    }
    
    .tour-carousel .owl-item {
        opacity: 1;
        float: none;
        display: flex;
        align-items: stretch;
    }
    
    .tour-carousel .owl-item.active {
        opacity: 1;
    }
    
    .tour-carousel-item {
        display: flex;
        align-items: stretch;
        height: 100%;
        width: 100%;
        box-sizing: border-box;
    }
    
    .tour-carousel-item .card {
        width: 100%;
        height: 100%;
        display: flex;
        flex-direction: column;
        margin: 0;
    }
    
    /* Navigation Styles */
    .tour-carousel-nav {
        position: relative;
    }
    
    .tour-carousel-prev,
    .tour-carousel-next {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        width: 45px;
        height: 45px;
        background: #1bbc9b;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        z-index: 100;
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        transition: all 0.3s ease;
        opacity: 0.9;
    }
    
    .tour-carousel-prev {
        left: -22px;
    }
    
    .tour-carousel-next {
        right: -22px;
    }
    
    .tour-carousel-prev:hover,
    .tour-carousel-next:hover {
        transform: translateY(-50%) scale(1.1);
        opacity: 1;
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.6);
    }
    
    /* Perfect SVG icon centering */
    .tour-carousel-prev svg,
    .tour-carousel-next svg {
        display: block;
        margin: 0 auto;
        vertical-align: middle;
        flex-shrink: 0;
    }
    
    .tour-carousel-prev:hover svg,
    .tour-carousel-next:hover svg {
        transform: scale(1.1);
        transition: transform 0.2s ease;
    }
    
    /* Dots Styles */
    .tour-carousel .owl-dots {
        text-align: center;
        margin-top: 40px;
        padding: 0;
    }
    
    .tour-carousel .owl-dot {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: #ddd;
        margin: 0 6px;
        transition: all 0.3s ease;
        display: inline-block;
        cursor: pointer;
    }
    
    .tour-carousel .owl-dot.active,
    .tour-carousel .owl-dot:hover {
        background: #1bbc9b;
        transform: scale(1.3);
    }
    
    /* Responsive Fixes */
    @media (max-width: 1199px) {
        .tour-carousel-container {
            padding: 0 50px;
        }
    }
    
    @media (max-width: 991px) {
        .tour-carousel-container {
            padding: 0 40px;
        }
        
        .tour-carousel-prev {
            left: -18px;
        }
        
        .tour-carousel-next {
            right: -18px;
        }
    }
    
    @media (max-width: 767px) {
        .tour-carousel-container {
            padding: 0 15px;
        }
        
        .tour-carousel-nav {
            display: none !important;
        }
    }
    
    @media (max-width: 575px) {
        .tour-carousel-container {
            padding: 0 10px;
        }
        
        .tour-carousel-item .card {
            margin: 0 5px;
        }
    }
    
    /* Card Animation Enhancements */
    .tour-carousel-item .card {
        transform: translateZ(0);
        backface-visibility: hidden;
        will-change: transform, box-shadow;
    }
    
    .tour-carousel-item .card-img-top {
        backface-visibility: hidden;
        will-change: transform;
    }
    
    /* Improved dot navigation */
    .tour-carousel .owl-dots .owl-dot {
        outline: none;
        border: 2px solid transparent;
    }
    
    .tour-carousel .owl-dots .owl-dot:focus {
        border-color: rgba(79, 70, 229, 0.5);
    }
    </style>
    
    <!-- Tour Carousel Section -->
    <section class="tour-carousel-section section-space" style="background: #f8f9fa; position: relative;">
        <div class="container">
            <div class="section-title text-center scroll-reveal" style="margin-bottom: 60px;">
                <div style="margin-bottom: 15px;">
                    <span class="badge" style="background: #1bbc9b; color: white; padding: 8px 16px; border-radius: 20px; font-size: 0.9rem;">
                        🌟 Featured Tours
                    </span>
                </div>
                <h2 class="gradient-text" style="font-size: 2.8rem; font-weight: 700; margin-bottom: 20px;">Discover Amazing Destinations</h2>
                <p style="font-size: 1.1rem; color: #6c757d; max-width: 500px; margin: 0 auto; line-height: 1.6;">
                    Explore our handpicked selection of the most popular tours and destinations
                </p>
                <div style="width: 80px; height: 4px; background: #1bbc9b; margin: 20px auto 0; border-radius: 2px;"></div>
            </div>
            
            <div class="tour-carousel-container">
                <div class="owl-carousel owl-theme tour-carousel" id="tourCarousel">
                    <?php foreach ($tours as $index => $tour): 
                        $image_path = !empty($tour['featured_image']) ? $tour['featured_image'] : 'assets/images/tours/default.jpg';
                        $price = isset($tour['price']) ? formatPriceINR($tour['price']) : 'Contact Us';
                        $discount_price = isset($tour['discount_price']) && $tour['discount_price'] > 0 ? formatPriceINR($tour['discount_price']) : null;
                        $duration = isset($tour['duration_days']) ? $tour['duration_days'] . ' Days' : '';
                        $destination = $tour['destination_name'] ?? $tour['city'] ?? 'Amazing Destination';
                        $country = $tour['country'] ?? '';
                    ?>
                    <div class="tour-carousel-item">
                        <div class="card h-100 tour-card" style="border: none; border-radius: 24px; overflow: hidden; box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12); transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); position: relative; background: #ffffff; margin-bottom: 0;">
                            <!-- Enhanced hover overlay -->
                            <div class="card-hover-overlay" style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: linear-gradient(135deg, rgba(102, 126, 234, 0.08) 0%, rgba(118, 75, 162, 0.08) 100%); opacity: 0; transition: all 0.3s ease; z-index: 1; border-radius: 24px;"></div>
                            
                            <div class="position-relative" style="overflow: hidden; border-radius: 24px 24px 0 0;">
                                <img src="<?php echo BASE_URL . $image_path; ?>" 
                                     class="card-img-top" style="height: 260px; width: 100%; object-fit: cover; transition: transform 0.5s cubic-bezier(0.4, 0, 0.2, 1);" 
                                     alt="<?php echo htmlspecialchars($tour['title']); ?>">
                                
                                <!-- Enhanced gradient overlay -->
                                <div style="position: absolute; bottom: 0; left: 0; right: 0; height: 120px; background: linear-gradient(transparent, rgba(0,0,0,0.4));"></div>
                                
                                <!-- Price badge (always visible) -->
                                <div class="position-absolute top-0 end-0 m-3 tour-card-price-badge" style="z-index: 20; opacity: 1; visibility: visible;">
                                    <div class="badge px-3 py-2" style="border-radius: 20px; background: rgba(0, 0, 0, 0.85); backdrop-filter: blur(10px); color: white; font-weight: 600; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3); border: 1px solid rgba(255, 255, 255, 0.1);">
                                        <?php if ($discount_price): ?>
                                            <div style="display: flex; flex-direction: column; align-items: center; line-height: 1.2;">
                                                <span style="text-decoration: line-through; color: rgba(255,255,255,0.7); font-size: 0.75rem;"><?php echo $price; ?></span>
                                                <span style="font-size: 1rem; font-weight: 700; color: #4ade80;"><?php echo $discount_price; ?></span>
                                            </div>
                                        <?php else: ?>
                                            <span style="font-size: 1rem; font-weight: 700;"><?php echo $price; ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Featured badge -->
                                <div class="position-absolute top-0 start-0 m-3" style="z-index: 3;">
                                    <span class="badge" style="border-radius: 12px; background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white; padding: 6px 10px; font-weight: 600; font-size: 0.75rem; box-shadow: 0 4px 15px rgba(245, 158, 11, 0.4); text-transform: uppercase; letter-spacing: 0.5px;">Featured</span>
                                </div>
                                
                                <!-- Rating badge -->
                                <div class="position-absolute bottom-0 start-0 m-3" style="z-index: 3;">
                                    <div class="badge" style="background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); color: #1f2937; border-radius: 16px; padding: 8px 12px; font-weight: 600; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1); border: 1px solid rgba(255, 255, 255, 0.2); font-size: 0.8rem;">
                                        <span style="color: #f59e0b; margin-right: 4px;">★</span>
                                        <span style="color: #1f2937;">4.<?php echo rand(6, 9); ?></span>
                                        <span style="color: #6b7280; font-size: 0.75rem; margin-left: 2px;">(<?php echo rand(50, 200); ?>+)</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card-body d-flex flex-column" style="padding: 24px; position: relative; z-index: 2; background: #ffffff; border-radius: 0 0 24px 24px;">
                                <div class="mb-3" style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 8px;">
                                    <div style="display: flex; align-items: center; color: #6b7280; font-weight: 500; font-size: 0.85rem;">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" style="margin-right: 6px; color: #ef4444;">
                                            <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                                        </svg>
                                        <span><?php echo htmlspecialchars($destination . ($country ? ', ' . $country : '')); ?></span>
                                    </div>
                                    <?php if ($duration): ?>
                                    <div style="display: flex; align-items: center; background: rgba(34, 197, 94, 0.1); color: #16a34a; font-weight: 600; font-size: 0.8rem; padding: 4px 10px; border-radius: 12px;">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" style="margin-right: 4px;">
                                            <path d="M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2M16.2,16.2L11,13V7H12.5V12.2L17,14.9L16.2,16.2Z"/>
                                        </svg>
                                        <span><?php echo $duration; ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <h5 class="card-title" style="margin-bottom: 12px; font-size: 1.25rem; font-weight: 700; line-height: 1.3; color: #111827; height: 52px; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;">
                                    <a href="<?php echo BASE_URL; ?>tour-details.php?id=<?php echo $tour['id']; ?>" class="text-decoration-none" style="color: inherit; transition: all 0.3s ease;" onmouseover="this.style.color='#46e5adff'" onmouseout="this.style.color='#111827'">
                                        <?php echo htmlspecialchars($tour['title']); ?>
                                    </a>
                                </h5>
                                
                                <p class="card-text flex-grow-1" style="color: #6b7280; font-size: 0.9rem; line-height: 1.5; margin-bottom: 16px; height: 54px; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical;">
                                    <?php 
                                    $description = $tour['short_description'] ?? $tour['description'] ?? 'Discover amazing experiences and create unforgettable memories with this carefully crafted tour package.';
                                    echo htmlspecialchars(substr($description, 0, 120) . (strlen($description) > 120 ? '...' : ''));
                                    ?>
                                </p>
                                
                                <div class="d-flex justify-content-between align-items-center mb-4" style="flex-wrap: wrap; gap: 8px; min-height: 32px;">
                                    <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                                        <span class="badge" style="background: rgba(79, 70, 229, 0.1); color: #4f46e5; padding: 4px 8px; border-radius: 8px; font-size: 0.7rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Easy</span>
                                        <span class="badge" style="background: rgba(16, 185, 129, 0.1); color: #059669; padding: 4px 8px; border-radius: 8px; font-size: 0.7rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Cultural</span>
                                    </div>
                                    <div style="display: flex; align-items: center; color: #6b7280; font-size: 0.8rem; font-weight: 500;">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" style="margin-right: 4px; color: #8b5cf6;">
                                            <path d="M16 4c0-1.11.89-2 2-2s2 .89 2 2-.89 2-2 2-2-.89-2-2zm4 18v-6h2.5l-2.54-7.63A2.002 2.002 0 0 0 17.5 7c-.8 0-1.54.5-1.85 1.26l-1.92 5.63A2.01 2.01 0 0 0 15.59 16H16v6h4zM12.5 11.5c.83 0 1.5-.67 1.5-1.5s-.67-1.5-1.5-1.5S11 9.17 11 10s.67 1.5 1.5 1.5zM5.5 6c1.11 0 2-.89 2-2s-.89-2-2-2-2 .89-2 2 .89 2 2 2zm2 16v-7H9l-1.5-4.5A2 2 0 0 0 5.61 9c-.8 0-1.54.5-1.85 1.26L2.24 15.5A2.01 2.01 0 0 0 4.09 18H5.5v4h2z"/>
                                        </svg>
                                        <span>Max <?php echo rand(6, 12); ?></span>
                                    </div>
                                </div>
                                
                                <div class="mt-auto">
                                    <a href="<?php echo BASE_URL; ?>tour-details.php?id=<?php echo $tour['id']; ?>" class="w-100 d-inline-block text-center text-decoration-none" style="background: #1bbc9b; color: white; padding: 14px 20px; border-radius: 16px; font-size: 0.9rem; font-weight: 600; transition: all 0.3s ease; box-shadow: 0 4px 20px rgba(79, 70, 229, 0.3); position: relative; overflow: hidden;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 30px rgba(79, 70, 229, 0.4)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 20px rgba(79, 70, 229, 0.3)'">
                                        <span style="position: relative; z-index: 1;">Explore Details</span>
                                        <div style="position: absolute; top: 0; left: -100%; width: 100%; height: 100%; background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent); transition: left 0.6s ease;"></div>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Custom navigation - Hidden -->
             
            </div>
            
            <div class="text-center mt-5 scroll-reveal">
                <a href="<?php echo BASE_URL; ?>tours.php" class="travhub-btn" style="padding: 15px 40px; font-size: 1.1rem; font-weight: 600;">
                    <span>🌍 View All Tours</span>
                </a>
                <p style="margin-top: 15px; color: #6c757d; font-size: 0.9rem;">Discover <?php echo count($tours); ?>+ amazing destinations worldwide</p>
            </div>
        </div>
    </section>
    <?php
}

/**
 * Initialize tour carousel JavaScript
 * Call this function before closing body tag for carousel
 */
function initTourCarouselJS() {
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize Owl Carousel for tour carousel (3 slides)
        if (typeof $.fn.owlCarousel !== 'undefined' && $('#tourCarousel').length) {
            // Destroy existing carousel if present
            var $carousel = $('#tourCarousel');
            if ($carousel.hasClass('owl-loaded')) {
                $carousel.trigger('destroy.owl.carousel');
            }
            
            // Initialize fresh carousel
            $carousel.owlCarousel({
                items: 3,
                loop: true,
                center: false,
                margin: 30,
                nav: false,
                dots: true,
                autoplay: true,
                autoplayTimeout: 4000,
                autoplayHoverPause: true,
                smartSpeed: 600,
                mouseDrag: true,
                touchDrag: true,
                pullDrag: true,
                freeDrag: false,
                stagePadding: 0,
                animateOut: false,
                animateIn: false,
                responsive: {
                    0: { 
                        items: 1,
                        margin: 15,
                        stagePadding: 0,
                        nav: false
                    },
                    576: { 
                        items: 2,
                        margin: 20,
                        stagePadding: 0,
                        nav: false
                    },
                    992: { 
                        items: 3,
                        margin: 30,
                        stagePadding: 0,
                        nav: false
                    },
                    1200: { 
                        items: 3,
                        margin: 30,
                        stagePadding: 0,
                        nav: true
                    }
                },
                onInitialized: function() {
                    console.log('Tour Carousel initialized successfully');
                },
                onChanged: function() {
                    // Refresh after change to fix any layout issues
                    setTimeout(function() {
                        $(window).trigger('resize');
                    }, 100);
                }
            });
            
            // Custom navigation
            $('.tour-carousel-next').click(function() {
                $('#tourCarousel').trigger('next.owl.carousel');
            });
            
            $('.tour-carousel-prev').click(function() {
                $('#tourCarousel').trigger('prev.owl.carousel');
            });
            
            // Hover effects for navigation
            $('.tour-carousel-next, .tour-carousel-prev').hover(
                function() {
                    $(this).css({
                        'transform': 'translateY(-50%) scale(1.1)',
                        'box-shadow': '0 8px 25px rgba(102, 126, 234, 0.6)'
                    });
                },
                function() {
                    $(this).css({
                        'transform': 'translateY(-50%) scale(1)',
                        'box-shadow': '0 4px 15px rgba(102, 126, 234, 0.4)'
                    });
                }
            );
            
            // Hide navigation on mobile
            function toggleNavigation() {
                if ($(window).width() < 768) {
                    $('.tour-carousel-nav').hide();
                } else {
                    $('.tour-carousel-nav').show();
                }
            }
            
            toggleNavigation();
            $(window).resize(toggleNavigation);
        }
        
        // Enhanced hover effects for tour cards
        $('.tour-carousel-item .card').hover(
            function() {
                // Card hover effect
                $(this).css({
                    'transform': 'translateY(-12px)',
                    'box-shadow': '0 20px 60px rgba(0, 0, 0, 0.15), 0 0 0 1px rgba(79, 70, 229, 0.1)'
                });
                
                // Image zoom effect
                $(this).find('.card-img-top').css('transform', 'scale(1.08)');
                
                // Show hover overlay
                $(this).find('.card-hover-overlay').css('opacity', '1');
                
                // Button shimmer effect
                var shimmer = $(this).find('a[style*="gradient"] div').last();
                shimmer.css('left', '100%');
                setTimeout(() => shimmer.css('left', '-100%'), 600);
            },
            function() {
                // Reset card
                $(this).css({
                    'transform': 'translateY(0)',
                    'box-shadow': '0 8px 32px rgba(0, 0, 0, 0.12)'
                });
                
                // Reset image
                $(this).find('.card-img-top').css('transform', 'scale(1)');
                
                // Hide hover overlay
                $(this).find('.card-hover-overlay').css('opacity', '0');
            }
        );
        
        // Button hover effects
        $('.tour-carousel-item .card a[style*="gradient"]').hover(
            function() {
                $(this).find('div').last().css('left', '100%');
            },
            function() {
                $(this).find('div').last().css('left', '-100%');
            }
        );
    });
    </script>
    <?php
}

/**
 * Initialize tour slider JavaScript
 * Call this function before closing body tag
 */
function initTourSliderJS() {
    global $db;
    
    // Get slider settings from database
    $slider_settings = [];
    try {
        $settings_result = $db->fetchAll("SELECT setting_key, setting_value FROM site_settings WHERE setting_key LIKE 'slider_%'");
        foreach ($settings_result as $setting) {
            $slider_settings[$setting['setting_key']] = $setting['setting_value'];
        }
    } catch (Exception $e) {
        // Use defaults if database error
    }
    
    // Set defaults
    $autoplay = isset($slider_settings['slider_autoplay']) ? ($slider_settings['slider_autoplay'] == '1') : true;
    $autoplay_speed = isset($slider_settings['slider_autoplay_speed']) ? intval($slider_settings['slider_autoplay_speed']) : 6000;
    $animation_speed = isset($slider_settings['slider_animation_speed']) ? intval($slider_settings['slider_animation_speed']) : 1000;
    $show_arrows = isset($slider_settings['slider_show_arrows']) ? ($slider_settings['slider_show_arrows'] == '1') : true;
    $show_dots = isset($slider_settings['slider_show_dots']) ? ($slider_settings['slider_show_dots'] == '1') : true;
    $pause_on_hover = isset($slider_settings['slider_pause_on_hover']) ? ($slider_settings['slider_pause_on_hover'] == '1') : true;
    
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize Owl Carousel for tour slider
        if (typeof $.fn.owlCarousel !== 'undefined') {
            $('#tourSlider').owlCarousel({
                items: 1,
                loop: true,
                nav: false,
                dots: false,
                autoplay: <?php echo $autoplay ? 'true' : 'false'; ?>,
                autoplayTimeout: <?php echo $autoplay_speed; ?>,
                autoplayHoverPause: <?php echo $pause_on_hover ? 'true' : 'false'; ?>,
                animateOut: 'fadeOut',
                animateIn: 'fadeIn',
                smartSpeed: <?php echo $animation_speed; ?>,
                mouseDrag: true,
                touchDrag: true,
                responsive: {
                    0: { items: 1 },
                    768: { items: 1 },
                    1024: { items: 1 }
                }
            });
            
            // Custom navigation
            $('.tour-slider-next').click(function() {
                $('#tourSlider').trigger('next.owl.carousel');
            });
            
            $('.tour-slider-prev').click(function() {
                $('#tourSlider').trigger('prev.owl.carousel');
            });
            
            // Custom dots
            $('.tour-slider-dot').click(function() {
                var slideIndex = $(this).data('slide');
                $('#tourSlider').trigger('to.owl.carousel', [slideIndex]);
            });
            
            // Update dots on slide change
            $('#tourSlider').on('changed.owl.carousel', function(event) {
                var currentIndex = event.item.index - event.relatedTarget._clones.length / 2;
                $('.tour-slider-dot').removeClass('active');
                $('.tour-slider-dot').eq(currentIndex).addClass('active');
            });
        }
        
        // Parallax effect for slide backgrounds
        $(window).scroll(function() {
            var scrolled = $(window).scrollTop();
            var parallaxSpeed = 0.5;
            $('.tour-slide-bg').css('transform', 'translateY(' + (scrolled * parallaxSpeed) + 'px)');
        });
    });
    </script>
    <?php
}
?>
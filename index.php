<?php
require_once 'config/config.php';
require_once 'includes/tour_slider_helper.php';
require_once 'includes/hero_helper.php';

// Load function files
require_once 'app/functions/destination_functions.php';

// Set page variables
$page_title = getSetting('site_name') . ' || Travel & Tour Booking Agency';
$current_page = 'home';

// Enable tour slider CSS for this page (needed for carousel styling)
enableTourSliderCSS();

// Add page-specific CSS
$extra_css = cssWithCache('assets/css/index.css');

// Get popular destinations using function
$popular_destinations = getDestinations([
    'popular' => true,
    'limit' => 4,
    'order_by' => 'd.created_at DESC'
]);

// Get popular destinations for home page (3 cards)
$home_categories = getPopularDestinationsForHome(3);

// Get countries for search dropdown
$countries = $db->fetchAll("
    SELECT DISTINCT d.country
    FROM destinations d
    INNER JOIN tours t ON t.destination_id = d.id AND t.status = 'active'
    WHERE d.status = 'active'
      AND d.country IS NOT NULL
      AND d.country != ''
    ORDER BY d.country ASC
");

// Get all destinations for search dropdown
$all_destinations = $db->fetchAll("
    SELECT d.*, COUNT(t.id) as tour_count
    FROM destinations d
    INNER JOIN tours t ON t.destination_id = d.id AND t.status = 'active'
    WHERE d.status = 'active'
    GROUP BY d.id
    HAVING COUNT(t.id) > 0
    ORDER BY d.name ASC
");

// Hero background for search section
$hero_image = getHeroContent();
$hero_bg_url = BASE_URL . ($hero_image['image_path'] ?? 'assets/images/hero/default-hero.jpg');

// Cab routes for Transfer (cab) search
$cab_routes = $db->fetchAll("
    SELECT id, from_location, to_location
    FROM cab_routes
    WHERE status = 'active'
    ORDER BY display_order ASC, from_location ASC
");
$cab_pickup_locations = [];
$cab_dropoff_locations = [];
foreach ($cab_routes as $route) {
    $cab_pickup_locations[$route['from_location']] = true;
    $cab_dropoff_locations[$route['to_location']] = true;
}
$cab_pickup_locations = array_keys($cab_pickup_locations);
$cab_dropoff_locations = array_keys($cab_dropoff_locations);
sort($cab_pickup_locations);
sort($cab_dropoff_locations);

$extra_js = '<script>window.BASE_URL = "' . BASE_URL . '";</script>'
    . '<script>window.CAB_ROUTES = ' . json_encode(array_values($cab_routes), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) . ';</script>'
    . jsWithCache('assets/js/index.js');

// Include header
include 'includes/header.php';
?>

<!-- Hero Search Section: Transfer (Cab) + Activity (Tour) -->
<section class="search-section hero-search-section" style="background-image: url('<?php echo htmlspecialchars($hero_bg_url); ?>');">
    <div class="hero-search-overlay"></div>
    <div class="decorative-element decorative-element-1"></div>
    <div class="decorative-element decorative-element-2"></div>

    <div class="container">
        <div class="hero-search-header text-center">
            <h2 class="hero-search-title">Luxury Options</h2>
            <p class="hero-search-subtitle">Search for best available hotel options, events, tours, activities and create various easy to book holiday packages.</p>
        </div>

        <div class="search-category-tabs" role="tablist" aria-label="Search type">
            <button type="button" class="search-category-tab active" data-search-tab="transfer" role="tab" aria-selected="true" aria-controls="transferSearchPanel">
                <i class="fas fa-car"></i>
                <span>Travel</span>
            </button>
            <button type="button" class="search-category-tab" data-search-tab="activity" role="tab" aria-selected="false" aria-controls="activitySearchPanel">
                <i class="fas fa-camera"></i>
                <span>Activity</span>
            </button>
        </div>

        <div class="search-container">
            <!-- Transfer / Cab search -->
            <div id="transferSearchPanel" class="search-panel active" role="tabpanel" data-search-type="transfer">
                <form class="search-form search-form--transfer" id="cabSearchForm" onsubmit="return false;">
                    <div class="form-group form-group-trip-type">
                        <select name="trip_type" id="cab_trip_type" aria-label="Trip type">
                            <option value="one_way">One Way</option>
                            <option value="round_trip">Round Trip</option>
                        </select>
                    </div>
                    <div class="form-group form-group-select">
                        <div class="input-wrapper">
                            <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                <circle cx="12" cy="10" r="3"></circle>
                            </svg>
                            <select name="pickup" id="cab_pickup" data-placeholder="Pick-Up">
                                <option value="">Pick-Up</option>
                                <?php foreach ($cab_pickup_locations as $loc): ?>
                                <option value="<?php echo htmlspecialchars($loc); ?>"><?php echo htmlspecialchars($loc); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group form-group-select">
                        <div class="input-wrapper">
                            <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                <circle cx="12" cy="10" r="3"></circle>
                            </svg>
                            <select name="dropoff" id="cab_dropoff" data-placeholder="Drop-Off">
                                <option value="">Drop-Off</option>
                                <?php foreach ($cab_dropoff_locations as $loc): ?>
                                <option value="<?php echo htmlspecialchars($loc); ?>"><?php echo htmlspecialchars($loc); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group form-group-input">
                        <div class="input-wrapper">
                            <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                <line x1="16" y1="2" x2="16" y2="6"></line>
                                <line x1="8" y1="2" x2="8" y2="6"></line>
                                <line x1="3" y1="10" x2="21" y2="10"></line>
                            </svg>
                            <input class="travhub-multi-datepicker" id="cab_travel_date" type="text" name="travel_date" placeholder="Date" data-label="Date">
                        </div>
                    </div>
                    <div class="form-group form-group-select">
                        <div class="input-wrapper">
                            <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                <circle cx="9" cy="7" r="4"></circle>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                            </svg>
                            <select name="guests" id="cab_guests">
                                <?php for ($g = 1; $g <= 8; $g++): ?>
                                <option value="<?php echo $g; ?>"<?php echo $g === 2 ? ' selected' : ''; ?>><?php echo $g; ?> Adult<?php echo $g > 1 ? 's' : ''; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    <button type="button" class="search-btn search-btn--red" onclick="showPhoneModal('transfer')">
                        <span>Search</span>
                    </button>
                </form>
            </div>

            <!-- Activity / Tour search -->
            <div id="activitySearchPanel" class="search-panel search-panel--activity" role="tabpanel" data-search-type="activity" hidden>
                <form class="search-form search-form--activity" id="tourSearchForm" onsubmit="return false;">
                    <div class="form-group form-group-select">
                        <div class="input-wrapper">
                            <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                            </svg>
                            <select name="country" id="activity_country" aria-label="Country">
                                <option value="">Select Country</option>
                                <?php foreach ($countries as $country): 
                                    $cname = $country['country'];
                                    $isIndia = (strcasecmp($cname, 'India') === 0);
                                ?>
                                <option value="<?php echo htmlspecialchars($cname); ?>"<?php echo $isIndia ? ' selected' : ''; ?>><?php echo htmlspecialchars($cname); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group form-group-select">
                        <div class="input-wrapper">
                            <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                <circle cx="12" cy="10" r="3"></circle>
                            </svg>
                            <select name="destination" id="activity_destination" aria-label="Destination">
                                <option value="">Select Destination</option>
                                <?php foreach ($all_destinations as $dest): ?>
                                <option value="<?php echo htmlspecialchars($dest['slug']); ?>"
                                    data-country="<?php echo htmlspecialchars($dest['country'] ?? ''); ?>"
                                    data-name="<?php echo htmlspecialchars($dest['name']); ?>">
                                    <?php echo htmlspecialchars($dest['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group form-group-select activity-pickup-group" id="activityPickupGroup">
                        <div class="input-wrapper">
                            <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                <circle cx="12" cy="10" r="3"></circle>
                            </svg>
                            <select name="pickup_place" id="activity_pickup_place" aria-label="Pickup Place" required>
                                <option value="" disabled selected hidden>Pickup Place</option>
                                <option value="Hotel">Hotel</option>
                                <option value="Lift Parking">Lift Parking</option>
                                <option value="Otherlocation">Otherlocation</option>
                            </select>
                        </div>
                        <div class="activity-pickup-detail" id="activityPickupDetailWrap" hidden>
                            <input type="text"
                                name="pickup_detail"
                                id="activity_pickup_detail"
                                class="activity-pickup-detail-input"
                                placeholder=""
                                autocomplete="off"
                                maxlength="200">
                        </div>
                    </div>
                    <div class="form-group form-group-input">
                        <div class="input-wrapper">
                            <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                <line x1="16" y1="2" x2="16" y2="6"></line>
                                <line x1="8" y1="2" x2="8" y2="6"></line>
                                <line x1="3" y1="10" x2="21" y2="10"></line>
                            </svg>
                            <input class="travhub-multi-datepicker" id="activity_travel_date" type="text" name="travel_date" placeholder="Date" data-label="Date" autocomplete="off">
                        </div>
                    </div>
                    <button type="button" class="search-btn search-btn--red" onclick="showPhoneModal('activity')">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"></circle>
                            <path d="m21 21-4.35-4.35"></path>
                        </svg>
                        <span>Search</span>
                    </button>
                </form>
                <script>
                (function() {
                    function syncActivityPickupDetail() {
                        var sel = document.getElementById('activity_pickup_place');
                        var wrap = document.getElementById('activityPickupDetailWrap');
                        var inp = document.getElementById('activity_pickup_detail');
                        var grp = document.getElementById('activityPickupGroup');
                        if (!sel || !wrap || !inp) return;
                        var v = sel.value;
                        var show = v === 'Hotel' || v === 'Otherlocation';
                        if (show) {
                            wrap.removeAttribute('hidden');
                            wrap.classList.add('activity-pickup-detail--open');
                            inp.placeholder = v === 'Hotel' ? 'Enter hotel name' : 'Enter location details';
                            inp.setAttribute('aria-label', inp.placeholder);
                            inp.setAttribute('required', 'required');
                            if (grp) grp.classList.add('activity-pickup-group--expanded');
                        } else {
                            wrap.setAttribute('hidden', '');
                            wrap.classList.remove('activity-pickup-detail--open');
                            inp.value = '';
                            inp.removeAttribute('required');
                            inp.placeholder = '';
                            if (grp) grp.classList.remove('activity-pickup-group--expanded');
                        }
                    }
                    window.syncActivityPickupDetail = syncActivityPickupDetail;
                    function bindPickupDetail() {
                        var sel = document.getElementById('activity_pickup_place');
                        if (!sel || sel.dataset.pickupDetailBound === '1') return;
                        sel.dataset.pickupDetailBound = '1';
                        sel.addEventListener('change', syncActivityPickupDetail);
                        sel.addEventListener('input', syncActivityPickupDetail);
                        syncActivityPickupDetail();
                    }
                    if (document.readyState === 'loading') {
                        document.addEventListener('DOMContentLoaded', bindPickupDetail);
                    } else {
                        bindPickupDetail();
                    }
                })();
                </script>
            </div>
        </div>
    </div>
</section>

<!-- Phone Number Modal -->
<div id="phoneModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h4><i class="flaticon-search"></i> Confirm Your Search</h4>
            <span class="modal-close" onclick="closePhoneModal()">&times;</span>
        </div>
        <div class="modal-body">
            <div class="search-details">
                <h6 style="color: #667eea; font-weight: 600; margin-bottom: 15px;">Your Search Details:</h6>
                <div id="searchDetails"></div>
            </div>
            
            <form id="phoneForm" onsubmit="submitSearch(event)">
                <div class="form-group">
                    <label class="form-label">
                        <i class="flaticon-phone-call"></i> Phone Number <span style="color: red;">*</span>
                    </label>
                    <input type="tel" name="phone" id="modalPhone" class="form-control" placeholder="Enter your 10-digit phone number" required
                           pattern="[0-9]{10}" maxlength="10">
                    <small style="color: #6c757d; margin-top: 5px; display: block;">We'll use this to contact you about your tour inquiry</small>
                </div>
                
                <div class="modal-actions">
                    <button type="button" onclick="closePhoneModal()" class="modal-btn modal-btn-cancel">
                        Cancel
                    </button>
                    <button type="submit" class="modal-btn modal-btn-submit" id="modalSubmitBtn">
                        <i class="flaticon-search"></i> <span id="modalSubmitText">Search</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/destinations_cab_section.php'; ?>

<section class="about-one section-space">
    <!-- Floating elements for visual appeal -->
    <div class="floating-element floating-element-1"></div>
    <div class="floating-element floating-element-2"></div>
    
    <div class="container">
        <div class="about-content-wrapper">
            <div class="text-center scroll-reveal">
                <div style="margin-bottom: 30px;">
                    <span class="badge bg-primary">✈️ Premium Travel Experience</span>
                </div>
                
                <h2 class="gradient-text">Welcome to <?php echo getSetting('site_name'); ?></h2>
                
                <p class="lead">Your Adventure Starts Here</p>
                
                <p style="font-size: 1.1rem; color: #495057; max-width: 600px; margin: 0 auto 40px; line-height: 1.6;">Experience the world like never before with our carefully curated travel packages. From exotic destinations to cultural experiences, we make your travel dreams come true.</p>
                
                <div class="mt-4" style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
                    <a href="<?php echo adminUrl('login'); ?>" class="travhub-btn" style="background: linear-gradient(135deg, #6c757d 0%, #495057 100%) !important; box-shadow: 0 4px 15px rgba(108, 117, 125, 0.4) !important;">
                        <span>🔐 Admin Panel</span>
                    </a>
                    <a href="<?php echo navUrl('tours'); ?>" class="travhub-btn">
                        <span>🌟 Explore Tours</span>
                    </a>
                </div>
                
                <!-- Stats section with Grid -->
                <div class="stats-grid">
                    <div class="stat-item">
                        <div class="glass-effect">
                            <h3 class="gradient-text" style="font-size: 2rem; margin-bottom: 5px;">500+</h3>
                            <p style="margin: 0; color: #6c757d;">Happy Travelers</p>
                        </div>
                    </div>
                    <div class="stat-item">
                        <div class="glass-effect">
                            <h3 class="gradient-text" style="font-size: 2rem; margin-bottom: 5px;">50+</h3>
                            <p style="margin: 0; color: #6c757d;">Destinations</p>
                        </div>
                    </div>
                    <div class="stat-item">
                        <div class="glass-effect">
                            <h3 class="gradient-text" style="font-size: 2rem; margin-bottom: 5px;">24/7</h3>
                            <p style="margin: 0; color: #6c757d;">Support</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Popular Destinations -->
<!-- Destinations Section - Grid Layout -->
<?php if (!empty($home_categories)): ?>
<section class="popular-destinations-section section-space">
    <div class="container">
        <div class="section-title text-center scroll-reveal" style="margin-bottom: 60px; position: relative; z-index: 2;">
            <div style="margin-bottom: 15px;">
                <span class="badge" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 8px 16px; border-radius: 20px; font-size: 0.9rem; box-shadow: 0 4px 15px rgba(118, 75, 162, 0.3);">
                    🌍 Explore The World
                </span>
            </div>
            <h2 class="gradient-text" style="font-size: 2.8rem; font-weight: 700; margin-bottom: 20px;">Popular Destinations</h2>
            <p style="font-size: 1.1rem; color: #6c757d; max-width: 500px; margin: 0 auto; line-height: 1.6;">
                Discover the most sought-after travel destinations around the globe
            </p>
            <div style="width: 80px; height: 4px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); margin: 20px auto 0; border-radius: 2px;"></div>
        </div>
        
        <!-- Grid Layout for Destinations -->
        <div class="destinations-grid">
            <?php foreach ($home_categories as $index => $destination): 
                $destination_image = getDestinationImageUrl($destination);
                $count = $destination['tour_count'] ?? 0;
            ?>
            <div class="destination-grid-item">
                <div class="destination-card">
                    <!-- Destination Image -->
                    <div class="destination-card-image">
                        <img src="<?php echo htmlspecialchars($destination_image); ?>" alt="<?php echo htmlspecialchars($destination['name']); ?>">
                        <div class="destination-overlay"></div>
                    </div>
                    
                    <!-- Destination Info -->
                    <div class="destination-card-content">
                        <div class="destination-header">
                            <h3 class="destination-name"><?php echo htmlspecialchars($destination['name']); ?></h3>
                            <span class="destination-count"><?php echo $count; ?> Tour<?php echo $count != 1 ? 's' : ''; ?></span>
                        </div>
                        
                        <?php if (!empty($destination['short_description'])): ?>
                        <p class="destination-description"><?php echo htmlspecialchars(substr($destination['short_description'], 0, 100)); ?>...</p>
                        <?php endif; ?>
                        
                        <a href="<?php echo BASE_URL; ?>tours.php?destination=<?php echo htmlspecialchars($destination['slug']); ?>" class="destination-btn">
                            Explore Destination <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>
















<?php initTourSliderJS(); ?>

<?php include 'includes/footer.php'; ?>

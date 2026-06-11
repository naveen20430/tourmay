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

// Hero background slideshow for search section (managed in Admin → Hero Images)
$hero_search_backgrounds = getHeroSearchBackgrounds();

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

// Activity tab: destinations, tours, countries, pickup places (from DB)
$activity_countries = $db->fetchAll("
    SELECT DISTINCT d.country
    FROM destinations d
    INNER JOIN tours t ON t.destination_id = d.id AND t.status = 'active'
    WHERE d.status = 'active'
      AND d.country IS NOT NULL
      AND d.country != ''
    ORDER BY d.country ASC
");

$activity_destinations = $db->fetchAll("
    SELECT d.id, d.name, d.slug, d.country, d.city, d.popular, d.featured_image,
           COUNT(t.id) AS tour_count
    FROM destinations d
    INNER JOIN tours t ON t.destination_id = d.id AND t.status = 'active'
    WHERE d.status = 'active'
    GROUP BY d.id
    ORDER BY d.popular DESC, d.name ASC
");

$activity_tours = $db->fetchAll("
    SELECT t.id, t.title, t.slug, d.slug AS destination_slug, d.name AS destination_name, d.country
    FROM tours t
    INNER JOIN destinations d ON d.id = t.destination_id AND d.status = 'active'
    WHERE t.status = 'active'
    ORDER BY t.title ASC
");

$activity_pickup_places = ['Hotel', 'Lift Parking', 'Others'];

function activityDestinationImageUrl(array $dest) {
    $img = trim((string) ($dest['featured_image'] ?? ''));
    if ($img !== '') {
        return BASE_URL . ltrim($img, '/');
    }
    return BASE_URL . 'assets/images/logonew.png';
}

$activity_search_payload = [
    'countries' => array_column($activity_countries, 'country'),
    'destinations' => array_map(static function ($d) {
        return [
            'id' => (int) $d['id'],
            'name' => $d['name'],
            'slug' => $d['slug'],
            'country' => $d['country'] ?? '',
            'city' => $d['city'] ?? '',
            'popular' => (int) ($d['popular'] ?? 0),
            'image' => activityDestinationImageUrl($d),
            'url' => destinationUrl($d['slug']),
            'tours_url' => toursUrl(['destination' => $d['slug']]),
            'tour_count' => (int) ($d['tour_count'] ?? 0),
        ];
    }, $activity_destinations),
    'tours' => array_map(static function ($t) {
        return [
            'id' => (int) $t['id'],
            'title' => $t['title'],
            'slug' => $t['slug'],
            'destination_slug' => $t['destination_slug'],
            'destination_name' => $t['destination_name'],
            'country' => $t['country'] ?? '',
            'url' => tourUrl($t['slug']),
            'type' => 'tour',
        ];
    }, $activity_tours),
    'pickup_places' => $activity_pickup_places,
];

$extra_js = '<script>window.BASE_URL = "' . BASE_URL . '";</script>'
    . '<script>window.CAB_ROUTES = ' . json_encode(array_values($cab_routes), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) . ';</script>'
    . '<script>window.ACTIVITY_SEARCH_DATA = ' . json_encode($activity_search_payload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) . ';</script>'
    . jsWithCache('assets/js/index.js');

// Include header
include 'includes/header.php';
?>

<!-- Hero Search Section: Transfer (Cab) + Activity (Tour) -->
<section class="search-section hero-search-section<?php echo !empty($hero_search_backgrounds) ? ' hero-search-section--slider' : ''; ?>">
    <?php if (!empty($hero_search_backgrounds)): ?>
    <div class="hero-search__bg" aria-hidden="true">
        <?php foreach ($hero_search_backgrounds as $hero_bg_path): ?>
        <div class="hero-search__bg-slide" style="background-image:url('<?php echo BASE_URL . htmlspecialchars($hero_bg_path); ?>')"></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div class="hero-search-overlay"></div>
    <div class="decorative-element decorative-element-1"></div>
    <div class="decorative-element decorative-element-2"></div>

    <div class="container">
        <div class="hero-search-header text-center">
            <h2 class="hero-search-title">Luxury Options</h2>
            <p class="hero-search-subtitle">Search for best available hotel options, events, tours, activities and create various easy to book holiday packages.</p>
        </div>

        <div class="search-category-tabs" role="tablist" aria-label="Search type">
            <button type="button" class="search-category-tab active" data-search-tab="activity" role="tab" aria-selected="true" aria-controls="activitySearchPanel">
                <i class="fas fa-camera"></i>
                <span>Activity</span>
            </button>
            <button type="button" class="search-category-tab" data-search-tab="transfer" role="tab" aria-selected="false" aria-controls="transferSearchPanel">
                <i class="fas fa-car"></i>
                <span>Travel</span>
            </button>
        </div>

        <div class="search-container">
            <!-- Transfer / Cab search -->
            <div id="transferSearchPanel" class="search-panel" role="tabpanel" data-search-type="transfer" hidden>
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
                    <button type="button" class="search-btn search-btn--red" onclick="submitCabSearch()">
                        <span>Search</span>
                    </button>
                </form>
            </div>

            <!-- Activity / Tour search -->
            <div id="activitySearchPanel" class="search-panel search-panel--activity active" role="tabpanel" data-search-type="activity">
                <form class="search-form search-form--activity-v2" id="tourSearchForm" onsubmit="return false;" autocomplete="off">
                    <div class="form-group activity-field activity-field--query">
                        <div class="input-wrapper activity-query-wrap">
                            <input type="text"
                                id="activity_query"
                                name="activity_query"
                                placeholder="Activity /Destination/ Tour"
                                aria-label="Activity, destination or tour"
                                autocomplete="off">
                            <input type="hidden" id="activity_destination" name="destination" value="">
                            <input type="hidden" id="activity_tour_slug" name="tour" value="">
                            <input type="hidden" id="activity_country" name="country" value="">
                            <svg class="input-icon input-icon--right" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                <circle cx="12" cy="10" r="3"></circle>
                            </svg>
                            <div class="activity-suggestions" id="activitySuggestions" hidden></div>
                        </div>
                    </div>

                    <div class="form-group activity-field activity-pickup-group" id="activityPickupGroup" hidden>
                        <div class="input-wrapper">
                            <select name="pickup_place" id="activity_pickup_place" aria-label="Pickup Place">
                                <option value="" disabled selected hidden>Pickup Place</option>
                                <?php foreach ($activity_pickup_places as $place): ?>
                                <option value="<?php echo htmlspecialchars($place); ?>"><?php echo htmlspecialchars($place); ?></option>
                                <?php endforeach; ?>
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

                    <div class="form-group activity-field activity-guest-field">
                        <div class="activity-guest-dropdown" id="activityGuestDropdown">
                            <button type="button" class="activity-guest-toggle" id="activityGuestToggle" aria-expanded="false" aria-haspopup="listbox">
                                <span id="activityGuestLabel">2 Adults</span>
                                <svg class="activity-guest-caret" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 12 8" width="12" height="8" aria-hidden="true"><path fill="currentColor" d="M1 1l5 5 5-5"/></svg>
                            </button>
                            <div class="activity-guest-panel" id="activityGuestPanel" hidden>
                                <div class="activity-guest-panel-head" id="activityGuestPanelHead">2 Adults</div>
                                <div class="activity-guest-row">
                                    <div>
                                        <strong>Adults</strong>
                                        <span class="activity-guest-sub">Above 12 Years</span>
                                    </div>
                                    <div class="activity-guest-counter">
                                        <button type="button" class="activity-counter-btn" data-guest-action="adults-minus" aria-label="Fewer adults">−</button>
                                        <span id="activityAdultsCount">2</span>
                                        <button type="button" class="activity-counter-btn" data-guest-action="adults-plus" aria-label="More adults">+</button>
                                    </div>
                                </div>
                                <div class="activity-guest-row">
                                    <div>
                                        <strong>Children</strong>
                                        <span class="activity-guest-sub">Below 12 Years</span>
                                    </div>
                                    <div class="activity-guest-counter">
                                        <button type="button" class="activity-counter-btn" data-guest-action="children-minus" aria-label="Fewer children">−</button>
                                        <span id="activityChildrenCount">0</span>
                                        <button type="button" class="activity-counter-btn" data-guest-action="children-plus" aria-label="More children">+</button>
                                    </div>
                                </div>
                            </div>
                            <input type="hidden" name="adults" id="activity_adults" value="2">
                            <input type="hidden" name="children" id="activity_children" value="0">
                            <input type="hidden" name="guests" id="activity_guests" value="2">
                        </div>
                    </div>

                    <div class="form-group activity-field">
                        <div class="input-wrapper">
                            <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                <line x1="16" y1="2" x2="16" y2="6"></line>
                                <line x1="8" y1="2" x2="8" y2="6"></line>
                                <line x1="3" y1="10" x2="21" y2="10"></line>
                            </svg>
                            <input class="travhub-multi-datepicker" id="activity_travel_date" type="text" name="travel_date" placeholder="Date" data-label="Date" autocomplete="off">
                        </div>
                    </div>

                    <button type="button" class="search-btn search-btn--red" onclick="showPhoneModal('activity')">
                        <span>Search</span>
                    </button>
                </form>

                <div class="activity-browser" id="activityBrowser">
                    <div class="activity-browser-layout">
                        <nav class="activity-sidebar" id="activitySidebar" aria-label="Destination categories">
                            <button type="button" class="activity-sidebar-item active" data-country="">
                                Top Destination
                            </button>
                            <?php foreach ($activity_countries as $row): ?>
                            <button type="button" class="activity-sidebar-item" data-country="<?php echo htmlspecialchars($row['country']); ?>">
                                <?php echo htmlspecialchars($row['country']); ?>
                            </button>
                            <?php endforeach; ?>
                        </nav>
                        <div class="activity-dest-grid" id="activityDestGrid" role="list">
                            <?php foreach ($activity_destinations as $dest): ?>
                            <button type="button"
                               class="activity-dest-card"
                               role="listitem"
                               data-name="<?php echo htmlspecialchars($dest['name']); ?>"
                               data-country="<?php echo htmlspecialchars($dest['country'] ?? ''); ?>"
                               data-popular="<?php echo (int) ($dest['popular'] ?? 0); ?>"
                               data-slug="<?php echo htmlspecialchars($dest['slug']); ?>"
                               aria-label="<?php echo htmlspecialchars($dest['name']); ?>">
                                <img src="<?php echo htmlspecialchars(activityDestinationImageUrl($dest)); ?>"
                                     alt="<?php echo htmlspecialchars($dest['name']); ?>"
                                     loading="lazy">
                                <span class="activity-dest-card__name"><?php echo htmlspecialchars($dest['name']); ?></span>
                            </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
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

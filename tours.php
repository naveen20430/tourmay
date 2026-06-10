<?php
require_once 'config/config.php';

// Get search and filter parameters
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$destination = $_GET['destination'] ?? '';
$min_price = $_GET['min_price'] ?? '';
$max_price = $_GET['max_price'] ?? '';
$difficulty = $_GET['difficulty'] ?? '';
$tour_type = $_GET['tour_type'] ?? '';
$sort = $_GET['sort'] ?? 'popular';

// Build query
$where_conditions = ['t.status = "active"'];
$params = [];

if ($search) {
    $where_conditions[] = '(t.title LIKE ? OR t.description LIKE ? OR d.name LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($category) {
    $where_conditions[] = 'tc.slug = ?';
    $params[] = $category;
}

if ($destination) {
    $where_conditions[] = 'd.slug = ?';
    $params[] = $destination;
}

if ($min_price) {
    $where_conditions[] = 't.price >= ?';
    $params[] = $min_price;
}

if ($max_price) {
    $where_conditions[] = 't.price <= ?';
    $params[] = $max_price;
}

if ($difficulty) {
    $where_conditions[] = 't.difficulty_level = ?';
    $params[] = $difficulty;
}

if ($tour_type) {
    $where_conditions[] = 't.tour_type = ?';
    $params[] = $tour_type;
}

$where_clause = implode(' AND ', $where_conditions);

$order_clause = 't.featured DESC, t.created_at DESC';
if ($sort === 'price_low') {
    $order_clause = 'COALESCE(NULLIF(t.discount_price, 0), t.price) ASC, t.created_at DESC';
} elseif ($sort === 'price_high') {
    $order_clause = 'COALESCE(NULLIF(t.discount_price, 0), t.price) DESC, t.created_at DESC';
} elseif ($sort === 'latest') {
    $order_clause = 't.created_at DESC';
}

// Get tours
$tours_query = "
    SELECT DISTINCT t.*, d.name as destination_name, d.country, d.description as destination_description
    FROM tours t 
    LEFT JOIN destinations d ON t.destination_id = d.id
    LEFT JOIN tour_category_relations tcr ON t.id = tcr.tour_id
    LEFT JOIN tour_categories tc ON tcr.category_id = tc.id
    WHERE $where_clause
    ORDER BY $order_clause
";

$tours = $db->fetchAll($tours_query, $params);

// Panel data for slide sidebar (Description / Inclusion / Timings / Useful Info)
$tours_panel_data = [];
foreach ($tours as $tour) {
    $inclusions = json_decode($tour['inclusions'] ?? '[]', true) ?: [];
    $exclusions = json_decode($tour['exclusions'] ?? '[]', true) ?: [];
    $itinerary = json_decode($tour['itinerary'] ?? '[]', true) ?: [];

    $availability = '';
    if (!empty($tour['availability_start']) || !empty($tour['availability_end'])) {
        $start = !empty($tour['availability_start']) ? date('d M Y', strtotime($tour['availability_start'])) : 'Open';
        $end = !empty($tour['availability_end']) ? date('d M Y', strtotime($tour['availability_end'])) : 'Open';
        $availability = $start . ' — ' . $end;
    }

    $tours_panel_data[(int) $tour['id']] = [
        'id' => (int) $tour['id'],
        'title' => $tour['title'],
        'slug' => $tour['slug'],
        'destination' => $tour['destination_name'] ?? '',
        'country' => $tour['country'] ?? '',
        'booking_url' => bookingUrl((int) $tour['id']),
        'detail_url' => tourUrl($tour['slug']),
        'cart_url' => navUrl('cart'),
        'default_people' => max((int) ($tour['min_people'] ?? 1), min(2, (int) ($tour['max_people'] ?? 8))),
        'price' => (float) ($tour['discount_price'] ?: $tour['price']),
        'panels' => [
            'description' => [
                'title' => 'Description',
                'short' => $tour['short_description'] ?? '',
                'body' => $tour['description'] ?? '',
            ],
            'inclusion' => [
                'title' => 'Inclusion',
                'inclusions' => $inclusions,
                'exclusions' => $exclusions,
            ],
            'timings' => [
                'title' => 'Timings',
                'duration_days' => (int) ($tour['duration_days'] ?? 0),
                'duration_nights' => (int) ($tour['duration_nights'] ?? 0),
                'availability' => $availability,
                'itinerary' => $itinerary,
            ],
            'useful' => [
                'title' => 'Useful Info',
                'destination' => trim(($tour['destination_name'] ?? '') . (!empty($tour['country']) ? ', ' . $tour['country'] : '')),
                'body' => $tour['destination_description'] ?? '',
            ],
        ],
    ];
}

// Get categories for filter
$categories = $db->fetchAll("
    SELECT tc.*, COUNT(DISTINCT t.id) as tour_count
    FROM tour_categories tc
    LEFT JOIN tour_category_relations tcr ON tc.id = tcr.category_id
    LEFT JOIN tours t ON tcr.tour_id = t.id AND t.status = 'active'
    WHERE tc.status = 'active'
    GROUP BY tc.id
    ORDER BY tc.name
");

$cart_count = (isset($_SESSION['tour_cart']) && is_array($_SESSION['tour_cart'])) ? count($_SESSION['tour_cart']) : 0;

$build_tours_url = function ($overrides = []) use ($search, $category, $destination, $min_price, $max_price, $difficulty, $tour_type, $sort) {
    $query = [
        'search' => $search,
        'category' => $category,
        'destination' => $destination,
        'min_price' => $min_price,
        'max_price' => $max_price,
        'difficulty' => $difficulty,
        'tour_type' => $tour_type,
        'sort' => $sort,
    ];
    foreach ($overrides as $key => $value) {
        $query[$key] = $value;
    }
    $query = array_filter($query, function ($value) {
        return $value !== '' && $value !== null;
    });
    $query_string = http_build_query($query);
    return navUrl('tours') . ($query_string ? ('?' . $query_string) : '');
};

// Get price range
$price_range = $db->fetch("SELECT MIN(price) as min_price, MAX(price) as max_price FROM tours WHERE status = 'active'");

// Set page variables
$page_title = 'Tours - ' . getSetting('site_name');
$current_page = 'tours';
$extra_css = '
<style>
.tours-listing{background:#f8f9fa;padding:60px 0}
.tours-topbar{display:flex;align-items:center;justify-content:space-between;background:linear-gradient(135deg, #667eea 0%, #764ba2 100%);color:#fff;padding:15px 20px;border-radius:8px;margin-bottom:20px;box-shadow:0 4px 15px rgba(118,75,162,0.15)}
.tours-topbar .left{display:flex;align-items:center;gap:10px;font-weight:700;font-size:1.1rem}
.tours-topbar .left i{font-size:20px}
.tours-topbar .right{font-weight:600;font-size:1rem;cursor:pointer}
.tours-layout{display:grid;grid-template-columns:minmax(0,1fr) 320px;gap:30px}
.tours-filterbar{display:grid;grid-template-columns:minmax(320px,38%) minmax(0,1fr);gap:15px;margin-bottom:20px;align-items:stretch}
.tours-filterbar .sort-wrap{display:flex;align-items:center;gap:12px;background:#fff;border:1px solid #e9ecef;border-radius:8px;padding:0 16px;min-height:48px;box-shadow:0 2px 10px rgba(0,0,0,0.02)}
.tours-filterbar .sort-wrap span{font-weight:600;color:#495057;white-space:nowrap;font-size:0.95rem;flex-shrink:0}
.tours-filterbar .sort-form{margin:0;flex:1;min-width:0}
.tours-filterbar .tours-sort-select{width:100%;min-width:170px;border:0;border-radius:0;height:46px;padding:0 32px 0 0;background-color:transparent;background-image:url("data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2212%22 height=%228%22 viewBox=%220 0 12 8%22%3E%3Cpath fill=%22%23667eea%22 d=%22M1 1l5 5 5-5%22/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 4px center;font-weight:600;font-size:0.95rem;color:#495057;cursor:pointer;appearance:none;-webkit-appearance:none;-moz-appearance:none}
.tours-filterbar .tours-sort-select:focus{outline:none;box-shadow:none}
.tours-filterbar input[type="text"]{width:100%;border:1px solid #e9ecef;border-radius:8px;height:48px;padding:0 50px 0 15px;background:#fff;font-size:0.95rem;color:#495057;transition:all 0.3s ease}
.tours-filterbar input[type="text"]:focus{border-color:#667eea;outline:none;box-shadow:0 0 0 3px rgba(102,126,234,0.1)}
.search-wrap{position:relative;min-width:0}
.search-wrap button{position:absolute;right:0;top:0;height:48px;width:50px;border:0;background:transparent;color:#667eea;font-size:1.1rem;transition:all 0.3s ease}
.search-wrap button:hover{color:#764ba2}
.tour-row{display:grid;grid-template-columns:320px minmax(0,1fr);background:#fff;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,0.08);margin-bottom:25px;overflow:hidden;transition:all 0.4s cubic-bezier(0.4,0,0.2,1);border:1px solid rgba(0,0,0,0.03)}
.tour-row:hover{transform:translateY(-5px);box-shadow:0 15px 40px rgba(118,75,162,0.15)}
.tour-row .thumb{position:relative;height:100%;min-height:240px;max-height:280px;overflow:hidden}
.tour-row .thumb img{width:100%;height:100%;object-fit:cover;transition:transform 0.5s ease}
.tour-row:hover .thumb img{transform:scale(1.05)}
.elite-badge{position:absolute;top:15px;right:15px;background:linear-gradient(135deg, #ff6a00 0%, #ee0979 100%);color:#fff;padding:6px 15px;font-weight:700;font-size:0.85rem;border-radius:20px;box-shadow:0 4px 10px rgba(238,9,121,0.3);letter-spacing:0.5px;text-transform:uppercase}
.tour-main{display:flex;flex-direction:column}
.tour-head{padding:20px 25px 15px}
.tour-title{font-size:1.4rem;font-weight:700;color:#1a202c;margin:0 0 10px;line-height:1.4}
.tour-title a:hover{color:#667eea;text-decoration:none}
.tour-meta-line{display:flex;align-items:center;gap:15px;color:#6c757d;font-weight:600;font-size:0.95rem}
.tour-meta-line i{color:#667eea}
.tour-flags{display:flex;gap:15px;flex-wrap:wrap;margin-top:12px;color:#28a745;font-weight:600;font-size:0.85rem}
.tour-flags i{margin-right:6px}
.tour-tabs{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));border-top:1px solid #f1f3f5;border-bottom:1px solid #f1f3f5;background:#f8f9fa}
.tour-tabs div{padding:12px 10px;border-right:1px solid #e9ecef;font-weight:600;color:#495057;font-size:0.85rem;text-align:center;display:flex;flex-direction:column;align-items:center;gap:5px;transition:all 0.3s ease;cursor:pointer}
.tour-tabs div:hover{background:#fff;color:#667eea}
.tour-tabs div i{font-size:1.1rem;color:#aeb5bc}
.tour-tabs div:hover i{color:#667eea}
.tour-tabs div:last-child{border-right:0}
.tour-bottom{display:flex;align-items:stretch;justify-content:space-between;gap:12px;background:#fff;border-top:1px solid #f1f3f5}
.tour-price{display:flex;flex-direction:column;justify-content:center;flex:1;min-width:0;padding:15px 20px;color:#6c757d;font-size:0.85rem;font-weight:600;text-transform:uppercase}
.tour-price b{font-size:1.6rem;color:#1a202c;margin-top:2px;line-height:1}
.tour-actions{display:flex;align-items:center;justify-content:flex-end;gap:8px;padding:10px 15px;flex-shrink:0}
.tour-cart-form{margin:0;display:flex}
.tour-cart-btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:10px 16px;border:1px solid #667eea;border-radius:6px;background:#fff;color:#667eea;font-weight:700;font-size:0.85rem;text-transform:uppercase;letter-spacing:0.3px;cursor:pointer;transition:all .2s ease;white-space:nowrap}
.tour-cart-btn:hover{background:#eef2ff}
.tour-book-btn{display:inline-flex;align-items:center;justify-content:center;padding:8px 14px;border:0;border-radius:6px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;font-weight:700;font-size:0.78rem;text-transform:uppercase;letter-spacing:0.5px;text-decoration:none;transition:all .2s ease;white-space:nowrap}
.tour-book-btn:hover{background:linear-gradient(135deg,#5a67d8 0%,#6b46c1 100%);color:#fff;box-shadow:0 4px 12px rgba(102,126,234,0.35)}
.right-card{background:#fff;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,0.05);margin-bottom:25px;overflow:hidden;border:1px solid rgba(0,0,0,0.03)}
.right-card .head{padding:18px 20px;border-bottom:1px solid #f1f3f5;font-size:1.2rem;font-weight:700;color:#1a202c;background:#f8f9fa}
.right-card .head i{font-size:1.1rem;color:#adb5bd;cursor:pointer;transition:color 0.3s ease}
.right-card .head i:hover{color:#667eea}
.cart-empty{padding:30px 20px;color:#6c757d;font-weight:600;text-align:center;flex-direction:column;gap:15px !important}
.cart-empty i{font-size:40px !important;color:#e9ecef;margin-bottom:10px}
.cat-list{padding:15px 20px}
.cat-item{display:flex;align-items:center;gap:12px;padding:10px 0;color:#495057;font-weight:600;font-size:0.95rem;transition:all 0.3s ease;border-bottom:1px dashed #f1f3f5}
.cat-item:last-child{border-bottom:none}
.cat-item i{color:#dee2e6;font-size:1.1rem;transition:all 0.3s ease}
.cat-item:hover{color:#667eea;padding-left:5px}
.cat-item:hover i{color:#667eea}
.cat-item.active{color:#667eea}
.cat-item.active i{color:#667eea}
@media (max-width: 1199px){.tours-layout{grid-template-columns:1fr}.tour-row{grid-template-columns:280px minmax(0,1fr)}.tour-actions{padding:10px 12px}}
@media (max-width: 767px){.tours-listing{padding:40px 0}.tours-topbar{flex-direction:column;align-items:flex-start;gap:15px}.tours-filterbar{grid-template-columns:1fr}.tour-row{grid-template-columns:1fr}.tour-row .thumb{height:220px;min-height:220px}.tour-title{font-size:1.2rem}.tour-tabs{grid-template-columns:repeat(2,1fr)}.tour-tabs div{border-bottom:1px solid #e9ecef;padding:15px 10px}.tour-tabs div:nth-child(3),.tour-tabs div:nth-child(4){border-bottom:0}.tour-tabs div:nth-child(even){border-right:0}.tour-bottom{flex-direction:column;align-items:stretch}.tour-price{padding:15px 20px;text-align:center;align-items:center}.tour-actions{justify-content:center;padding:12px 15px 15px;flex-wrap:wrap}.tour-cart-form,.tour-cart-btn,.tour-book-btn{flex:1 1 auto;min-width:120px}}
.tour-tab-btn.active{background:#fff;color:#667eea;box-shadow:inset 0 -3px 0 #667eea}
.tour-tab-btn.active i{color:#667eea}
.tour-info-sidebar{position:fixed;inset:0;z-index:10050;pointer-events:none;visibility:hidden}
.tour-info-sidebar.is-open{pointer-events:auto;visibility:visible}
.tour-info-sidebar__overlay{position:absolute;inset:0;background:rgba(15,23,42,0.45);opacity:0;transition:opacity .3s ease}
.tour-info-sidebar.is-open .tour-info-sidebar__overlay{opacity:1}
.tour-info-sidebar__panel{position:absolute;top:0;right:0;width:min(640px,92vw);max-width:100%;height:100%;background:#fff;box-shadow:-12px 0 40px rgba(15,23,42,0.18);transform:translateX(100%);transition:transform .35s cubic-bezier(.4,0,.2,1);display:flex;flex-direction:column}
.tour-info-sidebar.is-open .tour-info-sidebar__panel{transform:translateX(0)}
.tour-info-sidebar__head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;padding:20px 22px;border-bottom:1px solid #f1f3f5;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff}
.tour-info-sidebar__head h2{margin:0;font-size:1.15rem;font-weight:700;line-height:1.35}
.tour-info-sidebar__head p{margin:6px 0 0;font-size:0.85rem;opacity:0.9}
.tour-info-sidebar__close{border:0;background:rgba(255,255,255,0.2);color:#fff;width:36px;height:36px;border-radius:50%;cursor:pointer;flex-shrink:0;font-size:1.25rem;line-height:1}
.tour-info-sidebar__close:hover{background:rgba(255,255,255,0.32)}
.tour-info-sidebar__tabs{display:flex;gap:0;border-bottom:1px solid #e9ecef;background:#f8f9fa;overflow-x:auto;-webkit-overflow-scrolling:touch}
.tour-info-sidebar__tab{flex:1;min-width:90px;border:0;background:transparent;padding:12px 8px;font-size:0.78rem;font-weight:700;color:#6c757d;cursor:pointer;border-bottom:3px solid transparent;white-space:nowrap}
.tour-info-sidebar__tab.is-active{color:#667eea;border-bottom-color:#667eea;background:#fff}
.tour-info-sidebar__body{flex:1;overflow-y:auto;padding:22px;color:#374151;font-size:0.95rem;line-height:1.65}
.tour-info-sidebar__body h4{margin:0 0 10px;font-size:1rem;color:#1a202c}
.tour-info-sidebar__body .lead{color:#6c757d;font-size:1rem;margin-bottom:12px}
.tour-info-sidebar__list{margin:0;padding:0;list-style:none}
.tour-info-sidebar__list li{display:flex;gap:10px;padding:8px 0;border-bottom:1px dashed #f1f3f5}
.tour-info-sidebar__list li i{margin-top:4px;color:#28a745;flex-shrink:0}
.tour-info-sidebar__list li.is-exclude i{color:#dc3545}
.tour-info-sidebar__day{padding:12px 0;border-bottom:1px solid #f1f3f5}
.tour-info-sidebar__day strong{display:block;color:#1a202c;margin-bottom:4px}
.tour-info-sidebar__meta{display:grid;gap:10px;margin-bottom:16px}
.tour-info-sidebar__meta div{background:#f8f9fa;border-radius:8px;padding:12px 14px}
.tour-info-sidebar__meta span{display:block;font-size:0.75rem;text-transform:uppercase;color:#6c757d;font-weight:700;letter-spacing:0.04em}
.tour-info-sidebar__meta b{font-size:1rem;color:#1a202c}
.tour-info-sidebar__empty{color:#6c757d;font-style:italic}
.tour-info-sidebar__foot{padding:14px 18px 18px;border-top:1px solid #f1f3f5;background:#fff;flex-shrink:0}
.tour-info-sidebar__foot-actions{display:flex;flex-wrap:wrap;align-items:center;gap:8px;width:100%}
.tour-info-sidebar__foot a,.tour-info-sidebar__foot button{text-decoration:none;font-weight:700;border-radius:8px;cursor:pointer;transition:all .2s ease;box-sizing:border-box;font-family:inherit}
.tour-info-sidebar__foot .btn-view{flex:1 1 100%;text-align:center;padding:8px 12px;font-size:0.82rem;color:#667eea;border:1px solid #e9ecef;background:#f8f9fa}
.tour-info-sidebar__foot .btn-view:hover{background:#eef2ff;border-color:#667eea}
.tour-info-sidebar__foot .btn-cart{flex:1 1 auto;min-width:0;width:100%;text-align:center;padding:10px 14px;font-size:0.88rem;border:1px solid #667eea;color:#667eea;background:#fff}
.tour-info-sidebar__foot .btn-cart i{margin-right:6px;font-size:0.85em}
.tour-info-sidebar__foot .btn-cart:hover{background:#eef2ff}
.tour-info-sidebar__foot .btn-book{flex:0 0 auto;padding:8px 16px;font-size:0.8rem;border:0;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;white-space:nowrap}
.tour-info-sidebar__foot .btn-book:hover{filter:brightness(1.05);box-shadow:0 4px 12px rgba(102,126,234,0.35)}
.tour-info-sidebar__foot-form{flex:1 1 auto;min-width:0;margin:0;display:flex}
.tour-info-sidebar__foot-form .btn-cart{width:100%}
body.tour-sidebar-open{overflow:hidden}
@media (max-width:991px){
.tour-info-sidebar__panel{width:min(560px,100%)}
.tour-info-sidebar__head{padding:16px 18px}
.tour-info-sidebar__body{padding:18px 16px}
}
@media (min-width:576px){
.tour-info-sidebar__foot-actions{flex-wrap:wrap}
.tour-info-sidebar__foot .btn-view{flex:1 1 100%;order:3}
.tour-info-sidebar__foot-form{order:1;flex:1 1 0;min-width:140px;max-width:calc(100% - 120px)}
.tour-info-sidebar__foot .btn-book{order:2}
}
@media (max-width:575px){
.tour-info-sidebar__panel{width:100%}
.tour-info-sidebar__tabs{flex-wrap:nowrap}
.tour-info-sidebar__tab{min-width:72px;font-size:0.72rem;padding:10px 6px}
.tour-info-sidebar__head h2{font-size:1rem}
.tour-info-sidebar__foot{padding:12px 14px 16px}
.tour-info-sidebar__foot-actions{flex-direction:column;align-items:stretch}
.tour-info-sidebar__foot-form{order:1;width:100%;max-width:none}
.tour-info-sidebar__foot .btn-book{order:2;width:100%;text-align:center;padding:10px 14px;font-size:0.85rem}
.tour-info-sidebar__foot .btn-view{order:3;flex:1 1 auto;margin-top:4px}
}
</style>';
$extra_js = '<script>window.TOURS_PANEL_DATA = ' . json_encode($tours_panel_data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) . ';</script>'
    . jsWithCache('assets/js/tours-list.js');

// Include header
include 'includes/header.php';
?>

    <!-- Page Header -->
    <section class="page-header">
        <div class="container">
            <h1>Our Amazing Tours</h1>
            <ul class="travhub-breadcrumb list-unstyled">
                <li><a href="<?php echo navUrl('home'); ?>">Home</a></li>
                <li>Tours</li>
            </ul>
        </div>
    </section>

    <!-- Filters Section - Commented Out
    <section class="filter-section" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 50%, #f8f9fa 100%); padding: 40px 0; margin-bottom: 50px; position: relative; overflow: hidden;">
        <!-- Background decorative elements -->
        <div style="position: absolute; top: -30px; right: -30px; width: 120px; height: 120px; background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%); border-radius: 50%; animation: float 8s ease-in-out infinite;"></div>
        <div style="position: absolute; bottom: -50px; left: -50px; width: 180px; height: 180px; background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%); border-radius: 50%; animation: float 10s ease-in-out infinite reverse;"></div>
        
        <div class="container">
      
        </div>
        
        <style>
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-15px); }
        }
        
        /* Filter Input Enhancements */
        .filter-input::placeholder,
        .filter-select option {
            color: rgba(255, 255, 255, 0.7) !important;
        }
        
        .filter-input:focus,
        .filter-select:focus {
            background: #34495e !important;
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3) !important;
            transform: translateY(-2px) !important;
            outline: none !important;
        }
        
        .filter-input:hover,
        .filter-select:hover {
            background: #34495e !important;
            transform: translateY(-2px) !important;
        }
        
        .filter-select option {
            background: #2c3e50 !important;
            color: white !important;
            padding: 8px !important;
        }
        
        /* Button hover effect */
        button[type="submit"]:hover {
            transform: translateY(-3px) scale(1.05) !important;
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.6) !important;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .row.g-4 {
                gap: 15px !important;
            }
            
            .filter-input,
            .filter-select,
            button[type="submit"] {
                margin-bottom: 10px;
            }
        }
        </style>
    </section>
    -->

    <!-- Tours New Layout -->
    <div class="tours-listing">
        <div class="container">
            <!-- Topbar -->
            <div class="tours-topbar">
                <div class="left">
                    <i class="fas fa-camera"></i>
                    <?php echo count($tours); ?> Things to do <?php echo $destination ? 'in '.htmlspecialchars($destination) : ''; ?>
                </div>
                <div class="right">
                    Modify Search <i class="fas fa-plus-circle ms-2"></i>
                </div>
            </div>

            <div class="tours-layout">
                <!-- Main Content -->
                <div class="tours-main">
                    <!-- Filterbar -->
                    <div class="tours-filterbar">
                        <div class="sort-wrap">
                            <span>Sort results by:</span>
                            <form method="GET" action="" class="sort-form" id="sortForm">
                                <?php if($search): ?><input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>"><?php endif; ?>
                                <?php if($category): ?><input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>"><?php endif; ?>
                                <?php if($destination): ?><input type="hidden" name="destination" value="<?php echo htmlspecialchars($destination); ?>"><?php endif; ?>
                                <select name="sort" class="tours-sort-select" aria-label="Sort tours" onchange="document.getElementById('sortForm').submit();">
                                    <option value="popular" <?php echo $sort === 'popular' ? 'selected' : ''; ?>>Most Popular</option>
                                    <option value="price_low" <?php echo $sort === 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                                    <option value="price_high" <?php echo $sort === 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                                    <option value="latest" <?php echo $sort === 'latest' ? 'selected' : ''; ?>>Latest</option>
                                </select>
                            </form>
                        </div>
                        <div class="search-wrap">
                            <form method="GET" action="" style="margin:0;">
                                <?php if($category): ?><input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>"><?php endif; ?>
                                <?php if($destination): ?><input type="hidden" name="destination" value="<?php echo htmlspecialchars($destination); ?>"><?php endif; ?>
                                <input type="text" name="search" placeholder="Search Your Tour" value="<?php echo htmlspecialchars($search); ?>">
                                <button type="submit"><i class="fas fa-search"></i></button>
                            </form>
                        </div>
                    </div>

                    <?php if (empty($tours)): ?>
                        <div class="alert alert-info">
                            <h4>No tours found</h4>
                            <p>Try adjusting your search criteria or browse all tours.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($tours as $tour): ?>
                            <div class="tour-row" data-tour-id="<?php echo (int) $tour['id']; ?>">
                                <div class="thumb">
                                    <?php 
                                    $image_path = !empty($tour['featured_image']) && file_exists($tour['featured_image']) 
                                        ? BASE_URL . htmlspecialchars($tour['featured_image']) 
                                        : BASE_URL . 'assets/images/tours/default-tour.jpg';
                                    ?>
                                    <a href="<?php echo tourUrl($tour['slug']); ?>" style="display:block; height:100%;">
                                        <img src="<?php echo $image_path; ?>" alt="<?php echo htmlspecialchars($tour['title']); ?>" onerror="this.src='<?php echo BASE_URL; ?>assets/images/tours/default-tour.jpg'">
                                    </a>
                                    <?php if ($tour['featured']): ?>
                                        <div class="elite-badge">Elite</div>
                                    <?php endif; ?>
                                </div>
                                <div class="tour-main">
                                    <div class="tour-head">
                                        <h3 class="tour-title">
                                            <a href="<?php echo tourUrl($tour['slug']); ?>" style="color:inherit; text-decoration:none;">
                                                <?php echo htmlspecialchars($tour['title']); ?>
                                            </a>
                                        </h3>
                                        <div class="tour-meta-line">
                                            <span><i class="far fa-clock"></i> Duration: <?php echo $tour['duration_days']; ?> Days</span>
                                        </div>
                                        <div class="tour-flags">
                                            <span><i class="far fa-check-circle"></i> IMPORTANT INFORMATION</span>
                                            <span><i class="far fa-check-circle"></i> REFUNDABLE</span>
                                        </div>
                                    </div>
                                    <div style="flex:1;"></div>
                                    <div class="tour-tabs">
                                        <div class="tour-tab-btn" role="button" tabindex="0" data-tour-tab="description" data-tour-id="<?php echo (int) $tour['id']; ?>"><i class="far fa-file-alt"></i> Description</div>
                                        <div class="tour-tab-btn" role="button" tabindex="0" data-tour-tab="inclusion" data-tour-id="<?php echo (int) $tour['id']; ?>"><i class="fas fa-pen-square"></i> Inclusion</div>
                                        <div class="tour-tab-btn" role="button" tabindex="0" data-tour-tab="timings" data-tour-id="<?php echo (int) $tour['id']; ?>"><i class="far fa-clock"></i> Timings</div>
                                        <div class="tour-tab-btn" role="button" tabindex="0" data-tour-tab="useful" data-tour-id="<?php echo (int) $tour['id']; ?>"><i class="fas fa-info-circle"></i> Useful Info</div>
                                    </div>
                                    <div class="tour-bottom">
                                        <div class="tour-price">
                                            FROM INR <b>₹ <?php echo number_format($tour['discount_price'] ?: $tour['price'], 2); ?></b>
                                        </div>
                                        <div class="tour-actions">
                                            <?php
                                            $default_people = max((int) ($tour['min_people'] ?? 1), min(2, (int) ($tour['max_people'] ?? 8)));
                                            $tomorrow_date = date('Y-m-d', strtotime('+1 day'));
                                            ?>
                                            <form method="POST" action="<?php echo htmlspecialchars(navUrl('cart')); ?>" class="tour-cart-form">
                                                <input type="hidden" name="action" value="add">
                                                <input type="hidden" name="tour_id" value="<?php echo (int) $tour['id']; ?>">
                                                <input type="hidden" name="tour_date" value="<?php echo $tomorrow_date; ?>">
                                                <input type="hidden" name="people" value="<?php echo $default_people; ?>">
                                                <input type="hidden" name="return_url" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'] ?? ''); ?>">
                                                <button type="submit" class="tour-cart-btn">
                                                    <i class="fas fa-shopping-cart"></i> Add to Cart
                                                </button>
                                            </form>
                                            <a href="<?php echo bookingUrl((int) $tour['id']); ?>" class="tour-book-btn">Book Now</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Sidebar -->
                <div class="tours-sidebar">
                    <div class="right-card">
                        <div class="head" style="display:flex; justify-content:space-between; align-items:center;">
                            <span>Shopping Cart | <?php echo $cart_count; ?></span>
                            <i class="fas fa-minus-circle" style="color:#c9cdd3; font-size:24px;"></i>
                        </div>
                        <?php if ($cart_count > 0): ?>
                            <div class="cart-items" style="padding: 16px;">
                                <a href="<?php echo navUrl('cart'); ?>" class="btn btn-primary w-100">View Cart</a>
                            </div>
                        <?php else: ?>
                            <div class="cart-empty" style="display:flex; align-items:center; gap:14px;">
                                <i class="fas fa-shopping-cart" style="font-size:32px;"></i>
                                <span>Your Shopping Cart Is<br>Empty!</span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="right-card">
                        <div class="head" style="display:flex; justify-content:space-between; align-items:center;">
                            <span>Tour Category</span>
                            <i class="fas fa-minus-circle" style="color:#c9cdd3; font-size:24px;"></i>
                        </div>
                        <div class="cat-list">
                            <?php foreach ($categories as $cat): ?>
                                <a href="<?php echo $build_tours_url(['category' => $cat['slug']]); ?>" 
                                   class="cat-item <?php echo $category === $cat['slug'] ? 'active' : ''; ?>" 
                                   style="text-decoration:none;">
                                    <i class="fas fa-check-circle"></i>
                                    <span style="text-transform:uppercase;"><?php echo htmlspecialchars($cat['name']); ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tour info slide sidebar -->
    <div id="tourInfoSidebar" class="tour-info-sidebar" aria-hidden="true">
        <div class="tour-info-sidebar__overlay" data-tour-sidebar-close></div>
        <aside class="tour-info-sidebar__panel" role="dialog" aria-modal="true" aria-labelledby="tourInfoSidebarTitle">
            <header class="tour-info-sidebar__head">
                <div>
                    <h2 id="tourInfoSidebarTitle">Tour details</h2>
                    <p id="tourInfoSidebarMeta"></p>
                </div>
                <button type="button" class="tour-info-sidebar__close" data-tour-sidebar-close aria-label="Close">&times;</button>
            </header>
            <nav class="tour-info-sidebar__tabs" id="tourInfoSidebarTabs">
                <button type="button" class="tour-info-sidebar__tab is-active" data-panel-tab="description">Description</button>
                <button type="button" class="tour-info-sidebar__tab" data-panel-tab="inclusion">Inclusion</button>
                <button type="button" class="tour-info-sidebar__tab" data-panel-tab="timings">Timings</button>
                <button type="button" class="tour-info-sidebar__tab" data-panel-tab="useful">Useful Info</button>
            </nav>
            <div class="tour-info-sidebar__body" id="tourInfoSidebarBody"></div>
            <footer class="tour-info-sidebar__foot">
                <div class="tour-info-sidebar__foot-actions">
                    <form method="POST" action="<?php echo htmlspecialchars(navUrl('cart')); ?>" class="tour-info-sidebar__foot-form" id="tourInfoSidebarCartForm">
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="tour_id" id="tourInfoSidebarTourId" value="">
                        <input type="hidden" name="tour_date" id="tourInfoSidebarTourDate" value="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
                        <input type="hidden" name="people" id="tourInfoSidebarPeople" value="2">
                        <input type="hidden" name="return_url" id="tourInfoSidebarReturnUrl" value="">
                        <button type="submit" class="btn-cart">
                            <i class="fas fa-shopping-cart"></i> Add to Cart
                        </button>
                    </form>
                    <a href="#" class="btn-book" id="tourInfoSidebarBookLink">Book Now</a>
                    <a href="#" class="btn-view" id="tourInfoSidebarViewLink">View full tour</a>
                </div>
            </footer>
        </aside>
    </div>

<?php include 'includes/footer.php'; ?>

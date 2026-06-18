<?php
require_once 'config/config.php';
require_once 'includes/cab_options.php';
require_once 'includes/html_helpers.php';

// Get tour slug
$slug = $_GET['slug'] ?? '';

if (!$slug) {
    header('Location: tours.php');
    exit;
}

// Get tour details
$tour = $db->fetch("
    SELECT t.*, d.name as destination_name, d.country, d.description as destination_description
    FROM tours t 
    LEFT JOIN destinations d ON t.destination_id = d.id
    WHERE t.slug = ? AND t.status = 'active'
", [$slug]);

if (!$tour) {
    header('Location: tours.php');
    exit;
}

// Parse JSON fields
$inclusions = json_decode($tour['inclusions'], true) ?: [];
$exclusions = json_decode($tour['exclusions'], true) ?: [];
$itinerary = json_decode($tour['itinerary'], true) ?: [];

$availability = '';
if (!empty($tour['availability_start']) || !empty($tour['availability_end'])) {
    $start = !empty($tour['availability_start']) ? date('d M Y', strtotime($tour['availability_start'])) : 'Open';
    $end = !empty($tour['availability_end']) ? date('d M Y', strtotime($tour['availability_end'])) : 'Open';
    $availability = $start . ' — ' . $end;
}

$defaultPeople = max((int) ($tour['min_people'] ?? 1), min(2, (int) ($tour['max_people'] ?? 8)));
$tomorrow = date('Y-m-d', strtotime('+1 day'));
$tour_price = (float) ($tour['discount_price'] ?: $tour['price']);

// Get related tours
$related_tours = $db->fetchAll("
    SELECT t.*, d.name as destination_name
    FROM tours t 
    LEFT JOIN destinations d ON t.destination_id = d.id
    WHERE t.destination_id = ? AND t.id != ? AND t.status = 'active'
    LIMIT 3
", [$tour['destination_id'], $tour['id']]);

// Initialize cab options with error handling
$availableCabs = [];
$cab_functionality_enabled = false;

try {
    if (file_exists('includes/cab_options.php')) {
        // Test if cab_types table exists
        $db->fetch("SELECT COUNT(*) as count FROM cab_types LIMIT 1");
        $cabOptions = new CabOptions($db);
        $availableCabs = $cabOptions->getCabOptionsForDropdown();
        $cab_functionality_enabled = true;
    }
} catch (Exception $e) {
    // Cab functionality not available, continue without it
    $availableCabs = [];
    $cab_functionality_enabled = false;
}

$tourCabList = [];
if (!empty($availableCabs)) {
    foreach ($availableCabs as $cab) {
        $tourPrice = getCabPriceForTour($tour['title'], $cab['value'], $db);
        $tourCabList[] = array_merge($cab, [
            'tour_price' => $tourPrice > 0 ? (float) $tourPrice : (float) ($cab['price'] ?? 0),
        ]);
    }
} else {
    foreach (getDefaultCabPricing() as $cab) {
        $tourPrice = getCabPriceForTour($tour['title'], $cab['name'], $db);
        $tourCabList[] = [
            'value' => $cab['name'],
            'display_name' => $cab['display_name'],
            'max_passengers' => (int) $cab['max_passengers'],
            'description' => $cab['description'],
            'image_url' => cabTypeImageUrl($cab),
            'tour_price' => $tourPrice > 0 ? (float) $tourPrice : (float) $cab['base_price'],
        ];
    }
}

// Set page variables
$page_title = htmlspecialchars($tour['title']) . ' - ' . getSetting('site_name');
$current_page = 'tours';
$extra_css = '<style>

/* Enhanced Price Box Design */
.price-box {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 0;
    border-radius: 20px;
    position: sticky;
    top: 100px;
    box-shadow: 0 20px 60px rgba(102, 126, 234, 0.4);
    overflow: hidden;
    transition: all 0.3s ease;
}

.price-box:hover {
    transform: translateY(-5px);
    box-shadow: 0 25px 70px rgba(102, 126, 234, 0.5);
}

/* Price Header Section */
.price-box .price-header {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    padding: 30px;
    text-align: center;
    border-bottom: 1px solid rgba(255, 255, 255, 0.2);
}

.price-box .price-amount {
    font-size: 3rem;
    font-weight: 800;
    line-height: 1;
    margin-bottom: 5px;
    text-shadow: 2px 2px 10px rgba(0, 0, 0, 0.2);
}

.price-box .price-original {
    font-size: 1.5rem;
    text-decoration: line-through;
    opacity: 0.6;
    margin-right: 10px;
}

.price-box .price-discount {
    background: linear-gradient(135deg, #f09433 0%, #e6683c 100%);
    color: white;
    padding: 6px 15px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
    display: inline-block;
    margin-top: 10px;
    box-shadow: 0 4px 15px rgba(240, 148, 51, 0.4);
}

.price-box .price-label {
    font-size: 0.9rem;
    opacity: 0.9;
    margin-top: 10px;
}

/* Price Body Section */
.price-box .price-body {
    padding: 30px;
}

.price-box .tour-info-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    transition: all 0.3s ease;
}

.price-box .tour-info-item:last-child {
    border-bottom: none;
}

.price-box .tour-info-item:hover {
    padding-left: 10px;
    background: rgba(255, 255, 255, 0.05);
    margin: 0 -10px;
    padding-right: 10px;
    border-radius: 10px;
}

.price-box .tour-info-item i {
    color: #ffd700;
    margin-right: 10px;
    font-size: 1.1rem;
}

.price-box .tour-info-item .label {
    display: flex;
    align-items: center;
    font-weight: 500;
    opacity: 0.95;
    text-transform: capitalize;
}

.price-box .tour-info-item .value {
    font-weight: 700;
    font-size: 1.05rem;
    text-transform: capitalize;
}

/* Form Section */
.price-box .booking-form {
    padding: 28px 30px 30px 30px;
    border-top: 1px solid rgba(255, 255, 255, 0.15);
    background: rgba(255, 255, 255, 0.04);
    width: 100%;
    box-sizing: border-box;
}

.price-box .booking-form form {
    display: flex;
    flex-direction: column;
    width: 100%;
}

.price-box .booking-form .form-control,
.price-box .booking-form .form-select,
.price-box .booking-form .date-input-wrapper {
    width: 100%;
    box-sizing: border-box;
}

/* CUSTOM LABEL FOR PRICE BOX (NON-FLOATING) */
.price-box .booking-form-label {
    color: rgba(255, 255, 255, 0.95) !important;
    font-weight: 700 !important;
    margin-bottom: 12px !important;
    margin-top: 0 !important;
    font-size: 0.85rem !important;
    text-transform: uppercase !important;
    letter-spacing: 0.08em !important;
    opacity: 1;
    display: block !important;
    line-height: 1.5 !important;
    width: 100%;
    font-family: inherit;
    position: static !important;
    transform: none !important;
    pointer-events: auto !important;
    padding: 0 !important;
}

.price-box .booking-form-label i {
    color: #ffd700 !important;
    font-size: 1rem !important;
    margin-right: 8px;
    display: inline-block;
    vertical-align: middle;
}

.price-box .booking-form .mb-3 {
    margin-bottom: 24px !important;
    width: 100%;
    display: flex;
    flex-direction: column;
}

.price-box .booking-form .mb-3:last-child {
    margin-bottom: 0 !important;
}

.price-box .form-control,
.price-box .form-select {
    border: none !important;
    border-radius: 12px !important;
    padding: 14px 16px !important;
    background: #ffffff !important;
    color: #1f2937 !important;
    font-weight: 500 !important;
    font-size: 0.95rem !important;
    font-family: inherit !important;
    transition: all 0.3s ease;
    width: 100% !important;
    box-sizing: border-box !important;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    line-height: 1.5 !important;
    cursor: pointer;
    display: block;
    position: relative;
    vertical-align: middle;
    margin-top: 0 !important;
    margin-bottom: 0 !important;
}

.price-box .form-select {
    color: #1f2937 !important;
    background-color: #ffffff !important;
    height: auto;
    min-height: 48px;
    font-weight: 500 !important;
    font-size: 0.95rem !important;
    font-family: inherit !important;
}

.price-box .form-select:not(:focus) {
    color: #1f2937;
}

.price-box .form-select option {
    color: #1f2937 !important;
    font-weight: 500 !important;
    font-size: 0.95rem !important;
    font-family: inherit !important;
    padding: 10px !important;
    background: white !important;
    text-transform: none !important;
}

.price-box .form-select option:checked {
    color: #1f2937 !important;
    font-weight: 500 !important;
    background: #f8f9fa;
}

.price-box .form-control:hover,
.price-box .form-select:hover {
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.15);
}

.price-box .form-control::placeholder {
    color: rgba(31, 41, 55, 0.5);
    text-transform: none !important;
}

.price-box .form-control:focus,
.price-box .form-select:focus {
    border: none;
    background: #ffffff;
    box-shadow: 0 4px 12px rgba(255, 215, 0, 0.3);
    outline: none;
    color: #111827;
    transform: translateY(-1px);
}

.price-box .form-select {
    background-image: url("data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIxMiIgaGVpZ2h0PSIxMiIgdmlld0JveD0iMCAwIDEyIDEyIj48cGF0aCBmaWxsPSIjMzMzIiBkPSJNNiA5TDEgNGgxMHoiLz48L3N2Zz4=");
    background-repeat: no-repeat;
    background-position: right 16px center;
    padding-right: 40px;
    line-height: 1.5;
    vertical-align: middle;
    text-align: left;
    text-align-last: left;
}

.price-box .form-select::-ms-expand {
    display: none;
}

/* Date Input with Calendar Icon */
.price-box .date-input-wrapper {
    position: relative;
    display: flex;
    align-items: center;
    width: 100%;
}

.price-box .date-input-wrapper .date-icon {
    position: absolute;
    right: 16px;
    top: 50%;
    transform: translateY(-50%);
    color: #667eea;
    pointer-events: none;
    font-size: 1.1rem;
    z-index: 1;
    line-height: 1;
}

.price-box .form-control[type="date"] {
    padding-right: 45px;
    cursor: pointer;
    position: relative;
    width: 100%;
    box-sizing: border-box;
}

.price-box .form-control[type="date"]::-webkit-calendar-picker-indicator {
    position: absolute;
    right: 0;
    width: 100%;
    height: 100%;
    opacity: 0;
    cursor: pointer;
    z-index: 2;
}

/* Ensure select dropdown arrow is visible */
.price-box .form-select {
    cursor: pointer;
}

.price-box .form-select:hover {
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.15);
}

.price-box .form-text {
    color: rgba(255, 255, 255, 0.9);
    font-size: 0.8rem;
    margin-top: 8px;
    display: flex;
    align-items: center;
    gap: 6px;
    font-weight: 400;
    text-transform: none !important;
}

.price-box .form-text i {
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.75rem;
}

/* Book Button */
.price-box .btn-book {
    background: linear-gradient(135deg, #1bbc9b 0%, #17a689 100%);
    color: white;
    border: none;
    border-radius: 12px;
    padding: 16px 30px;
    font-weight: 700;
    font-size: 1.05rem;
    width: 100%;
    transition: all 0.3s ease;
    box-shadow: 0 8px 25px rgba(27, 188, 155, 0.4);
    position: relative;
    overflow: hidden;
    margin-top: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    cursor: pointer;
    text-transform: uppercase !important;
    letter-spacing: 0.05em !important;
}

.price-box .btn-book::before {
    content: \'\';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
    transition: left 0.5s ease;
}

.price-box .btn-book:hover {
    transform: translateY(-2px);
    box-shadow: 0 15px 40px rgba(27, 188, 155, 0.6);
}

.price-box .btn-book:hover::before {
    left: 100%;
}

.price-box .btn-book:active {
    transform: translateY(0);
}

.price-box .btn-book i {
    margin-right: 10px;
}

/* Security Badge */
.price-box .security-badge {
    text-align: center;
    padding: 12px 15px;
    background: rgba(255, 255, 255, 0.05);
    margin: 20px 0 0 0;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.price-box .security-badge small {
    color: rgba(255, 255, 255, 0.95);
    font-size: 0.8rem;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 6px;
    text-transform: none !important;
}

.price-box .security-badge i {
    color: #ffd700;
    font-size: 0.9rem;
}

/* Divider */
.price-box .divider {
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
    margin: 0;
    border: none;
}

/* Contact Section */
.price-box .contact-section {
    padding: 25px 30px 30px 30px;
    background: rgba(255, 255, 255, 0.05);
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    margin-top: 0;
}

.price-box .contact-section h6 {
    font-weight: 700;
    margin-bottom: 18px;
    font-size: 1.05rem;
    display: flex;
    align-items: center;
    gap: 10px;
    color: rgba(255, 255, 255, 0.95);
    text-transform: none !important;
}

.price-box .contact-section h6 i {
    color: #ffd700;
    font-size: 1.1rem;
}

.price-box .contact-item {
    display: flex;
    align-items: center;
    padding: 14px 16px;
    margin-bottom: 12px;
    background: rgba(255, 255, 255, 0.12);
    border-radius: 12px;
    transition: all 0.3s ease;
    width: 100%;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.price-box .contact-item:last-child {
    margin-bottom: 0;
}

.price-box .contact-item:hover {
    background: rgba(255, 255, 255, 0.18);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.price-box .contact-item i {
    color: #ffd700;
    margin-right: 12px;
    font-size: 1.1rem;
    width: 22px;
    text-align: center;
    flex-shrink: 0;
}

.price-box .contact-item a {
    color: rgba(255, 255, 255, 0.95);
    text-decoration: none;
    font-weight: 500;
    font-size: 0.95rem;
    flex: 1;
    text-transform: none !important;
}

/* Responsive Design */
@media (max-width: 991px) {
    .price-box {
        position: relative;
        top: 0;
        margin-top: 40px;
    }
}
@media (max-width: 768px) {
    .price-box .booking-form {
        padding: 20px !important;
    }
    
    .price-box .booking-form-label {
        margin-bottom: 8px !important;
        font-size: 0.8rem !important;
        position: relative !important;
        top: 0 !important;
        left: 0 !important;
        transform: none !important;
        padding: 0 !important;
    }
    
    .price-box .booking-form .mb-3 {
        margin-bottom: 20px !important;
    }
    
    .price-box .form-control,
    .price-box .form-select {
        padding: 12px 14px !important;
        font-size: 0.9rem !important;
    }
    
    /* Ensure date input icon stays properly positioned */
    .price-box .date-input-wrapper {
        position: relative;
    }
    
    .price-box .date-input-wrapper .date-icon {
        right: 14px !important;
        font-size: 1rem !important;
    }
    
    .price-box .form-control[type="date"] {
        padding-right: 40px !important;
    }
}

@media (max-width: 480px) {
    .price-box .booking-form {
        padding: 16px !important;
    }
    
    .price-box .booking-form-label {
        margin-bottom: 6px !important;
        font-size: 0.75rem !important;
    }
    
    .price-box .form-control,
    .price-box .form-select {
        padding: 10px 12px !important;
        font-size: 0.85rem !important;
        border-radius: 10px !important;
    }
    
    .price-box .btn-book {
        padding: 14px 20px !important;
        font-size: 0.95rem !important;
    }
}
@media (max-width: 768px) {
    .price-box .price-amount {
        font-size: 2.5rem;
    }
    
    .price-box .price-header,
    .price-box .price-body,
    .price-box .booking-form,
    .price-box .contact-section {
        padding: 20px;
    }
}

/* Animation for elements */
@keyframes slideInRight {
    from {
        opacity: 0;
        transform: translateX(30px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.price-box .tour-info-item {
    animation: slideInRight 0.5s ease forwards;
}

.price-box .tour-info-item:nth-child(1) { animation-delay: 0.1s; }
.price-box .tour-info-item:nth-child(2) { animation-delay: 0.2s; }
.price-box .tour-info-item:nth-child(3) { animation-delay: 0.3s; }
.price-box .tour-info-item:nth-child(4) { animation-delay: 0.4s; }

/* Itinerary Styles */
.itinerary-day {
    border-left: 3px solid #667eea;
    padding-left: 20px;
    margin-bottom: 30px;
    position: relative;
    background: #f8f9fa;
    padding: 20px 20px 20px 25px;
    border-radius: 0 10px 10px 0;
    transition: all 0.3s ease;
}

.itinerary-day:hover {
    background: white;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    transform: translateX(5px);
}

.itinerary-day::before {
    content: "";
    position: absolute;
    left: -8px;
    top: 25px;
    width: 13px;
    height: 13px;
    background: #667eea;
    border-radius: 50%;
    box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.2);
}

/* Feature List Updates */
.feature-list {
    list-style: none;
    padding: 0;
}
/* ... rest of previous feature-list styles ... */
.feature-list li {
    padding: 10px 15px;
    border-bottom: 1px solid #eee;
    display: flex;
    align-items: center;
    background: #fff;
    margin-bottom: 8px;
    border-radius: 8px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.02);
    color: #495057; /* Ensure dark text for list items */
}

.feature-list li:last-child {
    border-bottom: none;
    margin-bottom: 0;
}

.feature-list li i {
    color: #28a745;
    margin-right: 12px;
    background: rgba(40, 167, 69, 0.1);
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    font-size: 0.8rem;
    flex-shrink: 0;
}

.feature-list li i.fa-times {
    color: #dc3545;
    background: rgba(220, 53, 69, 0.1);
}

/* Responsive Design */
@media (min-width: 768px) {
    .tour-details-grid {
        display: flex !important;
        flex-wrap: wrap !important;
    }

    .tour-details-grid > .col-md-8 {
        flex: 0 0 66.66666667% !important;
        width: 66.66666667% !important;
        max-width: 66.66666667% !important;
    }

    .tour-details-grid > .col-md-4 {
        flex: 0 0 33.33333333% !important;
        width: 33.33333333% !important;
        max-width: 33.33333333% !important;
    }

    .related-tours-grid {
        display: flex !important;
        flex-wrap: wrap !important;
    }

    .related-tours-grid > .col-md-4 {
        flex: 0 0 33.33333333% !important;
        width: 33.33333333% !important;
        max-width: 33.33333333% !important;
    }
}

@media (min-width: 992px) {
    .tour-details-grid {
        display: flex !important;
        flex-wrap: wrap !important;
    }

    .tour-details-grid > .col-lg-8 {
        flex: 0 0 66.66666667% !important;
        width: 66.66666667% !important;
        max-width: 66.66666667% !important;
    }

    .tour-details-grid > .col-lg-4 {
        flex: 0 0 33.33333333% !important;
        width: 33.33333333% !important;
        max-width: 33.33333333% !important;
    }
}

</style>';

$extra_css .= '<style>
.tour-detail-card{background:#fff;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,0.08);margin-bottom:28px;overflow:hidden;border:1px solid rgba(0,0,0,0.03)}
.tour-detail-card .tour-head{padding:20px 25px 15px}
.tour-detail-card .tour-meta-line{display:flex;align-items:center;gap:15px;color:#6c757d;font-weight:600;font-size:0.95rem;margin-bottom:12px}
.tour-detail-card .tour-meta-line i{color:#667eea}
.tour-detail-card .tour-flags{display:flex;gap:15px;flex-wrap:wrap;color:#28a745;font-weight:600;font-size:0.85rem}
.tour-detail-card .tour-flags i{margin-right:6px}
.tour-tabs{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));border-top:1px solid #f1f3f5;border-bottom:1px solid #f1f3f5;background:#f8f9fa}
.tour-tabs div{padding:12px 10px;border-right:1px solid #e9ecef;font-weight:600;color:#495057;font-size:0.85rem;text-align:center;display:flex;flex-direction:column;align-items:center;gap:5px;transition:all 0.3s ease;cursor:pointer}
.tour-tabs div:hover{background:#fff;color:#667eea}
.tour-tabs div i{font-size:1.1rem;color:#aeb5bc}
.tour-tabs div:hover i{color:#667eea}
.tour-tabs div:last-child{border-right:0}
.tour-bottom{display:flex;align-items:stretch;justify-content:space-between;gap:12px;background:#fff;border-top:1px solid #f1f3f5}
.tour-bottom--top{border-top:0;border-bottom:1px solid #f1f3f5}
.tour-price{display:flex;flex-direction:column;justify-content:center;flex:1;min-width:0;padding:15px 20px;color:#6c757d;font-size:0.85rem;font-weight:600;text-transform:uppercase}
.tour-price b{font-size:1.6rem;color:#1a202c;margin-top:2px;line-height:1}
.tour-price-note{display:block;margin-top:6px;font-size:0.72rem;font-weight:500;color:#868e96;text-transform:none;letter-spacing:0;line-height:1.35;font-style:italic}
.tour-actions{display:flex;align-items:center;justify-content:flex-end;gap:8px;padding:10px 15px;flex-shrink:0}
.tour-cart-form{margin:0;display:flex}
.tour-cart-btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:10px 16px;border:1px solid #667eea;border-radius:6px;background:#fff;color:#667eea;font-weight:700;font-size:0.85rem;text-transform:uppercase;letter-spacing:0.3px;cursor:pointer;transition:all .2s ease;white-space:nowrap}
.tour-cart-btn:hover{background:#eef2ff}
.tour-book-btn{display:inline-flex;align-items:center;justify-content:center;padding:8px 14px;border:0;border-radius:6px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;font-weight:700;font-size:0.78rem;text-transform:uppercase;letter-spacing:0.5px;text-decoration:none;transition:all .2s ease;white-space:nowrap}
.tour-book-btn:hover{background:linear-gradient(135deg,#5a67d8 0%,#6b46c1 100%);color:#fff;box-shadow:0 4px 12px rgba(102,126,234,0.35)}
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
.tour-info-sidebar--detail .btn-view{display:none}
body.tour-sidebar-open{overflow:hidden}

/* Tour detail page — inline panels (no sidebar) */
.tour-detail-panels{padding:22px 24px 8px;border-top:1px solid #f1f3f5;background:#fff}
.tour-detail-panel{display:none;color:#374151;font-size:0.95rem;line-height:1.65}
.tour-detail-panel.is-active{display:block}
.tour-detail-panel__title{margin:0 0 14px;font-size:1.15rem;font-weight:700;color:#1a202c}
.tour-detail-panel__lead{color:#6c757d;font-size:1rem;margin-bottom:12px}
.tour-detail-panel__body{color:#374151}
.tour-detail-panel__body--rich p{margin:0 0 12px}
.tour-detail-panel__body--rich ul,.tour-detail-panel__body--rich ol{margin:0 0 12px;padding-left:1.25rem}
.tour-detail-panel__body--rich strong,.tour-detail-panel__body--rich b{font-weight:700;color:#1a202c}
.tour-detail-panel__empty{color:#6c757d;font-style:italic;margin:0}
.tour-detail-panel h4{margin:18px 0 10px;font-size:1rem;color:#1a202c}
.tour-detail-panel h4:first-of-type{margin-top:0}
.tour-detail-list{margin:0;padding:0;list-style:none}
.tour-detail-list li{display:flex;gap:10px;padding:8px 0;border-bottom:1px dashed #f1f3f5}
.tour-detail-list li i{margin-top:4px;color:#28a745;flex-shrink:0}
.tour-detail-list--exclude li i{color:#dc3545}
.tour-detail-meta{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:16px}
.tour-detail-meta div{background:#f8f9fa;border-radius:8px;padding:12px 14px}
.tour-detail-meta span{display:block;font-size:0.75rem;text-transform:uppercase;color:#6c757d;font-weight:700;letter-spacing:0.04em;margin-bottom:4px}
.tour-detail-meta strong{font-size:1rem;color:#1a202c}
.tour-detail-day{padding:12px 0;border-bottom:1px solid #f1f3f5}
.tour-detail-day:last-child{border-bottom:0}
.tour-detail-day strong{display:block;color:#1a202c;margin-bottom:4px}
.tour-detail-day p{margin:0;color:#6c757d}
.tour-tabs--inline .tour-tab-btn{cursor:pointer}
.tour-tabs--inline .tour-tab-btn.active{background:#fff;color:#667eea;box-shadow:inset 0 -3px 0 #667eea}
@media (max-width:767px){
.tour-tabs{grid-template-columns:repeat(2,1fr)}
.tour-tabs div{border-bottom:1px solid #e9ecef;padding:15px 10px}
.tour-tabs div:nth-child(2n){border-right:0}
.tour-tabs div:nth-child(5){border-bottom:0}
.tour-bottom{flex-direction:column;align-items:stretch}
.tour-price{padding:15px 20px;text-align:center;align-items:center}
.tour-actions{justify-content:center;padding:12px 15px 15px;flex-wrap:wrap}
.tour-cart-form,.tour-cart-btn,.tour-book-btn{flex:1 1 auto;min-width:120px}
}
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

$extra_css .= '<style>
.tour-hero{position:relative}
.tour-hero--slider{min-height:320px}
.tour-hero--slider .container{position:relative;z-index:3}
.tour-hero__bg{position:absolute;inset:0;z-index:1;overflow:hidden}
.tour-hero__bg-slide{position:absolute;inset:0;background-size:cover;background-position:center;opacity:0;transform:scale(1.03);transition:opacity 900ms ease, transform 6s ease}
.tour-hero__bg-slide.is-active{opacity:1;transform:scale(1.0)}
.tour-hero__overlay{position:absolute;inset:0;z-index:2;background:linear-gradient(180deg,rgba(0,0,0,.35) 0%,rgba(0,0,0,.55) 55%,rgba(0,0,0,.65) 100%)}
.tour-hero--slider .hero-section__inner{position:relative;z-index:3}
.tour-hero--slider .hero-section__inner h1{margin:0 0 12px 0;text-shadow:0 10px 30px rgba(0,0,0,.45)}
.tour-hero--slider .travhub-breadcrumb{margin:0}
@media (max-width: 768px){
  .tour-hero--slider{min-height:260px}
}
</style>';

$extra_js = '<script>
(function(){
  function initHeroSlider(){
    var slides = Array.prototype.slice.call(document.querySelectorAll(\'.tour-hero__bg-slide\'));
    if (!slides.length) return;
    var idx = 0;
    slides.forEach(function(s){ s.classList.remove(\'is-active\'); });
    slides[0].classList.add(\'is-active\');
    if (slides.length === 1) return;
    window.setInterval(function(){
      slides[idx].classList.remove(\'is-active\');
      idx = (idx + 1) % slides.length;
      slides[idx].classList.add(\'is-active\');
    }, 4500);
  }
  if (document.readyState === \'loading\') document.addEventListener(\'DOMContentLoaded\', initHeroSlider);
  else initHeroSlider();
})();
</script>'
    . '<script>
(function(){
  function initTourDetailTabs(){
    var tabs=document.querySelectorAll(".tour-tabs--inline .tour-tab-btn[data-inline-tab]");
    var panels=document.querySelectorAll(".tour-detail-panel[data-inline-panel]");
    if(!tabs.length||!panels.length)return;
    function show(tab){
      tabs.forEach(function(btn){btn.classList.toggle("active",btn.getAttribute("data-inline-tab")===tab);});
      panels.forEach(function(panel){panel.classList.toggle("is-active",panel.getAttribute("data-inline-panel")===tab);});
    }
    tabs.forEach(function(btn){
      function activate(e){if(e)e.preventDefault();show(btn.getAttribute("data-inline-tab"));}
      btn.addEventListener("click",activate);
      btn.addEventListener("keydown",function(e){if(e.key==="Enter"||e.key===" "){activate(e);}});
    });
  }
  if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",initTourDetailTabs);
  else initTourDetailTabs();
})();
</script>';

// Include header
include 'includes/header.php';
?>

<!-- Tour Hero Section -->
<?php
$hero_gallery_images = json_decode($tour['gallery'], true) ?: [];
$hero_images = [];
if (!empty($tour['featured_image'])) {
    $hero_images[] = $tour['featured_image'];
}
foreach ($hero_gallery_images as $img) {
    if ($img) {
        $hero_images[] = $img;
    }
}
$hero_images = array_values(array_unique(array_filter($hero_images)));
if (empty($hero_images)) {
    $hero_images = ['assets/images/tours/default-tour.jpg'];
}
$hero_images = array_slice($hero_images, 0, 6);
?>
<section class="tour-hero tour-hero--slider">
    <div class="tour-hero__bg" aria-hidden="true">
        <?php foreach ($hero_images as $img): ?>
            <div class="tour-hero__bg-slide" style="background-image:url('<?php echo BASE_URL . htmlspecialchars($img); ?>')"></div>
        <?php endforeach; ?>
    </div>
    <div class="tour-hero__overlay" aria-hidden="true"></div>
    <div class="container">
        <div class="hero-section__inner">
            <h1 class="text-white"><?php echo htmlspecialchars($tour['title']); ?></h1>
            <ul class="travhub-breadcrumb list-unstyled">
                <li><a href="<?php echo navUrl('home'); ?>">Home</a></li>
                <li><a href="<?php echo navUrl('tours'); ?>">Tours</a></li>
                <li><?php echo htmlspecialchars($tour['title']); ?></li>
            </ul>
        </div>
    </div>
</section>

    <!-- Tour Details -->
    <section class="section-space">
        <div class="container">
            <div class="row tour-details-grid">
                <div class="col-lg-12 col-md-12">
                    <!-- Tour Overview -->
                    <div class="tour-content">
                        <div class="tour-detail-card" data-tour-id="<?php echo (int) $tour['id']; ?>">
                            <div class="tour-head">
                                <div class="tour-meta-line">
                                    <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($tour['destination_name']); ?><?php echo !empty($tour['country']) ? ', ' . htmlspecialchars($tour['country']) : ''; ?></span>
                                    <span><i class="far fa-clock"></i> Duration: <?php echo (int) $tour['duration_days']; ?> Days</span>
                                </div>
                                <div class="d-flex flex-wrap gap-3 mb-2">
                                    <?php foreach ($tourCabList as $cab): ?>
                                        <span class="badge bg-primary px-3 py-2">
                                            <i class="fas fa-car me-1"></i>
                                            <?php echo htmlspecialchars($cab['display_name'] ?? getCabDisplayName($cab['value'])); ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                                <div class="tour-flags">
                                    <span><i class="far fa-check-circle"></i> IMPORTANT INFORMATION</span>
                                    <span><i class="far fa-check-circle"></i> REFUNDABLE</span>
                                </div>
                            </div>
                            <?php ob_start(); ?>
                            <div class="tour-bottom">
                                <div class="tour-price">
                                    FROM INR <b>₹ <?php echo number_format($tour_price, 2); ?></b>
                                    <span class="tour-price-note">This is only transportation price</span>
                                </div>
                                <div class="tour-actions">
                                    <form method="POST" action="<?php echo htmlspecialchars(navUrl('cart')); ?>" class="tour-cart-form">
                                        <input type="hidden" name="action" value="add">
                                        <input type="hidden" name="tour_id" value="<?php echo (int) $tour['id']; ?>">
                                        <input type="hidden" name="tour_date" value="<?php echo htmlspecialchars($tomorrow); ?>">
                                        <input type="hidden" name="people" value="<?php echo (int) $defaultPeople; ?>">
                                        <input type="hidden" name="return_url" value="<?php echo htmlspecialchars((string) ($_SERVER['REQUEST_URI'] ?? '')); ?>">
                                        <button type="submit" class="tour-cart-btn">
                                            <i class="fas fa-shopping-cart"></i> Add to Cart
                                        </button>
                                    </form>
                                    <a href="<?php echo bookingUrl((int) $tour['id']); ?>" class="tour-book-btn">Book Now</a>
                                </div>
                            </div>
                            <?php $tour_price_bar = ob_get_clean(); echo str_replace('class="tour-bottom"', 'class="tour-bottom tour-bottom--top"', $tour_price_bar); ?>
                            <div class="tour-tabs tour-tabs--inline">
                                <div class="tour-tab-btn active" role="button" tabindex="0" data-inline-tab="description"><i class="far fa-file-alt"></i> Description</div>
                                <div class="tour-tab-btn" role="button" tabindex="0" data-inline-tab="inclusion"><i class="fas fa-pen-square"></i> Inclusion</div>
                                <div class="tour-tab-btn" role="button" tabindex="0" data-inline-tab="exclusion"><i class="fas fa-ban"></i> Exclusion</div>
                                <div class="tour-tab-btn" role="button" tabindex="0" data-inline-tab="timings"><i class="far fa-clock"></i> Timings</div>
                                <div class="tour-tab-btn" role="button" tabindex="0" data-inline-tab="useful"><i class="fas fa-info-circle"></i> Useful Info</div>
                            </div>
                            <div class="tour-detail-panels">
                                <div class="tour-detail-panel is-active" id="tour-panel-description" data-inline-panel="description">
                                    <h3 class="tour-detail-panel__title">Description</h3>
                                    <?php if (!empty($tour['short_description'])): ?>
                                        <p class="tour-detail-panel__lead"><?php echo nl2br(htmlspecialchars($tour['short_description'])); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($tour['description'])): ?>
                                        <div class="tour-detail-panel__body tour-detail-panel__body--rich"><?php echo formatTourDescriptionForDisplay($tour['description']); ?></div>
                                    <?php elseif (empty($tour['short_description'])): ?>
                                        <p class="tour-detail-panel__empty">No description available for this tour.</p>
                                    <?php endif; ?>
                                </div>
                                <div class="tour-detail-panel" id="tour-panel-inclusion" data-inline-panel="inclusion">
                                    <h3 class="tour-detail-panel__title">Inclusion</h3>
                                    <?php if (!empty($inclusions)): ?>
                                        <ul class="tour-detail-list">
                                            <?php foreach ($inclusions as $item): ?>
                                                <li><i class="fas fa-check"></i><span><?php echo htmlspecialchars($item); ?></span></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php else: ?>
                                        <p class="tour-detail-panel__empty">No inclusion details added yet.</p>
                                    <?php endif; ?>
                                </div>
                                <div class="tour-detail-panel" id="tour-panel-exclusion" data-inline-panel="exclusion">
                                    <h3 class="tour-detail-panel__title">Exclusion</h3>
                                    <?php if (!empty($exclusions)): ?>
                                        <ul class="tour-detail-list tour-detail-list--exclude">
                                            <?php foreach ($exclusions as $item): ?>
                                                <li><i class="fas fa-times"></i><span><?php echo htmlspecialchars($item); ?></span></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php else: ?>
                                        <p class="tour-detail-panel__empty">No exclusion details added yet.</p>
                                    <?php endif; ?>
                                </div>
                                <div class="tour-detail-panel" id="tour-panel-timings" data-inline-panel="timings">
                                    <h3 class="tour-detail-panel__title">Timings</h3>
                                    <div class="tour-detail-meta">
                                        <div><span>Duration</span><strong><?php echo (int) $tour['duration_days']; ?> Days / <?php echo (int) $tour['duration_nights']; ?> Nights</strong></div>
                                        <?php if ($availability): ?>
                                            <div><span>Availability</span><strong><?php echo htmlspecialchars($availability); ?></strong></div>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($itinerary)): ?>
                                        <h4>Day-wise Schedule</h4>
                                        <?php foreach ($itinerary as $day): ?>
                                            <div class="tour-detail-day">
                                                <strong>Day <?php echo htmlspecialchars((string) ($day['day'] ?? '')); ?>: <?php echo htmlspecialchars((string) ($day['title'] ?? 'Schedule')); ?></strong>
                                                <?php if (!empty($day['description'])): ?>
                                                    <p><?php echo nl2br(htmlspecialchars($day['description'])); ?></p>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php elseif (!$availability): ?>
                                        <p class="tour-detail-panel__empty">No timing details available.</p>
                                    <?php endif; ?>
                                </div>
                                <div class="tour-detail-panel" id="tour-panel-useful" data-inline-panel="useful">
                                    <h3 class="tour-detail-panel__title">Useful Info</h3>
                                    <?php if (!empty($tour['destination_name'])): ?>
                                        <p><strong><?php echo htmlspecialchars(trim($tour['destination_name'] . (!empty($tour['country']) ? ', ' . $tour['country'] : ''))); ?></strong></p>
                                    <?php endif; ?>
                                    <?php if (!empty($tour['destination_description'])): ?>
                                        <div class="tour-detail-panel__body"><?php echo nl2br(htmlspecialchars($tour['destination_description'])); ?></div>
                                    <?php else: ?>
                                        <p class="tour-detail-panel__empty">No useful information available for this destination.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php echo $tour_price_bar; ?>
                        </div>

                        <!-- Photo Collage Gallery -->
                        <?php 
                        $gallery_images = json_decode($tour['gallery'], true) ?: [];
                        if (!empty($gallery_images) || !empty($tour['featured_image'])): 
                        ?>
                        <section class="photo-collage-section" style="padding: 80px 0; background: #ffffff;">
                            <div class="container">
                                <div class="text-center mb-5">
                                    <span class="badge" style="background: #1bbc9b; color: white; padding: 8px 16px; border-radius: 20px; font-size: 0.9rem; margin-bottom: 15px;">
                                        📸 Photo Gallery
                                    </span>
                                    <h2 class="mb-3" style="font-size: 2.5rem; font-weight: 700; color: #2c3e50;">Explore <?php echo htmlspecialchars($tour['title']); ?></h2>
                                    <p style="color: #6c757d; font-size: 1.1rem; max-width: 600px; margin: 0 auto;">Experience the beauty and adventure through our carefully captured moments</p>
                                </div>
                                
                                <div class="photo-collage-container" style="position: relative; max-width: 1200px; margin: 0 auto;">
                                    <div class="photo-collage-grid" id="photoCollage" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; min-height: 500px;">
                                        <?php 
                                        // Combine featured image with gallery images
                                        $all_images = [];
                                        if (!empty($tour['featured_image'])) {
                                            $all_images[] = $tour['featured_image'];
                                        }
                                        $all_images = array_merge($all_images, $gallery_images);
                                        
                                        $image_count = count($all_images);
                                        
                                        // Define the layout pattern similar to your reference image
                                        if ($image_count > 0): 
                                            // First image - large hero image (takes 2 columns, 2 rows)
                                            $first_image = $all_images[0];
                                        ?>
                                        <div class="collage-item hero-item" style="grid-column: 1 / 3; grid-row: 1 / 3; position: relative; overflow: hidden; border-radius: 20px; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 10px 30px rgba(0,0,0,0.2); min-height: 400px;" 
                                             onclick="openPhotoModal('<?php echo BASE_URL . $first_image; ?>', '<?php echo htmlspecialchars($tour['title']); ?> - Featured Image')">
                                            <img src="<?php echo BASE_URL . $first_image; ?>" 
                                                 alt="<?php echo htmlspecialchars($tour['title']); ?> - Featured Image" 
                                                 style="width: 100%; height: 100%; object-fit: cover; transition: transform 0.3s ease;" 
                                                 loading="lazy">
                                            <div class="collage-overlay" style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: linear-gradient(45deg, rgba(0,0,0,0.3), transparent); opacity: 0; transition: all 0.3s ease; display: flex; align-items: center; justify-content: center;">
                                                <i class="fas fa-expand-alt" style="color: white; font-size: 32px; transform: scale(0.8); transition: transform 0.3s ease;"></i>
                                            </div>
                                            <div class="image-overlay-content" style="position: absolute; bottom: 20px; left: 20px; color: white; z-index: 2;">
                                                <div class="featured-badge" style="background: rgba(255, 255, 255, 0.9); color: #333; padding: 8px 16px; border-radius: 25px; font-size: 0.85rem; font-weight: 600; backdrop-filter: blur(10px); display: inline-block; margin-bottom: 10px;">
                                                    ⭐ Featured
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <?php 
                                        // Right column - smaller images
                                        $remaining_images = array_slice($all_images, 1, 3); // Take next 3 images
                                        $positions = [
                                            ['grid-column: 3 / 5; grid-row: 1; min-height: 190px;'],
                                            ['grid-column: 3 / 4; grid-row: 2; min-height: 190px;'],
                                            ['grid-column: 4 / 5; grid-row: 2; min-height: 190px;']
                                        ];
                                        
                                        foreach ($remaining_images as $index => $image): 
                                            if ($index >= 3) break;
                                            $style = $positions[$index];
                                            $image_labels = ['Destinations', 'Activity & Sightseeing', 'Stays'];
                                            $label = isset($image_labels[$index]) ? $image_labels[$index] : 'Gallery';
                                        ?>
                                        <div class="collage-item small-item" style="<?php echo $style; ?> position: relative; overflow: hidden; border-radius: 15px; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 8px 25px rgba(0,0,0,0.15);" 
                                             onclick="openPhotoModal('<?php echo BASE_URL . $image; ?>', '<?php echo htmlspecialchars($tour['title']); ?> - <?php echo $label; ?>')">
                                            <img src="<?php echo BASE_URL . $image; ?>" 
                                                 alt="<?php echo htmlspecialchars($tour['title']); ?> - <?php echo $label; ?>" 
                                                 style="width: 100%; height: 100%; object-fit: cover; transition: transform 0.3s ease;" 
                                                 loading="lazy">
                                            <div class="collage-overlay" style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: linear-gradient(45deg, rgba(0,0,0,0.4), transparent); opacity: 0; transition: all 0.3s ease; display: flex; align-items: center; justify-content: center;">
                                                <i class="fas fa-search-plus" style="color: white; font-size: 20px; transform: scale(0.8); transition: transform 0.3s ease;"></i>
                                            </div>
                                            <div class="image-label" style="position: absolute; bottom: 15px; left: 15px; color: white; font-weight: 600; font-size: 0.9rem; text-shadow: 2px 2px 4px rgba(0,0,0,0.8); z-index: 2;">
                                                <?php echo $label; ?>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                        
                                        <?php if ($image_count > 4): ?>
                                        <!-- View All Images Button -->
                                        <div class="view-all-btn" style="position: absolute; bottom: 20px; right: 20px; background: rgba(255, 255, 255, 0.95); color: #333; padding: 12px 20px; border-radius: 25px; font-size: 0.9rem; font-weight: 600; backdrop-filter: blur(10px); cursor: pointer; transition: all 0.3s ease; box-shadow: 0 5px 15px rgba(0,0,0,0.1); border: 2px solid transparent; z-index: 5;" 
                                             onclick="openAllImagesModal()" 
                                             onmouseover="this.style.background='#667eea'; this.style.color='white'; this.style.transform='translateY(-2px)'" 
                                             onmouseout="this.style.background='rgba(255, 255, 255, 0.95)'; this.style.color='#333'; this.style.transform='translateY(0)'">
                                            <i class="fas fa-images" style="margin-right: 8px;"></i> View All <?php echo $image_count; ?> Images
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php else: ?>
                                        <!-- Single image fallback -->
                                        <div class="collage-item single-item" style="grid-column: 1 / 5; grid-row: 1; position: relative; overflow: hidden; border-radius: 20px; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 10px 30px rgba(0,0,0,0.2); min-height: 400px;" 
                                             onclick="openPhotoModal('<?php echo BASE_URL . $tour['featured_image']; ?>', '<?php echo htmlspecialchars($tour['title']); ?>')">
                                            <img src="<?php echo BASE_URL . $tour['featured_image']; ?>" 
                                                 alt="<?php echo htmlspecialchars($tour['title']); ?>" 
                                                 style="width: 100%; height: 100%; object-fit: cover; transition: transform 0.3s ease;" 
                                                 loading="lazy">
                                            <div class="collage-overlay" style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: linear-gradient(45deg, rgba(0,0,0,0.3), transparent); opacity: 0; transition: all 0.3s ease; display: flex; align-items: center; justify-content: center;">
                                                <i class="fas fa-expand-alt" style="color: white; font-size: 32px; transform: scale(0.8); transition: transform 0.3s ease;"></i>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <?php if (empty($all_images)): ?>
                                <div class="no-gallery" style="text-align: center; padding: 80px 20px; background: #f8f9fa; border-radius: 20px;">
                                    <i class="fas fa-camera" style="font-size: 48px; color: #bdc3c7; margin-bottom: 20px;"></i>
                                    <h4 style="color: #6c757d; margin-bottom: 10px;">Gallery Coming Soon</h4>
                                    <p style="color: #95a5a6; margin: 0;">We're preparing beautiful photos of this amazing destination</p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </section>

                        <!-- Photo Modal -->
                        <div id="photoModal" class="photo-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.95); z-index: 9999; align-items: center; justify-content: center; opacity: 0; transition: opacity 0.3s ease;">
                            <div class="modal-content" style="position: relative; max-width: 90%; max-height: 90%; display: flex; align-items: center; justify-content: center;">
                                <img id="modalImage" src="" alt="" style="max-width: 100%; max-height: 100%; border-radius: 12px; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);">
                                <div class="modal-caption" style="position: absolute; bottom: -60px; left: 0; right: 0; text-align: center; color: white; font-size: 16px; font-weight: 500;" id="modalCaption"></div>
                                <button class="modal-close" onclick="closePhotoModal()" style="position: absolute; top: -50px; right: 0; background: none; border: none; color: white; font-size: 32px; cursor: pointer; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; border-radius: 50%; transition: background 0.3s ease;" onmouseover="this.style.background='rgba(255,255,255,0.1)'" onmouseout="this.style.background='none'">
                                    <i class="fas fa-times"></i>
                                </button>
                                <button class="modal-nav modal-prev" onclick="navigatePhoto(-1)" style="position: absolute; left: -60px; top: 50%; transform: translateY(-50%); background: rgba(255, 255, 255, 0.1); border: none; color: white; font-size: 24px; cursor: pointer; width: 50px; height: 50px; display: flex; align-items: center; justify-content: center; border-radius: 50%; transition: all 0.3s ease; backdrop-filter: blur(10px);" onmouseover="this.style.background='rgba(255,255,255,0.2)'" onmouseout="this.style.background='rgba(255,255,255,0.1)'">
                                    <i class="fas fa-chevron-left"></i>
                                </button>
                                <button class="modal-nav modal-next" onclick="navigatePhoto(1)" style="position: absolute; right: -60px; top: 50%; transform: translateY(-50%); background: rgba(255, 255, 255, 0.1); border: none; color: white; font-size: 24px; cursor: pointer; width: 50px; height: 50px; display: flex; align-items: center; justify-content: center; border-radius: 50%; transition: all 0.3s ease; backdrop-filter: blur(10px);" onmouseover="this.style.background='rgba(255,255,255,0.2)'" onmouseout="this.style.background='rgba(255,255,255,0.1)'">
                                    <i class="fas fa-chevron-right"></i>
                                </button>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($related_tours): ?>
                            <div class="mb-5" id="tour-related">
                                <h3 class="mb-4">Related Tours</h3>
                                <div class="row related-tours-grid">
                                    <?php foreach ($related_tours as $related): ?>
                                        <div class="col-md-4 mb-3">
                                            <div class="card">
                                                <img src="<?php echo BASE_URL . ($related['featured_image'] ?: 'assets/images/tours/default-tour.jpg'); ?>" 
                                                     class="card-img-top" style="height: 200px; object-fit: cover;" alt="<?php echo htmlspecialchars($related['title']); ?>">
                                                <div class="card-body">
                                                    <h6 class="card-title">
                                        <a href="<?php echo tourUrl($related['slug']); ?>" class="text-decoration-none">
                                            <?php echo htmlspecialchars($related['title']); ?>
                                        </a>
                                                    </h6>
                                                    <p class="card-text small text-muted">
                                                        <?php echo htmlspecialchars($related['destination_name']); ?> • 
                                                        ₹<?php echo number_format($related['price'], 0); ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
        </div>
    </section>

<style>
/* Photo Collage Hover Effects */
.collage-item:hover img {
    transform: scale(1.05);
}

.collage-item:hover .collage-overlay {
    opacity: 1;
}

.collage-item:hover .collage-overlay i {
    transform: scale(1);
}

/* Photo Collage Responsive Design */
@media (max-width: 1024px) {
    .photo-collage-container {
        max-width: 100%;
        padding: 0 15px;
    }
}

@media (max-width: 768px) {
    .photo-collage-grid {
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 10px !important;
    }
    
    .hero-item {
        grid-column: 1 / 3 !important;
        grid-row: 1 / 3 !important;
        min-height: 300px !important;
    }
    
    .small-item:nth-child(2) {
        grid-column: 1 / 2 !important;
        grid-row: 3 / 4 !important;
        min-height: 150px !important;
    }
    
    .small-item:nth-child(3) {
        grid-column: 2 / 3 !important;
        grid-row: 3 / 4 !important;
        min-height: 150px !important;
    }
    
    .small-item:nth-child(4) {
        grid-column: 1 / 3 !important;
        grid-row: 4 / 5 !important;
        min-height: 150px !important;
    }
    
    .view-all-btn {
        bottom: 10px !important;
        right: 10px !important;
        padding: 10px 16px !important;
        font-size: 0.8rem !important;
    }
    
    .modal-nav {
        display: none !important;
    }
    
    .modal-close {
        top: 20px !important;
        right: 20px !important;
    }
    
    .modal-caption {
        bottom: 20px !important;
        padding: 0 20px;
        font-size: 14px !important;
    }
}

@media (max-width: 480px) {
    .photo-collage-section {
        padding: 40px 0 !important;
    }
    
    .photo-collage-section h2 {
        font-size: 1.8rem !important;
    }
    
    .hero-item {
        min-height: 250px !important;
        border-radius: 15px !important;
    }
    
    .small-item {
        border-radius: 10px !important;
        min-height: 120px !important;
    }
    
    .image-label {
        font-size: 0.8rem !important;
        bottom: 10px !important;
        left: 10px !important;
    }
    
    .featured-badge {
        padding: 6px 12px !important;
        font-size: 0.75rem !important;
    }
}
</style>

<script>
// Photo Gallery Modal Functionality
let currentPhotoIndex = 0;
let allPhotoImages = [];

// Initialize photo arrays when page loads
document.addEventListener('DOMContentLoaded', function() {
    // Collect all images from the collage
    const collageItems = document.querySelectorAll('.collage-item img');
    allPhotoImages = Array.from(collageItems).map(img => ({
        src: img.src,
        alt: img.alt
    }));
    
    // Add hover effects
    const collageGrid = document.getElementById('photoCollage');
    if (collageGrid) {
        // Add smooth fade-in animation
        collageGrid.style.opacity = '0';
        setTimeout(() => {
            collageGrid.style.transition = 'opacity 0.8s ease';
            collageGrid.style.opacity = '1';
        }, 100);
    }
});

// Open photo modal
function openPhotoModal(imageSrc, caption) {
    const modal = document.getElementById('photoModal');
    const modalImage = document.getElementById('modalImage');
    const modalCaption = document.getElementById('modalCaption');
    
    // Find the index of the clicked image
    currentPhotoIndex = allPhotoImages.findIndex(img => img.src === imageSrc);
    if (currentPhotoIndex === -1) currentPhotoIndex = 0;
    
    // Set image and caption
    modalImage.src = imageSrc;
    modalImage.alt = caption;
    modalCaption.textContent = caption;
    
    // Show modal with fade effect
    modal.style.display = 'flex';
    setTimeout(() => {
        modal.style.opacity = '1';
    }, 10);
    
    // Prevent body scrolling
    document.body.style.overflow = 'hidden';
    
    // Add escape key listener
    document.addEventListener('keydown', handleModalKeydown);
}

// Close photo modal
function closePhotoModal() {
    const modal = document.getElementById('photoModal');
    
    // Fade out effect
    modal.style.opacity = '0';
    setTimeout(() => {
        modal.style.display = 'none';
    }, 300);
    
    // Restore body scrolling
    document.body.style.overflow = 'auto';
    
    // Remove escape key listener
    document.removeEventListener('keydown', handleModalKeydown);
}

// Navigate through photos
function navigatePhoto(direction) {
    if (allPhotoImages.length === 0) return;
    
    // Calculate new index
    currentPhotoIndex += direction;
    if (currentPhotoIndex < 0) currentPhotoIndex = allPhotoImages.length - 1;
    if (currentPhotoIndex >= allPhotoImages.length) currentPhotoIndex = 0;
    
    // Update modal content
    const modalImage = document.getElementById('modalImage');
    const modalCaption = document.getElementById('modalCaption');
    const currentImage = allPhotoImages[currentPhotoIndex];
    
    // Fade transition effect
    modalImage.style.opacity = '0';
    setTimeout(() => {
        modalImage.src = currentImage.src;
        modalImage.alt = currentImage.alt;
        modalCaption.textContent = currentImage.alt;
        modalImage.style.opacity = '1';
    }, 150);
}

// Handle keyboard navigation
function handleModalKeydown(event) {
    switch(event.key) {
        case 'Escape':
            closePhotoModal();
            break;
        case 'ArrowLeft':
            navigatePhoto(-1);
            break;
        case 'ArrowRight':
            navigatePhoto(1);
            break;
    }
}

// Open all images modal (shows first image and allows navigation)
function openAllImagesModal() {
    if (allPhotoImages.length > 0) {
        openPhotoModal(allPhotoImages[0].src, allPhotoImages[0].alt);
    }
}

// Close modal when clicking outside the image
document.addEventListener('click', function(event) {
    const modal = document.getElementById('photoModal');
    if (event.target === modal) {
        closePhotoModal();
    }
});
</script>

<script>
// Booking form validation for tour details page
document.addEventListener('DOMContentLoaded', function() {
    const peopleSelect = document.getElementById('peopleSelect');
    const cabSelect = document.getElementById('cabSelect');
    const form = document.getElementById('quickBookingForm');
    const cabEnabled = <?php echo $cab_functionality_enabled ? 'true' : 'false'; ?>;
    
    function validateCabSelection() {
        if (!cabEnabled || !peopleSelect || !cabSelect) return true;
        
        const people = parseInt(peopleSelect.value);
        const selectedCab = cabSelect.options[cabSelect.selectedIndex];
        const maxPassengers = parseInt(selectedCab.dataset.maxPassengers) || 0;
        
        // Remove existing warnings
        const existingWarning = document.getElementById('cabWarningDetails');
        if (existingWarning) existingWarning.remove();
        
        if (cabSelect.value && people > maxPassengers) {
            const warning = document.createElement('div');
            warning.id = 'cabWarningDetails';
            warning.className = 'alert alert-warning mt-2';
            warning.innerHTML = '<small><i class="fas fa-exclamation-triangle me-1"></i>Selected cab can accommodate maximum ' + maxPassengers + ' passengers.</small>';
            cabSelect.parentNode.appendChild(warning);
            return false;
        }
        return true;
    }
    
    if (peopleSelect) {
        peopleSelect.addEventListener('change', validateCabSelection);
    }
    
    if (cabSelect) {
        cabSelect.addEventListener('change', validateCabSelection);
    }
    
    if (form) {
        form.addEventListener('submit', function(e) {
            if (cabEnabled && !validateCabSelection()) {
                e.preventDefault();
                alert('Please select an appropriate cab type for your group size.');
            }
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>

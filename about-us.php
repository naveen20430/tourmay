<?php
require_once 'config/config.php';

$page_title = 'About Us - ' . getSetting('site_name');
$current_page = 'about';

$site_name = getSetting('site_name', 'The World Journey');
$contact_phone = getSetting('contact_phone', '+91 9882076600');
$contact_email = getSetting('contact_email', 'info@travhub.com');
$site_address = getSetting('site_address', 'Himachal Pradesh, India');

include 'includes/header.php';
?>

<section class="page-header" style="background: linear-gradient(rgba(0,0,0,0.55), rgba(0,0,0,0.55)), url('assets/images/backgrounds/hero-2-1.jpg') center/cover; padding: 120px 0 60px;">
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <div class="page-header__inner text-center">
                    <h1 class="page-header__title text-white">About Us</h1>
                    <ul class="list-unstyled page-header__breadcrumb">
                        <li><a href="<?php echo navUrl('home'); ?>" class="text-white-50">Home</a></li>
                        <li class="text-white">About Us</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="py-5" style="background: #f8f9fa;">
    <div class="container">
        <div class="row g-4 align-items-center mb-5">
            <div class="col-lg-6">
                <img src="<?php echo BASE_URL; ?>assets/images/backgrounds/hero-2-2.jpg"
                     alt="<?php echo htmlspecialchars($site_name); ?>"
                     class="img-fluid rounded-3 shadow"
                     style="width: 100%; height: 360px; object-fit: cover;">
            </div>
            <div class="col-lg-6">
                <span class="badge bg-primary mb-3">✈️ Premium Travel Experience</span>
                <h2 class="mb-3" style="color: #0f172a; font-weight: 700;">Welcome to <?php echo htmlspecialchars($site_name); ?></h2>
                <p class="lead text-muted">Your trusted travel partner for Himachal Pradesh tours, activities, and cab services.</p>
                <p style="color: #495057; line-height: 1.8;">
                    We specialize in curated holiday experiences across Shimla, Manali, Dharamshala, Kasauli, and beyond.
                    From one-day excursions to multi-day packages and reliable one-way taxi transfers, we help travelers
                    explore the mountains with comfort, safety, and transparent pricing.
                </p>
                <a href="<?php echo navUrl('tours'); ?>" class="travhub-btn mt-2">
                    <span>Explore Our Tours</span>
                </a>
            </div>
        </div>

        <div class="row g-4 mb-5">
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm p-4 text-center">
                    <div style="font-size: 2rem; margin-bottom: 12px;">🗺️</div>
                    <h3 class="h5 mb-2" style="color: #667eea;">Curated Tours</h3>
                    <p class="text-muted mb-0">Handpicked sightseeing packages, day trips, and multi-day itineraries across Himachal.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm p-4 text-center">
                    <div style="font-size: 2rem; margin-bottom: 12px;">🚗</div>
                    <h3 class="h5 mb-2" style="color: #667eea;">Cab & Transfers</h3>
                    <p class="text-muted mb-0">One-way and round-trip taxi services between Chandigarh, Shimla, Manali, and more.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm p-4 text-center">
                    <div style="font-size: 2rem; margin-bottom: 12px;">🤝</div>
                    <h3 class="h5 mb-2" style="color: #667eea;">Personal Support</h3>
                    <p class="text-muted mb-0">Friendly assistance from inquiry to booking — we're here to make your journey smooth.</p>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-10 mx-auto">
                <div class="card border-0 shadow-sm p-4 p-md-5" style="border-radius: 12px;">
                    <h2 class="h4 mb-4" style="color: #667eea; font-weight: 700;">Why Travel With Us?</h2>
                    <ul class="list-unstyled mb-4" style="line-height: 2; color: #495057;">
                        <li>✅ Local expertise across popular Himachal destinations</li>
                        <li>✅ Easy online tour search, cart, and booking</li>
                        <li>✅ Transparent transportation pricing on tour pages</li>
                        <li>✅ Flexible cab options — Sedan, SUV, and Innova</li>
                        <li>✅ Dedicated customer support for travel inquiries</li>
                    </ul>

                    <div class="row g-3 mt-2">
                        <div class="col-md-4">
                            <div class="text-center p-3 rounded" style="background: #f1f5f9;">
                                <h3 class="h2 mb-1" style="color: #667eea;">500+</h3>
                                <p class="mb-0 text-muted">Happy Travelers</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center p-3 rounded" style="background: #f1f5f9;">
                                <h3 class="h2 mb-1" style="color: #667eea;">50+</h3>
                                <p class="mb-0 text-muted">Destinations</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center p-3 rounded" style="background: #f1f5f9;">
                                <h3 class="h2 mb-1" style="color: #667eea;">24/7</h3>
                                <p class="mb-0 text-muted">Support</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="py-5" style="background: #fff;">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8 text-center">
                <h2 class="mb-3" style="font-weight: 700; color: #0f172a;">Get in Touch</h2>
                <p class="text-muted mb-4">Planning a trip? We'd love to help you build the perfect itinerary.</p>
                <div class="d-flex flex-wrap justify-content-center gap-3 mb-4">
                    <a href="tel:<?php echo preg_replace('/\s+/', '', $contact_phone); ?>" class="travhub-btn">
                        <span><i class="fas fa-phone me-2"></i><?php echo htmlspecialchars($contact_phone); ?></span>
                    </a>
                    <a href="mailto:<?php echo htmlspecialchars($contact_email); ?>" class="travhub-btn" style="background: linear-gradient(135deg, #6c757d 0%, #495057 100%) !important;">
                        <span><i class="fas fa-envelope me-2"></i><?php echo htmlspecialchars($contact_email); ?></span>
                    </a>
                </div>
                <?php if ($site_address): ?>
                <p class="text-muted mb-4"><i class="fas fa-map-marker-alt me-2"></i><?php echo htmlspecialchars($site_address); ?></p>
                <?php endif; ?>
                <a href="<?php echo navUrl('contact'); ?>" class="travhub-btn">
                    <span>Contact Us</span>
                </a>
            </div>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>

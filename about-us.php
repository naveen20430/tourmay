<?php
require_once 'config/config.php';

$page_title = 'About Us - ' . getSetting('site_name');
$current_page = 'about';

$site_name = getSetting('site_name', 'The World Journey');

include 'includes/header.php';
?>

<section class="page-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 120px 0 60px;">
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
        <div class="row mb-5">
            <div class="col-lg-10 mx-auto">
                <span class="badge bg-primary mb-3">✈️ Premium Travel Experience</span>
                <h2 class="mb-3" style="color: #0f172a; font-weight: 700;">Welcome to <?php echo htmlspecialchars($site_name); ?></h2>
                <p style="color: #495057; line-height: 1.8;">
                    At <?php echo htmlspecialchars($site_name); ?>, we believe that every trip tells a story. Since our establishment in 2011, we have been dedicated to transforming travel dreams into unforgettable experiences through carefully crafted journeys and personalized service.
                </p>
                <p style="color: #495057; line-height: 1.8;">
                    What began as a vision to make travel more meaningful has grown into a trusted travel company serving leisure travelers, corporate clients, and international partners. Our passion lies in creating seamless travel experiences that go beyond bookings and itineraries—we focus on the moments, memories, and connections that make every journey special.
                </p>
                <a href="<?php echo navUrl('tours'); ?>" class="travhub-btn mt-2">
                    <span>Explore Our Tours</span>
                </a>
            </div>
        </div>

        <div class="row mb-5">
            <div class="col-lg-10 mx-auto">
                <div class="card border-0 shadow-sm p-4 p-md-5" style="border-radius: 12px;">
                    <p style="color: #495057; line-height: 1.8;">
                        From breathtaking holiday destinations and cultural explorations to business travel solutions and customized tours, our team works tirelessly to ensure every detail is handled with precision and care. We combine industry expertise with a customer-first approach, helping our clients travel with confidence, comfort, and peace of mind.
                    </p>
                    <p style="color: #495057; line-height: 1.8; margin-bottom: 0;">
                        Over the years, we have built lasting relationships based on trust, reliability, and a commitment to excellence. Whether you're exploring a new destination, planning a family vacation, or organizing corporate travel, <?php echo htmlspecialchars($site_name); ?> is here to guide you every step of the way.
                    </p>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-10 mx-auto">
                <div class="card border-0 shadow-sm p-4 p-md-5" style="border-radius: 12px;">
                    <h2 class="h4 mb-4" style="color: #667eea; font-weight: 700;">Why Choose Us?</h2>
                    <ul class="list-unstyled mb-4" style="line-height: 2; color: #495057;">
                        <li>✅ Personalized travel experiences tailored to your needs</li>
                        <li>✅ Expert guidance and destination knowledge</li>
                        <li>✅ Reliable support before, during, and after your trip</li>
                        <li>✅ Competitive pricing and trusted travel partnerships</li>
                        <li>✅ A commitment to creating memorable journeys</li>
                    </ul>

                    <p style="color: #495057; line-height: 1.8; font-size: 1.05rem;">
                        <?php echo htmlspecialchars($site_name); ?> is more than a travel agency—it's a gateway to new experiences, new cultures, and endless possibilities.
                    </p>
                    <p class="lead mb-0" style="color: #667eea; font-weight: 600;">
                        Explore Beyond Boundaries. Travel with <?php echo htmlspecialchars($site_name); ?>.
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="py-5" style="background: #fff;">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm p-4 p-md-5 text-center" style="border-radius: 12px;">
                    <h2 class="mb-4" style="font-weight: 700; color: #0f172a;">Thanks and Regards</h2>
                    <p class="mb-1" style="font-weight: 600; color: #0f172a; font-size: 1.1rem;">Sandeep Gupta</p>
                    <p class="text-muted mb-3"><?php echo htmlspecialchars($site_name); ?></p>
                    <p class="text-muted mb-3">
                        <i class="fas fa-map-marker-alt me-2"></i>
                        2nd Floor, Manmohik Building,<br>
                        Chander Lok Street, Mandi (H.P)
                    </p>
                    <div class="d-flex flex-wrap justify-content-center gap-3 mb-3">
                        <a href="tel:9882076600" class="travhub-btn">
                            <span><i class="fas fa-phone me-2"></i>9882076600</span>
                        </a>
                        <a href="tel:8679666000" class="travhub-btn">
                            <span><i class="fas fa-phone me-2"></i>8679666000</span>
                        </a>
                    </div>
                    <p class="mb-4">
                        <a href="https://theworldjourney.in" target="_blank" rel="noopener noreferrer" style="color: #667eea; text-decoration: none;">
                            <i class="fas fa-globe me-2"></i>theworldjourney.in
                        </a>
                    </p>
                    <a href="<?php echo navUrl('contact'); ?>" class="travhub-btn">
                        <span>Contact Us</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>

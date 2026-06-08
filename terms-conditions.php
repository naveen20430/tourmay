<?php
require_once 'config/config.php';

// Set page variables
$page_title = 'Terms and Conditions - ' . getSetting('site_name');
$current_page = 'terms';

// Include header
include 'includes/header.php';
?>

<!-- Page Header -->
<section class="page-header" style="background: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.5)), url('assets/images/backgrounds/hero-2-1.jpg') center/cover; padding: 120px 0 60px;">
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <div class="page-header__inner text-center">
                    <h1 class="page-header__title text-white">Terms and Conditions</h1>
                    <ul class="list-unstyled page-header__breadcrumb">
                        <li><a href="<?php echo navUrl('home'); ?>" class="text-white-50">Home</a></li>
                        <li class="text-white">Terms and Conditions</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Terms and Conditions Content -->
<section class="py-5" style="background: #f8f9fa;">
    <div class="container">
        <div class="row">
            <div class="col-lg-10 mx-auto">
                <div class="card" style="border: none; border-radius: 0; box-shadow: 0 5px 20px rgba(0,0,0,0.1); padding: 40px; color: #333;">
                    <style>
                        .card, .card *, .content-section, .content-section * {
                            color: #333 !important;
                        }
                        .content-section h2, .content-section h3, .content-section h4, .content-section h5, .content-section h6 {
                            color: #667eea !important;
                        }
                        .content-section a {
                            color: #667eea !important;
                        }
                        .text-muted {
                            color: #6c757d !important;
                        }
                    </style>
                    <div class="mb-4">
                        <p class="text-muted mb-2" style="color: #6c757d !important;">Last Updated: <?php echo date('F j, Y'); ?></p>
                        <p class="text-muted" style="color: #6c757d !important;">Effective Date: <?php echo date('F j, Y'); ?></p>
                    </div>

                    <div class="content-section mb-5">
                        <h2 class="h4 mb-3" style="color: #667eea; font-weight: 700;">1. Acceptance of Terms</h2>
                        <p>
                            By accessing and using <?php echo getSetting('site_name', 'TravHub'); ?> ("the Website"), you accept and agree to be bound by the terms and provision of this agreement. If you do not agree to abide by the above, please do not use this service.
                        </p>
                        <p>
                            These Terms and Conditions ("Terms") govern your access to and use of our website, services, and any bookings or transactions made through our platform. Please read these Terms carefully before using our services.
                        </p>
                    </div>

                    <div class="content-section mb-5">
                        <h2 class="h4 mb-3" style="color: #667eea; font-weight: 700;">2. Definitions</h2>
                        <ul>
                            <li><strong>"We," "Us," "Our":</strong> Refers to <?php echo getSetting('site_name', 'TravHub'); ?> and its operators</li>
                            <li><strong>"You," "Your," "User":</strong> Refers to the individual accessing or using our services</li>
                            <li><strong>"Services":</strong> Refers to all services provided through our website, including tour bookings, travel arrangements, and related services</li>
                            <li><strong>"Booking":</strong> Refers to any reservation or purchase made through our platform</li>
                        </ul>
                    </div>

                    <div class="content-section mb-5">
                        <h2 class="h4 mb-3" style="color: #667eea; font-weight: 700;">3. Use of Website</h2>
                        
                        <h3 class="h5 mb-2 mt-4">3.1 Eligibility</h3>
                        <p>You must be at least 18 years old to make a booking through our website. By using our services, you represent and warrant that you are at least 18 years of age and have the legal capacity to enter into binding contracts.</p>

                        <h3 class="h5 mb-2 mt-4">3.2 Account Registration</h3>
                        <p>To make bookings, you may be required to create an account. You agree to:</p>
                        <ul>
                            <li>Provide accurate, current, and complete information</li>
                            <li>Maintain and update your information to keep it accurate</li>
                            <li>Maintain the security of your account credentials</li>
                            <li>Accept responsibility for all activities under your account</li>
                            <li>Notify us immediately of any unauthorized use</li>
                        </ul>

                        <h3 class="h5 mb-2 mt-4">3.3 Prohibited Activities</h3>
                        <p>You agree not to:</p>
                        <ul>
                            <li>Use the website for any illegal or unauthorized purpose</li>
                            <li>Violate any laws in your jurisdiction</li>
                            <li>Transmit any viruses, malware, or harmful code</li>
                            <li>Attempt to gain unauthorized access to our systems</li>
                            <li>Interfere with or disrupt the website or servers</li>
                            <li>Use automated systems to access the website without permission</li>
                            <li>Copy, modify, or distribute content without authorization</li>
                        </ul>
                    </div>

                    <div class="content-section mb-5">
                        <h2 class="h4 mb-3" style="color: #667eea; font-weight: 700;">4. Bookings and Reservations</h2>
                        
                        <h3 class="h5 mb-2 mt-4">4.1 Booking Process</h3>
                        <p>When you make a booking through our website:</p>
                        <ul>
                            <li>You agree to provide accurate and complete information</li>
                            <li>Your booking is subject to availability and confirmation</li>
                            <li>We reserve the right to refuse or cancel any booking</li>
                            <li>All bookings are subject to these Terms and any additional terms specific to the service</li>
                        </ul>

                        <h3 class="h5 mb-2 mt-4">4.2 Pricing</h3>
                        <p>All prices displayed on our website are:</p>
                        <ul>
                            <li>Subject to change without notice</li>
                            <li>Quoted in the currency specified</li>
                            <li>Inclusive of applicable taxes unless otherwise stated</li>
                            <li>Valid only for the dates and services specified</li>
                        </ul>

                        <h3 class="h5 mb-2 mt-4">4.3 Payment</h3>
                        <p>Payment terms:</p>
                        <ul>
                            <li>Full payment may be required at the time of booking or as specified</li>
                            <li>We accept various payment methods as displayed on our website</li>
                            <li>All payments are processed securely through third-party payment processors</li>
                            <li>You are responsible for any additional fees charged by your payment provider</li>
                        </ul>
                    </div>

                    <div class="content-section mb-5">
                        <h2 class="h4 mb-3" style="color: #667eea; font-weight: 700;">5. Cancellations and Refunds</h2>
                        
                        <h3 class="h5 mb-2 mt-4">5.1 Cancellation by You</h3>
                        <p>Cancellation policies vary by service and are specified at the time of booking. Generally:</p>
                        <ul>
                            <li>Cancellations must be made in writing or through our booking system</li>
                            <li>Refunds, if applicable, will be processed according to the cancellation policy</li>
                            <li>Administrative fees may apply to cancellations</li>
                            <li>No refunds may be available for certain services or after specific deadlines</li>
                        </ul>

                        <h3 class="h5 mb-2 mt-4">5.2 Cancellation by Us</h3>
                        <p>We reserve the right to cancel bookings due to:</p>
                        <ul>
                            <li>Insufficient bookings or participation</li>
                            <li>Unforeseen circumstances beyond our control</li>
                            <li>Safety or security concerns</li>
                            <li>Force majeure events</li>
                        </ul>
                        <p>In such cases, we will provide a full refund or offer alternative arrangements.</p>

                        <h3 class="h5 mb-2 mt-4">5.3 Refund Processing</h3>
                        <p>Refunds, when applicable, will be processed to the original payment method within 7-14 business days, depending on your payment provider.</p>
                    </div>

                    <div class="content-section mb-5">
                        <h2 class="h4 mb-3" style="color: #667eea; font-weight: 700;">6. Travel Documents and Requirements</h2>
                        <p>You are responsible for:</p>
                        <ul>
                            <li>Obtaining all necessary travel documents (passports, visas, etc.)</li>
                            <li>Ensuring all documents are valid for the duration of your travel</li>
                            <li>Complying with all entry requirements of your destination</li>
                            <li>Obtaining appropriate travel insurance</li>
                            <li>Informing us of any special requirements or medical conditions</li>
                        </ul>
                        <p>We are not responsible for any issues arising from inadequate documentation or failure to meet entry requirements.</p>
                    </div>

                    <div class="content-section mb-5">
                        <h2 class="h4 mb-3" style="color: #667eea; font-weight: 700;">7. Travel Insurance</h2>
                        <p>
                            We strongly recommend that you obtain comprehensive travel insurance covering medical expenses, trip cancellation, personal liability, and loss of personal belongings. We are not responsible for any losses or expenses incurred due to lack of adequate insurance coverage.
                        </p>
                    </div>

                    <div class="content-section mb-5">
                        <h2 class="h4 mb-3" style="color: #667eea; font-weight: 700;">8. Limitation of Liability</h2>
                        <p>
                            To the maximum extent permitted by law, we shall not be liable for any indirect, incidental, special, consequential, or punitive damages, or any loss of profits or revenues, whether incurred directly or indirectly, or any loss of data, use, goodwill, or other intangible losses resulting from your use of our services.
                        </p>
                        <p>
                            Our total liability for any claims arising from your use of our services shall not exceed the amount you paid to us for the specific service giving rise to the claim.
                        </p>
                    </div>

                    <div class="content-section mb-5">
                        <h2 class="h4 mb-3" style="color: #667eea; font-weight: 700;">9. Force Majeure</h2>
                        <p>
                            We shall not be liable for any failure or delay in performance under these Terms which is due to circumstances beyond our reasonable control, including but not limited to natural disasters, war, terrorism, pandemics, government actions, or other force majeure events.
                        </p>
                    </div>

                    <div class="content-section mb-5">
                        <h2 class="h4 mb-3" style="color: #667eea; font-weight: 700;">10. Intellectual Property</h2>
                        <p>
                            All content on this website, including text, graphics, logos, images, and software, is the property of <?php echo getSetting('site_name', 'TravHub'); ?> or its content suppliers and is protected by copyright and other intellectual property laws. You may not reproduce, distribute, modify, or create derivative works from any content without our express written permission.
                        </p>
                    </div>

                    <div class="content-section mb-5">
                        <h2 class="h4 mb-3" style="color: #667eea; font-weight: 700;">11. Third-Party Services</h2>
                        <p>
                            Our website may contain links to third-party websites or services. We are not responsible for the content, privacy policies, or practices of any third-party sites. Your interactions with third-party services are solely between you and the third party.
                        </p>
                    </div>

                    <div class="content-section mb-5">
                        <h2 class="h4 mb-3" style="color: #667eea; font-weight: 700;">12. Modifications to Terms</h2>
                        <p>
                            We reserve the right to modify these Terms at any time. We will notify users of any material changes by posting the updated Terms on this page and updating the "Last Updated" date. Your continued use of our services after such modifications constitutes acceptance of the updated Terms.
                        </p>
                    </div>

                    <div class="content-section mb-5">
                        <h2 class="h4 mb-3" style="color: #667eea; font-weight: 700;">13. Governing Law</h2>
                        <p>
                            These Terms shall be governed by and construed in accordance with the laws of the jurisdiction in which <?php echo getSetting('site_name', 'TravHub'); ?> operates, without regard to its conflict of law provisions. Any disputes arising from these Terms shall be subject to the exclusive jurisdiction of the courts in that jurisdiction.
                        </p>
                    </div>

                    <div class="content-section mb-5">
                        <h2 class="h4 mb-3" style="color: #667eea; font-weight: 700;">14. Severability</h2>
                        <p>
                            If any provision of these Terms is found to be unenforceable or invalid, that provision shall be limited or eliminated to the minimum extent necessary, and the remaining provisions shall remain in full force and effect.
                        </p>
                    </div>

                    <div class="content-section mb-5">
                        <h2 class="h4 mb-3" style="color: #667eea; font-weight: 700;">15. Contact Information</h2>
                        <p>If you have any questions about these Terms and Conditions, please contact us:</p>
                        <ul style="list-style: none; padding-left: 0;">
                            <li><i class="fas fa-envelope me-2" style="color: #667eea;"></i>Email: <a href="mailto:<?php echo getSetting('contact_email', 'info@travhub.com'); ?>"><?php echo getSetting('contact_email', 'info@travhub.com'); ?></a></li>
                            <li><i class="fas fa-phone me-2" style="color: #667eea;"></i>Phone: <a href="tel:<?php echo getSetting('contact_phone', '+1-234-567-8900'); ?>"><?php echo getSetting('contact_phone', '+1-234-567-8900'); ?></a></li>
                            <li><i class="fas fa-map-marker-alt me-2" style="color: #667eea;"></i>Address: <?php echo getSetting('site_address', '123 Travel Street, City, Country'); ?></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>


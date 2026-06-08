<?php
require_once 'config/config.php';

// Set page variables
$page_title = 'Privacy Policy - ' . getSetting('site_name');
$current_page = 'privacy';

// Include header
include 'includes/header.php';
?>

<!-- Page Header -->
<section class="page-header" style="background: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.5)), url('assets/images/backgrounds/hero-2-1.jpg') center/cover; padding: 120px 0 60px;">
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <div class="page-header__inner text-center">
                    <h1 class="page-header__title text-white">Privacy Policy</h1>
                    <ul class="list-unstyled page-header__breadcrumb">
                        <li><a href="<?php echo navUrl('home'); ?>" class="text-white-50">Home</a></li>
                        <li class="text-white">Privacy Policy</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Privacy Policy Content -->
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
                        <h2 class="h4 mb-3" style="color: #667eea; font-weight: 700;">1. Introduction</h2>
                        <p>
                            Welcome to <?php echo getSetting('site_name', 'TravHub'); ?> ("we," "our," or "us"). We are committed to protecting your privacy and ensuring you have a positive experience on our website and in using our products and services. This Privacy Policy explains how we collect, use, disclose, and safeguard your information when you visit our website <?php echo BASE_URL; ?>, including any other media form, media channel, mobile website, or mobile application related or connected thereto.
                        </p>
                        <p>
                            Please read this privacy policy carefully. If you do not agree with the terms of this privacy policy, please do not access the site.
                        </p>
                    </div>

                    <div class="content-section mb-5">
                        <h2 class="h4 mb-3" style="color: #667eea; font-weight: 700;">2. Information We Collect</h2>
                        
                        <h3 class="h5 mb-2 mt-4">2.1 Personal Information</h3>
                        <p>We may collect personal information that you voluntarily provide to us when you:</p>
                        <ul>
                            <li>Register for an account</li>
                            <li>Book a tour or service</li>
                            <li>Subscribe to our newsletter</li>
                            <li>Contact us through our contact form</li>
                            <li>Participate in surveys or promotions</li>
                        </ul>
                        <p>This information may include:</p>
                        <ul>
                            <li>Name and contact information (email address, phone number, mailing address)</li>
                            <li>Payment information (credit card details, billing address)</li>
                            <li>Travel preferences and special requirements</li>
                            <li>Passport and identification information (for travel bookings)</li>
                        </ul>

                        <h3 class="h5 mb-2 mt-4">2.2 Automatically Collected Information</h3>
                        <p>When you visit our website, we automatically collect certain information about your device, including:</p>
                        <ul>
                            <li>IP address</li>
                            <li>Browser type and version</li>
                            <li>Operating system</li>
                            <li>Pages you visit and time spent on pages</li>
                            <li>Referring website addresses</li>
                            <li>Cookies and similar tracking technologies</li>
                        </ul>
                    </div>

                    <div class="content-section mb-5">
                        <h2 class="h4 mb-3" style="color: #667eea; font-weight: 700;">3. How We Use Your Information</h2>
                        <p>We use the information we collect for various purposes, including:</p>
                        <ul>
                            <li><strong>Service Delivery:</strong> To process and manage your bookings, reservations, and payments</li>
                            <li><strong>Communication:</strong> To send you booking confirmations, updates, and customer service communications</li>
                            <li><strong>Marketing:</strong> To send you promotional materials, newsletters, and special offers (with your consent)</li>
                            <li><strong>Improvement:</strong> To analyze website usage and improve our services</li>
                            <li><strong>Legal Compliance:</strong> To comply with legal obligations and protect our rights</li>
                            <li><strong>Security:</strong> To detect and prevent fraud, abuse, and security issues</li>
                        </ul>
                    </div>

                    <div class="content-section mb-5">
                        <h2 class="h4 mb-3" style="color: #667eea; font-weight: 700;">4. Information Sharing and Disclosure</h2>
                        <p>We may share your information in the following circumstances:</p>
                        
                        <h3 class="h5 mb-2 mt-4">4.1 Service Providers</h3>
                        <p>We may share your information with third-party service providers who perform services on our behalf, such as:</p>
                        <ul>
                            <li>Payment processors</li>
                            <li>Travel operators and tour guides</li>
                            <li>Email service providers</li>
                            <li>Analytics and marketing services</li>
                        </ul>

                        <h3 class="h5 mb-2 mt-4">4.2 Legal Requirements</h3>
                        <p>We may disclose your information if required by law or in response to valid requests by public authorities.</p>

                        <h3 class="h5 mb-2 mt-4">4.3 Business Transfers</h3>
                        <p>In the event of a merger, acquisition, or sale of assets, your information may be transferred to the acquiring entity.</p>
                    </div>

                    <div class="content-section mb-5">
                        <h2 class="h4 mb-3" style="color: #667eea; font-weight: 700;">5. Cookies and Tracking Technologies</h2>
                        <p>
                            We use cookies and similar tracking technologies to track activity on our website and store certain information. Cookies are files with a small amount of data which may include an anonymous unique identifier. You can instruct your browser to refuse all cookies or to indicate when a cookie is being sent.
                        </p>
                        <p>We use cookies for:</p>
                        <ul>
                            <li>Remembering your preferences and settings</li>
                            <li>Analyzing website traffic and usage patterns</li>
                            <li>Providing personalized content and advertisements</li>
                            <li>Improving website functionality</li>
                        </ul>
                    </div>

                    <div class="content-section mb-5">
                        <h2 class="h4 mb-3" style="color: #667eea; font-weight: 700;">6. Data Security</h2>
                        <p>
                            We implement appropriate technical and organizational security measures to protect your personal information against unauthorized access, alteration, disclosure, or destruction. However, no method of transmission over the Internet or electronic storage is 100% secure, and we cannot guarantee absolute security.
                        </p>
                        <p>Our security measures include:</p>
                        <ul>
                            <li>SSL encryption for data transmission</li>
                            <li>Secure payment processing</li>
                            <li>Regular security audits and updates</li>
                            <li>Access controls and authentication</li>
                        </ul>
                    </div>

                    <div class="content-section mb-5">
                        <h2 class="h4 mb-3" style="color: #667eea; font-weight: 700;">7. Your Rights and Choices</h2>
                        <p>Depending on your location, you may have the following rights regarding your personal information:</p>
                        <ul>
                            <li><strong>Access:</strong> Request access to your personal information</li>
                            <li><strong>Correction:</strong> Request correction of inaccurate information</li>
                            <li><strong>Deletion:</strong> Request deletion of your personal information</li>
                            <li><strong>Objection:</strong> Object to processing of your personal information</li>
                            <li><strong>Portability:</strong> Request transfer of your data to another service</li>
                            <li><strong>Withdrawal:</strong> Withdraw consent for data processing</li>
                        </ul>
                        <p>To exercise these rights, please contact us using the information provided in the "Contact Us" section below.</p>
                    </div>

                    <div class="content-section mb-5">
                        <h2 class="h4 mb-3" style="color: #667eea; font-weight: 700;">8. Data Retention</h2>
                        <p>
                            We will retain your personal information only for as long as necessary to fulfill the purposes outlined in this Privacy Policy, unless a longer retention period is required or permitted by law. When we no longer need your information, we will securely delete or anonymize it.
                        </p>
                    </div>

                    <div class="content-section mb-5">
                        <h2 class="h4 mb-3" style="color: #667eea; font-weight: 700;">9. Children's Privacy</h2>
                        <p>
                            Our services are not directed to individuals under the age of 18. We do not knowingly collect personal information from children. If you become aware that a child has provided us with personal information, please contact us, and we will take steps to delete such information.
                        </p>
                    </div>

                    <div class="content-section mb-5">
                        <h2 class="h4 mb-3" style="color: #667eea; font-weight: 700;">10. International Data Transfers</h2>
                        <p>
                            Your information may be transferred to and processed in countries other than your country of residence. These countries may have data protection laws that differ from those in your country. We take appropriate measures to ensure that your information receives an adequate level of protection.
                        </p>
                    </div>

                    <div class="content-section mb-5">
                        <h2 class="h4 mb-3" style="color: #667eea; font-weight: 700;">11. Changes to This Privacy Policy</h2>
                        <p>
                            We may update this Privacy Policy from time to time. We will notify you of any changes by posting the new Privacy Policy on this page and updating the "Last Updated" date. You are advised to review this Privacy Policy periodically for any changes.
                        </p>
                    </div>

                    <div class="content-section mb-5">
                        <h2 class="h4 mb-3" style="color: #667eea; font-weight: 700;">12. Contact Us</h2>
                        <p>If you have any questions about this Privacy Policy, please contact us:</p>
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


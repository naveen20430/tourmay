<?php
require_once 'config/config.php';

// Set page variables
$page_title = getSetting('site_name') . ' || Contact Us';
$current_page = 'contact';

// Add custom CSS for contact page
$extra_css = '<link rel="stylesheet" href="' . BASE_URL . 'assets/css/contact.css">';

// Handle form submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    
    // Validation
    $errors = [];
    
    if (empty($name)) {
        $errors[] = 'Name is required';
    }
    
    if (empty($email)) {
        $errors[] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address';
    }
    
    if (empty($subject)) {
        $errors[] = 'Subject is required';
    }
    
    if (empty($message)) {
        $errors[] = 'Message is required';
    }
    
    if (empty($errors)) {
        // Save to database
        try {
            $result = $db->execute("INSERT INTO contact_inquiries (name, email, phone, subject, message, created_at) VALUES (?, ?, ?, ?, ?, NOW())", [$name, $email, $phone, $subject, $message]);
            if ($result > 0) {
                $success_message = 'Thank you for contacting us! We will get back to you soon.';
                
                // Send email notification (if email settings are configured)
                $admin_email = getSetting('contact_email');
                if ($admin_email && function_exists('mail')) {
                    $email_subject = "New Contact Form Submission: " . $subject;
                    $email_body = "
                        New contact form submission received:
                        
                        Name: $name
                        Email: $email
                        Phone: $phone
                        Subject: $subject
                        
                        Message:
                        $message
                        
                        ---
                        Sent from " . getSetting('site_name') . " website
                    ";
                    
                    $headers = "From: " . $email . "\r\n";
                    $headers .= "Reply-To: " . $email . "\r\n";
                    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
                    
                    mail($admin_email, $email_subject, $email_body, $headers);
                }
                
                // Clear form data after successful submission
                $name = $email = $phone = $subject = $message = '';
            } else {
                $error_message = 'Sorry, there was an error sending your message. Please try again.';
            }
        } catch (Exception $e) {
            $error_message = 'Sorry, there was an error sending your message. Please try again.';
        }
    } else {
        $error_message = implode('<br>', $errors);
    }
}

// Include header
include 'includes/header.php';
?>

<!-- Page Header Start -->
<section class="page-header">
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <div class="page-header__inner text-center">
                    <h1 class="page-header__title text-white">Contact Us</h1>
                    <ul class="travhub-breadcrumb list-unstyled">
                        <li><a href="<?php echo navUrl('home'); ?>">Home</a></li>
                        <li>Contact Us</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>
<!-- Page Header End -->

<!-- Contact Info Section Start -->
<section class="contact-info section-space">
    <div class="container">
        <div class="section-title text-center">
            <span class="section-title__tagline">Get In Touch</span>
            <h2 class="section-title__title">Contact Information</h2>
            <p class="section-title__text">
                We're here to help you plan your perfect journey. Get in touch with us through any of these channels.
            </p>
        </div>
        
        <div class="row mt-5">
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="contact-info__item text-center h-100 p-4 bg-white shadow-sm rounded">
                    <div class="contact-info__icon mb-3">
                        <i class="fas fa-map-marker-alt" style="font-size: 2.5rem; color: #ff6b35;"></i>
                    </div>
                    <h4 class="contact-info__title">Our Location</h4>
                    <p class="contact-info__text">
                        <?php echo getSetting('site_address') ?: '123 Travel Street, Adventure City, TC 12345'; ?>
                    </p>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="contact-info__item text-center h-100 p-4 bg-white shadow-sm rounded">
                    <div class="contact-info__icon mb-3">
                        <i class="fas fa-phone" style="font-size: 2.5rem; color: #ff6b35;"></i>
                    </div>
                    <h4 class="contact-info__title">Phone Number</h4>
                    <p class="contact-info__text">
                        <a href="tel:<?php echo getSetting('contact_phone') ?: '+1-234-567-8900'; ?>" class="text-decoration-none">
                            <?php echo getSetting('contact_phone') ?: '+1-234-567-8900'; ?>
                        </a>
                    </p>
                    <small class="text-muted">Available 24/7 for emergencies</small>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="contact-info__item text-center h-100 p-4 bg-white shadow-sm rounded">
                    <div class="contact-info__icon mb-3">
                        <i class="fas fa-envelope" style="font-size: 2.5rem; color: #ff6b35;"></i>
                    </div>
                    <h4 class="contact-info__title">Email Address</h4>
                    <p class="contact-info__text">
                        <a href="mailto:<?php echo getSetting('contact_email') ?: 'info@travhub.com'; ?>" class="text-decoration-none">
                            <?php echo getSetting('contact_email') ?: 'info@travhub.com'; ?>
                        </a>
                    </p>
                    <small class="text-muted">We respond within 24 hours</small>
                </div>
            </div>
        </div>
    </div>
</section>
<!-- Contact Info Section End -->

<!-- Contact Form Section Start -->
<section class="contact-form section-space" style="background: #f8f9fa;">
    <div class="container">
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="contact-form__inner bg-white p-5 rounded shadow-lg">
                    <div class="section-title text-center mb-4">
                        <span class="section-title__tagline">Send Message</span>
                        <h2 class="section-title__title">Drop Us a Line</h2>
                        <p class="section-title__text">
                            Have questions about our tours? Need help planning your trip? Send us a message and we'll get back to you promptly.
                        </p>
                    </div>
                    
                    <?php if (!empty($success_message)): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i>
                            <?php echo $success_message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($error_message)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?php echo $error_message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="" class="contact-form__form">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?php echo htmlspecialchars($name ?? ''); ?>" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" id="phone" name="phone" 
                                       value="<?php echo htmlspecialchars($phone ?? ''); ?>">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="subject" class="form-label">Subject <span class="text-danger">*</span></label>
                                <select class="form-select" id="subject" name="subject" required>
                                    <option value="">Select a subject</option>
                                    <option value="General Inquiry" <?php echo (isset($subject) && $subject == 'General Inquiry') ? 'selected' : ''; ?>>General Inquiry</option>
                                    <option value="Tour Booking" <?php echo (isset($subject) && $subject == 'Tour Booking') ? 'selected' : ''; ?>>Tour Booking</option>
                                    <option value="Tour Information" <?php echo (isset($subject) && $subject == 'Tour Information') ? 'selected' : ''; ?>>Tour Information</option>
                                    <option value="Custom Trip" <?php echo (isset($subject) && $subject == 'Custom Trip') ? 'selected' : ''; ?>>Custom Trip Planning</option>
                                    <option value="Support" <?php echo (isset($subject) && $subject == 'Support') ? 'selected' : ''; ?>>Customer Support</option>
                                    <option value="Partnership" <?php echo (isset($subject) && $subject == 'Partnership') ? 'selected' : ''; ?>>Business Partnership</option>
                                    <option value="Other" <?php echo (isset($subject) && $subject == 'Other') ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="message" class="form-label">Your Message <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="message" name="message" rows="6" 
                                      placeholder="Tell us about your travel plans, questions, or how we can help you..." required><?php echo htmlspecialchars($message ?? ''); ?></textarea>
                        </div>
                        
                        <div class="text-center">
                            <button type="submit" class="travhub-btn">
                                <span><i class="fas fa-paper-plane me-2"></i>Send Message</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
<!-- Contact Form Section End -->

<!-- Map Section Start -->
<section class="contact-map">
    <div class="container-fluid p-0">
        <div class="row g-0">
            <div class="col-lg-12">
                <div class="contact-map__inner" style="height: 450px;">
                    <!-- Google Maps Embed - Replace with your actual location -->
                    <iframe 
                        src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3022.9663095343008!2d-74.00425878459418!3d40.74844097932681!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x89c259bf5c1654f3%3A0xc80f9e7f8a4e36f!2sNew%20York%2C%20NY%2C%20USA!5e0!3m2!1sen!2sus!4v1635959783267!5m2!1sen!2sus" 
                        width="100%" 
                        height="450" 
                        style="border:0;" 
                        allowfullscreen="" 
                        loading="lazy" 
                        referrerpolicy="no-referrer-when-downgrade">
                    </iframe>
                </div>
            </div>
        </div>
    </div>
</section>
<!-- Map Section End -->

<!-- Business Hours Section Start -->
<section class="business-hours section-space">
    <div class="container">
        <div class="row">
            <div class="col-lg-6 mx-auto">
                <div class="business-hours__inner text-center bg-white p-5 rounded shadow">
                    <div class="section-title mb-4">
                        <span class="section-title__tagline">Working Hours</span>
                        <h3 class="section-title__title">When We're Available</h3>
                    </div>
                    
                    <div class="business-hours__list">
                        <div class="row align-items-center py-2 border-bottom">
                            <div class="col-6 text-start">
                                <strong>Monday - Friday</strong>
                            </div>
                            <div class="col-6 text-end">
                                <span class="text-primary">9:00 AM - 6:00 PM</span>
                            </div>
                        </div>
                        
                        <div class="row align-items-center py-2 border-bottom">
                            <div class="col-6 text-start">
                                <strong>Saturday</strong>
                            </div>
                            <div class="col-6 text-end">
                                <span class="text-primary">10:00 AM - 4:00 PM</span>
                            </div>
                        </div>
                        
                        <div class="row align-items-center py-2 border-bottom">
                            <div class="col-6 text-start">
                                <strong>Sunday</strong>
                            </div>
                            <div class="col-6 text-end">
                                <span class="text-muted">Closed</span>
                            </div>
                        </div>
                        
                        <div class="row align-items-center py-2">
                            <div class="col-6 text-start">
                                <strong>Emergency Support</strong>
                            </div>
                            <div class="col-6 text-end">
                                <span class="text-danger">24/7 Available</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <small class="text-muted">
                            * Emergency support is available 24/7 for travelers currently on tour
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<!-- Business Hours Section End -->

<?php include 'includes/footer.php'; ?>
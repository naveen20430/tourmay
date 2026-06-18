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

$contact_address = getSetting('site_address') ?: '2nd Floor, Manmohik Building, Chander Lok Street, Mandi (H.P)';
$contact_phone = getSetting('contact_phone') ?: '+91 9882076600';
$contact_email = getSetting('contact_email') ?: 'info@travhub.com';

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

<!-- Contact Two Column Section Start -->
<section class="contact-page section-space">
    <div class="container">
        <div class="contact-page__grid">
            <div class="contact-page__col contact-page__col--info">
                <div class="contact-page__info h-100">
                    <span class="section-title__tagline">Get In Touch</span>
                    <h2 class="contact-page__title">Contact Information</h2>
                    <p class="contact-page__intro">
                        We're here to help you plan your perfect journey. Reach out through any of these channels.
                    </p>

                    <div class="contact-page__details">
                        <div class="contact-page__detail">
                            <div class="contact-page__detail-icon">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <div>
                                <h4>Our Location</h4>
                                <p><?php echo htmlspecialchars($contact_address); ?></p>
                            </div>
                        </div>

                        <div class="contact-page__detail">
                            <div class="contact-page__detail-icon">
                                <i class="fas fa-phone"></i>
                            </div>
                            <div>
                                <h4>Phone Number</h4>
                                <p>
                                    <a href="tel:<?php echo preg_replace('/\s+/', '', $contact_phone); ?>">
                                        <?php echo htmlspecialchars($contact_phone); ?>
                                    </a>
                                </p>
                                <small>Available 24/7 for emergencies</small>
                            </div>
                        </div>

                        <div class="contact-page__detail">
                            <div class="contact-page__detail-icon">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div>
                                <h4>Email Address</h4>
                                <p>
                                    <a href="mailto:<?php echo htmlspecialchars($contact_email); ?>">
                                        <?php echo htmlspecialchars($contact_email); ?>
                                    </a>
                                </p>
                                <small>We respond within 24 hours</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="contact-page__col contact-page__col--form">
                <div class="contact-form__inner contact-page__form h-100">
                    <div class="section-title mb-4">
                        <span class="section-title__tagline">Send Message</span>
                        <h2 class="section-title__title">Drop Us a Line</h2>
                        <p class="section-title__text mb-0">
                            Have questions about our tours? Send us a message and we'll get back to you promptly.
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

                        <div>
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
<!-- Contact Two Column Section End -->

<?php include 'includes/footer.php'; ?>

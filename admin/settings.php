<?php
require_once '../config/config.php';
requireLogin();

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'update_settings') {
        $success = true;
        $updated_count = 0;
        
        foreach ($_POST['settings'] as $key => $value) {
            try {
                // Sanitize the value
                $value = trim($value);
                
                // Check if setting exists
                $existing = $db->fetch("SELECT id FROM site_settings WHERE setting_key = ?", [$key]);
                
                if ($existing) {
                    // Update existing setting
                    $result = $db->execute("UPDATE site_settings SET setting_value = ? WHERE setting_key = ?", [$value, $key]);
                } else {
                    // Insert new setting
                    $result = $db->execute("INSERT INTO site_settings (setting_key, setting_value, setting_type) VALUES (?, ?, ?)", [$key, $value, 'text']);
                }
                
                // Count successful operations (both 0 and positive numbers indicate success)
                $updated_count++;
                
            } catch (Exception $e) {
                $success = false;
                $error_message = "Error updating setting '$key': " . $e->getMessage();
                error_log("Settings update error for key '$key': " . $e->getMessage());
                break;
            }
        }
        
        if ($success) {
            $success_message = "$updated_count setting(s) updated successfully!";
        }
    }
}

// Get all current settings
try {
    $current_settings = $db->fetchAll("SELECT * FROM site_settings ORDER BY setting_key");
    $settings_array = [];
    foreach ($current_settings as $setting) {
        $settings_array[$setting['setting_key']] = $setting['setting_value'];
    }
} catch (Exception $e) {
    $settings_array = [];
    $error_message = "Error loading settings: " . $e->getMessage();
}

// Default settings structure
$default_settings = [
    'site_name' => 'TravHub',
    'site_tagline' => 'Adventure & Experience The Travel',
    'site_description' => 'Your ultimate travel companion for amazing adventures and unforgettable experiences.',
    'site_email' => 'info@travhub.com',
    'contact_email' => 'contact@travhub.com',
    'site_phone' => '+1 234 567 8900',
    'contact_phone' => '+1 234 567 8900',
    'site_address' => '123 Travel Street, Adventure City, TC 12345',
    'opening_hours' => '9:00 AM - 6:00 PM',
    'facebook_url' => '',
    'twitter_url' => '',
    'instagram_url' => '',
    'linkedin_url' => '',
    'youtube_url' => '',
    'google_analytics_id' => '',
    'meta_keywords' => 'travel, tours, booking, adventure, vacation',
    'currency' => 'USD',
    'currency_symbol' => '$',
    'timezone' => 'America/New_York',
    'items_per_page' => '12',
    'enable_booking' => '1',
    'enable_blog' => '1',
    'enable_newsletter' => '1',
    'maintenance_mode' => '0',
    'razorpay_key_id' => '',
    'razorpay_key_secret' => '',
    'cash_payment_note' => 'Pay in cash at our office or to the tour guide before departure.',
    'twilio_account_sid' => '',
    'twilio_api_key_sid' => '',
    'twilio_auth_token' => '',
    'twilio_whatsapp_from' => 'whatsapp:+14155238886',
    'twilio_whatsapp_content_sid' => 'HXb5b62575e6e4ff6129ad7c8efe1f983e',
    'twilio_whatsapp_sandbox_join' => ''
];

// Merge with current settings
foreach ($default_settings as $key => $default_value) {
    if (!isset($settings_array[$key])) {
        $settings_array[$key] = $default_value;
    }
}

$page_title = 'Site Settings';
include 'includes/header.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Site Settings</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Settings</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle mr-2"></i>
                    <?php echo $success_message; ?>
                    <button type="button" class="close" data-dismiss="alert">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    <?php echo $error_message; ?>
                    <button type="button" class="close" data-dismiss="alert">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="action" value="update_settings">
                
                <!-- Basic Site Information -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-info-circle mr-2"></i>Basic Site Information
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="site_name">Site Name</label>
                                    <input type="text" class="form-control" id="site_name" 
                                           name="settings[site_name]" 
                                           value="<?php echo htmlspecialchars($settings_array['site_name']); ?>"
                                           required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="site_tagline">Site Tagline</label>
                                    <input type="text" class="form-control" id="site_tagline" 
                                           name="settings[site_tagline]" 
                                           value="<?php echo htmlspecialchars($settings_array['site_tagline']); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="site_description">Site Description</label>
                            <textarea class="form-control" id="site_description" rows="3"
                                      name="settings[site_description]"><?php echo htmlspecialchars($settings_array['site_description']); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="meta_keywords">Meta Keywords (comma separated)</label>
                            <input type="text" class="form-control" id="meta_keywords" 
                                   name="settings[meta_keywords]" 
                                   value="<?php echo htmlspecialchars($settings_array['meta_keywords']); ?>">
                        </div>
                    </div>
                </div>

                <!-- Contact Information -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-address-book mr-2"></i>Contact Information
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="site_email">Site Email</label>
                                    <input type="email" class="form-control" id="site_email" 
                                           name="settings[site_email]" 
                                           value="<?php echo htmlspecialchars($settings_array['site_email']); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="contact_email">Contact Form Email</label>
                                    <input type="email" class="form-control" id="contact_email" 
                                           name="settings[contact_email]" 
                                           value="<?php echo htmlspecialchars($settings_array['contact_email']); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="site_phone">Site Phone</label>
                                    <input type="tel" class="form-control" id="site_phone" 
                                           name="settings[site_phone]" 
                                           value="<?php echo htmlspecialchars($settings_array['site_phone']); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="contact_phone">Contact Phone</label>
                                    <input type="tel" class="form-control" id="contact_phone" 
                                           name="settings[contact_phone]" 
                                           value="<?php echo htmlspecialchars($settings_array['contact_phone']); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="site_address">Business Address</label>
                            <textarea class="form-control" id="site_address" rows="2"
                                      name="settings[site_address]"><?php echo htmlspecialchars($settings_array['site_address']); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="opening_hours">Opening Hours</label>
                            <input type="text" class="form-control" id="opening_hours" 
                                   name="settings[opening_hours]" 
                                   value="<?php echo htmlspecialchars($settings_array['opening_hours']); ?>"
                                   placeholder="e.g. Mon-Fri 9:00 AM - 6:00 PM">
                        </div>
                    </div>
                </div>

                <!-- Social Media Links -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-share-alt mr-2"></i>Social Media Links
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="facebook_url">
                                        <i class="fab fa-facebook mr-1"></i> Facebook URL
                                    </label>
                                    <input type="url" class="form-control" id="facebook_url" 
                                           name="settings[facebook_url]" 
                                           value="<?php echo htmlspecialchars($settings_array['facebook_url']); ?>"
                                           placeholder="https://facebook.com/yourpage">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="twitter_url">
                                        <i class="fab fa-twitter mr-1"></i> Twitter URL
                                    </label>
                                    <input type="url" class="form-control" id="twitter_url" 
                                           name="settings[twitter_url]" 
                                           value="<?php echo htmlspecialchars($settings_array['twitter_url']); ?>"
                                           placeholder="https://twitter.com/yourhandle">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="instagram_url">
                                        <i class="fab fa-instagram mr-1"></i> Instagram URL
                                    </label>
                                    <input type="url" class="form-control" id="instagram_url" 
                                           name="settings[instagram_url]" 
                                           value="<?php echo htmlspecialchars($settings_array['instagram_url']); ?>"
                                           placeholder="https://instagram.com/yourhandle">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="linkedin_url">
                                        <i class="fab fa-linkedin mr-1"></i> LinkedIn URL
                                    </label>
                                    <input type="url" class="form-control" id="linkedin_url" 
                                           name="settings[linkedin_url]" 
                                           value="<?php echo htmlspecialchars($settings_array['linkedin_url']); ?>"
                                           placeholder="https://linkedin.com/company/yourcompany">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="youtube_url">
                                <i class="fab fa-youtube mr-1"></i> YouTube Channel URL
                            </label>
                            <input type="url" class="form-control" id="youtube_url" 
                                   name="settings[youtube_url]" 
                                   value="<?php echo htmlspecialchars($settings_array['youtube_url']); ?>"
                                   placeholder="https://youtube.com/channel/yourchannel">
                        </div>
                    </div>
                </div>

                <!-- System Settings -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-cog mr-2"></i>System Settings
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="currency">Currency</label>
                                    <select class="form-control" id="currency" name="settings[currency]">
                                        <option value="USD" <?php echo $settings_array['currency'] == 'USD' ? 'selected' : ''; ?>>USD - US Dollar</option>
                                        <option value="EUR" <?php echo $settings_array['currency'] == 'EUR' ? 'selected' : ''; ?>>EUR - Euro</option>
                                        <option value="GBP" <?php echo $settings_array['currency'] == 'GBP' ? 'selected' : ''; ?>>GBP - British Pound</option>
                                        <option value="INR" <?php echo $settings_array['currency'] == 'INR' ? 'selected' : ''; ?>>INR - Indian Rupee</option>
                                        <option value="CAD" <?php echo $settings_array['currency'] == 'CAD' ? 'selected' : ''; ?>>CAD - Canadian Dollar</option>
                                        <option value="AUD" <?php echo $settings_array['currency'] == 'AUD' ? 'selected' : ''; ?>>AUD - Australian Dollar</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="currency_symbol">Currency Symbol</label>
                                    <input type="text" class="form-control" id="currency_symbol" 
                                           name="settings[currency_symbol]" 
                                           value="<?php echo htmlspecialchars($settings_array['currency_symbol']); ?>"
                                           maxlength="3">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="items_per_page">Items Per Page</label>
                                    <input type="number" class="form-control" id="items_per_page" 
                                           name="settings[items_per_page]" 
                                           value="<?php echo htmlspecialchars($settings_array['items_per_page']); ?>"
                                           min="5" max="50">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="timezone">Timezone</label>
                            <select class="form-control" id="timezone" name="settings[timezone]">
                                <option value="America/New_York" <?php echo $settings_array['timezone'] == 'America/New_York' ? 'selected' : ''; ?>>Eastern Time (New York)</option>
                                <option value="America/Chicago" <?php echo $settings_array['timezone'] == 'America/Chicago' ? 'selected' : ''; ?>>Central Time (Chicago)</option>
                                <option value="America/Denver" <?php echo $settings_array['timezone'] == 'America/Denver' ? 'selected' : ''; ?>>Mountain Time (Denver)</option>
                                <option value="America/Los_Angeles" <?php echo $settings_array['timezone'] == 'America/Los_Angeles' ? 'selected' : ''; ?>>Pacific Time (Los Angeles)</option>
                                <option value="Europe/London" <?php echo $settings_array['timezone'] == 'Europe/London' ? 'selected' : ''; ?>>GMT (London)</option>
                                <option value="Europe/Paris" <?php echo $settings_array['timezone'] == 'Europe/Paris' ? 'selected' : ''; ?>>CET (Paris)</option>
                                <option value="Asia/Tokyo" <?php echo $settings_array['timezone'] == 'Asia/Tokyo' ? 'selected' : ''; ?>>JST (Tokyo)</option>
                                <option value="Asia/Kolkata" <?php echo $settings_array['timezone'] == 'Asia/Kolkata' ? 'selected' : ''; ?>>IST (India)</option>
                                <option value="Australia/Sydney" <?php echo $settings_array['timezone'] == 'Australia/Sydney' ? 'selected' : ''; ?>>AEDT (Sydney)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Feature Toggles -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-toggle-on mr-2"></i>Feature Settings
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-check">
                                    <input type="hidden" name="settings[enable_booking]" value="0">
                                    <input class="form-check-input" type="checkbox" id="enable_booking" 
                                           name="settings[enable_booking]" value="1"
                                           <?php echo $settings_array['enable_booking'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="enable_booking">
                                        Enable Booking System
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-check">
                                    <input type="hidden" name="settings[enable_blog]" value="0">
                                    <input class="form-check-input" type="checkbox" id="enable_blog" 
                                           name="settings[enable_blog]" value="1"
                                           <?php echo $settings_array['enable_blog'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="enable_blog">
                                        Enable Blog
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-check">
                                    <input type="hidden" name="settings[enable_newsletter]" value="0">
                                    <input class="form-check-input" type="checkbox" id="enable_newsletter" 
                                           name="settings[enable_newsletter]" value="1"
                                           <?php echo $settings_array['enable_newsletter'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="enable_newsletter">
                                        Enable Newsletter
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-check">
                                    <input type="hidden" name="settings[maintenance_mode]" value="0">
                                    <input class="form-check-input" type="checkbox" id="maintenance_mode" 
                                           name="settings[maintenance_mode]" value="1"
                                           <?php echo $settings_array['maintenance_mode'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="maintenance_mode">
                                        <span class="text-warning">Maintenance Mode</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment Settings -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-credit-card mr-2"></i>Payment Settings
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="razorpay_key_id">Razorpay Key ID</label>
                                    <input type="text" class="form-control" id="razorpay_key_id"
                                           name="settings[razorpay_key_id]"
                                           value="<?php echo htmlspecialchars($settings_array['razorpay_key_id']); ?>"
                                           placeholder="rzp_test_xxxxxxxx">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="razorpay_key_secret">Razorpay Key Secret</label>
                                    <input type="password" class="form-control" id="razorpay_key_secret"
                                           name="settings[razorpay_key_secret]"
                                           value="<?php echo htmlspecialchars($settings_array['razorpay_key_secret']); ?>"
                                           placeholder="Enter Razorpay secret key">
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="cash_payment_note">Cash Payment Note (shown on invoice)</label>
                            <textarea class="form-control" id="cash_payment_note" rows="3"
                                      name="settings[cash_payment_note]"><?php echo htmlspecialchars($settings_array['cash_payment_note']); ?></textarea>
                        </div>
                        <p class="text-muted mb-0">
                            Add your Razorpay test or live keys here to enable online checkout. Cash/manual payment works without Razorpay keys.
                        </p>
                    </div>
                </div>

                <!-- WhatsApp OTP / Twilio -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fab fa-whatsapp mr-2"></i>WhatsApp OTP Login (Twilio)
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="twilio_account_sid">Twilio Account SID</label>
                                    <input type="text" class="form-control" id="twilio_account_sid"
                                           name="settings[twilio_account_sid]"
                                           value="<?php echo htmlspecialchars($settings_array['twilio_account_sid']); ?>"
                                           placeholder="ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx">
                                    <small class="form-text text-muted">Must start with AC (used in API URL).</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="twilio_api_key_sid">Twilio API Key SID</label>
                                    <input type="text" class="form-control" id="twilio_api_key_sid"
                                           name="settings[twilio_api_key_sid]"
                                           value="<?php echo htmlspecialchars($settings_array['twilio_api_key_sid'] ?? ''); ?>"
                                           placeholder="SKxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx">
                                    <small class="form-text text-muted">Optional. Starts with SK. When set, Secret below is the API Key Secret.</small>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="twilio_auth_token">Twilio Auth Token / API Key Secret</label>
                                    <input type="password" class="form-control" id="twilio_auth_token"
                                           name="settings[twilio_auth_token]"
                                           value="<?php echo htmlspecialchars($settings_array['twilio_auth_token']); ?>"
                                           placeholder="Auth token or API key secret">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="twilio_whatsapp_from">WhatsApp From Number</label>
                                    <input type="text" class="form-control" id="twilio_whatsapp_from"
                                           name="settings[twilio_whatsapp_from]"
                                           value="<?php echo htmlspecialchars($settings_array['twilio_whatsapp_from']); ?>"
                                           placeholder="whatsapp:+14155238886">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="twilio_whatsapp_content_sid">WhatsApp Content Template SID</label>
                                    <input type="text" class="form-control" id="twilio_whatsapp_content_sid"
                                           name="settings[twilio_whatsapp_content_sid]"
                                           value="<?php echo htmlspecialchars($settings_array['twilio_whatsapp_content_sid']); ?>"
                                           placeholder="HXxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx">
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="twilio_whatsapp_sandbox_join">Twilio Sandbox Join Code</label>
                            <input type="text" class="form-control" id="twilio_whatsapp_sandbox_join"
                                   name="settings[twilio_whatsapp_sandbox_join]"
                                   value="<?php echo htmlspecialchars($settings_array['twilio_whatsapp_sandbox_join']); ?>"
                                   placeholder="e.g. happy-tiger">
                            <small class="text-muted">Required for Twilio sandbox (+14155238886). Each new user must WhatsApp <code>join your-code</code> to the sandbox number before OTP works.</small>
                        </div>
                        <p class="text-muted mb-0">
                            Used for WhatsApp OTP on login/register/profile. For production, use an approved Twilio WhatsApp Business sender so OTP works on any number without sandbox join.
                        </p>
                    </div>
                </div>

                <!-- Analytics & Tracking -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-chart-line mr-2"></i>Analytics & Tracking
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="google_analytics_id">Google Analytics ID</label>
                            <input type="text" class="form-control" id="google_analytics_id" 
                                   name="settings[google_analytics_id]" 
                                   value="<?php echo htmlspecialchars($settings_array['google_analytics_id']); ?>"
                                   placeholder="GA-XXXXXXXXX-X or G-XXXXXXXXXX">
                            <small class="form-text text-muted">
                                Enter your Google Analytics tracking ID to enable analytics tracking.
                            </small>
                        </div>
                    </div>
                </div>

                <!-- Save Button -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-footer">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-save mr-2"></i>Save All Settings
                                </button>
                                <a href="index.php" class="btn btn-secondary btn-lg ml-2">
                                    <i class="fas fa-times mr-2"></i>Cancel
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
            </form>

        </div>
    </section>
</div>

<script>
// Form validation and enhancements
document.addEventListener('DOMContentLoaded', function() {
    // Maintenance mode warning
    const maintenanceCheckbox = document.getElementById('maintenance_mode');
    if (maintenanceCheckbox) {
        maintenanceCheckbox.addEventListener('change', function() {
            if (this.checked) {
                alert('Warning: Enabling maintenance mode will show a maintenance page to all visitors except admins.');
            }
        });
    }
    
    // Auto-update currency symbol based on currency selection
    const currencySelect = document.getElementById('currency');
    const currencySymbol = document.getElementById('currency_symbol');
    
    if (currencySelect && currencySymbol) {
        currencySelect.addEventListener('change', function() {
            const symbols = {
                'USD': '$',
                'EUR': '€',
                'GBP': '£',
                'INR': '₹',
                'CAD': 'C$',
                'AUD': 'A$'
            };
            
            if (symbols[this.value]) {
                currencySymbol.value = symbols[this.value];
            }
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>
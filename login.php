<?php
require_once 'config/config.php';
require_once 'includes/whatsapp_otp_helpers.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    $redirect = $_GET['redirect'] ?? navUrl('home');
    header('Location: ' . $redirect);
    exit;
}

$errors = [];
$success_message = '';
$whatsappEnabled = mobileOtpIsConfigured();
$whatsappSandboxNotice = getWhatsAppSandboxInstructions();
$otpChannelLabel = fast2smsIsConfigured() ? 'SMS' : 'WhatsApp';
$loginRedirect = $_GET['redirect'] ?? navUrl('home');
$activeLoginTab = 'whatsapp';

// Handle login form submission
if ($_POST) {
    $activeLoginTab = 'email';
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    // Validation
    if (empty($email)) {
        $errors[] = 'Email is required';
    }
    if (empty($password)) {
        $errors[] = 'Password is required';
    }
    
    // Authenticate user
    if (empty($errors)) {
        try {
            $user = $db->fetch("SELECT * FROM users WHERE email = ? AND status = 'active'", [$email]);
            
            if ($user && password_verify($password, $user['password'])) {
                // Login successful
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_first_name'] = $user['first_name'];
                
                // Update last login
                $db->execute("UPDATE users SET updated_at = NOW() WHERE id = ?", [$user['id']]);
                
                // Handle remember me
                if ($remember) {
                    $token = bin2hex(random_bytes(16));
                    setcookie('remember_token', $token, time() + (86400 * 30), '/', '', false, true); // 30 days
                    // In a real app, store this token in database with expiry
                }
                
                // Redirect to intended page or dashboard
                $redirect = $_GET['redirect'] ?? navUrl('home');
                header('Location: ' . $redirect);
                exit;
            } else {
                $errors[] = 'Invalid email or password';
            }
        } catch (Exception $e) {
            $errors[] = 'Login failed. Please try again.';
        }
    }
}

// Check for registration success message
if (isset($_GET['registered']) && $_GET['registered'] == '1') {
    $success_message = 'Registration successful! You can now login with your credentials.';
}

// Set page variables
$page_title = 'Login - ' . (getSetting('site_name') ?: 'Travel Hub');
$current_page = 'login';
$extra_css = cssWithCache('assets/css/auth-pages.css');

// Include header
include 'includes/header.php';
?>

<!-- Login Section -->
<section class="auth-section login-section">
    <div class="container">
        <div class="auth-page-wrap">
                <div class="auth-card login-card">
                    <div class="auth-card__head login-header">
                        <h2>
                            <i class="fas fa-sign-in-alt me-2"></i>Welcome Back
                        </h2>
                        <p>Sign in to your travel account</p>
                    </div>
                    
                    <div class="auth-card__body">
                        <!-- Success Message -->
                        <?php if ($success_message): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Error Messages -->
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <?php if (count($errors) == 1): ?>
                                    <?php echo htmlspecialchars($errors[0]); ?>
                                <?php else: ?>
                                    <ul class="mb-0 mt-2">
                                        <?php foreach ($errors as $error): ?>
                                            <li><?php echo htmlspecialchars($error); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Login Tabs -->
                        <div class="auth-tabs login-tabs">
                            <button type="button" class="auth-tab-btn login-tab-btn <?php echo $activeLoginTab === 'whatsapp' ? 'active' : ''; ?>" data-login-tab="whatsapp">
                                <i class="fas fa-mobile-alt"></i> Mobile OTP
                            </button>
                            <button type="button" class="auth-tab-btn login-tab-btn <?php echo $activeLoginTab === 'email' ? 'active' : ''; ?>" data-login-tab="email">
                                <i class="fas fa-envelope"></i> Email
                            </button>
                        </div>

                        <!-- Email Login -->
                        <div class="auth-panel login-panel <?php echo $activeLoginTab === 'email' ? 'active' : ''; ?>" id="loginPanelEmail">
                        <form method="POST" novalidate>
                            <div class="auth-form-field">
                                <label class="form-label" for="loginEmail">Email Address</label>
                                <input type="email" name="email" id="loginEmail" class="form-control" 
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                                       required placeholder="Enter your email">
                                <div class="invalid-feedback">Please enter a valid email address</div>
                            </div>
                            
                            <div class="auth-form-field">
                                <label class="form-label" for="password">Password</label>
                                <div class="auth-input-group">
                                    <input type="password" name="password" class="form-control" 
                                           required placeholder="Enter your password" id="password">
                                    <button type="button" class="auth-input-group__btn" id="togglePassword" aria-label="Show password">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="invalid-feedback">Password is required</div>
                            </div>
                            
                            <div class="auth-form-meta">
                                <label class="auth-remember-check" for="remember">
                                    <input class="form-check-input" type="checkbox" name="remember" id="remember"
                                           <?php echo isset($_POST['remember']) ? 'checked' : ''; ?>>
                                    <span>Remember me</span>
                                </label>
                                <a href="forgot-password.php" class="auth-form-meta__link">Forgot Password?</a>
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-lg w-100 auth-submit-btn">
                                <i class="fas fa-sign-in-alt me-2"></i>Sign In
                            </button>
                        </form>
                        </div>

                        <!-- Mobile OTP Login -->
                        <div class="auth-panel login-panel <?php echo $activeLoginTab === 'whatsapp' ? 'active' : ''; ?>" id="loginPanelWhatsapp">
                            <?php if ($whatsappEnabled): ?>
                                <div class="auth-note whatsapp-note">
                                    <i class="fas fa-mobile-alt me-1"></i>
                                    We will send a 5-digit verification code to your mobile number.
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    Mobile OTP login is not configured yet. Please use email login or contact support.
                                </div>
                            <?php endif; ?>

                            <div id="whatsappOtpAlert" class="alert d-none" role="alert"></div>

                            <div id="whatsappStepPhone">
                                <div class="auth-form-field">
                                    <label class="form-label" for="whatsappPhone">Mobile Number</label>
                                    <input type="tel" id="whatsappPhone" class="form-control"
                                           maxlength="16" placeholder="e.g. +91 9876543210 or 9876543210"
                                           <?php echo $whatsappEnabled ? '' : 'disabled'; ?>>
                                    <small class="auth-field-hint">Include country code for numbers outside India.</small>
                                </div>
                                <button type="button" class="btn btn-primary btn-lg w-100 auth-submit-btn" id="sendWhatsappOtpBtn" <?php echo $whatsappEnabled ? '' : 'disabled'; ?>>
                                    <i class="fas fa-mobile-alt me-2"></i>Send OTP
                                </button>
                            </div>

                            <div id="whatsappStepOtp" class="d-none">
                                <p class="auth-otp-sent">
                                    OTP sent to <strong id="whatsappPhoneDisplay"></strong>
                                    <button type="button" class="btn btn-link btn-sm p-0 align-baseline" id="changeWhatsappPhone">Change</button>
                                </p>
                                <div class="auth-form-field">
                                    <label class="form-label" for="whatsappOtp">Enter OTP</label>
                                    <input type="text" id="whatsappOtp" class="form-control auth-otp-input otp-input"
                                           maxlength="5" pattern="[0-9]{5}" placeholder="5-digit code" inputmode="numeric">
                                </div>
                                <div class="auth-otp-actions">
                                    <button type="button" class="btn btn-primary btn-lg w-100 auth-submit-btn" id="verifyWhatsappOtpBtn">
                                        <i class="fas fa-check-circle me-2"></i>Verify &amp; Login
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary w-100" id="resendWhatsappOtpBtn">
                                        Resend OTP
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <hr class="auth-divider">
                        <p class="auth-footer-text">
                            Don't have an account?
                            <a href="<?php echo navUrl('register'); ?>">Create Account</a>
                        </p>
                        
                        <div class="auth-back-link">
                            <a href="<?php echo navUrl('home'); ?>" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-home me-2"></i>Back to Homepage
                            </a>
                        </div>
                    </div>
                </div>
        </div>
    </div>
</section>

<?php ob_start(); ?>
<script>
(function() {
    'use strict';

    window.addEventListener('load', function() {
        Array.prototype.forEach.call(document.getElementsByTagName('form'), function(form) {
            form.addEventListener('submit', function(event) {
                if (form.checkValidity() === false) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    });

    var togglePassword = document.getElementById('togglePassword');
    if (togglePassword) {
        togglePassword.addEventListener('click', function() {
            var password = document.getElementById('password');
            var icon = this.querySelector('i');
            if (!password || !icon) return;
            if (password.type === 'password') {
                password.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                password.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    }

    setTimeout(function() {
        document.querySelectorAll('.alert-success').forEach(function(alert) {
            if (typeof bootstrap !== 'undefined' && bootstrap.Alert) {
                bootstrap.Alert.getOrCreateInstance(alert).close();
            }
        });
    }, 5000);

    document.querySelectorAll('[data-login-tab]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var tab = btn.getAttribute('data-login-tab');
            document.querySelectorAll('[data-login-tab]').forEach(function(item) {
                item.classList.toggle('active', item === btn);
            });
            document.getElementById('loginPanelEmail').classList.toggle('active', tab === 'email');
            document.getElementById('loginPanelWhatsapp').classList.toggle('active', tab === 'whatsapp');
        });
    });
})();

(function() {
        const redirectUrl = <?php echo json_encode($loginRedirect); ?>;
        const sendUrl = <?php echo json_encode(BASE_URL . 'api/whatsapp-send-otp.php'); ?>;
        const verifyUrl = <?php echo json_encode(BASE_URL . 'api/whatsapp-verify-otp.php'); ?>;
        const phoneInput = document.getElementById('whatsappPhone');
        const otpInput = document.getElementById('whatsappOtp');
        const alertBox = document.getElementById('whatsappOtpAlert');
        const stepPhone = document.getElementById('whatsappStepPhone');
        const stepOtp = document.getElementById('whatsappStepOtp');
        const phoneDisplay = document.getElementById('whatsappPhoneDisplay');
        let activePhone = '';

        function showAlert(type, message) {
            if (!alertBox) return;
            alertBox.className = 'alert alert-' + type;
            alertBox.textContent = message;
            alertBox.classList.remove('d-none');
        }

        function hideAlert() {
            if (!alertBox) return;
            alertBox.classList.add('d-none');
        }

        function getPhoneValue() {
            return normalizeWhatsappPhone(phoneInput ? phoneInput.value : '');
        }

        function normalizeWhatsappPhone(raw) {
            let digits = (raw || '').replace(/\D/g, '');
            if (digits.length === 10) {
                return '+91' + digits;
            }
            if (digits.length > 10) {
                return '+' + digits;
            }
            return '';
        }

        function sendOtp() {
            hideAlert();
            const phone = getPhoneValue();
            if (!phone || phone.replace(/\D/g, '').length < 10) {
                showAlert('danger', 'Please enter a valid mobile number with country code');
                return;
            }

            const btn = document.getElementById('sendWhatsappOtpBtn');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Sending...';
            }

            fetch(sendUrl, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ phone: phone })
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (!data.success) {
                    throw new Error(data.message || 'Unable to send OTP');
                }
                activePhone = data.phone || ('+91' + phone);
                if (phoneDisplay) phoneDisplay.textContent = activePhone;
                stepPhone.classList.add('d-none');
                stepOtp.classList.remove('d-none');
                showAlert('success', data.message || 'OTP sent to your mobile');
                if (otpInput) otpInput.focus();
            })
            .catch(function(err) {
                showAlert('danger', err.message || 'Unable to send OTP');
            })
            .finally(function() {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-mobile-alt me-2"></i>Send OTP';
                }
            });
        }

        function verifyOtp() {
            hideAlert();
            const otp = (otpInput && otpInput.value ? otpInput.value : '').replace(/\D/g, '');
            if (!activePhone || otp.length !== 5) {
                showAlert('danger', 'Please enter the 5-digit verification code');
                return;
            }

            const btn = document.getElementById('verifyWhatsappOtpBtn');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Verifying...';
            }

            fetch(verifyUrl, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ phone: activePhone, otp: otp, redirect: redirectUrl })
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (!data.success) {
                    throw new Error(data.message || 'OTP verification failed');
                }
                window.location.href = data.redirect_url || redirectUrl;
            })
            .catch(function(err) {
                showAlert('danger', err.message || 'OTP verification failed');
            })
            .finally(function() {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-check-circle me-2"></i>Verify & Login';
                }
            });
        }

        const sendBtn = document.getElementById('sendWhatsappOtpBtn');
        const verifyBtn = document.getElementById('verifyWhatsappOtpBtn');
        const resendBtn = document.getElementById('resendWhatsappOtpBtn');
        const changeBtn = document.getElementById('changeWhatsappPhone');

        if (sendBtn) sendBtn.addEventListener('click', sendOtp);
        if (verifyBtn) verifyBtn.addEventListener('click', verifyOtp);
        if (resendBtn) resendBtn.addEventListener('click', sendOtp);
        if (changeBtn) changeBtn.addEventListener('click', function() {
            stepOtp.classList.add('d-none');
            stepPhone.classList.remove('d-none');
            hideAlert();
            if (otpInput) otpInput.value = '';
        });
        if (otpInput) {
            otpInput.addEventListener('keyup', function(e) {
                if (e.key === 'Enter') verifyOtp();
            });
        }
    })();
</script>
<?php
$extra_js = ob_get_clean();
include 'includes/footer.php';

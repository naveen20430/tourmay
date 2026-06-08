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
$whatsappEnabled = twilioIsConfigured();
$whatsappSandboxNotice = getWhatsAppSandboxInstructions();
$loginRedirect = $_GET['redirect'] ?? navUrl('home');

// Handle login form submission
if ($_POST) {
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
$extra_css = '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Login form specific styles (scoped to login section only) */
        .login-section { 
            padding: 80px 0; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: calc(100vh - 200px);
        }
        .login-card { 
            background: white; 
            border-radius: 20px; 
            box-shadow: 0 25px 50px rgba(0,0,0,0.15); 
            overflow: hidden;
        }
        .login-header { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            color: white; 
        }
        .login-section .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.25rem rgba(102, 126, 234, 0.25);
        }
        .login-section .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }
        .login-section .btn-primary:hover {
            background: linear-gradient(135deg, #5a67d8 0%, #6b46c1 100%);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }
        .social-login {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            margin-top: 20px;
        }
        .login-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 20px;
        }
        .login-tab-btn {
            flex: 1;
            border: 1px solid #e2e8f0;
            background: #fff;
            color: #475569;
            border-radius: 12px;
            padding: 10px 12px;
            font-weight: 600;
        }
        .login-tab-btn.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            border-color: transparent;
        }
        .login-panel { display: none; }
        .login-panel.active { display: block; }
        .otp-input {
            letter-spacing: 0.35em;
            text-align: center;
            font-weight: 700;
        }
        .whatsapp-note {
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            color: #065f46;
            border-radius: 10px;
            padding: 10px 12px;
            font-size: 0.9rem;
            margin-bottom: 14px;
        }
    </style>';

// Include header
include 'includes/header.php';
?>

<!-- Login Section -->
<section class="login-section">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-5 col-md-7">
                <div class="login-card">
                    <div class="login-header p-4 text-center">
                        <h2 class="mb-1">
                            <i class="fas fa-sign-in-alt me-2"></i>Welcome Back
                        </h2>
                        <p class="mb-0 opacity-75">Sign in to your travel account</p>
                    </div>
                    
                    <div class="p-4">
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
                        <div class="login-tabs">
                            <button type="button" class="login-tab-btn active" data-login-tab="email">
                                <i class="fas fa-envelope me-1"></i> Email
                            </button>
                            <button type="button" class="login-tab-btn" data-login-tab="whatsapp">
                                <i class="fab fa-whatsapp me-1"></i> WhatsApp OTP
                            </button>
                        </div>

                        <!-- Email Login -->
                        <div class="login-panel active" id="loginPanelEmail">
                        <form method="POST" novalidate>
                            <div class="mb-3">
                                <label class="form-label">
                                    <i class="fas fa-envelope me-2 text-muted"></i>Email Address
                                </label>
                                <input type="email" name="email" class="form-control form-control-lg" 
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                                       required placeholder="Enter your email">
                                <div class="invalid-feedback">Please enter a valid email address</div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">
                                    <i class="fas fa-lock me-2 text-muted"></i>Password
                                </label>
                                <div class="input-group">
                                    <input type="password" name="password" class="form-control form-control-lg" 
                                           required placeholder="Enter your password" id="password">
                                    <button type="button" class="btn btn-outline-secondary" id="togglePassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="invalid-feedback">Password is required</div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="remember" id="remember">
                                        <label class="form-check-label" for="remember">
                                            Remember me
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6 text-end">
                                    <a href="forgot-password.php" class="text-decoration-none">
                                        <small>Forgot Password?</small>
                                    </a>
                                </div>
                            </div>
                            
                            <div class="d-grid mb-3">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-sign-in-alt me-2"></i>Sign In
                                </button>
                            </div>
                            </div>
                        </form>
                        </div>

                        <!-- WhatsApp OTP Login -->
                        <div class="login-panel" id="loginPanelWhatsapp">
                            <?php if ($whatsappEnabled): ?>
                                <div class="whatsapp-note">
                                    <i class="fab fa-whatsapp me-1"></i>
                                    We will send a 5-digit verification code to your WhatsApp number.
                                </div>
                                <?php if ($whatsappSandboxNotice): ?>
                                    <div class="alert alert-info" style="font-size:.9rem;"><?php echo $whatsappSandboxNotice; ?></div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    WhatsApp OTP login is not configured yet. Please use email login or contact support.
                                </div>
                            <?php endif; ?>

                            <div id="whatsappOtpAlert" class="alert d-none" role="alert"></div>

                            <div id="whatsappStepPhone">
                                <div class="mb-3">
                                    <label class="form-label">
                                        <i class="fab fa-whatsapp me-2 text-success"></i>Mobile Number
                                    </label>
                                    <input type="tel" id="whatsappPhone" class="form-control form-control-lg"
                                           maxlength="16" placeholder="e.g. +91 9876543210 or 9876543210"
                                           <?php echo $whatsappEnabled ? '' : 'disabled'; ?>>
                                    <small class="text-muted">Include country code for numbers outside India.</small>
                                </div>
                                <div class="d-grid mb-3">
                                    <button type="button" class="btn btn-success btn-lg" id="sendWhatsappOtpBtn" <?php echo $whatsappEnabled ? '' : 'disabled'; ?>>
                                        <i class="fab fa-whatsapp me-2"></i>Send OTP on WhatsApp
                                    </button>
                                </div>
                            </div>

                            <div id="whatsappStepOtp" class="d-none">
                                <p class="text-muted mb-3">
                                    OTP sent to <strong id="whatsappPhoneDisplay"></strong>
                                    <button type="button" class="btn btn-link btn-sm p-0 align-baseline" id="changeWhatsappPhone">Change</button>
                                </p>
                                <div class="mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-key me-2 text-muted"></i>Enter OTP
                                    </label>
                                    <input type="text" id="whatsappOtp" class="form-control form-control-lg otp-input"
                                           maxlength="5" pattern="[0-9]{5}" placeholder="5-digit code" inputmode="numeric">
                                </div>
                                <div class="d-grid gap-2 mb-3">
                                    <button type="button" class="btn btn-primary btn-lg" id="verifyWhatsappOtpBtn">
                                        <i class="fas fa-check-circle me-2"></i>Verify &amp; Login
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" id="resendWhatsappOtpBtn">
                                        Resend OTP
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Register Link -->
                        <hr class="my-4">
                        <div class="text-center">
                            <p class="mb-0">
                                Don't have an account? 
                                <a href="register.php" class="text-primary text-decoration-none fw-bold">
                                    Create Account
                                </a>
                            </p>
                        </div>
                        
                        <!-- Back to Home -->
                        <div class="text-center mt-3">
                            <a href="index.php" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-home me-2"></i>Back to Homepage
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Form validation
    (function() {
        'use strict';
        window.addEventListener('load', function() {
            var forms = document.getElementsByTagName('form');
            var validation = Array.prototype.filter.call(forms, function(form) {
                form.addEventListener('submit', function(event) {
                    if (form.checkValidity() === false) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, false);
            });
        }, false);
    })();
    
    // Password visibility toggle
    document.getElementById('togglePassword').addEventListener('click', function() {
        const password = document.getElementById('password');
        const icon = this.querySelector('i');
        
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
    
    // Auto-hide success messages
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert-success');
        alerts.forEach(function(alert) {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);

    // Login tabs
    document.querySelectorAll('[data-login-tab]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const tab = btn.getAttribute('data-login-tab');
            document.querySelectorAll('[data-login-tab]').forEach(function(item) {
                item.classList.toggle('active', item === btn);
            });
            document.getElementById('loginPanelEmail').classList.toggle('active', tab === 'email');
            document.getElementById('loginPanelWhatsapp').classList.toggle('active', tab === 'whatsapp');
        });
    });

    // WhatsApp OTP login
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
                showAlert('danger', 'Please enter a valid WhatsApp number with country code');
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
                showAlert('success', data.message || 'OTP sent on WhatsApp');
                if (otpInput) otpInput.focus();
            })
            .catch(function(err) {
                showAlert('danger', err.message || 'Unable to send OTP');
            })
            .finally(function() {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fab fa-whatsapp me-2"></i>Send OTP on WhatsApp';
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

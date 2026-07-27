<?php
require_once 'config/config.php';
require_once 'includes/email_otp_helpers.php';
// require_once 'includes/whatsapp_otp_helpers.php'; // Mobile OTP (commented — email OTP only)

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    $redirect = $_GET['redirect'] ?? navUrl('home');
    header('Location: ' . $redirect);
    exit;
}

$errors = [];
$success_message = '';
$emailOtpEnabled = emailOtpIsConfigured();
$loginRedirect = $_GET['redirect'] ?? navUrl('home');
$activeLoginTab = 'email_otp';

/*
// --- Password email login (commented — using email OTP) ---
if ($_POST) {
    $activeLoginTab = 'email';
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    if (empty($email)) { $errors[] = 'Email is required'; }
    if (empty($password)) { $errors[] = 'Password is required'; }
    if (empty($errors)) {
        try {
            $user = $db->fetch("SELECT * FROM users WHERE email = ? AND status = 'active'", [$email]);
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_first_name'] = $user['first_name'];
                $db->execute("UPDATE users SET updated_at = NOW() WHERE id = ?", [$user['id']]);
                if ($remember) {
                    $token = bin2hex(random_bytes(16));
                    setcookie('remember_token', $token, time() + (86400 * 30), '/', '', false, true);
                }
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
*/

if (isset($_GET['registered']) && $_GET['registered'] == '1') {
    $success_message = 'Registration successful! Sign in with the OTP sent to your email.';
}

$page_title = 'Login - ' . (getSetting('site_name') ?: 'Travel Hub');
$current_page = 'login';
$extra_css = cssWithCache('assets/css/auth-pages.css');

include 'includes/header.php';
?>

<section class="auth-section login-section">
    <div class="container">
        <div class="auth-page-wrap">
                <div class="auth-card login-card">
                    <div class="auth-card__head login-header">
                        <h2>
                            <i class="fas fa-sign-in-alt me-2"></i>Welcome Back
                        </h2>
                        <p>Sign in with a one-time code sent to your email</p>
                    </div>

                    <div class="auth-card__body">
                        <?php if ($success_message): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

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

                        <?php /* Mobile OTP / Email password tabs — commented out
                        <div class="auth-tabs login-tabs">
                            <button type="button" class="auth-tab-btn login-tab-btn" data-login-tab="whatsapp">
                                <i class="fas fa-mobile-alt"></i> Mobile OTP
                            </button>
                            <button type="button" class="auth-tab-btn login-tab-btn" data-login-tab="email">
                                <i class="fas fa-envelope"></i> Email
                            </button>
                        </div>
                        */ ?>

                        <!-- Email OTP Login -->
                        <div class="auth-panel login-panel active" id="loginPanelEmailOtp">
                            <?php if ($emailOtpEnabled): ?>
                                <div class="auth-note whatsapp-note">
                                    <i class="fas fa-envelope me-1"></i>
                                    We will send a 5-digit verification code to your email.
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    Email OTP login is not configured yet. Please set SMTP password in Admin → Settings.
                                </div>
                            <?php endif; ?>

                            <div id="emailOtpAlert" class="alert d-none" role="alert"></div>

                            <div id="emailStepEmail">
                                <div class="auth-form-field">
                                    <label class="form-label" for="loginOtpEmail">Email Address</label>
                                    <input type="email" id="loginOtpEmail" class="form-control"
                                           placeholder="Enter your registered email"
                                           <?php echo $emailOtpEnabled ? '' : 'disabled'; ?>>
                                </div>
                                <button type="button" class="btn btn-primary btn-lg w-100 auth-submit-btn" id="sendEmailOtpBtn" <?php echo $emailOtpEnabled ? '' : 'disabled'; ?>>
                                    <i class="fas fa-envelope me-2"></i>Send OTP
                                </button>
                            </div>

                            <div id="emailStepOtp" class="d-none">
                                <p class="auth-otp-sent">
                                    OTP sent to <strong id="emailOtpDisplay"></strong>
                                    <button type="button" class="btn btn-link btn-sm p-0 align-baseline" id="changeLoginEmail">Change</button>
                                </p>
                                <div class="auth-form-field">
                                    <label class="form-label" for="loginEmailOtp">Enter OTP</label>
                                    <input type="text" id="loginEmailOtp" class="form-control auth-otp-input otp-input"
                                           maxlength="5" pattern="[0-9]{5}" placeholder="5-digit code" inputmode="numeric">
                                </div>
                                <div class="auth-otp-actions">
                                    <button type="button" class="btn btn-primary btn-lg w-100 auth-submit-btn" id="verifyEmailOtpBtn">
                                        <i class="fas fa-check-circle me-2"></i>Verify &amp; Login
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary w-100" id="resendEmailOtpBtn">
                                        Resend OTP
                                    </button>
                                </div>
                            </div>
                        </div>

                        <?php /*
                        <!-- Email Password Login (commented) -->
                        <div class="auth-panel login-panel" id="loginPanelEmail">...</div>

                        <!-- Mobile OTP Login (commented) -->
                        <div class="auth-panel login-panel" id="loginPanelWhatsapp">...</div>
                        */ ?>

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

    setTimeout(function() {
        document.querySelectorAll('.alert-success').forEach(function(alert) {
            if (typeof bootstrap !== 'undefined' && bootstrap.Alert) {
                bootstrap.Alert.getOrCreateInstance(alert).close();
            }
        });
    }, 5000);

    const redirectUrl = <?php echo json_encode($loginRedirect); ?>;
    const sendUrl = <?php echo json_encode(BASE_URL . 'api/email-send-otp.php'); ?>;
    const verifyUrl = <?php echo json_encode(BASE_URL . 'api/email-verify-otp.php'); ?>;
    const emailInput = document.getElementById('loginOtpEmail');
    const otpInput = document.getElementById('loginEmailOtp');
    const alertBox = document.getElementById('emailOtpAlert');
    const stepEmail = document.getElementById('emailStepEmail');
    const stepOtp = document.getElementById('emailStepOtp');
    const emailDisplay = document.getElementById('emailOtpDisplay');
    let activeEmail = '';

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

    function sendOtp() {
        hideAlert();
        const email = (emailInput && emailInput.value ? emailInput.value : '').trim();
        if (!email || email.indexOf('@') < 1) {
            showAlert('danger', 'Please enter a valid email address');
            return;
        }

        const btn = document.getElementById('sendEmailOtpBtn');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Sending...';
        }

        fetch(sendUrl, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ email: email, purpose: 'login' })
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (!data.success) throw new Error(data.message || 'Unable to send OTP');
            activeEmail = data.email || email;
            if (emailDisplay) emailDisplay.textContent = activeEmail;
            stepEmail.classList.add('d-none');
            stepOtp.classList.remove('d-none');
            showAlert('success', data.message || 'OTP sent to your email');
            if (otpInput) otpInput.focus();
        })
        .catch(function(err) {
            showAlert('danger', err.message || 'Unable to send OTP');
        })
        .finally(function() {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-envelope me-2"></i>Send OTP';
            }
        });
    }

    function verifyOtp() {
        hideAlert();
        const otp = (otpInput && otpInput.value ? otpInput.value : '').replace(/\D/g, '');
        if (!activeEmail || otp.length !== 5) {
            showAlert('danger', 'Please enter the 5-digit verification code');
            return;
        }

        const btn = document.getElementById('verifyEmailOtpBtn');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Verifying...';
        }

        fetch(verifyUrl, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ email: activeEmail, otp: otp, redirect: redirectUrl, purpose: 'login' })
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (!data.success) throw new Error(data.message || 'OTP verification failed');
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

    const sendBtn = document.getElementById('sendEmailOtpBtn');
    const verifyBtn = document.getElementById('verifyEmailOtpBtn');
    const resendBtn = document.getElementById('resendEmailOtpBtn');
    const changeBtn = document.getElementById('changeLoginEmail');

    if (sendBtn) sendBtn.addEventListener('click', sendOtp);
    if (verifyBtn) verifyBtn.addEventListener('click', verifyOtp);
    if (resendBtn) resendBtn.addEventListener('click', sendOtp);
    if (changeBtn) changeBtn.addEventListener('click', function() {
        stepOtp.classList.add('d-none');
        stepEmail.classList.remove('d-none');
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

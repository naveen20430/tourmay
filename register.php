<?php
require_once 'config/config.php';
require_once 'includes/email_otp_helpers.php';
// require_once 'includes/whatsapp_otp_helpers.php'; // Mobile OTP (commented — email OTP only)

$errors = [];
$success = false;
$emailOtpEnabled = emailOtpIsConfigured();

if ($_POST) {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    $country = trim($_POST['country'] ?? 'IN');

    if (empty($first_name)) $errors[] = 'First name is required';
    if (empty($last_name)) $errors[] = 'Last name is required';
    if (empty($email)) $errors[] = 'Email is required';
    if (empty($password)) $errors[] = 'Password is required';
    if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters';
    if ($password !== $confirm_password) $errors[] = 'Passwords do not match';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    }

    if (!isVerifiedEmailSession('register', $email)) {
        $errors[] = 'Please verify your email with OTP before creating an account';
    }

    // Optional phone (no OTP)
    $normalizedPhone = '';
    if ($phone !== '') {
        $normalizedPhone = normalizePhoneE164($phone);
        if ($normalizedPhone === '' || strlen(preg_replace('/\D+/', '', $normalizedPhone)) < 10) {
            $errors[] = 'Please enter a valid mobile number or leave it blank';
        } elseif (phoneBelongsToAnotherUser($normalizedPhone)) {
            $errors[] = 'This mobile number is already registered. Please login instead.';
        }
    }

    if (empty($errors)) {
        $existing_user = $db->fetch('SELECT id FROM users WHERE email = ?', [normalizeEmailAddress($email)]);
        if ($existing_user) {
            $errors[] = 'Email already registered';
        }
    }

    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $verification_token = bin2hex(random_bytes(16));
        $normalizedEmail = normalizeEmailAddress($email);

        try {
            $user_id = $db->execute(
                "INSERT INTO users (first_name, last_name, email, password, phone, country, verification_token, email_verified, status, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 1, 'active', NOW())",
                [$first_name, $last_name, $normalizedEmail, $hashed_password, $normalizedPhone !== '' ? $normalizedPhone : null, $country, $verification_token]
            );

            if ($user_id) {
                consumeVerifiedEmailSession('register', $normalizedEmail);
                header('Location: login.php?registered=1');
                exit;
            }

            $errors[] = 'Registration failed. Please try again.';
        } catch (Exception $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

$page_title = 'Register - ' . getSetting('site_name');
$current_page = 'register';
$extra_css = cssWithCache('assets/css/auth-pages.css');

include 'includes/header.php';
?>

<section class="auth-section register-section">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-6 col-md-8 col-sm-10">
                <div class="auth-card register-card">
                    <div class="auth-card__head register-header">
                        <h2><i class="fas fa-user-plus me-2"></i>Create Account</h2>
                        <p>Verify your email and join us for amazing travel experiences</p>
                    </div>

                    <div class="auth-card__body">
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <form method="POST" id="registerForm">
                            <div class="auth-verify-box" id="emailVerifyBox">
                                <label class="form-label fw-semibold">
                                    <i class="fas fa-envelope text-primary me-2"></i>Email Address *
                                </label>
                                <?php if (!$emailOtpEnabled): ?>
                                    <div class="alert alert-warning mb-0">
                                        Email OTP verification is not configured yet. Please set SMTP password in Admin → Settings.
                                    </div>
                                <?php else: ?>
                                    <div id="registerOtpAlert" class="alert d-none" role="alert"></div>
                                    <div id="registerEmailStep">
                                        <input type="email" id="registerOtpEmail" class="form-control mb-2"
                                               placeholder="Enter your email" required
                                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                                        <button type="button" class="btn btn-success w-100" id="registerSendOtpBtn">
                                            <i class="fas fa-envelope me-2"></i>Send OTP
                                        </button>
                                    </div>
                                    <div id="registerOtpStep" class="d-none">
                                        <p class="text-muted mb-2">
                                            OTP sent to <strong id="registerEmailDisplay"></strong>
                                            <button type="button" class="btn btn-link btn-sm p-0 align-baseline" id="registerChangeEmail">Change</button>
                                        </p>
                                        <input type="text" id="registerEmailOtp" class="form-control auth-otp-input otp-input mb-3"
                                               maxlength="5" pattern="[0-9]{5}" placeholder="5-digit code" inputmode="numeric">
                                        <div class="d-grid gap-2">
                                            <button type="button" class="btn btn-primary" id="registerVerifyOtpBtn">
                                                <i class="fas fa-check-circle me-2"></i>Verify Email
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary btn-sm" id="registerResendOtpBtn">
                                                Resend OTP
                                            </button>
                                        </div>
                                    </div>
                                    <div id="registerVerifiedBadge" class="d-none mt-2">
                                        <span class="badge bg-success"><i class="fas fa-check me-1"></i> Email verified</span>
                                    </div>
                                <?php endif; ?>
                                <input type="hidden" name="email" id="registerEmailHidden" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                            </div>

                            <?php /* Mobile OTP verify box — commented out
                            <div class="auth-verify-box" id="whatsappVerifyBox">...</div>
                            */ ?>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">First Name *</label>
                                    <input type="text" name="first_name" class="form-control" required
                                           value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Last Name *</label>
                                    <input type="text" name="last_name" class="form-control" required
                                           value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Mobile Number (optional)</label>
                                <input type="tel" name="phone" class="form-control" maxlength="16"
                                       placeholder="e.g. +91 9876543210"
                                       value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Password *</label>
                                    <input type="password" name="password" class="form-control" required minlength="6">
                                    <small class="text-muted">Minimum 6 characters</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Confirm Password *</label>
                                    <input type="password" name="confirm_password" class="form-control" required minlength="6">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Country</label>
                                <select name="country" class="form-select">
                                    <option value="IN" <?php echo ($_POST['country'] ?? 'IN') == 'IN' ? 'selected' : ''; ?>>India</option>
                                    <option value="US" <?php echo ($_POST['country'] ?? '') == 'US' ? 'selected' : ''; ?>>United States</option>
                                    <option value="UK" <?php echo ($_POST['country'] ?? '') == 'UK' ? 'selected' : ''; ?>>United Kingdom</option>
                                    <option value="CA" <?php echo ($_POST['country'] ?? '') == 'CA' ? 'selected' : ''; ?>>Canada</option>
                                    <option value="AU" <?php echo ($_POST['country'] ?? '') == 'AU' ? 'selected' : ''; ?>>Australia</option>
                                    <option value="SG" <?php echo ($_POST['country'] ?? '') == 'SG' ? 'selected' : ''; ?>>Singapore</option>
                                    <option value="AE" <?php echo ($_POST['country'] ?? '') == 'AE' ? 'selected' : ''; ?>>UAE</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>

                            <div class="mb-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="terms" required>
                                    <label class="form-check-label" for="terms">
                                        I agree to the <a href="<?php echo navUrl('terms-conditions'); ?>" target="_blank" rel="noopener">Terms of Service</a> and <a href="<?php echo navUrl('privacy-policy'); ?>" target="_blank" rel="noopener">Privacy Policy</a>
                                    </label>
                                </div>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg" id="registerSubmitBtn" <?php echo $emailOtpEnabled ? 'disabled' : ''; ?>>
                                    <i class="fas fa-user-plus me-2"></i>Create Account
                                </button>
                            </div>
                        </form>

                        <hr class="auth-divider">
                        <p class="auth-footer-text">
                            Already have an account? <a href="<?php echo navUrl('login'); ?>">Login here</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const password = document.querySelector('input[name="password"]');
    const confirmPassword = document.querySelector('input[name="confirm_password"]');
    if (password && confirmPassword) {
        function validatePassword() {
            confirmPassword.setCustomValidity(password.value !== confirmPassword.value ? "Passwords don't match" : '');
        }
        password.addEventListener('input', validatePassword);
        confirmPassword.addEventListener('input', validatePassword);
    }

    <?php if ($emailOtpEnabled): ?>
    (function() {
        const sendUrl = <?php echo json_encode(BASE_URL . 'api/email-send-otp.php'); ?>;
        const verifyUrl = <?php echo json_encode(BASE_URL . 'api/email-verify-otp.php'); ?>;
        const emailInput = document.getElementById('registerOtpEmail');
        const otpInput = document.getElementById('registerEmailOtp');
        const emailHidden = document.getElementById('registerEmailHidden');
        const submitBtn = document.getElementById('registerSubmitBtn');
        const alertBox = document.getElementById('registerOtpAlert');
        const emailStep = document.getElementById('registerEmailStep');
        const otpStep = document.getElementById('registerOtpStep');
        const verifiedBadge = document.getElementById('registerVerifiedBadge');
        const verifyBox = document.getElementById('emailVerifyBox');
        let activeEmail = emailHidden.value || '';

        function showAlert(type, message) {
            alertBox.className = 'alert alert-' + type;
            alertBox.textContent = message;
            alertBox.classList.remove('d-none');
        }

        function hideAlert() {
            alertBox.classList.add('d-none');
        }

        function markVerified(email) {
            activeEmail = email;
            emailHidden.value = email;
            if (emailInput) emailInput.value = email;
            emailStep.classList.add('d-none');
            otpStep.classList.add('d-none');
            verifiedBadge.classList.remove('d-none');
            verifyBox.classList.add('is-verified');
            submitBtn.disabled = false;
            showAlert('success', 'Email verified. You can complete registration.');
        }

        if (activeEmail && <?php echo isVerifiedEmailSession('register', $_POST['email'] ?? '') ? 'true' : 'false'; ?>) {
            markVerified(activeEmail);
        }

        function sendOtp() {
            hideAlert();
            const email = (emailInput.value || '').trim();
            if (!email || email.indexOf('@') < 1) {
                showAlert('danger', 'Please enter a valid email address');
                return;
            }

            const btn = document.getElementById('registerSendOtpBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Sending...';

            fetch(sendUrl, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ email: email, purpose: 'register' })
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (!data.success) throw new Error(data.message || 'Unable to send OTP');
                activeEmail = data.email;
                document.getElementById('registerEmailDisplay').textContent = data.email;
                emailStep.classList.add('d-none');
                otpStep.classList.remove('d-none');
                verifiedBadge.classList.add('d-none');
                verifyBox.classList.remove('is-verified');
                submitBtn.disabled = true;
                showAlert('success', data.message || 'OTP sent to your email');
                otpInput.focus();
            })
            .catch(function(err) {
                showAlert('danger', err.message || 'Unable to send OTP');
            })
            .finally(function() {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-envelope me-2"></i>Send OTP';
            });
        }

        function verifyOtp() {
            hideAlert();
            const otp = (otpInput.value || '').replace(/\D/g, '');
            if (!activeEmail || otp.length !== 5) {
                showAlert('danger', 'Please enter the 5-digit verification code');
                return;
            }

            const btn = document.getElementById('registerVerifyOtpBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Verifying...';

            fetch(verifyUrl, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ email: activeEmail, otp: otp, purpose: 'register' })
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (!data.success) throw new Error(data.message || 'OTP verification failed');
                markVerified(data.email);
            })
            .catch(function(err) {
                showAlert('danger', err.message || 'OTP verification failed');
            })
            .finally(function() {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check-circle me-2"></i>Verify Email';
            });
        }

        document.getElementById('registerSendOtpBtn').addEventListener('click', sendOtp);
        document.getElementById('registerVerifyOtpBtn').addEventListener('click', verifyOtp);
        document.getElementById('registerResendOtpBtn').addEventListener('click', sendOtp);
        document.getElementById('registerChangeEmail').addEventListener('click', function() {
            otpStep.classList.add('d-none');
            emailStep.classList.remove('d-none');
            verifiedBadge.classList.add('d-none');
            verifyBox.classList.remove('is-verified');
            submitBtn.disabled = true;
            emailHidden.value = '';
            activeEmail = '';
            otpInput.value = '';
            hideAlert();
        });

        document.getElementById('registerForm').addEventListener('submit', function(e) {
            if (!emailHidden.value) {
                e.preventDefault();
                showAlert('danger', 'Please verify your email before creating an account');
            }
        });
    })();
    <?php endif; ?>
});
</script>

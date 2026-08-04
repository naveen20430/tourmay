# Checkout debug is ON

Flag file: config/debug.enabled
Logs (not web-accessible):
- logs/checkout.log   — checkout / Razorpay events (JSON lines)
- logs/php-error.log  — PHP errors
- logs/otp.log        — OTP send history

Turn debug OFF: delete config/debug.enabled
(Payment failures still log even when debug is off.)

<?php
/**
 * Checkout / payment debug logging.
 * Enable by creating config/debug.enabled (empty file) or setting TWJ_DEBUG=1 in the environment.
 */

function twjDebugEnabled(): bool
{
    static $enabled = null;
    if ($enabled !== null) {
        return $enabled;
    }

    $env = getenv('TWJ_DEBUG');
    if ($env !== false && $env !== '' && $env !== '0' && strtolower((string) $env) !== 'false') {
        $enabled = true;
        return $enabled;
    }

    $flagFile = dirname(__DIR__) . '/config/debug.enabled';
    $enabled = is_file($flagFile);
    return $enabled;
}

function twjCheckoutLog(string $event, array $context = []): void
{
    if (!twjDebugEnabled() && empty($context['force'])) {
        // Always log hard payment failures even when debug is off.
        $forceEvents = [
            'checkout_exception',
            'razorpay_create_order_fail',
            'razorpay_verify_fail',
            'razorpay_request_fail',
        ];
        if (!in_array($event, $forceEvents, true)) {
            return;
        }
    }

    $dir = dirname(__DIR__) . '/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    $row = [
        'ts' => date('Y-m-d H:i:s'),
        'event' => $event,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        'uri' => $_SERVER['REQUEST_URI'] ?? '',
        'method' => $_SERVER['REQUEST_METHOD'] ?? '',
        'user_id' => $_SESSION['user_id'] ?? null,
        'context' => $context,
    ];

    unset($row['context']['force']);

    // Never persist secrets.
    array_walk_recursive($row, function (&$value, $key) {
        if (is_string($key) && preg_match('/secret|password|signature|otp|token/i', $key)) {
            $value = '[redacted]';
        }
    });

    @file_put_contents(
        $dir . '/checkout.log',
        json_encode($row, JSON_UNESCAPED_SLASHES) . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );

    if (twjDebugEnabled()) {
        @error_log('[TWJ_CHECKOUT] ' . $event . ' ' . json_encode($context, JSON_UNESCAPED_SLASHES));
    }
}

<?php
/**
 * Generic error page (403 / 400 / 502 / 503 / custom).
 * Uses REDIRECT_STATUS from Apache ErrorDocument when available.
 */
require_once __DIR__ . '/includes/error_page.php';

$code = (int) ($_GET['code'] ?? ($_SERVER['REDIRECT_STATUS'] ?? 500));
if ($code < 400 || $code > 599) {
    $code = 500;
}

$catalog = [
    400 => [
        'title' => 'Bad request',
        'message' => 'The request could not be understood. Please go back and try again.',
        'hint' => '',
    ],
    401 => [
        'title' => 'Login required',
        'message' => 'You need to sign in to access this page.',
        'hint' => '',
    ],
    403 => [
        'title' => 'Access denied',
        'message' => 'You do not have permission to view this page.',
        'hint' => 'If you believe this is a mistake, contact support.',
    ],
    404 => [
        'title' => 'Page not found',
        'message' => 'The page you are looking for may have been moved, deleted, or never existed.',
        'hint' => 'Check the URL, or go back to the homepage to continue browsing tours.',
    ],
    408 => [
        'title' => 'Request timeout',
        'message' => 'The server timed out waiting for the request. Please try again.',
        'hint' => '',
    ],
    429 => [
        'title' => 'Too many requests',
        'message' => 'You have made too many requests in a short time. Please wait and try again.',
        'hint' => '',
    ],
    500 => [
        'title' => 'Something went wrong',
        'message' => 'An unexpected server error occurred. Our team can review this if it keeps happening.',
        'hint' => 'Please try again in a few moments.',
    ],
    502 => [
        'title' => 'Bad gateway',
        'message' => 'The server received an invalid response. Please try again shortly.',
        'hint' => '',
    ],
    503 => [
        'title' => 'Service unavailable',
        'message' => 'The site is temporarily unavailable due to maintenance or high load.',
        'hint' => 'Please check back soon.',
    ],
    504 => [
        'title' => 'Gateway timeout',
        'message' => 'The server took too long to respond. Please try again.',
        'hint' => '',
    ],
];

$meta = $catalog[$code] ?? $catalog[500];

twjRenderErrorPage([
    'code' => $code,
    'title' => $meta['title'],
    'message' => $meta['message'],
    'hint' => $meta['hint'],
]);

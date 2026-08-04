<?php
/**
 * 500 Internal Server Error
 */
require_once __DIR__ . '/includes/error_page.php';

twjRenderErrorPage([
    'code' => 500,
    'title' => 'Something went wrong',
    'message' => 'An unexpected server error occurred while processing your request.',
    'hint' => 'Please try again in a few moments. If the problem continues, contact us.',
]);

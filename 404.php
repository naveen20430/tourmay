<?php
/**
 * 404 Not Found
 */
require_once __DIR__ . '/includes/error_page.php';

twjRenderErrorPage([
    'code' => 404,
    'title' => 'Page not found',
    'message' => 'The page you are looking for may have been moved, deleted, or never existed.',
    'hint' => 'Check the URL, or go back to the homepage to continue browsing tours.',
]);

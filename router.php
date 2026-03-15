<?php
/**
 * Router for PHP Development Server
 * Usage: php -S localhost:8000 router.php
 * 
 * This router:
 * 1. Serves static files (CSS, JS, images, fonts, etc.) directly
 * 2. Routes clean URLs to appropriate PHP files
 * 3. Handles query strings and fragments
 */

$requested_uri = $_SERVER['REQUEST_URI'];
$requested_path = parse_url($requested_uri, PHP_URL_PATH);
$script_dir = __DIR__;

// Step 1: Serve static files directly (don't route them)
$static_extensions = ['css', 'js', 'jpg', 'jpeg', 'png', 'gif', 'svg', 'ico', 'woff', 'woff2', 'ttf', 'eot', 'map'];
$file_extension = pathinfo($requested_path, PATHINFO_EXTENSION);

if (!empty($file_extension) && in_array(strtolower($file_extension), $static_extensions)) {
    $file_path = $script_dir . $requested_path;
    if (file_exists($file_path) && is_file($file_path)) {
        return false; // Let dev server serve the file
    }
}

// Step 2: Check if it's an actual directory or file
if ($requested_path !== '/' && file_exists($script_dir . $requested_path)) {
    if (is_dir($script_dir . $requested_path)) {
        // Try to serve index.php from the directory
        if (file_exists($script_dir . $requested_path . '/index.php')) {
            $_SERVER['SCRIPT_FILENAME'] = $script_dir . $requested_path . '/index.php';
            require $_SERVER['SCRIPT_FILENAME'];
            return true;
        }
    } elseif (is_file($script_dir . $requested_path)) {
        // Serve the file if it exists
        return false;
    }
}

// Step 3: Route clean URLs to PHP files
$path = ltrim($requested_path, '/');
$path = rtrim($path, '/');

// Extract base path (first segment) for routing
$path_parts = explode('/', $path);
$base_route = $path_parts[0] ?? '';

// Route mapping - map clean URLs to PHP files
$routes = [
    '' => 'index.php',
    'home' => 'index.php',
    'tours' => 'tours.php',
    'blog' => 'blog.php',
    'contact' => 'contact.php',
    'destinations' => 'destinations.php',
    'booking' => 'booking.php',
    'payment' => 'payment.php',
    'success' => 'success.php',
    'login' => 'login.php',
    'register' => 'register.php',
    'logout' => 'logout.php',
    'profile' => 'profile.php',
    'user-dashboard' => 'user-dashboard.php',
    'tour-details' => 'tour-details.php',
    'destination-details' => 'destination-details.php',
    'book' => 'book.php',
    'simple-booking' => 'simple-booking.php',
    'admin' => 'admin/index.php',
    'tour' => 'tour-details.php',
    'destination' => 'destination-details.php',
];

// Handle dynamic routes (slugs)
if ($base_route === 'tour' && isset($path_parts[1])) {
    $_GET['slug'] = $path_parts[1];
} elseif ($base_route === 'destination' && isset($path_parts[1])) {
    $_GET['slug'] = $path_parts[1];
}

// Check if we have a route for this base path
if (isset($routes[$base_route])) {
    $file_to_load = $routes[$base_route];
} else {
    // Default to index.php if no route matches
    $file_to_load = 'index.php';
}

// Load the PHP file
$_SERVER['SCRIPT_FILENAME'] = $script_dir . '/' . $file_to_load;
$_SERVER['SCRIPT_NAME'] = '/' . $file_to_load;

if (file_exists($_SERVER['SCRIPT_FILENAME'])) {
    require $_SERVER['SCRIPT_FILENAME'];
    return true;
}

// If file doesn't exist, return 404
http_response_code(404);
echo "404 Not Found";
return true;

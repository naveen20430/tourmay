<?php
/**
 * URL Helper Functions
 * Generate clean URLs consistently throughout the application
 */

/**
 * Generate URL for a tour
 * @param string $slug Tour slug
 * @return string Clean URL
 */
function tourUrl($slug) {
    return BASE_URL . "tour/" . urlencode($slug);
}

/**
 * Generate URL for a destination
 * @param string $slug Destination slug
 * @return string Clean URL
 */
function destinationUrl($slug) {
    return BASE_URL . "destination/" . urlencode($slug);
}

/**
 * Generate URL for a blog post
 * @param string $slug Blog post slug
 * @return string Clean URL
 */
function blogPostUrl($slug) {
    return BASE_URL . "blog/" . urlencode($slug);
}

/**
 * Generate URL for tours listing
 * @param array $filters Optional filters (category, destination, difficulty, search)
 * @return string Clean URL
 */
function toursUrl($filters = []) {
    if (empty($filters)) {
        return BASE_URL . "tours";
    }
    
    // Handle specific filter types with clean URLs
    if (isset($filters['category'])) {
        return BASE_URL . "tours/category/" . urlencode($filters['category']);
    }
    
    if (isset($filters['destination'])) {
        return BASE_URL . "tours/destination/" . urlencode($filters['destination']);
    }
    
    if (isset($filters['difficulty'])) {
        return BASE_URL . "tours/difficulty/" . urlencode($filters['difficulty']);
    }
    
    if (isset($filters['search'])) {
        return BASE_URL . "tours/search/" . urlencode($filters['search']);
    }
    
    // For multiple filters, use query parameters
    $url = BASE_URL . "tours";
    $queryParams = [];
    
    foreach ($filters as $key => $value) {
        if (!empty($value)) {
            $queryParams[] = urlencode($key) . '=' . urlencode($value);
        }
    }
    
    if (!empty($queryParams)) {
        $url .= '?' . implode('&', $queryParams);
    }
    
    return $url;
}

/**
 * Generate URL for destinations listing
 * @param array $filters Optional filters
 * @return string Clean URL
 */
function destinationsUrl($filters = []) {
    $url = BASE_URL . "destinations";
    
    if (!empty($filters)) {
        $queryParams = [];
        foreach ($filters as $key => $value) {
            if (!empty($value)) {
                $queryParams[] = urlencode($key) . '=' . urlencode($value);
            }
        }
        
        if (!empty($queryParams)) {
            $url .= '?' . implode('&', $queryParams);
        }
    }
    
    return $url;
}

/**
 * Generate URL for blog listing
 * @param array $filters Optional filters (category, search)
 * @return string Clean URL
 */
function blogUrl($filters = []) {
    if (empty($filters)) {
        return BASE_URL . "blog";
    }
    
    // Handle specific filter types with clean URLs
    if (isset($filters['category'])) {
        return BASE_URL . "blog/category/" . urlencode($filters['category']);
    }
    
    if (isset($filters['search'])) {
        return BASE_URL . "blog/search/" . urlencode($filters['search']);
    }
    
    // For multiple filters, use query parameters
    $url = BASE_URL . "blog";
    $queryParams = [];
    
    foreach ($filters as $key => $value) {
        if (!empty($value)) {
            $queryParams[] = urlencode($key) . '=' . urlencode($value);
        }
    }
    
    if (!empty($queryParams)) {
        $url .= '?' . implode('&', $queryParams);
    }
    
    return $url;
}

/**
 * Generate booking URL
 * @param int $tourId Tour ID
 * @return string Clean URL
 */
function bookingUrl($tourId = null) {
    if ($tourId) {
        return BASE_URL . "book/" . intval($tourId);
    }
    return BASE_URL . "booking";
}

/**
 * Generate admin URL
 * @param string $page Admin page
 * @param int $id Optional ID parameter
 * @return string Clean URL
 */
function adminUrl($page = '', $id = null) {
    $url = BASE_URL . "admin";
    
    if (!empty($page)) {
        $url .= "/" . urlencode($page);
    }
    
    if ($id !== null) {
        $url .= "/" . intval($id);
    }
    
    return $url;
}

/**
 * Generate navigation URLs
 * @param string $page Page name
 * @return string Clean URL
 */
function navUrl($page) {
    switch (strtolower($page)) {
        case 'home':
            return BASE_URL;
        case 'tours':
            return BASE_URL . "tours";
        case 'destinations':
            return BASE_URL . "destinations";
        case 'blog':
            return BASE_URL . "blog";
        case 'contact':
            return BASE_URL . "contact";
        case 'cart':
            return BASE_URL . "cart";
        case 'about':
        case 'about-us':
            return BASE_URL . "about-us";
        case 'gallery':
            return BASE_URL . "gallery";
        case 'login':
            return BASE_URL . "login";
        case 'register':
            return BASE_URL . "register";
        case 'profile':
            return BASE_URL . "profile";
        case 'privacy':
        case 'privacy-policy':
            return BASE_URL . "privacy-policy";
        case 'terms':
        case 'terms-conditions':
            return BASE_URL . "terms-conditions";
        default:
            return BASE_URL . urlencode($page);
    }
}

/**
 * Get base URL for the site
 * @return string Base URL
 */
function getBaseUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    $scriptPath = dirname($_SERVER['SCRIPT_NAME']);
    
    // Remove /tour from the path if it exists (for development environment)
    $basePath = str_replace('/tour', '', $scriptPath);
    $basePath = rtrim($basePath, '/');
    
    return $protocol . $host . $basePath;
}

/**
 * Generate full URL
 * @param string $path Relative path
 * @return string Full URL
 */
function fullUrl($path) {
    return getBaseUrl() . $path;
}

/**
 * Generate canonical URL for SEO
 * @param string $path Current page path
 * @return string Canonical URL
 */
function canonicalUrl($path = '') {
    if (empty($path)) {
        $path = $_SERVER['REQUEST_URI'];
        // Remove query parameters for canonical URL
        if (strpos($path, '?') !== false) {
            $path = substr($path, 0, strpos($path, '?'));
        }
    }
    
    return fullUrl($path);
}

/**
 * Check if current page matches given page
 * @param string $page Page identifier
 * @return bool
 */
function isCurrentPage($page) {
    $current_uri = $_SERVER['REQUEST_URI'];
    
    switch (strtolower($page)) {
        case 'home':
            return $current_uri === '/' || $current_uri === '/tour/' || strpos($current_uri, '/index.php') !== false;
        case 'tours':
            return strpos($current_uri, '/tours') === 0;
        case 'destinations':
            return strpos($current_uri, '/destinations') === 0 || strpos($current_uri, '/destination/') === 0;
        case 'blog':
            return strpos($current_uri, '/blog') === 0;
        case 'contact':
            return strpos($current_uri, '/contact') === 0;
        case 'about':
        case 'about-us':
            return strpos($current_uri, '/about') === 0;
        case 'gallery':
            return strpos($current_uri, '/gallery') === 0;
        case 'admin':
            return strpos($current_uri, '/admin') === 0;
        default:
            return strpos($current_uri, '/' . $page) === 0;
    }
}

/**
 * Generate breadcrumb data
 * @param array $items Breadcrumb items [['title' => 'Title', 'url' => 'URL'], ...]
 * @return array Breadcrumb data
 */
function generateBreadcrumb($items = []) {
    $breadcrumb = [
        ['title' => 'Home', 'url' => '/']
    ];
    
    return array_merge($breadcrumb, $items);
}

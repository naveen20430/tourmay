<?php
/**
 * AJAX Search API
 * Provides real-time search functionality for tours, destinations, and blog posts
 */

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    require_once '../config/config.php';
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Config error',
        'message' => $e->getMessage()
    ]);
    exit;
}

// Set JSON content type
header('Content-Type: application/json');

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get search parameters
$query = trim($_GET['q'] ?? '');
$type = $_GET['type'] ?? 'all'; // all, tours, destinations, blog
$limit = min(intval($_GET['limit'] ?? 10), 20); // Max 20 results

// Validate query
if (empty($query) || strlen($query) < 2) {
    echo json_encode([
        'results' => [],
        'message' => 'Please enter at least 2 characters to search'
    ]);
    exit;
}

try {
    $results = [];
    
    // Search Tours
    if ($type === 'all' || $type === 'tours') {
        $tourResults = searchTours($query, $limit);
        $results['tours'] = $tourResults;
    }
    
    // Search Destinations
    if ($type === 'all' || $type === 'destinations') {
        $destinationResults = searchDestinations($query, $limit);
        $results['destinations'] = $destinationResults;
    }
    
    // Search Blog Posts
    if ($type === 'all' || $type === 'blog') {
        $blogResults = searchBlogPosts($query, $limit);
        $results['blog'] = $blogResults;
    }
    
    // Count total results
    $totalResults = 0;
    foreach ($results as $category => $items) {
        $totalResults += count($items);
    }
    
    echo json_encode([
        'success' => true,
        'query' => $query,
        'total' => $totalResults,
        'results' => $results
    ]);

} catch (Exception $e) {
    error_log('AJAX Search Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Search failed',
        'message' => 'Please try again later',
        'debug' => $e->getMessage() // Remove this in production
    ]);
}

/**
 * Search tours based on title, description, and destination
 */
function searchTours($query, $limit) {
    global $db;
    
    $searchQuery = "%$query%";
    
    $sql = "
        SELECT 
            t.id,
            t.title,
            t.slug,
            t.short_description,
            t.featured_image,
            t.price,
            t.discount_price,
            t.duration_days,
            d.name as destination_name,
            d.country,
            'tour' as type
        FROM tours t 
        LEFT JOIN destinations d ON t.destination_id = d.id 
        WHERE t.status = 'active' 
        AND (
            t.title LIKE ? 
            OR t.short_description LIKE ? 
            OR t.description LIKE ? 
            OR d.name LIKE ? 
            OR d.country LIKE ?
        )
        ORDER BY 
            CASE 
                WHEN t.title LIKE ? THEN 1
                WHEN t.short_description LIKE ? THEN 2
                ELSE 3
            END,
            t.featured DESC,
            t.popular DESC
        LIMIT ?
    ";
    
    $params = [
        $searchQuery, $searchQuery, $searchQuery, $searchQuery, $searchQuery,
        $searchQuery, $searchQuery, $limit
    ];
    
    $results = $db->fetchAll($sql, $params);
    
    // Format results
    foreach ($results as &$result) {
        $result['url'] = BASE_URL . 'tour-details.php?id=' . $result['id'];
        $result['image'] = $result['featured_image'] ? BASE_URL . $result['featured_image'] : BASE_URL . 'assets/images/tours/default.jpg';
        $result['price_formatted'] = formatPriceINR($result['price']);
        $result['discount_price_formatted'] = $result['discount_price'] ? formatPriceINR($result['discount_price']) : null;
        $result['duration_text'] = $result['duration_days'] . ' Days';
        $result['location'] = ($result['destination_name'] ? $result['destination_name'] : '') . 
                             ($result['country'] ? ', ' . $result['country'] : '');
    }
    
    return $results;
}

/**
 * Search destinations based on name, city, country, and description
 */
function searchDestinations($query, $limit) {
    global $db;
    
    $searchQuery = "%$query%";
    
    $sql = "
        SELECT 
            id,
            name,
            slug,
            country,
            city,
            short_description,
            featured_image,
            'destination' as type
        FROM destinations 
        WHERE status = 'active' 
        AND (
            name LIKE ? 
            OR country LIKE ? 
            OR city LIKE ? 
            OR short_description LIKE ? 
            OR description LIKE ?
        )
        ORDER BY 
            CASE 
                WHEN name LIKE ? THEN 1
                WHEN country LIKE ? THEN 2
                ELSE 3
            END,
            popular DESC
        LIMIT ?
    ";
    
    $params = [
        $searchQuery, $searchQuery, $searchQuery, $searchQuery, $searchQuery,
        $searchQuery, $searchQuery, $limit
    ];
    
    $results = $db->fetchAll($sql, $params);
    
    // Format results
    foreach ($results as &$result) {
        $result['url'] = BASE_URL . 'destination-details.php?id=' . $result['id'];
        $result['image'] = $result['featured_image'] ? BASE_URL . $result['featured_image'] : BASE_URL . 'assets/images/destinations/default.jpg';
        $result['location'] = ($result['city'] ? $result['city'] . ', ' : '') . $result['country'];
    }
    
    return $results;
}

/**
 * Search blog posts based on title, excerpt, and content
 */
function searchBlogPosts($query, $limit) {
    global $db;
    
    $searchQuery = "%$query%";
    
    $sql = "
        SELECT 
            bp.id,
            bp.title,
            bp.slug,
            bp.excerpt,
            bp.featured_image,
            bp.published_at,
            bc.name as category_name,
            au.full_name as author_name,
            'blog' as type
        FROM blog_posts bp
        LEFT JOIN blog_categories bc ON bp.category_id = bc.id
        LEFT JOIN admin_users au ON bp.author_id = au.id
        WHERE bp.status = 'published' 
        AND (
            bp.title LIKE ? 
            OR bp.excerpt LIKE ? 
            OR bp.content LIKE ?
        )
        ORDER BY 
            CASE 
                WHEN bp.title LIKE ? THEN 1
                WHEN bp.excerpt LIKE ? THEN 2
                ELSE 3
            END,
            bp.featured DESC,
            bp.published_at DESC
        LIMIT ?
    ";
    
    $params = [
        $searchQuery, $searchQuery, $searchQuery,
        $searchQuery, $searchQuery, $limit
    ];
    
    $results = $db->fetchAll($sql, $params);
    
    // Format results
    foreach ($results as &$result) {
        $result['url'] = BASE_URL . 'blog/' . $result['slug'];
        $result['image'] = $result['featured_image'] ? BASE_URL . $result['featured_image'] : BASE_URL . 'assets/images/blog/default-blog.jpg';
        $result['date_formatted'] = date('M d, Y', strtotime($result['published_at']));
        $result['excerpt_short'] = substr($result['excerpt'], 0, 100) . (strlen($result['excerpt']) > 100 ? '...' : '');
    }
    
    return $results;
}
?>
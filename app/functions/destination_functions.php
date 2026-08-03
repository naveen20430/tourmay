<?php
/**
 * Destination Functions
 * All database operations related to destinations
 */

require_once __DIR__ . '/../db.php';

/**
 * Get all active destinations
 * @param array $filters Optional filters (status, popular, country, etc.)
 * @return array
 */
function getDestinations($filters = []) {
    $db = DB::getInstance();
    
    $where = ['d.status = "active"'];
    $params = [];
    
    if (!empty($filters['popular'])) {
        $where[] = 'd.popular = 1';
    }
    
    if (!empty($filters['country'])) {
        $where[] = 'd.country = ?';
        $params[] = $filters['country'];
    }
    
    if (!empty($filters['slug'])) {
        $where[] = 'd.slug = ?';
        $params[] = $filters['slug'];
    }
    
    $whereClause = implode(' AND ', $where);
    $orderBy = $filters['order_by'] ?? 'd.created_at DESC';
    $limit = isset($filters['limit']) ? 'LIMIT ' . (int)$filters['limit'] : '';
    
    $sql = "SELECT d.*,
                   (
                       SELECT COUNT(DISTINCT t.id)
                       FROM tours t
                       LEFT JOIN tour_destinations td ON td.tour_id = t.id
                       WHERE t.status = 'active'
                         AND (td.destination_id = d.id OR t.destination_id = d.id)
                   ) AS tour_count
            FROM destinations d
            WHERE $whereClause
            ORDER BY $orderBy
            $limit";
    
    return $db->fetchAll($sql, $params);
}

/**
 * Get single destination by ID or slug
 * @param int|string $identifier Destination ID or slug
 * @return array|false
 */
function getDestination($identifier) {
    $db = DB::getInstance();
    
    if (is_numeric($identifier)) {
        $sql = "SELECT * FROM destinations WHERE id = ? AND status = 'active'";
    } else {
        $sql = "SELECT * FROM destinations WHERE slug = ? AND status = 'active'";
    }
    
    return $db->fetch($sql, [$identifier]);
}

/**
 * Get popular destinations for home page
 * @param int $limit Number of destinations to return
 * @return array
 */
function getPopularDestinationsForHome($limit = 3) {
    return getDestinations([
        'popular' => true,
        'limit' => $limit,
        'order_by' => 'd.created_at DESC'
    ]);
}

/**
 * Get all countries from destinations
 * @return array
 */
function getDestinationCountries() {
    $db = DB::getInstance();
    
    $sql = "SELECT DISTINCT country 
            FROM destinations 
            WHERE status = 'active' 
            AND country IS NOT NULL 
            AND country != '' 
            ORDER BY country";
    
    return $db->fetchAll($sql);
}

/**
 * Get destination image URL with fallback
 * @param array $destination Destination data
 * @param string $default Default image path
 * @return string
 */
function getDestinationImageUrl($destination, $default = 'assets/images/destinations/default.jpg') {
    if (!empty($destination['featured_image']) && file_exists($destination['featured_image'])) {
        return BASE_URL . $destination['featured_image'];
    }
    return BASE_URL . $default;
}


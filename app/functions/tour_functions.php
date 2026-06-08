<?php
/**
 * Tour Functions
 * All database operations related to tours
 */

require_once __DIR__ . '/../db.php';

/**
 * Get tours with optional filters
 * @param array $filters Optional filters (status, featured, destination, etc.)
 * @return array
 */
function getTours($filters = []) {
    $db = DB::getInstance();
    
    $where = ['t.status = "active"'];
    $params = [];
    
    if (!empty($filters['featured'])) {
        $where[] = 't.featured = 1';
    }
    
    if (!empty($filters['destination_slug'])) {
        $where[] = 'd.slug = ?';
        $params[] = $filters['destination_slug'];
    }
    
    if (!empty($filters['destination_id'])) {
        $where[] = 't.destination_id = ?';
        $params[] = $filters['destination_id'];
    }
    
    if (!empty($filters['country'])) {
        $where[] = 'd.country = ?';
        $params[] = $filters['country'];
    }
    
    $whereClause = implode(' AND ', $where);
    $orderBy = $filters['order_by'] ?? 't.created_at DESC';
    $limit = isset($filters['limit']) ? 'LIMIT ' . (int)$filters['limit'] : '';
    
    $sql = "SELECT t.*, d.name as destination_name, d.country, d.slug as destination_slug
            FROM tours t
            LEFT JOIN destinations d ON t.destination_id = d.id
            WHERE $whereClause
            ORDER BY $orderBy
            $limit";
    
    return $db->fetchAll($sql, $params);
}

/**
 * Get single tour by ID or slug
 * @param int|string $identifier Tour ID or slug
 * @return array|false
 */
function getTour($identifier) {
    $db = DB::getInstance();
    
    if (is_numeric($identifier)) {
        $sql = "SELECT t.*, d.name as destination_name, d.country, d.slug as destination_slug
                FROM tours t
                LEFT JOIN destinations d ON t.destination_id = d.id
                WHERE t.id = ? AND t.status = 'active'";
    } else {
        $sql = "SELECT t.*, d.name as destination_name, d.country, d.slug as destination_slug
                FROM tours t
                LEFT JOIN destinations d ON t.destination_id = d.id
                WHERE t.slug = ? AND t.status = 'active'";
    }
    
    return $db->fetch($sql, [$identifier]);
}

/**
 * Get related tours (same destination, excluding current tour)
 * @param int $tourId Current tour ID
 * @param int $destinationId Destination ID
 * @param int $limit Number of related tours
 * @return array
 */
function getRelatedTours($tourId, $destinationId, $limit = 3) {
    $db = DB::getInstance();
    
    $sql = "SELECT t.*, d.name as destination_name
            FROM tours t
            LEFT JOIN destinations d ON t.destination_id = d.id
            WHERE t.destination_id = ? 
            AND t.id != ? 
            AND t.status = 'active'
            ORDER BY t.created_at DESC
            LIMIT ?";
    
    return $db->fetchAll($sql, [$destinationId, $tourId, $limit]);
}

/**
 * Get tour gallery images
 * @param int $tourId Tour ID
 * @return array
 */
function getTourGallery($tourId) {
    $db = DB::getInstance();
    
    $sql = "SELECT image_path 
            FROM tour_gallery 
            WHERE tour_id = ? 
            ORDER BY display_order ASC, id ASC";
    
    return $db->fetchAll($sql, [$tourId]);
}

/**
 * Get tour image URL with fallback
 * @param array $tour Tour data
 * @param string $default Default image path
 * @return string
 */
function getTourImageUrl($tour, $default = 'assets/images/tours/default-tour.jpg') {
    if (!empty($tour['featured_image']) && file_exists($tour['featured_image'])) {
        return BASE_URL . $tour['featured_image'];
    }
    return BASE_URL . $default;
}


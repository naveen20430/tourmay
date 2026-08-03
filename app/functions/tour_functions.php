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
        $where[] = '(
            EXISTS (
                SELECT 1 FROM tour_destinations td_link
                INNER JOIN destinations d_link ON d_link.id = td_link.destination_id
                WHERE td_link.tour_id = t.id AND d_link.slug = ?
            )
            OR EXISTS (
                SELECT 1 FROM destinations d_legacy
                WHERE d_legacy.id = t.destination_id AND d_legacy.slug = ?
            )
        )';
        $params[] = $filters['destination_slug'];
        $params[] = $filters['destination_slug'];
    }
    
    if (!empty($filters['destination_id'])) {
        $where[] = '(
            EXISTS (
                SELECT 1 FROM tour_destinations td_link
                WHERE td_link.tour_id = t.id AND td_link.destination_id = ?
            )
            OR t.destination_id = ?
        )';
        $params[] = $filters['destination_id'];
        $params[] = $filters['destination_id'];
    }
    
    if (!empty($filters['country'])) {
        $where[] = '(
            EXISTS (
                SELECT 1 FROM tour_destinations td_c
                INNER JOIN destinations d_c ON d_c.id = td_c.destination_id
                WHERE td_c.tour_id = t.id AND d_c.country = ?
            )
            OR d.country = ?
        )';
        $params[] = $filters['country'];
        $params[] = $filters['country'];
    }
    
    $whereClause = implode(' AND ', $where);
    $orderBy = $filters['order_by'] ?? 't.created_at DESC';
    $limit = isset($filters['limit']) ? 'LIMIT ' . (int)$filters['limit'] : '';
    
    $sql = "SELECT t.*,
                   COALESCE(
                       NULLIF((
                           SELECT GROUP_CONCAT(DISTINCT d2.name ORDER BY td2.sort_order ASC, d2.name ASC SEPARATOR ', ')
                           FROM tour_destinations td2
                           INNER JOIN destinations d2 ON d2.id = td2.destination_id
                           WHERE td2.tour_id = t.id
                       ), ''),
                       d.name
                   ) AS destination_name,
                   d.country,
                   d.slug AS destination_slug
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
    
    $sql = "SELECT DISTINCT t.*,
                   COALESCE(
                       NULLIF((
                           SELECT GROUP_CONCAT(DISTINCT d2.name ORDER BY td2.sort_order ASC, d2.name ASC SEPARATOR ', ')
                           FROM tour_destinations td2
                           INNER JOIN destinations d2 ON d2.id = td2.destination_id
                           WHERE td2.tour_id = t.id
                       ), ''),
                       d.name
                   ) AS destination_name
            FROM tours t
            LEFT JOIN destinations d ON t.destination_id = d.id
            WHERE t.id != ?
              AND t.status = 'active'
              AND (
                  EXISTS (
                      SELECT 1 FROM tour_destinations td_cur
                      INNER JOIN tour_destinations td_rel ON td_rel.destination_id = td_cur.destination_id
                      WHERE td_cur.tour_id = ? AND td_rel.tour_id = t.id
                  )
                  OR t.destination_id = ?
              )
            ORDER BY t.created_at DESC
            LIMIT ?";
    
    return $db->fetchAll($sql, [$tourId, $tourId, $destinationId, $limit]);
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


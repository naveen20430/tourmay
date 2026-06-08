<?php
/**
 * Blog Functions
 * All database operations related to blog posts
 */

require_once __DIR__ . '/../db.php';

/**
 * Get blog posts with optional filters
 * @param array $filters Optional filters (category, search, status, etc.)
 * @param int $page Page number
 * @param int $perPage Posts per page
 * @return array ['posts' => array, 'total' => int]
 */
function getBlogPosts($filters = [], $page = 1, $perPage = 6) {
    $db = DB::getInstance();
    
    $where = ['bp.status = "published"'];
    $params = [];
    
    if (!empty($filters['category_slug'])) {
        $where[] = 'bc.slug = ?';
        $params[] = $filters['category_slug'];
    }
    
    if (!empty($filters['category_id'])) {
        $where[] = 'bp.category_id = ?';
        $params[] = $filters['category_id'];
    }
    
    if (!empty($filters['featured'])) {
        $where[] = 'bp.featured = 1';
    }
    
    if (!empty($filters['search'])) {
        $where[] = '(bp.title LIKE ? OR bp.content LIKE ? OR bp.excerpt LIKE ?)';
        $searchTerm = '%' . $filters['search'] . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    $whereClause = implode(' AND ', $where);
    $offset = ($page - 1) * $perPage;
    
    // Get posts
    $sql = "SELECT bp.*, bc.name as category_name, bc.slug as category_slug, au.full_name as author_name
            FROM blog_posts bp
            LEFT JOIN blog_categories bc ON bp.category_id = bc.id
            LEFT JOIN admin_users au ON bp.author_id = au.id
            WHERE $whereClause
            ORDER BY bp.published_at DESC, bp.created_at DESC
            LIMIT ? OFFSET ?";
    
    $params[] = $perPage;
    $params[] = $offset;
    
    $posts = $db->fetchAll($sql, $params);
    
    // Get total count
    $countSql = "SELECT COUNT(*) as total
                 FROM blog_posts bp
                 LEFT JOIN blog_categories bc ON bp.category_id = bc.id
                 WHERE $whereClause";
    
    $total = $db->fetch($countSql, array_slice($params, 0, -2))['total'];
    
    return [
        'posts' => $posts,
        'total' => $total
    ];
}

/**
 * Get single blog post by ID or slug
 * @param int|string $identifier Post ID or slug
 * @return array|false
 */
function getBlogPost($identifier) {
    $db = DB::getInstance();
    
    if (is_numeric($identifier)) {
        $sql = "SELECT bp.*, bc.name as category_name, bc.slug as category_slug, au.full_name as author_name
                FROM blog_posts bp
                LEFT JOIN blog_categories bc ON bp.category_id = bc.id
                LEFT JOIN admin_users au ON bp.author_id = au.id
                WHERE bp.id = ? AND bp.status = 'published'";
    } else {
        $sql = "SELECT bp.*, bc.name as category_name, bc.slug as category_slug, au.full_name as author_name
                FROM blog_posts bp
                LEFT JOIN blog_categories bc ON bp.category_id = bc.id
                LEFT JOIN admin_users au ON bp.author_id = au.id
                WHERE bp.slug = ? AND bp.status = 'published'";
    }
    
    return $db->fetch($sql, [$identifier]);
}

/**
 * Get blog categories
 * @return array
 */
function getBlogCategories() {
    $db = DB::getInstance();
    
    $sql = "SELECT bc.*, COUNT(bp.id) as post_count
            FROM blog_categories bc
            LEFT JOIN blog_posts bp ON bc.id = bp.category_id AND bp.status = 'published'
            GROUP BY bc.id
            ORDER BY bc.name ASC";
    
    return $db->fetchAll($sql);
}

/**
 * Get featured blog posts
 * @param int $limit Number of posts
 * @return array
 */
function getFeaturedBlogPosts($limit = 3) {
    $result = getBlogPosts(['featured' => true], 1, $limit);
    return $result['posts'];
}

/**
 * Get blog post image URL with fallback
 * @param array $post Blog post data
 * @param string $default Default image path
 * @return string
 */
function getBlogPostImageUrl($post, $default = 'assets/images/blog/default-blog.jpg') {
    if (!empty($post['featured_image']) && file_exists($post['featured_image'])) {
        return BASE_URL . $post['featured_image'];
    }
    return BASE_URL . $default;
}


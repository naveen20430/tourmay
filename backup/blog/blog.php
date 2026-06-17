<?php
require_once 'config/config.php';

// Set page variables
$page_title = 'Travel Blog - ' . getSetting('site_name');
$current_page = 'blog';

// Get search parameters
$category = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';
$page = $_GET['page'] ?? 1;
$per_page = 6;
$offset = ($page - 1) * $per_page;

// Build query
$where_conditions = ['bp.status = "published"'];
$params = [];

if ($category) {
    $where_conditions[] = 'bc.slug = ?';
    $params[] = $category;
}

if ($search) {
    $where_conditions[] = '(bp.title LIKE ? OR bp.content LIKE ? OR bp.excerpt LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_clause = implode(' AND ', $where_conditions);

// Get blog posts
$blog_posts = $db->fetchAll("
    SELECT bp.*, bc.name as category_name, bc.slug as category_slug, au.full_name as author_name
    FROM blog_posts bp
    LEFT JOIN blog_categories bc ON bp.category_id = bc.id
    LEFT JOIN admin_users au ON bp.author_id = au.id
    WHERE $where_clause
    ORDER BY bp.published_at DESC, bp.created_at DESC
    LIMIT $per_page OFFSET $offset
", $params);

// Get total count for pagination
$total_posts = $db->fetch("
    SELECT COUNT(*) as total
    FROM blog_posts bp
    LEFT JOIN blog_categories bc ON bp.category_id = bc.id
    WHERE $where_clause
", $params)['total'];

$total_pages = ceil($total_posts / $per_page);

// Get categories for filter
$categories = $db->fetchAll("SELECT * FROM blog_categories WHERE status = 'active' ORDER BY name");

// Get featured posts
$featured_posts = $db->fetchAll("
    SELECT bp.*, bc.name as category_name, bc.slug as category_slug, au.full_name as author_name
    FROM blog_posts bp
    LEFT JOIN blog_categories bc ON bp.category_id = bc.id
    LEFT JOIN admin_users au ON bp.author_id = au.id
    WHERE bp.status = 'published' AND bp.featured = 1
    ORDER BY bp.published_at DESC
    LIMIT 3
");

// Set extra CSS for blog page
$extra_css = '
<style>
    /* Blog card styling */
    .blog-card {
        background: white;
        border: none;
        border-radius: 12px;
        overflow: hidden;
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        height: 100%;
        display: flex;
        flex-direction: column;
    }
    
    .blog-card:hover {
        transform: translateY(-12px) scale(1.02);
        box-shadow: 0 25px 60px rgba(118, 75, 162, 0.25);
    }
    
    .blog-image {
        height: 240px;
        object-fit: cover;
        width: 100%;
        transition: transform 0.4s ease;
    }
    
    .blog-card:hover .blog-image {
        transform: scale(1.08);
    }
    
    /* Featured badge styling */
    .featured-badge {
        position: absolute;
        top: 15px;
        left: 15px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 8px 16px;
        border-radius: 20px;
        font-size: 0.9rem;
        font-weight: 600;
        box-shadow: 0 4px 15px rgba(118, 75, 162, 0.3);
        z-index: 2;
    }
    
    /* Category badge styling */
    .category-badge {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 0.8rem;
        text-decoration: none;
        font-weight: 600;
        display: inline-block;
        transition: all 0.3s ease;
    }
    
    .category-badge:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(118, 75, 162, 0.3);
        color: white;
    }
    
    /* Blog post meta styling */
    .blog-meta {
        display: flex;
        gap: 15px;
        font-size: 0.9rem;
        color: #6c757d;
        margin: 12px 0;
        flex-wrap: wrap;
    }
    
    .blog-meta a {
        color: #667eea;
        text-decoration: none;
        transition: color 0.3s ease;
    }
    
    .blog-meta a:hover {
        color: #764ba2;
    }
    
    /* Blog card body */
    .blog-card-body {
        padding: 25px;
        flex: 1;
        display: flex;
        flex-direction: column;
    }
    
    .blog-title {
        font-size: 1.3rem;
        font-weight: 700;
        margin: 0 0 12px 0;
        color: #1a202c;
        line-height: 1.4;
    }
    
    .blog-title a {
        color: #1a202c;
        text-decoration: none;
        transition: color 0.3s ease;
    }
    
    .blog-title a:hover {
        color: #667eea;
    }
    
    .blog-excerpt {
        color: #6c757d;
        line-height: 1.6;
        margin: 12px 0;
        flex: 1;
    }
    
    /* Blog card footer */
    .blog-card-footer {
        padding-top: 20px;
        border-top: 1px solid #e9ecef;
        margin-top: auto;
    }
    
    .blog-card-footer a {
        color: white;
        text-decoration: none;
    }
    
    /* Blog-specific responsive improvements */
    @media (max-width: 767px) {
        .blog-image {
            height: 200px;
        }
        
        .blog-title {
            font-size: 1.1rem;
        }
        
        .blog-excerpt {
            min-height: 50px;
        }
    }
    
    @media (max-width: 575px) {
        .blog-image {
            height: 180px;
        }
        
        .featured-badge,
        .category-badge {
            font-size: 0.65rem;
            padding: 3px 8px;
        }
    }

    .blog-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 24px;
    }

    @media (max-width: 991px) {
        .blog-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 575px) {
        .blog-grid {
            grid-template-columns: 1fr;
        }
    }

    .blog-section-block {
        margin-bottom: 50px;
    }

    .blog-section-heading {
        font-size: 1.6rem;
        font-weight: 700;
        color: #1a202c;
        margin: 0 0 20px 0;
    }

    .blog-section-heading a {
        color: inherit;
        text-decoration: none;
        transition: color 0.3s ease;
    }

    .blog-section-heading a:hover {
        color: #667eea;
    }

    /* Custom styles for search form fields */
    .form-field-container {
        position: relative;
        padding-top: 5px;
    }
    
    .form-field-label {
        font-weight: 600;
        color: #1a202c;
        margin-bottom: 0;
        position: absolute;
        top: -8px;
        left: 12px;
        font-size: 0.75rem;
        background: white;
        padding: 0 4px;
        z-index: 3;
    }
    
    .form-field-input {
        border: 2px solid #e9ecef;
        border-radius: 8px;
        padding: 20px 15px 10px 15px;
        font-size: 0.95rem;
        transition: all 0.3s ease;
        width: 100%;
        position: relative;
        z-index: 2;
    }
    
    .form-field-input:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        outline: none;
    }
    
    .form-field-select {
        border: 2px solid #e9ecef;
        border-radius: 8px;
        padding: 20px 38px 10px 15px;
        font-size: 0.95rem;
        transition: all 0.3s ease;
        width: 100%;
        appearance: none;
        background-image: url(\'data:image/svg+xml;charset=UTF-8,%3csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="%23667eea" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"%3e%3cpolyline points="6 9 12 15 18 9"%3e%3c/polyline%3e%3c/svg%3e\');
        background-repeat: no-repeat;
        background-position: right 12px center;
        background-size: 18px;
        position: relative;
        z-index: 2;
    }
    
    .form-field-select:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        outline: none;
    }
</style>
';

// Include header
include 'includes/header.php';
?>

    <!-- Blog Hero -->
    <section class="blog-hero">
        <div class="container">
            <h1 class="display-4 fw-bold">Travel Stories & Tips</h1>
            <p class="lead">Discover amazing travel experiences, helpful tips, and inspiring stories from around the world</p>
        </div>
    </section>

    <!-- Search & Filter Section -->
    <section class="blog-search-section" style="background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%); padding: 60px 0; position: relative; overflow: hidden;">
        <!-- Decorative elements -->
        <div style="position: absolute; top: -50px; right: -50px; width: 300px; height: 300px; background: rgba(102, 126, 234, 0.1); border-radius: 50%; animation: float 8s ease-in-out infinite;"></div>
        <div style="position: absolute; bottom: -100px; left: -100px; width: 400px; height: 400px; background: rgba(118, 75, 162, 0.05); border-radius: 50%; animation: float 12s ease-in-out infinite reverse;"></div>
        
        <div class="container" style="position: relative; z-index: 2;">
            <div class="row mb-5">
                <div class="col-lg-10 mx-auto text-center">
                    <h3 style="font-size: 2rem; font-weight: 700; color: #1a202c; margin-bottom: 12px;">Find Travel Stories</h3>
                    <p style="color: #6c757d; font-size: 1.05rem; line-height: 1.6;">Search through our collection of travel tips, destination guides, and inspiring stories</p>
                </div>
            </div>
            
            <form method="GET">
                <div class="row gx-4 justify-content-center align-items-end">
                    <!-- Search Posts Field -->
                    <div class="col-lg-4 col-md-6 col-sm-12">
                        <div class="form-field-container">
                            <label class="form-field-label">
                                <i class="fas fa-search" style="color: #667eea; margin-right: 6px;"></i>Search Posts
                            </label>
                            <input type="text" name="search" class="form-control form-field-input" placeholder="Enter keywords..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                    </div>

                    <!-- Category Field -->
                    <div class="col-lg-3 col-md-5 col-sm-12">
                        <div class="form-field-container">
                            <label class="form-field-label">
                                <i class="fas fa-tag" style="color: #667eea; margin-right: 6px;"></i>Category
                            </label>
                            <select name="category" class="form-select form-field-select">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['slug']; ?>" <?php echo $category == $cat['slug'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="col-lg-3 col-md-12 col-sm-12">
                        <button type="submit" class="btn w-100" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 8px; padding: 12px 24px; font-weight: 600; font-size: 0.95rem; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(118, 75, 162, 0.3); cursor: pointer;"
                                onmouseover="this.style.boxShadow='0 8px 25px rgba(118, 75, 162, 0.4)'; this.style.transform='translateY(-2px)';"
                                onmouseout="this.style.boxShadow='0 4px 15px rgba(118, 75, 162, 0.3)'; this.style.transform='translateY(0)';">
                            <i class="fas fa-search" style="margin-right: 6px;"></i>Search
                        </button>
                    </div>
                </div>
                
                <?php if ($search || $category): ?>
                <div class="row mt-4">
                    <div class="col-lg-12 text-center">
                        <a href="blog" class="btn" style="border: 2px solid #667eea; color: #667eea; background: transparent; border-radius: 8px; padding: 10px 24px; font-weight: 600; transition: all 0.3s ease; display: inline-block;"
                           onmouseover="this.style.backgroundColor='#667eea'; this.style.color='white';"
                           onmouseout="this.style.backgroundColor='transparent'; this.style.color='#667eea';">
                            <i class="fas fa-times" style="margin-right: 6px;"></i>Clear All Filters
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </form>
        </div>
        
        <style>
            @keyframes float {
                0%, 100% { transform: translateY(0px); }
                50% { transform: translateY(-15px); }
            }
            
            @media (max-width: 768px) {
                .blog-search-section {
                    padding: 40px 0;
                }
                
                .blog-search-section h3 {
                    font-size: 1.5rem !important;
                }
                
                .blog-search-section p {
                    font-size: 0.95rem !important;
                }
            }
        </style>
    </section>

    <!-- Featured Posts -->
    <?php if (!$search && !$category && !empty($featured_posts)): ?>
    <section class="section-space">
        <div class="container">
            <div class="section-title text-center mb-5">
                <span class="section-title__tagline">Featured Stories</span>
                <h2 class="section-title__title">Must-Read Travel Posts</h2>
            </div>
            
            <div class="blog-grid">
                <?php foreach ($featured_posts as $post): ?>
                    <div class="card blog-card shadow-sm h-100">
                        <div class="position-relative">
                            <?php 
                            $image_path = !empty($post['featured_image']) && file_exists($post['featured_image']) 
                                ? BASE_URL . htmlspecialchars($post['featured_image']) 
                                : BASE_URL . 'assets/images/blog/default-blog.jpg';
                            ?>
                            <img src="<?php echo $image_path; ?>" 
                                 class="blog-image" alt="<?php echo htmlspecialchars($post['title']); ?>"
                                 onerror="this.src='<?php echo BASE_URL; ?>assets/images/blog/default-blog.jpg'">
                            <span class="featured-badge">Featured</span>
                        </div>
                        
                        <div class="card-body d-flex flex-column">
                            <div class="mb-2">
                                <?php if ($post['category_name']): ?>
                                    <a href="blog?category=<?php echo $post['category_slug']; ?>" class="category-badge">
                                        <?php echo htmlspecialchars($post['category_name']); ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                            
                            <h5 class="card-title">
                                <a href="blog/<?php echo $post['slug']; ?>" class="text-decoration-none text-dark">
                                    <?php echo htmlspecialchars($post['title']); ?>
                                </a>
                            </h5>
                            
                            <p class="card-text text-muted flex-grow-1">
                                <?php echo htmlspecialchars(substr($post['excerpt'], 0, 120)); ?>...
                            </p>
                            
                            <div class="mt-auto">
                                <small class="text-muted">
                                    <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($post['author_name']); ?>
                                    <i class="fas fa-calendar ms-3 me-1"></i><?php echo date('M d, Y', strtotime($post['published_at'])); ?>
                                </small>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Blog Posts Grid -->
    <section class="section-space" <?php echo (!$search && !$category && !empty($featured_posts)) ? 'style="padding-top: 0;"' : ''; ?>>
        <div class="container">
            <div class="section-title text-center mb-5">
                <span class="section-title__tagline">
                    <?php if ($category): ?>
                        <?php 
                        $current_category = array_filter($categories, function($cat) use ($category) {
                            return $cat['slug'] === $category;
                        });
                        echo htmlspecialchars(reset($current_category)['name'] ?? 'Category');
                        ?>
                    <?php elseif ($search): ?>
                        Search Results
                    <?php else: ?>
                        Latest Posts
                    <?php endif; ?>
                </span>
                <h2 class="section-title__title">
                    <?php if ($search): ?>
                        Results for "<?php echo htmlspecialchars($search); ?>"
                    <?php else: ?>
                        Travel Blog Posts
                    <?php endif; ?>
                </h2>
            </div>

            <?php if (empty($blog_posts)): ?>
                <div class="text-center py-5">
                    <div class="mb-4">
                        <i class="fas fa-search fa-4x text-muted"></i>
                    </div>
                    <h3 class="text-muted">No Posts Found</h3>
                    <p class="text-muted">Try adjusting your search terms or browse all posts.</p>
                    <a href="blog" class="btn btn-primary">View All Posts</a>
                </div>
            <?php else: ?>
                <?php 
                $posts_by_category = [];
                foreach ($blog_posts as $post) {
                    $cat_name = $post['category_name'] ?: 'Other';
                    $cat_slug = $post['category_slug'] ?: '';
                    if (!isset($posts_by_category[$cat_name])) {
                        $posts_by_category[$cat_name] = [
                            'slug' => $cat_slug,
                            'posts' => []
                        ];
                    }
                    $posts_by_category[$cat_name]['posts'][] = $post;
                }
                ?>

                <?php foreach ($posts_by_category as $cat_name => $group): ?>
                    <div class="blog-section-block">
                        <h3 class="blog-section-heading">
                            <?php if (!empty($group['slug'])): ?>
                                <a href="blog?category=<?php echo $group['slug']; ?>">
                                    <?php echo htmlspecialchars($cat_name); ?>
                                </a>
                            <?php else: ?>
                                <?php echo htmlspecialchars($cat_name); ?>
                            <?php endif; ?>
                        </h3>

                        <div class="blog-grid">
                            <?php foreach ($group['posts'] as $post): ?>
                                <div class="card blog-card shadow-sm h-100">
                                    <div class="position-relative">
                                        <?php 
                                        $image_path = !empty($post['featured_image']) && file_exists($post['featured_image']) 
                                            ? BASE_URL . htmlspecialchars($post['featured_image']) 
                                            : BASE_URL . 'assets/images/blog/default-blog.jpg';
                                        ?>
                                        <img src="<?php echo $image_path; ?>" 
                                             class="blog-image" alt="<?php echo htmlspecialchars($post['title']); ?>"
                                             onerror="this.src='<?php echo BASE_URL; ?>assets/images/blog/default-blog.jpg'">
                                        
                                        <?php if ($post['featured']): ?>
                                            <span class="featured-badge">Featured</span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="card-body d-flex flex-column">
                                        <div class="mb-2">
                                            <?php if ($post['category_name']): ?>
                                                <a href="blog?category=<?php echo $post['category_slug']; ?>" class="category-badge">
                                                    <?php echo htmlspecialchars($post['category_name']); ?>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <h5 class="card-title">
                                            <a href="blog/<?php echo $post['slug']; ?>" class="text-decoration-none text-dark">
                                                <?php echo htmlspecialchars($post['title']); ?>
                                            </a>
                                        </h5>
                                        
                                        <p class="card-text text-muted flex-grow-1">
                                            <?php echo htmlspecialchars(substr($post['excerpt'], 0, 120)); ?>...
                                        </p>
                                        
                                        <div class="mt-auto">
                                            <small class="text-muted">
                                                <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($post['author_name']); ?>
                                                <i class="fas fa-calendar ms-3 me-1"></i><?php echo date('M d, Y', strtotime($post['published_at'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="row mt-5">
                        <div class="col-12">
                            <nav aria-label="Blog pagination">
                                <ul class="pagination justify-content-center">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page-1; ?><?php echo $category ? '&category='.$category : ''; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?>">
                                                <i class="fas fa-chevron-left"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?><?php echo $category ? '&category='.$category : ''; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page+1; ?><?php echo $category ? '&category='.$category : ''; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?>">
                                                <i class="fas fa-chevron-right"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>

<?php include 'includes/footer.php'; ?>
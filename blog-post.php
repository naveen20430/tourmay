<?php
require_once 'config/config.php';

// Get blog post slug from URL
$slug = $_GET['slug'] ?? '';

if (!$slug) {
    header('Location: blog.php');
    exit;
}

// Get blog post details
$post = $db->fetch("
    SELECT * FROM blog_posts 
    WHERE slug = ? AND status = 'published'
", [$slug]);

if (!$post) {
    header('HTTP/1.0 404 Not Found');
    include '404.php';
    exit;
}

// Get related posts
$related_posts = $db->fetchAll("
    SELECT * FROM blog_posts 
    WHERE category_id = ? AND id != ? AND status = 'published'
    ORDER BY created_at DESC 
    LIMIT 3
", [$post['category_id'], $post['id']]);

// Set page variables
$page_title = htmlspecialchars($post['title']) . ' - ' . getSetting('site_name');
$current_page = 'blog';

// Include header
include 'includes/header.php';
?>

<div class="container py-5">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo navUrl('home'); ?>">Home</a></li>
            <li class="breadcrumb-item"><a href="<?php echo navUrl('blog'); ?>">Blog</a></li>
            <li class="breadcrumb-item active"><?php echo htmlspecialchars($post['title']); ?></li>
        </ol>
    </nav>

    <div class="row">
        <div class="col-lg-8">
            <!-- Blog Post -->
            <article class="blog-post">
                <div class="post-image mb-4">
                    <?php 
                    $image_path = !empty($post['featured_image']) && file_exists($post['featured_image']) 
                        ? BASE_URL . htmlspecialchars($post['featured_image']) 
                        : BASE_URL . 'assets/images/blog/default-blog.jpg';
                    ?>
                    <img src="<?php echo $image_path; ?>" 
                         class="img-fluid rounded" 
                         alt="<?php echo htmlspecialchars($post['title']); ?>"
                         style="width: 100%; height: 400px; object-fit: cover;"
                         onerror="this.src='<?php echo BASE_URL; ?>assets/images/blog/default-blog.jpg'">
                </div>

                <header class="post-header mb-4">
                    <h1 class="post-title mb-3"><?php echo htmlspecialchars($post['title']); ?></h1>
                    
                    <div class="post-meta d-flex flex-wrap align-items-center text-muted mb-3">
                        <span class="me-3">
                            <i class="fas fa-calendar me-1"></i>
                            <?php echo date('M d, Y', strtotime($post['created_at'])); ?>
                        </span>
                        <span class="me-3">
                            <i class="fas fa-user me-1"></i>
                            <?php echo htmlspecialchars($post['author'] ?? 'Admin'); ?>
                        </span>
                        <span class="me-3">
                            <i class="fas fa-clock me-1"></i>
                            <?php echo $post['reading_time'] ?? '5'; ?> min read
                        </span>
                        <span>
                            <i class="fas fa-tag me-1"></i>
                            <a href="<?php echo blogUrl(['category' => $post['category_id']]); ?>" class="text-decoration-none">
                                <?php echo htmlspecialchars($post['category_name'] ?? 'Travel'); ?>
                            </a>
                        </span>
                    </div>
                </header>

                <div class="post-content">
                    <?php if ($post['excerpt']): ?>
                        <div class="post-excerpt lead text-muted mb-4">
                            <?php echo htmlspecialchars($post['excerpt']); ?>
                        </div>
                    <?php endif; ?>

                    <div class="post-body">
                        <?php echo nl2br(htmlspecialchars($post['content'])); ?>
                    </div>
                </div>

                <footer class="post-footer mt-5">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <div class="post-tags">
                                <?php if (!empty($post['tags'])): ?>
                                    <strong>Tags:</strong>
                                    <?php 
                                    $tags = explode(',', $post['tags']);
                                    foreach ($tags as $tag): 
                                    ?>
                                        <span class="badge bg-secondary me-1"><?php echo trim(htmlspecialchars($tag)); ?></span>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <div class="social-share">
                                <strong>Share:</strong>
                                <a href="#" class="btn btn-sm btn-outline-primary ms-2">
                                    <i class="fab fa-facebook-f"></i>
                                </a>
                                <a href="#" class="btn btn-sm btn-outline-info ms-1">
                                    <i class="fab fa-twitter"></i>
                                </a>
                                <a href="#" class="btn btn-sm btn-outline-success ms-1">
                                    <i class="fab fa-whatsapp"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </footer>
            </article>

            <!-- Related Posts -->
            <?php if (!empty($related_posts)): ?>
                <section class="related-posts mt-5 pt-5 border-top">
                    <h3 class="mb-4">Related Posts</h3>
                    <div class="row">
                        <?php foreach ($related_posts as $related): ?>
                            <div class="col-md-4 mb-4">
                                <div class="card h-100 border-0 shadow-sm">
                                    <?php if ($related['featured_image']): ?>
                                        <img src="<?php echo htmlspecialchars($related['featured_image']); ?>" 
                                             class="card-img-top" 
                                             style="height: 200px; object-fit: cover;"
                                             alt="<?php echo htmlspecialchars($related['title']); ?>">
                                    <?php endif; ?>
                                    <div class="card-body">
                                        <h6 class="card-title">
                                            <a href="<?php echo blogPostUrl($related['slug']); ?>" class="text-decoration-none">
                                                <?php echo htmlspecialchars($related['title']); ?>
                                            </a>
                                        </h6>
                                        <p class="card-text text-muted small">
                                            <?php echo substr(htmlspecialchars($related['excerpt']), 0, 100); ?>...
                                        </p>
                                        <small class="text-muted">
                                            <?php echo date('M d, Y', strtotime($related['created_at'])); ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        </div>

        <div class="col-lg-4">
            <!-- Sidebar -->
            <aside class="blog-sidebar">
                <!-- Search -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h6 class="card-title">Search Blog</h6>
                        <form action="<?php echo navUrl('blog'); ?>" method="GET">
                            <div class="input-group">
                                <input type="text" name="search" class="form-control" placeholder="Search posts...">
                                <button class="btn btn-outline-primary" type="submit">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Recent Posts -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h6 class="card-title">Recent Posts</h6>
                        <?php
                        $recent_posts = $db->fetchAll("
                            SELECT title, slug, created_at FROM blog_posts 
                            WHERE status = 'published' AND id != ?
                            ORDER BY created_at DESC LIMIT 5
                        ", [$post['id']]);
                        
                        if ($recent_posts):
                        ?>
                            <ul class="list-unstyled">
                                <?php foreach ($recent_posts as $recent): ?>
                                    <li class="mb-2 pb-2 border-bottom">
                                        <a href="<?php echo blogPostUrl($recent['slug']); ?>" class="text-decoration-none">
                                            <?php echo htmlspecialchars($recent['title']); ?>
                                        </a>
                                        <br>
                                        <small class="text-muted">
                                            <?php echo date('M d, Y', strtotime($recent['created_at'])); ?>
                                        </small>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Categories -->
                <div class="card">
                    <div class="card-body">
                        <h6 class="card-title">Categories</h6>
                        <?php
                        $categories = $db->fetchAll("
                            SELECT DISTINCT category_name as name, category_id as id
                            FROM blog_posts 
                            WHERE status = 'published' AND category_name IS NOT NULL
                        ");
                        
                        if ($categories):
                        ?>
                            <ul class="list-unstyled">
                                <?php foreach ($categories as $category): ?>
                                    <li class="mb-1">
                                        <a href="<?php echo blogUrl(['category' => $category['id']]); ?>" class="text-decoration-none">
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

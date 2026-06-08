<?php
require_once '../config/config.php';
requireLogin();

// Handle blog post actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'delete':
                if (isset($_POST['post_id'])) {
                    $post_id = (int)$_POST['post_id'];
                    $db->execute("DELETE FROM blog_posts WHERE id = ?", [$post_id]);
                    header('Location: blog.php?deleted=1');
                    exit;
                }
                break;
                
            case 'toggle_status':
                if (isset($_POST['post_id'])) {
                    $post_id = (int)$_POST['post_id'];
                    $new_status = $_POST['status'] == 'published' ? 'draft' : 'published';
                    $db->execute("UPDATE blog_posts SET status = ? WHERE id = ?", [$new_status, $post_id]);
                    header('Location: blog.php?updated=1');
                    exit;
                }
                break;
                
            case 'bulk_delete':
                if (isset($_POST['selected_posts']) && is_array($_POST['selected_posts'])) {
                    $ids = array_map('intval', $_POST['selected_posts']);
                    $placeholders = str_repeat('?,', count($ids) - 1) . '?';
                    $db->execute("DELETE FROM blog_posts WHERE id IN ($placeholders)", $ids);
                    header('Location: blog.php?deleted=' . count($ids));
                    exit;
                }
                break;
        }
    }
}

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$category_filter = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';

// Build query conditions
$conditions = [];
$params = [];

if (!empty($status_filter)) {
    $conditions[] = "bp.status = ?";
    $params[] = $status_filter;
}

if (!empty($category_filter)) {
    $conditions[] = "bp.category_id = ?";
    $params[] = (int)$category_filter;
}

if (!empty($search)) {
    $conditions[] = "(bp.title LIKE ? OR bp.content LIKE ? OR bp.excerpt LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Get total count
$count_query = "SELECT COUNT(*) as total FROM blog_posts bp $where_clause";
$total_result = $db->fetch($count_query, $params);
$total_posts = $total_result['total'];
$total_pages = ceil($total_posts / $per_page);

// Get blog posts with category and author info
$query = "
    SELECT bp.*, bc.name as category_name, au.full_name as author_name
    FROM blog_posts bp 
    LEFT JOIN blog_categories bc ON bp.category_id = bc.id 
    LEFT JOIN admin_users au ON bp.author_id = au.id 
    $where_clause 
    ORDER BY bp.created_at DESC 
    LIMIT $per_page OFFSET $offset
";
$blog_posts = $db->fetchAll($query, $params);

// Get blog categories for filter
$categories = $db->fetchAll("SELECT * FROM blog_categories ORDER BY name");

// Get statistics
$stats = [
    'total' => $db->fetch("SELECT COUNT(*) as count FROM blog_posts")['count'],
    'published' => $db->fetch("SELECT COUNT(*) as count FROM blog_posts WHERE status = 'published'")['count'],
    'draft' => $db->fetch("SELECT COUNT(*) as count FROM blog_posts WHERE status = 'draft'")['count'],
    'featured' => $db->fetch("SELECT COUNT(*) as count FROM blog_posts WHERE featured = 1")['count']
];

$page_title = 'Blog Management';
include 'includes/header.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Blog Management</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Blog Posts</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            
            <?php if (isset($_GET['deleted'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-trash mr-2"></i>
                    <?php echo (int)$_GET['deleted']; ?> blog post(s) deleted successfully!
                    <button type="button" class="close" data-dismiss="alert">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['updated'])): ?>
                <div class="alert alert-info alert-dismissible fade show">
                    <i class="fas fa-check mr-2"></i>
                    Blog post updated successfully!
                    <button type="button" class="close" data-dismiss="alert">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-info">
                        <div class="inner">
                            <h3><?php echo $stats['total']; ?></h3>
                            <p>Total Posts</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-blog"></i>
                        </div>
                        <a href="?" class="small-box-footer">
                            View All <i class="fas fa-arrow-circle-right"></i>
                        </a>
                    </div>
                </div>
                
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-success">
                        <div class="inner">
                            <h3><?php echo $stats['published']; ?></h3>
                            <p>Published</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <a href="?status=published" class="small-box-footer">
                            View Published <i class="fas fa-arrow-circle-right"></i>
                        </a>
                    </div>
                </div>
                
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-warning">
                        <div class="inner">
                            <h3><?php echo $stats['draft']; ?></h3>
                            <p>Drafts</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-edit"></i>
                        </div>
                        <a href="?status=draft" class="small-box-footer">
                            View Drafts <i class="fas fa-arrow-circle-right"></i>
                        </a>
                    </div>
                </div>
                
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-danger">
                        <div class="inner">
                            <h3><?php echo $stats['featured']; ?></h3>
                            <p>Featured</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-star"></i>
                        </div>
                        <a href="#" class="small-box-footer">
                            Featured Posts <i class="fas fa-arrow-circle-right"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Add New Post Button -->
            <div class="row mb-3">
                <div class="col-12">
                    <div class="alert alert-info">
                        <h5><i class="fas fa-info-circle mr-2"></i>Blog Post Management</h5>
                        <p class="mb-2">This is a basic blog management interface. Currently you can:</p>
                        <ul class="mb-2">
                            <li>View all blog posts with filtering and search</li>
                            <li>Toggle post status (Published/Draft)</li>
                            <li>Delete posts individually or in bulk</li>
                            <li>View post statistics</li>
                        </ul>
                        <p class="mb-0">To add or edit blog posts, you can use the database directly or implement a full blog editor.</p>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Filter Blog Posts</h3>
                </div>
                <div class="card-body">
                    <form method="GET" action="">
                        <div class="row">
                            <div class="col-md-3">
                                <select name="status" class="form-control">
                                    <option value="">All Status</option>
                                    <option value="published" <?php echo $status_filter == 'published' ? 'selected' : ''; ?>>Published</option>
                                    <option value="draft" <?php echo $status_filter == 'draft' ? 'selected' : ''; ?>>Draft</option>
                                    <option value="archived" <?php echo $status_filter == 'archived' ? 'selected' : ''; ?>>Archived</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select name="category" class="form-control">
                                    <option value="">All Categories</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>" <?php echo $category_filter == $category['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <input type="text" name="search" class="form-control" placeholder="Search posts..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary btn-block">
                                    <i class="fas fa-search mr-1"></i> Filter
                                </button>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-12">
                                <a href="blog.php" class="btn btn-secondary btn-sm">
                                    <i class="fas fa-times mr-1"></i> Clear Filters
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Blog Posts Table -->
            <div class="card mt-3">
                <div class="card-header">
                    <h3 class="card-title">Blog Posts (<?php echo $total_posts; ?> total)</h3>
                </div>
                
                <form method="POST" action="">
                    <input type="hidden" name="action" value="bulk_delete">
                    
                    <div class="card-body table-responsive p-0">
                        <table class="table table-hover text-nowrap">
                            <thead>
                                <tr>
                                    <th><input type="checkbox" id="select-all"></th>
                                    <th>Title</th>
                                    <th>Category</th>
                                    <th>Author</th>
                                    <th>Status</th>
                                    <th>Featured</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($blog_posts)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-4">
                                            <i class="fas fa-blog fa-3x text-muted mb-3"></i>
                                            <p class="text-muted">No blog posts found.</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($blog_posts as $post): ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" name="selected_posts[]" value="<?php echo $post['id']; ?>">
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($post['title']); ?></strong>
                                                <br><small class="text-muted">
                                                    <?php echo $post['excerpt'] ? substr(htmlspecialchars($post['excerpt']), 0, 60) . '...' : 'No excerpt'; ?>
                                                </small>
                                            </td>
                                            <td>
                                                <span class="badge badge-info">
                                                    <?php echo htmlspecialchars($post['category_name'] ?: 'Uncategorized'); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small><?php echo htmlspecialchars($post['author_name'] ?: 'Unknown'); ?></small>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php 
                                                    echo $post['status'] == 'published' ? 'success' : 
                                                         ($post['status'] == 'draft' ? 'warning' : 'secondary'); 
                                                ?>">
                                                    <?php echo ucfirst($post['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($post['featured']): ?>
                                                    <i class="fas fa-star text-warning"></i>
                                                <?php else: ?>
                                                    <i class="far fa-star text-muted"></i>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small><?php echo date('M d, Y', strtotime($post['created_at'])); ?></small>
                                                <?php if ($post['published_at']): ?>
                                                    <br><small class="text-success">Published: <?php echo date('M d', strtotime($post['published_at'])); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <?php if ($post['slug']): ?>
                                                        <a href="../blog-post.php?slug=<?php echo urlencode($post['slug']); ?>" 
                                                           class="btn btn-info btn-sm" target="_blank" title="View Post">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    
                                                    <button type="button" class="btn btn-warning btn-sm" 
                                                            onclick="toggleStatus(<?php echo $post['id']; ?>, '<?php echo $post['status']; ?>')"
                                                            title="Toggle Status">
                                                        <i class="fas fa-toggle-<?php echo $post['status'] == 'published' ? 'on' : 'off'; ?>"></i>
                                                    </button>
                                                    
                                                    <button type="button" class="btn btn-danger btn-sm" 
                                                            onclick="deletePost(<?php echo $post['id']; ?>)" 
                                                            title="Delete Post">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if (!empty($blog_posts)): ?>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete selected posts?')">
                                <i class="fas fa-trash mr-1"></i> Delete Selected
                            </button>
                        </div>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="d-flex justify-content-center mt-3">
                    <nav aria-label="Blog posts pagination">
                        <ul class="pagination">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                </li>
                            <?php endif; ?>

                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>

        </div>
    </section>
</div>

<!-- Hidden forms for actions -->
<form id="status-form" method="POST" style="display: none;">
    <input type="hidden" name="action" value="toggle_status">
    <input type="hidden" name="post_id" id="status-post-id">
    <input type="hidden" name="status" id="status-current">
</form>

<form id="delete-form" method="POST" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="post_id" id="delete-post-id">
</form>

<script>
// Select all functionality
document.getElementById('select-all').addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('input[name="selected_posts[]"]');
    checkboxes.forEach(checkbox => {
        checkbox.checked = this.checked;
    });
});

// Auto-check select-all when all items are selected
document.querySelectorAll('input[name="selected_posts[]"]').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('input[name="selected_posts[]"]');
        const selectAll = document.getElementById('select-all');
        selectAll.checked = Array.from(checkboxes).every(cb => cb.checked);
    });
});

function toggleStatus(postId, currentStatus) {
    if (confirm('Are you sure you want to change the status of this post?')) {
        document.getElementById('status-post-id').value = postId;
        document.getElementById('status-current').value = currentStatus;
        document.getElementById('status-form').submit();
    }
}

function deletePost(postId) {
    if (confirm('Are you sure you want to delete this blog post? This action cannot be undone.')) {
        document.getElementById('delete-post-id').value = postId;
        document.getElementById('delete-form').submit();
    }
}
</script>

<?php include 'includes/footer.php'; ?>
<?php
require_once '../config/config.php';
requireLogin();

$success = '';
$errors = [];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $title = trim($_POST['title']);
                $subtitle = trim($_POST['subtitle']);
                $description = trim($_POST['description']);
                $sort_order = intval($_POST['sort_order']);
                
                // Handle file upload
                if (isset($_FILES['hero_image']) && $_FILES['hero_image']['error'] === 0) {
                    $uploadResult = uploadFile($_FILES['hero_image'], 'hero');
                    if ($uploadResult) {
                        $db->execute(
                            "INSERT INTO hero_images (title, subtitle, description, image_path, sort_order) VALUES (?, ?, ?, ?, ?)",
                            [$title, $subtitle, $description, $uploadResult, $sort_order]
                        );
                        $success = 'Hero image added successfully!';
                    } else {
                        $errors[] = 'Failed to upload image';
                    }
                } else {
                    $errors[] = 'Please select an image file';
                }
                break;
                
            case 'update_image':
                $id = intval($_POST['id'] ?? 0);
                $title = trim($_POST['title'] ?? '');
                $subtitle = trim($_POST['subtitle'] ?? '');

                if (!$id) {
                    $errors[] = 'Invalid hero image.';
                    break;
                }

                $hero = $db->fetch("SELECT * FROM hero_images WHERE id = ?", [$id]);
                if (!$hero) {
                    $errors[] = 'Hero image not found.';
                    break;
                }

                $image_path = $hero['image_path'];

                if (isset($_FILES['hero_image']) && $_FILES['hero_image']['error'] === UPLOAD_ERR_OK) {
                    $uploadResult = uploadFile($_FILES['hero_image'], 'hero');
                    if ($uploadResult) {
                        $oldPath = BASE_PATH . $hero['image_path'];
                        if (is_file($oldPath)) {
                            unlink($oldPath);
                        }
                        $image_path = $uploadResult;
                    } else {
                        $errors[] = 'Failed to upload replacement image.';
                    }
                }

                if (empty($errors)) {
                    $db->execute(
                        "UPDATE hero_images SET title = ?, subtitle = ?, image_path = ? WHERE id = ?",
                        [$title, $subtitle, $image_path, $id]
                    );
                    $success = 'Hero image updated successfully!';
                }
                break;

            case 'toggle_active':
                $id = intval($_POST['id']);
                $is_active = intval($_POST['is_active']);
                
                $db->execute("UPDATE hero_images SET is_active = ? WHERE id = ?", [$is_active, $id]);
                $success = 'Hero image status updated!';
                break;
                
            case 'delete':
                $id = intval($_POST['id']);
                $hero = $db->fetch("SELECT image_path FROM hero_images WHERE id = ?", [$id]);
                
                if ($hero) {
                    // Delete file if exists
                    $filePath = BASE_PATH . $hero['image_path'];
                    if (file_exists($filePath)) {
                        unlink($filePath);
                    }
                    
                    $db->execute("DELETE FROM hero_images WHERE id = ?", [$id]);
                    $success = 'Hero image deleted successfully!';
                }
                break;
                
            case 'update_order':
                if (isset($_POST['hero_orders'])) {
                    foreach ($_POST['hero_orders'] as $id => $order) {
                        $db->execute("UPDATE hero_images SET sort_order = ? WHERE id = ?", [intval($order), intval($id)]);
                    }
                    $success = 'Sort order updated successfully!';
                }
                break;
        }
    }
}

// Get all hero images
$hero_images = $db->fetchAll("SELECT * FROM hero_images ORDER BY sort_order ASC, created_at DESC");

include 'includes/header.php';
?>

<div class="content-wrapper">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Homepage Search Hero Backgrounds</h3>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-4">
                        Upload images here to show behind the homepage search section (“Luxury Options”).
                        All images rotate automatically. Recommended size: 1920×1080px landscape.
                    </p>
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>
                    
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <?php foreach ($errors as $error): ?>
                                <p><?php echo $error; ?></p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Add New Hero Image Form -->
                    <div class="mb-4">
                        <h4>Add Background Image</h4>
                        <form method="POST" enctype="multipart/form-data" class="border p-3">
                            <input type="hidden" name="action" value="add">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="title">Title</label>
                                        <input type="text" class="form-control" name="title" id="title" placeholder="Hero title">
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="sort_order">Sort Order</label>
                                        <input type="number" class="form-control" name="sort_order" id="sort_order" value="0">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="subtitle">Subtitle</label>
                                <input type="text" class="form-control" name="subtitle" id="subtitle" placeholder="Hero subtitle">
                            </div>
                            
                            <div class="form-group">
                                <label for="description">Description</label>
                                <textarea class="form-control" name="description" id="description" rows="3" placeholder="Hero description"></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="hero_image">Hero Image</label>
                                <input type="file" class="form-control" name="hero_image" id="hero_image" accept="image/*" required>
                                <small class="text-muted">Shown on homepage search hero. Use 1920×1080px or similar landscape.</small>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Add Hero Image</button>
                        </form>
                    </div>

                    <!-- Existing Hero Images -->
                    <div class="mt-4">
                        <h4>Existing Hero Images</h4>
                        
                        <?php if (empty($hero_images)): ?>
                            <p class="text-muted">No hero images found. Add one above.</p>
                        <?php else: ?>
                            
                            <!-- Sort Order Form -->
                            <form method="POST" class="mb-3">
                                <input type="hidden" name="action" value="update_order">
                                
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Image</th>
                                                <th>Title</th>
                                                <th>Subtitle</th>
                                                <th>Sort Order</th>
                                                <th>Update</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($hero_images as $hero): ?>
                                                <tr>
                                                    <td>
                                                        <img src="<?php echo BASE_URL . $hero['image_path']; ?>" 
                                                             alt="Hero Image" style="width: 100px; height: 60px; object-fit: cover;">
                                                    </td>
                                                    <td><?php echo htmlspecialchars($hero['title'] ?: 'No title'); ?></td>
                                                    <td><?php echo htmlspecialchars($hero['subtitle'] ?: 'No subtitle'); ?></td>
                                                    <td>
                                                        <input type="number" name="hero_orders[<?php echo $hero['id']; ?>]" 
                                                               value="<?php echo $hero['sort_order']; ?>" 
                                                               class="form-control" style="width: 80px;">
                                                    </td>
                                                    <td>
                                                        <form method="POST" enctype="multipart/form-data" class="d-flex flex-column gap-1" style="min-width:200px;">
                                                            <input type="hidden" name="action" value="update_image">
                                                            <input type="hidden" name="id" value="<?php echo $hero['id']; ?>">
                                                            <input type="text" name="title" class="form-control form-control-sm" value="<?php echo htmlspecialchars($hero['title'] ?: ''); ?>" placeholder="Title">
                                                            <input type="text" name="subtitle" class="form-control form-control-sm" value="<?php echo htmlspecialchars($hero['subtitle'] ?: ''); ?>" placeholder="Subtitle">
                                                            <input type="file" name="hero_image" class="form-control form-control-sm" accept="image/*">
                                                            <button type="submit" class="btn btn-sm btn-primary">Save / Replace Image</button>
                                                        </form>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group">
                                                            <!-- Delete -->
                                                            <form method="POST" style="display: inline;" 
                                                                  onsubmit="return confirm('Are you sure you want to delete this hero image?')">
                                                                <input type="hidden" name="action" value="delete">
                                                                <input type="hidden" name="id" value="<?php echo $hero['id']; ?>">
                                                                <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                                            </form>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <button type="submit" class="btn btn-info">Update Sort Order</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.hero-preview {
    position: relative;
    height: 200px;
    background-size: cover;
    background-position: center;
    border-radius: 8px;
    overflow: hidden;
}
.hero-preview-overlay {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: linear-gradient(transparent, rgba(0,0,0,0.7));
    color: white;
    padding: 20px;
}
</style>

<?php include 'includes/footer.php'; ?>

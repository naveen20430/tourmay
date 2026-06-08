<?php
require_once '../config/config.php';
requireLogin();

$errors = [];
$success = false;
$destination = null;

// Get destination ID
$destination_id = $_GET['id'] ?? 0;

if (!$destination_id) {
    header('Location: destinations.php');
    exit;
}

// Fetch destination data
$destination = $db->fetch("SELECT * FROM destinations WHERE id = ?", [$destination_id]);

if (!$destination) {
    header('Location: destinations.php?msg=notfound');
    exit;
}

// Handle form submission
if ($_POST) {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $short_description = trim($_POST['short_description'] ?? '');
    $country = trim($_POST['country'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $best_time_to_visit = trim($_POST['best_time_to_visit'] ?? '');
    $popular = isset($_POST['popular']) ? 1 : 0;
    $status = $_POST['status'] ?? 'active';
    $meta_title = trim($_POST['meta_title'] ?? '');
    $meta_description = trim($_POST['meta_description'] ?? '');
    
    // Validation
    if (empty($name)) $errors[] = 'Destination name is required';
    if (empty($description)) $errors[] = 'Description is required';
    if (empty($short_description)) $errors[] = 'Short description is required';
    if (empty($country)) $errors[] = 'Country is required';
    
    // Generate slug if name changed
    $slug = $destination['slug'];
    if ($name !== $destination['name']) {
        $slug = generateSlug($name);
        // Check if slug exists (excluding current destination)
        if ($slug) {
            $existing = $db->fetch("SELECT id FROM destinations WHERE slug = ? AND id != ?", [$slug, $destination_id]);
            if ($existing) {
                $slug = $slug . '-' . time();
            }
        }
    }
    
    // Handle file uploads
    $featured_image = $destination['featured_image'];
    $gallery_images = json_decode($destination['gallery'], true) ?: [];
    
    // Featured image upload
    if (!empty($_FILES['featured_image']['name'])) {
        $uploadErrors = getUploadError($_FILES['featured_image']);
        if (!empty($uploadErrors)) {
            foreach ($uploadErrors as $error) {
                $errors[] = 'Featured image: ' . $error;
            }
        } else {
            // Delete old featured image
            if ($featured_image && file_exists('../' . $featured_image)) {
                unlink('../' . $featured_image);
            }
            
            $upload_result = uploadFile($_FILES['featured_image'], 'destinations');
            if ($upload_result) {
                $featured_image = $upload_result;
            } else {
                $errors[] = 'Failed to upload featured image - server error';
            }
        }
    }
    
    // Gallery images upload
    if (!empty($_FILES['gallery_images']['name'][0])) {
        foreach ($_FILES['gallery_images']['tmp_name'] as $key => $tmp_name) {
            if (!empty($tmp_name)) {
                $file = [
                    'name' => $_FILES['gallery_images']['name'][$key],
                    'tmp_name' => $tmp_name,
                    'error' => $_FILES['gallery_images']['error'][$key],
                    'size' => $_FILES['gallery_images']['size'][$key],
                    'type' => $_FILES['gallery_images']['type'][$key]
                ];
                
                $uploadErrors = getUploadError($file);
                if (!empty($uploadErrors)) {
                    foreach ($uploadErrors as $error) {
                        $errors[] = 'Gallery image ' . ($key + 1) . ': ' . $error;
                    }
                } else {
                    $upload_result = uploadFile($file, 'destinations');
                    if ($upload_result) {
                        $gallery_images[] = $upload_result;
                    } else {
                        $errors[] = 'Failed to upload gallery image ' . ($key + 1) . ' - server error';
                    }
                }
            }
        }
    }
    
    // Handle gallery image deletion
    if (isset($_POST['delete_gallery'])) {
        foreach ($_POST['delete_gallery'] as $image_to_delete) {
            if (file_exists('../' . $image_to_delete)) {
                unlink('../' . $image_to_delete);
            }
            $gallery_images = array_filter($gallery_images, function($img) use ($image_to_delete) {
                return $img !== $image_to_delete;
            });
        }
        $gallery_images = array_values($gallery_images); // Re-index array
    }
    
    // Update destination if no errors
    if (empty($errors)) {
        try {
            $result = $db->execute(
                "UPDATE destinations SET 
                    name = ?, slug = ?, description = ?, short_description = ?, 
                    country = ?, city = ?, best_time_to_visit = ?, 
                    featured_image = ?, gallery = ?, popular = ?, status = ?, 
                    meta_title = ?, meta_description = ?, updated_at = NOW()
                 WHERE id = ?",
                [$name, $slug, $description, $short_description, $country, $city, 
                 $best_time_to_visit, $featured_image, json_encode($gallery_images), 
                 $popular, $status, $meta_title, $meta_description, $destination_id]
            );
            
            if ($result !== false) {
                header('Location: destinations.php?msg=updated');
                exit;
            } else {
                $errors[] = 'Failed to update destination. Please try again.';
            }
        } catch (Exception $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
    
    // Refresh destination data after update attempt
    $destination = $db->fetch("SELECT * FROM destinations WHERE id = ?", [$destination_id]);
    if ($destination && isset($featured_image)) {
        $destination['featured_image'] = $featured_image;
    }
    if ($destination && isset($gallery_images)) {
        $destination['gallery'] = json_encode($gallery_images);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Destination - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar { background: #2c3e50; min-height: 100vh; }
        .sidebar .nav-link { color: #bdc3c7; padding: 15px 20px; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { color: #fff; background: #34495e; }
        .main-content { background: #ecf0f1; min-height: 100vh; }
        .image-preview { max-width: 200px; max-height: 150px; object-fit: cover; border-radius: 0; margin: 5px; }
        .form-section { background: white; border-radius: 0; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .gallery-item { position: relative; display: inline-block; margin: 5px; }
        .gallery-item .delete-btn { position: absolute; top: 5px; right: 5px; background: rgba(220, 53, 69, 0.9); color: white; border: none; border-radius: 0; padding: 5px 10px; cursor: pointer; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 px-0 sidebar">
                <div class="p-3 text-center">
                    <h4 class="text-white"><?php echo getSetting('site_name'); ?></h4>
                    <small class="text-muted">Admin Panel</small>
                </div>
                <nav class="nav flex-column">
                    <a class="nav-link" href="index.php">
                        <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                    </a>
                    <a class="nav-link" href="tours.php">
                        <i class="fas fa-map-marked-alt me-2"></i> Tours
                    </a>
                    <a class="nav-link active" href="destinations.php">
                        <i class="fas fa-globe me-2"></i> Destinations
                    </a>
                    <a class="nav-link" href="bookings.php">
                        <i class="fas fa-calendar-check me-2"></i> Bookings
                    </a>
                    <a class="nav-link" href="blog.php">
                        <i class="fas fa-blog me-2"></i> Blog Posts
                    </a>
                    <a class="nav-link" href="users.php">
                        <i class="fas fa-users me-2"></i> Users
                    </a>
                    <a class="nav-link" href="settings.php">
                        <i class="fas fa-cog me-2"></i> Settings
                    </a>
                    <a class="nav-link" href="logout.php">
                        <i class="fas fa-sign-out-alt me-2"></i> Logout
                    </a>
                </nav>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-10 main-content">
                <!-- Header -->
                <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
                    <div class="container-fluid">
                        <span class="navbar-brand mb-0 h1">Edit Destination</span>
                        <div class="navbar-nav ms-auto">
                            <span class="nav-link">Welcome, <?php echo $_SESSION['admin_name']; ?>!</span>
                        </div>
                    </div>
                </nav>
                
                <!-- Content -->
                <div class="p-4">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <h6><i class="fas fa-exclamation-triangle me-2"></i>Please fix the following errors:</h6>
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" enctype="multipart/form-data">
                        <!-- Basic Information -->
                        <div class="form-section">
                            <h4><i class="fas fa-info-circle me-2"></i>Basic Information</h4>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="name" class="form-label">Destination Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($_POST['name'] ?? $destination['name']); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="country" class="form-label">Country <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="country" name="country" value="<?php echo htmlspecialchars($_POST['country'] ?? $destination['country']); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="city" class="form-label">City</label>
                                        <input type="text" class="form-control" id="city" name="city" value="<?php echo htmlspecialchars($_POST['city'] ?? $destination['city'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="best_time_to_visit" class="form-label">Best Time to Visit</label>
                                        <input type="text" class="form-control" id="best_time_to_visit" name="best_time_to_visit" 
                                               value="<?php echo htmlspecialchars($_POST['best_time_to_visit'] ?? $destination['best_time_to_visit'] ?? ''); ?>" 
                                               placeholder="e.g., April to October">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Description -->
                        <div class="form-section">
                            <h4><i class="fas fa-align-left me-2"></i>Description</h4>
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="mb-3">
                                        <label for="short_description" class="form-label">Short Description <span class="text-danger">*</span></label>
                                        <textarea class="form-control" id="short_description" name="short_description" rows="3" required><?php echo htmlspecialchars($_POST['short_description'] ?? $destination['short_description']); ?></textarea>
                                        <small class="form-text text-muted">Brief description shown in listings (around 150 characters)</small>
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <div class="mb-3">
                                        <label for="description" class="form-label">Full Description <span class="text-danger">*</span></label>
                                        <textarea class="form-control" id="description" name="description" rows="8" required><?php echo htmlspecialchars($_POST['description'] ?? $destination['description']); ?></textarea>
                                        <small class="form-text text-muted">Detailed description of the destination</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Images -->
                        <div class="form-section">
                            <h4><i class="fas fa-images me-2"></i>Images</h4>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="featured_image" class="form-label">Featured Image</label>
                                        <?php if ($destination['featured_image']): ?>
                                            <div class="mb-2">
                                                <img src="../<?php echo htmlspecialchars($destination['featured_image']); ?>" class="image-preview" alt="Current featured image">
                                                <p class="text-muted small">Current featured image</p>
                                            </div>
                                        <?php endif; ?>
                                        <input type="file" class="form-control" id="featured_image" name="featured_image" accept="image/*" onchange="previewImage(this, 'featured-preview')">
                                        <small class="form-text text-muted">Upload new image to replace current one (recommended: 800x600px)</small>
                                        <div id="featured-preview" class="mt-2"></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="gallery_images" class="form-label">Add Gallery Images</label>
                                        <input type="file" class="form-control" id="gallery_images" name="gallery_images[]" accept="image/*" multiple onchange="previewMultipleImages(this, 'gallery-preview')">
                                        <small class="form-text text-muted">Add additional images to the gallery</small>
                                        <div id="gallery-preview" class="mt-2"></div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Existing Gallery Images -->
                            <?php 
                            $existing_gallery = json_decode($destination['gallery'], true) ?: [];
                            if (!empty($existing_gallery)): 
                            ?>
                            <div class="row mt-3">
                                <div class="col-md-12">
                                    <label class="form-label">Current Gallery Images</label>
                                    <div class="d-flex flex-wrap">
                                        <?php foreach ($existing_gallery as $gallery_image): ?>
                                            <div class="gallery-item">
                                                <img src="../<?php echo htmlspecialchars($gallery_image); ?>" class="image-preview" alt="Gallery image">
                                                <button type="button" class="delete-btn" onclick="deleteGalleryImage(this, '<?php echo htmlspecialchars($gallery_image); ?>')">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                                <input type="hidden" name="delete_gallery[]" value="<?php echo htmlspecialchars($gallery_image); ?>" class="delete-input" disabled>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Settings -->
                        <div class="form-section">
                            <h4><i class="fas fa-cog me-2"></i>Settings</h4>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="status" class="form-label">Status</label>
                                        <select class="form-control" id="status" name="status">
                                            <option value="active" <?php echo ($_POST['status'] ?? $destination['status']) === 'active' ? 'selected' : ''; ?>>Active</option>
                                            <option value="inactive" <?php echo ($_POST['status'] ?? $destination['status']) === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <div class="form-check form-switch mt-4">
                                            <input class="form-check-input" type="checkbox" id="popular" name="popular" value="1" <?php echo ($_POST['popular'] ?? $destination['popular']) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="popular">Mark as Popular Destination</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- SEO -->
                        <div class="form-section">
                            <h4><i class="fas fa-search me-2"></i>SEO Meta Data</h4>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="meta_title" class="form-label">Meta Title</label>
                                        <input type="text" class="form-control" id="meta_title" name="meta_title" value="<?php echo htmlspecialchars($_POST['meta_title'] ?? $destination['meta_title'] ?? ''); ?>" maxlength="60">
                                        <small class="form-text text-muted">SEO title (recommended: under 60 characters)</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="meta_description" class="form-label">Meta Description</label>
                                        <textarea class="form-control" id="meta_description" name="meta_description" rows="3" maxlength="160"><?php echo htmlspecialchars($_POST['meta_description'] ?? $destination['meta_description'] ?? ''); ?></textarea>
                                        <small class="form-text text-muted">SEO description (recommended: under 160 characters)</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Submit Buttons -->
                        <div class="form-section">
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Update Destination
                                </button>
                                <a href="destinations.php" class="btn btn-secondary">
                                    <i class="fas fa-times me-2"></i>Cancel
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function previewImage(input, previewId) {
            const preview = document.getElementById(previewId);
            preview.innerHTML = '';
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.className = 'image-preview';
                    preview.appendChild(img);
                };
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        function previewMultipleImages(input, previewId) {
            const preview = document.getElementById(previewId);
            preview.innerHTML = '';
            
            if (input.files) {
                Array.from(input.files).forEach(file => {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.className = 'image-preview';
                        preview.appendChild(img);
                    };
                    reader.readAsDataURL(file);
                });
            }
        }
        
        function deleteGalleryImage(btn, imagePath) {
            if (confirm('Are you sure you want to delete this image?')) {
                const galleryItem = btn.closest('.gallery-item');
                const deleteInput = galleryItem.querySelector('.delete-input');
                deleteInput.disabled = false;
                galleryItem.style.opacity = '0.5';
                btn.style.display = 'none';
            }
        }
    </script>
</body>
</html>


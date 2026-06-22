<?php
require_once '../config/config.php';
require_once '../includes/html_helpers.php';
requireLogin();

$errors = [];
$success = false;

if ($_POST) {
    // Debug: Check if files were received
    if (!empty($_FILES['featured_image']['name'])) {
        error_log("Featured image received: " . $_FILES['featured_image']['name']);
    }
    if (!empty($_FILES['gallery_images']['name'][0])) {
        error_log("Gallery images received: " . count($_FILES['gallery_images']['name']));
    }
    
    $title = trim($_POST['title'] ?? '');
    $destination_id = $_POST['destination_id'] ?? '';
    $description = sanitizeRichTextHtml(trim($_POST['description'] ?? ''));
    $short_description = trim($_POST['short_description'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $discount_price = $_POST['discount_price'] ? floatval($_POST['discount_price']) : null;
    $duration_days = intval($_POST['duration_days'] ?? 0);
    $duration_nights = intval($_POST['duration_nights'] ?? 0);
    $max_people = intval($_POST['max_people'] ?? 0);
    $min_people = intval($_POST['min_people'] ?? 1);
    $difficulty_level = $_POST['difficulty_level'] ?? 'moderate';
    $tour_type = $_POST['tour_type'] ?? 'cultural';
    $featured = isset($_POST['featured']) ? 1 : 0;
    $popular = isset($_POST['popular']) ? 1 : 0;
    $status = $_POST['status'] ?? 'active';
    $availability_start = $_POST['availability_start'] ?? null;
    $availability_end = $_POST['availability_end'] ?? null;
    
    // Inclusions and exclusions
    $inclusions = array_filter(array_map('trim', $_POST['inclusions'] ?? []));
    $exclusions = array_filter(array_map('trim', $_POST['exclusions'] ?? []));
    
    // Itinerary
    $itinerary = [];
    if (!empty($_POST['itinerary_day'])) {
        foreach ($_POST['itinerary_day'] as $index => $day) {
            if (!empty($day) && !empty($_POST['itinerary_title'][$index])) {
                $itinerary[] = [
                    'day' => intval($day),
                    'title' => trim($_POST['itinerary_title'][$index]),
                    'description' => trim($_POST['itinerary_description'][$index] ?? '')
                ];
            }
        }
    }
    
    // Validation
    if (empty($title)) $errors[] = 'Tour title is required';
    if (!richTextHasContent($description)) $errors[] = 'Tour description is required';
    if (empty($short_description)) $errors[] = 'Short description is required';
    if ($price <= 0) $errors[] = 'Price must be greater than 0';
    if ($duration_days <= 0) $errors[] = 'Duration days must be greater than 0';
    if ($max_people <= 0) $errors[] = 'Maximum people must be greater than 0';
    
    // Generate slug
    $slug = generateSlug($title);
    
    // Check if slug exists
    if ($slug) {
        $existing = $db->fetch("SELECT id FROM tours WHERE slug = ?", [$slug]);
        if ($existing) {
            $slug = $slug . '-' . time();
        }
    }
    
    // Handle file uploads
    $featured_image = '';
    $gallery_images = [];
    
    // Featured image upload
    if (!empty($_FILES['featured_image']['name'])) {
        $uploadErrors = getUploadError($_FILES['featured_image']);
        if (!empty($uploadErrors)) {
            foreach ($uploadErrors as $error) {
                $errors[] = 'Featured image: ' . $error;
            }
        } else {
            $upload_result = uploadFile($_FILES['featured_image'], 'tours');
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
                    $upload_result = uploadFile($file, 'tours');
                    if ($upload_result) {
                        $gallery_images[] = $upload_result;
                    } else {
                        $errors[] = 'Failed to upload gallery image ' . ($key + 1) . ' - server error';
                    }
                }
            }
        }
    }
    
    // Create tour if no errors
    if (empty($errors)) {
        try {
            $tour_id = $db->execute(
                "INSERT INTO tours (title, slug, destination_id, description, short_description, price, discount_price, duration_days, duration_nights, max_people, min_people, featured_image, gallery, inclusions, exclusions, itinerary, difficulty_level, tour_type, featured, popular, status, availability_start, availability_end, created_at) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                [$title, $slug, $destination_id ?: null, $description, $short_description, $price, $discount_price, $duration_days, $duration_nights, $max_people, $min_people, $featured_image, json_encode($gallery_images), json_encode($inclusions), json_encode($exclusions), json_encode($itinerary), $difficulty_level, $tour_type, $featured, $popular, $status, $availability_start ?: null, $availability_end ?: null]
            );
            
            if ($tour_id) {
                header('Location: tours.php?msg=added');
                exit;
            } else {
                $errors[] = 'Failed to create tour. Please try again.';
            }
        } catch (Exception $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

// Get destinations for dropdown
$destinations = $db->fetchAll("SELECT * FROM destinations WHERE status = 'active' ORDER BY name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Tour - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar { background: #2c3e50; min-height: 100vh; }
        .sidebar .nav-link { color: #bdc3c7; padding: 15px 20px; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { color: #fff; background: #34495e; }
        .main-content { background: #ecf0f1; min-height: 100vh; }
        .image-preview { max-width: 200px; max-height: 150px; object-fit: cover; border-radius: 8px; margin: 5px; }
        .itinerary-item { border: 1px solid #ddd; padding: 15px; margin-bottom: 10px; border-radius: 8px; background: #f9f9f9; }
        .remove-btn { background: #dc3545; color: white; border: none; border-radius: 50%; width: 25px; height: 25px; font-size: 12px; }
        .tour-description-editor-wrap .ck-editor {
            border-radius: 0.375rem;
            overflow: hidden;
        }
        .tour-description-editor-wrap .ck-editor__editable {
            min-height: 320px;
        }
        .tour-description-editor-wrap .ck.ck-editor__main > .ck-editor__editable:not(.ck-focused) {
            border-color: #ced4da;
        }
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
                    <a class="nav-link active" href="tours.php">
                        <i class="fas fa-map-marked-alt me-2"></i> Tours
                    </a>
                    <a class="nav-link" href="destinations.php">
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
                        <span class="navbar-brand mb-0 h1">Add New Tour</span>
                        <div class="navbar-nav ms-auto">
                            <a href="tours.php" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-arrow-left me-1"></i>Back to Tours
                            </a>
                        </div>
                    </div>
                </nav>
                
                <!-- Content -->
                <div class="p-4">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <h5><i class="fas fa-exclamation-circle me-2"></i>Please fix the following errors:</h5>
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <div class="card">
                        <div class="card-body">
                            <form method="POST" enctype="multipart/form-data">
                                <!-- Basic Information -->
                                <div class="row mb-4">
                                    <div class="col-12">
                                        <h5 class="card-title border-bottom pb-2">Basic Information</h5>
                                    </div>
                                    <div class="col-md-8 mb-3">
                                        <label class="form-label">Tour Title *</label>
                                        <input type="text" name="title" class="form-control" required
                                               value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Destination</label>
                                        <select name="destination_id" class="form-select">
                                            <option value="">Select Destination</option>
                                            <?php foreach ($destinations as $dest): ?>
                                                <option value="<?php echo $dest['id']; ?>" <?php echo ($_POST['destination_id'] ?? '') == $dest['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($dest['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="row mb-4">
                                    <div class="col-md-12 mb-3">
                                        <label class="form-label">Short Description *</label>
                                        <textarea name="short_description" class="form-control" rows="2" required><?php echo htmlspecialchars($_POST['short_description'] ?? ''); ?></textarea>
                                    </div>
                                    <div class="col-md-12 mb-3 tour-description-editor-wrap">
                                        <label class="form-label" for="tour-description-editor">Full Description *</label>
                                        <textarea id="tour-description-editor" name="description" class="form-control" rows="10"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                                        <small class="text-muted">Use the toolbar for bold text, font size, and lists.</small>
                                    </div>
                                </div>
                                
                                <!-- Pricing & Duration -->
                                <div class="row mb-4">
                                    <div class="col-12">
                                        <h5 class="card-title border-bottom pb-2">Pricing & Duration</h5>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Regular Price (₹) *</label>
                                        <input type="number" name="price" class="form-control" step="1" required
                                               placeholder="Enter price in INR"
                                               value="<?php echo $_POST['price'] ?? ''; ?>">
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Discount Price (₹)</label>
                                        <input type="number" name="discount_price" class="form-control" step="1"
                                               placeholder="Enter discount price in INR"
                                               value="<?php echo $_POST['discount_price'] ?? ''; ?>">
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Duration (Days) *</label>
                                        <input type="number" name="duration_days" class="form-control" required
                                               value="<?php echo $_POST['duration_days'] ?? ''; ?>">
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Duration (Nights) *</label>
                                        <input type="number" name="duration_nights" class="form-control" required
                                               value="<?php echo $_POST['duration_nights'] ?? ''; ?>">
                                    </div>
                                </div>
                                
                                <!-- Capacity & Details -->
                                <div class="row mb-4">
                                    <div class="col-12">
                                        <h5 class="card-title border-bottom pb-2">Capacity & Details</h5>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Max People *</label>
                                        <input type="number" name="max_people" class="form-control" required
                                               value="<?php echo $_POST['max_people'] ?? ''; ?>">
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Min People</label>
                                        <input type="number" name="min_people" class="form-control"
                                               value="<?php echo $_POST['min_people'] ?? 1; ?>">
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Difficulty Level</label>
                                        <select name="difficulty_level" class="form-select">
                                            <option value="easy" <?php echo ($_POST['difficulty_level'] ?? '') === 'easy' ? 'selected' : ''; ?>>Easy</option>
                                            <option value="moderate" <?php echo ($_POST['difficulty_level'] ?? 'moderate') === 'moderate' ? 'selected' : ''; ?>>Moderate</option>
                                            <option value="difficult" <?php echo ($_POST['difficulty_level'] ?? '') === 'difficult' ? 'selected' : ''; ?>>Difficult</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Tour Type</label>
                                        <select name="tour_type" class="form-select">
                                            <option value="adventure" <?php echo ($_POST['tour_type'] ?? '') === 'adventure' ? 'selected' : ''; ?>>Adventure</option>
                                            <option value="cultural" <?php echo ($_POST['tour_type'] ?? 'cultural') === 'cultural' ? 'selected' : ''; ?>>Cultural</option>
                                            <option value="wildlife" <?php echo ($_POST['tour_type'] ?? '') === 'wildlife' ? 'selected' : ''; ?>>Wildlife</option>
                                            <option value="beach" <?php echo ($_POST['tour_type'] ?? '') === 'beach' ? 'selected' : ''; ?>>Beach</option>
                                            <option value="mountain" <?php echo ($_POST['tour_type'] ?? '') === 'mountain' ? 'selected' : ''; ?>>Mountain</option>
                                            <option value="city" <?php echo ($_POST['tour_type'] ?? '') === 'city' ? 'selected' : ''; ?>>City</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <!-- Images -->
                                <div class="row mb-4">
                                    <div class="col-12">
                                        <h5 class="card-title border-bottom pb-2">Images</h5>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Featured Image</label>
                                        <input type="file" name="featured_image" class="form-control" accept="image/*">
                                        <small class="text-muted">Main image for the tour (JPG, PNG, max 5MB)</small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Gallery Images</label>
                                        <input type="file" name="gallery_images[]" class="form-control" accept="image/*" multiple>
                                        <small class="text-muted">Additional images (JPG, PNG, max 5MB each)</small>
                                    </div>
                                </div>
                                
                                <!-- Inclusions -->
                                <div class="row mb-4">
                                    <div class="col-12">
                                        <h5 class="card-title border-bottom pb-2">What's Included</h5>
                                    </div>
                                    <div class="col-12">
                                        <div id="inclusions-container">
                                            <?php if (!empty($_POST['inclusions'])): ?>
                                                <?php foreach ($_POST['inclusions'] as $inclusion): ?>
                                                    <div class="input-group mb-2">
                                                        <input type="text" name="inclusions[]" class="form-control" value="<?php echo htmlspecialchars($inclusion); ?>">
                                                        <button type="button" class="btn btn-outline-danger btn-sm remove-inclusion">Remove</button>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <div class="input-group mb-2">
                                                    <input type="text" name="inclusions[]" class="form-control" placeholder="e.g., Hotel pickup and drop-off">
                                                    <button type="button" class="btn btn-outline-danger btn-sm remove-inclusion">Remove</button>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <button type="button" class="btn btn-outline-primary btn-sm" id="add-inclusion">Add Inclusion</button>
                                    </div>
                                </div>
                                
                                <!-- Exclusions -->
                                <div class="row mb-4">
                                    <div class="col-12">
                                        <h5 class="card-title border-bottom pb-2">What's Not Included</h5>
                                    </div>
                                    <div class="col-12">
                                        <div id="exclusions-container">
                                            <?php if (!empty($_POST['exclusions'])): ?>
                                                <?php foreach ($_POST['exclusions'] as $exclusion): ?>
                                                    <div class="input-group mb-2">
                                                        <input type="text" name="exclusions[]" class="form-control" value="<?php echo htmlspecialchars($exclusion); ?>">
                                                        <button type="button" class="btn btn-outline-danger btn-sm remove-exclusion">Remove</button>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <div class="input-group mb-2">
                                                    <input type="text" name="exclusions[]" class="form-control" placeholder="e.g., International flights">
                                                    <button type="button" class="btn btn-outline-danger btn-sm remove-exclusion">Remove</button>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <button type="button" class="btn btn-outline-primary btn-sm" id="add-exclusion">Add Exclusion</button>
                                    </div>
                                </div>
                                
                                <!-- Itinerary -->
                                <div class="row mb-4">
                                    <div class="col-12">
                                        <h5 class="card-title border-bottom pb-2">Tour Itinerary</h5>
                                    </div>
                                    <div class="col-12">
                                        <div id="itinerary-container">
                                            <?php if (!empty($_POST['itinerary_day'])): ?>
                                                <?php foreach ($_POST['itinerary_day'] as $index => $day): ?>
                                                    <div class="itinerary-item">
                                                        <div class="row">
                                                            <div class="col-md-2">
                                                                <label class="form-label">Day</label>
                                                                <input type="number" name="itinerary_day[]" class="form-control" value="<?php echo htmlspecialchars($day); ?>">
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label">Title</label>
                                                                <input type="text" name="itinerary_title[]" class="form-control" value="<?php echo htmlspecialchars($_POST['itinerary_title'][$index] ?? ''); ?>">
                                                            </div>
                                                            <div class="col-md-5">
                                                                <label class="form-label">Description</label>
                                                                <textarea name="itinerary_description[]" class="form-control" rows="2"><?php echo htmlspecialchars($_POST['itinerary_description'][$index] ?? ''); ?></textarea>
                                                            </div>
                                                            <div class="col-md-1 d-flex align-items-end">
                                                                <button type="button" class="remove-btn remove-itinerary">×</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <div class="itinerary-item">
                                                    <div class="row">
                                                        <div class="col-md-2">
                                                            <label class="form-label">Day</label>
                                                            <input type="number" name="itinerary_day[]" class="form-control" value="1">
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="form-label">Title</label>
                                                            <input type="text" name="itinerary_title[]" class="form-control" placeholder="e.g., Arrival & Check-in">
                                                        </div>
                                                        <div class="col-md-5">
                                                            <label class="form-label">Description</label>
                                                            <textarea name="itinerary_description[]" class="form-control" rows="2" placeholder="Describe what happens on this day"></textarea>
                                                        </div>
                                                        <div class="col-md-1 d-flex align-items-end">
                                                            <button type="button" class="remove-btn remove-itinerary">×</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <button type="button" class="btn btn-outline-primary btn-sm" id="add-itinerary">Add Day</button>
                                    </div>
                                </div>
                                
                                <!-- Availability -->
                                <div class="row mb-4">
                                    <div class="col-12">
                                        <h5 class="card-title border-bottom pb-2">Availability & Status</h5>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Available From</label>
                                        <input type="date" name="availability_start" class="form-control"
                                               value="<?php echo $_POST['availability_start'] ?? ''; ?>">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Available Until</label>
                                        <input type="date" name="availability_end" class="form-control"
                                               value="<?php echo $_POST['availability_end'] ?? ''; ?>">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Status</label>
                                        <select name="status" class="form-select">
                                            <option value="active" <?php echo ($_POST['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
                                            <option value="inactive" <?php echo ($_POST['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <!-- Options -->
                                <div class="row mb-4">
                                    <div class="col-12">
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="checkbox" name="featured" id="featured" 
                                                   <?php echo !empty($_POST['featured']) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="featured">Featured Tour</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="checkbox" name="popular" id="popular" 
                                                   <?php echo !empty($_POST['popular']) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="popular">Popular Tour</label>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-12">
                                        <button type="submit" class="btn btn-primary btn-lg me-3">
                                            <i class="fas fa-save me-2"></i>Create Tour
                                        </button>
                                        <a href="tours.php" class="btn btn-secondary btn-lg">
                                            <i class="fas fa-times me-2"></i>Cancel
                                        </a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add inclusions
        document.getElementById('add-inclusion').addEventListener('click', function() {
            const container = document.getElementById('inclusions-container');
            const div = document.createElement('div');
            div.className = 'input-group mb-2';
            div.innerHTML = `
                <input type="text" name="inclusions[]" class="form-control" placeholder="e.g., Professional tour guide">
                <button type="button" class="btn btn-outline-danger btn-sm remove-inclusion">Remove</button>
            `;
            container.appendChild(div);
        });
        
        // Remove inclusions
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-inclusion')) {
                e.target.closest('.input-group').remove();
            }
        });
        
        // Add exclusions
        document.getElementById('add-exclusion').addEventListener('click', function() {
            const container = document.getElementById('exclusions-container');
            const div = document.createElement('div');
            div.className = 'input-group mb-2';
            div.innerHTML = `
                <input type="text" name="exclusions[]" class="form-control" placeholder="e.g., Personal expenses">
                <button type="button" class="btn btn-outline-danger btn-sm remove-exclusion">Remove</button>
            `;
            container.appendChild(div);
        });
        
        // Remove exclusions
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-exclusion')) {
                e.target.closest('.input-group').remove();
            }
        });
        
        // Add itinerary
        document.getElementById('add-itinerary').addEventListener('click', function() {
            const container = document.getElementById('itinerary-container');
            const items = container.children.length;
            const div = document.createElement('div');
            div.className = 'itinerary-item';
            div.innerHTML = `
                <div class="row">
                    <div class="col-md-2">
                        <label class="form-label">Day</label>
                        <input type="number" name="itinerary_day[]" class="form-control" value="${items + 1}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Title</label>
                        <input type="text" name="itinerary_title[]" class="form-control" placeholder="e.g., City Tour">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Description</label>
                        <textarea name="itinerary_description[]" class="form-control" rows="2" placeholder="Describe what happens on this day"></textarea>
                    </div>
                    <div class="col-md-1 d-flex align-items-end">
                        <button type="button" class="remove-btn remove-itinerary">×</button>
                    </div>
                </div>
            `;
            container.appendChild(div);
        });
        
        // Remove itinerary
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-itinerary')) {
                e.target.closest('.itinerary-item').remove();
            }
        });
        
        // Auto-calculate nights from days
        document.querySelector('input[name="duration_days"]').addEventListener('input', function() {
            const days = parseInt(this.value) || 0;
            const nightsField = document.querySelector('input[name="duration_nights"]');
            if (days > 0) {
                nightsField.value = days - 1;
            }
        });
    </script>
    <script src="https://cdn.ckeditor.com/ckeditor5/41.4.2/super-build/ckeditor.js"></script>
    <script src="assets/js/tour-description-editor.js?v=2"></script>
</body>
</html>

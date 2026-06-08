<?php
require_once '../config/config.php';
requireLogin();

$success = '';
$errors = [];
$debug = [];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $title = trim($_POST['title']);
                $subtitle = trim($_POST['subtitle']);
                $description = trim($_POST['description']);
                $sort_order = intval($_POST['sort_order']);
                
                $debug[] = "Form data received: Title='$title', Subtitle='$subtitle'";
                $debug[] = "Upload directory: " . BASE_PATH . 'assets/images/hero/';
                $debug[] = "Directory writable: " . (is_writable(BASE_PATH . 'assets/images/hero/') ? 'YES' : 'NO');
                
                // Debug file upload
                if (isset($_FILES['hero_image'])) {
                    $debug[] = "File upload data: " . print_r($_FILES['hero_image'], true);
                    
                    if ($_FILES['hero_image']['error'] === 0) {
                        $debug[] = "No upload error detected";
                        
                        // Check file details
                        $debug[] = "File name: " . $_FILES['hero_image']['name'];
                        $debug[] = "File size: " . $_FILES['hero_image']['size'] . " bytes";
                        $debug[] = "File type: " . $_FILES['hero_image']['type'];
                        $debug[] = "Temp name: " . $_FILES['hero_image']['tmp_name'];
                        
                        // Check upload errors
                        $uploadErrors = getUploadError($_FILES['hero_image']);
                        if (!empty($uploadErrors)) {
                            $debug[] = "Upload validation errors: " . implode(', ', $uploadErrors);
                            $errors = array_merge($errors, $uploadErrors);
                        } else {
                            $debug[] = "No upload validation errors";
                            
                            $uploadResult = uploadFile($_FILES['hero_image'], 'hero');
                            $debug[] = "Upload result: " . ($uploadResult ? $uploadResult : 'FALSE');
                            
                            if ($uploadResult) {
                                try {
                                    $result = $db->execute(
                                        "INSERT INTO hero_images (title, subtitle, description, image_path, sort_order) VALUES (?, ?, ?, ?, ?)",
                                        [$title, $subtitle, $description, $uploadResult, $sort_order]
                                    );
                                    $debug[] = "Database insert result: " . ($result ? 'SUCCESS' : 'FAILED');
                                    $success = 'Hero image added successfully!';
                                } catch (Exception $e) {
                                    $errors[] = 'Database error: ' . $e->getMessage();
                                    $debug[] = "Database exception: " . $e->getMessage();
                                }
                            } else {
                                $errors[] = 'Failed to upload image - check debug info below';
                            }
                        }
                    } else {
                        $debug[] = "File upload error code: " . $_FILES['hero_image']['error'];
                        switch ($_FILES['hero_image']['error']) {
                            case UPLOAD_ERR_INI_SIZE:
                                $errors[] = 'File is too large (php.ini limit)';
                                break;
                            case UPLOAD_ERR_FORM_SIZE:
                                $errors[] = 'File is too large (form limit)';
                                break;
                            case UPLOAD_ERR_PARTIAL:
                                $errors[] = 'File upload was interrupted';
                                break;
                            case UPLOAD_ERR_NO_FILE:
                                $errors[] = 'No file was selected';
                                break;
                            case UPLOAD_ERR_NO_TMP_DIR:
                                $errors[] = 'Server error: no temp directory';
                                break;
                            case UPLOAD_ERR_CANT_WRITE:
                                $errors[] = 'Server error: cannot write file';
                                break;
                            default:
                                $errors[] = 'Unknown upload error: ' . $_FILES['hero_image']['error'];
                        }
                    }
                } else {
                    $errors[] = 'No file data received';
                    $debug[] = "No _FILES['hero_image'] data";
                }
                break;
        }
    }
}

// Check directory permissions and setup
$heroDir = BASE_PATH . 'assets/images/hero/';
$debug[] = "Hero directory path: $heroDir";
$debug[] = "Directory exists: " . (is_dir($heroDir) ? 'YES' : 'NO');
$debug[] = "Directory writable: " . (is_writable($heroDir) ? 'YES' : 'NO');
$debug[] = "BASE_PATH: " . BASE_PATH;
$debug[] = "BASE_URL: " . BASE_URL;

// Check PHP settings
$debug[] = "PHP upload_max_filesize: " . ini_get('upload_max_filesize');
$debug[] = "PHP post_max_size: " . ini_get('post_max_size');
$debug[] = "PHP max_file_uploads: " . ini_get('max_file_uploads');

// Get all hero images
try {
    $hero_images = $db->fetchAll("SELECT * FROM hero_images ORDER BY sort_order ASC, created_at DESC");
    $debug[] = "Hero images found: " . count($hero_images);
} catch (Exception $e) {
    $hero_images = [];
    $debug[] = "Database error fetching hero images: " . $e->getMessage();
}

include 'includes/header.php';
?>

<div class="content-wrapper">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Hero Images Management - DEBUG MODE</h3>
                </div>
                <div class="card-body">
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>
                    
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <h5>Upload Errors:</h5>
                            <?php foreach ($errors as $error): ?>
                                <p><?php echo $error; ?></p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($debug)): ?>
                        <div class="alert alert-info">
                            <h5>Debug Information:</h5>
                            <?php foreach ($debug as $info): ?>
                                <p><code><?php echo htmlspecialchars($info); ?></code></p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Add New Hero Image Form -->
                    <div class="mb-4">
                        <h4>Add New Hero Image (Debug Version)</h4>
                        <form method="POST" enctype="multipart/form-data" class="border p-3">
                            <input type="hidden" name="action" value="add">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="title">Title</label>
                                        <input type="text" class="form-control" name="title" id="title" placeholder="Hero title">
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="sort_order">Sort Order</label>
                                        <input type="number" class="form-control" name="sort_order" id="sort_order" value="0">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group mb-3">
                                <label for="subtitle">Subtitle</label>
                                <input type="text" class="form-control" name="subtitle" id="subtitle" placeholder="Hero subtitle">
                            </div>
                            
                            <div class="form-group mb-3">
                                <label for="description">Description</label>
                                <textarea class="form-control" name="description" id="description" rows="3" placeholder="Hero description"></textarea>
                            </div>
                            
                            <div class="form-group mb-3">
                                <label for="hero_image">Hero Image</label>
                                <input type="file" class="form-control" name="hero_image" id="hero_image" accept="image/*" required>
                                <small class="text-muted">Recommended size: 1920x1080px or similar landscape orientation. Max size: 5MB</small>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Add Hero Image (Debug)</button>
                        </form>
                    </div>

                    <!-- Directory Check -->
                    <div class="alert alert-warning">
                        <h5>System Check:</h5>
                        <p><strong>Hero Directory:</strong> <?php echo $heroDir; ?></p>
                        <p><strong>Exists:</strong> <?php echo is_dir($heroDir) ? '✅ YES' : '❌ NO'; ?></p>
                        <p><strong>Writable:</strong> <?php echo is_writable($heroDir) ? '✅ YES' : '❌ NO'; ?></p>
                        <p><strong>PHP Upload Max:</strong> <?php echo ini_get('upload_max_filesize'); ?></p>
                        <p><strong>PHP Post Max:</strong> <?php echo ini_get('post_max_size'); ?></p>
                    </div>

                    <!-- Existing Hero Images -->
                    <div class="mt-4">
                        <h4>Existing Hero Images (<?php echo count($hero_images); ?>)</h4>
                        
                        <?php if (empty($hero_images)): ?>
                            <p class="text-muted">No hero images found. Try uploading one above.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Image</th>
                                            <th>Title</th>
                                            <th>Subtitle</th>
                                            <th>Status</th>
                                            <th>Path</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($hero_images as $hero): ?>
                                            <tr>
                                                <td>
                                                    <?php 
                                                    $imagePath = BASE_PATH . $hero['image_path'];
                                                    if (file_exists($imagePath)): 
                                                    ?>
                                                        <img src="<?php echo BASE_URL . $hero['image_path']; ?>" 
                                                             alt="Hero Image" style="width: 100px; height: 60px; object-fit: cover;">
                                                    <?php else: ?>
                                                        <span class="text-danger">File not found</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($hero['title'] ?: 'No title'); ?></td>
                                                <td><?php echo htmlspecialchars($hero['subtitle'] ?: 'No subtitle'); ?></td>
                                                <td>
                                                    <span class="badge <?php echo $hero['is_active'] ? 'bg-success' : 'bg-secondary'; ?>">
                                                        <?php echo $hero['is_active'] ? 'Active' : 'Inactive'; ?>
                                                    </span>
                                                </td>
                                                <td><small><?php echo htmlspecialchars($hero['image_path']); ?></small></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

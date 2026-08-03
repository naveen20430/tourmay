<?php
require_once '../config/config.php';
requireLogin();

$success = '';
$errors = [];

if (!function_exists('return_bytes')) {
    function return_bytes($val) {
        $val = trim((string) $val);
        if ($val === '') {
            return 0;
        }
        $last = strtolower(substr($val, -1));
        $num = (float) $val;
        switch ($last) {
            case 'g':
                $num *= 1024;
                // no break
            case 'm':
                $num *= 1024;
                // no break
            case 'k':
                $num *= 1024;
        }
        return (int) $num;
    }
}

/**
 * Resolve a hero image upload with clear errors.
 */
function heroHandleUpload(array $file, array &$errors) {
    $uploadErrors = getUploadError($file);
    if (!empty($uploadErrors)) {
        $errors = array_merge($errors, $uploadErrors);
        return null;
    }

    $uploadResult = uploadFile($file, 'hero');
    if ($uploadResult) {
        return $uploadResult;
    }

    $heroDir = BASE_PATH . 'assets/images/hero/';
    if (!is_dir($heroDir)) {
        @mkdir($heroDir, 0755, true);
    }
    if (!is_dir($heroDir) || !is_writable($heroDir)) {
        $errors[] = 'Upload folder is not writable: assets/images/hero/';
    } else {
        $errors[] = 'Failed to save the uploaded image. Please try another JPG, PNG, or WebP under 8MB.';
    }
    return null;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    $postMaxBytes = (int) return_bytes((string) ini_get('post_max_size'));

    // When POST body exceeds post_max_size, PHP empties $_POST and $_FILES with no warning.
    if ($contentLength > 0 && empty($_POST) && empty($_FILES)) {
        $limitLabel = ini_get('post_max_size') ?: 'unknown';
        $errors[] = 'Upload was rejected by the server because the file (or form) is too large. '
            . 'Server post limit is ' . $limitLabel . '. Please upload a JPG/WebP under 8MB and try again.';
    } elseif (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $title = trim((string) ($_POST['title'] ?? ''));
                $subtitle = trim((string) ($_POST['subtitle'] ?? ''));
                $description = trim((string) ($_POST['description'] ?? ''));
                $sort_order = intval($_POST['sort_order'] ?? 0);
                $is_active = 1;

                if (!isset($_FILES['hero_image']) || !is_array($_FILES['hero_image'])) {
                    $errors[] = 'Please select an image file (JPG/PNG/WebP, max 8–10MB).';
                    break;
                }

                $uploadResult = heroHandleUpload($_FILES['hero_image'], $errors);
                if (!$uploadResult) {
                    break;
                }

                try {
                    $db->execute(
                        "INSERT INTO hero_images (title, subtitle, description, image_path, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?)",
                        [$title, $subtitle, $description, $uploadResult, $sort_order, $is_active]
                    );
                    $success = 'Hero image added successfully!';
                } catch (Exception $e) {
                    $errors[] = 'Database error while saving hero image: ' . $e->getMessage();
                }
                break;

            case 'update_image':
                $id = intval($_POST['id'] ?? 0);
                $title = trim((string) ($_POST['title'] ?? ''));
                $subtitle = trim((string) ($_POST['subtitle'] ?? ''));
                $description = trim((string) ($_POST['description'] ?? ''));

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

                if (isset($_FILES['hero_image']) && is_array($_FILES['hero_image']) && (int) $_FILES['hero_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $uploadResult = heroHandleUpload($_FILES['hero_image'], $errors);
                    if ($uploadResult) {
                        $oldPath = BASE_PATH . $hero['image_path'];
                        if (is_file($oldPath)) {
                            @unlink($oldPath);
                        }
                        $image_path = $uploadResult;
                    }
                }

                if (empty($errors)) {
                    $db->execute(
                        "UPDATE hero_images SET title = ?, subtitle = ?, description = ?, image_path = ?, is_active = 1 WHERE id = ?",
                        [$title, $subtitle, $description, $image_path, $id]
                    );
                    $success = 'Hero image updated successfully!';
                }
                break;

            case 'toggle_active':
                $id = intval($_POST['id'] ?? 0);
                $is_active = intval($_POST['is_active'] ?? 0);
                $db->execute("UPDATE hero_images SET is_active = ? WHERE id = ?", [$is_active, $id]);
                $success = 'Hero image status updated!';
                break;

            case 'delete':
                $id = intval($_POST['id'] ?? 0);
                $hero = $db->fetch("SELECT image_path FROM hero_images WHERE id = ?", [$id]);

                if ($hero) {
                    $filePath = BASE_PATH . $hero['image_path'];
                    if (is_file($filePath)) {
                        @unlink($filePath);
                    }
                    $db->execute("DELETE FROM hero_images WHERE id = ?", [$id]);
                    $success = 'Hero image deleted successfully!';
                }
                break;

            case 'update_order':
                if (isset($_POST['hero_orders']) && is_array($_POST['hero_orders'])) {
                    foreach ($_POST['hero_orders'] as $id => $order) {
                        $db->execute(
                            "UPDATE hero_images SET sort_order = ? WHERE id = ?",
                            [intval($order), intval($id)]
                        );
                    }
                    $success = 'Sort order updated successfully!';
                }
                break;
        }
    }
}

$hero_images = $db->fetchAll("SELECT * FROM hero_images ORDER BY sort_order ASC, created_at DESC");
$uploadMax = ini_get('upload_max_filesize') ?: 'unknown';
$postMax = ini_get('post_max_size') ?: 'unknown';

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
                        Each upload is <strong>one homepage slider</strong> with its own image, title, subtitle, and description.
                        Add multiple slides below — they rotate automatically on the homepage (about every 5 seconds).
                        Use <strong>Sort Order</strong> to control the sequence (1 = first). Recommended size: 1920×1080px landscape JPG/WebP under 8MB.
                        <br><small>Server limits: upload_max_filesize=<?php echo htmlspecialchars($uploadMax); ?>, post_max_size=<?php echo htmlspecialchars($postMax); ?></small>
                    </p>
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                    <?php endif; ?>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <?php foreach ($errors as $error): ?>
                                <p class="mb-1"><?php echo htmlspecialchars($error); ?></p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="mb-4">
                        <h4>Add New Slider</h4>
                        <p class="text-muted">Fill title / description for this slide, choose an image, then click <strong>Add Hero Image</strong>. Repeat to add more sliders.</p>
                        <form method="POST" action="" enctype="multipart/form-data" class="border p-3" id="heroAddForm">
                            <input type="hidden" name="action" value="add">
                            <input type="hidden" name="MAX_FILE_SIZE" value="10485760">

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="title">Title (this slide)</label>
                                        <input type="text" class="form-control" name="title" id="title" placeholder="e.g. Book Your Cab" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="sort_order">Sort Order</label>
                                        <input type="number" class="form-control" name="sort_order" id="sort_order" value="<?php echo count($hero_images) + 1; ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="subtitle">Subtitle (optional)</label>
                                <input type="text" class="form-control" name="subtitle" id="subtitle" placeholder="e.g. Your Adventure Starts Here">
                            </div>

                            <div class="form-group">
                                <label for="description">Description (this slide)</label>
                                <textarea class="form-control" name="description" id="description" rows="3" placeholder="Short text shown under the title for this slider" required></textarea>
                            </div>

                            <div class="form-group">
                                <label for="hero_image">Slider Image <span class="text-danger">*</span></label>
                                <input type="file" class="form-control" name="hero_image" id="hero_image" accept=".jpg,.jpeg,.png,.webp,.gif,image/jpeg,image/png,image/webp,image/gif" required>
                                <small class="text-muted">JPG, PNG, or WebP. Max 10MB.</small>
                                <div id="heroFileHint" class="text-danger small mt-1" style="display:none;"></div>
                            </div>

                            <button type="submit" class="btn btn-primary" id="heroAddBtn">Add Hero Image</button>
                        </form>
                    </div>

                    <div class="mt-4">
                        <h4>Existing Hero Images (<?php echo count($hero_images); ?>)</h4>

                        <?php if (empty($hero_images)): ?>
                            <p class="text-muted">No hero images found. Add one above.</p>
                        <?php else: ?>
                            <form method="POST" class="mb-3">
                                <input type="hidden" name="action" value="update_order">
                                <div class="table-responsive">
                                    <table class="table table-striped align-middle">
                                        <thead>
                                            <tr>
                                                <th>Image</th>
                                                <th>Title / Text</th>
                                                <th>Sort</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($hero_images as $hero): ?>
                                                <tr>
                                                    <td>
                                                        <img src="<?php echo htmlspecialchars(BASE_URL . $hero['image_path']); ?>"
                                                             alt="Hero Image"
                                                             style="width: 120px; height: 70px; object-fit: cover; border-radius: 6px;">
                                                    </td>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($hero['title'] ?: 'No title'); ?></strong><br>
                                                        <small class="text-muted"><?php echo htmlspecialchars($hero['subtitle'] ?: ''); ?></small>
                                                    </td>
                                                    <td>
                                                        <input type="number"
                                                               name="hero_orders[<?php echo (int) $hero['id']; ?>]"
                                                               value="<?php echo (int) $hero['sort_order']; ?>"
                                                               class="form-control"
                                                               style="width: 90px;">
                                                    </td>
                                                    <td>
                                                        <?php if (!empty($hero['is_active'])): ?>
                                                            <span class="badge badge-success">Active</span>
                                                        <?php else: ?>
                                                            <span class="badge badge-secondary">Inactive</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <button type="submit" class="btn btn-info">Update Sort Order</button>
                            </form>

                            <h5 class="mt-4">Edit / Replace an image</h5>
                            <p class="text-muted">Pick a row below to change text or replace the file. These are separate forms (not nested).</p>
                            <?php foreach ($hero_images as $hero): ?>
                                <form method="POST" enctype="multipart/form-data" class="border rounded p-3 mb-3">
                                    <input type="hidden" name="action" value="update_image">
                                    <input type="hidden" name="id" value="<?php echo (int) $hero['id']; ?>">
                                    <div class="row">
                                        <div class="col-md-2 mb-2">
                                            <img src="<?php echo htmlspecialchars(BASE_URL . $hero['image_path']); ?>"
                                                 alt=""
                                                 style="width:100%; height:80px; object-fit:cover; border-radius:6px;">
                                        </div>
                                        <div class="col-md-3 mb-2">
                                            <input type="text" name="title" class="form-control form-control-sm" value="<?php echo htmlspecialchars($hero['title'] ?: ''); ?>" placeholder="Title">
                                        </div>
                                        <div class="col-md-3 mb-2">
                                            <input type="text" name="subtitle" class="form-control form-control-sm" value="<?php echo htmlspecialchars($hero['subtitle'] ?: ''); ?>" placeholder="Subtitle">
                                        </div>
                                        <div class="col-md-4 mb-2">
                                            <textarea name="description" class="form-control form-control-sm" rows="2" placeholder="Description"><?php echo htmlspecialchars($hero['description'] ?: ''); ?></textarea>
                                        </div>
                                    </div>
                                    <div class="d-flex flex-wrap gap-2 align-items-center">
                                        <input type="file" name="hero_image" class="form-control form-control-sm" style="max-width:280px;" accept=".jpg,.jpeg,.png,.webp,.gif,image/*">
                                        <button type="submit" class="btn btn-sm btn-primary">Save / Replace Image</button>
                                    </div>
                                </form>
                                <form method="POST" class="mb-4" onsubmit="return confirm('Are you sure you want to delete this hero image?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo (int) $hero['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete “<?php echo htmlspecialchars($hero['title'] ?: ('#'.$hero['id'])); ?>”</button>
                                </form>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var input = document.getElementById('hero_image');
    var hint = document.getElementById('heroFileHint');
    var form = document.getElementById('heroAddForm');
    var btn = document.getElementById('heroAddBtn');
    var maxBytes = 10 * 1024 * 1024;

    function checkFile() {
        if (!input || !input.files || !input.files[0]) {
            if (hint) hint.style.display = 'none';
            return true;
        }
        var file = input.files[0];
        if (file.size > maxBytes) {
            if (hint) {
                hint.style.display = 'block';
                hint.textContent = 'This file is ' + (file.size / (1024 * 1024)).toFixed(1) + 'MB. Please use an image under 10MB.';
            }
            return false;
        }
        if (hint) hint.style.display = 'none';
        return true;
    }

    if (input) input.addEventListener('change', checkFile);
    if (form) {
        form.addEventListener('submit', function (e) {
            if (!checkFile()) {
                e.preventDefault();
                return;
            }
            if (btn) {
                btn.disabled = true;
                btn.textContent = 'Uploading...';
            }
        });
    }
})();
</script>

<?php include 'includes/footer.php'; ?>

<?php
require_once '../config/config.php';
requireLogin();

$success_message = '';
$error_message = '';

// Ensure cab_types.image_path column exists
try {
    $imageColumn = $db->fetch("SHOW COLUMNS FROM cab_types LIKE 'image_path'");
    if (!$imageColumn) {
        $db->execute("ALTER TABLE cab_types ADD COLUMN image_path VARCHAR(255) NULL DEFAULT NULL AFTER description");
    }
} catch (Exception $e) {
    // Non-fatal; upload UI still works once column is added manually
}

// Handle form submissions
if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'update_base_pricing') {
            // Update base cab pricing
            $cab_updates = $_POST['cabs'] ?? [];
            $remove_images = $_POST['remove_image'] ?? [];
            
            foreach ($cab_updates as $cab_id => $cab_data) {
                $currentCab = $db->fetch("SELECT image_path FROM cab_types WHERE id = ?", [$cab_id]);
                $image_path = $currentCab['image_path'] ?? null;

                if (!empty($remove_images[$cab_id])) {
                    if ($image_path && is_file('../' . $image_path)) {
                        unlink('../' . $image_path);
                    }
                    $image_path = null;
                }

                if (!empty($_FILES['cab_images']['name'][$cab_id])) {
                    $file = [
                        'name' => $_FILES['cab_images']['name'][$cab_id],
                        'type' => $_FILES['cab_images']['type'][$cab_id],
                        'tmp_name' => $_FILES['cab_images']['tmp_name'][$cab_id],
                        'error' => $_FILES['cab_images']['error'][$cab_id],
                        'size' => $_FILES['cab_images']['size'][$cab_id],
                    ];

                    $uploadErrors = getUploadError($file);
                    if (!empty($uploadErrors)) {
                        throw new Exception('Cab image (' . ($cab_data['display_name'] ?? $cab_id) . '): ' . implode(', ', $uploadErrors));
                    }

                    if ($image_path && is_file('../' . $image_path)) {
                        unlink('../' . $image_path);
                    }

                    $upload_result = uploadFile($file, 'cabs');
                    if (!$upload_result) {
                        throw new Exception('Failed to upload image for ' . ($cab_data['display_name'] ?? 'cab type'));
                    }
                    $image_path = $upload_result;
                }

                $db->execute("
                    UPDATE cab_types 
                    SET display_name = ?, base_price = ?, price_per_km = ?, max_passengers = ?, description = ?, image_path = ?
                    WHERE id = ?
                ", [
                    $cab_data['display_name'],
                    $cab_data['base_price'],
                    $cab_data['price_per_km'],
                    $cab_data['max_passengers'],
                    $cab_data['description'],
                    $image_path,
                    $cab_id
                ]);
            }
            $success_message = "Base cab pricing updated successfully!";
            
        } elseif ($action === 'update_tour_pricing') {
            require_once '../includes/cab_options.php';
            ensureTourCabPricesSchema();

            $tourUpdates = $_POST['tour_prices'] ?? [];
            $savedCount = 0;
            foreach ($tourUpdates as $tourId => $cabPrices) {
                $tourId = (int) $tourId;
                if ($tourId <= 0 || !is_array($cabPrices)) {
                    continue;
                }
                saveTourCabPrices($tourId, $cabPrices, $db);
                $savedCount++;
            }
            $success_message = $savedCount > 0
                ? 'Tour cab pricing updated successfully!'
                : 'No tour cab prices were updated.';

        } elseif ($action === 'clear_tour_pricing') {
            require_once '../includes/cab_options.php';
            ensureTourCabPricesSchema();
            $tourId = (int) ($_POST['tour_id'] ?? 0);
            if ($tourId > 0) {
                $db->execute("DELETE FROM tour_cab_prices WHERE tour_id = ?", [$tourId]);
                $success_message = 'Tour cab prices cleared. Default base prices will be used.';
            }
        }
        
    } catch (Exception $e) {
        $error_message = "Error: " . $e->getMessage();
    }
}

require_once '../includes/cab_options.php';
ensureTourCabPricesSchema();

// Fetch current cab types
$cab_types = $db->fetchAll("SELECT * FROM cab_types WHERE status = 'active' ORDER BY base_price ASC");

// Fetch all tours for tour-specific pricing grid
$available_tours = $db->fetchAll("SELECT id, title, duration_days, status FROM tours ORDER BY title ASC");

// Build price map: tour_id => cab_type_id => price
$tourPriceRows = $db->fetchAll("SELECT tour_id, cab_type_id, price FROM tour_cab_prices");
$tourPricesByTour = [];
foreach ($tourPriceRows as $row) {
    $tourPricesByTour[(int) $row['tour_id']][(int) $row['cab_type_id']] = (float) $row['price'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cab Pricing Management - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .pricing-card { border: 1px solid #dee2e6; border-radius: 8px; margin-bottom: 20px; }
        .pricing-card .card-header { background: #f8f9fa; font-weight: bold; }
        .price-input { max-width: 120px; }
        .tour-pricing-table { font-size: 0.9em; }
        .cab-image-preview { width: 100%; max-height: 140px; object-fit: cover; border-radius: 8px; border: 1px solid #dee2e6; background: #f8f9fa; }
        .cab-image-placeholder { width: 100%; height: 120px; border-radius: 8px; border: 1px dashed #ced4da; background: #f8f9fa; display: flex; align-items: center; justify-content: center; color: #adb5bd; font-size: 0.85rem; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 bg-dark vh-100">
                <div class="pt-3">
                    <h5 class="text-white text-center">Admin Panel</h5>
                    <nav class="nav flex-column mt-4">
                        <a class="nav-link text-light" href="index.php">
                            <i class="fas fa-dashboard me-2"></i> Dashboard
                        </a>
                        <a class="nav-link text-light" href="tours.php">
                            <i class="fas fa-map me-2"></i> Tours
                        </a>
                        <a class="nav-link text-light" href="bookings.php">
                            <i class="fas fa-calendar-check me-2"></i> Bookings
                        </a>
                        <a class="nav-link text-warning fw-bold" href="cab_pricing.php">
                            <i class="fas fa-car me-2"></i> Cab Pricing
                        </a>
                        <a class="nav-link text-light" href="users.php">
                            <i class="fas fa-users me-2"></i> Users
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-10">
                <div class="container-fluid py-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2><i class="fas fa-car me-2"></i>Cab Pricing Management</h2>
                        <div>
                            <a href="bookings.php" class="btn btn-outline-primary">
                                <i class="fas fa-calendar-check me-1"></i> View Bookings
                            </a>
                        </div>
                    </div>

                    <!-- Success/Error Messages -->
                    <?php if ($success_message): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <?php echo htmlspecialchars($success_message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if ($error_message): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <?php echo htmlspecialchars($error_message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Base Cab Types Management -->
                    <div class="card mb-5">
                        <div class="card-header">
                            <h4><i class="fas fa-cog me-2"></i>Base Cab Types & Default Pricing</h4>
                        </div>
                        <div class="card-body">
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="update_base_pricing">
                                
                                <div class="row">
                                    <?php foreach ($cab_types as $cab): ?>
                                        <div class="col-lg-6 col-xl-3 mb-4">
                                            <div class="pricing-card">
                                                <div class="card-header">
                                                    <i class="fas fa-car me-2"></i><?php echo htmlspecialchars($cab['display_name']); ?>
                                                </div>
                                                <div class="card-body">
                                                    <div class="mb-3">
                                                        <label class="form-label">Cab Image</label>
                                                        <?php if (!empty($cab['image_path']) && is_file('../' . $cab['image_path'])): ?>
                                                            <img src="../<?php echo htmlspecialchars($cab['image_path']); ?>"
                                                                 alt="<?php echo htmlspecialchars($cab['display_name']); ?>"
                                                                 class="cab-image-preview mb-2">
                                                            <div class="form-check mb-2">
                                                                <input class="form-check-input" type="checkbox"
                                                                       name="remove_image[<?php echo $cab['id']; ?>]"
                                                                       value="1" id="remove_image_<?php echo $cab['id']; ?>">
                                                                <label class="form-check-label" for="remove_image_<?php echo $cab['id']; ?>">Remove current image</label>
                                                            </div>
                                                        <?php else: ?>
                                                            <div class="cab-image-placeholder mb-2">No image uploaded</div>
                                                        <?php endif; ?>
                                                        <input type="file" class="form-control"
                                                               name="cab_images[<?php echo $cab['id']; ?>]"
                                                               accept="image/jpeg,image/png,image/gif,image/webp">
                                                        <small class="text-muted">Shown on travel cab listings (Car Type row)</small>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Display Name</label>
                                                        <input type="text" class="form-control" 
                                                               name="cabs[<?php echo $cab['id']; ?>][display_name]" 
                                                               value="<?php echo htmlspecialchars($cab['display_name']); ?>">
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label">Base Price (₹)</label>
                                                        <input type="number" step="0.01" class="form-control price-input" 
                                                               name="cabs[<?php echo $cab['id']; ?>][base_price]" 
                                                               value="<?php echo $cab['base_price']; ?>">
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label">Price per KM (₹)</label>
                                                        <input type="number" step="0.01" class="form-control price-input" 
                                                               name="cabs[<?php echo $cab['id']; ?>][price_per_km]" 
                                                               value="<?php echo $cab['price_per_km']; ?>">
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label">Max Passengers</label>
                                                        <input type="number" class="form-control price-input" 
                                                               name="cabs[<?php echo $cab['id']; ?>][max_passengers]" 
                                                               value="<?php echo $cab['max_passengers']; ?>">
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label">Description</label>
                                                        <textarea class="form-control" rows="2" 
                                                                name="cabs[<?php echo $cab['id']; ?>][description]"><?php echo htmlspecialchars($cab['description']); ?></textarea>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <div class="text-end">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i> Update Base Pricing
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Tour-Specific Pricing Management -->
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h4 class="mb-0"><i class="fas fa-map-signs me-2"></i>Tour-Specific Cab Pricing</h4>
                            <span class="badge bg-info"><?php echo count($available_tours); ?> Tours</span>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                Set a flat cab price for each tour (fetched directly on cart/checkout). Leave blank or <strong>0</strong> to use the default base price above.
                                You can also edit these from <strong>Tours → Edit Tour</strong>.
                            </div>

                            <?php if (empty($available_tours)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-car fa-3x text-muted mb-3"></i>
                                    <h5>No tours found</h5>
                                    <p class="text-muted">Add tours first, then set cab prices here.</p>
                                </div>
                            <?php elseif (empty($cab_types)): ?>
                                <div class="alert alert-warning">No active cab types found. Add cab types in Base Cab Types above.</div>
                            <?php else: ?>
                                <form method="POST">
                                    <input type="hidden" name="action" value="update_tour_pricing">
                                    <div class="table-responsive">
                                        <table class="table table-hover tour-pricing-table align-middle">
                                            <thead>
                                                <tr>
                                                    <th style="min-width:220px;">Tour</th>
                                                    <?php foreach ($cab_types as $cab): ?>
                                                        <th>
                                                            <?php echo htmlspecialchars($cab['display_name']); ?>
                                                            <div class="small text-muted fw-normal">Default ₹<?php echo number_format((float)$cab['base_price'], 0); ?></div>
                                                        </th>
                                                    <?php endforeach; ?>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($available_tours as $tour): ?>
                                                    <?php
                                                    $tid = (int) $tour['id'];
                                                    $hasCustom = !empty($tourPricesByTour[$tid]);
                                                    ?>
                                                    <tr>
                                                        <td>
                                                            <strong><?php echo htmlspecialchars($tour['title']); ?></strong>
                                                            <div class="small text-muted">
                                                                <?php echo (int) $tour['duration_days']; ?> day(s)
                                                                · <?php echo htmlspecialchars($tour['status']); ?>
                                                                <?php if ($hasCustom): ?>
                                                                    · <span class="text-success">Custom prices set</span>
                                                                <?php else: ?>
                                                                    · Using defaults
                                                                <?php endif; ?>
                                                            </div>
                                                            <a class="small" href="tour-edit.php?id=<?php echo $tid; ?>">Edit tour</a>
                                                        </td>
                                                        <?php foreach ($cab_types as $cab): ?>
                                                            <?php
                                                            $cid = (int) $cab['id'];
                                                            $value = $tourPricesByTour[$tid][$cid] ?? '';
                                                            ?>
                                                            <td>
                                                                <input type="number"
                                                                       step="0.01"
                                                                       min="0"
                                                                       class="form-control price-input"
                                                                       name="tour_prices[<?php echo $tid; ?>][<?php echo $cid; ?>]"
                                                                       value="<?php echo $value !== '' ? htmlspecialchars((string)$value) : ''; ?>"
                                                                       placeholder="<?php echo number_format((float)$cab['base_price'], 0); ?>">
                                                            </td>
                                                        <?php endforeach; ?>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <p class="text-muted small mt-2 mb-0">Tip: leave blank or enter 0 and save to use the default base price for that cab.</p>
                                    <div class="text-end mt-3">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-1"></i> Save Tour Cab Prices
                                        </button>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.querySelectorAll('input[type="number"]').forEach(function(input) {
            input.addEventListener('change', function() {
                this.style.backgroundColor = '#fff3cd';
                setTimeout(() => {
                    this.style.backgroundColor = '';
                }, 1000);
            });
        });
    </script>
</body>
</html>
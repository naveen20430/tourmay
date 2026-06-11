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
            // Update tour-specific pricing
            $tour_updates = $_POST['tours'] ?? [];
            
            foreach ($tour_updates as $tour_id => $tour_data) {
                $db->execute("
                    UPDATE tour_cab_pricing 
                    SET sedan_price = ?, ertiga_price = ?, innova_price = ?, tempo_traveller_price = ?
                    WHERE id = ?
                ", [
                    $tour_data['sedan_price'],
                    $tour_data['ertiga_price'],
                    $tour_data['innova_price'],
                    $tour_data['tempo_traveller_price'],
                    $tour_id
                ]);
            }
            $success_message = "Tour-specific pricing updated successfully!";
            
        } elseif ($action === 'add_tour_pricing') {
            // Add new tour pricing
            $tour_name = $_POST['new_tour_name'] ?? '';
            $sedan_price = $_POST['new_sedan_price'] ?? 0;
            $ertiga_price = $_POST['new_ertiga_price'] ?? 0;
            $innova_price = $_POST['new_innova_price'] ?? 0;
            $tempo_price = $_POST['new_tempo_price'] ?? 0;
            
            if ($tour_name) {
                $db->execute("
                    INSERT INTO tour_cab_pricing (tour_name, sedan_price, ertiga_price, innova_price, tempo_traveller_price)
                    VALUES (?, ?, ?, ?, ?)
                ", [$tour_name, $sedan_price, $ertiga_price, $innova_price, $tempo_price]);
                
                $success_message = "New tour pricing added successfully!";
            } else {
                $error_message = "Tour name is required!";
            }
            
        } elseif ($action === 'delete_tour_pricing') {
            // Delete tour pricing
            $tour_id = $_POST['tour_id'] ?? 0;
            if ($tour_id) {
                $db->execute("DELETE FROM tour_cab_pricing WHERE id = ?", [$tour_id]);
                $success_message = "Tour pricing deleted successfully!";
            }
        }
        
    } catch (Exception $e) {
        $error_message = "Error: " . $e->getMessage();
    }
}

// Fetch current cab types
$cab_types = $db->fetchAll("SELECT * FROM cab_types ORDER BY base_price ASC");

// Fetch all tours for dropdown
$available_tours = $db->fetchAll("SELECT id, title FROM tours ORDER BY title ASC");

// Fetch tour-specific pricing with tour details
$tour_pricing = $db->fetchAll("
    SELECT tcp.*, t.title as tour_title 
    FROM tour_cab_pricing tcp 
    LEFT JOIN tours t ON tcp.tour_name = t.title 
    ORDER BY tcp.tour_name ASC
");
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
                                                        <label class="form-label">Base Price (₹/day)</label>
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
                            <h4><i class="fas fa-map-signs me-2"></i>Tour-Specific Pricing</h4>
                            <div>
                                <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addTourModal">
                                    <i class="fas fa-plus me-1"></i> Add Tour Pricing
                                </button>
                                <span class="badge bg-info ms-2"><?php echo count($available_tours); ?> Tours Available</span>
                            </div>
                        </div>
                        <div class="card-body">
                            <!-- Quick Info -->
                            <?php 
                            $configured_tours = array_column($tour_pricing, 'tour_name');
                            $unconfigured_tours = array_filter($available_tours, function($tour) use ($configured_tours) {
                                return !in_array($tour['title'], $configured_tours);
                            });
                            ?>
                            
                            <?php if (!empty($unconfigured_tours)): ?>
                                <div class="alert alert-info">
                                    <h6><i class="fas fa-info-circle me-2"></i>Tours without cab pricing:</h6>
                                    <div class="row">
                                        <?php foreach ($unconfigured_tours as $tour): ?>
                                            <div class="col-md-4 mb-1">
                                                <small>• <?php echo htmlspecialchars($tour['title']); ?></small>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <small class="text-muted">Click "Add Tour Pricing" to configure pricing for these tours.</small>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($tour_pricing)): ?>
                                <form method="POST">
                                    <input type="hidden" name="action" value="update_tour_pricing">
                                    
                                    <div class="table-responsive">
                                        <table class="table table-hover tour-pricing-table">
                                            <thead>
                                                <tr>
                                                    <th>Tour Name</th>
                                                    <th>Sedan (₹)</th>
                                                    <th>Ertiga (₹)</th>
                                                    <th>Innova (₹)</th>
                                                    <th>Tempo Traveller (₹)</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($tour_pricing as $tour): ?>
                                                    <tr>
                                                        <td>
                                                            <strong><?php echo htmlspecialchars($tour['tour_name']); ?></strong>
                                                            <?php if ($tour['tour_title'] && $tour['tour_title'] !== $tour['tour_name']): ?>
                                                                <br><small class="text-muted">Database: <?php echo htmlspecialchars($tour['tour_title']); ?></small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <input type="number" step="0.01" class="form-control price-input" 
                                                                   name="tours[<?php echo $tour['id']; ?>][sedan_price]" 
                                                                   value="<?php echo $tour['sedan_price']; ?>">
                                                        </td>
                                                        <td>
                                                            <input type="number" step="0.01" class="form-control price-input" 
                                                                   name="tours[<?php echo $tour['id']; ?>][ertiga_price]" 
                                                                   value="<?php echo $tour['ertiga_price']; ?>">
                                                        </td>
                                                        <td>
                                                            <input type="number" step="0.01" class="form-control price-input" 
                                                                   name="tours[<?php echo $tour['id']; ?>][innova_price]" 
                                                                   value="<?php echo $tour['innova_price']; ?>">
                                                        </td>
                                                        <td>
                                                            <input type="number" step="0.01" class="form-control price-input" 
                                                                   name="tours[<?php echo $tour['id']; ?>][tempo_traveller_price]" 
                                                                   value="<?php echo $tour['tempo_traveller_price']; ?>">
                                                        </td>
                                                        <td>
                                                            <form method="POST" style="display: inline;" 
                                                                  onsubmit="return confirm('Are you sure you want to delete this tour pricing?')">
                                                                <input type="hidden" name="action" value="delete_tour_pricing">
                                                                <input type="hidden" name="tour_id" value="<?php echo $tour['id']; ?>">
                                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    
                                    <div class="text-end mt-3">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-1"></i> Update Tour Pricing
                                        </button>
                                    </div>
                                </form>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-car fa-3x text-muted mb-3"></i>
                                    <h5>No tour-specific pricing configured</h5>
                                    <p class="text-muted">Click "Add Tour Pricing" to set up specific pricing for different tours.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Tour Pricing Modal -->
    <div class="modal fade" id="addTourModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="add_tour_pricing">
                    
                    <div class="modal-header">
                        <h5 class="modal-title">Add New Tour Pricing</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Select Tour *</label>
                            <select class="form-select" name="new_tour_name" required>
                                <option value="">Choose a tour...</option>
                                <?php foreach ($available_tours as $tour): ?>
                                    <?php 
                                    // Check if this tour already has pricing configured
                                    $has_pricing = false;
                                    foreach ($tour_pricing as $existing) {
                                        if ($existing['tour_name'] === $tour['title']) {
                                            $has_pricing = true;
                                            break;
                                        }
                                    }
                                    ?>
                                    <option value="<?php echo htmlspecialchars($tour['title']); ?>" 
                                            <?php echo $has_pricing ? 'disabled' : ''; ?>>
                                        <?php echo htmlspecialchars($tour['title']); ?>
                                        <?php echo $has_pricing ? ' (Already configured)' : ''; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-muted">Select from existing tours in your system</small>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Sedan Price (₹)</label>
                                <input type="number" step="0.01" class="form-control" name="new_sedan_price" value="0">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Ertiga Price (₹)</label>
                                <input type="number" step="0.01" class="form-control" name="new_ertiga_price" value="0">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Innova Price (₹)</label>
                                <input type="number" step="0.01" class="form-control" name="new_innova_price" value="0">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tempo Traveller Price (₹)</label>
                                <input type="number" step="0.01" class="form-control" name="new_tempo_price" value="0">
                            </div>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-plus me-1"></i> Add Tour Pricing
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-save indication
        document.querySelectorAll('input[type="number"]').forEach(function(input) {
            input.addEventListener('change', function() {
                this.style.backgroundColor = '#fff3cd';
                setTimeout(() => {
                    this.style.backgroundColor = '';
                }, 1000);
            });
        });
        
        // Tour selection enhancement
        document.addEventListener('DOMContentLoaded', function() {
            const tourSelect = document.querySelector('select[name="new_tour_name"]');
            if (tourSelect) {
                tourSelect.addEventListener('change', function() {
                    const selectedOption = this.options[this.selectedIndex];
                    if (selectedOption.disabled) {
                        this.value = '';
                        alert('This tour already has pricing configured. Please select a different tour.');
                    }
                });
            }
            
            // Highlight unconfigured tours in the alert
            const unconfiguredAlert = document.querySelector('.alert-info');
            if (unconfiguredAlert) {
                unconfiguredAlert.addEventListener('click', function(e) {
                    if (e.target.tagName === 'SMALL') {
                        // Open modal and pre-select the clicked tour
                        const tourName = e.target.textContent.replace('• ', '');
                        const modal = new bootstrap.Modal(document.getElementById('addTourModal'));
                        modal.show();
                        
                        // Pre-select the tour in dropdown
                        setTimeout(() => {
                            const tourSelect = document.querySelector('select[name="new_tour_name"]');
                            if (tourSelect) {
                                for (let option of tourSelect.options) {
                                    if (option.value === tourName) {
                                        option.selected = true;
                                        break;
                                    }
                                }
                            }
                        }, 100);
                    }
                });
                
                // Add pointer cursor to tour names
                const tourNames = unconfiguredAlert.querySelectorAll('small');
                tourNames.forEach(name => {
                    name.style.cursor = 'pointer';
                    name.style.textDecoration = 'underline';
                    name.title = 'Click to add pricing for this tour';
                });
            }
        });
    </script>
</body>
</html>
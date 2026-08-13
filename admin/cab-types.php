<?php
require_once '../config/config.php';
requireLogin();

$success_message = '';
$error_message = '';

// Ensure optional columns exist
try {
    $pdo = $db->getConnection();
    $cols = array_column($db->fetchAll('SHOW COLUMNS FROM cab_types'), 'Field');
    if (!in_array('image_path', $cols, true)) {
        $pdo->exec("ALTER TABLE cab_types ADD COLUMN image_path VARCHAR(255) NULL DEFAULT NULL");
        $cols[] = 'image_path';
    }
    if (!in_array('updated_at', $cols, true)) {
        $pdo->exec("ALTER TABLE cab_types ADD COLUMN updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP");
    }
} catch (Exception $e) {
    // Non-fatal
}

function cabTypeMakeSlug(string $displayName, string $fallback = 'cab'): string {
    $slug = strtolower(trim($displayName));
    $slug = preg_replace('/[^a-z0-9]+/', '_', $slug);
    $slug = trim((string) $slug, '_');
    if ($slug === '') {
        $slug = $fallback;
    }
    return substr($slug, 0, 80);
}

function cabTypeUniqueName(string $base, $db, int $excludeId = 0): string {
    $name = $base;
    $i = 2;
    while (true) {
        if ($excludeId > 0) {
            $existing = $db->fetch('SELECT id FROM cab_types WHERE name = ? AND id != ?', [$name, $excludeId]);
        } else {
            $existing = $db->fetch('SELECT id FROM cab_types WHERE name = ?', [$name]);
        }
        if (!$existing) {
            return $name;
        }
        $name = $base . '_' . $i;
        $i++;
        if ($i > 100) {
            return $base . '_' . time();
        }
    }
}

function cabTypeSeedRoutePricing(int $cabTypeId, $db): int {
    $routes = $db->fetchAll("SELECT id FROM cab_routes WHERE status = 'active'");
    $added = 0;
    foreach ($routes as $route) {
        $exists = $db->fetch(
            'SELECT id FROM cab_route_pricing WHERE route_id = ? AND cab_type_id = ?',
            [(int) $route['id'], $cabTypeId]
        );
        if ($exists) {
            continue;
        }
        $db->execute(
            "INSERT INTO cab_route_pricing (route_id, cab_type_id, price, one_way_price, round_trip_price, status)
             VALUES (?, ?, 0, 0, 0, 'active')",
            [(int) $route['id'], $cabTypeId]
        );
        $added++;
    }
    return $added;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (function_exists('requireCsrfOrFail')) {
        requireCsrfOrFail();
    }
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'add') {
            $display_name = trim((string) ($_POST['display_name'] ?? ''));
            $base_price = (float) ($_POST['base_price'] ?? 0);
            $price_per_km = (float) ($_POST['price_per_km'] ?? 0);
            $max_passengers = max(1, (int) ($_POST['max_passengers'] ?? 4));
            $description = trim((string) ($_POST['description'] ?? ''));
            $status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';
            $nameInput = trim((string) ($_POST['name'] ?? ''));

            if ($display_name === '') {
                throw new Exception('Display name is required.');
            }

            $baseSlug = $nameInput !== '' ? cabTypeMakeSlug($nameInput) : cabTypeMakeSlug($display_name);
            $name = cabTypeUniqueName($baseSlug, $db);

            $image_path = null;
            if (!empty($_FILES['image']['name'])) {
                $uploadErrors = getUploadError($_FILES['image']);
                if (!empty($uploadErrors)) {
                    throw new Exception(implode(', ', $uploadErrors));
                }
                $upload_result = uploadFile($_FILES['image'], 'cabs');
                if (!$upload_result) {
                    throw new Exception('Failed to upload cab image.');
                }
                $image_path = $upload_result;
            }

            $db->execute(
                "INSERT INTO cab_types (name, display_name, base_price, price_per_km, max_passengers, description, image_path, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [$name, $display_name, $base_price, $price_per_km, $max_passengers, $description, $image_path, $status]
            );
            $newId = (int) $db->lastInsertId();
            $seeded = 0;
            if ($status === 'active' && $newId > 0) {
                $seeded = cabTypeSeedRoutePricing($newId, $db);
            }
            $success_message = 'Cab type added successfully.'
                . ($seeded > 0 ? " Route pricing rows created for {$seeded} route(s) — set fares in Cab Routes → Pricing." : '');
        }

        if ($action === 'update') {
            $id = (int) ($_POST['id'] ?? 0);
            $cab = $db->fetch('SELECT * FROM cab_types WHERE id = ?', [$id]);
            if (!$cab) {
                throw new Exception('Cab type not found.');
            }

            $display_name = trim((string) ($_POST['display_name'] ?? ''));
            $base_price = (float) ($_POST['base_price'] ?? 0);
            $price_per_km = (float) ($_POST['price_per_km'] ?? 0);
            $max_passengers = max(1, (int) ($_POST['max_passengers'] ?? 4));
            $description = trim((string) ($_POST['description'] ?? ''));
            $status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

            if ($display_name === '') {
                throw new Exception('Display name is required.');
            }

            $image_path = $cab['image_path'] ?? null;
            if (!empty($_POST['remove_image'])) {
                if ($image_path && is_file('../' . $image_path)) {
                    unlink('../' . $image_path);
                }
                $image_path = null;
            }
            if (!empty($_FILES['image']['name'])) {
                $uploadErrors = getUploadError($_FILES['image']);
                if (!empty($uploadErrors)) {
                    throw new Exception(implode(', ', $uploadErrors));
                }
                if ($image_path && is_file('../' . $image_path)) {
                    unlink('../' . $image_path);
                }
                $upload_result = uploadFile($_FILES['image'], 'cabs');
                if (!$upload_result) {
                    throw new Exception('Failed to upload cab image.');
                }
                $image_path = $upload_result;
            }

            $db->execute(
                "UPDATE cab_types
                 SET display_name = ?, base_price = ?, price_per_km = ?, max_passengers = ?, description = ?, image_path = ?, status = ?
                 WHERE id = ?",
                [$display_name, $base_price, $price_per_km, $max_passengers, $description, $image_path, $status, $id]
            );

            if ($status === 'active') {
                cabTypeSeedRoutePricing($id, $db);
            }

            $success_message = 'Cab type updated successfully.';
        }

        if ($action === 'toggle') {
            $id = (int) ($_POST['id'] ?? 0);
            $cab = $db->fetch('SELECT id, status FROM cab_types WHERE id = ?', [$id]);
            if (!$cab) {
                throw new Exception('Cab type not found.');
            }
            $newStatus = ($cab['status'] ?? '') === 'active' ? 'inactive' : 'active';
            $db->execute('UPDATE cab_types SET status = ? WHERE id = ?', [$newStatus, $id]);
            if ($newStatus === 'active') {
                cabTypeSeedRoutePricing($id, $db);
            }
            $success_message = $newStatus === 'active' ? 'Cab type activated.' : 'Cab type deactivated.';
        }

        if ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            $cab = $db->fetch('SELECT * FROM cab_types WHERE id = ?', [$id]);
            if (!$cab) {
                throw new Exception('Cab type not found.');
            }

            // Soft-delete by default if referenced by bookings / pricing
            $pricingCount = (int) ($db->fetch(
                'SELECT COUNT(*) AS c FROM cab_route_pricing WHERE cab_type_id = ?',
                [$id]
            )['c'] ?? 0);

            $bookingCount = 0;
            try {
                $bookingCount = (int) ($db->fetch(
                    'SELECT COUNT(*) AS c
                     FROM cab_bookings cb
                     INNER JOIN cab_route_pricing crp ON cb.pricing_id = crp.id
                     WHERE crp.cab_type_id = ?',
                    [$id]
                )['c'] ?? 0);
            } catch (Exception $e) {
                $bookingCount = 0;
            }

            if ($bookingCount > 0 || $pricingCount > 0) {
                $db->execute("UPDATE cab_types SET status = 'inactive' WHERE id = ?", [$id]);
                $success_message = 'Cab type is in use, so it was deactivated instead of deleted.';
            } else {
                if (!empty($cab['image_path']) && is_file('../' . $cab['image_path'])) {
                    unlink('../' . $cab['image_path']);
                }
                $db->execute('DELETE FROM cab_types WHERE id = ?', [$id]);
                $success_message = 'Cab type deleted.';
            }
        }
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

$cab_types = $db->fetchAll('SELECT * FROM cab_types ORDER BY status ASC, base_price ASC, display_name ASC');
$edit_id = (int) ($_GET['edit'] ?? 0);
$edit_cab = null;
if ($edit_id > 0) {
    $edit_cab = $db->fetch('SELECT * FROM cab_types WHERE id = ?', [$edit_id]);
}

$page_title = 'Cab Types';
include 'includes/header.php';
?>

<style>
.cab-type-thumb { width: 72px; height: 54px; object-fit: cover; border-radius: 8px; border: 1px solid #e2e8f0; background: #f8fafc; }
.cab-type-placeholder { width: 72px; height: 54px; border-radius: 8px; border: 1px dashed #cbd5e1; background: #f8fafc; display:flex; align-items:center; justify-content:center; color:#94a3b8; font-size:12px; }
.cab-inactive-row { opacity: .72; }
</style>

<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h2 class="mb-1">Cab Types</h2>
            <p class="text-muted mb-0">Add, edit, or deactivate cabs used on tours, cart, and home taxi routes.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="cab_pricing.php" class="btn btn-outline-primary btn-sm"><i class="fas fa-rupee-sign me-1"></i> Cab Pricing</a>
            <a href="cab-routes.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-route me-1"></i> Cab Routes</a>
        </div>
    </div>

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

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><?php echo $edit_cab ? 'Edit Cab Type' : 'Add New Cab'; ?></h5>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <?php echo function_exists('csrfField') ? csrfField() : ''; ?>
                        <input type="hidden" name="action" value="<?php echo $edit_cab ? 'update' : 'add'; ?>">
                        <?php if ($edit_cab): ?>
                            <input type="hidden" name="id" value="<?php echo (int) $edit_cab['id']; ?>">
                        <?php endif; ?>

                        <div class="mb-3">
                            <label class="form-label">Display name <span class="text-danger">*</span></label>
                            <input type="text" name="display_name" class="form-control" required
                                   placeholder="e.g. Traveller 12 Seater"
                                   value="<?php echo htmlspecialchars($edit_cab['display_name'] ?? ''); ?>">
                        </div>

                        <?php if (!$edit_cab): ?>
                            <div class="mb-3">
                                <label class="form-label">Internal code (optional)</label>
                                <input type="text" name="name" class="form-control"
                                       placeholder="Auto from display name (e.g. traveller_12_seater)">
                                <small class="text-muted">Used in system; leave blank to auto-generate.</small>
                            </div>
                        <?php else: ?>
                            <div class="mb-3">
                                <label class="form-label">Internal code</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($edit_cab['name'] ?? ''); ?>" disabled>
                            </div>
                        <?php endif; ?>

                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label">Base price (₹)</label>
                                <input type="number" step="0.01" min="0" name="base_price" class="form-control"
                                       value="<?php echo htmlspecialchars((string) ($edit_cab['base_price'] ?? '0')); ?>">
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label">Price / KM (₹)</label>
                                <input type="number" step="0.01" min="0" name="price_per_km" class="form-control"
                                       value="<?php echo htmlspecialchars((string) ($edit_cab['price_per_km'] ?? '0')); ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Max passengers</label>
                            <input type="number" min="1" max="50" name="max_passengers" class="form-control"
                                   value="<?php echo htmlspecialchars((string) ($edit_cab['max_passengers'] ?? '4')); ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="2"
                                      placeholder="Short notes shown with this cab"><?php echo htmlspecialchars($edit_cab['description'] ?? ''); ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="active" <?php echo (($edit_cab['status'] ?? 'active') === 'active') ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo (($edit_cab['status'] ?? '') === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Cab image</label>
                            <?php if (!empty($edit_cab['image_path']) && is_file('../' . $edit_cab['image_path'])): ?>
                                <img src="../<?php echo htmlspecialchars($edit_cab['image_path']); ?>"
                                     alt="" class="cab-type-thumb d-block mb-2" style="width:120px;height:90px;">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="remove_image" value="1" id="removeImage">
                                    <label class="form-check-label" for="removeImage">Remove current image</label>
                                </div>
                            <?php endif; ?>
                            <input type="file" name="image" class="form-control" accept="image/jpeg,image/png,image/gif,image/webp">
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> <?php echo $edit_cab ? 'Update Cab' : 'Add Cab'; ?>
                            </button>
                            <?php if ($edit_cab): ?>
                                <a href="cab-types.php" class="btn btn-secondary">Cancel</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">All Cab Types (<?php echo count($cab_types); ?>)</h5>
                </div>
                <div class="card-body table-responsive p-0">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>Image</th>
                                <th>Cab</th>
                                <th>Price</th>
                                <th>Seats</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($cab_types)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">No cab types yet. Add your first cab on the left.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($cab_types as $cab): ?>
                                    <tr class="<?php echo ($cab['status'] ?? '') !== 'active' ? 'cab-inactive-row' : ''; ?>">
                                        <td>
                                            <?php if (!empty($cab['image_path']) && is_file('../' . $cab['image_path'])): ?>
                                                <img src="../<?php echo htmlspecialchars($cab['image_path']); ?>" alt="" class="cab-type-thumb">
                                            <?php else: ?>
                                                <div class="cab-type-placeholder">No img</div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($cab['display_name']); ?></strong>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($cab['name']); ?></small>
                                        </td>
                                        <td>
                                            ₹<?php echo number_format((float) $cab['base_price'], 0); ?>
                                            <br><small class="text-muted">₹<?php echo number_format((float) $cab['price_per_km'], 0); ?>/km</small>
                                        </td>
                                        <td><?php echo (int) $cab['max_passengers']; ?></td>
                                        <td>
                                            <?php if (($cab['status'] ?? '') === 'active'): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="cab-types.php?edit=<?php echo (int) $cab['id']; ?>" class="btn btn-primary" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <form method="POST" class="d-inline">
                                                    <?php echo function_exists('csrfField') ? csrfField() : ''; ?>
                                                    <input type="hidden" name="action" value="toggle">
                                                    <input type="hidden" name="id" value="<?php echo (int) $cab['id']; ?>">
                                                    <button type="submit" class="btn btn-warning" title="Activate / Deactivate">
                                                        <i class="fas fa-power-off"></i>
                                                    </button>
                                                </form>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete or deactivate this cab type?');">
                                                    <?php echo function_exists('csrfField') ? csrfField() : ''; ?>
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo (int) $cab['id']; ?>">
                                                    <button type="submit" class="btn btn-danger" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <p class="text-muted small mt-2 mb-0">
                New active cabs are added to existing cab routes with ₹0 fares — set route prices under
                <a href="cab-routes.php">Cab Routes</a>. Tour cart prices can be set in
                <a href="cab_pricing.php">Cab Pricing</a> or Tour Edit.
            </p>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

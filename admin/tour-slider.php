<?php
require_once '../config/config.php';

// Check admin login
requireLogin();

$page_title = 'Tour Slider Management';

// Handle form submissions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_slider_settings':
                try {
                    // Update slider settings
                    $settings = [
                        'slider_autoplay' => isset($_POST['autoplay']) ? '1' : '0',
                        'slider_autoplay_speed' => intval($_POST['autoplay_speed']),
                        'slider_animation_speed' => intval($_POST['animation_speed']),
                        'slider_show_arrows' => isset($_POST['show_arrows']) ? '1' : '0',
                        'slider_show_dots' => isset($_POST['show_dots']) ? '1' : '0',
                        'slider_pause_on_hover' => isset($_POST['pause_on_hover']) ? '1' : '0',
                        'slider_slides_count' => intval($_POST['slides_count'])
                    ];
                    
                    foreach ($settings as $key => $value) {
                        $existing = $db->fetch("SELECT id FROM site_settings WHERE setting_key = ?", [$key]);
                        if ($existing) {
                            $db->execute("UPDATE site_settings SET setting_value = ? WHERE setting_key = ?", [$value, $key]);
                        } else {
                            $db->execute("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)", [$key, $value]);
                        }
                    }
                    
                    $message = 'Slider settings updated successfully!';
                    $message_type = 'success';
                } catch (Exception $e) {
                    $message = 'Error updating settings: ' . $e->getMessage();
                    $message_type = 'error';
                }
                break;
                
            case 'update_tour_slider_status':
                try {
                    $tour_id = intval($_POST['tour_id']);
                    $in_slider = isset($_POST['in_slider']) ? 1 : 0;
                    $slider_order = intval($_POST['slider_order']);
                    
                    $db->execute("UPDATE tours SET in_slider = ?, slider_order = ? WHERE id = ?", 
                                [$in_slider, $slider_order, $tour_id]);
                    
                    $message = 'Tour slider status updated successfully!';
                    $message_type = 'success';
                } catch (Exception $e) {
                    $message = 'Error updating tour: ' . $e->getMessage();
                    $message_type = 'error';
                }
                break;
        }
    }
}

// Get current slider settings
$slider_settings = [];
$settings_result = $db->fetchAll("SELECT setting_key, setting_value FROM site_settings WHERE setting_key LIKE 'slider_%'");
foreach ($settings_result as $setting) {
    $slider_settings[$setting['setting_key']] = $setting['setting_value'];
}

// Set default values if not exists
$defaults = [
    'slider_autoplay' => '1',
    'slider_autoplay_speed' => '6000',
    'slider_animation_speed' => '1000',
    'slider_show_arrows' => '1',
    'slider_show_dots' => '1',
    'slider_pause_on_hover' => '1',
    'slider_slides_count' => '5'
];

foreach ($defaults as $key => $value) {
    if (!isset($slider_settings[$key])) {
        $slider_settings[$key] = $value;
    }
}

// Check if columns exist, if not add them
try {
    $db->execute("ALTER TABLE tours ADD COLUMN in_slider TINYINT(1) DEFAULT 0");
} catch (Exception $e) {
    // Column already exists
}

try {
    $db->execute("ALTER TABLE tours ADD COLUMN slider_order INT DEFAULT 0");
} catch (Exception $e) {
    // Column already exists
}

// Get all tours with slider status
try {
    $tours = $db->fetchAll("
        SELECT t.*, d.name as destination_name, d.country,
               COALESCE(t.in_slider, 0) as in_slider,
               COALESCE(t.slider_order, 0) as slider_order
        FROM tours t 
        LEFT JOIN destinations d ON t.destination_id = d.id 
        WHERE t.status = 'active'
        ORDER BY t.slider_order ASC, t.featured DESC, t.popular DESC, t.created_at DESC
    ");
} catch (Exception $e) {
    // If still failing, show error and redirect to update script
    if (strpos($e->getMessage(), 'Unknown column') !== false) {
        echo "<div class='alert alert-warning'>";
        echo "<h4>⚠️ Database Update Required</h4>";
        echo "<p>The tour slider requires database updates. Please run the update script first:</p>";
        echo "<a href='../update_slider_database.php' class='btn btn-primary'>🔧 Run Database Update</a>";
        echo "</div>";
        include 'includes/footer.php';
        exit;
    }
    $tours = [];
}

include 'includes/header.php';
?>

<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">🎞️ Tour Slider Management</h1>
        <div>
            <a href="<?php echo BASE_URL; ?>index.php" target="_blank" class="btn btn-info btn-sm">
                <i class="fas fa-eye"></i> Preview Slider
            </a>
        </div>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show">
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="row">
        <!-- Slider Settings -->
        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">⚙️ Slider Settings</h6>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="update_slider_settings">
                        
                        <div class="mb-3">
                            <label class="form-label">Number of Slides</label>
                            <select name="slides_count" class="form-select">
                                <?php for ($i = 3; $i <= 10; $i++): ?>
                                <option value="<?php echo $i; ?>" <?php echo $slider_settings['slider_slides_count'] == $i ? 'selected' : ''; ?>>
                                    <?php echo $i; ?> Slides
                                </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="autoplay" 
                                       <?php echo $slider_settings['slider_autoplay'] == '1' ? 'checked' : ''; ?>>
                                <label class="form-check-label">Auto-play Slides</label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Auto-play Speed (milliseconds)</label>
                            <input type="number" name="autoplay_speed" class="form-control" 
                                   value="<?php echo $slider_settings['slider_autoplay_speed']; ?>" 
                                   min="2000" max="15000" step="1000">
                            <small class="form-text text-muted">Time each slide is shown (2-15 seconds)</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Animation Speed (milliseconds)</label>
                            <input type="number" name="animation_speed" class="form-control" 
                                   value="<?php echo $slider_settings['slider_animation_speed']; ?>" 
                                   min="300" max="2000" step="100">
                            <small class="form-text text-muted">Speed of slide transitions</small>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="show_arrows" 
                                       <?php echo $slider_settings['slider_show_arrows'] == '1' ? 'checked' : ''; ?>>
                                <label class="form-check-label">Show Navigation Arrows</label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="show_dots" 
                                       <?php echo $slider_settings['slider_show_dots'] == '1' ? 'checked' : ''; ?>>
                                <label class="form-check-label">Show Dot Indicators</label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="pause_on_hover" 
                                       <?php echo $slider_settings['slider_pause_on_hover'] == '1' ? 'checked' : ''; ?>>
                                <label class="form-check-label">Pause on Hover</label>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-save"></i> Update Settings
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Slider Statistics -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-success">📊 Slider Statistics</h6>
                </div>
                <div class="card-body">
                    <?php
                    $total_tours = count($tours);
                    $slider_tours = count(array_filter($tours, function($tour) { return $tour['in_slider'] == 1; }));
                    $featured_tours = count(array_filter($tours, function($tour) { return $tour['featured'] == 1; }));
                    ?>
                    <div class="row text-center">
                        <div class="col-4">
                            <div class="border-end">
                                <div class="h4 text-primary"><?php echo $slider_tours; ?></div>
                                <small class="text-muted">In Slider</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="border-end">
                                <div class="h4 text-success"><?php echo $featured_tours; ?></div>
                                <small class="text-muted">Featured</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="h4 text-info"><?php echo $total_tours; ?></div>
                            <small class="text-muted">Total Tours</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tours Management -->
        <div class="col-lg-8">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">🎯 Manage Tours in Slider</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($tours)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-exclamation-circle fa-3x text-warning mb-3"></i>
                        <h5>No Tours Found</h5>
                        <p class="text-muted">Add some tours first to manage the slider.</p>
                        <a href="tour-add.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add Tour
                        </a>
                    </div>
                    <?php else: ?>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>How it works:</strong> Check "Include in Slider" for tours you want to display. 
                        Set the order (1 = first slide). Tours are also automatically selected based on Featured/Popular status.
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th width="60">Image</th>
                                    <th>Tour Details</th>
                                    <th width="120">Status</th>
                                    <th width="120">Include in Slider</th>
                                    <th width="100">Order</th>
                                    <th width="80">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tours as $tour): ?>
                                <tr class="<?php echo $tour['in_slider'] ? 'table-success' : ''; ?>">
                                    <td>
                                        <?php 
                                        $image = $tour['featured_image'] ?: 'assets/images/tours/default.jpg';
                                        ?>
                                        <img src="<?php echo BASE_URL . $image; ?>" 
                                             alt="<?php echo htmlspecialchars($tour['title']); ?>"
                                             class="img-thumbnail" style="width: 50px; height: 50px; object-fit: cover;">
                                    </td>
                                    <td>
                                        <div class="fw-bold"><?php echo htmlspecialchars($tour['title']); ?></div>
                                        <small class="text-muted">
                                            <i class="fas fa-map-marker-alt"></i> 
                                            <?php echo htmlspecialchars($tour['destination_name'] ?: 'No destination'); ?>
                                            <?php if ($tour['price']): ?>
                                            | <i class="fas fa-tag"></i> <?php echo formatPriceINR($tour['price']); ?>
                                            <?php endif; ?>
                                        </small>
                                        <?php if ($tour['duration_days']): ?>
                                        <br><small class="text-muted">
                                            <i class="fas fa-clock"></i> <?php echo $tour['duration_days']; ?> Days
                                        </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column gap-1">
                                            <?php if ($tour['featured']): ?>
                                            <span class="badge bg-warning text-dark">Featured</span>
                                            <?php endif; ?>
                                            <?php if ($tour['popular']): ?>
                                            <span class="badge bg-info">Popular</span>
                                            <?php endif; ?>
                                            <span class="badge bg-<?php echo $tour['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                <?php echo ucfirst($tour['status']); ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="update_tour_slider_status">
                                            <input type="hidden" name="tour_id" value="<?php echo $tour['id']; ?>">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="in_slider" 
                                                       <?php echo $tour['in_slider'] ? 'checked' : ''; ?>
                                                       onchange="this.form.submit()">
                                                <label class="form-check-label small">Include</label>
                                            </div>
                                        </form>
                                    </td>
                                    <td>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="update_tour_slider_status">
                                            <input type="hidden" name="tour_id" value="<?php echo $tour['id']; ?>">
                                            <input type="hidden" name="in_slider" value="<?php echo $tour['in_slider']; ?>">
                                            <input type="number" name="slider_order" 
                                                   value="<?php echo $tour['slider_order']; ?>" 
                                                   class="form-control form-control-sm" 
                                                   min="0" max="99"
                                                   onchange="this.form.submit()"
                                                   style="width: 70px;">
                                        </form>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="tour-edit.php?id=<?php echo $tour['id']; ?>" 
                                               class="btn btn-outline-primary" title="Edit Tour">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="<?php echo BASE_URL; ?>tour-details.php?id=<?php echo $tour['id']; ?>" 
                                               target="_blank" class="btn btn-outline-info" title="Preview">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </div>
                                    </td>
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
    
    <!-- Quick Actions -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">⚡ Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <a href="tour-add.php" class="btn btn-success w-100 mb-2">
                                <i class="fas fa-plus"></i> Add New Tour
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="<?php echo BASE_URL; ?>index.php" target="_blank" class="btn btn-info w-100 mb-2">
                                <i class="fas fa-eye"></i> Preview Homepage
                            </a>
                        </div>
                        <div class="col-md-3">
                            <button type="button" class="btn btn-warning w-100 mb-2" onclick="resetSliderOrder()">
                                <i class="fas fa-sort-numeric-down"></i> Reset Order
                            </button>
                        </div>
                        <div class="col-md-3">
                            <a href="tours.php" class="btn btn-primary w-100 mb-2">
                                <i class="fas fa-list"></i> Manage All Tours
                            </a>
                        </div>
                    </div>
                    
                    <div class="alert alert-light mt-3">
                        <h6><i class="fas fa-lightbulb text-warning"></i> Pro Tips:</h6>
                        <ul class="mb-0 small">
                            <li><strong>Order matters:</strong> Lower numbers appear first (1, 2, 3...)</li>
                            <li><strong>Auto-selection:</strong> Featured and Popular tours are automatically prioritized</li>
                            <li><strong>Image quality:</strong> Use high-resolution images (1920x1080) for best results</li>
                            <li><strong>Performance:</strong> Keep slider to 5-7 slides for optimal loading speed</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.tour-slider-preview {
    width: 60px;
    height: 40px;
    background-size: cover;
    background-position: center;
    border-radius: 8px;
    position: relative;
    overflow: hidden;
}

.tour-slider-preview::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 0;
    height: 0;
    border-left: 8px solid rgba(255, 255, 255, 0.8);
    border-top: 6px solid transparent;
    border-bottom: 6px solid transparent;
    opacity: 0.7;
}

.slider-stats-card {
    background: #1bbc9b;
    color: white;
    border-radius: 15px;
}

.table-success {
    background-color: rgba(25, 135, 84, 0.1) !important;
    border-left: 4px solid #28a745;
}

.btn-group-sm .btn {
    border-radius: 6px;
}

.form-check-input:checked {
    background-color: #28a745;
    border-color: #28a745;
}

.alert-info {
    border-left: 4px solid #0dcaf0;
}

.card {
    transition: all 0.3s ease;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.stats-display {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    border-radius: 10px;
    padding: 15px;
    margin-bottom: 15px;
}
</style>

<script>
function resetSliderOrder() {
    if (confirm('This will reset all tour order numbers. Are you sure?')) {
        // Create forms to reset order for all tours
        let forms = document.querySelectorAll('form input[name="slider_order"]');
        forms.forEach((input, index) => {
            input.value = index + 1;
            input.form.submit();
        });
    }
}

// Auto-submit forms on checkbox change
document.addEventListener('DOMContentLoaded', function() {
    // Add loading indicators
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function() {
            const btn = form.querySelector('button[type="submit"]');
            if (btn) {
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
                btn.disabled = true;
            }
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>
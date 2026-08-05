<?php
require_once '../config/config.php';
require_once '../includes/pickup_times.php';
requireLogin();

ensurePickupTimesSchema();

$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'add') {
        $result = addPickupTimeSlot(
            (string) ($_POST['time_value'] ?? ''),
            (string) ($_POST['label'] ?? ''),
            (int) ($_POST['sort_order'] ?? 0),
            !empty($_POST['is_active'])
        );
        $flash = ['type' => !empty($result['ok']) ? 'success' : 'danger', 'message' => $result['message']];
    }

    if ($action === 'generate') {
        $result = generatePickupTimeSlots(
            (string) ($_POST['start_time'] ?? '09:00'),
            (string) ($_POST['end_time'] ?? '18:00'),
            (int) ($_POST['interval_minutes'] ?? 30),
            !empty($_POST['replace_all'])
        );
        $flash = ['type' => !empty($result['ok']) ? 'success' : 'danger', 'message' => $result['message']];
    }

    if ($action === 'toggle') {
        $id = (int) ($_POST['id'] ?? 0);
        $active = !empty($_POST['is_active']);
        setPickupTimeSlotActive($id, $active);
        $flash = ['type' => 'success', 'message' => $active ? 'Pickup time activated.' : 'Pickup time deactivated.'];
    }

    if ($action === 'update') {
        $id = (int) ($_POST['id'] ?? 0);
        $row = $db->fetch("SELECT time_value FROM pickup_time_slots WHERE id = ?", [$id]);
        if ($row) {
            $result = addPickupTimeSlot(
                (string) ($row['time_value'] ?? ''),
                (string) ($_POST['label'] ?? ''),
                (int) ($_POST['sort_order'] ?? 0),
                !empty($_POST['is_active'])
            );
            $flash = ['type' => !empty($result['ok']) ? 'success' : 'danger', 'message' => $result['message'] ?? 'Updated.'];
        } else {
            $flash = ['type' => 'danger', 'message' => 'Slot not found.'];
        }
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if (deletePickupTimeSlot($id)) {
            $flash = ['type' => 'success', 'message' => 'Pickup time deleted.'];
        } else {
            $flash = ['type' => 'danger', 'message' => 'Could not delete pickup time.'];
        }
    }

    if ($action === 'seed_defaults') {
        $db->execute("DELETE FROM pickup_time_slots");
        seedDefaultPickupTimeSlotsIfEmpty();
        $flash = ['type' => 'success', 'message' => 'Default 9:00 AM – 6:00 PM (30 min) slots restored.'];
    }
}

$slots = getAllPickupTimeSlots(false);
$page_title = 'Pickup Times';
include 'includes/header.php';
?>

<style>
.pickup-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(140px,1fr)); gap:10px; }
.pickup-chip { background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:10px 12px; }
.pickup-chip.is-off { opacity:.55; }
</style>

<div class="container-fluid py-4">
    <?php if ($flash): ?>
        <div class="alert alert-<?php echo htmlspecialchars($flash['type']); ?> alert-dismissible fade show">
            <?php echo htmlspecialchars($flash['message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="mb-1"><i class="fas fa-clock me-2"></i>Pickup Times</h2>
            <p class="text-muted mb-0">Manage global pickup time options. Assign specific times per tour from Tour Edit.</p>
        </div>
        <form method="post" onsubmit="return confirm('Replace all slots with default 9:00 AM – 6:00 PM (every 30 minutes)?');">
            <input type="hidden" name="action" value="seed_defaults">
            <button type="submit" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-undo me-1"></i> Restore defaults
            </button>
        </form>
    </div>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card mb-4">
                <div class="card-header bg-white"><strong>Add pickup time</strong></div>
                <div class="card-body">
                    <form method="post" class="row g-3">
                        <input type="hidden" name="action" value="add">
                        <div class="col-md-6">
                            <label class="form-label">Time (24h)</label>
                            <input type="time" name="time_value" class="form-control" required step="300">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Label (optional)</label>
                            <input type="text" name="label" class="form-control" placeholder="e.g. 9:00 AM">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Sort order</label>
                            <input type="number" name="sort_order" class="form-control" value="0" min="0">
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_active" id="addActive" value="1" checked>
                                <label class="form-check-label" for="addActive">Active</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-plus me-1"></i> Add time</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-white"><strong>Generate time range</strong></div>
                <div class="card-body">
                    <form method="post" class="row g-3">
                        <input type="hidden" name="action" value="generate">
                        <div class="col-md-4">
                            <label class="form-label">Start</label>
                            <input type="time" name="start_time" class="form-control" value="09:00" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">End</label>
                            <input type="time" name="end_time" class="form-control" value="18:00" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Interval (min)</label>
                            <input type="number" name="interval_minutes" class="form-control" value="30" min="5" max="120" step="5" required>
                        </div>
                        <div class="col-12">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="replace_all" id="replaceAll" value="1">
                                <label class="form-check-label" for="replaceAll">Replace all existing slots</label>
                            </div>
                            <button type="submit" class="btn btn-success"><i class="fas fa-magic me-1"></i> Generate slots</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <strong>Current slots (<?php echo count($slots); ?>)</strong>
                    <span class="text-muted small">Inactive slots stay hidden on checkout</span>
                </div>
                <div class="card-body">
                    <?php if (empty($slots)): ?>
                        <p class="text-muted mb-0">No pickup times yet. Generate a range or restore defaults.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table align-middle">
                                <thead>
                                    <tr>
                                        <th>Time</th>
                                        <th>Label</th>
                                        <th>Sort</th>
                                        <th>Status</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($slots as $slot): ?>
                                        <tr class="<?php echo empty($slot['is_active']) ? 'table-light' : ''; ?>">
                                            <td><code><?php echo htmlspecialchars($slot['value']); ?></code></td>
                                            <td>
                                                <form method="post" class="d-flex gap-2">
                                                    <input type="hidden" name="action" value="update">
                                                    <input type="hidden" name="id" value="<?php echo (int) $slot['id']; ?>">
                                                    <input type="hidden" name="is_active" value="<?php echo !empty($slot['is_active']) ? '1' : '0'; ?>">
                                                    <input type="text" name="label" class="form-control form-control-sm" value="<?php echo htmlspecialchars($slot['label']); ?>">
                                                    <input type="number" name="sort_order" class="form-control form-control-sm" style="max-width:90px" value="<?php echo (int) $slot['sort_order']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-primary">Save</button>
                                                </form>
                                            </td>
                                            <td><?php echo (int) $slot['sort_order']; ?></td>
                                            <td>
                                                <form method="post">
                                                    <input type="hidden" name="action" value="toggle">
                                                    <input type="hidden" name="id" value="<?php echo (int) $slot['id']; ?>">
                                                    <?php if (!empty($slot['is_active'])): ?>
                                                        <input type="hidden" name="is_active" value="0">
                                                        <button type="submit" class="btn btn-sm btn-success">Active</button>
                                                    <?php else: ?>
                                                        <input type="hidden" name="is_active" value="1">
                                                        <button type="submit" class="btn btn-sm btn-secondary">Inactive</button>
                                                    <?php endif; ?>
                                                </form>
                                            </td>
                                            <td>
                                                <form method="post" onsubmit="return confirm('Delete this pickup time?');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo (int) $slot['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                                </form>
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
</div>

<?php include 'includes/footer.php'; ?>

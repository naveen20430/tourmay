<?php
require_once '../config/config.php';
requireLogin();

// Handle status updates
if ($_POST['action'] ?? '' == 'update_status' && !empty($_POST['contact_id'])) {
    $contact_id = (int)$_POST['contact_id'];
    $status = $_POST['status'] ?? 'new';
    
    $db->execute("UPDATE contact_inquiries SET status = ? WHERE id = ?", [$status, $contact_id]);
    
    header('Location: contacts.php?updated=1');
    exit;
}

// Handle bulk delete
if ($_POST['action'] ?? '' == 'bulk_delete' && !empty($_POST['selected_contacts'])) {
    $ids = array_map('intval', $_POST['selected_contacts']);
    $placeholders = str_repeat('?,', count($ids) - 1) . '?';
    $db->execute("DELETE FROM contact_inquiries WHERE id IN ($placeholders)", $ids);
    
    header('Location: contacts.php?deleted=' . count($ids));
    exit;
}

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

// Build query conditions
$conditions = [];
$params = [];

if (!empty($status_filter)) {
    $conditions[] = "status = ?";
    $params[] = $status_filter;
}

if (!empty($search)) {
    $conditions[] = "(name LIKE ? OR email LIKE ? OR subject LIKE ? OR message LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Get total count
$count_query = "SELECT COUNT(*) as total FROM contact_inquiries $where_clause";
$total_result = $db->fetch($count_query, $params);
$total_contacts = $total_result['total'];
$total_pages = ceil($total_contacts / $per_page);

// Get contacts
$query = "SELECT * FROM contact_inquiries $where_clause ORDER BY created_at DESC LIMIT $per_page OFFSET $offset";
$contacts = $db->fetchAll($query, $params);

// Get status counts for dashboard
$status_counts = $db->fetchAll("
    SELECT status, COUNT(*) as count 
    FROM contact_inquiries 
    GROUP BY status
");

$page_title = 'Contact Messages';
include 'includes/header.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Contact Messages</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Contact Messages</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            
            <?php if (isset($_GET['updated'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle mr-2"></i>
                    Contact message status updated successfully!
                    <button type="button" class="close" data-dismiss="alert">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['deleted'])): ?>
                <div class="alert alert-info alert-dismissible fade show">
                    <i class="fas fa-trash mr-2"></i>
                    <?php echo (int)$_GET['deleted']; ?> contact message(s) deleted successfully!
                    <button type="button" class="close" data-dismiss="alert">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>

            <!-- Status Cards -->
            <div class="row mb-4">
                <?php
                $status_info = [
                    'new' => ['New Messages', 'bg-info', 'fas fa-envelope'],
                    'read' => ['Read Messages', 'bg-warning', 'fas fa-envelope-open'],
                    'replied' => ['Replied Messages', 'bg-success', 'fas fa-reply']
                ];
                
                foreach ($status_info as $status => $info):
                    $count = 0;
                    foreach ($status_counts as $status_count) {
                        if ($status_count['status'] == $status) {
                            $count = $status_count['count'];
                            break;
                        }
                    }
                ?>
                    <div class="col-lg-3 col-6">
                        <div class="small-box <?php echo $info[1]; ?>">
                            <div class="inner">
                                <h3><?php echo $count; ?></h3>
                                <p><?php echo $info[0]; ?></p>
                            </div>
                            <div class="icon">
                                <i class="<?php echo $info[2]; ?>"></i>
                            </div>
                            <a href="?status=<?php echo $status; ?>" class="small-box-footer">
                                View Details <i class="fas fa-arrow-circle-right"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-primary">
                        <div class="inner">
                            <h3><?php echo $total_contacts; ?></h3>
                            <p>Total Messages</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-comments"></i>
                        </div>
                        <a href="?" class="small-box-footer">
                            View All <i class="fas fa-arrow-circle-right"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Filter Messages</h3>
                </div>
                <div class="card-body">
                    <form method="GET" action="">
                        <div class="row">
                            <div class="col-md-3">
                                <select name="status" class="form-control">
                                    <option value="">All Status</option>
                                    <option value="new" <?php echo $status_filter == 'new' ? 'selected' : ''; ?>>New</option>
                                    <option value="read" <?php echo $status_filter == 'read' ? 'selected' : ''; ?>>Read</option>
                                    <option value="replied" <?php echo $status_filter == 'replied' ? 'selected' : ''; ?>>Replied</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <input type="text" name="search" class="form-control" placeholder="Search messages..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search mr-1"></i> Filter
                                </button>
                                <a href="contacts.php" class="btn btn-secondary ml-1">
                                    <i class="fas fa-times mr-1"></i> Clear
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Messages Table -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Contact Messages</h3>
                </div>
                
                <form method="POST" action="">
                    <input type="hidden" name="action" value="bulk_delete">
                    
                    <div class="card-body table-responsive p-0">
                        <table class="table table-hover text-nowrap">
                            <thead>
                                <tr>
                                    <th><input type="checkbox" id="select-all"></th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Subject</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($contacts)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4">
                                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                            <p class="text-muted">No contact messages found.</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($contacts as $contact): ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" name="selected_contacts[]" value="<?php echo $contact['id']; ?>">
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($contact['name']); ?></strong>
                                                <?php if ($contact['phone']): ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($contact['phone']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="mailto:<?php echo htmlspecialchars($contact['email']); ?>">
                                                    <?php echo htmlspecialchars($contact['email']); ?>
                                                </a>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($contact['subject']); ?></strong>
                                                <br><small class="text-muted">
                                                    <?php echo substr(htmlspecialchars($contact['message']), 0, 80) . (strlen($contact['message']) > 80 ? '...' : ''); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php 
                                                    echo $contact['status'] == 'new' ? 'info' : 
                                                         ($contact['status'] == 'read' ? 'warning' : 'success'); 
                                                ?>">
                                                    <?php echo ucfirst($contact['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small><?php echo date('M d, Y H:i', strtotime($contact['created_at'])); ?></small>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <button type="button" class="btn btn-sm btn-info" data-toggle="modal" data-target="#viewModal<?php echo $contact['id']; ?>">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-warning" data-toggle="modal" data-target="#statusModal<?php echo $contact['id']; ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>

                                        <!-- View Modal -->
                                        <div class="modal fade" id="viewModal<?php echo $contact['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog modal-lg">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h4 class="modal-title">Contact Message Details</h4>
                                                        <button type="button" class="close" data-dismiss="modal">
                                                            <span>&times;</span>
                                                        </button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <strong>Name:</strong> <?php echo htmlspecialchars($contact['name']); ?><br>
                                                                <strong>Email:</strong> <?php echo htmlspecialchars($contact['email']); ?><br>
                                                                <?php if ($contact['phone']): ?>
                                                                    <strong>Phone:</strong> <?php echo htmlspecialchars($contact['phone']); ?><br>
                                                                <?php endif; ?>
                                                                <strong>Subject:</strong> <?php echo htmlspecialchars($contact['subject']); ?><br>
                                                                <strong>Date:</strong> <?php echo date('M d, Y H:i', strtotime($contact['created_at'])); ?><br>
                                                                <strong>Status:</strong> <span class="badge badge-<?php 
                                                                    echo $contact['status'] == 'new' ? 'info' : 
                                                                         ($contact['status'] == 'read' ? 'warning' : 'success'); 
                                                                ?>"><?php echo ucfirst($contact['status']); ?></span>
                                                            </div>
                                                        </div>
                                                        <hr>
                                                        <strong>Message:</strong>
                                                        <div class="mt-2 p-3 bg-light rounded">
                                                            <?php echo nl2br(htmlspecialchars($contact['message'])); ?>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <a href="mailto:<?php echo htmlspecialchars($contact['email']); ?>?subject=Re: <?php echo urlencode($contact['subject']); ?>" class="btn btn-primary">
                                                            <i class="fas fa-reply mr-1"></i> Reply via Email
                                                        </a>
                                                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Status Update Modal -->
                                        <div class="modal fade" id="statusModal<?php echo $contact['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <form method="POST" action="">
                                                        <input type="hidden" name="action" value="update_status">
                                                        <input type="hidden" name="contact_id" value="<?php echo $contact['id']; ?>">
                                                        
                                                        <div class="modal-header">
                                                            <h4 class="modal-title">Update Status</h4>
                                                            <button type="button" class="close" data-dismiss="modal">
                                                                <span>&times;</span>
                                                            </button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="form-group">
                                                                <label>Status</label>
                                                                <select name="status" class="form-control" required>
                                                                    <option value="new" <?php echo $contact['status'] == 'new' ? 'selected' : ''; ?>>New</option>
                                                                    <option value="read" <?php echo $contact['status'] == 'read' ? 'selected' : ''; ?>>Read</option>
                                                                    <option value="replied" <?php echo $contact['status'] == 'replied' ? 'selected' : ''; ?>>Replied</option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="submit" class="btn btn-primary">Update Status</button>
                                                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if (!empty($contacts)): ?>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete selected messages?')">
                                <i class="fas fa-trash mr-1"></i> Delete Selected
                            </button>
                        </div>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="d-flex justify-content-center">
                    <nav aria-label="Contacts pagination">
                        <ul class="pagination">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                </li>
                            <?php endif; ?>

                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>

        </div>
    </section>
</div>

<script>
// Select all functionality
document.getElementById('select-all').addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('input[name="selected_contacts[]"]');
    checkboxes.forEach(checkbox => {
        checkbox.checked = this.checked;
    });
});

// Auto-check select-all when all items are selected
document.querySelectorAll('input[name="selected_contacts[]"]').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('input[name="selected_contacts[]"]');
        const selectAll = document.getElementById('select-all');
        selectAll.checked = Array.from(checkboxes).every(cb => cb.checked);
    });
});
</script>

<?php include 'includes/footer.php'; ?>
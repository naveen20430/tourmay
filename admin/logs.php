<?php
require_once '../config/config.php';
requireLogin();

$logsDir = realpath(dirname(__DIR__) . '/logs');
$debugFlag = dirname(__DIR__) . '/config/debug.enabled';

$allowedLogs = [
    'checkout' => [
        'file' => 'checkout.log',
        'label' => 'Checkout / Razorpay',
        'icon' => 'fa-credit-card',
        'desc' => 'Cart checkout, order create/verify, payment failures',
    ],
    'php' => [
        'file' => 'php-error.log',
        'label' => 'PHP Errors',
        'icon' => 'fa-bug',
        'desc' => 'PHP warnings and fatal errors',
    ],
    'otp' => [
        'file' => 'otp.log',
        'label' => 'OTP',
        'icon' => 'fa-key',
        'desc' => 'Email / SMS OTP send history',
    ],
    'error' => [
        'file' => 'error_log',
        'label' => 'Server error_log',
        'icon' => 'fa-server',
        'desc' => 'Legacy / server error_log in web root (if present)',
        'alt_paths' => [dirname(__DIR__) . '/error_log'],
    ],
];

$flash = null;

function adminLogsResolvePath(string $logsDir, array $meta): ?string
{
    $candidates = [];
    if ($logsDir) {
        $candidates[] = $logsDir . DIRECTORY_SEPARATOR . $meta['file'];
    }
    foreach ($meta['alt_paths'] ?? [] as $alt) {
        $candidates[] = $alt;
    }
    foreach ($candidates as $path) {
        $real = realpath($path);
        if ($real && is_file($real)) {
            return $real;
        }
        // Allow empty not-yet-created log files under logs/
        if ($logsDir && str_starts_with($path, $logsDir) && is_dir($logsDir)) {
            return $path;
        }
    }
    return null;
}

function adminLogsTail(string $path, int $lines = 200): array
{
    if (!is_file($path) || !is_readable($path)) {
        return ['lines' => [], 'size' => 0, 'mtime' => null, 'exists' => false];
    }

    $size = filesize($path);
    $mtime = filemtime($path);
    $lines = max(20, min(2000, $lines));

    $buffer = '';
    $chunk = 8192;
    $fp = @fopen($path, 'rb');
    if (!$fp) {
        return ['lines' => ['(Unable to open log file)'], 'size' => $size, 'mtime' => $mtime, 'exists' => true];
    }

    $pos = $size;
    $lineCount = 0;
    while ($pos > 0 && $lineCount <= $lines) {
        $read = ($pos >= $chunk) ? $chunk : $pos;
        $pos -= $read;
        fseek($fp, $pos);
        $buffer = fread($fp, $read) . $buffer;
        $lineCount = substr_count($buffer, "\n");
    }
    fclose($fp);

    $all = preg_split("/\r\n|\n|\r/", $buffer);
    $all = array_values(array_filter($all, static function ($l) {
        return $l !== '';
    }));
    $tail = array_slice($all, -$lines);

    return [
        'lines' => $tail,
        'size' => $size,
        'mtime' => $mtime,
        'exists' => true,
    ];
}

function adminLogsFormatBytes(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    if ($bytes < 1048576) {
        return round($bytes / 1024, 1) . ' KB';
    }
    return round($bytes / 1048576, 2) . ' MB';
}

$selected = trim((string) ($_GET['log'] ?? 'checkout'));
if (!isset($allowedLogs[$selected])) {
    $selected = 'checkout';
}

$linesRequested = (int) ($_GET['lines'] ?? 200);
$linesRequested = max(50, min(1000, $linesRequested));
$filter = trim((string) ($_GET['q'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $tokenOk = true; // admin session already required

    if ($action === 'toggle_debug' && $tokenOk) {
        if (is_file($debugFlag)) {
            @unlink($debugFlag);
            $flash = ['type' => 'success', 'message' => 'Checkout debug mode turned OFF. Payment failures still log.'];
        } else {
            @file_put_contents($debugFlag, '');
            $flash = ['type' => 'success', 'message' => 'Checkout debug mode turned ON. Verbose events write to checkout.log.'];
        }
    }

    if ($action === 'clear_log' && $tokenOk) {
        $clearKey = (string) ($_POST['log'] ?? '');
        if (isset($allowedLogs[$clearKey])) {
            $path = adminLogsResolvePath((string) $logsDir, $allowedLogs[$clearKey]);
            if ($path && is_file($path) && is_writable($path)) {
                // Only allow clearing files under /logs (never web-root error_log via alt unless under logs)
                $logsReal = $logsDir ? realpath($logsDir) : false;
                $fileReal = realpath($path);
                if ($logsReal && $fileReal && str_starts_with($fileReal, $logsReal . DIRECTORY_SEPARATOR)) {
                    file_put_contents($path, '');
                    $flash = ['type' => 'success', 'message' => 'Cleared ' . $allowedLogs[$clearKey]['label'] . ' log.'];
                    $selected = $clearKey;
                } else {
                    $flash = ['type' => 'danger', 'message' => 'That log cannot be cleared from the admin panel.'];
                }
            } else {
                $flash = ['type' => 'warning', 'message' => 'Log file not found or not writable.'];
            }
        }
    }

    if ($action === 'refresh') {
        $selected = (string) ($_POST['log'] ?? $selected);
    }
}

$meta = $allowedLogs[$selected];
$path = adminLogsResolvePath((string) $logsDir, $meta);
$tail = $path
    ? adminLogsTail($path, $linesRequested)
    : ['lines' => [], 'size' => 0, 'mtime' => null, 'exists' => false];

$displayLines = $tail['lines'];
if ($filter !== '') {
    $displayLines = array_values(array_filter($displayLines, static function ($line) use ($filter) {
        return stripos($line, $filter) !== false;
    }));
}

$debugOn = is_file($debugFlag);
$page_title = 'System Logs';
include 'includes/header.php';
?>

<style>
.logs-toolbar .nav-link { border-radius: 8px; margin-right: 6px; margin-bottom: 6px; }
.logs-toolbar .nav-link.active { background: #2c3e50; color: #fff !important; }
.log-viewer {
    background: #0f172a;
    color: #e2e8f0;
    border-radius: 12px;
    padding: 16px;
    max-height: 70vh;
    overflow: auto;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
    font-size: 12.5px;
    line-height: 1.45;
    white-space: pre-wrap;
    word-break: break-word;
}
.log-viewer .log-line { display: block; padding: 2px 0; border-bottom: 1px solid rgba(148,163,184,.12); }
.log-viewer .log-line:hover { background: rgba(148,163,184,.08); }
.log-empty { color: #94a3b8; font-style: italic; }
.logs-meta { color: #64748b; font-size: .9rem; }
.badge-debug-on { background: #16a34a; }
.badge-debug-off { background: #64748b; }
</style>

<div class="container-fluid py-4">
    <?php if ($flash): ?>
        <div class="alert alert-<?php echo htmlspecialchars($flash['type']); ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($flash['message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div>
            <h2 class="mb-1"><i class="fas fa-file-alt me-2"></i>System Logs</h2>
            <p class="text-muted mb-0">Checkout, payment, OTP, and PHP error logs for debugging.</p>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <span class="badge <?php echo $debugOn ? 'badge-debug-on' : 'badge-debug-off'; ?> rounded-pill px-3 py-2">
                Debug <?php echo $debugOn ? 'ON' : 'OFF'; ?>
            </span>
            <form method="post" class="d-inline">
                <input type="hidden" name="action" value="toggle_debug">
                <button type="submit" class="btn btn-<?php echo $debugOn ? 'outline-secondary' : 'success'; ?> btn-sm">
                    <i class="fas fa-bug me-1"></i>
                    <?php echo $debugOn ? 'Turn Debug Off' : 'Turn Debug On'; ?>
                </button>
            </form>
            <a class="btn btn-outline-primary btn-sm" href="logs.php?log=<?php echo urlencode($selected); ?>&lines=<?php echo (int) $linesRequested; ?>&q=<?php echo urlencode($filter); ?>">
                <i class="fas fa-sync-alt me-1"></i> Refresh
            </a>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body logs-toolbar">
            <nav class="nav nav-pills flex-wrap mb-3">
                <?php foreach ($allowedLogs as $key => $item): ?>
                    <?php
                    $itemPath = adminLogsResolvePath((string) $logsDir, $item);
                    $itemSize = ($itemPath && is_file($itemPath)) ? filesize($itemPath) : 0;
                    ?>
                    <a class="nav-link <?php echo $selected === $key ? 'active' : ''; ?>"
                       href="logs.php?log=<?php echo urlencode($key); ?>&lines=<?php echo (int) $linesRequested; ?>">
                        <i class="fas <?php echo htmlspecialchars($item['icon']); ?> me-1"></i>
                        <?php echo htmlspecialchars($item['label']); ?>
                        <span class="badge bg-light text-dark ms-1"><?php echo adminLogsFormatBytes((int) $itemSize); ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>

            <form method="get" class="row g-2 align-items-end">
                <input type="hidden" name="log" value="<?php echo htmlspecialchars($selected); ?>">
                <div class="col-md-3">
                    <label class="form-label">Lines</label>
                    <select name="lines" class="form-select">
                        <?php foreach ([100, 200, 500, 1000] as $n): ?>
                            <option value="<?php echo $n; ?>" <?php echo $linesRequested === $n ? 'selected' : ''; ?>><?php echo $n; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Filter</label>
                    <input type="text" name="q" class="form-control" value="<?php echo htmlspecialchars($filter); ?>" placeholder="e.g. razorpay, invoice, OTP, Exception">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-1"></i> Apply</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <strong><i class="fas <?php echo htmlspecialchars($meta['icon']); ?> me-2"></i><?php echo htmlspecialchars($meta['label']); ?></strong>
                <div class="logs-meta"><?php echo htmlspecialchars($meta['desc']); ?></div>
                <div class="logs-meta">
                    <?php if (!empty($tail['exists'])): ?>
                        Size <?php echo adminLogsFormatBytes((int) $tail['size']); ?>
                        <?php if (!empty($tail['mtime'])): ?>
                            · Updated <?php echo date('Y-m-d H:i:s', (int) $tail['mtime']); ?>
                        <?php endif; ?>
                        · Showing <?php echo count($displayLines); ?> line(s)
                        <?php if ($filter !== ''): ?> (filtered)<?php endif; ?>
                    <?php else: ?>
                        File not created yet
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($selected !== 'error'): ?>
                <form method="post" onsubmit="return confirm('Clear this log file? This cannot be undone.');">
                    <input type="hidden" name="action" value="clear_log">
                    <input type="hidden" name="log" value="<?php echo htmlspecialchars($selected); ?>">
                    <button type="submit" class="btn btn-outline-danger btn-sm">
                        <i class="fas fa-trash me-1"></i> Clear log
                    </button>
                </form>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <div class="log-viewer" id="logViewer">
                <?php if (empty($displayLines)): ?>
                    <span class="log-empty">No log entries<?php echo $filter !== '' ? ' match this filter' : ' yet'; ?>.</span>
                <?php else: ?>
                    <?php foreach ($displayLines as $line): ?>
                        <span class="log-line"><?php echo htmlspecialchars($line); ?></span>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    var viewer = document.getElementById('logViewer');
    if (viewer) viewer.scrollTop = viewer.scrollHeight;
})();
</script>

<?php include 'includes/footer.php'; ?>

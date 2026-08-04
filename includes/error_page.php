<?php
/**
 * Shared bootstrap for public error pages.
 * Survives missing DB / broken config so 500 pages still render.
 */
$twjErrorPage = true;
$siteName = 'The World Journey';
$baseUrl = '/';
$configOk = false;

try {
    $configPath = __DIR__ . '/config/config.php';
    if (is_file($configPath)) {
        require_once $configPath;
        $configOk = true;
        if (function_exists('getSetting')) {
            $name = trim((string) getSetting('site_name'));
            if ($name !== '') {
                $siteName = $name;
            }
        }
        if (defined('BASE_URL')) {
            $baseUrl = BASE_URL;
        }
    }
} catch (Throwable $e) {
    $configOk = false;
}

if (!$configOk) {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $host = $_SERVER['HTTP_HOST'] ?? 'theworldjourney.in';
    $baseUrl = ($https ? 'https://' : 'http://') . $host . '/';
}

if (!function_exists('navUrl')) {
    function navUrl($page) {
        global $baseUrl;
        $map = [
            'home' => '',
            'tours' => 'tours',
            'destinations' => 'destinations',
            'contact' => 'contact',
            'cart' => 'cart',
            'about' => 'about-us',
        ];
        $slug = $map[$page] ?? ltrim((string) $page, '/');
        return rtrim($baseUrl, '/') . '/' . $slug;
    }
}

function twjRenderErrorPage(array $opts): void
{
    global $siteName, $baseUrl, $configOk;

    $code = (int) ($opts['code'] ?? 500);
    $title = (string) ($opts['title'] ?? 'Something went wrong');
    $message = (string) ($opts['message'] ?? 'Please try again in a moment.');
    $hint = (string) ($opts['hint'] ?? '');
    $pageTitle = $title . ' (' . $code . ') - ' . $siteName;

    if (!headers_sent()) {
        http_response_code($code > 0 ? $code : 500);
        header('Content-Type: text/html; charset=UTF-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    }

    $homeUrl = function_exists('navUrl') ? navUrl('home') : $baseUrl;
    $toursUrl = function_exists('navUrl') ? navUrl('tours') : rtrim($baseUrl, '/') . '/tours';
    $contactUrl = function_exists('navUrl') ? navUrl('contact') : rtrim($baseUrl, '/') . '/contact';
    $requested = htmlspecialchars((string) ($_SERVER['REQUEST_URI'] ?? ''), ENT_QUOTES, 'UTF-8');

    // Prefer full site chrome when config works; otherwise standalone HTML.
    if ($configOk && is_file(__DIR__ . '/includes/header.php') && is_file(__DIR__ . '/includes/footer.php')) {
        $page_title = $pageTitle;
        $current_page = 'error';
        $extra_css = '<style>
.twj-error{padding:70px 0 90px;background:linear-gradient(180deg,#f8f9ff 0%,#ffffff 55%)}
.twj-error__card{max-width:720px;margin:0 auto;background:#fff;border:1px solid rgba(102,126,234,.12);border-radius:20px;box-shadow:0 18px 50px rgba(118,75,162,.10);padding:42px 28px;text-align:center}
.twj-error__code{font-size:clamp(4rem,12vw,6.5rem);font-weight:800;line-height:1;letter-spacing:-2px;background:linear-gradient(135deg,#667eea,#764ba2);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;margin:0 0 8px}
.twj-error__title{font-size:1.75rem;font-weight:800;color:#0f172a;margin:0 0 12px}
.twj-error__text{color:#64748b;font-size:1.05rem;line-height:1.7;margin:0 auto 10px;max-width:34rem}
.twj-error__hint{color:#94a3b8;font-size:.92rem;margin:0 0 28px}
.twj-error__path{display:inline-block;max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;background:#f1f5f9;color:#475569;border-radius:999px;padding:6px 14px;font-size:.85rem;margin:8px 0 22px}
.twj-error__actions{display:flex;flex-wrap:wrap;gap:12px;justify-content:center}
.twj-error__actions .btn{min-width:150px;border-radius:10px;font-weight:700}
.twj-error__actions .btn-primary{background:linear-gradient(135deg,#667eea,#764ba2);border:0}
</style>';
        include __DIR__ . '/includes/header.php';
        ?>
<section class="page-header" style="background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);padding:100px 0 50px;">
    <div class="container text-center">
        <h1 class="text-white mb-2" style="font-weight:800;"><?php echo htmlspecialchars($title); ?></h1>
        <p class="text-white-50 mb-0">Error <?php echo (int) $code; ?></p>
    </div>
</section>
<section class="twj-error">
    <div class="container">
        <div class="twj-error__card">
            <div class="twj-error__code"><?php echo (int) $code; ?></div>
            <h2 class="twj-error__title"><?php echo htmlspecialchars($title); ?></h2>
            <p class="twj-error__text"><?php echo htmlspecialchars($message); ?></p>
            <?php if ($requested !== '' && $code === 404): ?>
                <div class="twj-error__path" title="<?php echo $requested; ?>">Requested: <?php echo $requested; ?></div>
            <?php endif; ?>
            <?php if ($hint !== ''): ?>
                <p class="twj-error__hint"><?php echo htmlspecialchars($hint); ?></p>
            <?php endif; ?>
            <div class="twj-error__actions">
                <a class="btn btn-primary text-white" href="<?php echo htmlspecialchars($homeUrl); ?>"><i class="fas fa-home me-1"></i> Home</a>
                <a class="btn btn-outline-primary" href="<?php echo htmlspecialchars($toursUrl); ?>"><i class="fas fa-map-marked-alt me-1"></i> Tours</a>
                <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars($contactUrl); ?>"><i class="fas fa-envelope me-1"></i> Contact</a>
            </div>
        </div>
    </div>
</section>
        <?php
        include __DIR__ . '/includes/footer.php';
        return;
    }

    // Standalone fallback (no theme / DB)
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>' . htmlspecialchars($pageTitle) . '</title>';
    echo '<style>
body{margin:0;font-family:Geologica,system-ui,sans-serif;background:linear-gradient(180deg,#f8f9ff,#fff);color:#0f172a}
.wrap{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:32px 16px}
.card{max-width:640px;width:100%;background:#fff;border:1px solid #e2e8f0;border-radius:20px;box-shadow:0 18px 50px rgba(118,75,162,.1);padding:40px 24px;text-align:center}
.code{font-size:5rem;font-weight:800;line-height:1;background:linear-gradient(135deg,#667eea,#764ba2);-webkit-background-clip:text;color:transparent}
h1{margin:8px 0 12px;font-size:1.6rem}
p{color:#64748b;line-height:1.6}
.actions{display:flex;flex-wrap:wrap;gap:10px;justify-content:center;margin-top:24px}
a.btn{display:inline-block;padding:12px 18px;border-radius:10px;text-decoration:none;font-weight:700}
a.primary{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff}
a.ghost{border:1px solid #cbd5e1;color:#334155}
</style></head><body><div class="wrap"><div class="card">';
    echo '<div class="code">' . (int) $code . '</div>';
    echo '<h1>' . htmlspecialchars($title) . '</h1>';
    echo '<p>' . htmlspecialchars($message) . '</p>';
    if ($hint !== '') {
        echo '<p>' . htmlspecialchars($hint) . '</p>';
    }
    echo '<div class="actions">';
    echo '<a class="btn primary" href="' . htmlspecialchars($homeUrl) . '">Home</a>';
    echo '<a class="btn ghost" href="' . htmlspecialchars($toursUrl) . '">Tours</a>';
    echo '<a class="btn ghost" href="' . htmlspecialchars($contactUrl) . '">Contact</a>';
    echo '</div></div></div></body></html>';
}

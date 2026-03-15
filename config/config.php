<?php
session_start();

// Define constants - Dynamic Base URL
// Auto-detect the base URL based on current server and directory
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];
$scriptPath = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
$basePath = rtrim($scriptPath, '/');

// Remove common subdirectories from the path to get the project root
$pathParts = explode('/', trim($basePath, '/'));
$projectRoot = '';
foreach ($pathParts as $part) {
    if ($part && !in_array($part, ['admin', 'includes', 'config'])) {
        $projectRoot .= '/' . $part;
    }
}

define('BASE_URL', $protocol . $host . $projectRoot . '/');
define('BASE_PATH', dirname(dirname(__FILE__)) . '/');
define('UPLOAD_PATH', BASE_PATH . 'uploads/');
define('UPLOAD_URL', BASE_URL . 'uploads/');

// Include database class
require_once BASE_PATH . 'config/database.php';

// Include URL helpers
require_once BASE_PATH . 'includes/url_helpers.php';

// Initialize database connection
$db = new Database();

// Helper functions
function getSetting($key) {
    global $db;
    $result = $db->fetch("SELECT setting_value FROM site_settings WHERE setting_key = ?", [$key]);
    return $result ? $result['setting_value'] : '';
}

function formatPrice($price) {
    return '₹' . number_format($price, 2);
}

function formatPriceINR($price) {
    // Indian number formatting with commas
    return '₹' . number_format($price, 0);
}

function formatDate($date) {
    return date('M d, Y', strtotime($date));
}

function generateSlug($text) {
    return strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $text), '-'));
}

function uploadFile($file, $directory) {
    // Check for upload errors first
    $uploadErrors = getUploadError($file);
    if (!empty($uploadErrors)) {
        return false;
    }
    
    // Use assets/images directory for uploads
    $uploadDir = BASE_PATH . 'assets/images/' . $directory . '/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Generate unique filename
    $fileName = date('Y-m-d') . '_' . time() . '_' . basename($file['name']);
    $uploadPath = $uploadDir . $fileName;
    
    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        return 'assets/images/' . $directory . '/' . $fileName;
    }
    return false;
}

function getUploadError($file) {
    $errors = [];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        switch ($file['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $errors[] = 'File is too large';
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
                $errors[] = 'Unknown upload error';
        }
        return $errors;
    }
    
    // Check file type
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    
    // Check MIME type using finfo if available, fallback to $_FILES type
    $mimeType = $file['type'];
    if (function_exists('finfo_open') && $file['tmp_name'] && is_uploaded_file($file['tmp_name'])) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if ($detectedType) {
            $mimeType = $detectedType;
        }
    }
    
    if (!in_array($mimeType, $allowedTypes)) {
        $errors[] = 'Invalid file type. Only JPEG, PNG, GIF, and WebP images are allowed. Detected: ' . $mimeType;
    }
    
    // Check file size (5MB max)
    if ($file['size'] > 5 * 1024 * 1024) {
        $errors[] = 'File is too large. Maximum size is 5MB';
    }
    
    return $errors;
}

function isLoggedIn() {
    return isset($_SESSION['admin_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . 'admin/login.php');
        exit;
    }
}

// User authentication functions
function isUserLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getCurrentUser() {
    if (isUserLoggedIn()) {
        return [
            'id' => $_SESSION['user_id'],
            'name' => $_SESSION['user_name'] ?? '',
            'email' => $_SESSION['user_email'] ?? '',
            'first_name' => $_SESSION['user_first_name'] ?? ''
        ];
    }
    return null;
}

function requireUserLogin() {
    if (!isUserLoggedIn()) {
        header('Location: ' . BASE_URL . 'login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
}

// Auto-load classes
spl_autoload_register(function ($class) {
    $classFile = BASE_PATH . 'classes/' . $class . '.php';
    if (file_exists($classFile)) {
        require_once $classFile;
    }
});

/**
 * Get cache-busting version for CSS/JS files
 * Returns file modification time as version to clear cache automatically
 * 
 * @param string $filePath Relative path from BASE_PATH (e.g., 'assets/css/index.css')
 * @return string Version number based on file modification time
 */
function getCacheVersion($filePath) {
    $fullPath = BASE_PATH . $filePath;
    if (file_exists($fullPath)) {
        return filemtime($fullPath);
    }
    // Return current timestamp if file doesn't exist (fallback)
    return time();
}

/**
 * Generate CSS link with cache busting
 * 
 * @param string $filePath Relative path from BASE_PATH (e.g., 'assets/css/index.css')
 * @return string HTML link tag with cache-busting version
 */
function cssWithCache($filePath) {
    $version = getCacheVersion($filePath);
    return '<link rel="stylesheet" href="' . BASE_URL . $filePath . '?v=' . $version . '" />';
}
?>

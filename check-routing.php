#!/usr/bin/env php
<?php
echo "\n╔════════════════════════════════════════════════════════════╗\n";
echo "║        🔍 TourHub Routing Verification Script              ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

$errors = [];
$warnings = [];
$success = [];

// Check 1: .htaccess exists
echo "[1] Checking .htaccess file...\n";
if (file_exists('.htaccess')) {
    $success[] = ".htaccess file exists";
    echo "    ✅ .htaccess found\n";
} else {
    $errors[] = ".htaccess not found";
    echo "    ❌ .htaccess not found\n";
}
echo "\n";

// Check 2: routing-fix.js exists
echo "[2] Checking routing-fix.js...\n";
if (file_exists('assets/js/routing-fix.js')) {
    $success[] = "routing-fix.js exists";
    echo "    ✅ routing-fix.js found\n";
} else {
    $errors[] = "routing-fix.js not found";
    echo "    ❌ routing-fix.js not found\n";
}
echo "\n";

// Check 3: Check main PHP files
echo "[3] Checking main PHP files...\n";
$php_files = ['index.php', 'tours.php', 'tour-details.php', 'blog.php'];
foreach ($php_files as $file) {
    if (file_exists($file)) {
        echo "    ✅ $file\n";
    } else {
        $errors[] = "$file not found";
        echo "    ❌ $file\n";
    }
}
echo "\n";

// Check 4: Verify .htaccess has rewrite rules
echo "[4] Checking .htaccess rewrite rules...\n";
if (file_exists('.htaccess')) {
    $content = file_get_contents('.htaccess');
    if (strpos($content, 'RewriteEngine On') !== false) {
        echo "    ✅ Rewrite engine enabled\n";
    } else {
        $warnings[] = "RewriteEngine not found";
        echo "    ⚠️  RewriteEngine not found\n";
    }
    if (strpos($content, 'RewriteRule ^tours') !== false) {
        echo "    ✅ Tours rule found\n";
    } else {
        $warnings[] = "Tours rule not found";
        echo "    ⚠️  Tours rule not found\n";
    }
} else {
    echo "    ⚠️  .htaccess not found, skipping checks\n";
}
echo "\n";

// Check 5: Check if routing-fix.js is included in header
echo "[5] Checking header includes...\n";
if (file_exists('includes/header.php')) {
    $header = file_get_contents('includes/header.php');
    if (strpos($header, 'routing-fix.js') !== false) {
        $success[] = "routing-fix.js included";
        echo "    ✅ routing-fix.js included in header\n";
    } else {
        $warnings[] = "routing-fix.js not in header";
        echo "    ⚠️  routing-fix.js not in header\n";
    }
} else {
    echo "    ❌ header.php not found\n";
}
echo "\n";

// Summary
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║                        SUMMARY                             ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

if (count($errors) > 0) {
    echo "❌ ERRORS (" . count($errors) . "):\n";
    foreach ($errors as $e) echo "   • $e\n";
    echo "\n";
}

if (count($warnings) > 0) {
    echo "⚠️  WARNINGS (" . count($warnings) . "):\n";
    foreach ($warnings as $w) echo "   • $w\n";
    echo "\n";
}

echo "✅ PASSED (" . count($success) . "):\n";
foreach ($success as $s) echo "   • $s\n";
echo "\n";

// Status
if (count($errors) === 0) {
    echo "Status: ✅ Ready to test routing!\n\n";
} else {
    echo "Status: ❌ Fix errors before testing\n\n";
}

echo "📋 NEXT STEPS:\n";
echo "1. Clear browser cache (Ctrl+Shift+Delete)\n";
echo "2. Hard refresh page (Ctrl+F5)\n";
echo "3. Try navigating to:\n";
echo "   • /tours\n";
echo "   • /blog\n";
echo "   • /tour/[tour-slug]\n";
echo "4. Check browser console (F12) for errors\n\n";

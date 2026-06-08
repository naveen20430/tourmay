<?php
require_once '../config/config.php';
requireLogin();

// Test upload configuration
echo "<h1>Upload Configuration Test</h1>";

// Check PHP settings
echo "<h2>PHP Configuration:</h2>";
echo "file_uploads: " . (ini_get('file_uploads') ? 'Enabled' : 'Disabled') . "<br>";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "<br>";
echo "post_max_size: " . ini_get('post_max_size') . "<br>";
echo "max_execution_time: " . ini_get('max_execution_time') . "<br>";
echo "memory_limit: " . ini_get('memory_limit') . "<br>";

// Check directories
echo "<h2>Directory Status:</h2>";
$uploadDir = BASE_PATH . 'assets/images/tours/';
echo "Upload directory: " . $uploadDir . "<br>";
echo "Directory exists: " . (is_dir($uploadDir) ? 'Yes' : 'No') . "<br>";
echo "Directory writable: " . (is_writable($uploadDir) ? 'Yes' : 'No') . "<br>";

if (!is_dir($uploadDir)) {
    echo "Attempting to create directory...<br>";
    if (mkdir($uploadDir, 0755, true)) {
        echo "Directory created successfully!<br>";
    } else {
        echo "Failed to create directory!<br>";
    }
}

// Test form
if ($_POST) {
    echo "<h2>Upload Test Result:</h2>";
    
    if (isset($_FILES['test_image'])) {
        echo "File received:<br>";
        echo "Name: " . $_FILES['test_image']['name'] . "<br>";
        echo "Size: " . $_FILES['test_image']['size'] . " bytes<br>";
        echo "Type: " . $_FILES['test_image']['type'] . "<br>";
        echo "Error: " . $_FILES['test_image']['error'] . "<br>";
        echo "Temp file: " . $_FILES['test_image']['tmp_name'] . "<br>";
        
        if (!empty($_FILES['test_image']['name'])) {
            $uploadErrors = getUploadError($_FILES['test_image']);
            if (!empty($uploadErrors)) {
                echo "<div style='color: red;'>";
                echo "Upload errors:<br>";
                foreach ($uploadErrors as $error) {
                    echo "- " . $error . "<br>";
                }
                echo "</div>";
            } else {
                $result = uploadFile($_FILES['test_image'], 'tours');
                if ($result) {
                    echo "<div style='color: green;'>";
                    echo "Upload successful!<br>";
                    echo "File path: " . $result . "<br>";
                    echo "<img src='../" . $result . "' style='max-width: 200px;'><br>";
                    echo "</div>";
                } else {
                    echo "<div style='color: red;'>Upload failed - server error</div>";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Upload Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="card">
            <div class="card-body">
                <h2>Test Image Upload</h2>
                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label">Select Test Image:</label>
                        <input type="file" name="test_image" class="form-control" accept="image/*" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Test Upload</button>
                    <a href="tours.php" class="btn btn-secondary">Back to Tours</a>
                </form>
            </div>
        </div>
    </div>
</body>
</html>

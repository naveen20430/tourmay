<?php
require_once '../config/config.php';
requireLogin();

if ($_POST && !empty($_FILES['test_file']['name'])) {
    echo "<h2>DEBUG INFO:</h2>";
    echo "<pre>";
    print_r($_FILES['test_file']);
    echo "</pre>";
    
    echo "<h3>Upload attempt:</h3>";
    
    // Basic directory check
    $uploadDir = BASE_PATH . 'assets/images/tours/';
    echo "Upload dir: " . $uploadDir . "<br>";
    echo "Directory exists: " . (is_dir($uploadDir) ? 'Yes' : 'No') . "<br>";
    echo "Directory writable: " . (is_writable($uploadDir) ? 'Yes' : 'No') . "<br>";
    
    // Ensure directory exists
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
        echo "Created directory<br>";
    }
    
    // Simple upload without our functions first
    $fileName = time() . '_' . $_FILES['test_file']['name'];
    $targetPath = $uploadDir . $fileName;
    
    echo "Target path: " . $targetPath . "<br>";
    
    if (move_uploaded_file($_FILES['test_file']['tmp_name'], $targetPath)) {
        echo "<div style='color: green;'>DIRECT UPLOAD SUCCESS!</div>";
        echo "File uploaded to: " . $targetPath . "<br>";
        
        // Show the image
        $webPath = 'assets/images/tours/' . $fileName;
        echo "<img src='../" . $webPath . "' style='max-width: 200px;'><br>";
        
        // Test our uploadFile function
        echo "<h3>Testing our uploadFile function:</h3>";
        $result = uploadFile($_FILES['test_file'], 'tours');
        if ($result) {
            echo "<div style='color: green;'>OUR FUNCTION WORKS TOO: " . $result . "</div>";
        } else {
            echo "<div style='color: red;'>OUR FUNCTION FAILED</div>";
        }
    } else {
        echo "<div style='color: red;'>DIRECT UPLOAD FAILED</div>";
        echo "Error: " . error_get_last()['message'] . "<br>";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Simple Upload Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="card">
            <div class="card-body">
                <h2>Simple Upload Test</h2>
                <p>This test bypasses our validation to check basic upload functionality.</p>
                
                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label">Select Image File:</label>
                        <input type="file" name="test_file" class="form-control" accept="image/*" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Test Upload</button>
                    <a href="tours.php" class="btn btn-secondary">Back to Tours</a>
                </form>
            </div>
        </div>
    </div>
</body>
</html>

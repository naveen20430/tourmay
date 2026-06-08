<?php
// Connect without database first
$host = 'localhost';
$user = 'root';
$pass = '';

try {
    $conn = new PDO("mysql:host=$host", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create database if not exists
    $sql = "CREATE DATABASE IF NOT EXISTS tour_booking";
    $conn->exec($sql);
    echo "Database created successfully.<br>";

    // Select database
    $conn->exec("USE tour_booking");

    // Create admin_users table if not exists
    $sql = "CREATE TABLE IF NOT EXISTS admin_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE,
        password VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $conn->exec($sql);
    echo "Table admin_users created successfully.<br>";

    // Insert default admin if not exists
    $username = 'admin';
    $password = password_hash('admin123', PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT IGNORE INTO admin_users (username, password) VALUES (?, ?)");
    $stmt->execute([$username, $password]);
    echo "Default admin user inserted successfully.<br>";

    echo "Setup complete.";
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
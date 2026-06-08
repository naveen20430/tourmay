<?php include 'includes/db.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Day Hikes / Add-ons</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <h1>Day Hikes / Add-ons</h1>
    <nav>
        <a href="index.php">Home</a>
        <a href="addons.php">Day Hikes / Add-ons</a>
    </nav>
    <?php
    $sql = "SELECT * FROM addons";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            echo '<div class="addon">';
            echo '<h3>' . $row['name'] . '</h3>';
            echo '<p>' . $row['description'] . '</p>';
            echo '<p>Price: ₹' . $row['price'] . '</p>';
            echo '</div>';
        }
    } else {
        echo 'No add-ons available.';
    }
    ?>
</body>
</html>
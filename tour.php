<?php include 'includes/db.php'; ?>
<?php
$id = $_GET['id'];
$sql = "SELECT * FROM tours WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->execute([$id]);
$result = $stmt;
if ($result->rowCount() > 0) {
    $tour = $result->fetch(PDO::FETCH_ASSOC);
} else {
    echo 'Tour not found.';
    exit;
}
$pricings = explode(',', $tour['pricing']);
$vehicles = explode(',', $tour['vehicle_options']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo $tour['title']; ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <h1><?php echo $tour['title']; ?></h1>
    <p><?php echo $tour['description']; ?></p>
    <p>Inclusions: <?php echo $tour['inclusions']; ?></p>
    <form action="book.php" method="post">
        <input type="hidden" name="tour_id" value="<?php echo $id; ?>">
        <label>Name: <input type="text" name="name" required></label><br>
        <label>Email: <input type="email" name="email" required></label><br>
        <label>Date of Travel: <input type="date" name="date_of_travel" required></label><br>
        <label>No. of Pax: <input type="number" name="no_of_pax" required></label><br>
        <label>Contact: <input type="text" name="contact" required></label><br>
        <label>ID Proof: <input type="text" name="id_proof" required></label><br>
        <label>Address: <textarea name="address" required></textarea></label><br>
        <label>Pricing: <select name="pricing" required>
            <?php foreach($pricings as $price) { echo '<option value="' . $price . '">' . $price . '</option>'; } ?>
        </select></label><br>
        <label>Vehicle: <select name="vehicle" required>
            <?php foreach($vehicles as $vehicle) { echo '<option value="' . $vehicle . '">' . $vehicle . '</option>'; } ?>
        </select></label><br>
        <button type="submit">Book Now</button>
    </form>
</body>
</html>
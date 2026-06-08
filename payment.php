<?php include 'includes/db.php'; ?>
<?php
$booking_id = $_GET['booking_id'];
$sql = "SELECT * FROM bookings WHERE id = $booking_id";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    $booking = $result->fetch_assoc();
} else {
    echo 'Booking not found.';
    exit;
}
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Simulate payment success
    $sql = "UPDATE bookings SET status = 'paid' WHERE id = $booking_id";
    if ($conn->query($sql)) {
        header("Location: success.php?booking_id=$booking_id");
        exit;
    } else {
        echo 'Error updating status.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <h1>Payment for Booking #<?php echo $booking_id; ?></h1>
    <p>Total Amount: ₹<?php echo $booking['pricing']; ?></p>
    <p>Vehicle: <?php echo $booking['vehicle']; ?></p>
    <form method="post">
        <button type="submit">Simulate Payment Success</button>
    </form>
</body>
</html>
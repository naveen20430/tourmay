<?php include 'includes/db.php'; ?>
<?php
$booking_id = $_GET['booking_id'];
$sql = "SELECT b.*, t.title FROM bookings b JOIN tours t ON b.tour_id = t.id WHERE b.id = $booking_id";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    $data = $result->fetch_assoc();
    // Send email
    $to = $data['email'];
    $subject = 'Booking Confirmation';
    $message = 'Your booking for ' . $data['title'] . ' is confirmed. Booking ID: ' . $booking_id;
    if (mail($to, $subject, $message)) {
        $email_status = 'Email sent successfully.';
    } else {
        $email_status = 'Email could not be sent.';
    }
} else {
    echo 'Booking not found.';
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Booking Success</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <h1>Booking Confirmed!</h1>
    <p>Thank you for your booking.</p>
    <p>Booking ID: <?php echo $booking_id; ?></p>
    <p>Tour: <?php echo $data['title']; ?></p>
    <p><?php echo $email_status; ?></p>
    <a href="index.php">Back to Home</a>
</body>
</html>
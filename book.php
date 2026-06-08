<?php include 'includes/db.php'; ?>
<?php
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $tour_id = $_POST['tour_id'];
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $date_of_travel = $_POST['date_of_travel'];
    $no_of_pax = $_POST['no_of_pax'];
    $contact = trim($_POST['contact']);
    $id_proof = trim($_POST['id_proof']);
    $address = trim($_POST['address']);
    $pricing = $_POST['pricing'];
    $vehicle = $_POST['vehicle'];

    // Validation
    $errors = [];
    if (empty($name)) $errors[] = 'Name is required.';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
    if (empty($date_of_travel) || strtotime($date_of_travel) < time()) $errors[] = 'Valid future date is required.';
    if ($no_of_pax < 1) $errors[] = 'At least 1 pax required.';
    if (empty($contact)) $errors[] = 'Contact is required.';
    if (empty($id_proof)) $errors[] = 'ID Proof is required.';
    if (empty($address)) $errors[] = 'Address is required.';

    if (empty($errors)) {
        $sql = "INSERT INTO bookings (tour_id, name, email, date_of_travel, no_of_pax, contact, id_proof, address, pricing, vehicle) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('isssisssss', $tour_id, $name, $email, $date_of_travel, $no_of_pax, $contact, $id_proof, $address, $pricing, $vehicle);
        if ($stmt->execute()) {
            $booking_id = $stmt->insert_id;
            header("Location: payment.php?booking_id=$booking_id");
            exit;
        } else {
            echo 'Error: ' . $stmt->error;
        }
    } else {
        foreach($errors as $error) echo $error . '<br>';
    }
}
?>
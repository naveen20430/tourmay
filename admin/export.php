<?php 
include '../includes/db.php';
require_once '../includes/cab_options.php';

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="bookings_with_cabs.csv"');
$output = fopen('php://output', 'w');

// Updated CSV header to include cab information
fputcsv($output, array('Booking ID', 'Booking Number', 'Guest Name', 'Email', 'Phone', 'Tour', 'Date', 'People', 'Tour Amount', 'Cab Type', 'Cab Cost', 'Total Amount', 'Status', 'Created Date'));

$sql = "SELECT b.*, t.title as tour_title FROM bookings b LEFT JOIN tours t ON b.tour_id = t.id ORDER BY b.created_at DESC";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        // Handle different column names (legacy vs new structure)
        $guest_name = $row['guest_name'] ?? $row['name'] ?? 'N/A';
        $guest_email = $row['guest_email'] ?? $row['email'] ?? 'N/A';
        $guest_phone = $row['guest_phone'] ?? $row['contact'] ?? 'N/A';
        $tour_date = $row['tour_date'] ?? $row['date_of_travel'] ?? 'N/A';
        $people = $row['number_of_people'] ?? $row['no_of_pax'] ?? 'N/A';
        $status = $row['booking_status'] ?? $row['status'] ?? 'N/A';
        
        $cab_type_display = !empty($row['cab_type']) ? getCabDisplayName($row['cab_type']) : 'No Cab';
        $cab_cost = $row['cab_price'] ?? 0;
        $total_with_cab = $row['total_with_cab'] ?? $row['total_amount'];
        
        fputcsv($output, array(
            $row['id'],
            $row['booking_number'] ?? 'N/A',
            $guest_name,
            $guest_email,
            $guest_phone,
            $row['tour_title'],
            $tour_date,
            $people,
            $row['total_amount'],
            $cab_type_display,
            number_format($cab_cost, 2),
            number_format($total_with_cab, 2),
            ucfirst($status),
            $row['created_at']
        ));
    }
}
fclose($output);
?>

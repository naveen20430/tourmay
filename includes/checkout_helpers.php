<?php
/**
 * Checkout, invoice, and Razorpay payment helpers
 */

function ensureCheckoutSchema() {
    global $db;
    static $ready = false;
    if ($ready) {
        return;
    }

    $pdo = $db->getConnection();
    $pdo->exec("CREATE TABLE IF NOT EXISTS invoices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        invoice_number VARCHAR(30) NOT NULL UNIQUE,
        user_id INT NULL,
        guest_name VARCHAR(100) NOT NULL,
        guest_email VARCHAR(100) NOT NULL,
        guest_phone VARCHAR(20) NOT NULL,
        special_requirements TEXT NULL,
        subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,
        cab_total DECIMAL(10,2) NOT NULL DEFAULT 0,
        total_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
        payment_method ENUM('cash','razorpay') NOT NULL DEFAULT 'cash',
        payment_status ENUM('pending','paid','failed','cancelled') NOT NULL DEFAULT 'pending',
        razorpay_order_id VARCHAR(100) NULL,
        razorpay_payment_id VARCHAR(100) NULL,
        razorpay_signature VARCHAR(255) NULL,
        paid_amount DECIMAL(10,2) DEFAULT 0,
        paid_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_invoice_number (invoice_number)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $cols = array_column($db->fetchAll('SHOW COLUMNS FROM bookings'), 'Field');
    if (!in_array('invoice_id', $cols, true)) {
        $pdo->exec('ALTER TABLE bookings ADD COLUMN invoice_id INT NULL AFTER booking_number');
    }

    $ready = true;
}

function generateInvoiceNumber() {
    return 'INV' . date('Y') . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
}

function generateBookingNumber() {
    return 'TH' . date('Y') . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
}

function validateCartForCheckout($cartItems, $cab_functionality_enabled = false) {
    global $db;

    $errors = [];
    if (empty($cartItems)) {
        return ['errors' => ['Your cart is empty'], 'toursById' => [], 'lines' => [], 'subtotal' => 0, 'cabTotal' => 0, 'total' => 0];
    }

    $tourIds = array_values(array_filter(array_map('intval', array_keys($cartItems))));
    if (empty($tourIds)) {
        return ['errors' => ['Your cart is empty'], 'toursById' => [], 'lines' => [], 'subtotal' => 0, 'cabTotal' => 0, 'total' => 0];
    }

    $placeholders = implode(',', array_fill(0, count($tourIds), '?'));
    $tours = $db->fetchAll("
        SELECT id, title, slug, min_people, max_people, duration_days, price, discount_price, itinerary
        FROM tours
        WHERE status = 'active' AND id IN ($placeholders)
    ", $tourIds);

    $toursById = [];
    foreach ($tours as $tour) {
        $toursById[(string) $tour['id']] = $tour;
    }

    $lines = [];
    $subtotal = 0.0;
    $cabTotal = 0.0;

    foreach ($cartItems as $tourIdStr => $item) {
        $tour = $toursById[$tourIdStr] ?? null;
        if (!$tour) {
            $errors[] = 'One or more tours in your cart are no longer available';
            break;
        }

        $tourDate = trim((string) ($item['tour_date'] ?? ''));
        if ($tourDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tourDate)) {
            $errors[] = 'Please select a tour date for: ' . $tour['title'];
            continue;
        }
        if ($tourDate <= date('Y-m-d')) {
            $errors[] = 'Tour date must be tomorrow or later for: ' . $tour['title'];
            continue;
        }

        $people = $item['people'] ?? null;
        if (is_string($people) && $people !== '') {
            $people = filter_var($people, FILTER_VALIDATE_INT);
        }
        if (!$people || !is_int($people)) {
            $errors[] = 'Please select number of people for: ' . $tour['title'];
            continue;
        }
        if ($people < (int) $tour['min_people'] || $people > (int) $tour['max_people']) {
            $errors[] = 'Number of people must be between ' . (int) $tour['min_people'] . ' and ' . (int) $tour['max_people'] . ' for: ' . $tour['title'];
            continue;
        }

        $pricePerPerson = $tour['discount_price'] ? (float) $tour['discount_price'] : (float) $tour['price'];
        $lineTotal = $pricePerPerson * $people;
        $cabType = trim((string) ($item['cab_type'] ?? ''));
        $cabPrice = 0.0;

        if ($cab_functionality_enabled && $cabType !== '' && class_exists('CabOptions')) {
            $cabOptions = new CabOptions($db);
            if (!$cabOptions->canAccommodate($cabType, $people)) {
                $errors[] = 'Selected cab type cannot accommodate ' . $people . ' people for: ' . $tour['title'];
                continue;
            }
            $cabPrice = (float) $cabOptions->calculateCabPrice($cabType, (int) $tour['duration_days']);
        }

        $lines[] = [
            'tour_id' => (int) $tour['id'],
            'tour' => $tour,
            'tour_date' => $tourDate,
            'people' => $people,
            'cab_type' => $cabType,
            'cab_price' => $cabPrice,
            'price_per_person' => $pricePerPerson,
            'line_total' => $lineTotal + $cabPrice,
        ];

        $subtotal += $lineTotal;
        $cabTotal += $cabPrice;
    }

    return [
        'errors' => $errors,
        'toursById' => $toursById,
        'lines' => $lines,
        'subtotal' => $subtotal,
        'cabTotal' => $cabTotal,
        'total' => $subtotal + $cabTotal,
    ];
}

function createInvoiceFromBooking(array $booking, array $guest, string $paymentMethod, $cab_functionality_enabled = false) {
    $cartItems = [
        (string) (int) $booking['tour_id'] => [
            'tour_date' => trim((string) ($booking['tour_date'] ?? '')),
            'people' => (int) ($booking['people'] ?? 0),
            'cab_type' => trim((string) ($booking['cab_type'] ?? '')),
        ],
    ];

    return createInvoiceFromCart($cartItems, $guest, $paymentMethod, $cab_functionality_enabled);
}

function createInvoiceFromCart(array $cartItems, array $guest, string $paymentMethod, $cab_functionality_enabled = false) {
    global $db;

    ensureCheckoutSchema();

    $paymentMethod = in_array($paymentMethod, ['cash', 'razorpay'], true) ? $paymentMethod : 'cash';
    $validated = validateCartForCheckout($cartItems, $cab_functionality_enabled);
    if (!empty($validated['errors'])) {
        throw new Exception(implode(' | ', $validated['errors']));
    }

    $userId = isUserLoggedIn() ? (int) $_SESSION['user_id'] : null;
    $pdo = $db->getConnection();
    $pdo->beginTransaction();

    try {
        $invoiceNumber = generateInvoiceNumber();
        $db->execute(
            "INSERT INTO invoices (invoice_number, user_id, guest_name, guest_email, guest_phone, special_requirements, subtotal, cab_total, total_amount, payment_method, payment_status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())",
            [
                $invoiceNumber,
                $userId,
                $guest['name'],
                $guest['email'],
                $guest['phone'],
                $guest['special_requirements'] ?? '',
                $validated['subtotal'],
                $validated['cabTotal'],
                $validated['total'],
                $paymentMethod,
            ]
        );

        $invoiceId = (int) $db->lastInsertId();

        foreach ($validated['lines'] as $line) {
            $tour = $line['tour'];
            $bookingNumber = generateBookingNumber();
            $totalAmount = $line['line_total'] - $line['cab_price'];
            $cabType = $line['cab_type'] !== '' ? $line['cab_type'] : null;

            if ($cab_functionality_enabled && $cabType) {
                $db->execute(
                    "INSERT INTO bookings (booking_number, invoice_id, tour_id, user_id, guest_name, guest_email, guest_phone, number_of_people, tour_date, total_amount, cab_type, cab_price, total_with_cab, special_requirements, booking_status, payment_status, payment_method, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', ?, NOW())",
                    [
                        $bookingNumber,
                        $invoiceId,
                        $line['tour_id'],
                        $userId,
                        $guest['name'],
                        $guest['email'],
                        $guest['phone'],
                        $line['people'],
                        $line['tour_date'],
                        $totalAmount,
                        $cabType,
                        $line['cab_price'],
                        $line['line_total'],
                        $guest['special_requirements'] ?? '',
                        $paymentMethod,
                    ]
                );
            } else {
                $db->execute(
                    "INSERT INTO bookings (booking_number, invoice_id, tour_id, user_id, guest_name, guest_email, guest_phone, number_of_people, tour_date, total_amount, special_requirements, booking_status, payment_status, payment_method, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', ?, NOW())",
                    [
                        $bookingNumber,
                        $invoiceId,
                        $line['tour_id'],
                        $userId,
                        $guest['name'],
                        $guest['email'],
                        $guest['phone'],
                        $line['people'],
                        $line['tour_date'],
                        $line['line_total'],
                        $guest['special_requirements'] ?? '',
                        $paymentMethod,
                    ]
                );
            }
        }

        $pdo->commit();

        return [
            'invoice_id' => $invoiceId,
            'invoice_number' => $invoiceNumber,
            'total_amount' => $validated['total'],
            'payment_method' => $paymentMethod,
        ];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function getInvoiceByNumber($invoiceNumber) {
    global $db;
    ensureCheckoutSchema();
    return $db->fetch("SELECT * FROM invoices WHERE invoice_number = ?", [$invoiceNumber]);
}

function getInvoiceBookings($invoiceId) {
    global $db;
    return $db->fetchAll("
        SELECT b.*, t.title AS tour_title, t.slug AS tour_slug, t.duration_days, t.duration_nights,
               t.itinerary, t.short_description, d.name AS destination_name
        FROM bookings b
        JOIN tours t ON b.tour_id = t.id
        LEFT JOIN destinations d ON t.destination_id = d.id
        WHERE b.invoice_id = ?
        ORDER BY b.id ASC
    ", [(int) $invoiceId]);
}

function userCanViewInvoice(array $invoice) {
    if (!isUserLoggedIn()) {
        return false;
    }
    if (!empty($invoice['user_id']) && (int) $invoice['user_id'] === (int) $_SESSION['user_id']) {
        return true;
    }
    $userEmail = $_SESSION['user_email'] ?? '';
    return $userEmail !== '' && strcasecmp($userEmail, (string) $invoice['guest_email']) === 0;
}

function invoiceUrl($invoiceNumber) {
    return BASE_URL . 'invoice/' . rawurlencode($invoiceNumber);
}

function payInvoiceUrl($invoiceNumber) {
    return BASE_URL . 'pay-invoice/' . rawurlencode($invoiceNumber);
}

function razorpayIsConfigured() {
    $keyId = trim((string) getSetting('razorpay_key_id'));
    $keySecret = trim((string) getSetting('razorpay_key_secret'));
    return $keyId !== '' && $keySecret !== '';
}

function razorpayRequest($method, $endpoint, array $payload = null) {
    $keyId = trim((string) getSetting('razorpay_key_id'));
    $keySecret = trim((string) getSetting('razorpay_key_secret'));
    if ($keyId === '' || $keySecret === '') {
        throw new Exception('Razorpay is not configured. Please contact support.');
    }

    $url = 'https://api.razorpay.com/v1/' . ltrim($endpoint, '/');
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD => $keyId . ':' . $keySecret,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 30,
    ]);

    if (strtoupper($method) === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload ?? []));
    }

    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        throw new Exception('Payment gateway error: ' . $curlError);
    }

    $data = json_decode($response, true);
    if ($httpCode >= 400) {
        $message = $data['error']['description'] ?? 'Unable to create payment order';
        throw new Exception($message);
    }

    return is_array($data) ? $data : [];
}

function createRazorpayOrderForInvoice(array $invoice) {
    global $db;

    if ((float) $invoice['total_amount'] <= 0) {
        throw new Exception('Invalid invoice amount');
    }

    $amountPaise = (int) round(((float) $invoice['total_amount']) * 100);
    $order = razorpayRequest('POST', 'orders', [
        'amount' => $amountPaise,
        'currency' => 'INR',
        'receipt' => $invoice['invoice_number'],
        'notes' => [
            'invoice_number' => $invoice['invoice_number'],
            'guest_email' => $invoice['guest_email'],
        ],
    ]);

    $db->execute(
        "UPDATE invoices SET razorpay_order_id = ?, updated_at = NOW() WHERE id = ?",
        [$order['id'], (int) $invoice['id']]
    );

    return $order;
}

function verifyRazorpayPayment(array $invoice, $paymentId, $orderId, $signature) {
    global $db;

    $keySecret = trim((string) getSetting('razorpay_key_secret'));
    $expected = hash_hmac('sha256', $orderId . '|' . $paymentId, $keySecret);
    if (!hash_equals($expected, (string) $signature)) {
        throw new Exception('Payment verification failed');
    }

    if (!empty($invoice['razorpay_order_id']) && $invoice['razorpay_order_id'] !== $orderId) {
        throw new Exception('Order mismatch');
    }

    $pdo = $db->getConnection();
    $pdo->beginTransaction();

    try {
        $db->execute(
            "UPDATE invoices SET payment_status = 'paid', paid_amount = total_amount, paid_at = NOW(), razorpay_order_id = ?, razorpay_payment_id = ?, razorpay_signature = ?, updated_at = NOW() WHERE id = ?",
            [$orderId, $paymentId, $signature, (int) $invoice['id']]
        );

        $db->execute(
            "UPDATE bookings SET payment_status = 'paid', paid_amount = COALESCE(total_with_cab, total_amount + COALESCE(cab_price, 0)), booking_status = 'confirmed', payment_method = 'razorpay', updated_at = NOW() WHERE invoice_id = ?",
            [(int) $invoice['id']]
        );

        $pdo->commit();

        require_once __DIR__ . '/invoice_pdf_helpers.php';
        notifyInvoiceViaWhatsApp($invoice['invoice_number']);

        return true;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function decodeTourItinerary($json) {
    if (empty($json)) {
        return [];
    }
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

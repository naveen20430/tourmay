<?php
/**
 * Checkout, invoice, and Razorpay payment helpers
 */

require_once __DIR__ . '/cab_options.php';

/**
 * Allowed pickup times: 9:00 AM – 6:00 PM (30-minute steps).
 * @return array<int, array{value: string, label: string}>
 */
function getPickupTimeOptions() {
    $options = [];
    for ($hour = 9; $hour <= 18; $hour++) {
        foreach ([0, 30] as $minute) {
            if ($hour === 18 && $minute > 0) {
                break;
            }
            $value = sprintf('%02d:%02d', $hour, $minute);
            $labelHour = $hour % 12;
            if ($labelHour === 0) {
                $labelHour = 12;
            }
            $ampm = $hour < 12 ? 'AM' : 'PM';
            $options[] = [
                'value' => $value,
                'label' => sprintf('%d:%02d %s', $labelHour, $minute, $ampm),
            ];
        }
    }
    return $options;
}

/**
 * @return string[]
 */
function getAllowedPickupTimes() {
    return array_column(getPickupTimeOptions(), 'value');
}

function isValidPickupTime($time) {
    $time = trim((string) $time);
    if ($time === '') {
        return false;
    }
    // Accept HH:MM or HH:MM:SS from older browsers
    if (preg_match('/^(\d{2}:\d{2})/', $time, $m)) {
        $time = $m[1];
    }
    return in_array($time, getAllowedPickupTimes(), true);
}

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
    if (!in_array('pickup_place', $cols, true)) {
        $pdo->exec('ALTER TABLE bookings ADD COLUMN pickup_place VARCHAR(100) NULL AFTER cab_price');
    }
    if (!in_array('pickup_detail', $cols, true)) {
        $pdo->exec('ALTER TABLE bookings ADD COLUMN pickup_detail TEXT NULL AFTER pickup_place');
    } else {
        try {
            $pdo->exec('ALTER TABLE bookings MODIFY COLUMN pickup_detail TEXT NULL');
        } catch (Exception $e) {
            // Column may already be TEXT
        }
    }
    if (!in_array('pickup_time', $cols, true)) {
        $pdo->exec('ALTER TABLE bookings ADD COLUMN pickup_time VARCHAR(10) NULL AFTER pickup_detail');
    }

    $ready = true;
}

function generateInvoiceNumber() {
    return 'INV' . date('Y') . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
}

function generateBookingNumber() {
    return 'TH' . date('Y') . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
}

function addTourToSessionCart(int $tourId, array $options = []) {
    global $db;

    if (!isset($_SESSION['tour_cart']) || !is_array($_SESSION['tour_cart'])) {
        $_SESSION['tour_cart'] = [];
    }

    $tour = $db->fetch("SELECT id, min_people, max_people FROM tours WHERE id = ? AND status = 'active'", [$tourId]);
    if (!$tour) {
        return ['ok' => false, 'message' => 'Tour not found'];
    }

    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    $tourDate = trim((string) ($options['tour_date'] ?? ''));
    if ($tourDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tourDate) || $tourDate <= date('Y-m-d')) {
        $tourDate = $tomorrow;
    }

    $people = $options['people'] ?? null;
    if ($people === null || $people === '') {
        $people = max((int) ($tour['min_people'] ?? 1), min(2, (int) ($tour['max_people'] ?? 8)));
    } else {
        $people = filter_var($people, FILTER_VALIDATE_INT);
        if ($people === false) {
            $people = max((int) ($tour['min_people'] ?? 1), min(2, (int) ($tour['max_people'] ?? 8)));
        }
    }

    $cabType = trim((string) ($options['cab_type'] ?? ''));
    $pickupPlace = trim((string) ($options['pickup_place'] ?? ''));
    $pickupDetail = trim((string) ($options['pickup_detail'] ?? ''));
    $pickupTime = trim((string) ($options['pickup_time'] ?? ''));
    if ($pickupTime !== '' && !preg_match('/^\d{2}:\d{2}$/', $pickupTime)) {
        $pickupTime = '';
    }
    if ($cabType === '') {
        $pickupPlace = '';
        $pickupDetail = '';
        $pickupTime = '';
    }

    $_SESSION['tour_cart'][(string) $tourId] = [
        'tour_date' => $tourDate,
        'people' => $people,
        'cab_type' => $cabType,
        'pickup_place' => $pickupPlace,
        'pickup_detail' => $pickupDetail,
        'pickup_time' => $pickupTime,
    ];

    return ['ok' => true, 'message' => 'Added to cart'];
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

        // Tour listing price is display-only ("starts from"). Charge comes from cab selection.
        $startsFromPrice = $tour['discount_price'] ? (float) $tour['discount_price'] : (float) $tour['price'];
        $tourPrice = 0.0;
        $lineTotal = 0.0;
        $cabType = trim((string) ($item['cab_type'] ?? ''));
        $pickupPlace = trim((string) ($item['pickup_place'] ?? ''));
        $pickupDetail = trim((string) ($item['pickup_detail'] ?? ''));
        $pickupAddress = trim((string) ($item['pickup_address'] ?? ''));
        $pickupTime = trim((string) ($item['pickup_time'] ?? ''));
        if (preg_match('/^(\d{2}:\d{2})/', $pickupTime, $m)) {
            $pickupTime = $m[1];
        }
        if ($pickupTime !== '' && !isValidPickupTime($pickupTime)) {
            $pickupTime = '';
        }
        $cabPrice = 0.0;

        if ($cab_functionality_enabled) {
            if ($cabType === '') {
                $errors[] = 'Please select a cab option for: ' . $tour['title'];
                continue;
            }
            if (!class_exists('CabOptions')) {
                $errors[] = 'Cab options are not available right now.';
                continue;
            }
            $cabOptions = new CabOptions($db);
            if (!$cabOptions->canAccommodate($cabType, $people)) {
                $errors[] = 'Selected cab type cannot accommodate ' . $people . ' people for: ' . $tour['title'];
                continue;
            }
            if ($pickupPlace === '') {
                $errors[] = 'Please select a pickup point for: ' . $tour['title'];
                continue;
            }
            if ($pickupTime === '' || !isValidPickupTime($pickupTime)) {
                $errors[] = 'Please select a pickup time between 9:00 AM and 6:00 PM for: ' . $tour['title'];
                continue;
            }
            if (in_array($pickupPlace, ['Hotel', 'Others'], true) && $pickupDetail === '') {
                $errors[] = 'Please enter pickup details for: ' . $tour['title'];
                continue;
            }
            $cabPrice = (float) getCabPriceForTour((int) $tour['id'], $cabType, $db);
            if ($cabPrice <= 0) {
                $errors[] = 'Cab price is not configured for: ' . $tour['title'];
                continue;
            }
            $lineTotal = $cabPrice;
        } elseif ($cabType !== '') {
            $pickupPlace = '';
            $pickupDetail = '';
            $pickupAddress = '';
            $pickupTime = '';
        }

        $lines[] = [
            'tour_id' => (int) $tour['id'],
            'tour' => $tour,
            'tour_date' => $tourDate,
            'people' => $people,
            'cab_type' => $cabType,
            'pickup_place' => $pickupPlace,
            'pickup_detail' => $pickupDetail,
            'pickup_address' => $pickupAddress,
            'pickup_time' => $pickupTime,
            'cab_price' => $cabPrice,
            'tour_price' => $tourPrice,
            'starts_from_price' => $startsFromPrice,
            'line_total' => $lineTotal,
        ];

        $subtotal += $tourPrice;
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
            // Tour listing price is not charged; payable amount is the cab total.
            $totalAmount = 0.0;
            $cabType = $line['cab_type'] !== '' ? $line['cab_type'] : null;

            if ($cab_functionality_enabled && $cabType) {
                $db->execute(
                    "INSERT INTO bookings (booking_number, invoice_id, tour_id, user_id, guest_name, guest_email, guest_phone, number_of_people, tour_date, total_amount, cab_type, cab_price, pickup_place, pickup_detail, pickup_time, total_with_cab, special_requirements, booking_status, payment_status, payment_method, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', ?, NOW())",
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
                        $line['pickup_place'] !== '' ? $line['pickup_place'] : null,
                        $line['pickup_detail'] !== '' ? $line['pickup_detail'] : null,
                        $line['pickup_time'] !== '' ? $line['pickup_time'] : null,
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
               t.itinerary, t.inclusions, t.exclusions, t.availability_start, t.availability_end,
               t.description AS tour_description, t.short_description,
               d.name AS destination_name, d.country, d.description AS destination_description
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

function decodeTourListItems($json) {
    if (empty($json)) {
        return [];
    }
    $data = json_decode($json, true);
    if (!is_array($data)) {
        return [];
    }

    return array_values(array_filter(array_map(static function ($item) {
        $item = trim((string) $item);
        $item = preg_replace('/^[•\-\*]\s*/u', '', $item);
        $item = preg_replace('/^o\s+/i', '', $item);

        return trim($item);
    }, $data), static function ($item) {
        return $item !== '';
    }));
}

function plainTextForPdf($text) {
    $text = normalizeItinerarySourceText($text);
    $text = stripSymbolsForPdf($text);

    return trim($text);
}

function stripSymbolsForPdf($text) {
    $text = (string) $text;
    $text = preg_replace('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}\x{FE0F}\x{200D}]/u', '', $text);
    $text = preg_replace('/\?{2,}/', '', $text);
    $text = preg_replace("/[ \t]+/u", ' ', $text);
    $text = preg_replace("/\n{3,}/", "\n\n", $text);

    return trim($text);
}

function formatTourAvailabilityLabel(array $tour) {
    $start = $tour['availability_start'] ?? null;
    $end = $tour['availability_end'] ?? null;
    if (empty($start) && empty($end)) {
        return '';
    }

    $startLabel = !empty($start) ? date('d M Y', strtotime($start)) : 'Open';
    $endLabel = !empty($end) ? date('d M Y', strtotime($end)) : 'Open';

    return $startLabel . ' - ' . $endLabel;
}

function normalizeItinerarySourceText($text) {
    $text = html_entity_decode((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/<\s*br\s*\/?>/i', "\n", $text);
    $text = preg_replace('/<\/\s*p>/i', "\n\n", $text);
    $text = preg_replace('/<\/\s*li>/i', "\n", $text);
    $text = strip_tags($text);
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $text = preg_replace("/\n{3,}/", "\n\n", $text);

    return trim($text);
}

function getTourDescriptionIntroForPdf($shortDescription, $fullDescription) {
    $intro = trim((string) $shortDescription);

    $full = normalizeItinerarySourceText($fullDescription);
    if ($full === '') {
        return plainTextForPdf($intro);
    }

    $markers = [
        'Tour Itinerary',
        'Duration',
        'Vehicle Capacity',
        'Inclusions',
        'Exclusions',
        'Perfect For',
    ];

    $cutAt = null;
    foreach ($markers as $marker) {
        $pos = mb_stripos($full, $marker);
        if ($pos !== false && $pos > 0 && ($cutAt === null || $pos < $cutAt)) {
            $cutAt = $pos;
        }
    }

    if ($cutAt !== null) {
        $chunk = trim(mb_substr($full, 0, $cutAt));
        if ($chunk !== '' && mb_strlen($chunk) > mb_strlen($intro)) {
            $intro = $chunk;
        }
    } elseif ($intro === '') {
        $paragraphs = preg_split('/\n{2,}/', $full) ?: [];
        $intro = trim((string) ($paragraphs[0] ?? ''));
    }

    return plainTextForPdf($intro);
}

function itineraryContentLength(array $items) {
    $length = 0;
    foreach ($items as $item) {
        $length += strlen(trim((string) ($item['title'] ?? '')));
        $length += strlen(trim((string) ($item['description'] ?? '')));
    }

    return $length;
}

function splitItinerarySectionHeader($line) {
    if (preg_match('/^([^:\n]{2,90}):\s*$/', $line, $match)) {
        return [trim($match[1]), ''];
    }
    if (preg_match('/^([^:\n]{2,90}):\s*(.+)$/', $line, $match)) {
        return [trim($match[1]), trim($match[2])];
    }

    return [null, null];
}

function extractTourItineraryFromDescription($description) {
    $description = normalizeItinerarySourceText($description);
    if ($description === '') {
        return '';
    }

    if (preg_match('/(?:🗓\s*)?Tour Itinerary\s*\n([\s\S]*)/iu', $description, $matches)) {
        $chunk = trim($matches[1]);
        if (preg_match('/^([\s\S]*?)(?=\n\s*(?:⏱|🚗|✅|❌|💼|📸)|\nDuration\b|\nVehicle Capacity|\n✅ Inclusions|\n❌ Exclusions)/u', $chunk, $section)) {
            return trim($section[1]);
        }

        return $chunk;
    }

    return $description;
}

function parseTourItinerarySections($text) {
    $text = trim((string) $text);
    if ($text === '') {
        return [];
    }

    $sections = [];
    $currentTitle = null;
    $currentBody = [];

    $flush = function () use (&$sections, &$currentTitle, &$currentBody) {
        if ($currentTitle === null) {
            return;
        }
        $body = trim(implode("\n", $currentBody));
        if ($body === '' && $currentTitle === '') {
            return;
        }
        $sections[] = [
            'day' => '',
            'title' => $currentTitle,
            'description' => $body,
            'is_section' => true,
        ];
        $currentTitle = null;
        $currentBody = [];
    };

    foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
        $trimmed = trim($line);
        if ($trimmed === '') {
            if ($currentTitle !== null && !empty($currentBody)) {
                $currentBody[] = '';
            }
            continue;
        }

        [$headerTitle, $headerBody] = splitItinerarySectionHeader($trimmed);
        if ($headerTitle !== null) {
            $flush();
            $currentTitle = $headerTitle;
            $currentBody = $headerBody !== '' ? [$headerBody] : [];
            continue;
        }

        if ($currentTitle === null) {
            $currentTitle = 'Itinerary';
        }
        $currentBody[] = $trimmed;
    }

    $flush();

    return $sections;
}

function getInvoiceTourItineraryDays(array $booking) {
    $days = decodeTourItinerary($booking['itinerary'] ?? '');
    $text = extractTourItineraryFromDescription($booking['tour_description'] ?? '');
    $sections = parseTourItinerarySections($text);

    $daysLength = itineraryContentLength($days);
    $sectionsLength = itineraryContentLength($sections);

    if (!empty($sections) && ($sectionsLength > $daysLength + 40 || $daysLength < 80)) {
        return $sections;
    }

    if (!empty($days)) {
        return $days;
    }

    if (!empty($sections)) {
        return $sections;
    }

    if ($text !== '') {
        return [[
            'day' => '',
            'title' => 'Full Itinerary',
            'description' => $text,
            'is_section' => true,
        ]];
    }

    return [];
}

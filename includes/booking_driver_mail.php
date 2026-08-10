<?php
/**
 * Driver assignment columns + email to guest when booking is confirmed.
 */

require_once __DIR__ . '/email_otp_helpers.php';

function ensureBookingDriverSchema() {
    global $db;
    static $ready = false;
    if ($ready) {
        return;
    }

    $pdo = $db->getConnection();
    $cols = array_column($db->fetchAll('SHOW COLUMNS FROM bookings'), 'Field');

    if (!in_array('driver_name', $cols, true)) {
        $pdo->exec("ALTER TABLE bookings ADD COLUMN driver_name VARCHAR(120) NULL AFTER notes");
        $cols[] = 'driver_name';
    }
    if (!in_array('vehicle_number', $cols, true)) {
        $pdo->exec("ALTER TABLE bookings ADD COLUMN vehicle_number VARCHAR(60) NULL AFTER driver_name");
        $cols[] = 'vehicle_number';
    }
    if (!in_array('driver_contact', $cols, true)) {
        $pdo->exec("ALTER TABLE bookings ADD COLUMN driver_contact VARCHAR(30) NULL AFTER vehicle_number");
        $cols[] = 'driver_contact';
    }
    if (!in_array('driver_details_sent_at', $cols, true)) {
        $pdo->exec("ALTER TABLE bookings ADD COLUMN driver_details_sent_at DATETIME NULL AFTER driver_contact");
    }

    $ready = true;
}

/**
 * Paid booking still waiting for driver details email.
 */
function bookingNeedsDriverDetailsNotice(array $booking): bool {
    if (($booking['booking_status'] ?? '') === 'cancelled') {
        return false;
    }
    $paid = (($booking['payment_status'] ?? '') === 'paid') || ((float) ($booking['paid_amount'] ?? 0) > 0);
    if (!$paid) {
        return false;
    }
    return empty($booking['driver_details_sent_at']);
}

function markBookingDriverDetailsSent(int $bookingId): void {
    global $db;
    $db->execute(
        'UPDATE bookings SET driver_details_sent_at = NOW(), updated_at = NOW() WHERE id = ?',
        [$bookingId]
    );
}

function countBookingsNeedingDriverDetails(): int {
    global $db;
    try {
        $row = $db->fetch("
            SELECT COUNT(*) AS count
            FROM bookings
            WHERE booking_status != 'cancelled'
              AND driver_details_sent_at IS NULL
              AND (payment_status = 'paid' OR COALESCE(paid_amount, 0) > 0)
        ");
        return (int) ($row['count'] ?? 0);
    } catch (Exception $e) {
        return 0;
    }
}

function buildBookingDriverDetailsHtml(array $booking) {
    $siteName = getSetting('site_name') ?: 'The World Journey';
    $siteTagline = getSetting('site_tagline') ?: 'Travel & Tour Booking Agency';
    $siteAddress = trim((string) getSetting('site_address'));
    $contactEmail = trim((string) (getSetting('contact_email') ?: getSetting('site_email') ?: ''));
    $contactPhone = trim((string) (getSetting('contact_phone') ?: getSetting('site_phone') ?: ''));
    $homeUrl = rtrim(BASE_URL, '/');
    $logoUrl = $homeUrl . '/assets/images/logonew.png';

    $siteEsc = htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8');
    $tagEsc = htmlspecialchars($siteTagline, ENT_QUOTES, 'UTF-8');
    $logoEsc = htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8');
    $homeEsc = htmlspecialchars($homeUrl, ENT_QUOTES, 'UTF-8');

    $guestName = htmlspecialchars((string) ($booking['guest_name'] ?? 'Guest'), ENT_QUOTES, 'UTF-8');
    $bookingNo = htmlspecialchars((string) ($booking['booking_number'] ?? ''), ENT_QUOTES, 'UTF-8');
    $tourTitle = htmlspecialchars((string) ($booking['tour_title'] ?? 'Your tour'), ENT_QUOTES, 'UTF-8');
    $tourDate = !empty($booking['tour_date'])
        ? htmlspecialchars(date('d M Y', strtotime($booking['tour_date'])), ENT_QUOTES, 'UTF-8')
        : '';
    $pickup = trim((string) (($booking['pickup_place'] ?? '') . (empty($booking['pickup_detail']) ? '' : ' — ' . $booking['pickup_detail'])));
    $pickupTime = trim((string) ($booking['pickup_time'] ?? ''));
    $driverName = htmlspecialchars((string) ($booking['driver_name'] ?? ''), ENT_QUOTES, 'UTF-8');
    $vehicleNumber = htmlspecialchars((string) ($booking['vehicle_number'] ?? ''), ENT_QUOTES, 'UTF-8');
    $driverContact = htmlspecialchars((string) ($booking['driver_contact'] ?? ''), ENT_QUOTES, 'UTF-8');

    $addressRow = $siteAddress !== ''
        ? '<div style="margin-top:4px;font-size:12px;line-height:1.4;color:rgba(255,255,255,0.88);">' . htmlspecialchars($siteAddress, ENT_QUOTES, 'UTF-8') . '</div>'
        : '';

    $footerBits = [];
    if ($contactEmail !== '') {
        $footerBits[] = htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8');
    }
    if ($contactPhone !== '') {
        $footerBits[] = htmlspecialchars($contactPhone, ENT_QUOTES, 'UTF-8');
    }
    $footerContact = implode(' · ', $footerBits);

    $pickupRows = '';
    if ($pickup !== '') {
        $pickupRows .= '<tr><td style="padding:8px 0;color:#64748b;width:40%;">Pickup</td><td style="padding:8px 0;color:#0f172a;font-weight:600;">' . htmlspecialchars($pickup, ENT_QUOTES, 'UTF-8') . '</td></tr>';
    }
    if ($pickupTime !== '') {
        $pickupRows .= '<tr><td style="padding:8px 0;color:#64748b;">Pickup time</td><td style="padding:8px 0;color:#0f172a;font-weight:600;">' . htmlspecialchars($pickupTime, ENT_QUOTES, 'UTF-8') . '</td></tr>';
    }

    return '<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Driver details</title></head>
<body style="margin:0;padding:0;background:#f4f6fb;font-family:Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#0f172a;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#f4f6fb;padding:28px 12px;">
  <tr>
    <td align="center">
      <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="max-width:600px;width:100%;background:#ffffff;border:1px solid #dde3ef;border-radius:4px;overflow:hidden;">
        <tr>
          <td style="background:#0f766e;padding:18px 24px;">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
              <tr>
                <td valign="middle">
                  <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                    <tr>
                      <td valign="middle" style="background:#ffffff;border-radius:4px;padding:8px 10px;">
                        <img src="' . $logoEsc . '" alt="' . $siteEsc . '" width="72" height="48" style="display:block;max-width:72px;max-height:48px;width:auto;height:auto;margin:0 auto;border:0;">
                      </td>
                      <td valign="middle" style="padding-left:14px;">
                        <h1 style="margin:0 0 2px;font-size:20px;line-height:1.2;font-weight:700;color:#ffffff;">' . $siteEsc . '</h1>
                        <p style="margin:0;font-size:13px;line-height:1.35;color:rgba(255,255,255,0.92);">' . $tagEsc . '</p>
                        ' . $addressRow . '
                      </td>
                    </tr>
                  </table>
                </td>
                <td valign="middle" align="right">
                  <div style="font-size:11px;text-transform:uppercase;letter-spacing:0.12em;font-weight:700;color:rgba(255,255,255,0.9);">Booking</div>
                  <div style="margin-top:2px;font-size:18px;font-weight:700;color:#ffffff;">Confirmed</div>
                </td>
              </tr>
            </table>
          </td>
        </tr>
        <tr>
          <td style="padding:28px 24px 8px;">
            <p style="margin:0 0 12px;font-size:16px;line-height:1.5;">Hi ' . $guestName . ',</p>
            <p style="margin:0;font-size:15px;line-height:1.55;color:#334155;">Your booking is confirmed. Please find your assigned driver and vehicle details below.</p>
          </td>
        </tr>
        <tr>
          <td style="padding:12px 24px;">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:4px;">
              <tr>
                <td style="padding:16px 18px;">
                  <div style="font-size:12px;text-transform:uppercase;letter-spacing:0.08em;color:#64748b;font-weight:700;margin-bottom:8px;">Trip details</div>
                  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="font-size:14px;">
                    <tr><td style="padding:8px 0;color:#64748b;width:40%;">Booking</td><td style="padding:8px 0;color:#0f172a;font-weight:600;">#' . $bookingNo . '</td></tr>
                    <tr><td style="padding:8px 0;color:#64748b;">Tour</td><td style="padding:8px 0;color:#0f172a;font-weight:600;">' . $tourTitle . '</td></tr>
                    ' . ($tourDate !== '' ? '<tr><td style="padding:8px 0;color:#64748b;">Date</td><td style="padding:8px 0;color:#0f172a;font-weight:600;">' . $tourDate . '</td></tr>' : '') . '
                    ' . $pickupRows . '
                  </table>
                </td>
              </tr>
            </table>
          </td>
        </tr>
        <tr>
          <td style="padding:8px 24px 20px;">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#ecfdf5;border:1px solid #a7f3d0;border-radius:4px;">
              <tr>
                <td style="padding:16px 18px;">
                  <div style="font-size:12px;text-transform:uppercase;letter-spacing:0.08em;color:#047857;font-weight:700;margin-bottom:8px;">Driver details</div>
                  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="font-size:14px;">
                    <tr><td style="padding:8px 0;color:#64748b;width:40%;">Driver name</td><td style="padding:8px 0;color:#0f172a;font-weight:700;">' . $driverName . '</td></tr>
                    <tr><td style="padding:8px 0;color:#64748b;">Vehicle number</td><td style="padding:8px 0;color:#0f172a;font-weight:700;">' . $vehicleNumber . '</td></tr>
                    <tr><td style="padding:8px 0;color:#64748b;">Driver contact</td><td style="padding:8px 0;color:#0f172a;font-weight:700;"><a href="tel:' . $driverContact . '" style="color:#0f766e;text-decoration:none;">' . $driverContact . '</a></td></tr>
                  </table>
                </td>
              </tr>
            </table>
          </td>
        </tr>
        <tr>
          <td style="padding:0 24px 24px;">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border-top:1px solid #e9ecef;">
              <tr>
                <td style="padding-top:16px;font-size:12px;line-height:1.5;color:#64748b;">
                  Please keep this information handy on travel day. For any help, reply to this email or contact us.
                  <br>
                  Thank you for choosing ' . $siteEsc . '.
                  ' . ($footerContact !== '' ? '<br>' . $footerContact : '') . '
                </td>
              </tr>
              <tr>
                <td style="padding-top:14px;">
                  <a href="' . $homeEsc . '" style="display:inline-block;background:#0f766e;color:#ffffff;text-decoration:none;font-size:13px;font-weight:600;padding:10px 16px;border-radius:4px;">Visit Website</a>
                </td>
              </tr>
            </table>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
</body>
</html>';
}

/**
 * Email assigned driver details to the booking guest.
 *
 * @return array{ok:bool,to?:string,error?:string}
 */
function sendBookingDriverDetailsEmail(array $booking) {
    $toEmail = normalizeEmailAddress($booking['guest_email'] ?? '');
    if ($toEmail === '') {
        return ['ok' => false, 'error' => 'Guest email is missing or invalid'];
    }

    $driverName = trim((string) ($booking['driver_name'] ?? ''));
    $vehicleNumber = trim((string) ($booking['vehicle_number'] ?? ''));
    $driverContact = trim((string) ($booking['driver_contact'] ?? ''));
    if ($driverName === '' || $vehicleNumber === '' || $driverContact === '') {
        return ['ok' => false, 'error' => 'Driver name, vehicle number, and driver contact are required'];
    }

    $siteName = getSetting('site_name') ?: 'The World Journey';
    $bookingNo = (string) ($booking['booking_number'] ?? '');
    $tourTitle = (string) ($booking['tour_title'] ?? 'your tour');
    $guestName = (string) ($booking['guest_name'] ?? 'Guest');
    $tourDate = !empty($booking['tour_date']) ? date('d M Y', strtotime($booking['tour_date'])) : '';

    $subject = $siteName . ' — Driver details for booking #' . $bookingNo;
    $bodyText = "Hi {$guestName},\n\n"
        . "Your booking #{$bookingNo} is confirmed.\n"
        . "Tour: {$tourTitle}\n"
        . ($tourDate !== '' ? "Date: {$tourDate}\n" : '')
        . "\nDriver details:\n"
        . "Driver name: {$driverName}\n"
        . "Vehicle number: {$vehicleNumber}\n"
        . "Driver contact: {$driverContact}\n"
        . "\nPlease keep these details handy on travel day.\n"
        . "Thank you for choosing {$siteName}.\n";

    try {
        $result = sendSmtpMail($toEmail, $subject, $bodyText, buildBookingDriverDetailsHtml($booking));
        return ['ok' => true, 'to' => $result['to'] ?? $toEmail];
    } catch (Exception $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

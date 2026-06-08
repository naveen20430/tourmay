<?php
/**
 * API Endpoint to save tour search queries
 * Saves user search data with phone number to database
 */

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

require_once dirname(__DIR__) . '/config/config.php';

$response = [
    'success' => false,
    'message' => ''
];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        throw new Exception('Invalid request method');
    }

    $getPostString = function (string $key, int $maxLen): ?string {
        if (!isset($_POST[$key])) {
            return null;
        }
        $value = trim((string)$_POST[$key]);
        if ($value === '') {
            return null;
        }
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $maxLen);
        }
        return substr($value, 0, $maxLen);
    };

    $normalizeStartDate = function (?string $raw): ?string {
        if ($raw === null || $raw === '') {
            return null;
        }

        $raw = trim($raw);
        $startPart = $raw;
        $rangeParts = preg_split('/\s+-\s+/', $raw);
        if (is_array($rangeParts) && count($rangeParts) >= 1) {
            $startPart = trim((string)$rangeParts[0]);
        }

        $formats = ['Y-m-d', 'j M y', 'j M Y', 'd M y', 'd M Y', 'd/m/Y', 'm/d/Y'];
        foreach ($formats as $format) {
            $dt = DateTime::createFromFormat($format, $startPart);
            if ($dt instanceof DateTime) {
                return $dt->format('Y-m-d');
            }
        }

        return null;
    };

    $from = $getPostString('from', 255);
    $destination = $getPostString('destination', 255);
    $travel_date_raw = $getPostString('travel_date', 64);
    $phone = $getPostString('phone', 20);

    if ($destination !== null && !preg_match('/^[a-z0-9\-]+$/i', $destination)) {
        throw new Exception('Invalid destination');
    }

    if ($phone === null) {
        throw new Exception('Phone number is required');
    }

    if (!preg_match('/^[0-9]{10}$/', $phone)) {
        throw new Exception('Invalid phone number format');
    }

    $travel_date = $normalizeStartDate($travel_date_raw);

    $ip_address = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    if ($ip_address !== '' && filter_var($ip_address, FILTER_VALIDATE_IP) === false) {
        $ip_address = '';
    }

    $user_agent = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    if (function_exists('mb_substr')) {
        $user_agent = mb_substr($user_agent, 0, 1024);
    } else {
        $user_agent = substr($user_agent, 0, 1024);
    }

    $db->query("
        INSERT INTO search_queries 
        (from_location, destination_slug, travel_date, return_date, phone, ip_address, user_agent) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ", [
        $from,
        $destination,
        $travel_date ?: null,
        null,
        $phone,
        $ip_address !== '' ? $ip_address : null,
        $user_agent !== '' ? $user_agent : null
    ]);
    
    $response['success'] = true;
    $response['message'] = 'Search query saved successfully';
    $response['query_id'] = $db->lastInsertId();
    
} catch (Exception $e) {
    $response['success'] = false;
    if (http_response_code() === 200) {
        http_response_code(400);
    }
    $response['message'] = $e->getMessage();
    error_log('Search Query API Error: ' . $e->getMessage());
}

echo json_encode($response, JSON_UNESCAPED_SLASHES);
exit;

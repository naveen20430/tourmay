<?php
require_once '../config/config.php';
require_once '../includes/url_helpers.php';
require_once '../includes/tour_destinations.php';

ensureTourDestinationsSchema();

header('Content-Type: application/json');

$countries = $db->fetchAll("
    SELECT DISTINCT d.country
    FROM destinations d
    WHERE d.status = 'active'
      AND d.country IS NOT NULL
      AND d.country != ''
      AND EXISTS (
          SELECT 1 FROM tours t
          LEFT JOIN tour_destinations td ON td.tour_id = t.id
          WHERE t.status = 'active'
            AND (td.destination_id = d.id OR t.destination_id = d.id)
      )
    ORDER BY d.country ASC
");

$destinations = $db->fetchAll("
    SELECT d.id, d.name, d.slug, d.country, d.city, d.popular, d.featured_image,
           (
               SELECT COUNT(DISTINCT t.id)
               FROM tours t
               LEFT JOIN tour_destinations td ON td.tour_id = t.id
               WHERE t.status = 'active'
                 AND (td.destination_id = d.id OR t.destination_id = d.id)
           ) AS tour_count
    FROM destinations d
    WHERE d.status = 'active'
      AND EXISTS (
          SELECT 1 FROM tours t
          LEFT JOIN tour_destinations td ON td.tour_id = t.id
          WHERE t.status = 'active'
            AND (td.destination_id = d.id OR t.destination_id = d.id)
      )
    ORDER BY d.popular DESC, d.name ASC
");

$tours = $db->fetchAll("
    SELECT t.id, t.title, t.slug,
           d.slug AS destination_slug,
           COALESCE(
               NULLIF((
                   SELECT GROUP_CONCAT(DISTINCT d2.name ORDER BY td2.sort_order ASC, d2.name ASC SEPARATOR ', ')
                   FROM tour_destinations td2
                   INNER JOIN destinations d2 ON d2.id = td2.destination_id
                   WHERE td2.tour_id = t.id
               ), ''),
               d.name
           ) AS destination_name,
           d.country
    FROM tours t
    INNER JOIN destinations d ON d.id = t.destination_id AND d.status = 'active'
    WHERE t.status = 'active'
    ORDER BY t.title ASC
");

$pickupList = ['Hotel', 'Lift Parking', 'Others'];

function activityApiImage(array $dest) {
    $img = trim((string) ($dest['featured_image'] ?? ''));
    if ($img !== '') {
        return BASE_URL . ltrim($img, '/');
    }
    return BASE_URL . 'assets/images/logonew.png';
}

echo json_encode([
    'success' => true,
    'countries' => array_column($countries, 'country'),
    'destinations' => array_map(static function ($d) {
        return [
            'id' => (int) $d['id'],
            'name' => $d['name'],
            'slug' => $d['slug'],
            'country' => $d['country'] ?? '',
            'city' => $d['city'] ?? '',
            'popular' => (int) ($d['popular'] ?? 0),
            'image' => activityApiImage($d),
            'url' => destinationUrl($d['slug']),
            'tours_url' => toursUrl(['destination' => $d['slug']]),
            'tour_count' => (int) ($d['tour_count'] ?? 0),
        ];
    }, $destinations),
    'tours' => array_map(static function ($t) {
        return [
            'id' => (int) $t['id'],
            'title' => $t['title'],
            'slug' => $t['slug'],
            'destination_slug' => $t['destination_slug'],
            'destination_name' => $t['destination_name'],
            'country' => $t['country'] ?? '',
            'url' => tourUrl($t['slug']),
            'type' => 'tour',
        ];
    }, $tours),
    'pickup_places' => $pickupList,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

<?php
if (!isset($db)) {
    global $db;
}

$cab_travel_routes = $db->fetchAll("
    SELECT *
    FROM cab_routes
    WHERE status = 'active'
    ORDER BY display_order ASC, from_location ASC
");

function cabTravelImageUrl(array $pricingRow) {
    $imagePath = trim((string) ($pricingRow['image_path'] ?? ''));
    if ($imagePath !== '' && is_file(BASE_PATH . $imagePath)) {
        return BASE_URL . $imagePath;
    }

    $slug = strtolower((string) ($pricingRow['cab_slug'] ?? ''));
    $candidates = [
        'sedan' => 'assets/images/cabs/sedan.jpg',
        'innova' => 'assets/images/cabs/innova.jpg',
        'ertiga' => 'assets/images/cabs/ertiga.jpg',
        'tempo_traveller' => 'assets/images/cabs/tempo.jpg',
    ];

    $relative = $candidates[$slug] ?? 'assets/images/tours/default-tour.jpg';
    if (is_file(BASE_PATH . $relative)) {
        return BASE_URL . $relative;
    }

    return BASE_URL . 'assets/images/tours/default-tour.jpg';
}

function cabTravelFareLabel($amount) {
    return formatPriceINR((float) $amount) . '/-';
}
?>

<div class="destinations-view destinations-view--travel" id="destinationsTravelView" hidden>
    <?php if (empty($cab_travel_routes)): ?>
        <div class="destination-block cab-route-block">
            <div class="cab-travel-empty">No cab routes available at the moment.</div>
        </div>
    <?php else: ?>
        <?php foreach ($cab_travel_routes as $route): ?>
            <?php
            $pricing_rows = $db->fetchAll("
                SELECT crp.*, ct.display_name, ct.name AS cab_slug, ct.image_path
                FROM cab_route_pricing crp
                INNER JOIN cab_types ct ON crp.cab_type_id = ct.id
                WHERE crp.route_id = ? AND crp.status = 'active' AND ct.status = 'active'
                ORDER BY crp.one_way_price ASC
            ", [(int) $route['id']]);

            if (empty($pricing_rows)) {
                continue;
            }

            $route_title = htmlspecialchars($route['from_location'])
                . ' to '
                . htmlspecialchars($route['to_location'])
                . ' One Way Taxi Services';
            ?>
            <div class="destination-block cab-route-block"
                 data-route-id="<?php echo (int) $route['id']; ?>"
                 data-from="<?php echo htmlspecialchars($route['from_location']); ?>"
                 data-to="<?php echo htmlspecialchars($route['to_location']); ?>">
                <h2 class="cab-travel-title"><?php echo $route_title; ?></h2>

                <div class="cab-travel-rows">
                    <?php foreach ($pricing_rows as $pricing): ?>
                        <div class="cab-travel-row">
                            <div class="cab-travel-row__image">
                                <img src="<?php echo htmlspecialchars(cabTravelImageUrl($pricing)); ?>"
                                     alt="<?php echo htmlspecialchars($pricing['display_name']); ?>"
                                     loading="lazy"
                                     onerror="this.src='<?php echo BASE_URL; ?>assets/images/tours/default-tour.jpg'">
                            </div>
                            <div class="cab-travel-row__type">
                                <span>Car Type</span>
                                <strong><?php echo htmlspecialchars($pricing['display_name']); ?></strong>
                            </div>
                            <div class="cab-travel-row__fare">
                                <span>Fare Starts From</span>
                                <strong><?php echo cabTravelFareLabel($pricing['one_way_price']); ?></strong>
                            </div>
                            <div class="cab-travel-row__action">
                                <a href="<?php echo BASE_URL; ?>cab-route-details.php?route_id=<?php echo (int) $route['id']; ?>&pricing_id=<?php echo (int) $pricing['id']; ?>"
                                   class="cab-travel-book-btn">Book Now</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <div class="cab-travel-empty cab-travel-empty--filter" id="cabTravelEmptyFilter" hidden>
        No cab routes found for the selected pick-up and drop-off. Try different locations.
    </div>
</div>

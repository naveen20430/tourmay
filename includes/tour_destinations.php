<?php
/**
 * Many-to-many tour ↔ destination helpers.
 * Keeps tours.destination_id as the primary (first) destination for legacy queries.
 */

function ensureTourDestinationsSchema() {
    global $db;
    static $ready = false;
    if ($ready) {
        return;
    }

    try {
        $db->getConnection()->exec("CREATE TABLE IF NOT EXISTS tour_destinations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tour_id INT NOT NULL,
            destination_id INT NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_tour_destination (tour_id, destination_id),
            INDEX idx_tour_id (tour_id),
            INDEX idx_destination_id (destination_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Migrate legacy single destination_id rows once
        $db->getConnection()->exec("
            INSERT IGNORE INTO tour_destinations (tour_id, destination_id, sort_order)
            SELECT id, destination_id, 0
            FROM tours
            WHERE destination_id IS NOT NULL AND destination_id > 0
        ");

        $ready = true;
    } catch (Exception $e) {
        // Table may already exist or DB may be unavailable
    }
}

/**
 * Normalize posted destination IDs to unique positive ints.
 *
 * @param mixed $input
 * @return int[]
 */
function normalizeTourDestinationIds($input) {
    if (!is_array($input)) {
        if ($input === null || $input === '') {
            return [];
        }
        $input = [$input];
    }

    $ids = [];
    foreach ($input as $id) {
        $id = (int) $id;
        if ($id > 0) {
            $ids[$id] = $id;
        }
    }
    return array_values($ids);
}

/**
 * Replace destinations for a tour. First ID becomes tours.destination_id (primary).
 *
 * @param int $tourId
 * @param int[]|mixed $destinationIds
 * @param Database|null $db
 */
function saveTourDestinations($tourId, $destinationIds, $db = null) {
    if ($db === null) {
        global $db;
    }
    ensureTourDestinationsSchema();

    $tourId = (int) $tourId;
    if ($tourId <= 0) {
        return;
    }

    $ids = normalizeTourDestinationIds($destinationIds);
    $primaryId = $ids[0] ?? null;

    $db->execute("DELETE FROM tour_destinations WHERE tour_id = ?", [$tourId]);

    foreach ($ids as $sort => $destinationId) {
        $db->execute(
            "INSERT INTO tour_destinations (tour_id, destination_id, sort_order) VALUES (?, ?, ?)",
            [$tourId, $destinationId, (int) $sort]
        );
    }

    $db->execute(
        "UPDATE tours SET destination_id = ? WHERE id = ?",
        [$primaryId, $tourId]
    );
}

/**
 * @param int $tourId
 * @return int[]
 */
function getTourDestinationIds($tourId, $db = null) {
    if ($db === null) {
        global $db;
    }
    ensureTourDestinationsSchema();

    $rows = $db->fetchAll(
        "SELECT destination_id FROM tour_destinations WHERE tour_id = ? ORDER BY sort_order ASC, id ASC",
        [(int) $tourId]
    );

    if (empty($rows)) {
        // Fallback to legacy column
        $tour = $db->fetch("SELECT destination_id FROM tours WHERE id = ?", [(int) $tourId]);
        if (!empty($tour['destination_id'])) {
            return [(int) $tour['destination_id']];
        }
        return [];
    }

    return array_map(static function ($row) {
        return (int) $row['destination_id'];
    }, $rows);
}

/**
 * @param int $tourId
 * @return array[] destination rows (id, name, slug, country, ...)
 */
function getTourDestinations($tourId, $db = null) {
    if ($db === null) {
        global $db;
    }
    ensureTourDestinationsSchema();

    $rows = $db->fetchAll(
        "SELECT d.*
         FROM tour_destinations td
         INNER JOIN destinations d ON d.id = td.destination_id
         WHERE td.tour_id = ?
         ORDER BY td.sort_order ASC, d.name ASC",
        [(int) $tourId]
    );

    if (!empty($rows)) {
        return $rows;
    }

    $legacy = $db->fetch(
        "SELECT d.*
         FROM tours t
         INNER JOIN destinations d ON d.id = t.destination_id
         WHERE t.id = ?",
        [(int) $tourId]
    );

    return $legacy ? [$legacy] : [];
}

/**
 * Comma-separated destination names for a tour.
 */
function formatTourDestinationNames($tourId, $db = null) {
    $destinations = getTourDestinations($tourId, $db);
    if (empty($destinations)) {
        return '';
    }
    return implode(', ', array_map(static function ($d) {
        return $d['name'];
    }, $destinations));
}

/**
 * SQL EXISTS fragment: tour linked to destination by id (join table or legacy FK).
 */
function tourLinkedToDestinationIdSql($tourAlias = 't', $destinationParam = '?') {
    return "(
        EXISTS (
            SELECT 1 FROM tour_destinations td_link
            WHERE td_link.tour_id = {$tourAlias}.id AND td_link.destination_id = {$destinationParam}
        )
        OR {$tourAlias}.destination_id = {$destinationParam}
    )";
}

/**
 * SQL EXISTS fragment: tour linked to destination by slug.
 */
function tourLinkedToDestinationSlugSql($tourAlias = 't', $slugParam = '?') {
    return "(
        EXISTS (
            SELECT 1
            FROM tour_destinations td_link
            INNER JOIN destinations d_link ON d_link.id = td_link.destination_id
            WHERE td_link.tour_id = {$tourAlias}.id AND d_link.slug = {$slugParam}
        )
        OR EXISTS (
            SELECT 1 FROM destinations d_legacy
            WHERE d_legacy.id = {$tourAlias}.destination_id AND d_legacy.slug = {$slugParam}
        )
    )";
}

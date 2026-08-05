<?php
/**
 * Admin-managed pickup time slots (global + per-tour).
 */

function ensurePickupTimesSchema(): void
{
    global $db;
    static $ready = false;
    if ($ready) {
        return;
    }

    $pdo = $db->getConnection();
    $pdo->exec("CREATE TABLE IF NOT EXISTS pickup_time_slots (
        id INT AUTO_INCREMENT PRIMARY KEY,
        time_value VARCHAR(5) NOT NULL,
        label VARCHAR(32) NOT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_pickup_time_value (time_value),
        INDEX idx_pickup_active_sort (is_active, sort_order, time_value)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS tour_pickup_time_slots (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tour_id INT NOT NULL,
        time_value VARCHAR(5) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_tour_pickup_time (tour_id, time_value),
        INDEX idx_tour_pickup_tour (tour_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    seedDefaultPickupTimeSlotsIfEmpty();
    $ready = true;
}

function formatPickupTimeLabel(string $timeValue): string
{
    if (!preg_match('/^(\d{2}):(\d{2})$/', $timeValue, $m)) {
        return $timeValue;
    }
    $hour = (int) $m[1];
    $minute = (int) $m[2];
    $labelHour = $hour % 12;
    if ($labelHour === 0) {
        $labelHour = 12;
    }
    $ampm = $hour < 12 ? 'AM' : 'PM';
    return sprintf('%d:%02d %s', $labelHour, $minute, $ampm);
}

function normalizePickupTimeValue($time): string
{
    $time = trim((string) $time);
    if (preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $time, $m)) {
        $hour = (int) $m[1];
        $minute = (int) $m[2];
        if ($hour >= 0 && $hour <= 23 && $minute >= 0 && $minute <= 59) {
            return sprintf('%02d:%02d', $hour, $minute);
        }
    }
    return '';
}

/**
 * Build default 9:00 AM – 6:00 PM slots (30-minute steps).
 * @return array<int, array{value:string,label:string}>
 */
function buildDefaultPickupTimeOptions(): array
{
    $options = [];
    for ($hour = 9; $hour <= 18; $hour++) {
        foreach ([0, 30] as $minute) {
            if ($hour === 18 && $minute > 0) {
                break;
            }
            $value = sprintf('%02d:%02d', $hour, $minute);
            $options[] = [
                'value' => $value,
                'label' => formatPickupTimeLabel($value),
            ];
        }
    }
    return $options;
}

function seedDefaultPickupTimeSlotsIfEmpty(): void
{
    global $db;
    try {
        $count = (int) ($db->fetch("SELECT COUNT(*) AS c FROM pickup_time_slots")['c'] ?? 0);
    } catch (Exception $e) {
        return;
    }
    if ($count > 0) {
        return;
    }

    $sort = 10;
    foreach (buildDefaultPickupTimeOptions() as $opt) {
        $db->execute(
            "INSERT IGNORE INTO pickup_time_slots (time_value, label, sort_order, is_active) VALUES (?, ?, ?, 1)",
            [$opt['value'], $opt['label'], $sort]
        );
        $sort += 10;
    }
}

/**
 * @return array<int, array{id?:int,value:string,label:string,is_active?:int,sort_order?:int}>
 */
function getAllPickupTimeSlots(bool $activeOnly = false): array
{
    global $db;
    ensurePickupTimesSchema();

    $sql = "SELECT id, time_value, label, sort_order, is_active
            FROM pickup_time_slots";
    if ($activeOnly) {
        $sql .= " WHERE is_active = 1";
    }
    $sql .= " ORDER BY sort_order ASC, time_value ASC";

    try {
        $rows = $db->fetchAll($sql);
    } catch (Exception $e) {
        $rows = [];
    }

    if (empty($rows) && $activeOnly) {
        return buildDefaultPickupTimeOptions();
    }

    $out = [];
    foreach ($rows as $row) {
        $value = normalizePickupTimeValue($row['time_value'] ?? '');
        if ($value === '') {
            continue;
        }
        $out[] = [
            'id' => (int) ($row['id'] ?? 0),
            'value' => $value,
            'label' => trim((string) ($row['label'] ?? '')) !== ''
                ? (string) $row['label']
                : formatPickupTimeLabel($value),
            'is_active' => (int) ($row['is_active'] ?? 1),
            'sort_order' => (int) ($row['sort_order'] ?? 0),
        ];
    }
    return $out;
}

/**
 * Selected time values for a tour (empty = use all active global defaults).
 * @return string[]
 */
function getTourPickupTimeValues(int $tourId): array
{
    global $db;
    if ($tourId <= 0) {
        return [];
    }
    ensurePickupTimesSchema();
    try {
        $rows = $db->fetchAll(
            "SELECT time_value FROM tour_pickup_time_slots WHERE tour_id = ? ORDER BY time_value ASC",
            [$tourId]
        );
    } catch (Exception $e) {
        return [];
    }
    $values = [];
    foreach ($rows as $row) {
        $value = normalizePickupTimeValue($row['time_value'] ?? '');
        if ($value !== '') {
            $values[] = $value;
        }
    }
    return array_values(array_unique($values));
}

function tourUsesCustomPickupTimes(int $tourId): bool
{
    return !empty(getTourPickupTimeValues($tourId));
}

/**
 * Options shown on cart/checkout for a tour.
 * @return array<int, array{value:string,label:string}>
 */
function getPickupTimeOptionsForTour(?int $tourId = null): array
{
    $allActive = getAllPickupTimeSlots(true);
    if ($tourId === null || $tourId <= 0) {
        return array_map(static function ($row) {
            return ['value' => $row['value'], 'label' => $row['label']];
        }, $allActive);
    }

    $custom = getTourPickupTimeValues($tourId);
    if (empty($custom)) {
        return array_map(static function ($row) {
            return ['value' => $row['value'], 'label' => $row['label']];
        }, $allActive);
    }

    $byValue = [];
    foreach ($allActive as $row) {
        $byValue[$row['value']] = $row['label'];
    }
    // Include inactive custom times still assigned so existing carts can validate/display
    foreach (getAllPickupTimeSlots(false) as $row) {
        if (!isset($byValue[$row['value']])) {
            $byValue[$row['value']] = $row['label'];
        }
    }

    $out = [];
    foreach ($custom as $value) {
        $out[] = [
            'value' => $value,
            'label' => $byValue[$value] ?? formatPickupTimeLabel($value),
        ];
    }
    usort($out, static function ($a, $b) {
        return strcmp($a['value'], $b['value']);
    });
    return $out;
}

/**
 * @return string[]
 */
function getAllowedPickupTimesForTour(?int $tourId = null): array
{
    return array_column(getPickupTimeOptionsForTour($tourId), 'value');
}

function isValidPickupTimeForTour($time, ?int $tourId = null): bool
{
    $time = normalizePickupTimeValue($time);
    if ($time === '') {
        return false;
    }
    return in_array($time, getAllowedPickupTimesForTour($tourId), true);
}

function saveTourPickupTimes(int $tourId, array $timeValues, bool $useDefaults = false): void
{
    global $db;
    if ($tourId <= 0) {
        return;
    }
    ensurePickupTimesSchema();

    $db->execute("DELETE FROM tour_pickup_time_slots WHERE tour_id = ?", [$tourId]);
    if ($useDefaults) {
        return;
    }

    $clean = [];
    foreach ($timeValues as $value) {
        $normalized = normalizePickupTimeValue($value);
        if ($normalized !== '') {
            $clean[$normalized] = true;
        }
    }
    $clean = array_keys($clean);
    sort($clean);

    // If admin unchecked everything, treat as defaults
    if (empty($clean)) {
        return;
    }

    foreach ($clean as $value) {
        $db->execute(
            "INSERT IGNORE INTO tour_pickup_time_slots (tour_id, time_value) VALUES (?, ?)",
            [$tourId, $value]
        );
    }
}

function addPickupTimeSlot(string $timeValue, ?string $label = null, int $sortOrder = 0, bool $active = true): array
{
    global $db;
    ensurePickupTimesSchema();
    $timeValue = normalizePickupTimeValue($timeValue);
    if ($timeValue === '') {
        return ['ok' => false, 'message' => 'Invalid time. Use HH:MM (24-hour).'];
    }
    $label = trim((string) $label);
    if ($label === '') {
        $label = formatPickupTimeLabel($timeValue);
    }
    if ($sortOrder <= 0) {
        $max = (int) ($db->fetch("SELECT MAX(sort_order) AS m FROM pickup_time_slots")['m'] ?? 0);
        $sortOrder = $max + 10;
    }
    try {
        $db->execute(
            "INSERT INTO pickup_time_slots (time_value, label, sort_order, is_active)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE label = VALUES(label), sort_order = VALUES(sort_order), is_active = VALUES(is_active)",
            [$timeValue, $label, $sortOrder, $active ? 1 : 0]
        );
        return ['ok' => true, 'message' => 'Pickup time saved.', 'value' => $timeValue];
    } catch (Exception $e) {
        return ['ok' => false, 'message' => $e->getMessage()];
    }
}

function setPickupTimeSlotActive(int $id, bool $active): bool
{
    global $db;
    ensurePickupTimesSchema();
    if ($id <= 0) {
        return false;
    }
    $db->execute("UPDATE pickup_time_slots SET is_active = ? WHERE id = ?", [$active ? 1 : 0, $id]);
    return true;
}

function deletePickupTimeSlot(int $id): bool
{
    global $db;
    ensurePickupTimesSchema();
    if ($id <= 0) {
        return false;
    }
    $row = $db->fetch("SELECT time_value FROM pickup_time_slots WHERE id = ?", [$id]);
    if (!$row) {
        return false;
    }
    $db->execute("DELETE FROM pickup_time_slots WHERE id = ?", [$id]);
    $db->execute("DELETE FROM tour_pickup_time_slots WHERE time_value = ?", [$row['time_value']]);
    return true;
}

/**
 * Replace/generate global slots from a range.
 */
function generatePickupTimeSlots(string $start, string $end, int $intervalMinutes = 30, bool $replaceAll = false): array
{
    global $db;
    ensurePickupTimesSchema();

    $start = normalizePickupTimeValue($start);
    $end = normalizePickupTimeValue($end);
    if ($start === '' || $end === '') {
        return ['ok' => false, 'message' => 'Start and end times are required.'];
    }
    if ($intervalMinutes < 5 || $intervalMinutes > 120) {
        return ['ok' => false, 'message' => 'Interval must be between 5 and 120 minutes.'];
    }

    $startMin = ((int) substr($start, 0, 2)) * 60 + (int) substr($start, 3, 2);
    $endMin = ((int) substr($end, 0, 2)) * 60 + (int) substr($end, 3, 2);
    if ($endMin < $startMin) {
        return ['ok' => false, 'message' => 'End time must be after start time.'];
    }

    if ($replaceAll) {
        $db->execute("DELETE FROM pickup_time_slots");
        // Keep tour mappings only for times we regenerate
    }

    $created = 0;
    $sort = 10;
    for ($mins = $startMin; $mins <= $endMin; $mins += $intervalMinutes) {
        $value = sprintf('%02d:%02d', intdiv($mins, 60), $mins % 60);
        $result = addPickupTimeSlot($value, formatPickupTimeLabel($value), $sort, true);
        if (!empty($result['ok'])) {
            $created++;
        }
        $sort += 10;
    }

    if ($replaceAll) {
        // Drop tour assignments for times that no longer exist
        $db->execute(
            "DELETE t FROM tour_pickup_time_slots t
             LEFT JOIN pickup_time_slots p ON p.time_value = t.time_value
             WHERE p.id IS NULL"
        );
    }

    return ['ok' => true, 'message' => "Saved {$created} pickup time slot(s)."];
}

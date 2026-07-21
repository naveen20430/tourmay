<?php
/**
 * Cab Options Helper Functions
 * Manages cab types, pricing, and related functionality
 */

class CabOptions {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Get all available cab types
     */
    public function getAllCabTypes() {
        return $this->db->fetchAll(
            "SELECT * FROM cab_types WHERE status = 'active' ORDER BY base_price ASC"
        );
    }
    
    /**
     * Get cab type by name
     */
    public function getCabTypeByName($name) {
        return $this->db->fetch(
            "SELECT * FROM cab_types WHERE name = ? AND status = 'active'", 
            [$name]
        );
    }
    
    /**
     * Calculate cab price based on tour duration and type
     */
    public function calculateCabPrice($cab_type_name, $duration_days = 1, $extra_km = 0) {
        $cab_type = $this->getCabTypeByName($cab_type_name);
        
        if (!$cab_type) {
            return 0;
        }
        
        // Base price covers the tour duration
        $base_cost = $cab_type['base_price'] * $duration_days;
        
        // Additional cost for extra kilometers
        $extra_cost = $extra_km * $cab_type['price_per_km'];
        
        return $base_cost + $extra_cost;
    }
    
    /**
     * Get cab options for dropdown display
     */
    public function getCabOptionsForDropdown() {
        $cab_types = $this->getAllCabTypes();
        $options = [];
        
        foreach ($cab_types as $cab) {
            $features = json_decode($cab['features'], true) ?: [];
            $feature_text = !empty($features) ? ' (' . implode(', ', array_slice($features, 0, 2)) . ')' : '';
            
            $options[] = [
                'value' => $cab['name'],
                'text' => $cab['display_name'] . ' - ₹' . number_format($cab['base_price'], 0) . $feature_text,
                'price' => $cab['base_price'],
                'max_passengers' => $cab['max_passengers'],
                'description' => $cab['description'],
                'display_name' => $cab['display_name'],
                'image_path' => $cab['image_path'] ?? '',
                'image_url' => cabTypeImageUrl($cab),
            ];
        }
        
        return $options;
    }
    
    /**
     * Check if selected cab can accommodate the number of people
     */
    public function canAccommodate($cab_type_name, $number_of_people) {
        $cab_type = $this->getCabTypeByName($cab_type_name);
        
        if (!$cab_type) {
            return false;
        }
        
        return $number_of_people <= $cab_type['max_passengers'];
    }
    
    /**
     * Get recommended cab types for given number of people
     */
    public function getRecommendedCabs($number_of_people) {
        $all_cabs = $this->getAllCabTypes();
        $recommended = [];
        
        foreach ($all_cabs as $cab) {
            if ($cab['max_passengers'] >= $number_of_people) {
                $recommended[] = $cab;
            }
        }
        
        return $recommended;
    }
}

/**
 * Static function to get cab display name
 */
function cabTypeImageUrl(array $cab) {
    $imagePath = trim((string) ($cab['image_path'] ?? ''));
    if ($imagePath !== '' && defined('BASE_PATH') && is_file(BASE_PATH . $imagePath)) {
        return BASE_URL . $imagePath;
    }

    $slug = strtolower((string) ($cab['name'] ?? ''));
    $candidates = [
        'sedan' => 'assets/images/cabs/sedan.jpg',
        'innova' => 'assets/images/cabs/innova.jpg',
        'ertiga' => 'assets/images/cabs/ertiga.jpg',
        'tempo_traveller' => 'assets/images/cabs/tempo.jpg',
    ];

    $relative = $candidates[$slug] ?? '';
    if ($relative !== '' && defined('BASE_PATH') && is_file(BASE_PATH . $relative)) {
        return BASE_URL . $relative;
    }

    if (defined('BASE_URL')) {
        return BASE_URL . 'assets/images/tours/default-tour.jpg';
    }

    return 'assets/images/tours/default-tour.jpg';
}

function getActivityPickupPlaces() {
    return ['Hotel', 'Lift Parking', 'Others'];
}

/**
 * Static function to get cab display name
 */
function getCabDisplayName($cab_type) {
    $cab_names = [
        'sedan' => 'Sedan',
        'ertiga' => 'Ertiga',
        'innova' => 'Innova',
        'tempo_traveller' => 'Tempo Traveller',
        // Legacy support
        'xuv_tavera' => 'Xylo / XUV / TAVERA'
    ];
    
    return $cab_names[$cab_type] ?? ucfirst(str_replace('_', ' ', $cab_type));
}

/**
 * Ensure per-tour cab price table exists (tour_id × cab_type_id).
 */
function ensureTourCabPricesSchema() {
    global $db;
    static $ready = false;
    if ($ready) {
        return;
    }

    try {
        $db->getConnection()->exec("CREATE TABLE IF NOT EXISTS tour_cab_prices (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tour_id INT NOT NULL,
            cab_type_id INT NOT NULL,
            price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_tour_cab (tour_id, cab_type_id),
            INDEX idx_tour_id (tour_id),
            INDEX idx_cab_type_id (cab_type_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $ready = true;
    } catch (Exception $e) {
        // Table may already exist or DB may be unavailable
    }
}

/**
 * Get map of cab_type name => price for a tour.
 * Missing cab types fall back to cab_types.base_price.
 *
 * @return array<string,float>
 */
function getTourCabPriceMap($tourId, $db = null) {
    if ($db === null) {
        global $db;
    }
    ensureTourCabPricesSchema();

    $tourId = (int) $tourId;
    $map = [];

    try {
        $cabTypes = $db->fetchAll("SELECT id, name, base_price FROM cab_types WHERE status = 'active'");
        foreach ($cabTypes as $cab) {
            $map[$cab['name']] = (float) $cab['base_price'];
        }

        if ($tourId > 0) {
            $rows = $db->fetchAll("
                SELECT ct.name, tcp.price
                FROM tour_cab_prices tcp
                INNER JOIN cab_types ct ON ct.id = tcp.cab_type_id
                WHERE tcp.tour_id = ? AND ct.status = 'active'
            ", [$tourId]);
            foreach ($rows as $row) {
                $price = (float) $row['price'];
                if ($price > 0) {
                    $map[$row['name']] = $price;
                }
            }
        }
    } catch (Exception $e) {
        // Fall back to whatever we already have
    }

    return $map;
}

/**
 * Get cab options for a specific tour (uses tour price when set).
 */
function getCabOptionsForTour($tourId, $db = null) {
    if ($db === null) {
        global $db;
    }
    $cabOptions = new CabOptions($db);
    $options = $cabOptions->getCabOptionsForDropdown();
    $priceMap = getTourCabPriceMap($tourId, $db);

    foreach ($options as &$option) {
        $name = $option['value'];
        $basePrice = (float) $option['price'];
        $price = isset($priceMap[$name]) ? (float) $priceMap[$name] : $basePrice;
        $option['price'] = $price;
        $option['is_tour_price'] = $price !== $basePrice;
        $featureSuffix = '';
        if (preg_match('/\s(\([^)]*\))$/', (string) $option['text'], $m)) {
            $featureSuffix = ' ' . $m[1];
        }
        $option['text'] = ($option['display_name'] ?? $name) . ' - ₹' . number_format($price, 0) . $featureSuffix;
    }
    unset($option);

    return $options;
}

/**
 * Save cab prices for a tour.
 * $pricesByCabTypeId = [cab_type_id => price]
 * Empty/zero prices remove the override (fall back to base).
 */
function saveTourCabPrices($tourId, array $pricesByCabTypeId, $db = null) {
    if ($db === null) {
        global $db;
    }
    ensureTourCabPricesSchema();

    $tourId = (int) $tourId;
    if ($tourId <= 0) {
        return false;
    }

    foreach ($pricesByCabTypeId as $cabTypeId => $price) {
        $cabTypeId = (int) $cabTypeId;
        $price = is_numeric($price) ? (float) $price : 0.0;
        if ($cabTypeId <= 0) {
            continue;
        }

        if ($price <= 0) {
            $db->execute("DELETE FROM tour_cab_prices WHERE tour_id = ? AND cab_type_id = ?", [$tourId, $cabTypeId]);
            continue;
        }

        $existing = $db->fetch(
            "SELECT id FROM tour_cab_prices WHERE tour_id = ? AND cab_type_id = ?",
            [$tourId, $cabTypeId]
        );
        if ($existing) {
            $db->execute(
                "UPDATE tour_cab_prices SET price = ? WHERE tour_id = ? AND cab_type_id = ?",
                [$price, $tourId, $cabTypeId]
            );
        } else {
            $db->execute(
                "INSERT INTO tour_cab_prices (tour_id, cab_type_id, price) VALUES (?, ?, ?)",
                [$tourId, $cabTypeId, $price]
            );
        }
    }

    return true;
}

/**
 * Get tour-specific cab pricing (legacy title-based table).
 */
function getTourSpecificPricing($tour_name, $db) {
    try {
        $pricing = $db->fetch(
            "SELECT * FROM tour_cab_pricing WHERE tour_name = ? LIMIT 1",
            [$tour_name]
        );
        return $pricing;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Get cab price for a tour (flat amount from backend — not per day).
 * Prefers tour_id-based tour_cab_prices, then legacy title table, then cab base price.
 */
function getCabPriceForTour($tour_name_or_id, $cab_type, $db, $duration_days = 1) {
    $cab_type = trim((string) $cab_type);
    $price = 0.0;

    if (is_numeric($tour_name_or_id)) {
        $map = getTourCabPriceMap((int) $tour_name_or_id, $db);
        if (isset($map[$cab_type])) {
            $price = (float) $map[$cab_type];
        }
    } else {
        // Try resolve tour id from title for new table
        try {
            $tour = $db->fetch("SELECT id FROM tours WHERE title = ? LIMIT 1", [(string) $tour_name_or_id]);
            if ($tour) {
                $map = getTourCabPriceMap((int) $tour['id'], $db);
                if (isset($map[$cab_type])) {
                    $price = (float) $map[$cab_type];
                }
            }
        } catch (Exception $e) {
            // ignore
        }

        if ($price <= 0) {
            $tour_pricing = getTourSpecificPricing($tour_name_or_id, $db);
            if ($tour_pricing) {
                switch ($cab_type) {
                    case 'sedan':
                        $price = (float) $tour_pricing['sedan_price'];
                        break;
                    case 'ertiga':
                        $price = (float) $tour_pricing['ertiga_price'];
                        break;
                    case 'innova':
                        $price = (float) $tour_pricing['innova_price'];
                        break;
                    case 'tempo_traveller':
                        $price = (float) $tour_pricing['tempo_traveller_price'];
                        break;
                    case 'xuv_tavera':
                        $price = (float) ($tour_pricing['xuv_tavera_price'] ?? 0);
                        break;
                }
            }
        }
    }

    if ($price <= 0) {
        $cab_options = new CabOptions($db);
        $cab_type_data = $cab_options->getCabTypeByName($cab_type);
        $price = $cab_type_data ? (float) $cab_type_data['base_price'] : 0.0;
    }

    return $price;
}

/**
 * Get default cab pricing (fallback if database is not available)
 */
function getDefaultCabPricing() {
    return [
        'sedan' => [
            'name' => 'sedan',
            'display_name' => 'Sedan',
            'base_price' => 3000.00,
            'max_passengers' => 4,
            'description' => 'Comfortable sedan car suitable for small groups'
        ],
        'ertiga' => [
            'name' => 'ertiga',
            'display_name' => 'Ertiga',
            'base_price' => 4000.00,
            'max_passengers' => 7,
            'description' => 'Spacious Ertiga perfect for medium-sized groups'
        ],
        'innova' => [
            'name' => 'innova',
            'display_name' => 'Innova',
            'base_price' => 5200.00,
            'max_passengers' => 7,
            'description' => 'Premium Toyota Innova for comfortable group travel'
        ],
        'tempo_traveller' => [
            'name' => 'tempo_traveller',
            'display_name' => 'Tempo Traveller',
            'base_price' => 7000.00,
            'max_passengers' => 12,
            'description' => 'Spacious Tempo Traveller for large groups and extended tours'
        ]
    ];
}
?>

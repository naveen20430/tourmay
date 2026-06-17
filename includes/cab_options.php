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
                'text' => $cab['display_name'] . ' - ₹' . number_format($cab['base_price'], 0) . '/day' . $feature_text,
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
        'xuv_tavera' => 'assets/images/cabs/suv.jpg',
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
 * Get tour-specific cab pricing
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
 * Get cab price for specific tour and cab type
 */
function getCabPriceForTour($tour_name, $cab_type, $db) {
    $tour_pricing = getTourSpecificPricing($tour_name, $db);
    
    if ($tour_pricing) {
        switch ($cab_type) {
            case 'sedan':
                return $tour_pricing['sedan_price'];
            case 'ertiga':
                return $tour_pricing['ertiga_price'];
            case 'innova':
                return $tour_pricing['innova_price'];
            case 'tempo_traveller':
                return $tour_pricing['tempo_traveller_price'];
        }
    }
    
    // Fallback to base pricing
    $cab_options = new CabOptions($db);
    $cab_type_data = $cab_options->getCabTypeByName($cab_type);
    return $cab_type_data ? $cab_type_data['base_price'] : 0;
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

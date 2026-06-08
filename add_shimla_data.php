<?php
require_once 'config/config.php';

echo "<h2>Adding Shimla Destination Data...</h2>";

try {
    // Check if Shimla already exists
    $existing = $db->fetch("SELECT id FROM destinations WHERE slug = 'shimla'");
    
    if ($existing) {
        echo "<p style='color: orange;'>⚠️ Shimla destination already exists! Updating instead...</p>";
        
        // Update existing Shimla data
        $db->query("
            UPDATE destinations SET
                name = ?,
                description = ?,
                short_description = ?,
                country = ?,
                city = ?,
                popular = 1,
                status = 'active',
                meta_title = ?,
                meta_description = ?
            WHERE slug = 'shimla'
        ", [
            'Shimla',
            'Shimla, the capital of Himachal Pradesh, is one of India\'s most popular hill stations. Known as the "Queen of Hills," Shimla offers a perfect blend of natural beauty, colonial architecture, and pleasant weather year-round. The town is set amidst pine-clad mountains and offers spectacular views of the snow-capped Himalayas. 

Key Attractions:
- Mall Road: The heart of Shimla with shops, restaurants, and colonial buildings
- The Ridge: A large open space with stunning mountain views
- Christ Church: A historic neo-Gothic church built in 1857
- Jakhu Temple: Dedicated to Lord Hanuman, situated on Jakhu Hill
- Viceregal Lodge: Former summer residence of British viceroys
- Kufri: Nearby hill station perfect for skiing and adventure activities
- Green Valley: Scenic valley offering breathtaking views
- Scandal Point: Popular meeting point with panoramic views

Best Time to Visit:
- Summer (April to June): Pleasant weather, perfect for sightseeing
- Winter (December to February): Snowfall and winter sports
- Monsoon (July to September): Lush green landscapes but heavy rainfall

Activities:
- Heritage walks through colonial architecture
- Shopping for woolens and handicrafts on Mall Road
- Toy Train ride on the UNESCO World Heritage Kalka-Shimla Railway
- Trekking and hiking in nearby hills
- Ice skating at Asia\'s largest open-air rink (in winter)
- Adventure sports in Kufri

Shimla offers a perfect escape from the hustle and bustle of city life, making it an ideal destination for families, couples, and solo travelers alike.',
            'The Queen of Hills, Shimla offers stunning Himalayan views, colonial charm, and pleasant weather. Perfect destination for families and couples seeking mountain getaway.',
            'India',
            'Shimla',
            'Shimla Tourism - Best Hill Station Tours & Packages | TravHub',
            'Explore Shimla, the Queen of Hills. Book best Shimla tour packages with TravHub. Experience colonial architecture, Mall Road shopping, and stunning Himalayan views.'
        ]);
        
        $shimla_id = $existing['id'];
        echo "<p style='color: green;'>✅ Shimla destination updated successfully!</p>";
        
    } else {
        // Insert new Shimla destination
        $db->query("
            INSERT INTO destinations (
                name, slug, description, short_description, country, city, 
                featured_image, popular, status, meta_title, meta_description
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ", [
            'Shimla',
            'shimla',
            'Shimla, the capital of Himachal Pradesh, is one of India\'s most popular hill stations. Known as the "Queen of Hills," Shimla offers a perfect blend of natural beauty, colonial architecture, and pleasant weather year-round. The town is set amidst pine-clad mountains and offers spectacular views of the snow-capped Himalayas. 

Key Attractions:
- Mall Road: The heart of Shimla with shops, restaurants, and colonial buildings
- The Ridge: A large open space with stunning mountain views
- Christ Church: A historic neo-Gothic church built in 1857
- Jakhu Temple: Dedicated to Lord Hanuman, situated on Jakhu Hill
- Viceregal Lodge: Former summer residence of British viceroys
- Kufri: Nearby hill station perfect for skiing and adventure activities
- Green Valley: Scenic valley offering breathtaking views
- Scandal Point: Popular meeting point with panoramic views

Best Time to Visit:
- Summer (April to June): Pleasant weather, perfect for sightseeing
- Winter (December to February): Snowfall and winter sports
- Monsoon (July to September): Lush green landscapes but heavy rainfall

Activities:
- Heritage walks through colonial architecture
- Shopping for woolens and handicrafts on Mall Road
- Toy Train ride on the UNESCO World Heritage Kalka-Shimla Railway
- Trekking and hiking in nearby hills
- Ice skating at Asia\'s largest open-air rink (in winter)
- Adventure sports in Kufri

Shimla offers a perfect escape from the hustle and bustle of city life, making it an ideal destination for families, couples, and solo travelers alike.',
            'The Queen of Hills, Shimla offers stunning Himalayan views, colonial charm, and pleasant weather. Perfect destination for families and couples seeking mountain getaway.',
            'India',
            'Shimla',
            'assets/images/destinations/shimla.jpg',
            1,
            'active',
            'Shimla Tourism - Best Hill Station Tours & Packages | TravHub',
            'Explore Shimla, the Queen of Hills. Book best Shimla tour packages with TravHub. Experience colonial architecture, Mall Road shopping, and stunning Himalayan views.'
        ]);
        
        $shimla_id = $db->lastInsertId();
        echo "<p style='color: green;'>✅ Shimla destination added successfully! ID: $shimla_id</p>";
    }
    
    // Add sample tours for Shimla
    echo "<h3>Adding Sample Tours...</h3>";
    
    $tours = [
        [
            'title' => 'Shimla Manali Honeymoon Package',
            'slug' => 'shimla-manali-honeymoon-package',
            'description' => 'A perfect romantic getaway combining the charm of Shimla with the adventure of Manali. This honeymoon package includes visits to Mall Road, Kufri, Solang Valley, and Rohtang Pass. Enjoy comfortable accommodations, candlelight dinners, and create memories that last a lifetime.',
            'short_description' => 'Romantic 6-day honeymoon package covering Shimla and Manali with luxury stays and special arrangements for couples.',
            'price' => 25000.00,
            'discount_price' => 22500.00,
            'duration_days' => 6,
            'duration_nights' => 5,
            'max_people' => 2,
            'inclusions' => '- 5 Nights accommodation in 4-star hotels
- Daily breakfast and dinner
- Private cab for all transfers and sightseeing
- Candlelight dinner
- Flower bed decoration
- Welcome drink on arrival
- All applicable taxes',
            'exclusions' => '- Lunch during the tour
- Entry fees to monuments and attractions
- Adventure activity charges
- Personal expenses
- Travel insurance',
            'difficulty_level' => 'easy',
            'tour_type' => 'mountain',
            'featured' => 1,
            'popular' => 1
        ],
        [
            'title' => 'Shimla Kufri Adventure Tour',
            'slug' => 'shimla-kufri-adventure-tour',
            'description' => 'An exciting 4-day adventure tour perfect for families and groups. Explore the colonial charm of Shimla and enjoy thrilling activities in Kufri including horse riding, yak riding, and skiing (in winter). Visit major attractions like Mall Road, Jakhu Temple, and Christ Church.',
            'short_description' => 'Thrilling 4-day adventure tour covering Shimla and Kufri with exciting outdoor activities.',
            'price' => 12000.00,
            'discount_price' => 10500.00,
            'duration_days' => 4,
            'duration_nights' => 3,
            'max_people' => 10,
            'inclusions' => '- 3 Nights accommodation
- Daily breakfast
- Cab for sightseeing
- Guide services
- All transfers
- All taxes',
            'exclusions' => '- Meals not mentioned
- Entry fees
- Adventure activity charges
- Personal expenses
- Travel insurance',
            'difficulty_level' => 'moderate',
            'tour_type' => 'adventure',
            'featured' => 0,
            'popular' => 1
        ],
        [
            'title' => 'Shimla Heritage Walk & Culture Tour',
            'slug' => 'shimla-heritage-culture-tour',
            'description' => 'Discover the rich colonial heritage and cultural charm of Shimla. This 3-day tour focuses on historical landmarks, architecture, and local culture. Visit Viceregal Lodge, Christ Church, Gaiety Theatre, and explore the heritage buildings on Mall Road.',
            'short_description' => 'Cultural 3-day tour exploring Shimla\'s colonial heritage and historical landmarks.',
            'price' => 8500.00,
            'discount_price' => 7500.00,
            'duration_days' => 3,
            'duration_nights' => 2,
            'max_people' => 15,
            'inclusions' => '- 2 Nights hotel accommodation
- Daily breakfast
- Heritage walk with guide
- Museum entry tickets
- Transportation
- All taxes',
            'exclusions' => '- Lunch and dinner
- Shopping expenses
- Camera fees
- Personal expenses
- Travel insurance',
            'difficulty_level' => 'easy',
            'tour_type' => 'cultural',
            'featured' => 0,
            'popular' => 0
        ]
    ];
    
    foreach ($tours as $tour) {
        // Check if tour already exists
        $existing_tour = $db->fetch("SELECT id FROM tours WHERE slug = ?", [$tour['slug']]);
        
        if ($existing_tour) {
            echo "<p style='color: orange;'>⚠️ Tour '{$tour['title']}' already exists. Skipping...</p>";
            continue;
        }
        
        $db->query("
            INSERT INTO tours (
                title, slug, destination_id, description, short_description,
                price, discount_price, duration_days, duration_nights, max_people,
                min_people, featured_image, inclusions, exclusions,
                difficulty_level, tour_type, featured, popular, status,
                meta_title, meta_description
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ", [
            $tour['title'],
            $tour['slug'],
            $shimla_id,
            $tour['description'],
            $tour['short_description'],
            $tour['price'],
            $tour['discount_price'],
            $tour['duration_days'],
            $tour['duration_nights'],
            $tour['max_people'],
            2,
            'assets/images/tours/' . $tour['slug'] . '.jpg',
            $tour['inclusions'],
            $tour['exclusions'],
            $tour['difficulty_level'],
            $tour['tour_type'],
            $tour['featured'],
            $tour['popular'],
            'active',
            $tour['title'] . ' - Best Price | TravHub',
            $tour['short_description']
        ]);
        
        echo "<p style='color: green;'>✅ Tour '{$tour['title']}' added successfully!</p>";
    }
    
    echo "<hr>";
    echo "<h3 style='color: green;'>✅ All Done!</h3>";
    echo "<p><strong>Shimla destination and tours have been added to your database.</strong></p>";
    echo "<p>You can now:</p>";
    echo "<ul>";
    echo "<li>View Shimla on: <a href='" . BASE_URL . "tours/destination/shimla' target='_blank'>" . BASE_URL . "tours/destination/shimla</a></li>";
    echo "<li>Manage in admin panel: <a href='" . BASE_URL . "admin/destinations.php' target='_blank'>Admin > Destinations</a></li>";
    echo "</ul>";
    echo "<p><em>Note: You'll need to upload images for the destination and tours to display properly.</em></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?>

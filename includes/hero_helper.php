<?php
/**
 * Hero Section Helper Functions
 * Use these functions to conditionally load hero CSS and manage hero content
 */

/**
 * Enable hero CSS for the current page
 * Call this function before including header.php to load hero styles
 */
function enableHeroCSS() {
    global $extra_css;
    $extra_css = '<link rel="stylesheet" href="' . BASE_URL . 'assets/css/hero.css">';
}

/**
 * Get hero content from database or fallback data
 * @return array Hero image data
 */
function getHeroContent() {
    global $db;
    
    // Try to get active hero image from database
    try {
        $hero_image = $db->fetch("SELECT * FROM hero_images WHERE is_active = 1 ORDER BY sort_order ASC, created_at DESC LIMIT 1");
    } catch (Exception $e) {
        $hero_image = null;
    }
    
    // Fallback hero data if no active hero image found or database error
    if (!$hero_image) {
        $hero_image = [
            'title' => getSetting('site_name') ? 'Welcome to ' . getSetting('site_name') : 'Welcome to Adventure Tours',
            'subtitle' => getSetting('site_tagline') ?: 'Discover Amazing Destinations',
            'description' => 'Experience the world like never before with our carefully curated travel packages. From exotic destinations to cultural experiences, we make your travel dreams come true.',
            'image_path' => 'assets/images/hero/default-hero.jpg'
        ];
    }
    
    return $hero_image;
}

/**
 * Background images for the homepage search hero (rotating slideshow).
 * @return string[] Relative image paths under assets/
 */
function getHeroSearchBackgrounds() {
    $slides = getHeroSearchSlides();
    $paths = [];
    foreach ($slides as $slide) {
        if (!empty($slide['image_path'])) {
            $paths[] = $slide['image_path'];
        }
    }
    return $paths;
}

/**
 * Full homepage hero slides (image + title + text) in sort order.
 * Each admin hero_images row becomes one slider with its own copy.
 *
 * @return array<int, array{image_path:string,image_url:string,title:string,subtitle:string,description:string}>
 */
function getHeroSearchSlides() {
    global $db;

    $defaults = [
        'title' => 'Luxury Options',
        'subtitle' => '',
        'description' => 'Search for best available hotel options, events, tours, activities and create various easy to book holiday packages.',
    ];

    $slides = [];

    try {
        $rows = $db->fetchAll("
            SELECT title, subtitle, description, image_path, is_active, sort_order
            FROM hero_images
            ORDER BY sort_order ASC, created_at DESC
        ");
        foreach ($rows as $row) {
            // Prefer active slides; if none are marked active, include all rows with files.
            $path = trim((string) ($row['image_path'] ?? ''));
            if ($path === '' || !is_file(BASE_PATH . $path)) {
                continue;
            }
            $title = trim((string) ($row['title'] ?? ''));
            $subtitle = trim((string) ($row['subtitle'] ?? ''));
            $description = trim((string) ($row['description'] ?? ''));
            $slides[] = [
                'image_path' => $path,
                'image_url' => BASE_URL . $path,
                'title' => $title !== '' ? $title : $defaults['title'],
                'subtitle' => $subtitle,
                'description' => $description !== '' ? $description : ($subtitle !== '' ? $subtitle : $defaults['description']),
                'is_active' => !empty($row['is_active']) ? 1 : 0,
            ];
        }
    } catch (Exception $e) {
        $slides = [];
    }

    $active = array_values(array_filter($slides, function ($s) {
        return !empty($s['is_active']);
    }));
    if (!empty($active)) {
        $slides = $active;
    }

    if (empty($slides)) {
        try {
            $tours = $db->fetchAll("
                SELECT featured_image
                FROM tours
                WHERE status = 'active'
                  AND featured_image IS NOT NULL
                  AND featured_image != ''
                ORDER BY featured DESC, popular DESC, id DESC
                LIMIT 6
            ");
            foreach ($tours as $tour) {
                $path = trim((string) ($tour['featured_image'] ?? ''));
                if ($path !== '' && is_file(BASE_PATH . $path)) {
                    $slides[] = [
                        'image_path' => $path,
                        'image_url' => BASE_URL . $path,
                        'title' => $defaults['title'],
                        'subtitle' => $defaults['subtitle'],
                        'description' => $defaults['description'],
                        'is_active' => 1,
                    ];
                }
            }
        } catch (Exception $e) {
            // ignore
        }
    }

    return array_values($slides);
}

/**
 * Title and copy for the homepage search hero.
 * Uses the first hero slide that has text (kept for older callers).
 * @return array{title: string, subtitle: string, description: string}
 */
function getHeroSearchText() {
    $slides = getHeroSearchSlides();
    if (!empty($slides[0])) {
        return [
            'title' => $slides[0]['title'],
            'subtitle' => $slides[0]['subtitle'],
            'description' => $slides[0]['description'],
        ];
    }

    return [
        'title' => 'Luxury Options',
        'subtitle' => '',
        'description' => 'Search for best available hotel options, events, tours, activities and create various easy to book holiday packages.',
    ];
}

/**
 * Title and copy for the homepage search hero.
 * Uses the first hero_images row (by sort order) that has a title or description set.
 * Newer background-only uploads with empty text are skipped so they do not force the fallback.
 * @return array{title: string, subtitle: string, description: string}
 */
function getHeroSearchText() {
    global $db;

    $defaults = [
        'title' => 'Luxury Options',
        'subtitle' => '',
        'description' => 'Search for best available hotel options, events, tours, activities and create various easy to book holiday packages.',
    ];

    try {
        $hero = $db->fetch("
            SELECT title, subtitle, description
            FROM hero_images
            WHERE TRIM(COALESCE(title, '')) != ''
               OR TRIM(COALESCE(description, '')) != ''
               OR TRIM(COALESCE(subtitle, '')) != ''
            ORDER BY sort_order ASC, created_at DESC
            LIMIT 1
        ");
        if ($hero) {
            $title = trim((string) ($hero['title'] ?? ''));
            $subtitle = trim((string) ($hero['subtitle'] ?? ''));
            $description = trim((string) ($hero['description'] ?? ''));

            return [
                'title' => $title !== '' ? $title : $defaults['title'],
                'subtitle' => $subtitle,
                'description' => $description !== '' ? $description : ($subtitle !== '' ? $subtitle : $defaults['description']),
            ];
        }
    } catch (Exception $e) {
        // hero_images table may not exist yet
    }

    return $defaults;
}

/**
 * Check if current page should have hero section
 * @param string $page Current page identifier
 * @return bool True if page should have hero
 */
function shouldHaveHero($page = '') {
    $heroPages = ['home', 'index', ''];
    return in_array($page, $heroPages);
}

/**
 * Render hero section HTML
 * @param array $heroData Hero content data
 * @return string Hero section HTML
 */
function renderHeroSection($heroData) {
    ob_start();
    ?>
    <!-- Dynamic Hero Section -->
    <section class="hero-one hero-dynamic" style="background-image: url('<?php echo BASE_URL . $heroData['image_path']; ?>'); background-color: #007bff;">
        <div class="hero-overlay"></div>
        <div class="container">
            <div class="hero-one__content">
                <?php if ($heroData['subtitle']): ?>
                    <h5 class="hero-one__sub-title sub-title"><?php echo htmlspecialchars($heroData['subtitle']); ?></h5>
                <?php endif; ?>
                
                <?php if ($heroData['title']): ?>
                    <h2 class="hero-one__title title"><?php echo htmlspecialchars($heroData['title']); ?></h2>
                <?php endif; ?>
                
                <?php if ($heroData['description']): ?>
                    <p class="hero-one__text sub-title"><?php echo htmlspecialchars($heroData['description']); ?></p>
                <?php endif; ?>
                
                <div class="hero-one__buttons mt-4">
                    <a href="<?php echo navUrl('tours'); ?>" class="travhub-btn me-3">
                        <span>Explore Tours</span>
                    </a>
                    <a href="<?php echo navUrl('destinations'); ?>" class="travhub-btn travhub-btn--outline">
                        <span>View Destinations</span>
                    </a>
                </div>
            </div>
        </div>
    </section>
    <?php
    return ob_get_clean();
}

/**
 * Easy function to add hero to any page
 * Call this function after header to add hero section
 */
function displayHero() {
    $heroData = getHeroContent();
    echo renderHeroSection($heroData);
}
?>

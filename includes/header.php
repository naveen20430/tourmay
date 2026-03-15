<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php 
    if (isset($page_title)) {
        echo $page_title;
    } else {
        $site_name = function_exists('getSetting') ? getSetting('site_name') : 'TravHub';
        $site_tagline = function_exists('getSetting') ? getSetting('site_tagline') : 'Travel & Tour Booking Agency';
        echo $site_name . ($site_tagline ? ' - ' . $site_tagline : ' || Travel & Tour Booking Agency');
    }
  ?></title>
  
  <!-- SEO Meta Tags -->
  <meta name="description" content="<?php echo function_exists('getSetting') ? getSetting('site_description') : 'Amazing travel website for tour booking and adventures'; ?>" />
  <meta name="keywords" content="<?php echo function_exists('getSetting') ? getSetting('meta_keywords') : 'travel, tours, booking, adventure, vacation'; ?>" />
  <meta name="author" content="<?php echo function_exists('getSetting') ? getSetting('site_name') : 'TravHub'; ?>" />
  
  <!-- Open Graph Meta Tags -->
  <meta property="og:title" content="<?php echo isset($page_title) ? $page_title : (function_exists('getSetting') ? getSetting('site_name') . ' - ' . getSetting('site_tagline') : 'TravHub - Travel Agency'); ?>" />
  <meta property="og:description" content="<?php echo function_exists('getSetting') ? getSetting('site_description') : 'Amazing travel website for tour booking and adventures'; ?>" />
  <meta property="og:type" content="website" />
  <meta property="og:url" content="<?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']; ?>" />
  
  <!-- favicons Icons -->
  <link rel="apple-touch-icon" sizes="180x180" href="assets/images/favicons/apple-touch-icon.png">
  <link rel="icon" type="image/png" sizes="32x32" href="assets/images/favicons/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="assets/images/favicons/favicon-16x16.png">
  <link rel="manifest" href="assets/images/favicons/site.webmanifest">

  
  <!-- fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Caveat:wght@400..700&family=Geologica:wght@100..900&family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&display=swap" rel="stylesheet">

  <!-- COMPRESSED STYLES - All CSS combined into one file -->
  <?php echo cssWithCache('assets/compressed/all-styles.min.css'); ?>
  
  <!-- Flaticon CSS for icons -->
  <?php echo cssWithCache('assets/vendors/travhub-icons/style.css'); ?>
  
  <!-- Layout Optimization CSS -->
  <?php echo cssWithCache('assets/css/layout-optimization.css'); ?>
  
  <!-- Spacing Utilities -->
  <?php echo cssWithCache('assets/css/utilities/spacing.css'); ?>
  
  <!-- Design Improvements CSS -->
  <?php echo cssWithCache('assets/css/design-improvements.css'); ?>
  
  <!-- Global Theme CSS (Applied to all pages) -->
  <?php echo cssWithCache('assets/css/global-theme.css'); ?>
  
  <!-- Responsive Improvements CSS -->
  <?php echo cssWithCache('assets/css/responsive-improvements.css'); ?>
  
  <!-- Spacing Fixes CSS (All pages & sections) -->
  <?php echo cssWithCache('assets/css/spacing-fixes.css'); ?>
  
  <!-- Search Section Responsive Fixes -->
  <?php echo cssWithCache('assets/css/search-section-responsive.css'); ?>
  
  <!-- Footer Layout Fix CSS -->
  <?php echo cssWithCache('assets/css/footer-layout-fix.css'); ?>
  
  <!-- Banner Height Fix CSS -->
  <?php echo cssWithCache('assets/css/banner-height-fix.css'); ?>
  
  <!-- Contact Page Fix CSS -->
  <?php echo cssWithCache('assets/css/contact-page-fix.css'); ?>
  
  <!-- AJAX Search CSS -->
  <?php echo cssWithCache('assets/css/ajax-search.css'); ?>
  
  <!-- Flatpickr CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  
  <!-- Attractive Datepicker CSS -->
  <?php echo cssWithCache('assets/css/attractive-datepicker.css'); ?>
  
  <!-- Routing Fix for URL Navigation -->
  <script src="<?php echo BASE_URL; ?>assets/js/routing-fix.js"></script>
  
  <?php if (isset($extra_css) && $extra_css): echo $extra_css; endif; ?>

  <!-- Header spacing fix (loaded after page-specific CSS to keep header consistent across all pages) -->
  <?php echo cssWithCache('assets/css/header-spacing-fix.css'); ?>

  <!-- Booking form theme (scoped styles; safe to load globally) -->
  <?php echo cssWithCache('assets/css/booking-form-theme.css'); ?>

  <!-- Global UI overrides (buttons + icons consistent across all pages) -->
  <?php echo cssWithCache('assets/css/global-ui-overrides.css'); ?>
  
  <?php 
  // Google Analytics integration from settings
  if (function_exists('getSetting')) {
      $ga_id = getSetting('google_analytics_id');
      if ($ga_id && strlen(trim($ga_id)) > 0): ?>
  <!-- Google Analytics -->
  <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo htmlspecialchars($ga_id); ?>"></script>
  <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
    gtag('config', '<?php echo htmlspecialchars($ga_id); ?>');
  </script>
  <!-- End Google Analytics -->
      <?php endif;
  } ?>
</head>

<body class="custom-cursor">
  <div class="custom-cursor__cursor"></div>
  <div class="custom-cursor__cursor-two"></div>

  <!-- Preloader Icon 
  <div class="preloader">
	<div class="loaderInner">
		<div id="top" class="mask">
			<div class="plane"></div>
		</div>
		<div id="middle" class="mask">
			<div class="plane"></div>
		</div>
		<div id="bottom" class="mask">
			<div class="plane"></div>
		</div>
		<p>LOADING...</p>
	</div>
</div>
<!-- Preloader Icon -->
 
 <div class="page-wrapper">
	<div class="topbar-one">
	    <div class="container">
	        <div class="topbar-one__inner">
	            <ul class="list-unstyled topbar-one__info">
	                <li class="topbar-one__info__item">
	                    <i class="flaticon-pin-1 topbar-one__info__icon"></i>
	                    <?php echo function_exists('getSetting') ? getSetting('site_address') ?: '123 Travel Street, City, Country' : '123 Travel Street, City, Country'; ?>
	                </li>
	                <li class="topbar-one__info__item">
	                    <i class="flaticon-mail topbar-one__info__icon"></i>
	                    <a href="mailto:<?php echo function_exists('getSetting') ? getSetting('contact_email') ?: 'info@travhub.com' : 'info@travhub.com'; ?>">
	                        <?php echo function_exists('getSetting') ? getSetting('contact_email') ?: 'info@travhub.com' : 'info@travhub.com'; ?>
	                    </a>
	                </li>
	                <li class="topbar-one__info__item">
	                    <i class="flaticon-phone-call topbar-one__info__icon"></i>
	                    <a href="tel:<?php echo function_exists('getSetting') ? str_replace(' ', '', getSetting('contact_phone')) ?: '+1234567890' : '+1234567890'; ?>">
	                        <?php echo function_exists('getSetting') ? getSetting('contact_phone') ?: '+1 234 567 890' : '+1 234 567 890'; ?>
	                    </a>
	                </li>
	                <li class="topbar-one__info__item topbar-one__info__item--last">
	                    <i class="flaticon-three-o-clock-clock topbar-one__info__icon"></i>
	                    <?php echo function_exists('getSetting') ? getSetting('opening_hours') ?: '9:00am - 10:00pm' : '9:00am - 10:00pm'; ?>
	                </li>
	            </ul><!-- /.list-unstyled topbar-one__info -->
	        </div><!-- /.topbar-one__inner -->
	    </div><!-- /.container-fluid -->
	</div><!-- /.topbar-one -->
	<header class="main-header sticky-header sticky-header--normal">
	    <div class="container">
	        <div class="main-header__inner">
            <div class="main-header__logo">
                <a href="<?php echo navUrl('home'); ?>">
                    <img src="<?php echo BASE_URL; ?>assets/images/logo.png" alt="<?php echo function_exists('getSetting') ? getSetting('site_name') ?: 'TravHub' : 'TravHub'; ?>" width="80">
	                </a>
	            </div><!-- /.main-header__logo -->
<nav class="main-header__nav main-menu">
    <ul class="main-menu__list">
        <li <?php echo (isset($current_page) && $current_page == 'home') ? 'class="current"' : ''; ?>>
            <a href="<?php echo navUrl('home'); ?>">Home</a>
        </li>
        <li <?php echo (isset($current_page) && $current_page == 'tours') ? 'class="current"' : ''; ?>>
            <a href="<?php echo navUrl('tours'); ?>">Tours</a>
        </li>
        <li <?php echo (isset($current_page) && $current_page == 'blog') ? 'class="current"' : ''; ?>>
            <a href="<?php echo navUrl('blog'); ?>">Blog</a>
        </li>
        <li <?php echo (isset($current_page) && $current_page == 'contact') ? 'class="current"' : ''; ?>>
            <a href="<?php echo navUrl('contact'); ?>">Contact</a>
        </li>
        <?php if (isset($_SESSION['user_id'])): ?>
            <li class="dropdown">
                <a href="#">Account</a>
                <ul>
                    <li><a href="<?php echo BASE_URL; ?>user-dashboard.php">Dashboard</a></li>
                    <li><a href="<?php echo bookingUrl(); ?>">My Bookings</a></li>
                    <li><a href="<?php echo BASE_URL; ?>logout.php">Logout</a></li>
                </ul>
            </li>
        <?php else: ?>
            <li <?php echo (isset($current_page) && $current_page == 'login') ? 'class="current"' : ''; ?>>
                <a href="<?php echo navUrl('login'); ?>">Login</a>
            </li>
        <?php endif; ?>
    </ul>
</nav><!-- /.main-header__nav -->

	            <div class="main-header__right">
	                <div class="mobile-nav__btn mobile-nav__toggler">
	                    <span></span>
	                    <span></span>
	                    <span></span>
	                </div><!-- /.mobile-nav__toggler -->
				
	                <div class="main-header__btn">
	                    
	                </div>
	            </div><!-- /.main-header__right -->
	        </div><!-- /.main-header__inner -->
	    </div><!-- /.container-fluid -->
	</header><!-- /.main-header -->

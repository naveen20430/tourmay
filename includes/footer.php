	</div><!-- /.page-wrapper -->

	<!-- Main Footer -->
	<footer class="main-footer">
        <div class="container">
            <div class="row py-5">
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="main-footer__about">
                        <a href="<?php echo navUrl('home'); ?>" class="main-footer__logo mb-3 d-block">
                            <img src="<?php echo BASE_URL; ?>assets/images/logo.png" alt="<?php echo function_exists('getSetting') ? getSetting('site_name') ?: 'TravHub' : 'TravHub'; ?>" width="100">
                        </a>
                        <p class="main-footer__text">
                            <?php echo function_exists('getSetting') ? getSetting('site_description', 'Discover amazing destinations and create unforgettable memories with our expertly crafted travel experiences.') : 'Discover amazing destinations and create unforgettable memories with our expertly crafted travel experiences.'; ?>
                        </p>
                        <div class="main-footer__social">
                            <?php if (function_exists('getSetting')): 
                                $facebook = getSetting('facebook_url');
                                $twitter = getSetting('twitter_url');
                                $instagram = getSetting('instagram_url');
                                $linkedin = getSetting('linkedin_url');
                                $youtube = getSetting('youtube_url');
                            ?>
                                <?php if ($facebook): ?><a href="<?php echo $facebook; ?>" target="_blank" rel="noopener"><i class="fab fa-facebook-f"></i></a><?php endif; ?>
                                <?php if ($twitter): ?><a href="<?php echo $twitter; ?>" target="_blank" rel="noopener"><i class="fab fa-twitter"></i></a><?php endif; ?>
                                <?php if ($instagram): ?><a href="<?php echo $instagram; ?>" target="_blank" rel="noopener"><i class="fab fa-instagram"></i></a><?php endif; ?>
                                <?php if ($linkedin): ?><a href="<?php echo $linkedin; ?>" target="_blank" rel="noopener"><i class="fab fa-linkedin-in"></i></a><?php endif; ?>
                                <?php if ($youtube): ?><a href="<?php echo $youtube; ?>" target="_blank" rel="noopener"><i class="fab fa-youtube"></i></a><?php endif; ?>
                            <?php else: ?>
                                <a href="#"><i class="fab fa-facebook-f"></i></a>
                                <a href="#"><i class="fab fa-twitter"></i></a>
                                <a href="#"><i class="fab fa-instagram"></i></a>
                                <a href="#"><i class="fab fa-linkedin-in"></i></a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-2 col-md-6 mb-4">
                    <div class="main-footer__navmenu">
                        <h3 class="main-footer__title">Quick Links</h3>
                        <ul class="list-unstyled main-footer__menu">
                            <li><a href="<?php echo navUrl('home'); ?>">Home</a></li>
                            <li><a href="<?php echo navUrl('tours'); ?>">Tours</a></li>
                            <li><a href="<?php echo navUrl('about-us'); ?>">About Us</a></li>
                            <li><a href="<?php echo navUrl('contact'); ?>">Contact Us</a></li>
                        </ul>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="main-footer__navmenu">
                        <h3 class="main-footer__title">Services</h3>
                        <ul class="list-unstyled main-footer__menu">
                            <li><a href="<?php echo navUrl('tours'); ?>">Tour Packages</a></li>
                            <li><a href="<?php echo navUrl('destinations'); ?>">Destinations</a></li>
                            <li><a href="<?php echo navUrl('about-us'); ?>">About Us</a></li>
                            <li><a href="<?php echo navUrl('contact'); ?>">Customer Support</a></li>
                        </ul>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="main-footer__contact">
                        <h3 class="main-footer__title">Contact Info</h3>
                        <ul class="list-unstyled main-footer__contact-list">
                            <li>
                                <i class="fas fa-map-marker-alt"></i>
                                <?php echo function_exists('getSetting') ? getSetting('site_address', '123 Travel Street, City, Country') : '123 Travel Street, City, Country'; ?>
                            </li>
                            <li>
                                <i class="fas fa-phone"></i>
                                <a href="tel:<?php echo function_exists('getSetting') ? getSetting('contact_phone', '+1-234-567-8900') : '+1-234-567-8900'; ?>">
                                    <?php echo function_exists('getSetting') ? getSetting('contact_phone', '+1-234-567-8900') : '+1-234-567-8900'; ?>
                                </a>
                            </li>
                            <li>
                                <i class="fas fa-envelope"></i>
                                <a href="mailto:<?php echo function_exists('getSetting') ? getSetting('contact_email', 'info@travhub.com') : 'info@travhub.com'; ?>">
                                    <?php echo function_exists('getSetting') ? getSetting('contact_email', 'info@travhub.com') : 'info@travhub.com'; ?>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="main-footer__bottom pt-4 border-top">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <p class="main-footer__copyright mb-0">
                            &copy; <?php echo date('Y'); ?> <?php echo function_exists('getSetting') ? getSetting('site_name', 'TravHub') : 'TravHub'; ?>. All Rights Reserved.
                        </p>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <ul class="list-inline main-footer__bottom-menu mb-0">
                            <li class="list-inline-item"><a href="<?php echo navUrl('privacy-policy'); ?>">Privacy Policy</a></li>
                            <li class="list-inline-item"><a href="<?php echo navUrl('terms-conditions'); ?>">Terms of Service</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </footer>

<div class="mobile-nav__wrapper">
	<div class="mobile-nav__overlay mobile-nav__toggler"></div>
	<!-- /.mobile-nav__overlay -->
	<div class="mobile-nav__content">
		<span class="mobile-nav__close mobile-nav__toggler"><i class="fa fa-times"></i></span>
		<div class="logo-box">
		<a href="<?php echo navUrl('home'); ?>" aria-label="logo image"><img src="<?php echo BASE_URL; ?>assets/images/logo.png" width="120" alt="<?php echo function_exists('getSetting') ? getSetting('site_name') ?: 'TravHub' : 'TravHub'; ?>" /></a>
		</div>
		<!-- /.logo-box -->
		<div class="mobile-nav__container"></div>
		<!-- /.mobile-nav__container -->

		<ul class="mobile-nav__contact list-unstyled">
			<li>
				<i class="fa fa-envelope"></i>
				<a href="mailto:<?php echo function_exists('getSetting') ? getSetting('contact_email', 'info@travhub.com') : 'info@travhub.com'; ?>">
					<?php echo function_exists('getSetting') ? getSetting('contact_email', 'info@travhub.com') : 'info@travhub.com'; ?>
				</a>
			</li>
			<li>
				<i class="fa fa-phone-alt"></i>
				<a href="tel:<?php echo function_exists('getSetting') ? getSetting('contact_phone', '+1-234-567-8900') : '+1-234-567-8900'; ?>">
					<?php echo function_exists('getSetting') ? getSetting('contact_phone', '+1-234-567-8900') : '+1-234-567-8900'; ?>
				</a>
			</li>
		</ul><!-- /.mobile-nav__contact -->
		<div class="mobile-nav__social">
			<?php if (function_exists('getSetting')): 
                $facebook = getSetting('facebook_url');
                $twitter = getSetting('twitter_url');
                $instagram = getSetting('instagram_url');
                $linkedin = getSetting('linkedin_url');
                $youtube = getSetting('youtube_url');
            ?>
                <?php if ($facebook): ?><a href="<?php echo $facebook; ?>" target="_blank" rel="noopener"><i class="fab fa-facebook-f" aria-hidden="true"></i><span class="sr-only">Facebook</span></a><?php endif; ?>
                <?php if ($twitter): ?><a href="<?php echo $twitter; ?>" target="_blank" rel="noopener"><i class="fab fa-twitter" aria-hidden="true"></i><span class="sr-only">Twitter</span></a><?php endif; ?>
                <?php if ($instagram): ?><a href="<?php echo $instagram; ?>" target="_blank" rel="noopener"><i class="fab fa-instagram" aria-hidden="true"></i><span class="sr-only">Instagram</span></a><?php endif; ?>
                <?php if ($linkedin): ?><a href="<?php echo $linkedin; ?>" target="_blank" rel="noopener"><i class="fab fa-linkedin-in" aria-hidden="true"></i><span class="sr-only">Linkedin</span></a><?php endif; ?>
                <?php if ($youtube): ?><a href="<?php echo $youtube; ?>" target="_blank" rel="noopener"><i class="fab fa-youtube" aria-hidden="true"></i><span class="sr-only">YouTube</span></a><?php endif; ?>
            <?php else: ?>
                <a href="#"><i class="fab fa-facebook-f" aria-hidden="true"></i><span class="sr-only">Facebook</span></a>
                <a href="#"><i class="fab fa-twitter" aria-hidden="true"></i><span class="sr-only">Twitter</span></a>
                <a href="#"><i class="fab fa-linkedin-in" aria-hidden="true"></i><span class="sr-only">Linkedin</span></a>
                <a href="#"><i class="fab fa-instagram" aria-hidden="true"></i><span class="sr-only">Instagram</span></a>
            <?php endif; ?>
		</div><!-- /.mobile-nav__social -->
	</div>
	<!-- /.mobile-nav__content -->
</div>
<!-- /.mobile-nav__wrapper -->

<div class="search-popup">
	<div class="search-popup__overlay search-toggler"></div>
	<!-- /.search-popup__overlay -->
	<div class="search-popup__content">
		<div class="search-popup__header">
			<h3 class="search-popup__title">Search Tours & Destinations</h3>
			<button class="search-popup__close search-toggler" aria-label="Close search">
				<i class="fas fa-times"></i>
			</button>
		</div>
		
		<form role="search" method="get" class="search-popup__form" id="ajaxSearchForm">
			<div class="search-input-wrapper">
				<input type="text" id="ajaxSearchInput" placeholder="Search tours and destinations..." autocomplete="off" />
				<button type="submit" aria-label="search submit" class="search-submit-btn">
					<span>
						<i class="flaticon-search"></i>
						<i class="fas fa-search" style="display: none;"></i>
					</span>
				</button>
				<div class="search-loading" id="searchLoading" style="display: none;">
					<i class="fas fa-spinner fa-spin"></i>
				</div>
			</div>
			
			<!-- Search Filters -->
			<div class="search-filters">
				<button type="button" class="search-filter-btn active" data-type="all">
					<i class="fas fa-search"></i> All
				</button>
				<button type="button" class="search-filter-btn" data-type="tours">
					<i class="fas fa-map-marked-alt"></i> Tours
				</button>
				<button type="button" class="search-filter-btn" data-type="destinations">
					<i class="fas fa-globe"></i> Destinations
				</button>
				<button type="button" class="search-filter-btn" data-type="blog" style="display:none;" aria-hidden="true">
					<i class="fas fa-blog"></i> Blog
				</button>
			</div>
		</form>
		
		<!-- Search Results -->
		<div class="search-results" id="searchResults">
			<div class="search-welcome">
				<i class="fas fa-search fa-3x mb-3"></i>
				<h4>Start typing to search</h4>
				<p>Find tours and destinations instantly</p>
			</div>
		</div>
		
		<!-- Quick Actions -->
		<div class="search-quick-actions">
			<h5>Quick Links</h5>
			<div class="quick-action-buttons">
				<a href="<?php echo BASE_URL; ?>tours.php" class="quick-action-btn">
					<i class="fas fa-map-marked-alt"></i> Browse All Tours
				</a>
				<a href="<?php echo BASE_URL; ?>destinations.php" class="quick-action-btn">
					<i class="fas fa-globe"></i> Explore Destinations
				</a>
				<a href="<?php echo navUrl('about-us'); ?>" class="quick-action-btn">
					<i class="fas fa-info-circle"></i> About Us
				</a>
			</div>
		</div>
	</div>
	<!-- /.search-popup__content -->
</div>
<!-- /.search-popup -->

<a href="#" class="scroll-to-top">
    <svg class="scroll-to-top__circle" width="100%" height="100%" viewBox="-1 -1 102 102">
        <path d="M50,1 a49,49 0 0,1 0,98 a49,49 0 0,1 0,-98" />
    </svg>
</a>

<!-- COMPRESSED SCRIPTS - All JavaScript combined into one file -->
<script src="<?php echo BASE_URL; ?>assets/compressed/all-scripts.min.js?v=<?php echo getCacheVersion('assets/compressed/all-scripts.min.js'); ?>"></script>

<!-- Flatpickr JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<!-- Design Enhancements JavaScript -->
<script src="<?php echo BASE_URL; ?>assets/js/design-enhancements.js"></script>

<!-- Attractive Datepicker JavaScript -->
<script src="<?php echo BASE_URL; ?>assets/js/attractive-datepicker.js"></script>

<!-- Flaticon Fallback Script -->
<script>
// Check if flaticon font loaded and fallback to FontAwesome if not
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
        // Check if flaticon-search icon is rendering properly
        var testElement = document.createElement('i');
        testElement.className = 'flaticon-search';
        testElement.style.position = 'absolute';
        testElement.style.visibility = 'hidden';
        testElement.style.fontSize = '16px';
        testElement.style.lineHeight = '1';
        document.body.appendChild(testElement);
        
        var width = testElement.offsetWidth;
        document.body.removeChild(testElement);
        
        // If flaticon didn't load (width would be 0 or very small), use FontAwesome fallback
        if (width < 8) {
            console.log('Flaticon not loaded, using FontAwesome fallback');
            var flaticonElements = document.querySelectorAll('.flaticon-search');
            flaticonElements.forEach(function(element) {
                element.className = element.className.replace('flaticon-search', 'fas fa-search');
            });
            
            // Show FontAwesome fallback icons that were hidden
            var fallbackIcons = document.querySelectorAll('.fas.fa-search[style*="display: none"]');
            fallbackIcons.forEach(function(icon) {
                icon.style.display = 'inline-block';
                // Hide the flaticon element
                var flaticonSibling = icon.previousElementSibling;
                if (flaticonSibling && flaticonSibling.classList.contains('flaticon-search')) {
                    flaticonSibling.style.display = 'none';
                }
            });
        }
    }, 100); // Small delay to ensure fonts are loaded
});
</script>

<?php 
// Initialize tour slider and carousel if they exist on the page
if (function_exists('initTourSliderJS')) {
    initTourSliderJS();
}
if (function_exists('initTourCarouselJS')) {
    initTourCarouselJS();
}

// Include page-specific JavaScript
if (isset($extra_js) && $extra_js): echo $extra_js; endif;
?>

</body>
</html>

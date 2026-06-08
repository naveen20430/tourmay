/**
 * Design Enhancements & Interactive Animations
 * Modern JavaScript improvements for better user experience
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // Smooth scroll reveal animations
    function initScrollReveal() {
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('revealed');
                }
            });
        }, observerOptions);

        // Add scroll-reveal class to cards and sections
        const revealElements = document.querySelectorAll('.card, .section-space > .container > .row > div, .tour-content > div');
        revealElements.forEach((el, index) => {
            el.classList.add('scroll-reveal');
            el.style.transitionDelay = `${index * 0.1}s`;
            observer.observe(el);
        });
    }

    // Enhanced button interactions
    function enhanceButtons() {
        const buttons = document.querySelectorAll('.travhub-btn, .btn');
        
        buttons.forEach(button => {
            // Add ripple effect
            button.addEventListener('click', function(e) {
                const ripple = document.createElement('span');
                const rect = this.getBoundingClientRect();
                const size = Math.max(rect.width, rect.height);
                const x = e.clientX - rect.left - size / 2;
                const y = e.clientY - rect.top - size / 2;
                
                ripple.style.width = ripple.style.height = size + 'px';
                ripple.style.left = x + 'px';
                ripple.style.top = y + 'px';
                ripple.classList.add('ripple');
                
                this.appendChild(ripple);
                
                setTimeout(() => ripple.remove(), 600);
            });
            
            // Add hover sound effect (optional)
            button.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-2px) scale(1.02)';
            });
            
            button.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });
    }

    // Smooth scrolling for navigation links
    function initSmoothScroll() {
        const navLinks = document.querySelectorAll('a[href^="#"]');
        
        navLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const targetId = this.getAttribute('href');
                const targetElement = document.querySelector(targetId);
                
                if (targetElement) {
                    targetElement.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    }

    // Enhanced card hover effects
    function enhanceCards() {
        const cards = document.querySelectorAll('.card');
        
        cards.forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-10px) rotateX(5deg)';
                this.style.transition = 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)';
                
                // Add glow effect
                this.style.boxShadow = '0 20px 40px rgba(102, 126, 234, 0.3)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) rotateX(0)';
                this.style.boxShadow = '0 10px 40px rgba(0, 0, 0, 0.1)';
            });
        });
    }

    // Loading skeleton effect for images
    function initImageLoading() {
        const images = document.querySelectorAll('img');
        
        images.forEach(img => {
            if (!img.complete) {
                img.classList.add('loading-skeleton');
                
                img.addEventListener('load', function() {
                    this.classList.remove('loading-skeleton');
                    this.classList.add('fade-in-up');
                });
                
                img.addEventListener('error', function() {
                    this.classList.remove('loading-skeleton');
                    this.style.background = '#f0f0f0';
                });
            }
        });
    }

    // Enhanced form interactions
    function enhanceForms() {
        const formGroups = document.querySelectorAll('.form-group, .mb-3');
        
        formGroups.forEach(group => {
            const input = group.querySelector('input, select, textarea');
            const label = group.querySelector('label');
            
            if (input && label) {
                // Floating label effect
                input.addEventListener('focus', function() {
                    label.style.transform = 'translateY(-25px) scale(0.8)';
                    label.style.color = '#667eea';
                });
                
                input.addEventListener('blur', function() {
                    if (!this.value) {
                        label.style.transform = 'translateY(0) scale(1)';
                        label.style.color = '#495057';
                    }
                });
                
                // Check if input has value on load
                if (input.value) {
                    label.style.transform = 'translateY(-25px) scale(0.8)';
                    label.style.color = '#667eea';
                }
            }
        });
        
        // Form validation feedback
        const inputs = document.querySelectorAll('input[required], select[required], textarea[required]');
        
        inputs.forEach(input => {
            input.addEventListener('blur', function() {
                if (this.validity.valid) {
                    this.classList.add('is-valid');
                    this.classList.remove('is-invalid');
                } else {
                    this.classList.add('is-invalid');
                    this.classList.remove('is-valid');
                }
            });
        });
    }

    // Parallax effect for hero sections
    function initParallax() {
        const heroSections = document.querySelectorAll('.tour-hero, .hero-section');
        
        window.addEventListener('scroll', function() {
            const scrolled = window.pageYOffset;
            
            heroSections.forEach(hero => {
                const rate = scrolled * -0.5;
                hero.style.transform = `translateY(${rate}px)`;
            });
        });
    }

    // Price counter animation
    function animateCounters() {
        const counters = document.querySelectorAll('.price-display, .counter');
        
        const counterObserver = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const counter = entry.target;
                    const target = parseInt(counter.textContent.replace(/[^\d]/g, ''));
                    
                    if (target > 0) {
                        animateValue(counter, 0, target, 1000);
                    }
                    
                    counterObserver.unobserve(counter);
                }
            });
        });
        
        counters.forEach(counter => {
            counterObserver.observe(counter);
        });
    }

    function animateValue(element, start, end, duration) {
        const startTimestamp = performance.now();
        const prefix = element.textContent.match(/[^\d]/g)?.join('') || '';
        
        const step = (timestamp) => {
            const progress = Math.min((timestamp - startTimestamp) / duration, 1);
            const current = Math.floor(progress * (end - start) + start);
            element.textContent = prefix + current.toLocaleString();
            
            if (progress < 1) {
                requestAnimationFrame(step);
            }
        };
        
        requestAnimationFrame(step);
    }

    // Search enhancement with AJAX functionality
    function enhanceSearch() {
        const searchToggler = document.querySelector('.search-toggler');
        const searchPopup = document.querySelector('.search-popup');
        
        if (searchToggler && searchPopup) {
            searchToggler.addEventListener('click', function(e) {
                e.preventDefault();
                searchPopup.classList.add('search-popup--visible');
                
                // Focus on search input
                setTimeout(() => {
                    const searchInput = searchPopup.querySelector('input');
                    if (searchInput) searchInput.focus();
                }, 300);
            });
            
            // Initialize AJAX search
            initAjaxSearch(searchPopup);
        }
    }
    
    // AJAX Search Implementation
    function initAjaxSearch(searchPopup) {
        const searchInput = searchPopup.querySelector('#ajaxSearchInput');
        const filterButtons = searchPopup.querySelectorAll('.search-filter-btn');
        const loadingSpinner = searchPopup.querySelector('#searchLoading');
        const resultsContainer = searchPopup.querySelector('#searchResults');
        const closeBtn = searchPopup.querySelector('.search-popup__close');
        
        let searchTimeout;
        let currentFilter = 'all';
        
        // Close search popup
        if (closeBtn) {
            closeBtn.addEventListener('click', function() {
                searchPopup.classList.remove('search-popup--visible');
                searchInput.value = '';
                resultsContainer.innerHTML = '<div class="search-welcome"><i class="fas fa-search fa-3x mb-3"></i><h4>Start typing to search</h4><p>Find tours, destinations, and blog posts instantly</p></div>';
            });
        }
        
        // Click outside to close
        searchPopup.addEventListener('click', function(e) {
            if (e.target === searchPopup || e.target.classList.contains('search-popup__overlay')) {
                searchPopup.classList.remove('search-popup--visible');
                searchInput.value = '';
                resultsContainer.innerHTML = '<div class="search-welcome"><i class="fas fa-search fa-3x mb-3"></i><h4>Start typing to search</h4><p>Find tours, destinations, and blog posts instantly</p></div>';
            }
        });
        
        // Filter button functionality
        filterButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                // Update active filter
                filterButtons.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                currentFilter = this.dataset.type;
                
                // Re-search with new filter if there's a query
                if (searchInput.value.trim()) {
                    performSearch(searchInput.value.trim(), currentFilter);
                }
            });
        });
        
        // Search input functionality
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                const query = this.value.trim();
                
                // Clear previous timeout
                clearTimeout(searchTimeout);
                
                if (query.length < 2) {
                    resultsContainer.innerHTML = '';
                    return;
                }
                
                // Debounce search requests
                searchTimeout = setTimeout(() => {
                    performSearch(query, currentFilter);
                }, 300);
            });
            
            // Handle Enter key
            searchInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const query = this.value.trim();
                    if (query.length >= 2) {
                        performSearch(query, currentFilter);
                    }
                }
            });
        }
        
        // Perform AJAX search
        function performSearch(query, filter) {
            if (!query || query.length < 2) return;
            
            // Show loading state
            loadingSpinner.style.display = 'block';
            resultsContainer.innerHTML = '';
            
            // Build API URL
            const apiUrl = `ajax/search.php?q=${encodeURIComponent(query)}&type=${encodeURIComponent(filter)}`;
            
            // Make AJAX request
            fetch(apiUrl)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Search response:', data); // Debug log
                    loadingSpinner.style.display = 'none';
                    renderSearchResults(data, query);
                })
                .catch(error => {
                    console.error('Search error:', error);
                    loadingSpinner.style.display = 'none';
                    resultsContainer.innerHTML = `
                        <div class="no-results">
                            <i class="fas fa-exclamation-triangle"></i>
                            <p>Search temporarily unavailable. Please try again later.</p>
                        </div>
                    `;
                });
        }
        
        // Render search results
        function renderSearchResults(data, query) {
            let html = '';
            
            // Handle both old and new data structures
            const results = data.results || data;
            const tours = results.tours || [];
            const destinations = results.destinations || [];
            const blogPosts = results.blog_posts || results.blog || [];
            
            if (!tours.length && !destinations.length && !blogPosts.length) {
                html = `
                    <div class="no-results">
                        <i class="fas fa-search"></i>
                        <p>No results found for "${escapeHtml(query)}"</p>
                        <small>Try different keywords or browse our content below</small>
                    </div>
                `;
            } else {
                // Tours results
                if (tours && tours.length > 0) {
                    html += '<div class="results-section">';
                    html += `<h4><i class="fas fa-map-marked-alt"></i> Tours (${tours.length})</h4>`;
                    tours.forEach(tour => {
                        const price = tour.discount_price ? tour.discount_price : tour.price;
                        const originalPrice = tour.discount_price ? tour.price : null;
                        const priceFormatted = tour.price_formatted || `₹${parseInt(price || 0).toLocaleString('en-IN')}`;
                        const originalPriceFormatted = originalPrice ? `₹${parseInt(originalPrice).toLocaleString('en-IN')}` : null;
                        
                        html += `
                            <div class="result-item">
                                <a href="tour-details.php?id=${tour.id}">
                                    <div class="result-content">
                                        <h5>${highlightQuery(escapeHtml(tour.title), query)}</h5>
                                        <p class="result-meta">
                                            <span><i class="fas fa-map-marker-alt"></i> ${escapeHtml(tour.location || tour.destination_name || 'Location TBD')}</span>
                                            <span class="price-info">
                                                <i class="fas fa-tag"></i> 
                                                ${originalPriceFormatted ? `<span class="original-price" style="text-decoration: line-through; color: #888;">${originalPriceFormatted}</span> ` : ''}
                                                <span class="current-price" style="font-weight: bold; color: #28a745;">${priceFormatted}</span>
                                                ${tour.duration_text ? ` • ${tour.duration_text}` : ''}
                                            </span>
                                        </p>
                                        <p class="result-description">${truncateText(escapeHtml(tour.short_description || tour.description || 'Explore this amazing destination with our carefully crafted tour package.'), 120)}</p>
                                    </div>
                                </a>
                            </div>
                        `;
                    });
                    html += '</div>';
                }
                
                // Destinations results
                if (destinations && destinations.length > 0) {
                    html += '<div class="results-section">';
                    html += `<h4><i class="fas fa-globe-americas"></i> Destinations (${destinations.length})</h4>`;
                    destinations.forEach(destination => {
                        const toursCount = destination.tours_count || 0;
                        const locationText = destination.location || (destination.city ? `${destination.city}, ${destination.country}` : destination.country || 'Amazing Destination');
                        
                        html += `
                            <div class="result-item">
                                <a href="destination-details.php?id=${destination.id}">
                                    <div class="result-content">
                                        <h5>${highlightQuery(escapeHtml(destination.name), query)}</h5>
                                        <p class="result-meta">
                                            <span><i class="fas fa-map-marker-alt"></i> ${escapeHtml(locationText)}</span>
                                            <span><i class="fas fa-route"></i> ${toursCount} tour${toursCount === 1 ? '' : 's'} available</span>
                                        </p>
                                        <p class="result-description">${truncateText(escapeHtml(destination.short_description || destination.description || 'Discover the beauty and culture of this incredible destination with our expert-guided tours.'), 120)}</p>
                                    </div>
                                </a>
                            </div>
                        `;
                    });
                    html += '</div>';
                }
                
                // Blog posts results
                if (blogPosts && blogPosts.length > 0) {
                    html += '<div class="results-section">';
                    html += `<h4><i class="fas fa-blog"></i> Blog Posts (${blogPosts.length})</h4>`;
                    blogPosts.forEach(post => {
                        const publishDate = post.published_at || post.created_at;
                        const authorName = post.author_name || 'Travel Expert';
                        const categoryName = post.category_name || 'Travel';
                        
                        html += `
                            <div class="result-item">
                                <a href="blog-post.php?id=${post.id}">
                                    <div class="result-content">
                                        <h5>${highlightQuery(escapeHtml(post.title), query)}</h5>
                                        <p class="result-meta">
                                            <span><i class="fas fa-calendar"></i> ${formatDate(publishDate)}</span>
                                            <span><i class="fas fa-user"></i> by ${escapeHtml(authorName)}</span>
                                            <span><i class="fas fa-folder"></i> ${escapeHtml(categoryName)}</span>
                                        </p>
                                        <p class="result-description">${truncateText(escapeHtml(post.excerpt || post.content || 'Read this interesting article about travel tips, destinations, and experiences from our travel experts.'), 120)}</p>
                                    </div>
                                </a>
                            </div>
                        `;
                    });
                    html += '</div>';
                }
            }
            
            resultsContainer.innerHTML = html;
        }
        
        // Helper functions
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function highlightQuery(text, query) {
            const regex = new RegExp(`(${escapeRegex(query)})`, 'gi');
            return text.replace(regex, '<mark>$1</mark>');
        }
        
        function escapeRegex(string) {
            return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        }
        
        function truncateText(text, maxLength) {
            if (text.length <= maxLength) return text;
            return text.substring(0, maxLength).trim() + '...';
        }
        
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
        }
    }

    // Progress bar for scroll
    function initScrollProgress() {
        const progressBar = document.createElement('div');
        progressBar.id = 'scroll-progress';
        progressBar.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 0%;
            height: 3px;
            background: #1bbc9b;
            z-index: 9999;
            transition: width 0.1s ease-out;
        `;
        document.body.appendChild(progressBar);
        
        window.addEventListener('scroll', function() {
            const scrolled = (window.pageYOffset / (document.body.scrollHeight - window.innerHeight)) * 100;
            progressBar.style.width = scrolled + '%';
        });
    }

    // Theme toggle (if needed)
    function initThemeToggle() {
        const themeToggle = document.querySelector('.theme-toggle');
        
        if (themeToggle) {
            themeToggle.addEventListener('click', function() {
                document.body.classList.toggle('dark-theme');
                localStorage.setItem('theme', document.body.classList.contains('dark-theme') ? 'dark' : 'light');
            });
            
            // Load saved theme
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme === 'dark') {
                document.body.classList.add('dark-theme');
            }
        }
    }

    // Initialize all enhancements
    function init() {
        initScrollReveal();
        enhanceButtons();
        initSmoothScroll();
        enhanceCards();
        initImageLoading();
        enhanceForms();
        initParallax();
        animateCounters();
        enhanceSearch();
        initScrollProgress();
        initThemeToggle();
        
        // Add loaded class to body for CSS animations
        document.body.classList.add('page-loaded');
        
        console.log('🎨 Design enhancements loaded successfully!');
    }

    // Start initialization
    init();
});

// Add CSS for ripple effect
const rippleCSS = `
<style>
.ripple {
    position: absolute;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.6);
    transform: scale(0);
    animation: ripple-animation 0.6s linear;
    pointer-events: none;
}

@keyframes ripple-animation {
    to {
        transform: scale(2);
        opacity: 0;
    }
}

.page-loaded .scroll-reveal {
    opacity: 1;
    transform: translateY(0);
}

.search-popup--visible {
    opacity: 1 !important;
    visibility: visible !important;
    transform: scale(1) !important;
}
</style>
`;

// Inject ripple CSS
document.head.insertAdjacentHTML('beforeend', rippleCSS);
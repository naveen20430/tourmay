/**
 * URL Routing Handler
 * Fixes routing issues when URL changes but page doesn't update
 * Handles popstate events for browser back/forward navigation
 */

(function() {
    'use strict';

    // Track page load state
    let lastLoadedUrl = window.location.href;

    /**
     * Handle browser back/forward buttons
     */
    window.addEventListener('popstate', function(event) {
        // Reload the page with new URL state
        const currentUrl = window.location.href;
        if (currentUrl !== lastLoadedUrl) {
            window.location.href = currentUrl;
        }
    });

    /**
     * Intercept all internal links to ensure proper navigation
     */
    document.addEventListener('click', function(e) {
        const link = e.target.closest('a[href]');
        
        if (!link) return;

        const href = link.getAttribute('href');
        
        // Only handle internal links (not external or mailto)
        if (!href || href.startsWith('http') || href.startsWith('mailto:') || href.startsWith('tel:') || href.startsWith('#')) {
            return;
        }

        const currentHost = window.location.origin;
        const linkUrl = new URL(href, currentHost);

        // Check if it's an internal link
        if (linkUrl.origin === currentHost) {
            e.preventDefault();
            
            const path = linkUrl.pathname + linkUrl.search + linkUrl.hash;
            
            // Update history and navigate
            window.history.pushState({ url: path }, '', path);
            window.location.href = path;
            
            return false;
        }
    });

    /**
     * Monitor URL changes and reload if needed
     */
    let previousUrl = window.location.href;
    setInterval(function() {
        const currentUrl = window.location.href;
        if (currentUrl !== previousUrl && previousUrl !== lastLoadedUrl) {
            previousUrl = currentUrl;
            lastLoadedUrl = currentUrl;
            // URL changed, page will reload on next navigation
        }
    }, 500);

    /**
     * Ensure page reloads on direct URL input
     */
    window.addEventListener('beforeunload', function() {
        lastLoadedUrl = window.location.href;
    });

    /**
     * Fix for links that don't have proper href attributes
     */
    document.addEventListener('contextmenu', function(e) {
        const link = e.target.closest('a[href]');
        if (link) {
            const href = link.getAttribute('href');
            if (href && !href.startsWith('http')) {
                link.setAttribute('data-href', href);
            }
        }
    }, true);

})();

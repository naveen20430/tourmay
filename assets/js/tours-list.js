/**
 * Tours listing — slide sidebar for Description / Inclusion / Timings / Useful Info
 */
(function() {
    'use strict';

    const panelData = window.TOURS_PANEL_DATA || {};
    const sidebar = document.getElementById('tourInfoSidebar');
    const bodyEl = document.getElementById('tourInfoSidebarBody');
    const titleEl = document.getElementById('tourInfoSidebarTitle');
    const metaEl = document.getElementById('tourInfoSidebarMeta');
    const tabsNav = document.getElementById('tourInfoSidebarTabs');
    const viewLink = document.getElementById('tourInfoSidebarViewLink');
    const bookLink = document.getElementById('tourInfoSidebarBookLink');

    let activeTourId = null;
    let activeTab = 'description';

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str == null ? '' : String(str);
        return div.innerHTML;
    }

    function formatPrice(amount) {
        return '₹ ' + Number(amount).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function renderDescription(panel) {
        if (!panel.short && !panel.body) {
            return '<p class="tour-info-sidebar__empty">No description available for this tour.</p>';
        }
        let html = '';
        if (panel.short) {
            html += '<p class="lead">' + escapeHtml(panel.short) + '</p>';
        }
        if (panel.body) {
            html += '<div>' + escapeHtml(panel.body).replace(/\n/g, '<br>') + '</div>';
        }
        return html;
    }

    function renderInclusion(panel) {
        const inc = panel.inclusions || [];
        const exc = panel.exclusions || [];
        if (!inc.length && !exc.length) {
            return '<p class="tour-info-sidebar__empty">No inclusion details added yet.</p>';
        }
        let html = '';
        if (inc.length) {
            html += '<h4>What\'s Included</h4><ul class="tour-info-sidebar__list">';
            inc.forEach(function(item) {
                html += '<li><i class="fas fa-check"></i><span>' + escapeHtml(item) + '</span></li>';
            });
            html += '</ul>';
        }
        if (exc.length) {
            html += '<h4 style="margin-top:18px;">What\'s Not Included</h4><ul class="tour-info-sidebar__list">';
            exc.forEach(function(item) {
                html += '<li class="is-exclude"><i class="fas fa-times"></i><span>' + escapeHtml(item) + '</span></li>';
            });
            html += '</ul>';
        }
        return html;
    }

    function renderTimings(panel) {
        const days = panel.duration_days || 0;
        const nights = panel.duration_nights || 0;
        const itinerary = panel.itinerary || [];
        let html = '<div class="tour-info-sidebar__meta">';
        html += '<div><span>Duration</span><b>' + days + ' Days / ' + nights + ' Nights</b></div>';
        if (panel.availability) {
            html += '<div><span>Availability</span><b>' + escapeHtml(panel.availability) + '</b></div>';
        }
        html += '</div>';

        if (itinerary.length) {
            html += '<h4>Day-wise Schedule</h4>';
            itinerary.forEach(function(day) {
                html += '<div class="tour-info-sidebar__day">';
                html += '<strong>Day ' + escapeHtml(day.day) + ': ' + escapeHtml(day.title) + '</strong>';
                if (day.description) {
                    html += '<p style="margin:0;color:#6c757d;">' + escapeHtml(day.description).replace(/\n/g, '<br>') + '</p>';
                }
                html += '</div>';
            });
        } else if (!panel.availability && !days) {
            html += '<p class="tour-info-sidebar__empty">No timing details available.</p>';
        }
        return html;
    }

    function renderUseful(panel) {
        if (!panel.body) {
            return '<p class="tour-info-sidebar__empty">No useful information available for this destination.</p>';
        }
        let html = '';
        if (panel.destination) {
            html += '<p><strong>' + escapeHtml(panel.destination) + '</strong></p>';
        }
        html += '<div>' + escapeHtml(panel.body).replace(/\n/g, '<br>') + '</div>';
        return html;
    }

    function renderPanelContent(tour, tab) {
        const panels = tour.panels || {};
        const panel = panels[tab];
        if (!panel) {
            return '<p class="tour-info-sidebar__empty">Content not available.</p>';
        }
        switch (tab) {
            case 'description':
                return renderDescription(panel);
            case 'inclusion':
                return renderInclusion(panel);
            case 'timings':
                return renderTimings(panel);
            case 'useful':
                return renderUseful(panel);
            default:
                return '<p class="tour-info-sidebar__empty">Content not available.</p>';
        }
    }

    function setActiveListTab(tourId, tab) {
        document.querySelectorAll('.tour-tab-btn').forEach(function(btn) {
            const match = btn.getAttribute('data-tour-id') === String(tourId) &&
                btn.getAttribute('data-tour-tab') === tab;
            btn.classList.toggle('active', match);
        });
    }

    function setSidebarTabs(tab) {
        if (!tabsNav) return;
        tabsNav.querySelectorAll('.tour-info-sidebar__tab').forEach(function(btn) {
            btn.classList.toggle('is-active', btn.getAttribute('data-panel-tab') === tab);
        });
    }

    function openSidebar(tourId, tab) {
        const tour = panelData[tourId];
        if (!tour || !sidebar || !bodyEl) return;

        activeTourId = tourId;
        activeTab = tab || 'description';

        if (titleEl) titleEl.textContent = tour.title;
        if (metaEl) {
            const parts = [];
            if (tour.destination) parts.push(tour.destination);
            if (tour.price) parts.push('From ' + formatPrice(tour.price));
            metaEl.textContent = parts.join(' · ');
        }
        if (viewLink) {
            viewLink.href = tour.detail_url || '#';
        }
        if (bookLink) {
            bookLink.href = tour.booking_url || '#';
        }

        const cartTourId = document.getElementById('tourInfoSidebarTourId');
        const cartPeople = document.getElementById('tourInfoSidebarPeople');
        const cartReturn = document.getElementById('tourInfoSidebarReturnUrl');
        if (cartTourId) cartTourId.value = tour.id;
        if (cartPeople) cartPeople.value = tour.default_people || 2;
        if (cartReturn) cartReturn.value = window.location.pathname + window.location.search;

        bodyEl.innerHTML = renderPanelContent(tour, activeTab);
        setSidebarTabs(activeTab);
        setActiveListTab(tourId, activeTab);

        sidebar.classList.add('is-open');
        sidebar.setAttribute('aria-hidden', 'false');
        document.body.classList.add('tour-sidebar-open');
    }

    function closeSidebar() {
        if (!sidebar) return;
        sidebar.classList.remove('is-open');
        sidebar.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('tour-sidebar-open');
        document.querySelectorAll('.tour-tab-btn.active').forEach(function(btn) {
            btn.classList.remove('active');
        });
        activeTourId = null;
    }

    function switchSidebarTab(tab) {
        if (!activeTourId || !panelData[activeTourId]) return;
        activeTab = tab;
        bodyEl.innerHTML = renderPanelContent(panelData[activeTourId], activeTab);
        setSidebarTabs(activeTab);
        setActiveListTab(activeTourId, activeTab);
    }

    function init() {
        if (!sidebar) return;

        document.querySelectorAll('.tour-tab-btn').forEach(function(btn) {
            function handleOpen(e) {
                e.preventDefault();
                e.stopPropagation();
                const tourId = btn.getAttribute('data-tour-id');
                const tab = btn.getAttribute('data-tour-tab');
                if (!tourId || !panelData[tourId]) return;
                openSidebar(tourId, tab);
            }

            btn.addEventListener('click', handleOpen);
            btn.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    handleOpen(e);
                }
            });
        });

        if (tabsNav) {
            tabsNav.addEventListener('click', function(e) {
                const tabBtn = e.target.closest('.tour-info-sidebar__tab');
                if (!tabBtn) return;
                switchSidebarTab(tabBtn.getAttribute('data-panel-tab'));
            });
        }

        sidebar.querySelectorAll('[data-tour-sidebar-close]').forEach(function(el) {
            el.addEventListener('click', closeSidebar);
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && sidebar.classList.contains('is-open')) {
                closeSidebar();
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();

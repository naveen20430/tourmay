/**
 * Home Page JavaScript
 * Hero search: Transfer (cab) + Activity (tour)
 */

(function() {
    'use strict';

    const BASE_URL = window.BASE_URL || '';
    let activeSearchType = 'activity';

    function getActiveSearchType() {
        const activeTab = document.querySelector('.search-category-tab.active');
        return activeTab ? activeTab.getAttribute('data-search-tab') : activeSearchType;
    }

    /**
     * Tab switching: Transfer (cab) / Activity (tour)
     */
    function scrollToDestinationsSection() {
        const section = document.getElementById('destinations-cab-section');
        if (!section) return;
        section.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function filterCabTravelBlocks() {
        const pickup = document.getElementById('cab_pickup');
        const dropoff = document.getElementById('cab_dropoff');
        const pickupVal = pickup ? pickup.value : '';
        const dropoffVal = dropoff ? dropoff.value : '';
        const blocks = document.querySelectorAll('.cab-route-block');
        const emptyMsg = document.getElementById('cabTravelEmptyFilter');
        let visible = 0;

        blocks.forEach(function(block) {
            const from = block.getAttribute('data-from') || '';
            const to = block.getAttribute('data-to') || '';
            let show = true;

            if (pickupVal && from !== pickupVal) {
                show = false;
            }
            if (dropoffVal && to !== dropoffVal) {
                show = false;
            }

            block.classList.toggle('is-hidden', !show);
            if (show) {
                visible++;
            }
        });

        if (emptyMsg) {
            emptyMsg.hidden = !(blocks.length && visible === 0);
        }
    }

    function switchHomeSectionView(type) {
        const toursView = document.getElementById('destinationsToursView');
        const travelView = document.getElementById('destinationsTravelView');
        if (!toursView || !travelView) {
            return;
        }

        if (type === 'transfer') {
            toursView.hidden = true;
            travelView.hidden = false;
            filterCabTravelBlocks();
            scrollToDestinationsSection();
        } else {
            toursView.hidden = false;
            travelView.hidden = true;
            const emptyMsg = document.getElementById('cabTravelEmptyFilter');
            if (emptyMsg) {
                emptyMsg.hidden = true;
            }
        }
    }

    function initSearchTabs() {
        const tabs = document.querySelectorAll('.search-category-tab');
        const panels = document.querySelectorAll('.search-panel');

        tabs.forEach(function(tab) {
            tab.addEventListener('click', function() {
                const type = tab.getAttribute('data-search-tab');
                activeSearchType = type;

                tabs.forEach(function(t) {
                    t.classList.remove('active');
                    t.setAttribute('aria-selected', 'false');
                });
                tab.classList.add('active');
                tab.setAttribute('aria-selected', 'true');

                panels.forEach(function(panel) {
                    const isActive = panel.getAttribute('data-search-type') === type;
                    panel.classList.toggle('active', isActive);
                    if (isActive) {
                        panel.removeAttribute('hidden');
                    } else {
                        panel.setAttribute('hidden', '');
                    }
                });

                switchHomeSectionView(type);
            });
        });
    }

    /**
     * Filter cab drop-off options based on pick-up
     */
    const activityData = window.ACTIVITY_SEARCH_DATA || { countries: [], destinations: [], tours: [], pickup_places: [] };

    function syncActivityPickupDetail() {
        const sel = document.getElementById('activity_pickup_place');
        const wrap = document.getElementById('activityPickupDetailWrap');
        const inp = document.getElementById('activity_pickup_detail');
        const grp = document.getElementById('activityPickupGroup');
        if (!sel || !wrap || !inp) return;

        const value = sel.value;
        const needsDetail = value === 'Hotel' || value === 'Others' || value === 'Other Location' || value === 'Otherlocation';

        if (needsDetail) {
            wrap.removeAttribute('hidden');
            wrap.classList.add('activity-pickup-detail--open');
            inp.placeholder = value === 'Hotel' ? 'Enter hotel name' : 'Enter location details';
            inp.setAttribute('aria-label', inp.placeholder);
            inp.setAttribute('required', 'required');
            if (grp) grp.classList.add('activity-pickup-group--expanded');
        } else {
            wrap.setAttribute('hidden', '');
            wrap.classList.remove('activity-pickup-detail--open');
            inp.value = '';
            inp.removeAttribute('required');
            inp.placeholder = '';
            if (grp) grp.classList.remove('activity-pickup-group--expanded');
        }
    }

    function initActivityPickupDetail() {
        const sel = document.getElementById('activity_pickup_place');
        if (!sel || sel.dataset.pickupDetailBound === '1') return;
        sel.dataset.pickupDetailBound = '1';
        sel.addEventListener('change', syncActivityPickupDetail);
        syncActivityPickupDetail();
    }

    function updateGuestLabel(prefix) {
        const adultsEl = document.getElementById(prefix + '_adults');
        const childrenEl = document.getElementById(prefix + '_children');
        const guestsEl = document.getElementById(prefix + '_guests');
        const labelEl = document.getElementById(prefix + 'GuestLabel');
        const panelHead = document.getElementById(prefix + 'GuestPanelHead');
        if (!adultsEl || !childrenEl) return;

        const adults = parseInt(adultsEl.value, 10) || 1;
        const children = parseInt(childrenEl.value, 10) || 0;
        const parts = [];
        if (adults > 0) parts.push(adults + ' Adult' + (adults > 1 ? 's' : ''));
        if (children > 0) parts.push(children + ' Child' + (children > 1 ? 'ren' : ''));
        const label = parts.join(', ') || '1 Adult';

        if (labelEl) labelEl.textContent = label;
        if (panelHead) panelHead.textContent = label;
        if (guestsEl) guestsEl.value = String(adults + children);

        const adultsCount = document.getElementById(prefix + 'AdultsCount');
        const childrenCount = document.getElementById(prefix + 'ChildrenCount');
        if (adultsCount) adultsCount.textContent = String(adults);
        if (childrenCount) childrenCount.textContent = String(children);
    }

    function initGuestDropdown(prefix) {
        const dropdown = document.getElementById(prefix + 'GuestDropdown');
        const toggle = document.getElementById(prefix + 'GuestToggle');
        const panel = document.getElementById(prefix + 'GuestPanel');
        const adultsInput = document.getElementById(prefix + '_adults');
        const childrenInput = document.getElementById(prefix + '_children');
        if (!dropdown || !toggle || !panel || !adultsInput || !childrenInput) return;

        function setGuests(adults, children) {
            adultsInput.value = String(Math.max(1, Math.min(12, adults)));
            childrenInput.value = String(Math.max(0, Math.min(8, children)));
            updateGuestLabel(prefix);
        }

        function setPanelOpen(isOpen) {
            panel.toggleAttribute('hidden', !isOpen);
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            dropdown.classList.toggle('is-open', isOpen);
        }

        toggle.addEventListener('click', function(e) {
            e.stopPropagation();
            const open = panel.hasAttribute('hidden');
            document.querySelectorAll('.activity-guest-dropdown').forEach(function(otherDropdown) {
                if (otherDropdown === dropdown) return;
                otherDropdown.classList.remove('is-open');
                const otherPanel = otherDropdown.querySelector('.activity-guest-panel');
                const otherToggle = otherDropdown.querySelector('.activity-guest-toggle');
                if (otherPanel) otherPanel.setAttribute('hidden', '');
                if (otherToggle) otherToggle.setAttribute('aria-expanded', 'false');
            });
            setPanelOpen(open);
        });

        dropdown.querySelectorAll('[data-guest-action]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const action = btn.getAttribute('data-guest-action');
                let adults = parseInt(adultsInput.value, 10) || 1;
                let children = parseInt(childrenInput.value, 10) || 0;

                if (action === 'adults-minus') adults--;
                if (action === 'adults-plus') adults++;
                if (action === 'children-minus') children--;
                if (action === 'children-plus') children++;

                setGuests(adults, children);
            });
        });

        document.addEventListener('click', function(e) {
            if (!dropdown.contains(e.target)) {
                setPanelOpen(false);
            }
        });

        updateGuestLabel(prefix);
    }

    function initActivityGuestDropdown() {
        initGuestDropdown('activity');
    }

    function initCabGuestDropdown() {
        initGuestDropdown('cab');
    }

    function previewActivitySuggestion(item) {
        if (item.country) {
            filterActivityDestinations(item.country, false);
            setActiveSidebarCountry(item.country);
        } else if (item.destination_slug) {
            const dest = activityData.destinations.find(function(d) { return d.slug === item.destination_slug; });
            if (dest) {
                filterActivityDestinations(dest.country, false);
                setActiveSidebarCountry(dest.country);
            }
        }

        const grid = document.getElementById('activityDestGrid');
        if (!grid || !item.destination_slug) return;

        grid.querySelectorAll('.activity-dest-card').forEach(function(card) {
            const match = card.getAttribute('data-slug') === item.destination_slug;
            card.classList.toggle('is-preview', match);
        });
    }

    function clearActivitySuggestionPreview() {
        const grid = document.getElementById('activityDestGrid');
        if (!grid) return;
        grid.querySelectorAll('.activity-dest-card.is-preview').forEach(function(card) {
            card.classList.remove('is-preview');
        });
    }

    function navigateActivitySuggestion(item) {
        if (item.type === 'tour' && item.slug) {
            window.location.href = BASE_URL + 'tour/' + encodeURIComponent(item.slug);
            return;
        }

        if (item.type === 'destination' && item.slug) {
            window.location.href = BASE_URL + 'tours/destination/' + encodeURIComponent(item.slug);
            return;
        }

        selectActivitySuggestion(item);
    }

    function selectActivitySuggestion(item) {
        const query = document.getElementById('activity_query');
        const destInput = document.getElementById('activity_destination');
        const tourInput = document.getElementById('activity_tour_slug');
        const countryInput = document.getElementById('activity_country');
        const suggestions = document.getElementById('activitySuggestions');

        if (query) query.value = item.label;
        if (destInput) destInput.value = item.destination_slug || (item.type === 'destination' ? item.slug : '') || '';
        if (tourInput) tourInput.value = item.type === 'tour' ? item.slug : '';
        if (countryInput) countryInput.value = item.country || '';

        if (suggestions) suggestions.setAttribute('hidden', '');

        previewActivitySuggestion(item);
        clearActivitySuggestionPreview();
    }

    function renderActivitySuggestions(items) {
        const box = document.getElementById('activitySuggestions');
        if (!box) return;

        if (!items.length) {
            box.setAttribute('hidden', '');
            box.innerHTML = '';
            return;
        }

        box.innerHTML = items.map(function(item) {
            const sub = item.sub ? '<small>' + item.sub + '</small>' : '';
            return '<button type="button" class="activity-suggestion-item" data-type="' + item.type + '" data-slug="' + item.slug + '">' +
                item.label + sub + '</button>';
        }).join('');

        box.removeAttribute('hidden');

        box.querySelectorAll('.activity-suggestion-item').forEach(function(btn, index) {
            btn.addEventListener('mouseenter', function() {
                previewActivitySuggestion(items[index]);
            });
            btn.addEventListener('click', function() {
                navigateActivitySuggestion(items[index]);
            });
        });
    }

    function buildActivitySuggestions(term) {
        const q = term.trim().toLowerCase();
        if (q.length < 1) return [];

        const results = [];

        activityData.destinations.forEach(function(dest) {
            if (dest.name.toLowerCase().includes(q) || (dest.city && dest.city.toLowerCase().includes(q))) {
                results.push({
                    type: 'destination',
                    label: dest.name,
                    sub: dest.country + (dest.tour_count ? ' · ' + dest.tour_count + ' tours' : ''),
                    slug: dest.slug,
                    destination_slug: dest.slug,
                    country: dest.country
                });
            }
        });

        activityData.tours.forEach(function(tour) {
            if (tour.title.toLowerCase().includes(q)) {
                results.push({
                    type: 'tour',
                    label: tour.title,
                    sub: tour.destination_name + ' · Tour',
                    slug: tour.slug,
                    destination_slug: tour.destination_slug,
                    country: tour.country
                });
            }
        });

        return results.slice(0, 8);
    }

    function initActivityAutocomplete() {
        const query = document.getElementById('activity_query');
        const suggestions = document.getElementById('activitySuggestions');
        if (!query) return;

        if (suggestions) {
            suggestions.addEventListener('mouseleave', clearActivitySuggestionPreview);
        }

        query.addEventListener('input', function() {
            const destInput = document.getElementById('activity_destination');
            const tourInput = document.getElementById('activity_tour_slug');
            const countryInput = document.getElementById('activity_country');
            if (destInput) destInput.value = '';
            if (tourInput) tourInput.value = '';
            if (countryInput) countryInput.value = '';
            renderActivitySuggestions(buildActivitySuggestions(query.value));
        });

        query.addEventListener('focus', function() {
            renderActivitySuggestions(buildActivitySuggestions(query.value));
        });

        document.addEventListener('click', function(e) {
            const wrap = document.querySelector('.activity-query-wrap');
            const suggestions = document.getElementById('activitySuggestions');
            if (wrap && suggestions && !wrap.contains(e.target)) {
                suggestions.setAttribute('hidden', '');
            }
        });
    }

    function setActiveSidebarCountry(country) {
        const sidebar = document.getElementById('activitySidebar');
        if (!sidebar) return;

        sidebar.querySelectorAll('.activity-sidebar-item').forEach(function(btn) {
            const match = (btn.getAttribute('data-country') || '') === (country || '');
            btn.classList.toggle('active', match);
        });
    }

    function filterActivityDestinations(country, topOnly) {
        const grid = document.getElementById('activityDestGrid');
        if (!grid) return;

        const cards = grid.querySelectorAll('.activity-dest-card');
        let visible = 0;

        cards.forEach(function(card) {
            const cardCountry = card.getAttribute('data-country') || '';
            const popular = card.getAttribute('data-popular') === '1';
            let show = true;

            if (country) {
                show = cardCountry === country;
            } else if (topOnly !== false) {
                const anyPopular = Array.from(cards).some(function(c) { return c.getAttribute('data-popular') === '1'; });
                show = anyPopular ? popular : true;
            }

            card.classList.toggle('is-hidden', !show);
            if (show) visible++;
        });

        let empty = grid.querySelector('.activity-dest-empty');
        if (!visible) {
            if (!empty) {
                empty = document.createElement('div');
                empty.className = 'activity-dest-empty';
                empty.textContent = 'No destinations found for this category.';
                grid.appendChild(empty);
            }
        } else if (empty) {
            empty.remove();
        }
    }

    function selectActivityDestination(dest) {
        const query = document.getElementById('activity_query');
        const destInput = document.getElementById('activity_destination');
        const tourInput = document.getElementById('activity_tour_slug');
        const countryInput = document.getElementById('activity_country');
        const suggestions = document.getElementById('activitySuggestions');

        if (query) query.value = dest.name || '';
        if (destInput) destInput.value = dest.slug || '';
        if (tourInput) tourInput.value = '';
        if (countryInput) countryInput.value = dest.country || '';

        if (suggestions) {
            suggestions.setAttribute('hidden', '');
            suggestions.innerHTML = '';
        }

        if (dest.country) {
            filterActivityDestinations(dest.country, false);
            setActiveSidebarCountry(dest.country);
        }

        clearActivitySuggestionPreview();

        if (query) {
            query.focus();
        }
    }

    function initActivityDestCards() {
        const grid = document.getElementById('activityDestGrid');
        if (!grid) return;

        grid.querySelectorAll('.activity-dest-card').forEach(function(card) {
            card.addEventListener('click', function() {
                selectActivityDestination({
                    name: card.getAttribute('data-name') || '',
                    slug: card.getAttribute('data-slug') || '',
                    country: card.getAttribute('data-country') || ''
                });
            });
        });
    }

    function initActivitySidebar() {
        const sidebar = document.getElementById('activitySidebar');
        if (!sidebar) return;

        sidebar.querySelectorAll('.activity-sidebar-item').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const country = btn.getAttribute('data-country') || '';
                setActiveSidebarCountry(country);
                filterActivityDestinations(country, !country);

                const countryInput = document.getElementById('activity_country');
                if (countryInput) countryInput.value = country;
            });
        });

        filterActivityDestinations('', true);
    }

    function initCabLocationFilter() {
        const pickup = document.getElementById('cab_pickup');
        const dropoff = document.getElementById('cab_dropoff');
        const routes = window.CAB_ROUTES || [];

        if (!pickup || !dropoff || !routes.length) return;

        const allDropoffOptions = Array.from(dropoff.options).map(function(opt) {
            return { value: opt.value, text: opt.text };
        });

        function updateDropoffs() {
            const from = pickup.value;
            const current = dropoff.value;

            dropoff.innerHTML = '<option value="">Drop-Off</option>';

            const validTos = routes
                .filter(function(r) { return !from || r.from_location === from; })
                .map(function(r) { return r.to_location; });

            const uniqueTos = [...new Set(validTos)].sort();

            if (!from) {
                allDropoffOptions.forEach(function(opt) {
                    if (opt.value) {
                        const o = document.createElement('option');
                        o.value = opt.value;
                        o.textContent = opt.text;
                        dropoff.appendChild(o);
                    }
                });
            } else {
                uniqueTos.forEach(function(loc) {
                    const o = document.createElement('option');
                    o.value = loc;
                    o.textContent = loc;
                    dropoff.appendChild(o);
                });
            }

            if (current && Array.from(dropoff.options).some(function(o) { return o.value === current; })) {
                dropoff.value = current;
            }
        }

        pickup.addEventListener('change', function() {
            updateDropoffs();
            if (getActiveSearchType() === 'transfer') {
                filterCabTravelBlocks();
            }
        });

        dropoff.addEventListener('change', function() {
            if (getActiveSearchType() === 'transfer') {
                filterCabTravelBlocks();
            }
        });
    }

    function findCabRoute(pickup, dropoff) {
        const routes = window.CAB_ROUTES || [];
        return routes.find(function(r) {
            return r.from_location === pickup && r.to_location === dropoff;
        });
    }

    function getTourSearchData() {
        const query = document.getElementById('activity_query');
        const destination = document.getElementById('activity_destination');
        const tourSlug = document.getElementById('activity_tour_slug');
        const country = document.getElementById('activity_country');
        const travelDateInput = document.getElementById('activity_travel_date');
        const pickupPlace = document.getElementById('activity_pickup_place');
        const pickupDetail = document.getElementById('activity_pickup_detail');
        const adults = document.getElementById('activity_adults');
        const children = document.getElementById('activity_children');
        const guests = document.getElementById('activity_guests');

        let destinationText = '';
        if (destination && destination.value) {
            const dest = activityData.destinations.find(function(d) { return d.slug === destination.value; });
            destinationText = dest ? dest.name : destination.value;
        }

        return {
            query: query ? query.value.trim() : '',
            destination: destination ? destination.value : '',
            destinationText: destinationText,
            tour: tourSlug ? tourSlug.value : '',
            country: country ? country.value : '',
            countryText: country ? country.value : '',
            travel_date: travelDateInput ? travelDateInput.value : '',
            pickup_place: pickupPlace ? pickupPlace.value : '',
            pickup_placeText: pickupPlace ? (pickupPlace.options[pickupPlace.selectedIndex]?.text || '') : '',
            pickup_detail: pickupDetail ? pickupDetail.value.trim() : '',
            adults: adults ? adults.value : '2',
            children: children ? children.value : '0',
            guests: guests ? guests.value : '2',
            guestsText: document.getElementById('activityGuestLabel')?.textContent || ''
        };
    }

    function validateActivityPickup() {
        const data = getTourSearchData();
        if (!data.pickup_place) {
            alert('Please select a Pickup Place.');
            return false;
        }
        if ((data.pickup_place === 'Hotel' || data.pickup_place === 'Others' || data.pickup_place === 'Other Location' || data.pickup_place === 'Otherlocation') && !data.pickup_detail) {
            alert(data.pickup_place === 'Hotel'
                ? 'Please enter your hotel name.'
                : 'Please enter location details.');
            return false;
        }
        return true;
    }

    function getCabSearchData() {
        const pickup = document.getElementById('cab_pickup');
        const dropoff = document.getElementById('cab_dropoff');
        const travelDateInput = document.getElementById('cab_travel_date');
        const tripType = document.getElementById('cab_trip_type');

        return {
            pickup: pickup ? pickup.value : '',
            pickupText: pickup ? (pickup.options[pickup.selectedIndex]?.text || '') : '',
            dropoff: dropoff ? dropoff.value : '',
            dropoffText: dropoff ? (dropoff.options[dropoff.selectedIndex]?.text || '') : '',
            travel_date: travelDateInput ? travelDateInput.value : '',
            trip_type: tripType ? tripType.value : 'one_way',
            trip_typeText: tripType ? (tripType.options[tripType.selectedIndex]?.text || '') : ''
        };
    }

    function submitCabSearch() {
        activeSearchType = 'transfer';
        const data = getCabSearchData();

        if (!data.pickup || !data.dropoff) {
            alert('Please select both Pick-Up and Drop-Off locations.');
            return false;
        }
        if (data.pickup === data.dropoff) {
            alert('Pick-Up and Drop-Off must be different.');
            return false;
        }

        switchHomeSectionView('transfer');

        const route = findCabRoute(data.pickup, data.dropoff);
        if (route) {
            const params = new URLSearchParams();
            if (data.travel_date) params.append('travel_date', data.travel_date);
            if (data.trip_type) params.append('trip_type', data.trip_type);
            const qs = params.toString();
            window.location.href = BASE_URL + 'cab-route-details.php?route_id=' + route.id + (qs ? '&' + qs : '');
            return false;
        }

        scrollToDestinationsSection();
        return false;
    }

    function showPhoneModal(type) {
        activeSearchType = type || getActiveSearchType();

        if (activeSearchType === 'transfer') {
            submitCabSearch();
            return;
        }

        let detailsHTML = '';

        const submitText = document.getElementById('modalSubmitText');
        if (submitText) {
            submitText.textContent = 'Search Tours';
        }

        if (!validateActivityPickup()) {
            return;
        }
        const data = getTourSearchData();
        if (data.query) detailsHTML += '<div><strong>Search:</strong> ' + data.query + '</div>';
        if (data.destination) detailsHTML += '<div><strong>Destination:</strong> ' + data.destinationText + '</div>';
        if (data.tour) detailsHTML += '<div><strong>Tour:</strong> ' + data.query + '</div>';
        if (data.country) detailsHTML += '<div><strong>Country:</strong> ' + data.countryText + '</div>';
        if (data.pickup_placeText) {
            let pickupLine = data.pickup_placeText;
            if (data.pickup_detail) {
                pickupLine += ' — ' + data.pickup_detail;
            }
            detailsHTML += '<div><strong>Pickup:</strong> ' + pickupLine + '</div>';
        }
        if (data.guestsText) detailsHTML += '<div><strong>Guests:</strong> ' + data.guestsText + '</div>';
        if (data.travel_date) detailsHTML += '<div><strong>Travel Date:</strong> ' + data.travel_date + '</div>';

        if (!detailsHTML) {
            detailsHTML = '<div style="color: #6c757d; font-style: italic;">No search filters selected</div>';
        }

        const searchDetailsEl = document.getElementById('searchDetails');
        if (searchDetailsEl) {
            searchDetailsEl.innerHTML = detailsHTML;
        }

        const modal = document.getElementById('phoneModal');
        if (modal) {
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
    }

    function closePhoneModal() {
        const modal = document.getElementById('phoneModal');
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        const phoneInput = document.getElementById('modalPhone');
        if (phoneInput) {
            phoneInput.value = '';
        }
    }

    function submitSearch(event) {
        event.preventDefault();

        const phoneInput = document.getElementById('modalPhone');
        if (!phoneInput) return false;

        const phone = phoneInput.value;
        const phonePattern = /^[0-9]{10}$/;

        if (!phonePattern.test(phone)) {
            alert('Please enter a valid 10-digit phone number');
            return false;
        }

        const formData = new FormData();
        formData.append('phone', phone);

        let redirectUrl = BASE_URL + 'tours';

        if (activeSearchType === 'transfer') {
            submitCabSearch();
            return false;
        }

        const tourData = getTourSearchData();
        if (!validateActivityPickup()) {
            return false;
        }
        if (tourData.destination) formData.append('destination', tourData.destination);
        if (tourData.tour) formData.append('tour', tourData.tour);
        if (tourData.country) formData.append('from', tourData.country);
        if (tourData.travel_date) formData.append('travel_date', tourData.travel_date);
        if (tourData.pickup_place) formData.append('pickup_place', tourData.pickup_place);
        if (tourData.pickup_detail) formData.append('pickup_detail', tourData.pickup_detail);
        if (tourData.adults) formData.append('adults', tourData.adults);
        if (tourData.children) formData.append('children', tourData.children);
        if (tourData.guests) formData.append('guests', tourData.guests);

        fetch(BASE_URL + 'api/save-search-query.php', {
            method: 'POST',
            body: formData
        })
        .then(function(response) {
            return response.text().then(function(text) {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    throw new Error('Server returned invalid JSON');
                }
            });
        })
        .then(function(data) {
            if (data.success) {
                if (tourData.tour) {
                    redirectUrl = BASE_URL + 'tour/' + encodeURIComponent(tourData.tour);
                } else {
                    const params = new URLSearchParams();
                    formData.forEach(function(value, key) {
                        if (value && key !== 'phone') params.append(key, value);
                    });
                    const queryString = params.toString();
                    redirectUrl = queryString ? BASE_URL + 'tours?' + queryString : BASE_URL + 'tours';
                }
                window.location.href = redirectUrl;
            } else {
                alert('Error: ' + (data.message || 'Failed to save search query. Please try again.'));
            }
        })
        .catch(function() {
            alert('Network error. Please check your connection and try again.');
        });

        return false;
    }

    function initCardHoverEffects() {
        const cards = document.querySelectorAll('.card');

        cards.forEach(function(card) {
            card.addEventListener('mouseenter', function() {
                this.style.boxShadow = '0 25px 50px rgba(102, 126, 234, 0.15), 0 0 0 1px rgba(102, 126, 234, 0.1)';
                const overlay = this.querySelector('div[style*="opacity: 0"]');
                if (overlay) {
                    overlay.style.opacity = '1';
                    overlay.style.background = 'linear-gradient(135deg, rgba(102, 126, 234, 0.08) 0%, rgba(118, 75, 162, 0.08) 100%)';
                }
                this.style.transform = 'translateY(-8px) scale(1.02)';
            });

            card.addEventListener('mouseleave', function() {
                this.style.boxShadow = '0 15px 35px rgba(0, 0, 0, 0.1)';
                this.style.transform = 'translateY(0) scale(1)';
                const overlay = this.querySelector('div[style*="opacity: 1"]');
                if (overlay && overlay.style.background.includes('rgba(102, 126, 234')) {
                    overlay.style.opacity = '0';
                    overlay.style.background = 'linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%)';
                }
            });
        });
    }

    function initCounterAnimation() {
        const counters = document.querySelectorAll('.gradient-text');
        const observerOptions = { threshold: 0.7 };

        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    const counter = entry.target;
                    const text = counter.textContent;
                    if (text.includes('+')) {
                        const number = parseInt(text, 10);
                        if (number > 0) {
                            animateCounter(counter, 0, number, 1500);
                        }
                    }
                    observer.unobserve(counter);
                }
            });
        }, observerOptions);

        counters.forEach(function(counter) {
            if (counter.textContent.includes('+')) {
                observer.observe(counter);
            }
        });

        function animateCounter(element, start, end, duration) {
            const startTime = performance.now();
            const suffix = element.textContent.match(/\+|\w+/g)?.slice(1).join(' ') || '';

            function updateCounter(currentTime) {
                const elapsed = currentTime - startTime;
                const progress = Math.min(elapsed / duration, 1);
                const current = Math.floor(progress * (end - start) + start);
                element.textContent = current + '+' + (suffix ? ' ' + suffix : '');

                if (progress < 1) {
                    requestAnimationFrame(updateCounter);
                }
            }

            requestAnimationFrame(updateCounter);
        }
    }

    function initParallaxEffect() {
        window.addEventListener('scroll', function() {
            const scrolled = window.pageYOffset;
            const floatingElements = document.querySelectorAll('[style*="animation: float"]');

            floatingElements.forEach(function(el, index) {
                const speed = 0.5 + (index * 0.2);
                el.style.transform = 'translateY(' + (scrolled * speed * -0.1) + 'px)';
            });
        });
    }

    function initModalHandlers() {
        window.onclick = function(event) {
            const modal = document.getElementById('phoneModal');
            if (event.target === modal) {
                closePhoneModal();
            }
        };

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closePhoneModal();
            }
        });
    }

    function initSearchFormLabels() {
        const formGroups = document.querySelectorAll('.search-panel .form-group');

        formGroups.forEach(function(formGroup) {
            const field = formGroup.querySelector('select, input');
            const label = formGroup.querySelector('label');

            if (!field || !label) return;

            function updateLabelVisibility() {
                if (field.value && field.value.trim() !== '') {
                    label.style.opacity = '0';
                } else {
                    label.style.opacity = '1';
                }
            }

            updateLabelVisibility();
            field.addEventListener('change', updateLabelVisibility);
            field.addEventListener('input', updateLabelVisibility);
            field.addEventListener('focus', function() {
                if (field.value && field.value.trim() !== '') {
                    label.style.opacity = '0';
                }
            });
            field.addEventListener('blur', updateLabelVisibility);
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        initSearchTabs();
        initActivityPickupDetail();
        initActivityGuestDropdown();
        initActivityAutocomplete();
        initActivityDestCards();
        initActivitySidebar();
        initCabLocationFilter();
        initCardHoverEffects();
        initCounterAnimation();
        initParallaxEffect();
        initModalHandlers();
        initSearchFormLabels();
    });

    window.showPhoneModal = showPhoneModal;
    window.closePhoneModal = closePhoneModal;
    window.submitSearch = submitSearch;
    window.submitCabSearch = submitCabSearch;

})();

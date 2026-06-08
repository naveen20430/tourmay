/**
 * Home Page JavaScript
 * Hero search: Transfer (cab) + Activity (tour)
 */

(function() {
    'use strict';

    const BASE_URL = window.BASE_URL || '';
    let activeSearchType = 'transfer';

    function getActiveSearchType() {
        const activeTab = document.querySelector('.search-category-tab.active');
        return activeTab ? activeTab.getAttribute('data-search-tab') : activeSearchType;
    }

    /**
     * Tab switching: Transfer (cab) / Activity (tour)
     */
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
            });
        });
    }

    /**
     * Filter cab drop-off options based on pick-up
     */
    /**
     * Activity tab: filter destinations by selected country
     */
    function initActivityCountryFilter() {
        const countrySelect = document.getElementById('activity_country');
        const destSelect = document.getElementById('activity_destination');
        if (!countrySelect || !destSelect) return;

        const allOptions = Array.from(destSelect.querySelectorAll('option')).map(function(opt) {
            return {
                value: opt.value,
                text: opt.textContent,
                country: opt.getAttribute('data-country') || ''
            };
        });

        function rebuildDestinations() {
            const country = countrySelect.value;
            const current = destSelect.value;
            destSelect.innerHTML = '<option value="">Select Destination</option>';

            allOptions.forEach(function(opt) {
                if (!opt.value) return;
                if (!country || opt.country === country) {
                    const o = document.createElement('option');
                    o.value = opt.value;
                    o.textContent = opt.text;
                    o.setAttribute('data-country', opt.country);
                    destSelect.appendChild(o);
                }
            });

            if (current && Array.from(destSelect.options).some(function(o) { return o.value === current; })) {
                destSelect.value = current;
            }
        }

        countrySelect.addEventListener('change', rebuildDestinations);
        rebuildDestinations();
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

        pickup.addEventListener('change', updateDropoffs);
    }

    function findCabRoute(pickup, dropoff) {
        const routes = window.CAB_ROUTES || [];
        return routes.find(function(r) {
            return r.from_location === pickup && r.to_location === dropoff;
        });
    }

    function getTourSearchData() {
        const destination = document.getElementById('activity_destination') || document.querySelector('#tourSearchForm select[name="destination"]');
        const country = document.getElementById('activity_country') || document.querySelector('#tourSearchForm select[name="country"]');
        const travelDateInput = document.getElementById('activity_travel_date') || document.querySelector('#tourSearchForm input[name="travel_date"]');
        const pickupPlace = document.getElementById('activity_pickup_place');
        const pickupDetail = document.getElementById('activity_pickup_detail');

        return {
            destination: destination ? destination.value : '',
            destinationText: destination ? (destination.options[destination.selectedIndex]?.text || '') : '',
            country: country ? country.value : '',
            countryText: country ? (country.options[country.selectedIndex]?.text || '') : '',
            travel_date: travelDateInput ? travelDateInput.value : '',
            pickup_place: pickupPlace ? pickupPlace.value : '',
            pickup_placeText: pickupPlace ? (pickupPlace.options[pickup.selectedIndex]?.text || '') : '',
            pickup_detail: pickupDetail ? pickupDetail.value.trim() : ''
        };
    }

    function validateActivityPickup() {
        const data = getTourSearchData();
        if (!data.pickup_place) {
            alert('Please select a Pickup Place.');
            return false;
        }
        if ((data.pickup_place === 'Hotel' || data.pickup_place === 'Otherlocation') && !data.pickup_detail) {
            alert(data.pickup_place === 'Hotel'
                ? 'Please enter your hotel name.'
                : 'Please enter location details.');
            return false;
        }
        return true;
    }

    function initActivityPickupDetail() {
        if (typeof window.syncActivityPickupDetail === 'function') {
            const sel = document.getElementById('activity_pickup_place');
            if (sel && sel.dataset.pickupDetailBound !== '1') {
                sel.dataset.pickupDetailBound = '1';
                sel.addEventListener('change', window.syncActivityPickupDetail);
                sel.addEventListener('input', window.syncActivityPickupDetail);
                window.syncActivityPickupDetail();
            }
        }
    }

    function getCabSearchData() {
        const pickup = document.getElementById('cab_pickup');
        const dropoff = document.getElementById('cab_dropoff');
        const travelDateInput = document.getElementById('cab_travel_date');
        const tripType = document.getElementById('cab_trip_type');
        const guests = document.getElementById('cab_guests');

        return {
            pickup: pickup ? pickup.value : '',
            pickupText: pickup ? (pickup.options[pickup.selectedIndex]?.text || '') : '',
            dropoff: dropoff ? dropoff.value : '',
            dropoffText: dropoff ? (dropoff.options[dropoff.selectedIndex]?.text || '') : '',
            travel_date: travelDateInput ? travelDateInput.value : '',
            trip_type: tripType ? tripType.value : 'one_way',
            trip_typeText: tripType ? (tripType.options[tripType.selectedIndex]?.text || '') : '',
            guests: guests ? guests.value : '2',
            guestsText: guests ? (guests.options[guests.selectedIndex]?.text || '') : ''
        };
    }

    function showPhoneModal(type) {
        activeSearchType = type || getActiveSearchType();
        let detailsHTML = '';

        const submitText = document.getElementById('modalSubmitText');
        if (submitText) {
            submitText.textContent = activeSearchType === 'transfer' ? 'Search Cabs' : 'Search Tours';
        }

        if (activeSearchType === 'transfer') {
            const data = getCabSearchData();
            if (!data.pickup || !data.dropoff) {
                alert('Please select both Pick-Up and Drop-Off locations.');
                return;
            }
            if (data.pickup === data.dropoff) {
                alert('Pick-Up and Drop-Off must be different.');
                return;
            }
            if (data.pickup) detailsHTML += '<div><strong>Pick-Up:</strong> ' + data.pickupText + '</div>';
            if (data.dropoff) detailsHTML += '<div><strong>Drop-Off:</strong> ' + data.dropoffText + '</div>';
            if (data.trip_typeText) detailsHTML += '<div><strong>Trip:</strong> ' + data.trip_typeText + '</div>';
            if (data.travel_date) detailsHTML += '<div><strong>Date:</strong> ' + data.travel_date + '</div>';
            if (data.guestsText) detailsHTML += '<div><strong>Guests:</strong> ' + data.guestsText + '</div>';
        } else {
            if (!validateActivityPickup()) {
                return;
            }
            const data = getTourSearchData();
            if (data.destination) detailsHTML += '<div><strong>Destination:</strong> ' + data.destinationText + '</div>';
            if (data.country) detailsHTML += '<div><strong>Country:</strong> ' + data.countryText + '</div>';
            if (data.pickup_placeText) {
                let pickupLine = data.pickup_placeText;
                if (data.pickup_detail) {
                    pickupLine += ' — ' + data.pickup_detail;
                }
                detailsHTML += '<div><strong>Pickup:</strong> ' + pickupLine + '</div>';
            }
            if (data.travel_date) detailsHTML += '<div><strong>Travel Date:</strong> ' + data.travel_date + '</div>';
        }

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
            const data = getCabSearchData();
            if (!data.pickup || !data.dropoff) {
                alert('Please select both Pick-Up and Drop-Off locations.');
                return false;
            }

            const route = findCabRoute(data.pickup, data.dropoff);
            formData.append('from', data.pickup);
            formData.append('destination', data.dropoff);
            if (data.travel_date) formData.append('travel_date', data.travel_date);

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
            .then(function(apiData) {
                if (route) {
                    const params = new URLSearchParams();
                    if (data.travel_date) params.append('travel_date', data.travel_date);
                    if (data.trip_type) params.append('trip_type', data.trip_type);
                    if (data.guests) params.append('guests', data.guests);
                    const qs = params.toString();
                    redirectUrl = BASE_URL + 'cab-route-details.php?route_id=' + route.id + (qs ? '&' + qs : '');
                } else {
                    redirectUrl = BASE_URL + '#destinations-cab-section';
                }
                window.location.href = redirectUrl;
            })
            .catch(function() {
                if (route) {
                    window.location.href = BASE_URL + 'cab-route-details.php?route_id=' + route.id;
                } else {
                    window.location.href = BASE_URL + '#destinations-cab-section';
                }
            });

            return false;
        }

        const tourData = getTourSearchData();
        if (!validateActivityPickup()) {
            return false;
        }
        if (tourData.destination) formData.append('destination', tourData.destination);
        if (tourData.country) formData.append('from', tourData.country);
        if (tourData.travel_date) formData.append('travel_date', tourData.travel_date);
        if (tourData.pickup_place) formData.append('pickup_place', tourData.pickup_place);
        if (tourData.pickup_detail) formData.append('pickup_detail', tourData.pickup_detail);

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
                const params = new URLSearchParams();
                formData.forEach(function(value, key) {
                    if (value && key !== 'phone') params.append(key, value);
                });
                const queryString = params.toString();
                redirectUrl = queryString ? BASE_URL + 'tours?' + queryString : BASE_URL + 'tours';
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
        initActivityCountryFilter();
        initActivityPickupDetail();
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

})();

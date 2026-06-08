/**
 * Attractive Modern Datepicker JavaScript
 * Initializes Flatpickr with custom configurations and animations
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // Initialize all datepicker inputs
    function initializeDatepickers() {
        // Find all date inputs
        const dateInputs = document.querySelectorAll('input[type="date"], .datepicker-input, .date-picker');
        
        dateInputs.forEach(function(input) {
            // Skip if already initialized
            if (input.classList.contains('flatpickr-input')) return;
            
            // Add wrapper for icon
            if (!input.parentElement.classList.contains('datepicker-wrapper')) {
                const wrapper = document.createElement('div');
                wrapper.className = 'datepicker-wrapper';
                input.parentNode.insertBefore(wrapper, input);
                wrapper.appendChild(input);
            }
            
            // Add datepicker class
            input.classList.add('datepicker-input');
            
            // Determine configuration based on input attributes
            let config = getDatepickerConfig(input);
            
            // Initialize Flatpickr
            const fp = flatpickr(input, config);
            
            // Add custom event listeners
            addCustomEventListeners(input, fp);
        });
    }
    
    // Get configuration based on input attributes
    function getDatepickerConfig(input) {
        const baseConfig = {
            dateFormat: "Y-m-d",
            theme: "light",
            animate: true,
            position: "auto center",
            allowInput: true,
            clickOpens: true,
            altInput: true,
            altFormat: "F j, Y",
            locale: {
                firstDayOfWeek: 1,
                weekdays: {
                    shorthand: ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"],
                    longhand: ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"]
                },
                months: {
                    shorthand: ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"],
                    longhand: ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"]
                }
            },
            onOpen: function(selectedDates, dateStr, instance) {
                // Add opening animation
                const calendar = instance.calendarContainer;
                calendar.style.transform = 'translateY(-10px) scale(0.95)';
                calendar.style.opacity = '0';
                
                requestAnimationFrame(() => {
                    calendar.style.transition = 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)';
                    calendar.style.transform = 'translateY(0) scale(1)';
                    calendar.style.opacity = '1';
                });
                
                // Add custom class for styling
                calendar.classList.add('flatpickr-custom');
            },
            onClose: function(selectedDates, dateStr, instance) {
                // Add closing animation
                const calendar = instance.calendarContainer;
                calendar.style.transform = 'translateY(-5px) scale(0.98)';
                calendar.style.opacity = '0';
            },
            onChange: function(selectedDates, dateStr, instance) {
                // Add ripple effect to input
                addRippleEffect(instance.input);
                
                // Custom validation if needed
                if (instance.input.hasAttribute('data-min-date')) {
                    validateMinDate(instance.input, selectedDates[0]);
                }
            }
        };
        
        // Check for range mode
        if (input.hasAttribute('data-range') || input.classList.contains('date-range')) {
            baseConfig.mode = "range";
            baseConfig.altFormat = "F j, Y - F j, Y";
        }
        
        // Check for time picker
        if (input.hasAttribute('data-time') || input.classList.contains('datetime-picker')) {
            baseConfig.enableTime = true;
            baseConfig.dateFormat = "Y-m-d H:i";
            baseConfig.altFormat = "F j, Y at h:i K";
            baseConfig.time_24hr = false;
        }
        
        // Check for multiple dates
        if (input.hasAttribute('data-multiple') || input.classList.contains('multi-date')) {
            baseConfig.mode = "multiple";
            baseConfig.altFormat = "F j, Y";
        }
        
        // Set minimum date
        if (input.hasAttribute('data-min-date')) {
            baseConfig.minDate = input.getAttribute('data-min-date');
        } else if (input.getAttribute('min')) {
            baseConfig.minDate = input.getAttribute('min');
        } else if (input.hasAttribute('data-today-min')) {
            baseConfig.minDate = "today";
        }
        
        // Set maximum date
        if (input.hasAttribute('data-max-date')) {
            baseConfig.maxDate = input.getAttribute('data-max-date');
        }
        
        // Disable specific dates
        if (input.hasAttribute('data-disable-dates')) {
            const disabledDates = input.getAttribute('data-disable-dates').split(',');
            baseConfig.disable = disabledDates;
        }
        
        // Enable only specific dates
        if (input.hasAttribute('data-enable-dates')) {
            const enabledDates = input.getAttribute('data-enable-dates').split(',');
            baseConfig.enable = enabledDates;
        }
        
        // Disable weekends
        if (input.hasAttribute('data-no-weekends') || input.classList.contains('no-weekends')) {
            baseConfig.disable = [
                function(date) {
                    return (date.getDay() === 0 || date.getDay() === 6);
                }
            ];
        }
        
        return baseConfig;
    }
    
    // Add custom event listeners
    function addCustomEventListeners(input, flatpickrInstance) {
        // Add loading state on focus
        input.addEventListener('focus', function() {
            if (!flatpickrInstance.isOpen) {
                input.classList.add('datepicker-loading');
                setTimeout(() => {
                    input.classList.remove('datepicker-loading');
                }, 300);
            }
        });
        
        // Add validation on change
        input.addEventListener('change', function() {
            validateDateInput(input);
        });
        
        // Add keyboard shortcuts
        input.addEventListener('keydown', function(e) {
            // Escape to close
            if (e.key === 'Escape' && flatpickrInstance.isOpen) {
                flatpickrInstance.close();
            }
            
            // Enter to open
            if (e.key === 'Enter' && !flatpickrInstance.isOpen) {
                e.preventDefault();
                flatpickrInstance.open();
            }
        });
    }
    
    // Add ripple effect to input
    function addRippleEffect(input) {
        const ripple = document.createElement('span');
        const rect = input.getBoundingClientRect();
        const size = Math.max(rect.width, rect.height);
        
        ripple.style.width = ripple.style.height = size + 'px';
        ripple.style.left = '50%';
        ripple.style.top = '50%';
        ripple.style.transform = 'translate(-50%, -50%)';
        ripple.style.position = 'absolute';
        ripple.style.borderRadius = '50%';
        ripple.style.background = 'rgba(102, 126, 234, 0.3)';
        ripple.style.animation = 'ripple-animation 0.6s linear';
        ripple.style.pointerEvents = 'none';
        ripple.style.zIndex = '1';
        
        input.parentElement.style.position = 'relative';
        input.parentElement.appendChild(ripple);
        
        setTimeout(() => {
            if (ripple.parentElement) {
                ripple.parentElement.removeChild(ripple);
            }
        }, 600);
    }
    
    // Validate minimum date
    function validateMinDate(input, selectedDate) {
        const minDateStr = input.getAttribute('data-min-date');
        if (minDateStr && selectedDate) {
            const minDate = new Date(minDateStr);
            if (selectedDate < minDate) {
                showValidationError(input, 'Please select a date after ' + minDate.toDateString());
                return false;
            }
        }
        clearValidationError(input);
        return true;
    }
    
    // Validate date input
    function validateDateInput(input) {
        if (input.required && !input.value) {
            showValidationError(input, 'This field is required');
            return false;
        }
        
        clearValidationError(input);
        return true;
    }
    
    // Show validation error
    function showValidationError(input, message) {
        clearValidationError(input);
        
        input.classList.add('is-invalid');
        
        const errorDiv = document.createElement('div');
        errorDiv.className = 'invalid-feedback';
        errorDiv.textContent = message;
        errorDiv.style.display = 'block';
        errorDiv.style.color = '#dc3545';
        errorDiv.style.fontSize = '0.875em';
        errorDiv.style.marginTop = '0.25rem';
        
        input.parentElement.appendChild(errorDiv);
    }
    
    // Clear validation error
    function clearValidationError(input) {
        input.classList.remove('is-invalid');
        
        const errorDiv = input.parentElement.querySelector('.invalid-feedback');
        if (errorDiv) {
            errorDiv.parentElement.removeChild(errorDiv);
        }
    }
    
    // Initialize datepickers with enhanced features
    function initEnhancedDatepickers() {
        // Booking date range picker
        const checkInOut = document.querySelectorAll('.check-in-out-date');
        checkInOut.forEach(function(input) {
            if (!input.classList.contains('flatpickr-input')) {
                flatpickr(input, {
                    dateFormat: "Y-m-d",
                    altInput: true,
                    altFormat: "F j, Y",
                    minDate: new Date().fp_incr(1),
                    showMonths: window.innerWidth > 768 ? 2 : 1,
                    static: false,
                    monthSelectorType: "dropdown",
                    prevArrow: '<svg><path d="M10,5L5,10l5,5"/></svg>',
                    nextArrow: '<svg><path d="M5,5l5,5-5,5"/></svg>',
                });
            }
        });
        
        // Tour date picker with availability
        const tourDates = document.querySelectorAll('.tour-date-picker');
        tourDates.forEach(function(input) {
            if (!input.classList.contains('flatpickr-input')) {
                flatpickr(input, {
                    dateFormat: "Y-m-d",
                    altInput: true,
                    altFormat: "F j, Y",
                    minDate: new Date().fp_incr(1),
                    disable: [
                        function(date) {
                            // Disable dates that are fully booked (you can customize this)
                            const fullyBookedDates = ['2024-12-25', '2024-12-31']; // Example
                            const dateStr = date.getFullYear() + '-' + 
                                          String(date.getMonth() + 1).padStart(2, '0') + '-' + 
                                          String(date.getDate()).padStart(2, '0');
                            return fullyBookedDates.includes(dateStr);
                        }
                    ],
                    onDayCreate: function(dObj, dStr, fp, dayElem) {
                        // Add availability indicators
                        const date = dayElem.dateObj;
                        const today = new Date();
                        
                        if (date > today) {
                            // Add availability indicator (you can customize this based on actual data)
                            const indicator = document.createElement('span');
                            indicator.className = 'availability-indicator';
                            indicator.style.cssText = `
                                position: absolute;
                                bottom: 2px;
                                right: 2px;
                                width: 6px;
                                height: 6px;
                                background: #28a745;
                                border-radius: 50%;
                                z-index: 1;
                            `;
                            dayElem.appendChild(indicator);
                        }
                    }
                });
            }
        });
    }
    
    // Initialize everything
    try {
        // Check if Flatpickr is available
        if (typeof flatpickr !== 'undefined') {
            initializeDatepickers();
            initEnhancedDatepickers();
            
            console.log('✨ Attractive datepickers initialized successfully!');
        } else {
            console.warn('Flatpickr library not found. Please include Flatpickr CSS and JS files.');
        }
    } catch (error) {
        console.error('Error initializing datepickers:', error);
    }
    
    // Re-initialize datepickers when new content is added dynamically
    window.reinitializeDatepickers = function() {
        initializeDatepickers();
        initEnhancedDatepickers();
    };
    
    // Add CSS animations
    const style = document.createElement('style');
    style.textContent = `
        @keyframes ripple-animation {
            to {
                transform: translate(-50%, -50%) scale(2);
                opacity: 0;
            }
        }
        
        .datepicker-input.is-invalid {
            border-color: #dc3545 !important;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.15) !important;
        }
        
        .availability-indicator {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
    `;
    document.head.appendChild(style);
});

// Export functions for use in other scripts
window.DatepickerUtils = {
    // Programmatically set date
    setDate: function(inputSelector, date) {
        const input = document.querySelector(inputSelector);
        if (input && input._flatpickr) {
            input._flatpickr.setDate(date);
        }
    },
    
    // Get selected date
    getDate: function(inputSelector) {
        const input = document.querySelector(inputSelector);
        if (input && input._flatpickr) {
            return input._flatpickr.selectedDates;
        }
        return null;
    },
    
    // Clear date
    clearDate: function(inputSelector) {
        const input = document.querySelector(inputSelector);
        if (input && input._flatpickr) {
            input._flatpickr.clear();
        }
    },
    
    // Destroy datepicker
    destroy: function(inputSelector) {
        const input = document.querySelector(inputSelector);
        if (input && input._flatpickr) {
            input._flatpickr.destroy();
        }
    }
};

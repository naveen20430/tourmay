/**
 * Enhanced Datepicker with Floating Labels
 * Provides modern UX improvements for datepicker inputs
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize floating labels for all datepicker inputs
    initializeFloatingLabels();
    
    // Initialize enhanced datepickers
    initializeEnhancedDatepickers();
    
    // Add input event listeners for better UX
    addInputEventListeners();
});

/**
 * Initialize floating label behavior
 */
function initializeFloatingLabels() {
    const datepickers = document.querySelectorAll('.datepicker-input');
    
    datepickers.forEach(function(input) {
        const wrapper = input.closest('.datepicker-wrapper');
        if (!wrapper) return;
        
        // Add floating-label class to wrapper
        wrapper.classList.add('floating-label');
        
        // Move label after input for CSS sibling selector
        const label = wrapper.querySelector('.form-label');
        if (label && input.nextElementSibling !== label) {
            input.parentNode.insertBefore(label, input.nextElementSibling);
        }
        
        // Check initial value and set has-value class
        checkInputValue(input);
        
        // Add event listeners
        input.addEventListener('blur', function() {
            checkInputValue(this);
        });
        
        input.addEventListener('input', function() {
            checkInputValue(this);
        });
    });
}

/**
 * Check if input has value and toggle has-value class
 */
function checkInputValue(input) {
    if (input.value.trim() !== '') {
        input.classList.add('has-value');
    } else {
        input.classList.remove('has-value');
    }
}

/**
 * Initialize enhanced datepickers with improved options
 */
function initializeEnhancedDatepickers() {
    const datepickers = document.querySelectorAll('.datepicker-input');
    
    datepickers.forEach(function(input) {
        // Skip if already initialized
        if (input._flatpickr) return;
        
        const options = {
            dateFormat: input.dataset.dateFormat || 'Y-m-d',
            altInput: true,
            altFormat: input.dataset.altFormat || 'F j, Y',
            allowInput: true,
            clickOpens: true,
            
            // Enhanced animations
            animate: true,
            
            // Accessibility improvements
            ariaDateFormat: 'F j, Y',
            
            // Custom styling
            prevArrow: '<svg width="14" height="14" viewBox="0 0 14 14"><path d="M8.5 3.5L5 7l3.5 3.5" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            nextArrow: '<svg width="14" height="14" viewBox="0 0 14 14"><path d="M5.5 3.5L9 7l-3.5 3.5" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            
            // Event handlers
            onOpen: function(selectedDates, dateStr, instance) {
                addCalendarAnimations(instance);
                updateFloatingLabel(input);
            },
            
            onChange: function(selectedDates, dateStr, instance) {
                updateFloatingLabel(input);
                addSelectionAnimation(input);
            },
            
            onClose: function(selectedDates, dateStr, instance) {
                updateFloatingLabel(input);
            }
        };
        
        // Add date range options if specified
        if (input.dataset.minDate) {
            options.minDate = input.dataset.minDate;
        }
        
        if (input.dataset.maxDate) {
            options.maxDate = input.dataset.maxDate;
        }
        
        // Initialize Flatpickr
        flatpickr(input, options);
    });
}

/**
 * Update floating label state
 */
function updateFloatingLabel(input) {
    setTimeout(function() {
        checkInputValue(input);
    }, 10);
}

/**
 * Add calendar opening animations
 */
function addCalendarAnimations(instance) {
    const calendar = instance.calendarContainer;
    if (!calendar) return;
    
    calendar.style.opacity = '0';
    calendar.style.transform = 'translateY(-10px) scale(0.95)';
    
    setTimeout(function() {
        calendar.style.transition = 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)';
        calendar.style.opacity = '1';
        calendar.style.transform = 'translateY(0) scale(1)';
    }, 10);
}

/**
 * Add selection animation to input
 */
function addSelectionAnimation(input) {
    input.style.transform = 'scale(1.02)';
    input.style.transition = 'transform 0.2s ease';
    
    setTimeout(function() {
        input.style.transform = 'scale(1)';
    }, 200);
}

/**
 * Add additional input event listeners for enhanced UX
 */
function addInputEventListeners() {
    const datepickers = document.querySelectorAll('.datepicker-input');
    
    datepickers.forEach(function(input) {
        // Add focus ripple effect
        input.addEventListener('focus', function() {
            createRippleEffect(this);
        });
        
        // Add keyboard navigation hints
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && this._flatpickr) {
                e.preventDefault();
                this._flatpickr.open();
            }
        });
    });
}

/**
 * Create a subtle ripple effect on focus
 */
function createRippleEffect(input) {
    const wrapper = input.closest('.datepicker-wrapper');
    if (!wrapper) return;
    
    // Remove existing ripple
    const existingRipple = wrapper.querySelector('.focus-ripple');
    if (existingRipple) {
        existingRipple.remove();
    }
    
    // Create new ripple element
    const ripple = document.createElement('div');
    ripple.className = 'focus-ripple';
    ripple.style.cssText = `
        position: absolute;
        top: 50%;
        left: 20px;
        width: 4px;
        height: 4px;
        background: #1bbc9b;
        border-radius: 50%;
        transform: translate(-50%, -50%);
        animation: rippleGrow 0.6s ease-out;
        pointer-events: none;
        z-index: 1;
    `;
    
    wrapper.appendChild(ripple);
    
    // Remove ripple after animation
    setTimeout(function() {
        if (ripple.parentNode) {
            ripple.remove();
        }
    }, 600);
}

// Add ripple animation keyframes
const rippleStyles = document.createElement('style');
rippleStyles.textContent = `
    @keyframes rippleGrow {
        0% {
            transform: translate(-50%, -50%) scale(0);
            opacity: 1;
        }
        100% {
            transform: translate(-50%, -50%) scale(15);
            opacity: 0;
        }
    }
`;
document.head.appendChild(rippleStyles);

/**
 * Utility function to format date display
 */
function formatDateDisplay(date, format) {
    if (!date) return '';
    
    const months = [
        'January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December'
    ];
    
    const d = new Date(date);
    const day = d.getDate();
    const month = months[d.getMonth()];
    const year = d.getFullYear();
    
    return `${month} ${day}, ${year}`;
}

/**
 * Auto-initialize when DOM changes (for dynamic content)
 */
const observer = new MutationObserver(function(mutations) {
    mutations.forEach(function(mutation) {
        mutation.addedNodes.forEach(function(node) {
            if (node.nodeType === 1 && (node.matches('.datepicker-input') || node.querySelector('.datepicker-input'))) {
                setTimeout(function() {
                    initializeFloatingLabels();
                    initializeEnhancedDatepickers();
                    addInputEventListeners();
                }, 100);
            }
        });
    });
});

observer.observe(document.body, {
    childList: true,
    subtree: true
});
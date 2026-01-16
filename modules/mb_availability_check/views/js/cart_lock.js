/**
 * Lock quantity inputs on cart page for products added via availability check link
 */
(function() {
    'use strict';

    var lockedProductsCache = null;

    function getCookie(name) {
        var m = document.cookie.match('(?:^|;)\\s*' + name + '=([^;]+)');
        return m ? decodeURIComponent(m[1]) : null;
    }

    function getLockedProducts() {
        if (lockedProductsCache !== null) {
            return lockedProductsCache;
        }

        var lockedJson = getCookie('mb_locked_products');
        if (!lockedJson) {
            lockedProductsCache = {};
            return lockedProductsCache;
        }

        try {
            lockedProductsCache = JSON.parse(lockedJson);
        } catch (e) {
            console.error('mb_availability_check: Failed to parse locked products', e);
            lockedProductsCache = {};
        }

        return lockedProductsCache;
    }

    function lockCartQuantities() {
        var locked = getLockedProducts();
        
        if (!locked || Object.keys(locked).length === 0) {
            return;
        }

        console.log('mb_availability_check: Locking products', locked);

        Object.keys(locked).forEach(function(productId) {
            var lockedQty = locked[productId];

            // Find the specific input for this product using data-product-id
            var input = document.querySelector('input.js-cart-line-product-quantity[data-product-id="' + productId + '"]');
            
            if (!input) {
                // Fallback: try other selectors
                input = document.querySelector('input[name="product-quantity-spin"][data-product-id="' + productId + '"]');
            }

            if (!input) {
                console.warn('mb_availability_check: Could not find quantity input for product', productId);
                return;
            }

            console.log('mb_availability_check: Locking product', productId, 'at quantity', lockedQty);

            // Mark input as locked with a data attribute
            if (input.getAttribute('data-mb-locked') === 'true') {
                return; // Already locked
            }
            input.setAttribute('data-mb-locked', 'true');

            // Set the locked quantity
            input.value = lockedQty;

            // Disable the input completely
            input.disabled = true;
            input.readOnly = true;
            input.style.opacity = '0.5';
            input.style.cursor = 'not-allowed';
            input.style.backgroundColor = '#f0f0f0';

            // Find the input group (bootstrap-touchspin container)
            var inputGroup = input.closest('.input-group.bootstrap-touchspin');
            
            if (inputGroup) {
                // Find and disable the +/- buttons
                var upBtn = inputGroup.querySelector('.bootstrap-touchspin-up, .js-increase-product-quantity');
                var downBtn = inputGroup.querySelector('.bootstrap-touchspin-down, .js-decrease-product-quantity');

                if (upBtn) {
                    upBtn.disabled = true;
                    upBtn.style.opacity = '0.3';
                    upBtn.style.cursor = 'not-allowed';
                    upBtn.style.pointerEvents = 'none';
                    // Remove existing event listeners by cloning
                    var newUpBtn = upBtn.cloneNode(true);
                    upBtn.parentNode.replaceChild(newUpBtn, upBtn);
                }

                if (downBtn) {
                    downBtn.disabled = true;
                    downBtn.style.opacity = '0.3';
                    downBtn.style.cursor = 'not-allowed';
                    downBtn.style.pointerEvents = 'none';
                    // Remove existing event listeners by cloning
                    var newDownBtn = downBtn.cloneNode(true);
                    downBtn.parentNode.replaceChild(newDownBtn, downBtn);
                }
            }

            // Override all event handlers on the input
            var preventChange = function(e) {
                e.preventDefault();
                e.stopImmediatePropagation();
                e.stopPropagation();
                input.value = lockedQty;
                return false;
            };

            // Clone the input to remove all event listeners
            var newInput = input.cloneNode(true);
            newInput.value = lockedQty;
            newInput.disabled = true;
            newInput.readOnly = true;
            newInput.style.opacity = '0.5';
            newInput.style.cursor = 'not-allowed';
            newInput.style.backgroundColor = '#f0f0f0';
            newInput.setAttribute('data-mb-locked', 'true');
            
            input.parentNode.replaceChild(newInput, input);

            // Add event listeners to the new input
            newInput.addEventListener('change', preventChange, true);
            newInput.addEventListener('input', preventChange, true);
            newInput.addEventListener('keydown', preventChange, true);
            newInput.addEventListener('keyup', preventChange, true);
            newInput.addEventListener('click', preventChange, true);
        });
    }

    // Run immediately
    lockCartQuantities();

    // Run after DOM is fully loaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            lockCartQuantities();
        });
    } else {
        setTimeout(lockCartQuantities, 100);
    }

    // Run after delays to catch AJAX-loaded content
    setTimeout(lockCartQuantities, 500);
    setTimeout(lockCartQuantities, 1000);
    setTimeout(lockCartQuantities, 2000);

    // Watch for AJAX updates to cart
    var observerRunning = false;
    var observer = new MutationObserver(function(mutations) {
        if (observerRunning) return;
        observerRunning = true;
        
        setTimeout(function() {
            lockCartQuantities();
            observerRunning = false;
        }, 100);
    });

    // Start observing cart changes
    setTimeout(function() {
        var cartOverview = document.querySelector('.cart-overview.js-cart, .cart-grid');
        if (cartOverview) {
            observer.observe(cartOverview, {
                childList: true,
                subtree: true,
                attributes: true,
                attributeFilter: ['value']
            });
        }
    }, 500);

    // Hook into prestashop events if available
    if (typeof prestashop !== 'undefined') {
        prestashop.on('updatedCart', function() {
            setTimeout(lockCartQuantities, 200);
        });
    }

    // Also intercept AJAX responses
    if (typeof $ !== 'undefined') {
        $(document).ajaxComplete(function() {
            setTimeout(lockCartQuantities, 200);
        });
    }
})();

// Helper function to create modal HTML dynamically
function createAvailabilityModal(productId, productName) {
    // Build the send URL (same pattern as backend)
    var sendUrl = '';
    
    // Try to get base URL from prestashop object
    if (typeof prestashop !== 'undefined' && prestashop.urls && prestashop.urls.base_url) {
        sendUrl = prestashop.urls.base_url;
    } else if (typeof baseDir !== 'undefined') {
        sendUrl = baseDir;
    } else {
        // Fallback: construct from current location
        sendUrl = window.location.origin + window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/'));
    }
    
    sendUrl = sendUrl.replace(/\/$/, '') + '/module/mb_availability_check/send';
    
    var modalHtml = '<div id="mb-availability-modal" class="mb-availability-modal">' +
        '<div class="mb-availability-modal-backdrop" data-mb-availability-close="1"></div>' +
        '<div class="mb-availability-modal-content">' +
        '<button class="mb-availability-modal-close" data-mb-availability-close="1">&times;</button>' +
        '<h3>Vérifier la disponibilité</h3>' +
        '<p>Le propriétaire vous contactera prochainement.</p>' +
        '<form method="post" action="' + sendUrl + '">' +
        '<input type="hidden" name="id_product" value="' + productId + '" />' +
        '<input type="hidden" name="qty" id="mb_quantity" value="1" />' +
        '<div class="form-group" id="mb_product_group">' +
        '<label>Produit</label>' +
        '<input type="text" class="form-control" value="' + (productName || '') + '" readonly="readonly" />' +
        '</div>' +
        '<div class="form-group">' +
        '<label for="mb_quantity_visible">Quantité souhaitée</label>' +
        '<input id="mb_quantity_visible" type="number" min="1" name="mb_quantity_visible" class="form-control" value="1" required />' +
        '</div>' +
        '<div class="form-group">' +
        '<label for="mb_firstname">Prénom</label>' +
        '<input id="mb_firstname" name="firstname" class="form-control" />' +
        '</div>' +
        '<div class="form-group">' +
        '<label for="mb_lastname">Nom</label>' +
        '<input id="mb_lastname" name="lastname" class="form-control" />' +
        '</div>' +
        '<div class="form-group">' +
        '<label for="mb_email">Email</label>' +
        '<input id="mb_email" type="email" name="email" class="form-control" />' +
        '</div>' +
        '<div class="form-group">' +
        '<label for="mb_phone">Téléphone</label>' +
        '<input id="mb_phone" type="text" name="phone" class="form-control" />' +
        '</div>' +
        '<button type="submit" class="btn btn-primary">Envoyer la demande</button>' +
        '</form>' +
        '</div>' +
        '</div>' +
        '<style>' +
        '#mb-availability-modal{display:none;position:fixed !important;z-index:999999 !important;top:0 !important;left:0 !important;width:100% !important;height:100% !important;}' +
        '#mb-availability-modal.mb-availability-open{display:block !important; position: fixed !important; z-index: 999999 !important; top: 0 !important; left: 0 !important; width: 100% !important; height: 100% !important;}' +
        '.mb-availability-modal-backdrop{position:fixed !important;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:999998;}' +
        '.mb-availability-modal-content{position:fixed !important;max-width:500px;top:50%;left:50%;transform:translate(-50%,-50%);padding:20px;background:#fff;max-height:80vh;overflow-y:auto;z-index:999999;border-radius:4px;box-shadow:0 5px 15px rgba(0,0,0,0.5);}' +
        '.mb-availability-modal-close{position:absolute;top:5px;right:10px;border:0;background:transparent;font-size:24px;cursor:pointer;z-index:1000000;line-height:1;padding:5px 10px;}' +
        '.mb-availability-modal .form-group{margin-bottom:15px;}' +
        '.mb-availability-modal .form-group label{display:block;margin-bottom:5px;font-weight:600;}' +
        '.mb-availability-modal .form-control{width:100%;padding:8px 12px;border:1px solid #ddd;border-radius:4px;box-sizing:border-box;}' +
        '.mb-availability-modal h3{margin-top:0;margin-bottom:15px;}' +
        '</style>';
    
    var wrapper = document.createElement('div');
    wrapper.innerHTML = modalHtml;
    var modalEl = wrapper.firstElementChild;

    // Ensure modal is appended as the last child of body (avoid being inside quickview)
    document.body.appendChild(modalEl);

    // Force fixed positioning and high z-index via inline styles to avoid theme overrides
    try {
        modalEl.style.cssText = 'display:none;position:fixed !important;z-index:9999999 !important;top:0 !important;left:0 !important;width:100% !important;height:100% !important;';
        var backdrop = modalEl.querySelector('.mb-availability-modal-backdrop');
        if (backdrop) backdrop.style.cssText = 'position:fixed !important;top:0 !important;left:0 !important;width:100% !important;height:100% !important;background:rgba(0,0,0,0.5);z-index:9999998 !important;pointer-events:auto !important;';
        var content = modalEl.querySelector('.mb-availability-modal-content');
        if (content) content.style.cssText = 'position:fixed !important;max-width:500px;top:50% !important;left:50% !important;transform:translate(-50%,-50%) !important;padding:20px;background:#fff;max-height:80vh;overflow-y:auto;z-index:9999999 !important;border-radius:4px;box-shadow:0 5px 15px rgba(0,0,0,0.5);pointer-events:auto !important;';
    } catch (err) {
        // ignore styling errors
    }

    return document.getElementById('mb-availability-modal');
}

// Main initialization function that can be called for dynamic content
function initMbAvailability() {
    var btn = document.querySelector('[data-mb-availability-btn]');
    
    if (!btn) {
        console.log('mb_availability_check: No button found, skipping initialization');
        return;
    }
    
    console.log('mb_availability_check: Initializing, button found:', btn);
    
    // Get or create the modal
    var modal = document.getElementById('mb-availability-modal');
    if (!modal) {
        // Try to find product data from quick view
        var productId = null;
        var productName = '';
        
        // Try to get from quick view modal
        var qvModal = btn.closest('.modal-content') || btn.closest('.quickview');
        if (qvModal) {
            var hiddenInput = qvModal.querySelector('input[name="id_product"]');
            if (hiddenInput) {
                productId = hiddenInput.value;
            }
            var h1 = qvModal.querySelector('h1.h1');
            if (h1) {
                productName = h1.textContent.trim();
            }
        }
        
        // Fallback to page product
        if (!productId) {
            var pageInput = document.querySelector('input[name="id_product"]') || document.querySelector('#product_page_product_id');
            if (pageInput) {
                productId = pageInput.value;
            }
        }
        
        if (!productId) {
            console.warn('mb_availability_check: Cannot find product ID for modal');
            return;
        }
        
        // Create modal dynamically
        console.log('mb_availability_check: Creating modal for product', productId, productName);
        modal = createAvailabilityModal(productId, productName);
        if (!modal) {
            console.error('mb_availability_check: Failed to create modal');
            return;
        }
        console.log('mb_availability_check: Modal created successfully');
    }
    
    if (!modal) {
        console.error('mb_availability_check: Modal is null after initialization');
        return;
    }

    // Store the configured button label for use in fallbacks
    var configuredButtonLabel = btn.textContent.trim() || 'Vérifier la disponibilité';

    // Check if we're in a quick view context
    var quickViewModal = btn.closest('.quickview') || btn.closest('[id^="quickview-modal"]');
    var isQuickView = quickViewModal !== null;
    
    if (isQuickView) {
        // In quick view: hide add to cart button and remove duplicate availability buttons
        var qvContainer = quickViewModal;
        
        // Hide add to cart button and quantity controls
        var addToCartBtns = qvContainer.querySelectorAll('button.add-to-cart, button[name="submit-add-to-cart"]');
        addToCartBtns.forEach(function (b) { 
            b.style.display = 'none !important';
            b.disabled = true;
            b.setAttribute('disabled', 'disabled');
        });
        
        // Also hide the entire quantity div if needed
        var qtyDiv = qvContainer.querySelector('.product-quantity.clearfix');
        if (qtyDiv) {
            qtyDiv.style.display = 'none';
        }
        
        // Find all availability buttons in quick view
        var allBtns = qvContainer.querySelectorAll('[data-mb-availability-btn]');
        
        // Keep only the one in modal-footer (product-additional-info), remove others
        var keepBtn = qvContainer.querySelector('.modal-footer [data-mb-availability-btn]') ||
                      qvContainer.querySelector('.product-additional-info [data-mb-availability-btn]');
        
        if (keepBtn) {
            // Remove all except the one we want to keep
            allBtns.forEach(function (b) {
                if (b !== keepBtn) {
                    b.parentNode && b.parentNode.removeChild(b);
                }
            });
            btn = keepBtn;
        } else {
            // If no button in footer, keep the first one and remove others
            allBtns.forEach(function (b, index) {
                if (index > 0) {
                    b.parentNode && b.parentNode.removeChild(b);
                }
            });
        }
    } else {
        // Not in quick view: original behavior for product page
        // Replace the product-quantity block with our availability button
        var productQtyContainer = document.querySelector('.product-quantity.clearfix');
        if (productQtyContainer) {
            // Remove existing children (quantity + add button)
            productQtyContainer.innerHTML = '';

            // Create wrapper and insert our module button into the same position
            var wrapper = document.createElement('div');
            wrapper.className = 'mb-availability-replacer';

            var newBtn = document.createElement('a');
            newBtn.href = '#';
            newBtn.className = 'btn btn-primary mb-availability-btn mb-availability-btn-custom';
            newBtn.setAttribute('data-mb-availability-btn', '1');
            
            // Get the configured button label from the existing button or use default
            var configuredLabel = configuredButtonLabel;
            var existingBtn = document.querySelector('[data-mb-availability-btn]');
            if (existingBtn && existingBtn.textContent.trim()) {
                configuredLabel = existingBtn.textContent.trim();
            }
            
            newBtn.textContent = configuredLabel;

            wrapper.appendChild(newBtn);
            productQtyContainer.appendChild(wrapper);

            // remove any add-to-cart inside the product container if present
            var addToCartBtns = document.querySelectorAll('button.add-to-cart, button[name="submit-add-to-cart"]');
            addToCartBtns.forEach(function (b) { b.style.display = 'none'; });

            // Remove any other module buttons to avoid duplicates
            var existingModuleBtns = document.querySelectorAll('[data-mb-availability-btn]');
            existingModuleBtns.forEach(function (el) {
                if (el !== newBtn) el.parentNode && el.parentNode.removeChild(el);
            });

            // Use the newly created button as our active button
            btn = newBtn;
        } else {
            // Fallback: hide default Add to cart button if we can't replace block
            var addToCartBtn = document.querySelector('button.add-to-cart, button[name="submit-add-to-cart"]');
            if (addToCartBtn) addToCartBtn.style.display = 'none';
        }
    }

    // Remove any existing click listeners by cloning the button (removes all event listeners)
    var cleanBtn = btn.cloneNode(true);
    btn.parentNode.replaceChild(cleanBtn, btn);
    btn = cleanBtn;
    
    btn.addEventListener('click', function (e) {
        e.preventDefault();
        console.log('mb_availability_check: Button clicked, opening modal');

        // Determine product id/name relative to the button that was clicked
        var opener = e.currentTarget || this || btn;
        var openerContainer = opener.closest && (opener.closest('.modal-content') || opener.closest('.quickview') || opener.closest('.product-container'));
        if (!openerContainer) openerContainer = document;

        var localProductId = null;
        var localProductName = '';
        var idInput = openerContainer.querySelector && (openerContainer.querySelector('input[name="id_product"]') || document.querySelector('input[name="id_product"]') || document.querySelector('#product_page_product_id'));
        if (idInput) localProductId = idInput.value;

        var titleEl = openerContainer.querySelector && (openerContainer.querySelector('h1.h1') || openerContainer.querySelector('h1') || openerContainer.querySelector('.product-title') || openerContainer.querySelector('.product-name'));
        if (titleEl) localProductName = titleEl.textContent.trim();

        // Fall back to data attributes on the button if present
        if ((!localProductId || localProductId === '') && opener.dataset) {
            localProductId = opener.dataset.idProduct || opener.dataset.productId || localProductId;
        }
        if ((!localProductName || localProductName === '') && opener.dataset) {
            localProductName = opener.dataset.productName || localProductName;
        }

        // Update modal form fields (create if missing)
        try {
            var formForModal = modal.querySelector('form');
            if (formForModal) {
                var prodFieldEl = formForModal.querySelector('[name="id_product"]');
                if (!prodFieldEl) {
                    prodFieldEl = document.createElement('input');
                    prodFieldEl.type = 'hidden';
                    prodFieldEl.name = 'id_product';
                    formForModal.appendChild(prodFieldEl);
                }
                // Assign product value whether it's a select or hidden input
                if (localProductId) {
                    if (prodFieldEl.tagName && prodFieldEl.tagName.toLowerCase() === 'select') {
                        try { prodFieldEl.value = localProductId; } catch (err) {}
                    } else {
                        prodFieldEl.value = localProductId;
                    }
                }

                var prodDisplay = formForModal.querySelector('input[readonly]');
                if (!prodDisplay) prodDisplay = formForModal.querySelector('input[type="text"]');
                if (prodDisplay) prodDisplay.value = localProductName || prodDisplay.value || '';

                // If availabilityProducts is exposed to JS, render a select for product selection
                if (window && window.availabilityProducts && Array.isArray(window.availabilityProducts) && window.availabilityProducts.length > 0) {
                    var productGroup = formForModal.querySelector('#mb_product_group');
                    if (productGroup) {
                        // build select HTML
                        var sel = document.createElement('select');
                        sel.name = 'id_product';
                        sel.id = 'mb_product_select';
                        sel.className = 'form-control';
                        window.availabilityProducts.forEach(function (p) {
                            var opt = document.createElement('option');
                            opt.value = p.id;
                            opt.textContent = p.name;
                            if (String(p.id) === String(localProductId)) opt.selected = true;
                            sel.appendChild(opt);
                        });
                        // Clear existing children and append select
                        productGroup.innerHTML = '';
                        var lbl = document.createElement('label');
                        lbl.textContent = 'Produit';
                        productGroup.appendChild(lbl);
                        productGroup.appendChild(sel);
                        // Ensure the page's hidden id_product (if any) is removed to avoid duplicates
                        var hiddenId = formForModal.querySelector('input[name="id_product"][type="hidden"]');
                        if (hiddenId) hiddenId.parentNode.removeChild(hiddenId);
                    }
                }
            }
        } catch (err) {
            // ignore
        }

        // If we have a visible qty field inside modal, set it to 1 by default
        var visibleQty = modal.querySelector('#mb_quantity_visible');
        if (visibleQty) {
            // Try to preserve value from original page qty if present
            var pageQtyInput = document.querySelector('#quantity_wanted') || document.querySelector('input[name="qty"]') || document.querySelector('input.qty');
            var initial = 1;
            if (pageQtyInput) {
                var v = parseInt(pageQtyInput.value, 10);
                if (!isNaN(v) && v > 0) initial = v;
            }
            visibleQty.value = initial;
        }

        // ensure hidden qty exists and sync value from visible
        var mbQty = modal.querySelector('#mb_quantity');
        if (!mbQty) {
            mbQty = document.createElement('input');
            mbQty.type = 'hidden';
            mbQty.name = 'qty';
            mbQty.id = 'mb_quantity';
            var form = modal.querySelector('form');
            if (form) form.appendChild(mbQty);
        }
        // copy visible to hidden
        var vis = modal.querySelector('#mb_quantity_visible');
        mbQty.value = (vis && vis.value) ? vis.value : '1';

        // Sync visible qty changes to hidden input while modal is open
        if (vis) {
            vis.addEventListener('input', function () {
                mbQty.value = vis.value;
            });
        }

        // Debug: log form action and method
        var form = modal.querySelector('form');
        if (form) {
            try { console.info('mb_availability_check: opening modal, form action=', form.action, 'method=', form.method); } catch (ex) {}
        }

        // remember which button opened this modal so submit can target it
        try { modal._mbActiveBtn = btn; } catch (e) {}

        // If a quickview modal exists, hide it (display:none) and disable pointer events while our modal is open
        var _quickviewContainer = document.querySelector('.quickview, [id^="quickview-modal"]');
        if (_quickviewContainer) {
            try {
                // save previous inline values so we can restore them later
                _quickviewContainer._mbPrevPointerEvents = _quickviewContainer.style.pointerEvents || '';
                _quickviewContainer._mbPrevDisplay = _quickviewContainer.style.display || '';
                _quickviewContainer.style.pointerEvents = 'none';
                _quickviewContainer.style.display = 'none';
            } catch (err) {}
        }

        console.log('mb_availability_check: Adding mb-availability-open class to modal');
        // Ensure modal is attached to body (avoid being inside quickview DOM)
        try { document.body.appendChild(modal); } catch (e) {}

        // Force display and positioning inline to avoid theme overrides
        try {
            modal.style.cssText = 'display:block;position:fixed !important;z-index:9999999 !important;top:0 !important;left:0 !important;width:100% !important;height:100% !important;';
            var contentEl = modal.querySelector('.mb-availability-modal-content');
            if (contentEl) contentEl.style.cssText = 'position:fixed !important;max-width:500px;top:50% !important;left:50% !important;transform:translate(-50%,-50%) !important;padding:20px;background:#fff;max-height:80vh;overflow-y:auto;z-index:9999999 !important;border-radius:4px;box-shadow:0 5px 15px rgba(0,0,0,0.5);pointer-events:auto !important;';
            var backdropEl = modal.querySelector('.mb-availability-modal-backdrop');
            if (backdropEl) backdropEl.style.cssText = 'position:fixed !important;top:0 !important;left:0 !important;width:100% !important;height:100% !important;background:rgba(0,0,0,0.5);z-index:9999998 !important;pointer-events:auto !important;';
            // focus first input inside modal for keyboard and to ensure clicks register
            try {
                var firstInput = modal.querySelector('input:not([type="hidden"]), textarea, select');
                if (firstInput) {
                    firstInput.focus();
                } else if (contentEl) {
                    // make content focusable
                    contentEl.setAttribute('tabindex', '-1');
                    contentEl.focus();
                }
            } catch (err) {}
        } catch (err) {}

        modal.classList.add('mb-availability-open');

        // Verify modal is visible
        if (modal.classList.contains('mb-availability-open') && modal.style.display !== 'none') {
            console.log('mb_availability_check: Modal should now be visible');
        }
    });

    // Bind close, ESC and submit handlers only once to avoid duplicate registrations
    if (!modal._mbHandlersBound) {
        var closeBtns = modal.querySelectorAll('[data-mb-availability-close]');
        closeBtns.forEach(function (el) {
            el.addEventListener('click', function (e) {
                e.preventDefault();
                // remove open class and hide modal (inline styles may force display)
                try { modal.classList.remove('mb-availability-open'); } catch (err) {}
                try { modal.style.display = 'none'; } catch (err) {}
                try {
                    var contentEl = modal.querySelector('.mb-availability-modal-content');
                    if (contentEl) contentEl.style.display = 'none';
                    var backdropEl = modal.querySelector('.mb-availability-modal-backdrop');
                    if (backdropEl) backdropEl.style.display = 'none';
                } catch (err) {}
                // restore quickview display and pointer-events if previously disabled
                try {
                    var qv = document.querySelector('.quickview, [id^="quickview-modal"]');
                    if (qv) {
                        if (typeof qv._mbPrevDisplay !== 'undefined') qv.style.display = qv._mbPrevDisplay;
                        if (typeof qv._mbPrevPointerEvents !== 'undefined') qv.style.pointerEvents = qv._mbPrevPointerEvents;
                        try { delete qv._mbPrevDisplay; } catch (e) {}
                        try { delete qv._mbPrevPointerEvents; } catch (e) {}
                    }
                } catch (err) {}
            });
        });

        // Close modal on ESC key (bound once)
        modal._mbEscHandler = function (e) {
            if (!modal) return;
            var code = e.which || e.keyCode;
            if (code === 27) { // ESC
                try { modal.classList.remove('mb-availability-open'); } catch (err) {}
                try { modal.style.display = 'none'; } catch (err) {}
                try {
                    var contentEl = modal.querySelector('.mb-availability-modal-content');
                    if (contentEl) contentEl.style.display = 'none';
                    var backdropEl = modal.querySelector('.mb-availability-modal-backdrop');
                    if (backdropEl) backdropEl.style.display = 'none';
                } catch (err) {}
                // restore quickview display and pointer-events if previously disabled
                try {
                    var qv = document.querySelector('.quickview, [id^="quickview-modal"]');
                    if (qv) {
                        if (typeof qv._mbPrevDisplay !== 'undefined') qv.style.display = qv._mbPrevDisplay;
                        if (typeof qv._mbPrevPointerEvents !== 'undefined') qv.style.pointerEvents = qv._mbPrevPointerEvents;
                        try { delete qv._mbPrevDisplay; } catch (e) {}
                        try { delete qv._mbPrevPointerEvents; } catch (e) {}
                    }
                } catch (err) {}
            }
        };
        document.addEventListener('keydown', modal._mbEscHandler);

        // Simple AJAX submit with custom popup and button disable
        var form = modal.querySelector('form');
        if (form && !form._mbSubmitBound) {
            // helper: create a small toast popup
            function showPopup(message, success) {
                var existing = document.querySelector('.mb-availability-toast');
                if (existing) existing.parentNode.removeChild(existing);

                var toast = document.createElement('div');
                toast.className = 'mb-availability-toast ' + (success ? 'success' : 'error');
                toast.innerHTML = '<div class="mb-toast-inner">' +
                    '<span class="mb-toast-close" title="Close">&times;</span>' +
                    '<div class="mb-toast-message">' + (message || '') + '</div>' +
                    '</div>';
                document.body.appendChild(toast);

                // close handler
                toast.querySelector('.mb-toast-close').addEventListener('click', function () {
                    if (toast.parentNode) toast.parentNode.removeChild(toast);
                });

                // auto-hide
                setTimeout(function () {
                    if (toast.parentNode) toast.parentNode.removeChild(toast);
                }, 4000);
            }

            // add basic styles for toast (scoped) if not present
            if (!document.getElementById('mb-availability-styles')) {
                var style = document.createElement('style');
                style.id = 'mb-availability-styles';
                style.innerHTML = '\n.mb-availability-toast{position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);z-index:10001;background:#fff;border-radius:6px;box-shadow:0 6px 24px rgba(0,0,0,.2);padding:14px 18px;min-width:320px;max-width:90%;font-family:Arial,sans-serif;text-align:center;}\n.mb-availability-toast.success{border-left:6px solid #28a745;}\n.mb-availability-toast.error{border-left:6px solid #dc3545;}\n.mb-availability-toast .mb-toast-close{position:absolute;top:6px;right:8px;cursor:pointer;font-size:16px;color:#666;}\n.mb-availability-toast .mb-toast-message{padding-right:18px;color:#222;}\n.mb-availability-btn.disabled{opacity:0.6;pointer-events:none;cursor:default;}\n';
                document.head.appendChild(style);
            }

            form.addEventListener('submit', function (e) {
                e.preventDefault();

                // Use the modal's submit button for loading/success UI (safer than mutating opener buttons)
                var submitBtn = form.querySelector('button[type="submit"]');
                var originalSubmitLabel = '';
                if (submitBtn) {
                    originalSubmitLabel = submitBtn.innerHTML;
                    try { submitBtn.innerHTML = '<span class="mb-btn-loading">Vérification...</span>'; } catch (e) { submitBtn.textContent = 'Vérification...'; }
                    submitBtn.setAttribute('aria-disabled', 'true');
                    submitBtn.classList.add('disabled');
                    try { submitBtn.disabled = true; } catch (ex) {}
                }

                var formData = new FormData(form);

                // Helper to safely restore a button's visual state
                function _restoreButtonState(btn, label) {
                    if (!btn) return;
                    try { btn.innerHTML = label || configuredButtonLabel; } catch (e) { btn.textContent = label || configuredButtonLabel; }
                    try { btn.removeAttribute('aria-disabled'); } catch (e) {}
                    try { btn.classList.remove('disabled'); } catch (e) {}
                    try { btn.disabled = false; } catch (e) {}
                }

                fetch(form.action, {
                    method: 'POST',
                    body: formData,
                })
                    .then(function (response) {
                        return response.text().then(function (text) {
                            var data = null;
                            try {
                                data = JSON.parse(text);
                            } catch (err) {
                                // not JSON
                            }
                            return { ok: response.ok, data: data, text: text, status: response.status };
                        });
                    })
                    .then(function (res) {
                        // If server responded OK and includes a success flag, show a success label briefly
                        if (res.data && res.data.success) {
                            // show success toast
                            showPopup(res.data.message || 'Votre demande a été enregistrée.', true);

                            // show transient success state on submit button, then restore
                            if (submitBtn) {
                                try { submitBtn.innerHTML = '<span class="mb-btn-success">Envoyé</span>'; } catch (e) { submitBtn.textContent = 'Envoyé'; }
                                setTimeout(function () { _restoreButtonState(submitBtn, originalSubmitLabel); }, 1500);
                            }

                            // restore quickview interactions if we disabled them
                            try {
                                var qv = document.querySelector('.quickview, [id^="quickview-modal"]');
                                if (qv) {
                                    if (typeof qv._mbPrevDisplay !== 'undefined') qv.style.display = qv._mbPrevDisplay;
                                    if (typeof qv._mbPrevPointerEvents !== 'undefined') qv.style.pointerEvents = qv._mbPrevPointerEvents;
                                    try { delete qv._mbPrevDisplay; } catch (e) {}
                                    try { delete qv._mbPrevPointerEvents; } catch (e) {}
                                }
                            } catch (err) {}

                            modal.classList.remove('mb-availability-open');
                            try { modal.style.display = 'none'; } catch (e) {}
                            form.reset();
                            return;
                        }

                        // On non-success responses, restore submit button and show error message
                        _restoreButtonState(submitBtn, originalSubmitLabel);

                        if (res.data && res.data.message) {
                            showPopup(res.data.message, false);
                        } else if (!res.ok && res.text) {
                            console.error('Server error (status ' + res.status + '):', res.text);
                            showPopup(res.text || 'Une erreur est survenue.', false);
                        } else {
                            showPopup('Une erreur est survenue.', false);
                        }
                    })
                    .catch(function (err) {
                        console.error('Fetch error:', err);
                        _restoreButtonState(submitBtn, originalSubmitLabel);
                        showPopup('Une erreur est survenue.', false);
                    });
            });

            form._mbSubmitBound = true;
        }

        modal._mbHandlersBound = true;
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function () {
    initMbAvailability();
});

// Re-initialize when PrestaShop updates product content (quick view, etc.)
document.addEventListener('updatedProduct', function() {
    setTimeout(initMbAvailability, 100);
});

// Also listen for PrestaShop's product list updates (for quick view modal)
if (typeof prestashop !== 'undefined') {
    prestashop.on('updatedProduct', function() {
        setTimeout(initMbAvailability, 100);
    });
}

// Listen for Bootstrap modal shown event (for quick view)
document.addEventListener('shown.bs.modal', function(e) {
    var modal = e.target;
    if (modal && (modal.classList.contains('quickview') || modal.id.indexOf('quickview-modal') === 0)) {
        setTimeout(initMbAvailability, 200);
    }
});

// MutationObserver to catch dynamically added quick view modals
var observer = new MutationObserver(function(mutations) {
    mutations.forEach(function(mutation) {
        mutation.addedNodes.forEach(function(node) {
            if (node.nodeType === 1) { // Element node
                if (node.classList && (node.classList.contains('quickview') || (node.id && node.id.indexOf('quickview-modal') === 0))) {
                    setTimeout(initMbAvailability, 200);
                } else if (node.querySelector) {
                    var quickview = node.querySelector('.quickview, [id^="quickview-modal"]');
                    if (quickview) {
                        setTimeout(initMbAvailability, 200);
                    }
                }
            }
        });
    });
});

// Start observing
if (document.body) {
    observer.observe(document.body, { childList: true, subtree: true });
} else {
    document.addEventListener('DOMContentLoaded', function() {
        observer.observe(document.body, { childList: true, subtree: true });
    });
}

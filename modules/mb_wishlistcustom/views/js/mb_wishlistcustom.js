/**
 * blockwishlist renders its modals wherever they live in the template (e.g. the
 * footer), which traps them in a low z-index stacking context below the sticky
 * header/megamenu. This relocates them to <body> so they always render on top.
 */
(function () {
    var WISHLIST_MODAL_WRAPPERS_SELECTOR = '.wishlist-add-to, .wishlist-delete, .wishlist-create, .wishlist-login';

    function moveWishlistModalsToBody() {
        document.querySelectorAll(WISHLIST_MODAL_WRAPPERS_SELECTOR).forEach(function (wrapper) {
            if (wrapper.parentElement !== document.body) {
                document.body.appendChild(wrapper);
            }
        });
    }

    function init() {
        moveWishlistModalsToBody();
        var observer = new MutationObserver(moveWishlistModalsToBody);
        observer.observe(document.body, { childList: true, subtree: true });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();

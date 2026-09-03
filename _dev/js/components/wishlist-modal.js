/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

// blockwishlist renders its modals inside the footer, which traps them in a low
// z-index stacking context. Relocate them to <body> so they always render above
// the rest of the page (they're present in the initial HTML, not injected later).
const WISHLIST_MODAL_WRAPPERS_SELECTOR = '.wishlist-add-to, .wishlist-delete, .wishlist-create, .wishlist-login';

const moveWishlistModalsToBody = () => {
  document.querySelectorAll(WISHLIST_MODAL_WRAPPERS_SELECTOR).forEach((wrapper) => {
    if (wrapper.parentElement !== document.body) {
      document.body.appendChild(wrapper);
    }
  });
};

const initWishlistModalFix = () => {
  moveWishlistModalsToBody();

  const observer = new MutationObserver(moveWishlistModalsToBody);
  observer.observe(document.body, { childList: true, subtree: true });
};

export default initWishlistModalFix;

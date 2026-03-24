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
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */
import $ from 'jquery';
import prestashop from 'prestashop';

const DELIVERY_STORAGE_KEY = 'fp_selected_delivery_option';
const DELIVERY_SELECTED_BY_USER_KEY = 'fp_delivery_selected_by_user';

function getDeliverySelectionInput($form) {
  return $form.find('input[type="radio"][name^="delivery_option"]');
}

function getCheckedDeliveryOption($form) {
  return getDeliverySelectionInput($form).filter(':checked');
}

function toggleDeliveryFormState($form) {
  const $submitButton = $form.find('button[name="confirmDeliveryOption"]');
  const hasSelectedCarrier = getCheckedDeliveryOption($form).length > 0;

  $submitButton.prop('disabled', !hasSelectedCarrier);
}

function restoreDeliverySelection() {
  const $form = $('#js-delivery');
  if (!$form.length) {
    return;
  }

  const $options = getDeliverySelectionInput($form);
  if (!$options.length) {
    return;
  }

  const selectedByUser = globalThis.sessionStorage.getItem(DELIVERY_SELECTED_BY_USER_KEY) === '1';
  const savedValue = globalThis.sessionStorage.getItem(DELIVERY_STORAGE_KEY);
  const selectedValue = selectedByUser ? savedValue : null;

  if (selectedValue) {
    const selector = `input[type="radio"][name^="delivery_option"][value="${selectedValue}"]`;
    const $savedOption = $form.find(selector);

    if ($savedOption.length) {
      $savedOption.prop('checked', true);
    }
  }

  toggleDeliveryFormState($form);
}

function setUpCheckout() {
  $(document)
    .off('click.fpTermsLink', prestashop.themeSelectors.checkout.termsLink)
    .on('click.fpTermsLink', prestashop.themeSelectors.checkout.termsLink, (event) => {
    event.preventDefault();
    event.stopPropagation();
    let url = $(event.currentTarget).attr('href');

    if (url) {
      try {
        const termsUrl = new URL(url, globalThis.location.origin);
        termsUrl.searchParams.set('content_only', '1');
        url = termsUrl.toString();
      } catch {
        const separator = url.includes('?') ? '&' : '?';
        url = `${url}${separator}content_only=1`;
      }

      $.get(url, (content) => {
        $(prestashop.themeSelectors.modal)
          .find(prestashop.themeSelectors.modalContent)
          .html($(content).find('.page-cms').contents());
      }).fail((resp) => {
        prestashop.emit('handleError', {eventType: 'clickTerms', resp});
      });
    }

    $(prestashop.themeSelectors.modal).modal('show');
  });

  $(prestashop.themeSelectors.checkout.giftCheckbox).on('click', () => {
    $('#gift').slideToggle();
  });
}

function toggleImage() {
  // Arrow show/hide details Checkout page
  $(prestashop.themeSelectors.checkout.imagesLink).on('click', function () {
    const icon = $(this).find('i.material-icons');

    if (icon.text() === 'expand_more') {
      icon.text('expand_less');
    } else {
      icon.text('expand_more');
    }
  });
}

/**
 * Force l'affichage du sous-total livraison si un transporteur est sélectionné
 * et qu'un montant existe.
 */
function syncShippingSubtotalVisibility() {
  if ($('body#checkout').length === 0) {
    return;
  }

  const $shippingSubtotal = $('#cart-subtotal-shipping');

  if (!$shippingSubtotal.length) {
    return;
  }

  const shippingValue = $shippingSubtotal.find('.value').text().trim();
  const hasShippingValue = shippingValue !== '' && shippingValue !== '-';

  if (hasShippingValue) {
    $shippingSubtotal.css('display', 'block');
    $shippingSubtotal.attr('style', ($shippingSubtotal.attr('style') || '').replaceAll(/display\s*:\s*none;?/gi, ''));
    $shippingSubtotal.show();
  } else {
    $shippingSubtotal.hide();
  }
}
$(document).ready(() => {
  if ($('body#checkout').length === 1) {
    setUpCheckout();
    toggleImage();
    restoreDeliverySelection();
    syncShippingSubtotalVisibility();
  }

  $(document)
    .off('change.fpDelivery', '#js-delivery input[type="radio"][name^="delivery_option"]')
    .on('change.fpDelivery', '#js-delivery input[type="radio"][name^="delivery_option"]', (event) => {
    const $option = $(event.currentTarget);
    const $form = $option.closest('#js-delivery');

    globalThis.sessionStorage.setItem(DELIVERY_SELECTED_BY_USER_KEY, '1');
    globalThis.sessionStorage.setItem(DELIVERY_STORAGE_KEY, $option.val());

    $form.find('.js-delivery-option-error').hide();
    toggleDeliveryFormState($form);

    syncShippingSubtotalVisibility();
  });

  $(document)
    .off('submit.fpDelivery', '#js-delivery')
    .on('submit.fpDelivery', '#js-delivery', (event) => {
    const $form = $(event.currentTarget);

    if (getCheckedDeliveryOption($form).length > 0) {
      return;
    }

    event.preventDefault();
    $form.find('.js-delivery-option-error').show();
    toggleDeliveryFormState($form);
  });

  prestashop.on('updatedDeliveryForm', (params) => {
    restoreDeliverySelection();
    syncShippingSubtotalVisibility();

    const $form = $('#js-delivery');
    const $checked = getCheckedDeliveryOption($form);

    $(prestashop.themeSelectors.checkout.carrierExtraContent).hide();

    if ($checked.length === 0) {
      return;
    }

    const deliveryOptionEl =
      params?.deliveryOption?.length
        ? params.deliveryOption
        : $checked;

    const carrierExtraContent = deliveryOptionEl.next(
      prestashop.themeSelectors.checkout.carrierExtraContent
    );

    if (carrierExtraContent.length && carrierExtraContent.html().trim() !== '') {
      carrierExtraContent.slideDown();
    }
  });

  $(document)
    .off('ajaxComplete.fpDelivery')
    .on('ajaxComplete.fpDelivery', () => {
    restoreDeliverySelection();
    syncShippingSubtotalVisibility();
  });
});

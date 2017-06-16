require([
  'jquery',
  'Magento_Checkout/js/action/get-totals',
  'Magento_Checkout/js/model/quote',
  'Magento_Checkout/js/view/cart/shipping-estimation',
  'Magento_Checkout/js/checkout-data',
], function ($, getTotals, quote, shippingEstimation, checkoutData) {

  var addressData = {'countryId': sveacheckout.country};
  checkoutData.setShippingAddressFromData(addressData);

  // on shipping method update
  quote.shippingMethod.subscribe(function (postData) {
    // set data
    postData.actionType = 'shipping_method_update';

    // send data
    updateCheckout(postData, false);
    return false;
  });

  // on clear cart action
  $('body').on('click', '#empty_cart_button', function () {
    // set data
    var postData = {
      actionType: 'cart_clear',
    };

    // send data
    updateCheckout(postData, true);
    return false;
  });

  // on delete item action
  $('body').on('click', '.action-delete', function () {
    // set data
    var formData = $(this).data('post').data;
    var postData = {};
    postData['id'] = formData.id;
    postData['actionType'] = 'delete_item';
    // send data
    updateCheckout(postData, true);
    return false;
  });

  // on update button click action
  $('body').on('click', '.action.update', function () {
    var formData = $('.form-cart').serializeArray();
    var postData = {};
    $.each(formData, (function (k, v) {
      k = v.name;
      v = v.value;
      postData[k] = v;
    }));
    postData['actionType'] = 'cart_update';


    // send data
    updateCheckout(postData, true);
    return false;
  });

  // on qty enter key action
  $('.form-cart').keypress(function (e) {
    if (e.which == 13) {
      e.preventDefault();

      // set data;
      var formData = $('.form-cart').serializeArray();
      var postData = {};
      $.each(formData, (function (k, v) {
        k = v.name;
        v = v.value;
        postData[k] = v;
      }));
      postData['actionType'] = 'cart_update';

      // send data
      updateCheckout(postData, true);
      return false;
    }
  });

  // on form submit (block it in favour of ajax call)
  $('body').on('submit', '.form-cart', function () {
    return false;
  });

  // update checkout via ajax call
  function updateCheckout(postData, fullUpdate) {
    if (typeof window.scoApi !== 'Undefined') {
      window.scoApi.setCheckoutEnabled(false);
    }
    // submit form
    $.ajax({
      type: 'POST',
      url: sveacheckout.postUrl,
      data: postData,
      success: function (result) {
        var blocks = $.parseJSON(result);

        updateCustomBlocks(blocks);

        if (fullUpdate == true) {
          getTotals([]);
          shippingEstimation([]);
        }

        // Update checkout window
        if (typeof window.scoApi !== 'Undefined') {
          window.scoApi.setCheckoutEnabled(true);
        }
      },
    });
  }

  // update custom ajax blocks
  function updateCustomBlocks(blocks) {
    // loop updated blocks
    $.each(blocks, function (key, block) {
      blockName = block.name;
      blockContent = block.content;

      // loop block ids
      $.each(sveacheckout.blockIds, function (ckey, cblock) {
        if (cblock.name == block.name) {
          blockElement = '#' + cblock.id;
        }
      });

      // replace content if matching block exists in DOM
      if (typeof blockElement !== 'undefined' && $(blockElement).length) {
        $(blockElement).html(blockContent);
      }
    });
  }
});

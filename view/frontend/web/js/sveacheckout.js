require([
  'jquery',
  'Magento_Checkout/js/action/get-totals',
  'Magento_Checkout/js/model/quote',
  'Magento_Checkout/js/view/cart/shipping-estimation',
  'Magento_Checkout/js/checkout-data',
  'Magento_Checkout/js/model/address-converter',
  'Magento_Customer/js/customer-data',
  'Magento_Checkout/js/model/cart/cache',
  'Magento_Checkout/js/model/shipping-service'
], function ($, getTotals, quote, shippingEstimation, checkoutData, addressConverter, customerData) {

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
  function callbackFunction(){}
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
    updateCheckout(postData, true);

    return false;
  });

  function enableAllShippingRadios() {
    document.querySelectorAll("#co-shipping-method-form input[type='radio']").forEach(function(el){
      if(el.disabled) {
        console.log('removed disabled State');
        el.disabled=false;
        el.removeAttribute("disabled");
      }
    });
  }
  enableAllShippingRadios();
  $('body').on('change',"input[type='radio']",function(){
    enableAllShippingRadios();
  });

  $('body').on('change',"[name='country_id']",function(){
    var formData = $('#shipping-zip-form').serializeArray();
    var postData = {};
    $.each(formData, (function (k, v) {
      k = v.name;
      v = v.value;
      postData[k] = v;
    }));
    postData['actionType'] = 'update_country';

    if (postData['country_id'] !== '') {

      updateCheckout(postData, true);
      callbackFunction = function () {
        var sections = ['customer', 'checkout-data', 'cart'];
        customerData.invalidate(sections);
        customerData.reload(sections, true);

        require([
          'jquery',
          'Magento_Checkout/js/model/quote'
        ], function ($, quote) {
        });

      }
    }
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
    if ('scoApi' in window) {
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
        if ('scoApi' in window) {
          window.scoApi.setCheckoutEnabled(true);
        }
      },
    }).done(callbackFunction);
  }

  // update custom ajax blocks
  function updateCustomBlocks(blocks) {
    // loop updated blocks
    $.each(blocks, function (key, block) {
      blockName    = block.name;
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
    enableAllShippingRadios();
  }

  /**
   * Observe the validationresult, check for last error message, if found display it.
   */
  if ('scoApi' in window) {
    window.scoApi.observeEvent('validation.result', function() {
      window.scoApi.setCheckoutEnabled(true);
    });
  }

  function observeEvents($) {
    if ('scoApi' in window) {
      window.scoApi.observeEvent("identity.postalCode", function (data) {
        $('#shipping-zip-form [name="postcode"]').val(data.value);
        $('#shipping-zip-form [name="postcode"]').trigger('change', this);
      });
    } else {
      setTimeout(setupObservers, 500, $);
    }
  }

  function setupObservers($) {
    observeEvents($);
  }

  setupObservers($);
});

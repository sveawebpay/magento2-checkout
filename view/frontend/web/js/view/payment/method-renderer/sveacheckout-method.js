/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
/*browser:true*/
/*global define*/
define(
  [
    'jquery',
    'Magento_Checkout/js/view/payment/default',
    'Magento_Checkout/js/model/full-screen-loader',
    'mage/url'
  ],
  function ($,Component,fullScreenLoader,url) {
    'use strict';
    return Component.extend({
      redirectAfterPlaceOrder: true,
      defaults: {
        template: 'Webbhuset_Sveacheckout/payment/sveacheckout'
      },
      getMailingAddress: function() {
        return window.checkoutConfig.payment.checkmo.mailingAddress;
      },
      isActive: function() {
        return true;
      }
    });
  }
);
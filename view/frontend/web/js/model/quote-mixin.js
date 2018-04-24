define([
],function () {
  'use strict';

  return function (target) {
    var getQuoteId = function() {
      return window.checkoutConfig.quoteData.entity_id;
    }

    target.getQuoteId = getQuoteId;

    return target;
  };
});
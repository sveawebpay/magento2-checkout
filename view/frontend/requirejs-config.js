var config = {
  "map": {
    "*": {
      "sveacheckout": "Webbhuset_Sveacheckout/js/sveacheckout",
      'Magento_Checkout/js/model/totals':'Webbhuset_Sveacheckout/js/model/totals'
    }
  },
  config: {
    mixins: {
      'Magento_Checkout/js/model/quote': {
        'Webbhuset_Sveacheckout/js/model/quote-mixin': true
      }
    }
  }
};

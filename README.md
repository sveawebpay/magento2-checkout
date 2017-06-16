Svea checkout for Magento2
*Only order creation for now*
 You will need to log into your Svea management interface to change or activate payments.

 Administration methods coming soon. 
 
*Requirements (inherited from dependencies)*
* PHP 5.6 >=
* php-soap
* php-curl

*Installation:*

run `composer require sveaekonomi/magento2-checkout && composer install` to install files.


Head to _administration -> Stores -> Configuration -> Payment methods -> Svea Ekonomi Checkout_


and enable it, provide your integration authentication details and choose order-statuses to be used 
before as well as after order acknowledgement. Save and clear cache.


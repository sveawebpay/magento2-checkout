Svea checkout for Magento2

Important!

Your domain needs to be whitelisted in order to use the module,

please contact Support-Webpay@sveaekonomi.se if you're unsure if you're whitelisted.


* Requirements (inherited from dependencies)*
* Magento 2.1.4 or above
* PHP 5.6 >=
* php-soap
* php-curl

*Installation:*

run `composer require sveaekonomi/magento2-checkout && composer install` to install files.
Then run `bin/magento setup:upgrade && bin/magento setup:di:compile && bin/magento cache:flush` from your base folder 
to install database scripts and recompile the dependency injections and clear cache.

Head to _administration -> Stores -> Configuration -> Payment methods -> Svea Ekonomi Checkout_


and enable it, provide your integration authentication details and choose order-statuses to be used 
before as well as after order acknowledgement. Save and clear cache.


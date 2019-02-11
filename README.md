Svea checkout for Magento2

* Requirements (inherited from dependencies)*
* Magento 2.1.4 or above
* PHP 5.6 >=
* php-soap
* php-curl

*Installation:*

run `composer require sveaekonomi/magento2-checkout && composer install` to install files.
Then run `bin/magento setup:upgrade && bin/magento setup:di:compile && bin/magento cache:flush` from your base folder 
to install database scripts and recompile the dependency injections and clear cache.

After that, head to administration -> Stores -> Configuration -> Payment methods -> Svea Ekonomi Checkout,
 to configure your new payment method.

To upgrade run `composer update sveaekonomi/magento2-checkout --with-dependencies`.
Then run `bin/magento setup:upgrade && bin/magento setup:di:compile && bin/magento cache:flush` from your base folder
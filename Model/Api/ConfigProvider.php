<?php

namespace Webbhuset\Sveacheckout\Model\Api;

use Svea\Checkout\Transport\Connector;
use Svea\WebPay\Config\ConfigurationProvider;
use Webbhuset\Sveacheckout\Helper\Data as helper;
use Magento\Framework\App\State as appState;
use Magento\Framework\App\ProductMetadataInterface;

/**
 * Class ConfigProvider
 *
 * @package Webbhuset\Sveacheckout\Model\Api
 * @module  Sveacheckout
 * @author  Webbhuset <info@webbhuset.se>
 */
class ConfigProvider
    implements ConfigurationProvider
{
    protected $helper;
    protected $appState;
    protected $productMetadata;

    /**
     * ConfigProvider constructor.
     *
     * @param \Webbhuset\Sveacheckout\Helper\Data             $helper
     * @param \Magento\Framework\App\State                    $appState
     * @param \Magento\Framework\App\ProductMetadataInterface $productMetadata
     */
    public function __construct(
        helper                   $helper,
        appState                 $appState,
        ProductMetadataInterface $productMetadata
    )
    {
        $this->helper          = $helper;
        $this->appState        = $appState;
        $this->productMetadata = $productMetadata;
    }

    /**
     * Get checkout Merchant ID from database.
     *
     * @return string API integration Checkout Merchant ID
     */
    public function getCheckoutMerchantId($countryId  = Null)
    {
        return $this->helper->getStoreConfig('payment/webbhuset_sveacheckout/merchant_id');
    }

    /**
     * Get checkout Secret from database.
     *
     * @return string API integration Secret
     */
    public function getCheckoutSecret($countryId  = Null)
    {
        return $this->helper->getStoreConfig('payment/webbhuset_sveacheckout/shared_secret');
    }

    /**
     * is admin?
     *
     * @return bool
     */
    protected function isAdmin()
    {
        $appState = $this->appState;
        return ($appState->getAreaCode() == \Magento\Framework\App\Area::AREA_ADMINHTML);
    }

    /**
     * get endpoint.
     *
     * @param string $type
     *
     * @return string
     */
    public function getEndPoint($type)
    {
        $testMode   = $this->helper->getStoreConfig('payment/webbhuset_sveacheckout/test_mode');
        $adminStore = $this->isAdmin();

        if ($type == 'CHECKOUT_ADMIN' || $adminStore) {
            return ($testMode)
                ? Connector::TEST_ADMIN_BASE_URL
                : Connector::PROD_ADMIN_BASE_URL;
        }

        return ($testMode)
            ? Connector::TEST_BASE_URL
            : Connector::PROD_BASE_URL;
    }

    /**
     * Get integration platform.
     *
     * @return string
     */
    public function getIntegrationPlatform()
    {
        $version = sprintf(
            "%s %s",
            $this->productMetadata->getName(),
            $this->productMetadata->getEdition()
        );

        return $version;

    }

    /**
     * Get module/integration name.
     *
     * @return string
     */
    public function getIntegrationCompany()
    {
        return 'Webbhuset_Svea_Ekonomi_Checkout_Module';
    }

    /**
     * Get version.
     *
     * @return string
     */
    public function getIntegrationVersion()
    {

        return $this->productMetadata->getVersion();
    }

    /**
     * not used in checkout.
     *.
     * @param string $type
     * @param string $country
     */
    public function getUsername($type, $country)
    {
    }

    /**
     * not used in checkout.
     *
     * @param string $type
     * @param string $country
     */
    public function getPassword($type, $country)
    {
    }

    /**
     * Get client number.
     *
     * @param string $type
     * @param string $country
     *
     * @return mixed
     */
    public function getClientNumber($type, $country)
    {
        return $this->getCredentialsProperty('clientNumber', $type, $country);
    }

    /**
     * Get merchant id.
     *
     * @param string $type
     * @param        $country
     *
     * @return mixed
     */
    public function getMerchantId($type, $country)
    {
        return $this->getCredentialsProperty('merchantId', $type, $country);
    }

    /**
     * Get shared secret.
     *
     * @param string $type
     * @param        $country
     *
     * @return mixed
     */
    public function getSecret($type, $country)
    {
        return $this->getCredentialsProperty('secret', $type, $country);
    }
}

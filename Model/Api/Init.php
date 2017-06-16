<?php

namespace Webbhuset\Sveacheckout\Model\Api;

use Webbhuset\Sveacheckout\Model\Api\ConfigProvider as apiCredentials;
use Webbhuset\Sveacheckout\Helper\Data as helper;
use Magento\Framework\App\State as appState;
use Magento\Framework\App\ProductMetadataInterface;

/**
 * Class Init
 *
 * @package Webbhuset\Sveacheckout
 * @module  Sveacheckout
 * @author  Webbhuset <info@webbhuset.se>
 */
class Init
    extends apiCredentials
{
    protected $helper;
    protected $productMetadata;

    /**
     * Init constructor.
     *
     * @param \Webbhuset\Sveacheckout\Helper\Data             $helper
     * @param \Magento\Framework\App\State                    $appState
     * @param \Magento\Framework\App\ProductMetadataInterface $productMetadata
     */
    function __construct(
        helper                   $helper,
        appState                 $appState,
        ProductMetadataInterface $productMetadata
    )
    {
        $this->helper          = $helper;
        $this->productMetadata = $productMetadata;

        parent::__construct($helper,
                            $appState,
                            $productMetadata
        );
    }

    /**
     * Setup communication.
     *
     * @return object Webbhuset\Sveacheckout\Model\Api\ConfigProvider
     */
    public function getSveaConfig()
    {

        return $this;
    }
}

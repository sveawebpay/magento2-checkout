<?php

namespace Webbhuset\Sveacheckout\Block;

use Magento\Framework\View\Element\Template\Context;
use Webbhuset\Sveacheckout\Model\Api\BuildOrder;
use Webbhuset\Sveacheckout\Helper\Data as helper;

/**
 * Class Checkout
 *
 * @package Webbhuset\Sveacheckout\Block
 * @module  Sveacheckout
 * @author  Webbhuset <info@webbhuset.se>
 */
class Checkout
    extends \Magento\Framework\View\Element\Template
{
    protected $context;
    protected $helper;

    /**
     * Checkout constructor.
     *
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Webbhuset\Sveacheckout\Model\Api\BuildOrder     $buildOrder
     * @param \Webbhuset\Sveacheckout\Helper\Data              $helper
     */
    public function __construct(
        Context    $context,
        BuildOrder $buildOrder,
        helper     $helper
    )
    {
        $this->context = $context;
        $this->helper  = $helper;

        return parent::__construct($context);
    }

    public function getClearLocalStorage()
    {

        return $this->getData('clearLocalStorage');
    }

    public function getCheckoutHtml()
    {

        return $this->getData('snippet');
    }

    public function getLocales() {
        $configPath = 'payment/webbhuset_sveacheckout/purchase_locale';

        return unserialize($this->helper->getStoreConfig($configPath));
    }

    public function getAllowCountrySwitching() {
        $configPath = 'payment/webbhuset_sveacheckout/enable_international_purchases';

        return $this->helper->getStoreConfig($configPath);
    }
}

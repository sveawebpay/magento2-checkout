<?php
namespace Webbhuset\Sveacheckout\Plugin\Result;

use Magento\Framework\App\ResponseInterface;
use Webbhuset\Sveacheckout\Helper\Data as helper;

class Page
{
    protected $context;
    protected $registry;
    protected $helper;

    public function __construct(
        \Magento\Framework\View\Element\Context     $context,
        \Magento\Framework\Registry                 $registry,
        helper                                      $helper
    )
    {
        $this->context      = $context;
        $this->registry     = $registry;
        $this->helper       = $helper;
    }

    public function beforeRenderResult(
        \Magento\Framework\View\Result\Page $subject,
        ResponseInterface $response
    )
    {
        if ($this->context->getRequest()->getFullActionName() == 'sveacheckout_index_index') {
            $showShipping = $this->helper->getStoreConfig('payment/webbhuset_sveacheckout/shipping_mobile');

            if (!$showShipping) {
                $subject->getConfig()->addBodyClass('svea_hidden_shipping');
            }
        }

        return [$response];
    }
}

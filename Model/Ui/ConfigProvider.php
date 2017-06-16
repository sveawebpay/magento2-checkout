<?php
namespace Webbhuset\Sveacheckout\Model\Ui;

use Magento\Framework\Escaper;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\View\Asset\Repository as assetRepository;
use Magento\Payment\Helper\Data as PaymentHelper;
use Webbhuset\Sveacheckout\Helper\Data as helper;

/**
 * Class ConfigProvider
 */
class ConfigProvider
    implements ConfigProviderInterface
{
    const CHECKOUT_CODE = 'webbhuset_sveacheckout';

    protected $helper;
    protected $escaper;
    protected $config;
    protected $sveaConfig;
    protected $assetRepository;
    protected $campaign;
    protected $paymentHelper;

    /**
     * ConfigProvider constructor.
     *
     * @param \Magento\Framework\Escaper               $escaper
     * @param \Magento\Framework\View\Asset\Repository $assetRepository
     * @param \Magento\Payment\Helper\Data             $paymentHelper
     * @param \Webbhuset\Sveacheckout\Helper\Data      $helper
     */
    public function __construct(
        Escaper         $escaper,
        assetRepository $assetRepository,
        PaymentHelper   $paymentHelper,
        helper          $helper
    )
    {
        $this->escaper         = $escaper;
        $this->assetRepository = $assetRepository;
        $this->paymentHelper   = $paymentHelper;
        $this->helper          = $helper;

    }

    /**
     * Payment config.
     *
     * @return array
     */
    public function getConfig()
    {

        return [
            'payment' => [
                'webbhuset_sveacheckout' => [

                ],
            ],
        ];
    }

    /**
     * Get payemnt Method by code.
     *
     * @param  string $code
     *
     * @return \Magento\Payment\Model\MethodInterface
     */
    public function getMethod($code)
    {

        return $this->paymentHelper->getMethodInstance($code);
    }
}

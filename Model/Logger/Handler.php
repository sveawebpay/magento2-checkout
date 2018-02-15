<?php
namespace Webbhuset\Sveacheckout\Model\Logger;

use Magento\Framework\Filesystem\DriverInterface;
use Webbhuset\Sveacheckout\Helper\Data;

class Handler extends \Magento\Framework\Logger\Handler\Base
{
    /**
     * @var string
     */
    protected $fileName = '/var/log/sveaekonomi-checkout.log';

    /**
     * Handler constructor.
     * @param DriverInterface $filesystem
     * @param Data $helper
     */
    public function __construct(
        DriverInterface $filesystem,
        Data $helper
    ) {
        $this->loggerType = $helper->getStoreConfig('payment/webbhuset_sveacheckout/developers/debug') ?
            \Monolog\Logger::DEBUG : \Monolog\Logger::INFO;
        parent::__construct($filesystem);
    }
}

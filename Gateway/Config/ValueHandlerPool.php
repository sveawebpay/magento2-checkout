<?php

namespace Webbhuset\Sveacheckout\Gateway\Config;

use Magento\Payment\Gateway\Config\ValueHandlerInterface;
use Magento\Sales\Model\Order\Payment;

/**
 * Class ValueHandlerPool
 *
 * @package Webbhuset\Sveacheckout\Gateway\Config
 * @module  Sveacheckout
 * @author  Webbhuset <info@webbhuset.se>
 */
class ValueHandlerPool  implements ValueHandlerInterface
{
    /**
     * CanVoidHandler constructor.
     */
    public function __construct()
    {
    }

    /**
     * Retrieve method configured value
     *
     * @param array    $subject
     * @param int|null $storeId
     *
     * @return mixed
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function handle(array $subject, $storeId = null)
    {
        $paymentDO = $subject['payment'];
        $payment = $paymentDO->getPayment();

        return $payment instanceof Payment && !(bool)$payment->getAmountPaid();
    }
}

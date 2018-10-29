<?php
namespace Webbhuset\Sveacheckout\Model;

use Magento\Payment\Model\InfoInterface;
use Magento\Payment\Model\Method\Adapter as paymentAdapter;

class Adapter
    extends paymentAdapter
{
    public function canCapturePartial()
    {
        $transactionData = $this->getInfoInstance()->getAdditionalInformation();

        if (!isset($transactionData['reservation']['PaymentType'])) {

            return parent::canRefundPartialPerInvoice();
        }

        switch ($transactionData['reservation']['PaymentType'])
        {
            case 'SVEACARDPAY':
                return parent::canCapturePartial();
            default:
                return parent::canCapturePartial();
        }
    }

    public function canRefund()
    {

        return parent::canRefund();
    }

    public function canRefundPartialPerInvoice()
    {
        $transactionData = $this->getInfoInstance()->getAdditionalInformation();

        if (!isset($transactionData['reservation']['PaymentType'])) {

            return parent::canRefundPartialPerInvoice();
        }

        switch ($transactionData['reservation']['PaymentType'])
        {
            default:
                return parent::canRefundPartialPerInvoice();
        }
    }
}

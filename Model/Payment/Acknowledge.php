<?php

namespace Webbhuset\Sveacheckout\Model\Payment;

use Magento\Framework\DataObject;
use Webbhuset\Sveacheckout\Model\Queue as queueModel;
use Magento\Sales\Model\OrderRepository;
use Webbhuset\Sveacheckout\Helper\Transaction as transactionHelper;
use Magento\Sales\Api\Data\TransactionInterface;
use Magento\Framework\App\Config\ScopeConfigInterface as scope;
use Magento\Sales\Model\Service\OrderService as orderService;
use Magento\Sales\Model\Order\Status;

/**
 * Class Acknowledge
 *
 * @package Webbhuset\Sveacheckout\Model\Payment
 * @module  Sveacheckout
 * @author  Webbhuset <info@webbhuset.se>
 */
class Acknowledge
{
    protected $orderRepository;
    protected $scopeConfig;
    protected $transactionHelper;
    protected $orderService;

    /**
     * Acknowledge constructor.
     *
     * @param \Magento\Sales\Model\OrderRepository               $orderRepository
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Webbhuset\Sveacheckout\Helper\Transaction         $transactionHelper
     * @param \Magento\Sales\Model\Service\OrderService          $orderService
     * @param \Magento\Sales\Model\Order\Status                  $status
     */
    public function __construct(
        OrderRepository   $orderRepository,
        scope             $scopeConfig,
        transactionHelper $transactionHelper,
        orderService      $orderService,
        Status            $status
    )
    {
        $this->orderRepository   = $orderRepository;
        $this->scopeConfig       = $scopeConfig;
        $this->transactionHelper = $transactionHelper;
        $this->orderService      = $orderService;
        $this->status            = $status;
    }

    /**
     * Acknowledge order.
     *
     * @param            $orderQueueItem
     * @param DataObject $responseObject
     * @param string     $mode
     */
    public function acknowledge($orderQueueItem, DataObject $responseObject, $mode)
    {
        $orderId = $orderQueueItem->getOrderId();
        $order   = $this->orderRepository->get($orderId);

        //Sometimes email is missing on order transaction
        $sveaData = $responseObject->getData();
        if (
            empty(trim($order->getCustomerEmail())) || 'missing-email@example.com' == $order->getCustomerEmail()
            && !empty($sveaData['EmailAddress'])
        ) {
            $order->setCustomerEmail($sveaData['EmailAddress']);
        }


        try {
            $this->orderService->notify($orderId);
        } catch (\Exception $e) {
            //Suppress unable to send mail exception.
        }

        $status = $this->getAcknowledgedOrderStatus();
        $state  = $this->status->getCollection()
            ->joinStates()
            ->addFieldToFilter('main_table.status', $status)
            ->getFirstItem()->getData('state');

        $order->setState($state)
            ->setStatus($status)
            ->save();

        $payment = $order->getPayment();
        $oldInfo = $payment->getAdditionalInformation();

        $info = [
            'reservation'  => $responseObject->getData(),
            'sveacheckout' => [
                'mode'     => $mode,
                'order_id' => $orderQueueItem->getId(),
            ],
        ];

        $info = array_merge($oldInfo, $info);
        $payment->setAdditionalInformation($info);

        $type = TransactionInterface::TYPE_AUTH;
        $this->transactionHelper->addTransaction($payment, $responseObject, $type, false);

        $payment->authorize(true, $order->getGrandTotal());

        $orderQueueItem->setState(queueModel::SVEA_QUEUE_STATE_OK)
            ->save();
    }

    /**
     * Get status for acknowledged order
     *
     * @return string
     */
    protected function getAcknowledgedOrderStatus()
    {
        $key    = strtolower("payment/webbhuset_sveacheckout/acknowledged_order_status");
        $status = $this->scopeConfig->getValue($key, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

        return $status;
    }
}

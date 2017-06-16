<?php

namespace Webbhuset\Sveacheckout\Model\Payment;

use Magento\Framework\DataObject;
use Magento\Quote\Model\QuoteManagement;
use Magento\Sales\Api\Data\TransactionInterface;
use Magento\Sales\Model\Order;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\OrderRepository;
use Webbhuset\Sveacheckout\Model\Queue as queueModel;
use Webbhuset\Sveacheckout\Helper\Transaction as transactionHelper;

/**
 * Class CreateOrder
 *
 * @package Webbhuset\Sveacheckout\Model\Payment
 * @module  Sveacheckout
 * @author  Webbhuset <info@webbhuset.se>
 */
class CreateOrder
{
    protected $quoteManagement;
    protected $orderRepository;
    protected $transactionHelper;

    /**
     * CreateOrder constructor.
     *
     * @param \Magento\Quote\Model\QuoteManagement       $quoteManagement
     * @param \Magento\Sales\Model\OrderRepository       $orderRepository
     * @param \Magento\Sales\Model\Order                 $order
     * @param \Webbhuset\Sveacheckout\Helper\Transaction $transactionHelper
     */
    public function __construct(
        QuoteManagement   $quoteManagement,
        OrderRepository   $orderRepository,
        Order             $order,
        transactionHelper $transactionHelper
    )
    {
        $this->quoteManagement = $quoteManagement;
        $this->orderRepository = $orderRepository;
        $this->order = $order;
        $this->transactionHelper = $transactionHelper;
    }

    /**
     * Create Order.
     *
     * @param  \Magento\Quote\Model\Quote $quote
     * @param  queueModel                 $orderQueueItem
     * @param  array                      $sveaOrder
     * @param  DataObject                 $responseObject
     *
     * @return int|mixed
     */
    public function createOrder(
        quote      $quote,
        queueModel $orderQueueItem,
        array      $sveaOrder,
        DataObject $responseObject
    )
    {
        $connection = $orderQueueItem->getResource()->getConnection();
        $connection->beginTransaction();

        try {
            $quote = $this->_addAddressToQuote($quote, $sveaOrder)
                ->setCustomerIsGuest(true);

            $quote->setIsActive(true)
                ->collectTotals()
                ->save();

            $orderId = $this->quoteManagement->placeOrder($quote->getId());

            $orderQueueItem->setState(queueModel::SVEA_QUEUE_STATE_NEW)
                ->setOrderId($orderId)
                ->save();

            $order   = $this->orderRepository->get($orderId);
            $payment = $order->getPayment();

            $type = TransactionInterface::TYPE_ORDER;
            $this->transactionHelper->addTransaction($payment, $responseObject, $type);

            $connection->commit();
        } catch (\Exception $e) {
            $connection->rollback();

            $orderQueueItem->setState(queueModel::SVEA_QUEUE_STATE_ERR)
                ->save();

            return false;
        }

        return $orderId;
    }

    /**
     * Add address from Svea to quote.
     *
     * @param  \Magento\Quote\Model\Quote $quote
     * @param  DataObject                 $data
     *
     * @return \Magento\Quote\Model\Quote
     */
    protected function _addAddressToQuote($quote, $data)
    {
        //ZeroWidthSpace
        $notNull = html_entity_decode('&#8203;');

        $billingAddress  = $data['BillingAddress'];
        $shippingAddress = $data['ShippingAddress'];

        $billingFirstname = ($billingAddress['FirstName'])
            ? $billingAddress['FirstName']
            : $billingAddress['FullName'];

        $billingFirstname = ($billingFirstname)
            ? $billingFirstname
            : $notNull;

        $billingLastname = ($billingAddress['LastName'])
            ? $billingAddress['LastName']
            : $notNull;

        $billingAddressData = [
            'firstname'  => $billingFirstname,
            'lastname'   => $billingLastname,
            'street'     => implode(
                "\n",
                [
                    $billingAddress['StreetAddress'],
                    $billingAddress['CoAddress'],
                ]
            ),
            'city'       => $billingAddress['City'],
            'postcode'   => $billingAddress['PostalCode'],
            'telephone'  => $data['PhoneNumber'],
            'country_id' => strtoupper($billingAddress['CountryCode']), 'payment_method' => 'checkmo',
        ];

        $shippingFirstname = ($shippingAddress['FirstName'])
            ? $shippingAddress['FirstName']
            : $shippingAddress['FullName'];

        $shippingFirstname = ($shippingFirstname)
            ? $shippingFirstname
            : $notNull;

        $shippingLastname = $shippingAddress['LastName']
            ? $shippingAddress['LastName']
            : $notNull;

        $shippingAddressData = [
            'firstname' => $shippingFirstname,
            'lastname'  => $shippingLastname,

            'street'         => implode(
                "\n",
                [
                    $shippingAddress['StreetAddress'],
                    $shippingAddress['CoAddress'],
                ]
            ),
            'city'           => $shippingAddress['City'],
            'postcode'       => $shippingAddress['PostalCode'],
            'country_id'     => strtoupper($shippingAddress['CountryCode']),
            'telephone'      => $data['PhoneNumber'],
            'payment_method' => 'checkmo',
        ];

        $quote->getBillingAddress()->addData($billingAddressData)
            ->setPaymentMethod('checkmo');
        $quote->getShippingAddress()->addData($shippingAddressData)
            ->setPaymentMethod('checkmo')
            ->setCollectShippingRates(true);

        $quote->setCustomerEmail($data['EmailAddress'])
            ->setCustomerFirstname($shippingAddress['FirstName'])
            ->setCustomerLastname($shippingAddress['LastName']);

        return $quote;
    }
}

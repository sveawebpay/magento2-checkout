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
use Webbhuset\Sveacheckout\Model\Logger\Logger as Logger;

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
    protected $logger;

    /**
     * CreateOrder constructor.
     *
     * @param \Magento\Quote\Model\QuoteManagement       $quoteManagement
     * @param \Magento\Sales\Model\OrderRepository       $orderRepository
     * @param \Magento\Sales\Model\Order                 $order
     * @param \Webbhuset\Sveacheckout\Helper\Transaction $transactionHelper
     * @param \Webbhuset\Sveacheckout\Model\Logger\Logger $logger
     */
    public function __construct(
        QuoteManagement   $quoteManagement,
        OrderRepository   $orderRepository,
        Order             $order,
        transactionHelper $transactionHelper,
        Logger            $logger
    )
    {
        $this->quoteManagement   = $quoteManagement;
        $this->orderRepository   = $orderRepository;
        $this->order             = $order;
        $this->transactionHelper = $transactionHelper;
        $this->logger            = $logger;
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

            $this->logger->info("Order {$order->getId()} #{$order->getIncrementId()} created.");
        } catch (\Exception $e) {
            $connection->rollback();
            $this->logger->error($e->getMessage());
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

        $email           = $data['EmailAddress'];
        $email           = ($email) ? $email : 'missing-email@example.com';
        $billingAddress  = $data['BillingAddress'];
        $shippingAddress = $data['ShippingAddress'];
        $customer        = $data['Customer'];

        $reference = (isset($customer['CustomerReference']) && !empty($customer['CustomerReference']))
                   ? $customer['CustomerReference']
                   : false;
        $reference = (!$reference && isset($data['CustomerReference']) && !empty($data['CustomerReference']))
                   ? $data['CustomerReference']
                   : false;

        $billingFirstname = ($billingAddress['FirstName'])
                          ? $billingAddress['FirstName']
                          : $billingAddress['FullName'];

        $billingFirstname = ($billingFirstname)
                          ? $billingFirstname
                          : $notNull;

        if ($customer['IsCompany'] == true) {
            $billingCompany   = $billingAddress['FullName'];
            $shippingCompany  = $shippingAddress['FullName'];
            $billingFirstname = ($reference)
                              ? $reference
                              : $notNull;
        }

        $billingLastname = ($billingAddress['LastName'])
                         ? $billingAddress['LastName']
                         : $notNull;

        $street = implode(
            "\n",
            [
                $billingAddress['StreetAddress'],
                $billingAddress['CoAddress'],
            ]
        );

        $street  = ($street) ? $street : $notNull;
        $city    = $billingAddress['City'];
        $city    = $city ? $city : $notNull;
        $zip     = $billingAddress['PostalCode'];
        $zip     = $zip ? $zip : $notNull;
        $phone   = $data['PhoneNumber'];
        $phone   = ($phone) ? $phone : $notNull;
        $country = strtoupper($billingAddress['CountryCode']);
        $country = ($country) ? $country : $notNull;

        $billingAddressData = [
            'firstname'      => $billingFirstname,
            'lastname'       => $billingLastname,
            'street'         => $street,
            'city'           => $city,
            'postcode'       => $zip,
            'telephone'      => $phone,
            'country_id'     => strtoupper($country),
            'payment_method' => 'checkmo',
        ];

        if (true == $customer['IsCompany'] && $reference) {
            $shippingFirstname = $reference;
        } else {
            $shippingFirstname = ($shippingAddress['FirstName'])
                ? $shippingAddress['FirstName']
                : $shippingAddress['FullName'];
        }
        $shippingFirstname = ($shippingFirstname)
                           ? $shippingFirstname
                           : $notNull;

        $shippingLastname  = $shippingAddress['LastName']
                           ? $shippingAddress['LastName']
                           : $notNull;

        $street = implode(
            "\n",
            [
                $shippingAddress['StreetAddress'],
                $shippingAddress['CoAddress'],
            ]
        );

        $street  = ($street) ? $street : $notNull;
        $city    = $shippingAddress['City'];
        $city    = $city ? $city : $notNull;
        $zip     = $shippingAddress['PostalCode'];
        $zip     = $zip ? $zip : $notNull;
        $phone   = $data['PhoneNumber'];
        $phone   = ($phone) ? $phone : $notNull;
        $country = strtoupper($shippingAddress['CountryCode']);
        $country = ($country) ? $country : $notNull;

        $shippingAddressData = [
            'firstname'      => $shippingFirstname,
            'lastname'       => $shippingLastname,
            'street'         => $street,
            'city'           => $city,
            'postcode'       => $zip,
            'country_id'     => $country,
            'telephone'      => $phone,
            'payment_method' => 'checkmo',
        ];

        if (isset($billingCompany)) {
            $billingAddressData['company'] = $billingCompany;
        }
        if (isset($shippingCompany)) {
            $shippingAddressData['company'] = $shippingCompany;
        }

        $quote->getBillingAddress()->addData($billingAddressData)
            ->setPaymentMethod('checkmo');
        $quote->getShippingAddress()->addData($shippingAddressData)
            ->setPaymentMethod('checkmo')
            ->setCollectShippingRates(true);

        $quote->setCustomerEmail($email)
            ->setCustomerFirstname($shippingFirstname)
            ->setCustomerLastname($shippingLastname);

        return $quote;
    }
}

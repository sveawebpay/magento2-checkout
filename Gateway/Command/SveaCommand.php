<?php

namespace Webbhuset\Sveacheckout\Gateway\Command;

use Magento\Payment\Gateway\Command\Result\ArrayResultFactory;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Webbhuset\Sveacheckout\Model\Api\Init as sveaConfig;
use Webbhuset\Sveacheckout\Helper\Data as helper;
use Magento\Eav\Model\Config as EavConfig;
use Svea\WebPay\WebPayItem;
use Svea\WebPay\WebPayAdmin;
use Svea\WebPay\Constant\DistributionType;

/**
 * Class SveaCommand
 *
 * @package Webbhuset\Sveacheckout\Gateway\Command
 * @module  Sveacheckout
 * @author  Webbhuset <info@webbhuset.se>
 */
class SveaCommand implements
    CommandInterface
{
    const SVEA_IS_INVOICEABLE               = 'CanDeliverOrder';
    const SVEA_IS_PARTIALLY_INVOICEABLE     = 'CanDeliverPartially';
    const SVEA_CAN_CANCEL_ORDER             = 'CanCancelOrder';
    const SVEA_ROW_IS_IS_INVOICEABLE        = 'CanDeliverRow';
    const SVEA_CAN_ADD_ORDER_ROW            = 'CanAddOrderRow';
    const SVEA_ROW_IS_IS_UPDATEABLE         = 'CanUpdateOrderRow';
    const SVEA_CURRENT_ROW_IS_IS_UPDATEABLE = 'CanUpdateRow';
    const SVEA_CAN_CREDIT_ORDER_ROWS        = 'CanCreditRow';

    protected $adapter;
    protected $validator;
    protected $resultFactory;
    protected $handler;
    protected $method;
    protected $sveaConfig;
    protected $helper;
    protected $eavConfig;

    /**
     * SveaCommand constructor.
     *
     * @param                                                         $client
     * @param \Webbhuset\Sveacheckout\Model\Api\Init                  $sveaConfig
     * @param \Webbhuset\Sveacheckout\Helper\Data                     $helper
     * @param \Magento\Payment\Gateway\Response\HandlerInterface|null $handler
     */
    public function __construct(
        $client,
        sveaConfig $sveaConfig,
        helper $helper,
        eavConfig $eavConfig,
        HandlerInterface $handler = null

    )
    {
        $this->sveaConfig = $sveaConfig;
        $this->helper     = $helper;
        $this->handler    = $handler;
        $this->eavConfig  = $eavConfig;
        $this->method     = $client['method'];
    }

    /**
     * @inheritdoc
     * @throws \Exception
     */
    public function execute(array $commandSubject)
    {
        $method = $this->method;
        if (method_exists($this, $method)) {
            call_user_func([$this, $method], $commandSubject);
        }
    }

    /**
     * Cancel payment.
     *
     * @see self::void()
     */
    public function cancel($payment)
    {

        return $this->void($payment);
    }

    /**
     * Void payment.
     *
     * @param $payment
     *
     * @return bool=false|string=json
     * @throws \Exception
     */
    public function void($payment)
    {
        $payment      = SubjectReader::readPayment($payment);
        $order        = $payment->getPayment()->getOrder();
        $sveaOrderId  = (int)$order->getPaymentReference();
        $sveaOrder    = $this->getCheckoutOrder($order);

        if ($order->getBaseTotalInvoiced() > 0) {
            throw new \Exception(
                'Cannot void, if invoice exist - use credit memo or svea admin.'
            );
            return false;
        }

        $canCancel = in_array($this::SVEA_CAN_CANCEL_ORDER, $sveaOrder['Actions']);
        if ($canCancel) {

            $request = WebPayAdmin::cancelOrder($this->sveaConfig)
                ->setCheckoutOrderId($sveaOrderId);

            $response = $request->cancelCheckoutOrder()->doRequest();

            return $response;
        } else {
            throw new \Exception(
                'Cannot void.'
            );

            return false;
        }
    }

    /**
     * @param $data
     *
     * @return mixed
     * @throws \Exception
     */
    public function capture($data)
    {
        $payment      = SubjectReader::readPayment($data);
        $order        = $payment->getPayment()->getOrder();
        $sveaOrderId  = (int)$order->getPaymentReference();
        $invoice      = $order->getInvoiceCollection()->getLastItem();
        $sveaOrder    = $this->getCheckoutOrder($order);
        $paymentItems = $invoice->getItems();
        $locale       = $this->getLocale($order);

        $shippingMethod      = '';
        $canPartiallyProcess = in_array($this::SVEA_IS_PARTIALLY_INVOICEABLE, $sveaOrder['Actions']);

        if (!in_array('CanDeliverOrder', $sveaOrder['Actions'])) {
            if ('Delivered' == $sveaOrder['OrderStatus']) {
                throw new \Exception(
                    'Svea responded: order already delivered. Handle it using Svea\'s admin interface'
                );
            }

            throw new \Exception(
                'Svea responded: order not billable. ' .
                'Order status: ' . $sveaOrder['OrderStatus']
            );
        }

        $shippingAmount = (float)$invoice->getOrder()->getShippingAmount();

        if ($shippingAmount > 0 && !$payment->getPayment()->getShippingCaptured()) {
            $shippingMethod = $order->getShippingDescription();
        }

        $invoiceIncrementId = $this->eavConfig
            ->getEntityType('invoice')
            ->fetchNewIncrementId($order->getStoreId());
        $invoice->setIncrementId($invoiceIncrementId);

        $deliverItems = $this->getActionRows(
            $paymentItems,
            $sveaOrder['OrderRows'],
            $shippingMethod,
            ['CanDeliverRow', 'CanUpdateRow']
        );

        if (!sizeof($sveaOrder['OrderRows'])) {
            throw new \Exception(
                'Could not save invoice, No more rows to invoice'
            );
        }

        foreach ($deliverItems as $key => $item) {
            $actionQty = round($item['action_qty']);
            if ($actionQty < 1) {
                if (!$canPartiallyProcess) {
                    throw new \Exception('Order cannot be partially processed.');
                }
                //Row should not be delivered, continue.
                unset($deliverItems[$key]);
                continue;
            }
            $this->adjustQty(
                $item,
                $key,
                $sveaOrderId,
                $locale,
                $sveaOrder['Actions'],
                $invoiceIncrementId
            );
        }

        $request = WebPayAdmin::deliverOrderRows($this->sveaConfig)
            ->setCheckoutOrderId($sveaOrderId)
            ->setCountryCode($locale['purchase_country'])
            ->setInvoiceDistributionType(DistributionType::POST)
            ->setRowsToDeliver(array_keys($deliverItems));

        $request->deliverCheckoutOrderRows()->doRequest();
        $sveaOrder = $this->getCheckoutOrder($order);

        return json_decode(json_encode($sveaOrder), false);
    }

    /**
     * Fetch order from Svea.
     *
     * @param  \Magento\Sales\Model\Order $order .
     *
     * @return string
     */
    protected function getCheckoutOrder($order)
    {
        $sveaConfig  = $this->sveaConfig;
        $sveaOrderId = (int)$order->getPaymentReference();
        $locale      = $this->getLocale($order);

        $request = WebPayAdmin::queryOrder($sveaConfig)
            ->setCheckoutOrderId($sveaOrderId)
            ->setCountryCode($locale['purchase_country']);

        return $request->queryCheckoutOrder()->doRequest();
    }

    /**
     * Get locale from order.
     *
     * @param  \Magento\Sales\Model\Order $order
     *
     * @return string|bool=false
     * @throws \Exception
     */
    protected function getLocale($order)
    {
        if ($order->getPayment()) {
            $transactionDetails = $order->getPayment()->getAdditionalInformation();
            if (!isset($transactionDetails['Locale'])) {
                throw new \Exception('Order transaction missing, is it acknowledged?');
            }
            $locale = ['purchase_country' => $transactionDetails['Locale']];
        }

        if (!isset($locale)) {

            return ('No usable locale found, The order is most likely not acknowledged by Svea yet.');
        }

        return $locale;
    }

    /**
     * Extracts svea-rows from requested change rows.
     *
     * @param        Mage_Sales_Model_Resource_Order_Creditmemo_Item_Collection
     *                                |Mage_Sales_Model_Resource_Order_Invoice_Item_Collection $itemCollection
     * @param array  $sveaItems
     * @param String $shippingMethod
     * @param array  $requireActions
     * @param Int    $referenceNumber incrementID reference
     *
     * @return array|bool=false
     *
     * @throws \Exception
     */
    protected function getActionRows(
        $itemCollection,
        $sveaItems,
        $shippingMethod,
        $requireActions,
        $referenceNumber = null
    )
    {
        $chosenItems = [];
        $return      = [];
        $items       = [];

        if (!$sveaItems) {

            return false;
        }

        foreach ($itemCollection as $item) {
            $prefix = '';
            if (isset($referenceNumber)) {
                $prefix = $referenceNumber . '-';
            }
            $orderItem = $item->getOrderItem();
            if ($orderItem->isChildrenCalculated()) {
                $prefix .= $orderItem->getQuoteItemId() . '-';
            }

            if ($item->getDiscountAmount()) {
                $prefixedSku = sprintf($prefix.'discount-%s',  trim($orderItem->getQuoteItemId()));
                $sku         = substr($prefixedSku, 0, 40);
                $items[]     = [
                    'sku'         => $sku,
                    'qty'         => 1,
                    'Price'       => $item->getDiscountAmount(),
                    'newDiscount' => $item->getDiscountAmount(),
                ];
            }

            if ($item->getQty()) {
                $items[] = [
                    'sku'         => $prefix . $item->getSku(),
                    'qty'         => $item->getQty(),
                    'Price'       => $item->getPriceInclTax(),
                    'newDiscount' => $orderItem->getDiscountAmount(),
                ];
            }
        }

        foreach ($sveaItems as $key => $row) {
            if (isset($row['ArticleNumber'])) {
                $itemKey = array_search($row['ArticleNumber'], array_column($items, 'sku'));
                if (false !== $itemKey) {
                    $chosenItems[$key]               = $row;
                    $qty                             = $items[$itemKey]['qty'];
                    $chosenItems[$key]['action_qty'] = (float)$qty;
                    $chosenItems[$key]['newDiscount'] = (float)$items[$itemKey]['newDiscount'];
                }
            } else {
                $itemKey = array_search($row['Name'], array_column($items, 'sku'));
                if (in_array($row['Name'], array_column($items, 'sku'))) {
                    $chosenItems[$key]               = $row;
                    $qty                             = $items[$itemKey]['qty'];
                    $chosenItems[$key]['action_qty'] = (float)$qty;
                    $chosenItems[$key]['newDiscount'] = (float)$items[$itemKey]['newDiscount'];
                }
            }
            if ($shippingMethod && $shippingMethod == $row['Name']) {
                $chosenItems[$key]               = $row;
                $chosenItems[$key]['action_qty'] = 1;
                $chosenItems[$key]['newDiscount'] = (float)0;
            }
        }

        foreach ($chosenItems as $key => $row) {
            foreach ($requireActions as $requireAction) {
                if (!in_array($requireAction, $row['Actions'])) {
                    throw new \Exception(
                        'Order row was unprocessable.'
                    );
                }
            }

            $return[$row['OrderRowId']] = $chosenItems[$key];
        }

        return $return;
    }

    /**-
     * Adjusts quantity and adds new row with the rest of your quantity.
     * Used when you do partial deliveries.
     *
     * @param $item
     * @param $key
     * @param $sveaOrderId
     * @param $locale
     * @param $orderActions
     * @param $referenceNumber
     *
     * @return string
     * @throws \Exception
     */
    protected function adjustQty(
        $item,
        $key,
        $sveaOrderId,
        $locale,
        $orderActions,
        $referenceNumber
    )
    {
        $adjustQty = $item['action_qty'];
        $qty       = $item['Quantity'];

        if ($adjustQty > $qty) {
            throw new \Exception('Cannot process more than ordered quantity.');
        }

        $prefix = '';
        if (isset($referenceNumber)) {
            $prefix = $referenceNumber . '-';
        }

        if (
            $item['UnitPrice'] <0
            && stripos($item['Name'] , 'discount') !== false
            && $item['UnitPrice'] != $item['newDiscount']*-1
        ) {
            $rest = $item['UnitPrice'] + $item['newDiscount'];

            $partialActionRow = WebPayItem::numberedOrderRow()
                ->setRowNumber($key)
                ->setArticleNumber($referenceNumber . '-' . $item['Name'])
                ->setAmountIncVat((float)$item['newDiscount']*-1)
                ->setVatPercent((int)$item['VatPercent'])
                ->setQuantity($item['Quantity']);

            $restOfRowQty = WebPayItem::orderRow()
                ->setArticleNumber($item['ArticleNumber'])
                ->setName($item['Name'])
                ->setAmountIncVat((float)$rest)
                ->setVatPercent((int)$item['VatPercent'])
                ->setQuantity((int)$item['Quantity']);

            $updateRows = WebPayAdmin::updateOrderRows($this->sveaConfig)
                ->setCheckoutOrderId($sveaOrderId)
                ->setCountryCode($locale)
                ->updateOrderRow($partialActionRow);

            $addRows = WebPayAdmin::addOrderRows($this->sveaConfig)
                ->setCheckoutOrderId($sveaOrderId)
                ->setCountryCode($locale['purchase_country'])
                ->addOrderRow($restOfRowQty);


            if (isset($updateRows)) {
                $updateRows->updateCheckoutOrderRows()->doRequest();
            }
            if (isset($addRows)) {
                $addRows->addCheckoutOrderRows()->doRequest();
            }

            return 'adjustedDiscount';
        }

        if ($adjustQty < $qty) {
            $rest = $item['Quantity'] - $adjustQty;

            if (!in_array($this::SVEA_CURRENT_ROW_IS_IS_UPDATEABLE, $item['Actions'])) {
                throw new \Exception('Cannot adjust row.');
            }

            if (!in_array($this::SVEA_ROW_IS_IS_UPDATEABLE, $orderActions)) {
                throw new \Exception('Cannot adjust row.');
            }

            if ($rest && !in_array($this::SVEA_CAN_ADD_ORDER_ROW, $orderActions)) {
                throw new \Exception('Cannot add rows to this order.');
            }


            $partialActionRow = WebPayItem::numberedOrderRow()
                ->setRowNumber($key)
                ->setArticleNumber($referenceNumber . '-' . $item['ArticleNumber'])
                ->setAmountIncVat((float)$item['UnitPrice'])
                ->setVatPercent((int)$item['VatPercent'])
                ->setQuantity($adjustQty);

            $restOfRowQty = WebPayItem::orderRow()
                ->setArticleNumber($item['ArticleNumber'])
                ->setName($item['Name'])
                ->setAmountIncVat((float)$item['UnitPrice'])
                ->setVatPercent((int)$item['VatPercent'])
                ->setQuantity((int)$rest);

            $updateRows = WebPayAdmin::updateOrderRows($this->sveaConfig)
                ->setCheckoutOrderId($sveaOrderId)
                ->setCountryCode($locale)
                ->updateOrderRow($partialActionRow);

            $addRows = WebPayAdmin::addOrderRows($this->sveaConfig)
                ->setCheckoutOrderId($sveaOrderId)
                ->setCountryCode($locale['purchase_country'])
                ->addOrderRow($restOfRowQty);

        }

        if (isset($updateRows)) {
            $updateRows->updateCheckoutOrderRows()->doRequest();
        }
        if (isset($addRows)) {
            $addRows->addCheckoutOrderRows()->doRequest();
        }

        return 'adjustedQty';
    }

    /**
     * @param $payment
     *
     * @return mixed
     */
    public function authorize($payment)
    {

        return $payment;
    }

    /**
     * Create a credit memo in Svea.
     *
     * @param  Mage_Sales_Model_Order_Creditmemo $creditMemo
     *
     * @return Object
     */
    public function refund($data)
    {
        $payment         = SubjectReader::readPayment($data);
        $order           = $payment->getPayment()->getOrder();
        $creditMemo      = $order->getPayment()->getCreditmemo();
        $invoiceNo       = $creditMemo->getInvoice()->getIncrementId();
        $sveaOrderId     = (int)$order->getPaymentReference();
        $sveaConfig      = $this->sveaConfig;
        $sveaOrder       = $this->getCheckoutOrder($order);
        $creditMemoItems = $creditMemo->getItems();
        $shippingMethod  = '';
        $shippingAmount  = $creditMemo->getOrder()->getShippingAmount();
        $shippingCredit  = $creditMemo->getShippingAmount();

        if ($shippingCredit > 0 && ($shippingCredit == $shippingAmount)) {
            $shippingMethod = $order->getShippingDescription();
        }

        foreach ($sveaOrder['Deliveries'] as $key => $deliveries) {
            foreach ($deliveries['OrderRows'] as $item) {
                if (stristr($item['ArticleNumber'], $invoiceNo) !== false) {
                    $deliveryKey = $key;
                    break;
                }
            }
        }

        if (isset($deliveryKey)) {
            $tmpRefundItems[$sveaOrder['Deliveries'][$deliveryKey]['Id']] = $this->getActionRows(
                $creditMemoItems,
                $sveaOrder['Deliveries'][$deliveryKey]['OrderRows'],
                $shippingMethod,
                [$this::SVEA_CAN_CREDIT_ORDER_ROWS],
                $invoiceNo
            );
        } else {
            foreach ($sveaOrder['Deliveries'] as $deliveries) {
                $tmpRefundItems[$deliveries['Id']] = $this->getActionRows(
                    $creditMemoItems,
                    $deliveries['OrderRows'],
                    $shippingMethod,
                    [$this::SVEA_CAN_CREDIT_ORDER_ROWS]
                );
            }
        }

        $tmpRefundItems = array_filter($tmpRefundItems);
        foreach ($tmpRefundItems as $deliveryId => $refundItems) {
            $deliveryId = (int)$deliveryId;
            $locale     = $this->getLocale($order);

            foreach ($refundItems as $key => $refundItem) {
                if (
                    $refundItem['UnitPrice'] < 0
                    && stripos($refundItem['Name'] , 'discount') !== false
                    && $refundItem['UnitPrice'] != $refundItem['newDiscount']*-1
                ) {
                    $partialRefundedItems[$key] = $refundItem;


                    continue;
                }

                if ($refundItem['action_qty'] == $refundItem['Quantity']) {
                    $fullyRefunded[$key] = $refundItem;
                } else {
                    $partialRefundedItems[$key] = $refundItem;
                }
            }

            if (isset($fullyRefunded) && sizeof($fullyRefunded)) {
                $creditOrder = WebPayAdmin::creditOrderRows($sveaConfig)
                    ->setCheckoutOrderId($sveaOrderId)
                    ->setDeliveryId($deliveryId)
                    ->setInvoiceDistributionType(DistributionType::POST)
                    ->setCountryCode($locale['purchase_country'])
                    ->setRowsToCredit(array_keys($fullyRefunded));
                $creditOrder->creditCheckoutOrderRows()->doRequest();
            }

            if (isset($partialRefundedItems) && sizeof($partialRefundedItems)) {
                usort($partialRefundedItems, function($a, $b) {
                    return $b['OrderRowId'] - $a['OrderRowId'];
                });
                $reduceCreditBy = [];
                foreach ($partialRefundedItems as $refundItem) {
                    if (
                        $refundItem['UnitPrice'] < 0
                        && stripos($refundItem['Name'] , 'discount') !== false
                        && $refundItem['UnitPrice'] != $refundItem['newDiscount']*-1
                    ) {
                        $reduceCreditBy = [
                            'name'   => $refundItem['Name'],
                            'amount' => $refundItem['newDiscount']
                        ];
                        continue;
                    }

                    $creditOrder = WebPayAdmin::creditOrderRows($sveaConfig)
                        ->setCheckoutOrderId($sveaOrderId)
                        ->setDeliveryId($deliveryId)
                        ->setInvoiceDistributionType(DistributionType::POST)
                        ->setCountryCode($locale['purchase_country']);

                    $creditAmount = $refundItem['UnitPrice'] * $refundItem['action_qty'];
                    $creditTitle =  '-' . $refundItem['action_qty'] . 'x ' . $refundItem['ArticleNumber'];

                    if (sizeof($reduceCreditBy)) {
                        $creditTitle .=  ' - '.$reduceCreditBy['name'] . '('. $reduceCreditBy['amount'] .')';
                        $creditAmount -= $reduceCreditBy['amount'];
                    }

                    $refundRow   = WebPayItem::orderRow()
                        ->setAmountIncVat($creditAmount)
                        ->setName($creditTitle)
                        ->setVatPercent($refundItem['VatPercent']);
                    $creditOrder->addCreditOrderRow($refundRow);

                    $reduceCreditBy = [];
                }
                if(isset($creditOrder)) {
                    $creditOrder->creditCheckoutOrderWithNewOrderRow()->doRequest();
                }
            }
        }
        $sveaOrder = $this->getCheckoutOrder($order);

        return json_decode(json_encode($sveaOrder), false);
    }
}

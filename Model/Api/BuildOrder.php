<?php

namespace Webbhuset\Sveacheckout\Model\Api;

use Svea\WebPay\WebPay;
use Svea\WebPay\WebPayItem;
use Webbhuset\Sveacheckout\Helper\Data as helper;
use Webbhuset\Sveacheckout\Model\Api\Init as sveaConfig;
use Magento\Checkout\Model\Session;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Quote\Api\Data\EstimateAddressInterfaceFactory;
use Magento\Quote\Api\ShippingMethodManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Webbhuset\Sveacheckout\Model\Logger\Logger as Logger;
use Webbhuset\Sveacheckout\Model\Queue as queueModel;
use Webbhuset\Sveacheckout\Model\QueueFactory as queueModelFactory;
use Webbhuset\Sveacheckout\Api\QueueRepositoryInterface;

/**
 * Class BuildOrder
 *
 * @package Webbhuset\Sveacheckout\Model\Api
 * @module  Sveacheckout
 * @author  Webbhuset <info@webbhuset.se>
 */
class BuildOrder
{
    protected $auth;
    protected $helper;
    protected $checkoutSession;
    protected $logger;
    protected $messageManager;
    protected $redirect;
    protected $queueModel;
    protected $queueInterface;
    protected $estimatedAddressFactory;
    protected $shippingMethodManager;
    protected $quoteRepository;
    protected $queueFactory;

    /**
     * BuildOrder constructor.
     *
     * @param \Webbhuset\Sveacheckout\Helper\Data                     $helper
     * @param \Webbhuset\Sveacheckout\Model\Api\Init                  $auth
     * @param \Magento\Checkout\Model\Session                         $session
     * @param \Magento\Framework\Message\ManagerInterface             $messageManager
     * @param \Magento\Framework\Controller\ResultFactory             $redirect
     * @param \Webbhuset\Sveacheckout\Model\Queue                     $queueModel
     * @param \Webbhuset\Sveacheckout\Api\QueueRepositoryInterface    $queueRepository
     * @param \Magento\Quote\Api\Data\EstimateAddressInterfaceFactory $estimatedAddressFactory
     * @param \Magento\Quote\Api\ShippingMethodManagementInterface    $shippingMethodManager
     * @param \Magento\Quote\Api\CartRepositoryInterface              $quoteRepository
     */
    public function __construct(
        helper $helper,
        sveaConfig $auth,
        Session $session,
        Logger $logger,
        ManagerInterface $messageManager,
        ResultFactory $redirect,
        queueModel $queueModel,
        QueueRepositoryInterface $queueRepository,
        EstimateAddressInterfaceFactory $estimatedAddressFactory,
        ShippingMethodManagementInterface $shippingMethodManager,
        CartRepositoryInterface $quoteRepository,
        queueModelFactory $queueFactory
    )
    {
        $this->auth                    = $auth;
        $this->helper                  = $helper;
        $this->checkoutSession         = $session;
        $this->logger                  = $logger;
        $this->messageManager          = $messageManager;
        $this->redirect                = $redirect;
        $this->queueModel              = $queueModel;
        $this->queueRepository         = $queueRepository;
        $this->estimatedAddressFactory = $estimatedAddressFactory;
        $this->shippingMethodManager   = $shippingMethodManager;
        $this->quoteRepository         = $quoteRepository;
        $this->queueFactory            = $queueFactory;
    }

    /**
     * Create Svea order.
     *
     * @param  \Magento\Quote\Model\Quote $quote
     *
     * @return void|array
     */
    public function createOrder($quote)
    {
        $sveaOrderBuilder = WebPay::checkout($this->auth);

        if (!$quote->getItemsCount()) {
            $this->checkoutSession->setSveaGotError("No items in quote.");

            return;
        }

        if (!$quote->getShippingAddress()->getCountryId()) {
            $this->_addBasicAddressToQuote($quote);
            $quote->setTotalsCollectedFlag(false)->collectTotals()->save();
        }

        try {
            $queueItem = $this->createQueueItem($quote);
            $response = $this->getOrder($quote);
            if (!is_array($response)) {
                $this->_initialSettings($sveaOrderBuilder)
                     ->_presetValues($sveaOrderBuilder, $quote)
                     ->_additionalSettings($sveaOrderBuilder, $quote)
                     ->_addCartItems($sveaOrderBuilder, $quote)
                     ->_addShipping($sveaOrderBuilder, $quote, false)
                     ->_addTotalRows($sveaOrderBuilder, $quote);
                $response = $sveaOrderBuilder->createOrder();

                if ($this->sveaOrderHasErrors($sveaOrderBuilder, $quote, $response)) {
                    $this->checkoutSession->setSveaGotError("Quote " . intval($quote->getId()) . " is not valid");

                    return;
                }

                $paymentReference = $response['OrderId'];
                $quote->setData('payment_reference', $paymentReference);
                $queueItem->setData('payment_reference', $paymentReference)
                    ->save();
            }
        } catch (\Exception $e) {
            $this->checkoutSession->setSveaGotError($e->getMessage());

            return;
        }

        return $response;
    }

    /**
     * Update Svea order.
     *
     * @param  \Magento\Quote\Model\Quote $quote
     * @param  boolean                    $validate
     *
     * @return void|array
     */
    public function getOrder($quote, $validate = true)
    {
        $sveaOrderId = (int)$quote->getData('payment_reference');
        if (!$sveaOrderId) {
            $this->logger->warning("Get order error - empty payment_reference for quote `{$quote->getId()}`");
            return;
        }
        $sveaOrderBuilder = WebPay::checkout($this->auth);
        $quote->setTotalsCollectedFlag(false)->collectTotals()->save();

        try {
            $this->_initialSettings($sveaOrderBuilder)
                 ->_additionalSettings($sveaOrderBuilder, $quote)
                 ->_addCartItems($sveaOrderBuilder, $quote)
                 ->_addShipping($sveaOrderBuilder, $quote, false)
                 ->_addTotalRows($sveaOrderBuilder, $quote);

            $response = $sveaOrderBuilder
                ->setCheckoutOrderId($sveaOrderId)
                ->getOrder();

            if ($validate) {
                if ($this->sveaOrderHasErrors($sveaOrderBuilder, $quote, $response)) {
                    $this->checkoutSession->setSveaGotError("Quote " . intval($quote->getId()) . " is not valid");
                    $this->logger->warning("Get order - quote `{$quote->getId()}` has errors");
					
                    return;
                }
            }
        } catch (\Exception $e) {
            $this->checkoutSession->setSveaGotError($e->getMessage());
            $this->logger->error("Get order error - {$e->getMessage()}");
            $this->logger->error($e);
			
            return;
        }

        return $response;
    }

    /**
     * Create a new queue item.
     *
     * @param \Magento\Sales\Model\Quote $quote
     *
     * @return \Webbhuset\Sveacheckout\Model\Queue
     */
    protected function createQueueItem($quote)
    {
        $queueItem = $this->queueFactory->create();

        $queueItem
            ->setData([
                'payment_reference' => $quote->getPaymentReference(),
                'quote_id'          => $quote->getId(),
                'state'             => queueModel::SVEA_QUEUE_STATE_INIT,
            ]);
        $queueItem->save();

        return $queueItem;
    }

    /**
     * Get queue item for quote.
     *
     * @param \Magento\Sales\Model\Quote $quote
     *
     * @return \Webbhuset\Sveacheckout\Model\Queue
     */
    protected function getQueueItem($quote)
    {
        $queueItem = $this->queueModel->getByQuoteId($quote->getId());

        if (!$queueItem->getId()) {
            $queueItem = $this->createQueueItem($quote);
        }

        return $queueItem;
    }

    /**
     * Go through the quote and add quoteItems to the invoiceOrder.
     *
     * @param  \Svea\WebPay\Checkout\CheckoutOrderEntry $sveaOrderBuilder
     * @param  \Magento\Sales\Model\Quote               $quote
     *
     * @return \Webbhuset\Sveacheckout\Model\Api\BuildOrder
     */
    protected function _addCartItems($sveaOrderBuilder, $quote)
    {
        $sortedItems = [];
        foreach ($quote->getAllItems() as $item) {
            if ($item->getHasChildren() || !$item->getParentItemId()) {
                $sortedItems[$item->getId()]['item'] = $item;
            } else {
                $parentId = $item->getParentItemId();

                if (empty($sortedItems[$parentId])) {
                    $sortedItems[$parentId] = ['children' => []];
                }

                $sortedItems[$parentId]['children'][] = $item;
            }
            unset($item);
        }

        foreach ($sortedItems as $data) {
            $item = isset($data['item'])
                ? $data['item']
                : null;

            $children = isset($data['children'])
                ? $data['children']
                : [];

            if (!$item) {
                continue;
            }

            if ($item->isChildrenCalculated()) {
                foreach ($children as $child) {
                    $this->_processItem($sveaOrderBuilder, $child, $item->getId(), $item->getQty());
                }
            } else {
                $this->_processItem($sveaOrderBuilder, $item);
            }
        }

        return $this;
    }

    /**
     * Adding a row to the (reservation)Order.
     *
     * @param \Svea\WebPay\Checkout\CheckoutOrderEntry    $buildOrder
     * @param \Magento\Quote\Model\Quote\Item             $item
     * @param string=quote_item_id | parent_quote_item_id $prefix
     * @param float|integer                               $multiply
     */
    protected function _processItem($buildOrder, $item, $prefix = '', $multiply = 1)
    {
        if ($item->getQty() > 0) {
            if ($prefix) {
                $prefix = $prefix . '-';
            }

            $qty = $multiply * $item->getQty();
            $orderRowItem = WebPayItem::orderRow()
                ->setAmountIncVat((float)$item->getPriceInclTax())
                ->setVatPercent((int)round($item->getTaxPercent()))
                ->setQuantity((float)round($qty, 2))
                ->setArticleNumber($prefix . $item->getSku())
                ->setName(mb_substr($item->getName(), 0, 40))
                ->setTemporaryReference((string)$item->getId());
            $buildOrder->addOrderRow($orderRowItem);

            if ((float)$item->getDiscountAmount()) {
                $itemRowDiscount = WebPayItem::fixedDiscount()
                    ->setName(mb_substr(sprintf('discount-%s', $prefix . $item->getId()), 0, 40))
                    ->setVatPercent((int)round($item->getTaxPercent()))
                    ->setAmountIncVat((float)$item->getDiscountAmount());

                $buildOrder->addDiscount($itemRowDiscount);
            }
        }
    }

    /**
     * Add order reference URIs etc. to Svea Order.
     *
     * @param  \Svea\WebPay\Checkout\CheckoutOrderEntry     $buildOrder
     * @param  \Magento\Quote\Model\Quote                   $quote
     *
     * @return \Webbhuset\Sveacheckout\Model\Api\BuildOrder $this
     */
    protected function _additionalSettings($buildOrder, $quote)
    {
        $quoteId = $quote->getId();
        $mode    = $this->helper->getStoreConfig('payment/webbhuset_sveacheckout/test_mode')
            ? 'test'
            : 'prod';

        $queueItem = $this->getQueueItem($quote);
        $pushParams = [
            'queueId' => $queueItem->getData('queue_id'),
            'mode'    => $mode,
        ];

        if (isset($pushParams['sveaId'])) {
            $pushParams['sveaId'] = $quote->getPaymentReference();
        }

        //To avoid order already being created, if you for example have
        //stageEnv/devEnv and ProductionEnv with quote id in same range.
        $allowedLength = 32;
        $separator     = '_';
        $lengthOfHash  = $allowedLength - (strlen((string)$quoteId) + strlen($separator));
        $hashedBaseUrl = sha1($this->helper->getBaseUrl());
        $clientId      = $quoteId . $separator . substr($hashedBaseUrl, 0, $lengthOfHash);
        $pushUri       = $this->helper->getUrl('sveacheckout/Index/push', $pushParams);
        $validationUri = $this->helper->getUrl('sveacheckout/Index/Validation', $pushParams);

        $overrideCallbackUri = $this->helper->getStoreConfig(
            'payment/webbhuset_sveacheckout/developers/callback_uri_override'
        );
        if ($overrideCallbackUri) {
            $overrideBase = $this->helper->getStoreConfig(
                'payment/webbhuset_sveacheckout/developers/callback_uri'
            );
            $pushUri       = str_replace($this->helper->getBaseUrl(), $overrideBase, $pushUri);
            $validationUri = str_replace($this->helper->getBaseUrl(), $overrideBase, $validationUri);
        }

        $restoreParams = array_merge($pushParams, ['reactivate' => 'true']);

        $buildOrder->setClientOrderNumber($clientId)
                   ->setCheckoutUri($this->helper->getUrl('sveacheckout/Index/index', $restoreParams))
                   ->setValidationCallbackUri($validationUri)
                   ->setConfirmationUri($this->helper->getUrl('sveacheckout/Index/success', $pushParams))
                   ->setPushUri($pushUri)
                   ->setTermsUri($this->helper->getUrl('sveacheckout/Index/terms', []));

        return $this;
    }

    /**
     * Set preset values
     *
     * @param \Svea\WebPay\Checkout\CheckoutOrderEntry  $buildOrder
     * @param \Magento\Quote\Api\Data\CartInterface     $quote
     *
     * @return $this
     */
    protected function _presetValues(
        \Svea\WebPay\Checkout\CheckoutOrderEntry $buildOrder,
        \Magento\Quote\Api\Data\CartInterface    $quote
    ) {
        $customer = $quote->getCustomer();

        if ($customer->getEmail()) {
            $email = $customer->getEmail();
            $presetEmail = WebPayItem::presetValue()
                ->setTypeName(\Svea\WebPay\Checkout\Model\PresetValue::EMAIL_ADDRESS)
                ->setValue($email)
                ->setIsReadonly(false);
            $buildOrder->addPresetValue($presetEmail);
        }

        $defaultBilling = $this->getCustomerDefaultBillingAddress($customer);
        if (!$defaultBilling) {

            return $this;
        }

        $telephone = $defaultBilling->getTelephone();
        $presetPhoneNumber = WebPayItem::presetValue()
            ->setTypeName(\Svea\WebPay\Checkout\Model\PresetValue::PHONE_NUMBER)
            ->setValue($telephone)
            ->setIsReadonly(false);
        $buildOrder->addPresetValue($presetPhoneNumber);

        $zip = $defaultBilling->getPostcode();
        $presetPostcode = WebPayItem::presetValue()
            ->setTypeName(\Svea\WebPay\Checkout\Model\PresetValue::POSTAL_CODE)
            ->setValue($zip)
            ->setIsReadonly(false);
        $buildOrder->addPresetValue($presetPostcode);

        return $this;
    }

    /**
     * Get customer default billing address
     *
     * @param  \Magento\Customer\Api\Data\CustomerInterface $customer
     *
     * @return \Magento\Customer\Api\Data\AddressInterface|null
     */
    protected function getCustomerDefaultBillingAddress(
        \Magento\Customer\Api\Data\CustomerInterface $customer
    ) {
        $addresses = $customer->getAddresses();
        if (!$addresses) {

            return null;
        }

        $defaultBilling = $customer->getDefaultBilling();

        foreach ($addresses as $address) {
            if ($address->getId() === $defaultBilling) {

                return $address;
            }
        }

        return null;
    }

    /**
     * @param \Svea\WebPay\Checkout\CheckoutOrderEntry  $buildOrder
     *
     * @return $this
     */
    protected function _initialSettings($buildOrder)
    {
        $getLocale = unserialize(
            $this->helper->getStoreConfig('payment/webbhuset_sveacheckout/purchase_locale')
        );

        $buildOrder->setCurrency($getLocale['purchase_currency'])
                   ->setLocale($getLocale['locale'])
                   ->setCountryCode($getLocale['purchase_country']);

        return $this;
    }

    /**
     * In order to make sure our quote is up to date; we instantiate a new order,
     * with a prefixed identifier and diff the response to our current.
     *
     * If the cart from the responses differ, the quote has changed.
     * If the (reservation)order is in a state where we can update it, we do.
     * Then we run the validation again to make sure they now are in sync.
     *
     * If the order is still not in sync we try once again.
     *
     * @param  \Svea\WebPay\Checkout\CheckoutOrderEntry $sveaOrder
     * @param  \Magento\Quote\Model\Quote               $quote
     * @param  array                                    $response
     * @param  int|null                                 $tries iteration counter
     *
     * @return bool
     */
    public function sveaOrderHasErrors($sveaOrder, $quote, $response, $tries = 0)
    {
        if (is_object($response)) {
            $response = $response->getData();
        }

        $sveaOrderBuilder = WebPay::checkout($this->auth);
        $this->_initialSettings($sveaOrderBuilder)
             ->_presetValues($sveaOrderBuilder, $quote)
             ->_additionalSettings($sveaOrderBuilder, $quote)
             ->_addCartItems($sveaOrderBuilder, $quote)
             ->_addShipping($sveaOrderBuilder, $quote, true)
             ->_addTotalRows($sveaOrderBuilder, $quote);
        $fakeOrder = $sveaOrderBuilder->getCheckoutOrderBuilder();
        $fakeOrder = json_decode(json_encode($fakeOrder), true);

        $diff = $this->_diffOrderRows($fakeOrder['rows'], $response['Cart']['Items']);
        if (sizeof($diff['error'])) {
            $this->logger->warning("sveaOrderHasErrors diff error, attempt {$tries}", ['error' => $diff['error']]);
            if (isset($response['Status']) && $response['Status'] != 'Created') {
                $this->logger->warning("sveaOrderHasErrors - incorrect order status `{$response['Status']}`");

                return true;
            }

            if ($tries >= 2) {
                $this->logger->warning("sveaOrderHasErrors - exceeded max number of tries");

                return true;
            }

            try {
                $updatedOrder = $sveaOrder->setCheckoutOrderId((int)$response['OrderId'])
                    ->updateOrder();
                return $this->sveaOrderHasErrors($sveaOrder, $quote, $updatedOrder, $tries + 1);
            } catch (\Exception $e) {
                $this->logger->error("sveaOrderHasErrors - {$e->getMessage()}");
                $this->logger->error($e);

                return true;
            }
        }
        $this->logger->debug("sveaOrderHasErrors - no errors after `$tries` tries");

        return false;
    }

    /**
     * Diff order Rows.
     *
     * @param  array $fakeOrderRows
     * @param  array $orderRows
     *
     * @return array
     */
    protected function _diffOrderRows($fakeOrderRows, $orderRows)
    {
        $differenceBetweenArrays = $this->helper->compareQuoteToSveaOrder($fakeOrderRows, $orderRows);

        return $differenceBetweenArrays;
    }

    /**
     * Add Shipping to quote and Svea Order.
     *
     * @param  \Svea\WebPay\Checkout\CheckoutOrderEntry $sveaOrder SveaOrder Object.
     * @param  \Magento\Quote\Model\Quote               $quote     Quote.
     * @param  boolean                                  $noSave    Do not modify the quote.
     *
     * @throws \Exception
     *
     * @return BuildOrder
     */
    protected function _addShipping($sveaOrder, $quote, $noSave)
    {
        $didNotLoadFromQuote = false;
        //Default shipping method.
        $method = $this->helper
            ->getStoreConfig('payment/sveacheckout/shipping_method_default');

        if (!$quote->getShippingAddress()->getCountryId()) {
            $this->_addBasicAddressToQuote($quote);
        }

        $shippingAddress = $quote->getShippingAddress();

        //Chosen shipping method.
        if ($quote->getShippingAddress()->getShippingMethod()) {
            $method = $shippingAddress->getShippingMethod();
        }

        //Neither Default nor chosen exists, select cheapest option.
        $selected = $shippingAddress->getShippingRateByCode($method);
        if (!$selected) {
            $method = $this->_getCheapestShippingOption($quote);
            $shippingAddress->setShippingMethod($method)
                ->collectShippingRates()
                ->save();
        }

        $methodTitle = $shippingAddress->getShippingDescription();

        //Add shipping to SveaOrder.
        $vatPercent    = 0;
        $shippingTitle = ($methodTitle)
            ? mb_substr($methodTitle, 0, 40)
            : __('Shipping');
        $shippingPrice = ($didNotLoadFromQuote && isset($fallbackPrice))
            ? $fallbackPrice
            : $shippingAddress->getShippingInclTax();

        $appliedTaxes = ($shippingAddress->getAppliedTaxes()) ? ($shippingAddress->getAppliedTaxes()) : [];
        $appliedTaxes = reset($appliedTaxes);

        if (isset($appliedTaxes['rates'][0]['percent'])) {
            $vatPercent = $appliedTaxes['rates'][0]['percent'];
        }

        $shippingFee = WebPayItem::shippingFee()
                                 ->setName((string)$shippingTitle)
                                 ->setVatPercent((int)$vatPercent)
                                 ->setAmountIncVat((float)$shippingPrice);
        $sveaOrder->addFee($shippingFee);

        return $this;
    }

    /**
     * Add a basic address to quote if missing.
     *
     * @param  \Magento\Quote\Model\Quote $quote
     *
     * @return \Webbhuset\Sveacheckout\Model\Api\BuildOrder
     */
    protected function _addBasicAddressToQuote($quote)
    {
        $locales        = unserialize($this->helper->getStoreConfig('payment/webbhuset_sveacheckout/purchase_locale'));
        $defaultCountry = $locales['purchase_country'];

        if (!$quote->getShippingAddress()->getShippingMethod()) {
            $estimatedAddress = $this->estimatedAddressFactory->create();
            $estimatedAddress->setCountryId($defaultCountry);
            $this->shippingMethodManager->estimateByAddress($quote->getId(), $estimatedAddress);
        }

        return $this;
    }

    /**
     * Get cheapest shipping option.
     *
     * @param \Magento\Quote\Model\Quote $quote
     *
     * @return string|bool=false
     */
    protected function _getCheapestShippingOption($quote)
    {
        //Get array of available rates
        $availableShippingRates = $quote
            ->getShippingAddress()->getShippingRatesCollection()->toArray();

        //Remove a level
        $availableShippingRates = array_pop($availableShippingRates);

        //Sort by price
        if (is_array($availableShippingRates)) {
            uasort(
                $availableShippingRates,
                function ($a, $b) {
                    return ($a['price'] < $b['price']) ? -1 : 1;
                }
            );
            //Reset keys
            $availableShippingRates = array_values($availableShippingRates);
        }

        //Select first
        if (isset($availableShippingRates[0]) && isset($availableShippingRates[0]['code'])) {

            return $availableShippingRates[0]['code'];
        }

        return false;
    }

    /**
     * Adding fees to the (reservation)Order.
     *
     * @param  \Svea\WebPay\Checkout\CheckoutOrderEntry $sveaOrder SveaOrder Object.
     * @param  \Magento\Quote\Model\Quote              $quote
     *
     * @return \Webbhuset\Sveacheckout\Model\Api\BuildOrder
     */
    protected function _addTotalRows($sveaOrder, $quote)
    {
        $totals = $quote->getTotals();

        /**
         * Magento standard order-totals should not be treated
         * as fees and thus added to the invoice.
         */
        $removeKeys = [
            'tax',
            'subtotal',
            'cost_total',
            'grand_total',
            'shipping',
            'discount',
        ];

        $taxPercent = 0;
        if (isset($totals['tax']) && isset($totals['grand_total'])) {
            if ($totals['tax']['value']) {
                $taxPercent = $totals['tax']['value'] / ($totals['grand_total']['value'] + $totals['tax']['value']);
            }
        }

        foreach ($removeKeys as $key) {
            if (isset($totals[$key])) {
                unset($totals[$key]);
            }
        }

        /**
         * If there are any totals left, they should be
         * treated as fees and thus added to invoice.
         */
        foreach ($totals as $totalRow) {
            $amount = round($totalRow->getValue(), 2);
            $title  = ($totalRow->getTitle())
                ? mb_substr($totalRow->getTitle(), 0, 40)
                : __('fee');

            $fee = WebPayItem::invoiceFee()
                             ->setName((string)$title)
                             ->setVatPercent((int)$taxPercent)
                             ->setAmountIncVat($amount);

            $sveaOrder->addFee($fee);
        }

        return $this;
    }
}

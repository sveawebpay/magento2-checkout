<?php

namespace Webbhuset\Sveacheckout\Controller\Index;

use Magento\Checkout\Model\Cart;
use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Checkout\Model\Session as checkoutSession;
use Magento\Quote\Model\QuoteRepository;
use Magento\CatalogInventory\Api\StockStateInterface;
use Magento\CatalogInventory\Api\StockItemRepositoryInterface as StockItemRepositoryInterface;
use Magento\Quote\Api\Data\ShippingInterface;
use Magento\Quote\Model\QuoteFactory;
use Webbhuset\Sveacheckout\Model\Api\BuildOrder;
use Magento\Quote\Model\QuoteIdMaskFactory;

/**
 * Class updateCart.
 *
 * @package Webbhuset\Sveacheckout\Controller\Index
 * @module  Sveacheckout
 * @author  Webbhuset <info@webbhuset.se>
 */
class updateCart
    extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Magento\Framework\View\Result\PageFactory
     */
    protected $_resultPageFactory;
    protected $jsonFactory;
    protected $checkoutSession;
    protected $context;
    protected $buildOrder;
    protected $quoteRepository;
    protected $stockItemRepository;
    protected $cartManagement;
    protected $cart;
    protected $quoteFactory;
    protected $configProvider;
    protected $quoteIdMaskFactory;

    /**
     * updateCart constructor.
     *
     * @param \Magento\Framework\App\Action\Context                      $context
     * @param \Magento\Framework\View\Result\PageFactory                 $resultPageFactory
     * @param \Magento\Framework\Controller\Result\JsonFactory           $jsonFactory
     * @param \Magento\Checkout\Model\Session                            $session
     * @param \Webbhuset\Sveacheckout\Model\Api\BuildOrder               $buildOrder
     * @param \Magento\CatalogInventory\Api\StockStateInterface          $stockItem
     * @param \Magento\Quote\Model\QuoteRepository                       $quoteRepository
     * @param \Magento\CatalogInventory\Api\StockItemRepositoryInterface $stockItemRepository
     * @param \Magento\Quote\Api\Data\ShippingInterface                  $shippingInterface
     * @param \Magento\Checkout\Model\Cart                               $cart
     * @param \Magento\Quote\Model\QuoteFactory                          $quoteFactory
     */
    public function __construct(
        Context                      $context,
        PageFactory                  $resultPageFactory,
        JsonFactory                  $jsonFactory,
        checkoutSession              $session,
        BuildOrder                   $buildOrder,
        StockStateInterface          $stockItem,
        QuoteRepository              $quoteRepository,
        StockItemRepositoryInterface $stockItemRepository,
        ShippingInterface            $shippingInterface,
        Cart                         $cart,
        QuoteFactory                 $quoteFactory,
        \Magento\Checkout\Model\CompositeConfigProvider $configProvider,
        QuoteIdMaskFactory $quoteIdMaskFactory
    )
    {
        $this->_resultPageFactory    = $resultPageFactory;
        $this->jsonFactory           = $jsonFactory;
        $this->checkoutSession       = $session;
        $this->buildOrder            = $buildOrder;
        $this->context               = $context;
        $this->quoteRepository       = $quoteRepository;
        $this->stockItem             = $stockItem;
        $this->stockItemRepository   = $stockItemRepository;
        $this->shippingInterface     = $shippingInterface;
        $this->cart                  = $cart;
        $this->quoteFactory          = $quoteFactory;
        $this->configProvider        = $configProvider;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;


        parent::__construct($context);
    }


    /**
     * Retrieve checkout configuration
     *
     * @return array
     * @codeCoverageIgnore
     */
    public function getCheckoutConfig()
    {
        return $this->configProvider->getConfig();
    }

    /**
     * Dispatch request.
     *
     * @return \Magento\Framework\Controller\ResultInterface
     * @throws \Magento\Framework\Exception\NotFoundException
     */
    public function execute()
    {
        $replaceBlocks = [];
        $returnBlocks  = [];
        $resultPage    = $this->_resultPageFactory->create();
        $layout        = $resultPage->getLayout();
        $quote         = $this->checkoutSession->getQuote();
        $requestParams = $this->context->getRequest()->getParams();

        if (!isset($requestParams['actionType'])) {

            return;
        }

        switch ($requestParams['actionType']) {
            case 'cart_update':

                $this->updateQty($quote, $requestParams['cart']);
                break;
            case 'delete_item':

                $this->deleteId($quote, $requestParams['id']);
                break;
            case 'shipping_method_update':
                $method = ($requestParams['carrier_code'] . '_' . $requestParams['method_code']);
                    $this->updateShipping($quote, $method);
                break;
            case 'update_country':
                $countryId = $this->context->getRequest()->getParam('country_id');
                $quote     = $this->replaceQuote($quote);

                $sveaResponse = $this->buildOrder->updateCountry($quote, $countryId);

                $checkoutConfig = \Zend_Json::encode($this->getCheckoutConfig());

                $replaceQuoteJs = "<script>
                window.checkoutConfig = {$checkoutConfig};
                window.isCustomerLoggedIn = window.checkoutConfig.isCustomerLoggedIn;
                window.customerData = window.checkoutConfig.customerData;
                </script>";

                $returnBlocks['snippet']      = $replaceQuoteJs.$sveaResponse['Gui']['Snippet'];
                break;
            case 'cart_clear':
                return $this->emptyCart($quote);
                break;
            default:
                break;
        }

        $this->buildOrder->getOrder($quote);

        foreach($returnBlocks as $name => $content) {
            $replaceBlocks = [
                'name' =>    $name,
                'content' => $content
            ];
        }
        $blocks = [
            [
                'name'    => 'cartBlock',
                'content' => $this->getCartHtml($layout),
            ],
            $replaceBlocks
        ];
        $result = $this->jsonFactory->create()->setData(json_encode($blocks));

        return $result;
    }

    /**
     * Remove quote.
     *
     * @param \Magento\Quote\Model\Quote $quote   QuoteModel
     * @param int                        $quoteId QuoteId
     */
    protected function deleteId($quote, $quoteId)
    {
        $quote->getItemById($quoteId)->delete();
        $this->quoteRepository->save($quote);
    }

    /**
     * Remove all items from quote.
     *
     * @param  \Magento\Quote\Model\Quote $quote
     *
     * @return string json
     */
    protected function emptyCart($quote)
    {
        foreach ((array)$quote as $itemId => $data) {
            foreach ($quote->getAllItems() as $item) {
                $item->delete();

            }
        }
        $blocks = [
            [
                'name'    => 'cartBlock',
                'content' => '<script type="text/javascript">document.location=document.location;</script>',
            ],
        ];
        $result = $this->jsonFactory->create()->setData(json_encode($blocks));
        $this->quoteRepository->save($quote);

        return $result;
    }

    /**
     * Update quantity.
     *
     * @param \Magento\Quote\Model\Quote $quote,
     * @param array $cart
     */
    protected function updateQty($quote, $cart)
    {
        foreach ((array)$cart as $itemId => $data) {
            foreach ($quote->getAllItems() as $item) {
                //Clear Errors.
                if ($item->getMessage() && $item->getHasError()) {
                    $item->setHasError(false)
                        ->removeMessageByText($item->getMessage());
                }

                if ($item->getItemId() != $itemId) {

                    continue;

                }

                $id = $item->getParentId() ?: $item->getId();

                $oldQty = $item->getQty();

                try {
                    $itemData = [$id => ['qty' => $data['qty']]];
                    $this->cart->updateItems($itemData);
                } catch (\Exception $e) {
                    $itemData = [$id => ['qty' => $oldQty]];

                    $this->cart->updateItems($itemData);
                    $item->setHasError(true)->setMessage($e->getMessage());
                }
            }
        }
    }

    /**
     * Assign a new shipping method to quote.
     *
     * @param \Magento\Quote\Model\Quote $quote,
     * @param $method
     *
     * @return self
     */
    protected function updateShipping($quote, $method=null)
    {
        $this->quoteRepository->save($quote);

        $shippingAddress = $quote->getShippingAddress();
        if($shippingAddress) {
            $shippingAddress->setCollectShippingRates(true)
                ->collectShippingRates()
                ->setShippingMethod($method)
                ->save();

            $quote->beforeSave();
            $quote->setShippingAddress($shippingAddress)
                ->save()
                ->afterSave();
        }

        return $this;
    }

    /**
     * @param $oldQuote
     *
     * @return mixed
     */
    protected function replaceQuote($oldQuote)
    {
        $quote = $this->quoteFactory->create();
        $quote->merge($oldQuote)
            ->setIsActive(1)
            ->setStoreId($oldQuote->getStoreId())
            ->setReservedOrderId(null)
            ->setPaymentReference(null)
            ->setShippingAddress($oldQuote->getShippingAddress())
            ->setPaymentReference(null)
            ->collectTotals();

        $oldQuote->setIsActive(0);
        $this->quoteRepository->save($oldQuote);
        $this->quoteRepository->save($quote);

        $this->checkoutSession->replaceQuote($quote)
            ->unsLastRealOrderId();
        $this->checkoutSession->setQuoteId($quote->getId());
        $quoteIdMask = $this->quoteIdMaskFactory->create()->load($quote->getId(), 'quote_id');
        $quoteIdMask->setQuoteId($quote->getId())->save();
        //sets Masked quote ID
        $this->checkoutSession->setIsQuoteMasked(true);

        return $quote;
    }

    /**
     * Extract product-table from Cart block.
     *
     * @param  \Magento\Framework\View\Layout\Interceptor $layout
     *
     * @return string
     */
    protected function getCartHtml($layout)
    {
        $cart = $layout->getBlock('checkout.cart.form')->toHtml();

        $dom = new \DOMDocument();
        $dom->loadHTML($cart);
        $table = $dom->saveHtml(
            $dom->getElementById('shopping-cart-table')
        );

        return utf8_decode($table);
    }
}

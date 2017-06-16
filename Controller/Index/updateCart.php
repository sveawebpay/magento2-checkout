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
use Webbhuset\Sveacheckout\Model\Api\BuildOrder;

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
        Cart                         $cart
    )
    {
        $this->_resultPageFactory  = $resultPageFactory;
        $this->jsonFactory         = $jsonFactory;
        $this->checkoutSession     = $session;
        $this->buildOrder          = $buildOrder;
        $this->context             = $context;
        $this->quoteRepository     = $quoteRepository;
        $this->stockItem           = $stockItem;
        $this->stockItemRepository = $stockItemRepository;
        $this->shippingInterface   = $shippingInterface;
        $this->cart                = $cart;
        parent::__construct($context);

    }

    /**
     * Dispatch request.
     *
     * @return \Magento\Framework\Controller\ResultInterface
     * @throws \Magento\Framework\Exception\NotFoundException
     */
    public function execute()
    {
        $resultPage    = $this->_resultPageFactory->create();
        $layout        = $resultPage->getLayout();
        $quote         = $this->checkoutSession->getQuote();
        $requestParams = $this->context->getRequest()->getParams();

        if ($requestParams['actionType'] == 'cart_update') {
            $this->updateQty($quote, $requestParams['cart']);
        }

        if ($requestParams['actionType'] == 'cart_clear') {
            return $this->emptyCart($quote);
        }

        if ($requestParams['actionType'] == 'delete_item') {
            $this->deleteId($quote, $requestParams['id']);
        }

        if ($requestParams['actionType'] == 'shipping_method_update') {
            $this->updateShipping(
                $quote,
                $requestParams['carrier_code'] . '_' . $requestParams['method_code']
            );
        }

        $this->buildOrder->getOrder($quote);
        $blocks = [
            [
                'name'    => 'cartBlock',
                'content' => $this->getCartHtml($layout),
            ],
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
     * @return bool
     */
    protected function updateShipping($quote, $method)
    {
        $this->quoteRepository->save($quote);

        $shippingAddress = $quote->getShippingAddress();

        $shippingAddress->setCollectShippingRates(true)
            ->collectShippingRates()
            ->setShippingMethod($method)
            ->save();
        $quote->beforeSave();
        $quote->setShippingAddress($shippingAddress)
            ->save()
            ->afterSave();

        return true;
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

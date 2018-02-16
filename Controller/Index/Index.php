<?php

namespace Webbhuset\Sveacheckout\Controller\Index;

use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Magento\Checkout\Model\Session as checkoutSession;
use Magento\Quote\Model\QuoteRepository;
use Webbhuset\Sveacheckout\Model\Api\BuildOrder;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Quote\Model\QuoteFactory;
use Webbhuset\Sveacheckout\Model\QueueFactory;
use Webbhuset\Sveacheckout\Helper\Data as helper;
use Webbhuset\Sveacheckout\Model\Logger\Logger as Logger;

/**
 * Class Index
 *
 * @package Webbhuset\Sveacheckout\Controller\Index
 * @module  Sveacheckout
 * @author  Webbhuset <info@webbhuset.se>
 */
class Index
    extends \Magento\Framework\App\Action\Action
{
    protected $_resultPageFactory;
    protected $checkoutSession;
    protected $context;
    protected $buildOrder;
    protected $logger;
    protected $orderRepository;
    protected $quoteRepository;
    protected $searchCriteriaBuilder;
    protected $queueFactory;
    protected $helper;

    /**
     * Index constructor.
     *
     * @param \Magento\Framework\App\Action\Context        $context
     * @param \Magento\Framework\View\Result\PageFactory   $resultPageFactory
     * @param \Magento\Checkout\Model\Session              $session
     * @param \Webbhuset\Sveacheckout\Model\Api\BuildOrder $buildOrder
     * @param \Magento\Sales\Api\OrderRepositoryInterface  $orderRepository
     * @param \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder
     * @param \Magento\Quote\Model\QuoteRepository         $quoteRepository
     * @param \Magento\Quote\Model\QuoteFactory            $quoteFactory
     * @param \Webbhuset\Sveacheckout\Model\Logger\Logger  $logger
     */
    public function __construct(
        Context                  $context,
        PageFactory              $resultPageFactory,
        checkoutSession          $session,
        BuildOrder               $buildOrder,
        OrderRepositoryInterface $orderRepository,
        SearchCriteriaBuilder    $searchCriteriaBuilder,
        QuoteRepository          $quoteRepository,
        QuoteFactory             $quoteFactory,
        QueueFactory             $queueFactory,
        Logger                   $logger,
        helper                   $helper
    )
    {
        $this->_resultPageFactory    = $resultPageFactory;
        $this->checkoutSession       = $session;
        $this->buildOrder            = $buildOrder;
        $this->context               = $context;
        $this->orderRepository       = $orderRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->quoteRepository       = $quoteRepository;
        $this->quoteFactory          = $quoteFactory;
        $this->queueFactory          = $queueFactory;
        $this->logger                = $logger;
        $this->helper          = $helper;

        parent::__construct($context);
    }

    /**
     * Dispatch request.
     *
     * @return \Magento\Framework\Controller\ResultInterface
     *
     * @throws \Magento\Framework\Exception\NotFoundException
     */
    public function execute()
    {
        $resultPage = $this->_resultPageFactory->create();
        $active = $this->helper->getStoreConfig('payment/webbhuset_sveacheckout/active');
        $paymentInformation = serialize($this->getSettings());
        if (!$active) {

            return $resultPage;
        }

        $block      = $resultPage
            ->getLayout()
            ->getBlock('webbhuset_sveacheckout_Checkout');
        $quote   = $this->getQuote()->setPaymentInformation($paymentInformation);
        $payment = $quote->getPayment();
        $payment->setMethod(\Webbhuset\Sveacheckout\Model\Ui\ConfigProvider::CHECKOUT_CODE);

        if ($quote->getPaymentReference()) {
            $this->logger->debug("Getting existing Svea order for quote `{$quote->getId()}` ref `{$quote->getPaymentReference()}`");
            $response = $this->buildOrder->getOrder($quote);
        } else {
            $this->logger->debug("Creating new Svea order for quote `{$quote->getId()}`");
            $response = $this->buildOrder->createOrder($quote);
            $this->quoteRepository->save($quote);
        }
        $error      = $this->checkoutSession->getSveaGotError($response);

        if (isset($error) && !empty($error)) {
            $this->logger->error("Checkout page error - $error");
            $this->messageManager->addErrorMessage(
                __($error)
            );

            $this->checkoutSession->setSveaGotError(null);
        } else {
            if ($block && isset($response['Gui']) && isset($response['Gui']['Snippet'])) {
                $block->setData('snippet', $response['Gui']['Snippet']);
            }

            if ('checkout' == $this->context->getRequest()->getModuleName()) {
                $this->_redirect('sveacheckout/*');
            }
        }

        return $resultPage;
    }

    /**
     * Get settings details, used to avoid breaking changes.
     *
     * @return array
     */
    protected function getSettings()
    {
        return [
            'include_options_on_invoice' => $this->helper->getStoreConfig('payment/webbhuset_sveacheckout/include_options_on_invoice')
        ];
    }

    /**
     * Get the quote, either from session or by id (sent GET param).
     *
     * @return \Magento\Quote\Model\Quote
     */
    protected function getQuote()
    {
        $requestParams = $this->context->getRequest()->getParams();

        if (isset($requestParams['reactivate']) && isset($requestParams['queueId'])) {
            $queueId = (int) $requestParams['queueId'];
            $quoteId = $this->getNewestQuoteId($queueId);

            $this->logger->info("Reactivating queueId `{$queueId}`, quoteId `$quoteId`");

            $quote   = $this->_restoreQuote($quoteId);

            return $quote;
        }

        $oldQuote = $this->checkoutSession->getQuote();

        return $oldQuote;
    }

    /**
     * Get newest quote id with same payment reference as queue item.
     *
     * @param $queueItemId
     *
     * @return string
     */
    protected function getNewestQuoteId($queueItemId)
    {
        $queue = $this->queueFactory
            ->create()
            ->getLatestQueueItemWithSameReference($queueItemId);

        return $queue->getQuoteId();
    }

    /**
     * Restore quote.
     *
     * @param  int $quoteId
     *
     * @return \Magento\Quote\Model\Quote
     */
    protected function _restoreQuote($quoteId)
    {
        $entityIdFilter = $this->searchCriteriaBuilder
            ->addFilter('entity_id', $quoteId)
            ->create();
        $oldQuote    = $this->quoteRepository->getList($entityIdFilter)->getItems();

        if (!sizeof($oldQuote)) {

            return $this->quoteFactory->create();
        } else {

            $oldQuote = reset($oldQuote);
        }
        $quote = $this->quoteFactory->create();
        $quote->merge($oldQuote)
            ->setIsActive(1)
            ->setStoreId($oldQuote->getStoreId())
            ->setReservedOrderId(null)
            ->setPaymentReference($oldQuote->getPaymentReference())
            ->setShippingAddress($oldQuote->getShippingAddress())
            ->collectTotals();

        $orderExists = count($this->getOrderCollection($oldQuote));

        if (
            $orderExists ||
            !$oldQuote->getIsActive() ||
            $oldQuote->getHasError() ||
            !$oldQuote->hasItems()
        ) {
            $quote->setPaymentReference(null);
        }

        $this->quoteRepository->save($quote);
        $this->checkoutSession->replaceQuote($quote)
            ->unsLastRealOrderId();

        $this->_redirect('sveacheckout/index/index');
        return $quote;
    }

    protected function getOrderCollection($quote) {
        $orderId    = $quote->getReservedOrderId();
        $repository = $this->orderRepository;

        $incrementIdFilter = $this->searchCriteriaBuilder
            ->addFilter('increment_id', $orderId)
            ->create();

        $orderCollection = $repository
            ->getList($incrementIdFilter)
            ->getItems();

        return $orderCollection;
    }
}

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
    protected $orderRepository;
    protected $quoteRepository;
    protected $searchCriteriaBuilder;

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
     */
    public function __construct(
        Context                  $context,
        PageFactory              $resultPageFactory,
        checkoutSession          $session,
        BuildOrder               $buildOrder,
        OrderRepositoryInterface $orderRepository,
        SearchCriteriaBuilder    $searchCriteriaBuilder,
        QuoteRepository          $quoteRepository,
        QuoteFactory             $quoteFactory
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
        $block      = $resultPage
            ->getLayout()
            ->getBlock('webbhuset_sveacheckout_Checkout');
        $quote      = $this->getQuote();
        $payment = $quote->getPayment();
        $payment->setMethod(\Webbhuset\Sveacheckout\Model\Ui\ConfigProvider::CHECKOUT_CODE);

        if ($quote->getPaymentReference()) {
            $response = $this->buildOrder->getOrder($quote, false);
        } else {
            $response = $this->buildOrder->createOrder($quote);
        }
        $error      = $this->checkoutSession->getSveaGotError($response);


        $this->quoteRepository->save($quote);

        if (isset($error) && !empty($error)) {
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
        $this->quoteRepository->save($quote);
        $this->checkoutSession->replaceQuote($quote)
            ->unsLastRealOrderId();

        $this->_redirect('sveacheckout/index/index');
        return $quote;
    }
}

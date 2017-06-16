<?php

namespace Webbhuset\Sveacheckout\Controller\Index;

use Magento\Framework\App\Action\Context;
use Magento\Framework\DataObject;
use Magento\Framework\View\Result\PageFactory;
use Webbhuset\Sveacheckout\Model\Queue as queueModel;
use Magento\Quote\Model\QuoteRepository;
use Webbhuset\Sveacheckout\Model\Payment\CreateOrder;
use Webbhuset\Sveacheckout\Model\Payment\Acknowledge;
use Webbhuset\Sveacheckout\Model\Api\BuildOrder;
use Psr\Log\LoggerInterface as logger;

/**
 * Class Push.
 *
 * @package Webbhuset\Sveacheckout\Controller\Index
 * @module  Sveacheckout
 * @author  Webbhuset <info@webbhuset.se>
 */
class Push
    extends \Magento\Framework\App\Action\Action
{
    protected $_resultPageFactory;
    protected $context;
    protected $logger;
    protected $svea;
    protected $queue;
    protected $quoteRepository;
    protected $createOrder;
    protected $acknowledge;

    /**
     * Push constructor.
     *
     * @param \Magento\Framework\App\Action\Context             $context
     * @param \Magento\Framework\View\Result\PageFactory        $resultPageFactory
     * @param \Psr\Log\LoggerInterface                          $logger
     * @param \Webbhuset\Sveacheckout\Model\Api\BuildOrder      $svea
     * @param \Webbhuset\Sveacheckout\Model\Queue               $queueModel
     * @param \Magento\Quote\Model\QuoteRepository              $quoteRepository
     * @param \Webbhuset\Sveacheckout\Model\Payment\CreateOrder $createOrder
     * @param \Webbhuset\Sveacheckout\Model\Payment\Acknowledge $acknowledge
     */
    public function __construct(
        Context         $context,
        PageFactory     $resultPageFactory,
        logger          $logger,
        BuildOrder      $svea,
        queueModel      $queueModel,
        QuoteRepository $quoteRepository,
        CreateOrder     $createOrder,
        Acknowledge     $acknowledge
    )
    {
        $this->_resultPageFactory = $resultPageFactory;
        $this->context            = $context;
        $this->logger             = $logger;
        $this->queue              = $queueModel;
        $this->svea               = $svea;
        $this->quoteRepository    = $quoteRepository;
        $this->createOrder        = $createOrder;
        $this->acknowledge        = $acknowledge;

        parent::__construct($context);
    }

    /**
     * Dispatch request
     *
     * @return \Magento\Framework\Controller\ResultInterface|boolean
     *
     * @throws \Magento\Framework\Exception\NotFoundException
     */
    public function execute()
    {
        $resultPage = $this->_resultPageFactory->create();
        $quoteId    = $this->context->getRequest()->getParam('quoteId');

        if (!$quoteId) {
            $resultPage->setHttpResponseCode('503');

            return $resultPage;
        }

        $orderQueueItem  = $this->queue->getByQuoteId($quoteId);
        $orderQueueState = $orderQueueItem->getState();

        if (!$orderQueueItem->getQueueId()) {
            return $this->reportAndReturn(404, "QueueItem {$quoteId} not found in queue.");
        }

        if ($orderQueueState == queueModel::SVEA_QUEUE_STATE_OK) {
            return $this->reportAndReturn(208, "QueueItem {$quoteId} already handled.");
        }

        try {
            $quote = $this->quoteRepository->get($quoteId);
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {

            return $this->reportAndReturn(404, "Quote {$quoteId} not found");
        }

        $sveaOrder = $this->svea->getOrder($quote);

        $responseObject = new DataObject($sveaOrder);

        switch (strtolower($responseObject->getData('Status'))) {
            case 'created':
                return $this->reportAndReturn(402, "{$quoteId} : is only in created state.");
            case 'cancelled':
                return $this->reportAndReturn(410, "{$quoteId} : is cancelled in Svea end.");
        }

        $orderQueueItem->setPushResponse($responseObject->toJson())
            ->updateState(queueModel::SVEA_QUEUE_STATE_WAIT)
            ->save();

        if (
            !$orderQueueItem->getData('order_id')
            && $orderQueueState != queueModel::SVEA_QUEUE_STATE_NEW
            && $orderQueueState != queueModel::SVEA_QUEUE_STATE_OK
        ) {
            $orderId = $this->createOrder->createOrder($quote, $orderQueueItem, $sveaOrder, $responseObject);
            if (!$orderId) {
                return $this->reportAndReturn(426, $this->getResponse()->getHttpResponseCode());
            }
        }

        if ('final' == strtolower($responseObject->getData('Status'))) {
            // acknowledge
            $mode = $this->getRequest()->getParam('mode');
            $this->acknowledge->acknowledge($orderQueueItem, $responseObject, $mode);
        }
    }

    /**
     * Set http status code log event and return.
     *
     * @see https://httpstatuses.com for references.
     *
     * @param int    $httpStatus HTTP status code
     * @param string $logMessage
     *
     * @return \Magento\Framework\View\Result\Page|bool=false
     */
    protected function reportAndReturn($httpStatus, $logMessage)
    {
        $request    = $this->getRequest();
        $simulation = $request->getParam('Simulation');

        $resultPage = $this->_resultPageFactory->create();
        $resultPage->setHttpResponseCode($httpStatus);

        if ('true' == $simulation) {
            print("http {$httpStatus} {$logMessage}");
        }

        $this->logger->info($logMessage);

        if ($httpStatus > 399) {

            return false;
        }

        return $resultPage;
    }
}

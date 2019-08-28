<?php

namespace Webbhuset\Sveacheckout\Controller\Index;

use Magento\Framework\App\Action\Context;
use Magento\Framework\DataObject;
use Magento\Framework\Controller\Result\JsonFactory;
use Webbhuset\Sveacheckout\Model\Queue as queueModel;
use Magento\Quote\Model\QuoteRepository;
use Webbhuset\Sveacheckout\Model\Payment\CreateOrder;
use Webbhuset\Sveacheckout\Model\Payment\Acknowledge;
use Webbhuset\Sveacheckout\Model\Api\BuildOrder;
use Webbhuset\Sveacheckout\Model\Logger\Logger as Logger;
use Webbhuset\Sveacheckout\Helper\Data as Helper;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\Data\OrderInterface;

    /**
 * Class Validation.
 *
 * @package Webbhuset\Sveacheckout\Controller\Index
 * @module  Sveacheckout
 * @author  Webbhuset <info@webbhuset.se>
 */
class Validation
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
    protected $orderManagement;
    protected $orderInterface;
    protected $helper;

    /**
     * Validation constructor.
     *
     * @param \Magento\Framework\App\Action\Context             $context
     * @param \Magento\Framework\Controller\Result\JsonFactory  $resultJsonFactory
     * @param \Webbhuset\Sveacheckout\Model\Logger\Logger       $logger
     * @param \Webbhuset\Sveacheckout\Model\Api\BuildOrder      $svea
     * @param \Webbhuset\Sveacheckout\Model\Queue               $queueModel
     * @param \Magento\Quote\Model\QuoteRepository              $quoteRepository
     * @param \Webbhuset\Sveacheckout\Model\Payment\CreateOrder $createOrder
     * @param \Webbhuset\Sveacheckout\Model\Payment\Acknowledge $acknowledge
     * @param \Magento\Sales\Api\OrderManagementInterface       $OrderManagementInterface
     * @param \Magento\Sales\Api\Data\OrderInterface            $orderInterface
     * @param \Webbhuset\Sveacheckout\Helper\Data               $helper
     */
    public function __construct(
        Context                  $context,
        JsonFactory              $resultJsonFactory,
        Logger                   $logger,
        BuildOrder               $svea,
        queueModel               $queueModel,
        QuoteRepository          $quoteRepository,
        CreateOrder              $createOrder,
        Acknowledge              $acknowledge,
        OrderManagementInterface $OrderManagementInterface,
        OrderInterface           $orderInterface,
        Helper                   $helper
    )
    {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->context            = $context;
        $this->logger             = $logger;
        $this->queue              = $queueModel;
        $this->svea               = $svea;
        $this->quoteRepository    = $quoteRepository;
        $this->createOrder        = $createOrder;
        $this->acknowledge        = $acknowledge;
        $this->orderManagement    = $OrderManagementInterface;
        $this->orderInterface     = $orderInterface;
        $this->helper             = $helper;

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
        $resultPage = $this->resultJsonFactory->create();
        $queueId    = (!empty($this->context->getRequest()->getParam('queueId')))
                    ? $this->context->getRequest()->getParam('queueId')
                    : $this->context->getRequest()->getParam('queueid');

        $this->logger->debug("Validation, queueId `{$queueId}`");

        $orderQueueItem = $this->queue->getLatestQueueItemWithSameReference($queueId);
        $this->logger->debug("Latest queueId `{$orderQueueItem->getQueueId()}`");

        $quoteId        = $orderQueueItem->getQuoteId();
        $orderId        = $orderQueueItem->getOrderId();

        if (!$quoteId) {
            $this->logger->info("Quote not found for queue ID `{$queueId}`");

            $resultPage->setHttpResponseCode('203');

            return $resultPage;
        }

        $orderQueueState = $orderQueueItem->getState();

        if (!$orderQueueItem->getQueueId()) {

            return $this->reportAndReturn(204, "QueueItem {$quoteId} not found in queue.");
        }

        try {
            $quote = $this->quoteRepository->get($quoteId);
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {

            return $this->reportAndReturn(204, "Quote {$quoteId} not found");
        }
        $sveaOrder = $this->svea->getOrder($quote);
        if (!$quote->getPayment()->getMethod()) {
            $quote = $this->helper->setPaymentMethod($quote);
        }

        if (!$sveaOrder) {
            return $this->reportAndReturn(
                207,
                "SveaOrder for q {$quoteId} not found, it probably failed validation");
        }

        $this->logger->debug('Svea order details', array_merge($sveaOrder, ['Gui' => '...']));

        $responseObject = new DataObject($sveaOrder);

        switch (strtolower($responseObject->getData('Status'))) {
            case 'cancelled':
                if (!$orderId) {

                    return $this->reportAndReturn(206, "{$quoteId} : is cancelled in both ends.");
                }
                $this->logger->info("Canceling order `{$orderId}``");
                $this->orderManagement->cancel($orderId);

                return $this->reportAndReturn(206, "{$quoteId} : is cancelled in Svea end.");
        }

        if ($orderQueueState == queueModel::SVEA_QUEUE_STATE_OK) {

            return $this->reportAndReturn(208, "QueueItem {$quoteId} already handled.");
        }

        $this->logger->info("Updating queue item `{$orderQueueItem->getQueueId()}` state WAIT");
        $orderQueueItem->setPushResponse($responseObject->toJson())
            ->updateState(queueModel::SVEA_QUEUE_STATE_WAIT)
            ->save();

        if (
            !$orderQueueItem->getData('order_id')
            && $orderQueueState != queueModel::SVEA_QUEUE_STATE_NEW
            && $orderQueueState != queueModel::SVEA_QUEUE_STATE_OK
        ) {
            $this->logger->info("Creating Magento order for quote `{$quote->getId()}`");
            $orderId = $this->createOrder->createOrder($quote, $orderQueueItem, $sveaOrder, $responseObject);
            if (!$orderId) {

                return $this->reportAndReturn(203, $this->getResponse()->getHttpResponseCode());
            }
        }

        if ($this->context->getResponse()->getHttpResponseCode() == 200) {
            $successMessage = sprintf(
                "Order with ID %d from SveaId %d QuoteId %d Created.",
                $orderQueueItem->getOrderId(),
                $orderId,
                $quoteId
            );

            return $this->reportAndReturn(
                201,
                $successMessage,
                $orderQueueItem->getOrderId(),
                $orderId
            );
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
    protected function reportAndReturn($httpStatus, $logMessage, $orderId = false)
    {
        $request    = $this->getRequest();
        $simulation = $request->getParam('Simulation');

        $resultPage = $this->resultJsonFactory->create();
        $resultPage->setHttpResponseCode($httpStatus);

        if ('true' == $simulation) {
            print("http {$httpStatus} {$logMessage}");
        }

        $this->logger->info(
            "Report {$httpStatus} - {$logMessage}"
            . ('true' == $simulation ? ' (simulation)' : '')
        );

        if ($httpStatus !== 201) {
            $resultPage->setData(['Valid' => false]);

            return $resultPage;
        }

        $clientOrderNumber = $this->makeSveaOrderId($orderId);
        $resultPage->setData([
            'Valid' => true,
            'ClientOrderNumber' => $clientOrderNumber,
        ]);

        $this->logger->info("Order id `{$orderId}`, client order number `{$clientOrderNumber}`");

        return $resultPage;
    }

    protected function makeSveaOrderId($orderId)
    {
        $reference = $orderId;
        $useForReference = $this->helper->getStoreConfig('payment/webbhuset_sveacheckout/developers/reference');

        if (in_array($useForReference, ['plain-increment-id', 'suffixed-increment-id'])) {
            $reference = $this->orderInterface->loadByAttribute('entity_id', $orderId)->getIncrementId();
        }

        if (in_array($useForReference, ['plain-increment-id', 'plain-order-id'])) {

            return $reference;
        }

        //To avoid order already being created, if you for example have
        //stageEnv/devEnv and ProductionEnv with order id in same range.

        $allowedLength = 32;
        $separator     = '_o_m2_';
        $lengthOfHash  = $allowedLength - (strlen((string)$reference) + strlen($separator));
        $hashedBaseUrl = sha1($this->helper->getBaseUrl());
        $clientOrder   = $reference . $separator . mb_substr($hashedBaseUrl, 0, $lengthOfHash);

        return $clientOrder;
    }
}

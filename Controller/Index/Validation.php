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
use Psr\Log\LoggerInterface as logger;
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

    /**
     * Validation constructor.
     *
     * @param \Magento\Framework\App\Action\Context             $context
     * @param \Magento\Framework\Controller\Result\JsonFactory  $resultJsonFactory
     * @param \Psr\Log\LoggerInterface                          $logger
     * @param \Webbhuset\Sveacheckout\Model\Api\BuildOrder      $svea
     * @param \Webbhuset\Sveacheckout\Model\Queue               $queueModel
     * @param \Magento\Quote\Model\QuoteRepository              $quoteRepository
     * @param \Webbhuset\Sveacheckout\Model\Payment\CreateOrder $createOrder
     * @param \Webbhuset\Sveacheckout\Model\Payment\Acknowledge $acknowledge
     * @param \Magento\Sales\Api\OrderManagementInterface       $OrderManagementInterface
     * @param \Magento\Sales\Api\Data\OrderInterface            $orderInterface
     */
    public function __construct(
        Context                  $context,
        JsonFactory              $resultJsonFactory,
        logger                   $logger,
        BuildOrder               $svea,
        queueModel               $queueModel,
        QuoteRepository          $quoteRepository,
        CreateOrder              $createOrder,
        Acknowledge              $acknowledge,
        OrderManagementInterface $OrderManagementInterface,
        OrderInterface           $orderInterface
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
        $queueId    = $this->context->getRequest()->getParam('queueId');

        $orderQueueItem = $this->queue->getLatestQueueItemWithSameReference($queueId);
        $quoteId        = $orderQueueItem->getQuoteId();
        $orderId        = $orderQueueItem->getOrderId();

        if (!$quoteId) {
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

        if (!$sveaOrder) {
            return $this->reportAndReturn(
                207,
                "SveaOrder får q {$quoteId} not found, it probably failed validation");
        }

        $responseObject = new DataObject($sveaOrder);

        switch (strtolower($responseObject->getData('Status'))) {
            case 'cancelled':
                if (!$orderId) {

                    return $this->reportAndReturn(206, "{$quoteId} : is cancelled in both ends.");
                }
                $this->orderManagement->cancel($orderId);

                return $this->reportAndReturn(206, "{$quoteId} : is cancelled in Svea end.");
        }

        if ($orderQueueState == queueModel::SVEA_QUEUE_STATE_OK) {

            return $this->reportAndReturn(208, "QueueItem {$quoteId} already handled.");
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
                return $this->reportAndReturn(226, $this->getResponse()->getHttpResponseCode());
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

        $this->logger->info($logMessage);

        if ($httpStatus !== 201) {
            $resultPage->setData(['Valid' => false]);

            return $resultPage;
        }

        $resultPage->setData([
            'Valid' => true,
            'ClientOrderNumber' => $this->makeSveaOrderId($orderId)
        ]);

        return $resultPage;
    }

    protected function makeSveaOrderId($orderId)
    {
        //To avoid order already being created, if you for example have
        //stageEnv/devEnv and ProductionEnv with order id in same range.
        $incrementId   = $this->orderInterface->loadByAttribute('entity_id', $orderId)->getIncrementId();
        $allowedLength = 32;
        $separator     = '_';
        $lengthOfHash  = $allowedLength - (strlen((string)$incrementId) + strlen($separator));
        $hashedBaseUrl = sha1($this->context->getUrl()->getBaseUrl());
        $sveaOrderId   = substr($hashedBaseUrl, 0, $lengthOfHash) . $separator . $incrementId;

        return $sveaOrderId;
    }
}
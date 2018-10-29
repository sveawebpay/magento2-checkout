<?php

namespace Webbhuset\Sveacheckout\Controller\Index;

use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Magento\Checkout\Model\Session as checkoutSession;
use Magento\Quote\Model\QuoteRepository;
use Webbhuset\Sveacheckout\Model\Api\BuildOrder;
use Webbhuset\Sveacheckout\Model\Queue as queueModel;
use Magento\Sales\Model\ResourceModel\Order\Collection  as  orderCollection;
use Webbhuset\Sveacheckout\Model\Logger\Logger as Logger;
use Magento\Framework\Encryption\EncryptorInterface;

/**
 * Class Success.
 *
 * @package \Webbhuset\Sveacheckout\Controller\Index
 * @module  Sveacheckout
 * @author  Webbhuset <info@webbhuset.se>
 */
class Success
    extends \Magento\Framework\App\Action\Action
{
    protected $resultPageFactory;
    protected $checkoutSession;
    protected $context;
    protected $buildOrder;
    protected $logger;
    protected $quoteRepository;
    protected $orderCollection;
    protected $queue;
    protected $cipher;

    /**
     * Success constructor.
     *
     * @param \Magento\Framework\App\Action\Context            $context
     * @param \Magento\Framework\View\Result\PageFactory       $resultPageFactory
     * @param \Magento\Checkout\Model\Session                  $session
     * @param \Webbhuset\Sveacheckout\Model\Api\BuildOrder     $buildOrder
     * @param \Magento\Quote\Model\QuoteRepository             $quoteRepository
     * @param \Webbhuset\Sveacheckout\Model\Logger\Logger      $logger
     * @param \Magento\Framework\Encryption\EncryptorInterface $cipher
     */
    public function __construct(
        Context            $context,
        PageFactory        $resultPageFactory,
        checkoutSession    $session,
        BuildOrder         $buildOrder,
        QuoteRepository    $quoteRepository,
        queueModel         $queueModel,
        orderCollection    $orderCollection,
        Logger             $logger,
        EncryptorInterface $cipher
    )
    {
        $this->resultPageFactory = $resultPageFactory;
        $this->checkoutSession   = $session;
        $this->buildOrder        = $buildOrder;
        $this->context           = $context;
        $this->quoteRepository   = $quoteRepository;
        $this->queue             = $queueModel;
        $this->orderCollection   = $orderCollection;
        $this->logger            = $logger;
        $this->cipher            = $cipher;
        parent::__construct($context);
    }

    /**
     * Dispatch request.
     *
     * @return \Magento\Framework\Controller\ResultInterface|ResponseInterface
     * @throws \Magento\Framework\Exception\NotFoundException
     */
    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        $block      = $resultPage
            ->getLayout()
            ->getBlock('webbhuset_sveacheckout_Checkout');

        $rawQueueId     = $this->context->getRequest()->getParam('queueId');
        $queueId        = $this->cipher->decrypt($rawQueueId);
        if (!is_numeric($queueId)) {
            $queueId        = $this->cipher->decrypt(urldecode($rawQueueId));
        }
        $orderQueueItem = $this->queue->getLatestQueueItemWithSameReference($queueId);
        $quoteId        = $orderQueueItem->getQuoteId();

        $this->logger->debug("Success, queueId `{$queueId}`, latest queueId `{$orderQueueItem->getQueueId()}`");

        try {
            $quote = $this->quoteRepository->get($quoteId);
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            $this->logger->error("Quote `{$quoteId}` not found.");

            return $resultPage;
        }

        $orderIds = $this->orderCollection
            ->addAttributeToFilter('increment_id', ['eq', $quote->getReservedOrderId()])
            ->getAllIds();

        $this->_eventManager->dispatch(
            'checkout_onepage_controller_success_action',
            ['order_ids' => $orderIds]
        );

        $response = $this->buildOrder->getOrder($quote, false);
        if ($block) {
            $block->setData('clearLocalStorage', 'true');
            $block->setData('snippet', $response['Gui']['Snippet']);
        }

        $quote->setIsActive(0);
        $this->quoteRepository->save($quote);
        $this->checkoutSession->setQuoteId(null);

        return $resultPage;
    }
}

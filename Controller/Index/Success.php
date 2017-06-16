<?php

namespace Webbhuset\Sveacheckout\Controller\Index;

use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Magento\Checkout\Model\Session as checkoutSession;
use Magento\Quote\Model\QuoteRepository;
use Webbhuset\Sveacheckout\Model\Api\BuildOrder;

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
    protected $quoteRepository;

    /**
     * Success constructor.
     *
     * @param \Magento\Framework\App\Action\Context        $context
     * @param \Magento\Framework\View\Result\PageFactory   $resultPageFactory
     * @param \Magento\Checkout\Model\Session              $session
     * @param \Webbhuset\Sveacheckout\Model\Api\BuildOrder $buildOrder
     * @param \Magento\Quote\Model\QuoteRepository         $quoteRepository
     */
    public function __construct(
        Context         $context,
        PageFactory     $resultPageFactory,
        checkoutSession $session,
        BuildOrder      $buildOrder,
        QuoteRepository $quoteRepository
    )
    {
        $this->resultPageFactory = $resultPageFactory;
        $this->checkoutSession   = $session;
        $this->buildOrder        = $buildOrder;
        $this->context           = $context;
        $this->quoteRepository   = $quoteRepository;
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

        $quoteId = $this->context->getRequest()->getParam('quoteId');

        try {
            $quote = $this->quoteRepository->get($quoteId);
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {

            return $resultPage;
        }

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

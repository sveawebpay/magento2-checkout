<?php
namespace Webbhuset\Sveacheckout\Controller\Index;

use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

/**
 * Class Terms
 *
 * @package Webbhuset\Sveacheckout\Controller\
 * @module  Sveacheckout
 * @author  Webbhuset <info@webbhuset.se>
 */
class Terms
    extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Magento\Framework\View\Result\PageFactory
     */
    protected $_resultPageFactory;
    protected $context;

    /**
     * Index constructor.
     *
     * @param \Magento\Framework\App\Action\Context      $context
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     */
    public function __construct(
        Context         $context,
        PageFactory     $resultPageFactory
    )
    {
        $this->_resultPageFactory = $resultPageFactory;
        parent::__construct($context);
        $this->context = $context;
    }

    /**
     *
     *
     */
    public function execute()
    {
        $resultPage = $this->_resultPageFactory->create();

        return $resultPage;
    }
}

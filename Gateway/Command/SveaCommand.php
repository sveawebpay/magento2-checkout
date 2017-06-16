<?php

namespace Webbhuset\Sveacheckout\Gateway\Command;

use Magento\Payment\Gateway\Command\Result\ArrayResultFactory;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;

/**
 * Class SveaCommand
 *
 * @package Webbhuset\Sveacheckout\Gateway\Command
 * @module  Sveacheckout
 * @author  Webbhuset <info@webbhuset.se>
 */
class SveaCommand implements
    CommandInterface
{
    protected $adapter;
    protected $validator;
    protected $resultFactory;
    protected $handler;

    /**
     * SveaCommand constructor.
     *
     */
    public function __construct()
    {
    }

    /**
     * @inheritdoc
     * @throws \Exception
     */
    public function execute(array $commandSubject)
    {
        $payment = SubjectReader::readPayment($commandSubject);
        $payment->getPayment();
    }

    /**
     *
     */
    public function void()
    {

    }

    /**
     *
     */
    public function capture()
    {

    }

    /**
     *
     */
    public function authorize()
    {

    }

    /**
     *
     */
    public function refund()
    {

    }
}

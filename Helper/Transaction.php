<?php

namespace Webbhuset\Sveacheckout\Helper;

/**
 * Class Transaction
 *
 * @package Webbhuset\Sveacheckout\Helper
 * @module  Sveacheckout
 * @author  Webbhuset <info@webbhuset.se>
 */
class Transaction
{
    /**
     * Create transaction.
     *
     * @param      $payment
     * @param      $responseObject
     * @param      $type
     * @param bool $status
     *
     */
    public function addTransaction($payment, $responseObject, $type, $status = false)
    {
        $id            = $responseObject->getData('OrderId');
        $txnId         = "{$id}-{$type}";
        $parentTransId = $payment->getLastTransId();

        $payment->setTransactionId($txnId)
            ->setIsTransactionClosed($status)
            ->setTransactionAdditionalInfo(
                \Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS,
                $this->flattenDataArray($responseObject->getData())
            );

        $transaction = $payment->addTransaction($type, null, true);

        if ($parentTransId) {
            $transaction->setParentTxnId($parentTransId);
        }

        $transaction->save();
        $payment->save();
    }

    /**
     * Flatten array.
     *
     * @param array  $array
     * @param string $prefix
     *
     * @return array
     */
    protected function flattenDataArray($array, $prefix = '')
    {
        $result = [];
        foreach ((array)$array as $key => $value) {
            if (is_array($value)) {
                if (!empty($prefix)) {
                    $index = sprintf("%s-%s", $prefix, $key);
                } else {
                    $index = $key;
                }
                $result += $this->flattenDataArray($value, $index);
            } else {
                if (!empty($prefix)) {
                    $index = sprintf("%s-%s", $prefix, $key);
                } else {
                    $index = $key;
                }
                $result[$index] = $value;
            }
        }

        return $result;
    }
}
<?php

namespace Webbhuset\Sveacheckout\Model;

use Magento\Framework\Event\ObserverInterface;

class Observer
    implements ObserverInterface
{
    /**
     * List of attributes that should be added to an order.
     *
     * @var array
     */
    private $attributes = [
        'payment_reference',
        'payment_information',
    ];

    /**
     *
     * @param \Magento\Framework\Event\Observer $observer
     *
     * @return $this
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $order = $observer->getEvent()->getData('order');
        $quote = $observer->getEvent()->getData('quote');

        foreach ($this->attributes as $attribute) {
            if ($quote->hasData($attribute)) {
                $order->setData($attribute, $quote->getData($attribute));
            }
        }

        return $this;
    }
}

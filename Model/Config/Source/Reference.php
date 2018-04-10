<?php

namespace Webbhuset\Sveacheckout\Model\Config\Source;

/**
 * Class Reference
 *
 * @package Webbhuset\Sveacheckout\Model\Config\Source
 * @module  Sveacheckout
 * @author  Webbhuset <info@webbhuset.se>
 */
class Reference
    implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * Creates a list of available reference options.
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            [
                'label' => __('Suffixed Order ID'),
                'title' => __('Suffixed Order ID'),
                'value' => 'suffixed-order-id',
            ],
            [
                'label' => __('Suffixed Increment ID'),
                'title' => __('Suffixed Increment ID'),
                'value' => 'suffixed-increment-id',
            ],
            [
                'label' => __('Plain Order ID'),
                'title' => __('Plain Order ID'),
                'value' => 'plain-order-id',
            ],
            [
                'label' => __('Plain Increment ID'),
                'title' => __('Plain Increment ID'),
                'value' => 'plain-increment-id',
            ]
        ];
    }
}

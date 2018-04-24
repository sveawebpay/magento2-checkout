<?php

namespace Webbhuset\Sveacheckout\Model\Config\Source;

/**
 * Class CustomerType
 *
 * @package Webbhuset\Sveacheckout\Model\Config\Source
 * @module  Sveacheckout
 * @author  Webbhuset <info@webbhuset.se>
 */
class CustomerType
    implements \Magento\Framework\Option\ArrayInterface
{
    const PRIMARILY_INDIVIDUALS   = 1;
    const PRIMARILY_COMPANIES     = 2;
    const EXCLUSIVELY_INDIVIDUALS = 3;
    const EXCLUSIVELY_COMPANIES   = 4;

    /**
     * Creates a list of available customer-type options.
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            [
                'label' => __('Default'),
                'title' => __('Default'),
                'value' => false,
            ],
            [
                'label' => __('Primarily individuals'),
                'title' => __('Primarily individuals'),
                'value' => self::PRIMARILY_INDIVIDUALS,
            ],
            [
                'label' => __('Primarily companies'),
                'title' => __('Primarily companies'),
                'value' => self::PRIMARILY_COMPANIES,
            ],
            [
                'label' => __('Exclusively individuals'),
                'title' => __('Exclusively individuals'),
                'value' => self::EXCLUSIVELY_INDIVIDUALS,
            ],
            [
                'label' => __('Exclusively companies'),
                'title' => __('Exclusively companies'),
                'value' => self::EXCLUSIVELY_COMPANIES,
            ]
        ];
    }
}

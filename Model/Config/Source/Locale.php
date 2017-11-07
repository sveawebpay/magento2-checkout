<?php

namespace Webbhuset\Sveacheckout\Model\Config\Source;

/**
 * Class Locale
 *
 * @package Webbhuset\Sveacheckout\Model\Config\Source
 * @module  Sveacheckout
 * @author  Webbhuset <info@webbhuset.se>
 */
class Locale
    implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * Locale code for sv-se.
     *
     * @var string
     */
    const LOCALE_SV_SE = 'sv-SE';
    const LOCALE_NN_NO = 'nn-NO';

    /**
     * Creates an option array with supported combinations of languages,
     * countries and locales.
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            [
                'label' => __('Sweden'),
                'title' => __('Sweden'),
                'value' => serialize($this->getOption(self::LOCALE_SV_SE)),
            ],
            [
                'label' => __('Norway'),
                'title' => __('Norway'),
                'value' => serialize($this->getOption(self::LOCALE_NN_NO)),
            ],
        ];
    }

    /**
     * Svea countries from locale code.
     *
     * @param string $locale
     *
     * @return array | bool
     */
    public function getOption($locale)
    {
        switch ($locale) {
            case self::LOCALE_SV_SE:
                return [
                    'locale'            => $locale,
                    'purchase_country'  => 'SE',
                    'purchase_currency' => 'SEK',
                ];

            case self::LOCALE_NN_NO:
                return [
                    'locale'            => $locale,
                    'purchase_country'  => 'NO',
                    'purchase_currency' => 'NOK',
                ];
        }

        return false;
    }
}

<?php

namespace Webbhuset\Sveacheckout\Helper;

use Magento\Framework\App\Helper\Context;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManager;

class Data
{
    private $context;
    private $urlBuilder;
    private $storeManager;

    /**
     * Data constructor.
     *
     * @param \Magento\Framework\App\Helper\Context $context
     * @param \Magento\Store\Model\StoreManager     $storeManager
     */
    public function __construct(
        Context      $context,
        StoreManager $storeManager
    )
    {
        $this->context      = $context;
        $this->urlBuilder   = $context->getUrlBuilder();
        $this->storeManager = $storeManager;
    }

    /**
     * Get config from core_config_data.
     *
     * @param  $path
     *
     * @return string
     */
    public function getStoreConfig($path)
    {
        $configValue = $this->context->getScopeConfig()->getValue($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

        return $configValue;
    }

    /**
     * Get Store by Id.
     *
     * @param  string $storeId
     *
     * @return \Magento\Store\Api\Data\StoreInterface|null|string
     */
    public function getStore($storeId = '')
    {

        return $this->storeManager->getStore($storeId);
    }

    /**
     * Get baseURL.
     *
     * @param  string $type
     *
     * @return mixed
     */
    public function getBaseUrl($type = '')
    {

        return $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_WEB);
    }

    /**
     * get Url by path and params.
     *
     * @param  string $path
     * @param  array  $params
     *
     * @return string
     */
    public function getUrl($path = '', $params = [])
    {

        return $this->urlBuilder->getUrl($path, $params);
    }

    public function setPaymentMethod($quote)
    {
        $configPath         = 'payment/webbhuset_sveacheckout/include_options_on_invoice';
        $paymentInformation = ['include_options_on_invoice' => $this->getStoreConfig($configPath)];
        $paymentInformation = serialize($paymentInformation);
        $quote   = $quote->setPaymentInformation($paymentInformation);
        $payment = $quote->getPayment();
        $payment->setMethod(\Webbhuset\Sveacheckout\Model\Ui\ConfigProvider::CHECKOUT_CODE);

        return $quote;
    }

    /**
     * Compare the current quote to Sveas order.
     *
     * @param  array $quoteItems
     * @param  array $sveaOrderItems
     *
     * @return array
     */
    public function compareQuoteToSveaOrder($quoteItems, $sveaOrderItems)
    {
        foreach ($quoteItems as $key => $quoteItem) {
            if (!array_key_exists('articleNumber', $quoteItem)) {
                $quoteItems[$key]['articleNumber'] = '';
            }
            if (!array_key_exists('quantity', $quoteItem)) {
                $quoteItems[$key]['quantity'] = 1;
            }
            if (!array_key_exists('discountPercent', $quoteItem)) {
                $quoteItems[$key]['discountPercent'] = 0;
            }
            if (!isset($quoteItem['temporaryReference'])) {
                $quoteItems[$key]['temporaryReference'] = $quoteItem['name'];
            }
            if (array_key_exists('discountId', $quoteItem)) {
                $quoteItems[$key]['amountIncVat'] = $quoteItem['amountIncVat'] * -1;
            }
        }

        foreach ($sveaOrderItems as $key => $sveaOrderItem) {

            if (!array_key_exists('ArticleNumber', $sveaOrderItem)) {
                $sveaOrderItems[$key]['ArticleNumber'] = '';
            }
            if (!array_key_exists('Quantity', $sveaOrderItem)) {
                $quoteItems[$key]['Quantity'] = 1;
            }
            if (!array_key_exists('DiscountPercent', $sveaOrderItem)) {
                $sveaOrderItems[$key]['DiscountPercent'] = 0;
            }
            if (!isset($sveaOrderItem['TemporaryReference'])) {
                $sveaOrderItems[$key]['TemporaryReference'] = $sveaOrderItem['Name'];
            }
        }

        usort($quoteItems, function ($a, $b) {
            return ($a['articleNumber'] < $b['articleNumber']) ? -1 : 1;
        });
        usort($sveaOrderItems, function ($a, $b) {
            return ($a['ArticleNumber'] < $b['ArticleNumber']) ? -1 : 1;
        });

        reset($quoteItems);
        reset($sveaOrderItems);

        $fieldMapper = [
            'articleNumber'   => 'ArticleNumber',
            'quantity'        => 'Quantity',
            'amountIncVat'    => 'UnitPrice',
            'vatPercent'      => 'VatPercent',
            'name'            => 'Name',
            'discountPercent' => 'DiscountPercent',
        ];

        $errors = [];
        foreach ($quoteItems as $num => $row) {
            foreach ($fieldMapper as $keyInQuote => $keyInSvea) {
               if(is_float($row[$keyInQuote]) && 'UnitPrice' == $keyInSvea) {
                    $row[$keyInQuote] = round($row[$keyInQuote],2);
                }

                if (!isset($sveaOrderItems[$num])) {
                   $errors[] .= json_encode($quoteItems[$num]) .' missing from Svea order';

                   continue;
                }

                if (isset($sveaOrderItems[$num]) && !array_key_exists($keyInSvea, $sveaOrderItems[$num])) {
                   $errors[] .= $keyInSvea.' missing from '. json_encode($sveaOrderItems[$num]);

                   continue;
                }

                if ($row[$keyInQuote] != $sveaOrderItems[$num][$keyInSvea]) {
                    $errors[] .= '$row[' . $keyInQuote . '] != $sveaOrderItems[' . $num . '][' . $keyInSvea . ']';
                    $errors[] .= $row[$keyInQuote] . '!=' . $sveaOrderItems[$num][$keyInSvea];
                }
            }
        }

        if (sizeof($errors)) {

            return ['error' => $errors];
        }
    }
}

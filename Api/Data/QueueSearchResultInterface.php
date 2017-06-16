<?php

namespace Webbhuset\Sveacheckout\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

/**
 * Interface QueueSearchResultInterface
 *
 * @package Webbhuset\Sveacheckout\Api\Data
 * @module  Sveacheckout
 * @author  Webbhuset <info@webbhuset.se>
 */
interface QueueSearchResultInterface
    extends SearchResultsInterface
{
    /**
     * @return \Webbhuset\Sveacheckout\Api\Data\QueueInterface[]
     */
    public function getItems();

    /**
     * @param \Webbhuset\Sveacheckout\Api\Data\QueueInterface[] $items
     * @return void
     */
    public function setItems(array $items);
}

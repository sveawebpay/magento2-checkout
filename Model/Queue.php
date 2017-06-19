<?php

namespace Webbhuset\Sveacheckout\Model;

use Magento\Framework\Model\AbstractModel;
use Webbhuset\Sveacheckout\Api\Data\QueueInterfaceFactory;

/**
 * Svea checkout queue model.
 *
 * @package Svea_Checkout
 * @module  Webbhuset
 * @author  Webbhuset <info@webbhuset.se>
 */
class Queue
    extends AbstractModel
    implements \Webbhuset\Sveacheckout\Api\QueueRepositoryInterface
{
    /**
     * @const int SVEA_QUEUE_STATE_INIT Customer has visited checkout, awaiting push.
     **/
    const SVEA_QUEUE_STATE_INIT = 1;

    /**
     * @const int SVEA_QUEUE_STATE_WAIT We've got push, but order not complete.
     **/
    const SVEA_QUEUE_STATE_WAIT = 2;

    /**
     * @const int SVEA_QUEUE_STATE_NEW  We got actual push.
     **/
    const SVEA_QUEUE_STATE_NEW  = 3;

    /**
     * @const int SVEA_QUEUE_STATE_OK   Order successfully created.
     **/
    const SVEA_QUEUE_STATE_OK   = 4;

    /**
     * @const int SVEA_QUEUE_STATE_ERR  Order creation failed.
     */
    const SVEA_QUEUE_STATE_ERR  = 5;

    protected $queueRepository;

    /**
     * Constructor method.
     *
     */
    public function _construct()
    {
        $this->_init('Webbhuset\Sveacheckout\Model\ResourceModel\Queue');
    }

    /**
     * Get default values array.
     *
     * @return array
     */
    public function getDefaultValues()
    {
        $values = [];

        return $values;
    }

    /**
     * Save entity.
     *
     * @return $this
     */
    public function save()
    {
        $this->_getResource()->save($this);

        return $this;
    }

    /**
     * Get queue item by queueId.
     *
     * @param  \Webbhuset\Sveacheckout\Model\Queue $queue
     *
     * @return \Magento\Framework\Model\ResourceModel\Db\AbstractDb
     *
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getById($queue)
    {
        $queue = $this->_getResource()->load($this, $queue,'quote_id');

        return $queue;
    }

    /**
     * Get queueItem by Quote Id.
     *
     * @param  int $quoteId
     *
     * @return  \Magento\Framework\DataObject
     *
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getByQuoteId($quoteId)
    {
        $queue = $this->getResourceCollection()
                      ->addFilter('quote_id', $quoteId, 'and')
                      ->getFirstItem();

        if (!$queue->getQueueId()) {

            return $this;
        }

        return $queue;
    }

    /**
     * Get latest queue item with same payment reference.
     *
     * @param int $queueId
     *
     * @return \Magento\Framework\DataObject
     */
    public function getLatestQueueItemWithSameReference($queueId)
    {
        $queue = $this->getResourceCollection()
            ->addFilter('queue_id', $queueId)
            ->getFirstItem();

        $paymentReference = $queue->getPaymentReference();

        $newest = $this->getResourceCollection()
            ->setOrder('queue_id', 'DESC')
            ->addFilter('payment_reference', $paymentReference)
            ->getFirstItem();

        if (!$newest->getQueueId()) {
            return $this;
        }

        return $newest;
    }

    /**
     * Update state if old state is lower or has error.
     *
     * @param  int $newState
     *
     * @return $this
     */
    public function updateState($newState)
    {
        $currentState   = (int)$this->getState();
        $hasError       = ($currentState === self::SVEA_QUEUE_STATE_ERR);
        $hasHigherState = ($currentState >= $newState);

        if ($hasError || $hasHigherState) {
            return $this;
        }

        return $this->setState($newState);
    }

    /**
     * Get collection.
     *
     * @param  \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     *
     * @return \Magento\Framework\Api\SearchResultsInterface
     */
    public function getList(\Magento\Framework\Api\SearchCriteriaInterface $searchCriteria)
    {
        $collection = $this->collectionFactory->create();
        foreach ($searchCriteria->getFilterGroups() as $group) {
            $this->addFilterGroupToCollection($group, $collection);
        }
        /** @var \Magento\Framework\Api\SortOrder $sortOrder */
        foreach ((array)$searchCriteria->getSortOrders() as $sortOrder) {
            $field = $sortOrder->getField();
            $collection->addOrder(
                $field,
                $this->getDirection($sortOrder->getDirection())
            );
        }

        $collection->setCurPage($searchCriteria->getCurrentPage());
        $collection->setPageSize($searchCriteria->getPageSize());
        $collection->load();
        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setCriteria($searchCriteria);

        $queues = [];
        foreach ($collection as $Queue) {
            $Queues[] = $Queue;
        }
        $searchResults->setItems($queues);
        $searchResults->setTotalCount($collection->getSize());

        return $searchResults;
    }

    /**
     * Filter collection.
     *
     * @param \Magento\Framework\Api\Search\FilterGroup $group
     * @param \Webbhuset\Sveacheckout\Model\Collection  $collection
     */
    private function addFilterGroupToCollection($group, $collection)
    {
        $fields     = [];
        $conditions = [];

        foreach ($group->getFilters() as $filter) {
            $condition = $filter->getConditionType() ?: 'eq';
            $field = $filter->getField();
            $value = $filter->getValue();
            $fields[] = $field;
            $conditions[] = [$condition => $value];
        }

        $collection->addFieldToFilter($fields, $conditions);
    }

    /**
     * Get sort order.
     *
     * @param  $direction
     *
     * @return string
     */
    private function getDirection($direction)
    {
        return $direction == SortOrder::SORT_ASC ?: SortOrder::SORT_DESC;
    }

    /**
     * Remove entity.
     *
     * @return \Webbhuset\Sveacheckout\Model\Queue
     */
    public function delete()
    {
        $this->_getResource()->delete($this);

        return $this;
    }

    /**
     * Remove entity by Id.
     *
     * @param  int $queue_id
     *
     * @return \Webbhuset\Sveacheckout\Model\Queue $queue
     */
    public function deleteById($queue_id)
    {
    }

    /**
     * Get related items.
     *
     * @param  int $queueId
     *
     * @return string
     */
    public function getAssociatedProductsIds($queueId)
    {
        $productIds = $this->queueResource
            ->getAssociatedProductIds($queueId);

        return json_encode($productIds);
    }
}

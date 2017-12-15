<?php

namespace Webbhuset\Sveacheckout\Model;

use Magento\Framework\Api\SearchResultsInterface;
use Webbhuset\Sveacheckout\Api\QueueRepositoryInterface;
use Webbhuset\Sveacheckout\Api\Data\QueueInterface;
use Webbhuset\Sveacheckout\Api\Data\QueueSearchResultInterface;
use Magento\Framework\Api\SearchCriteriaInterface;

use Magento\Framework\Api\SortOrder;
use Magento\Framework\Exception\NoSuchEntityException;
use Webbhuset\Sveacheckout\Api\Data\QueueSearchResultInterfaceFactory;
use Webbhuset\Sveacheckout\Model\ResourceModel\Queue\Collection as CollectionFactory;
use Webbhuset\Sveacheckout\Model\ResourceModel\Queue as QueueResource;

/**
 * Class QueueRepository.
 *
 * @package Webbhuset\Sveacheckout\Model
 */
class QueueRepository
    extends \Magento\Framework\Model\AbstractModel
    implements \Webbhuset\Sveacheckout\Api\QueueRepositoryInterface,
               \Magento\Framework\DataObject\IdentityInterface
{
    private $queueResource;
    private $queueFactory;
    private $collectionFactory;
    private $searchResultsFactory;

    /**
     * QueueRepository constructor.
     *
     * @param \Webbhuset\Sveacheckout\Model\ResourceModel\Queue                  $queueResource
     * @param \Webbhuset\Sveacheckout\Model\QueueFactory                         $queueFactory
     * @param \Webbhuset\Sveacheckout\Model\ResourceModel\Queue\Collection       $collectionFactory
     * @param \Webbhuset\Sveacheckout\Api\Data\QueueSearchResultInterfaceFactory $searchResultsFactory
     */
    public function __construct(
        QueueResource                     $queueResource,
        QueueFactory                      $queueFactory,
        CollectionFactory                 $collectionFactory,
        QueueSearchResultInterfaceFactory $searchResultsFactory
    )
    {
        $this->queueResource        = $queueResource;
        $this->queueFactory         = $queueFactory;
        $this->collectionFactory    = $collectionFactory;
        $this->searchResultsFactory = $searchResultsFactory;
    }

    /**
     * Constructor.
     *
     */
    protected function _construct()
    {
        $this->_init($this->queueResource);
    }

    /**
     * Initialize.
     *
     * @param string $resourceModel
     */
    protected function _init($resourceModel)
    {
        $this->_setResourceModel($resourceModel);
        $this->_idFieldName = $this->_getResource()->getIdFieldName();
    }

    /**
     * Get by Id.
     *
     * @param  int $queueId
     *
     * @return \Webbhuset\Sveacheckout\Api\Data\QueueInterface int
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getById($queueId)
    {
        $queue = $this->queueFactory->create();

        $queue->getResource()->load($queue, $queueId);
        if (!$queue->getId()) {
            throw new NoSuchEntityException(__('Unable to find Queue item with ID "%1"', $queueId));
        }

        return $queue;
    }

    /**
     * Save.
     *
     * @return $this
     */
    public function save()
    {
        $this->_getResource()->save($this);

        return $this;
    }

    /**
     * get Identification array.
     *
     * @return array
     */
    public function getIdentities()
    {
        return [$this->getId()];
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
     * Apply filters to collection.
     *
     * @param \Magento\Framework\Api\Search\FilterGroup $group
     * @param QueueResource\Collection                  $collection
     */
    private function addFilterGroupToCollection($group, $collection)
    {
        $fields     = [];
        $conditions = [];

        foreach ($group->getFilters() as $filter) {
            $condition    = $filter->getConditionType() ?: 'eq';
            $field        = $filter->getField();
            $value        = $filter->getValue();
            $fields[]     = $field;
            $conditions[] = [$condition => $value];

        }
        $collection->addFieldToFilter($fields, $conditions);
    }

    /**
     * Get sort order.
     *
     * @param $direction
     *
     * @return string
     */
    private function getDirection($direction)
    {
        return $direction == SortOrder::SORT_ASC ?: SortOrder::SORT_DESC;
    }

    /**
     * Remove an entity by id.
     *
     * @param $queue_id
     *
     * @return void
     */
    public function deleteById($queue_id)
    {
    }

    /**
     * Get related items.
     *
     * @param int $queueId
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

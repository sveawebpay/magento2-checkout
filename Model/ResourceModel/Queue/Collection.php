<?php
namespace Webbhuset\Sveacheckout\Model\ResourceModel\Queue;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * Queue resource collection model.
 *
 * @package Webbhuset\Sveacheckout\Model\ResourceModel\Queue
 * @module  Sveacheckout
 * @author  Webbhuset <info@webbhuset.se>
 */
class Collection
    extends AbstractCollection
{
    protected $_idFieldName = 'queue_id';
    protected $_eventPrefix = 'webbhuset_sveacheckout_queue_collection';
    protected $_eventObject = 'queue_collection';

    /**
     * Constructor method.
     */
    protected function _construct()
    {
        $this->_init(
            'Webbhuset\Sveacheckout\Model\Queue',
            'Webbhuset\Sveacheckout\Model\ResourceModel\Queue'
        );
    }

    /**
     * Get SQL for get record count.
     * Extra GROUP BY strip added.
     *
     * @return \Magento\Framework\DB\Select
     */
    public function getSelectCountSql()
    {
        $countSelect = parent::getSelectCountSql();
        $countSelect->reset(\Zend_Db_Select::GROUP);

        return $countSelect;
    }

    /**
     * Converts to option array.
     *
     * @param  string $valueField
     * @param  string $labelField
     * @param  array $additional
     * @return array
     */
    protected function _toOptionArray($valueField = 'post_id', $labelField = 'name', $additional = [])
    {
        return parent::_toOptionArray($valueField, $labelField, $additional);
    }
}

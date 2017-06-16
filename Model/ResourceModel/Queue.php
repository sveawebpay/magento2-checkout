<?php

namespace Webbhuset\Sveacheckout\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Resource queue model.
 *
 * @package Webbhuset\Sveacheckout\Model\ResourceModel
 * @module  Svea
 * @author  Webbhuset <info@webbhuset.se>
 */
class Queue
    extends AbstractDb
{
    /**
     * Constructor.
     */
    protected function _construct()
    {
        $this->_init('sveacheckout_queue', 'queue_id');
    }

    /**
     * Prepare Data For Save.
     *
     * @param \Magento\Framework\Model\AbstractModel $object
     *
     * @return array
     */
    protected function _prepareDataForSave(\Magento\Framework\Model\AbstractModel $object)
    {
        $sveaOrder = $object->getData('push_response');
        $object->setData('state', $this->_prepareState($object));
        $object->setData('STAMP_DATE', date('Y-M-d H:i:s'));
        if (is_array($sveaOrder) || is_object($sveaOrder)) {
            $sveaOrder = json_encode($sveaOrder);
        }
        $object->setData('push_response', ($sveaOrder));
        $data = parent::_prepareDataForSave($object);

        return $data;
    }

    /**
     * Prepare state.
     *
     * @param \Webbhuset\Sveacheckout\Model\Queue $object
     *
     * @return int
     */
    protected function _prepareState($object)
    {
        $newState = (int)$object->getData('state');
        $oldState = (int)$object->getOrigData('state');

        if (!$newState) {

            return $object::SVEA_QUEUE_STATE_INIT;
        }

        if ($newState > $oldState && $oldState !== $object::SVEA_QUEUE_STATE_ERR) {

            return $newState;
        } else {

            return $oldState;
        }
    }
}

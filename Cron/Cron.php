<?php

namespace Webbhuset\Sveacheckout\Cron;

use Webbhuset\Sveacheckout\Model\Queue;
use Webbhuset\Sveacheckout\Model\ResourceModel\Queue\Collection;
use Webbhuset\Sveacheckout\Model\Logger\Logger as Logger;

/**
 * Svea cron model. Removes finished and old queue rows.
 *
 * @package Webbhuset\Sveacheckout\Cron
 * @module  Sveacheckout
 * @author  Webbhuset <info@webbhuset.se>
 */
class Cron
{
    protected $queue;
    protected $collection;
    protected $logger;

    public function __construct(
        Queue      $queue,
        Collection $collection,
        Logger     $logger
    ) {
        $this->queue      = $queue;
        $this->collection = $collection;
        $this->logger     = $logger;
    }

    /**
     * Cron job triggers. Looks for items to handle.
     * Will delete old/error/created queue items.
     *
     */
    public function run()
    {
        $queueItems = $this->collection;

        foreach ($queueItems as $item) {
            $itemDate       = strtotime($item->getData('STAMP_CR_DATE'));
            $deleteNewLimit = strtotime('+2 days', $itemDate);
            $deleteOldLimit = strtotime('+1 month', $itemDate);

            if ($deleteNewLimit <= time()) {
                self::deleteNewAndFinished($item);
            }

            if ($deleteOldLimit <= time()) {
                self::deleteOldAndErrors($item);
            }
        }
    }

    /**
     * Deletes an item from queue, if state matches.
     *
     * @param $item
     */
    protected function deleteNewAndFinished($item)
    {
        $queueModel = $this->queue;

        $deleteItemsWithState = [
            $queueModel::SVEA_QUEUE_STATE_INIT,
            $queueModel::SVEA_QUEUE_STATE_OK,
        ];

        if (in_array((int)$item->getState(), $deleteItemsWithState)) {
            $this->logger->info("Deleting queue item `{$item->getQueueId()}` in state `{$item->getState()}`");
            $queueModel->setQueueId($item->getQueueId())->delete();
        }
    }

    /**
     * Deletes an item from queue, if state matches.
     *
     * @param $item
     */
    protected function deleteOldAndErrors($item)
    {
        $queueModel = $this->queue;

        $deleteItemsWithState = [
            $queueModel::SVEA_QUEUE_STATE_WAIT,
            $queueModel::SVEA_QUEUE_STATE_NEW,
            $queueModel::SVEA_QUEUE_STATE_ERR,
        ];

        if (in_array((int)$item->getState(), $deleteItemsWithState)) {
            $this->logger->info("Deleting queue item `{$item->getQueueId()}` in state `{$item->getState()}`");
            $queueModel->setQueueId($item->getQueueId())->delete();
        }
    }
}

<?php

namespace Webbhuset\Sveacheckout\Api;

use Webbhuset\Sveacheckout\Api\Data\QueueInterface;

/**
 * Interface QueueRepositoryInterface.
 *
 * @package Webbhuset\Sveacheckout\Api
 * @module  Sveacheckout
 * @author  Webbhuset <info@webbhuset.se>
 */
interface QueueRepositoryInterface
{
    /**
     * @param \Webbhuset\Sveacheckout\Data\QueueInterface $queue
     *
     * @return \Webbhuset\Sveacheckout\Model\Queue $queue
     */
    public function save();

    /**
     * @param $queue_id
     *
     * @return \Webbhuset\Sveacheckout\Model\Queue $queue
     */
    public function getById($queue_id);

    /**
     * @param \Webbhuset\Sveacheckout\Data\QueueInterface $queue
     *
     * @return \Webbhuset\Sveacheckout\Model\Queue $queue
     */
    public function delete();

    /**
     * @param $queue_id
     *
     * @return \Webbhuset\Sveacheckout\Model\Queue $queue
     */
    public function deleteById($queue_id);
}

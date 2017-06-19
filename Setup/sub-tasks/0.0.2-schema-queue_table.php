<?php
use Magento\Framework\DB\Ddl\Table;

$queueTableName = $this->installer->getTable('sveacheckout_queue');
if (!$this->installer->tableExists($queueTableName)) {
    $queueTable = $this->installer->getConnection()->newTable($queueTableName)
        ->addColumn(
            'queue_id',
            Table::TYPE_INTEGER,
            null,
            [
                'unsigned' => true,
                'nullable' => false,
                'primary'  => true,
                'identity' => true,
            ],
            'Queue ID'
        )
        ->addColumn(
            'quote_id',
            Table::TYPE_INTEGER,
            null,
            [
                'unsigned' => true,
                'nullable' => false,
                'primary'  => false,
                'unique'   => true,
            ],
            'Quote ID'
        )
        ->addColumn(
            'order_id',
            Table::TYPE_INTEGER,
            null,
            [
                'unsigned' => true,
                'nullable' => true,
                'primary'  => false,
                'unique'   => true,
                'default'  => null,
            ],
            'Order ID'
        )
        ->addColumn(
            'STAMP_DATE',
            Table::TYPE_TIMESTAMP,
            null,
            [
                'nullable' => false,
                'default'  => Table::TIMESTAMP_UPDATE,
            ],
            'Stamp date'
        )
        ->addColumn(
            'STAMP_CR_DATE',
            Table::TYPE_TIMESTAMP,
            null,
            [
                'nullable' => false,
                'default'  => Table::TIMESTAMP_INIT,
            ],
            'Stamp create date'
        )
        ->addColumn(
            'push_response',
            Table::TYPE_TEXT,
            '64k',
            [
                'nullable' => true,
            ],
            'Holds json response from Svea'
        )
        ->addColumn(
            'state',
            Table::TYPE_SMALLINT,
            4,
            [
                'default'  => 1,
                'nullable' => false,
                'primary'  => false,
                'unsigned' => true,
            ],
            'State'
        )
        ->addColumn(
            'payment_reference',
            Table::TYPE_TEXT,
            30,
            [
                'unsigned' => true,
                'nullable' => true,
                'primary'  => false,
                'comment'  => 'Payment reference',
            ]
        )
        ->addForeignKey(
            $this->getFkName('sveacheckout_queue', 'quote_id', 'quote', 'entity_id'),
            'quote_id',
            $this->installer->getTable('quote'),
            'entity_id',
            Table::ACTION_CASCADE,
            Table::ACTION_CASCADE
        )
        ->addForeignKey(
            $this->getFkName('sveacheckout_queue', 'quote_id', 'sales_order', 'entity_id'),
            'order_id',
            $this->installer->getTable('sales_order'),
            'entity_id',
            Table::ACTION_CASCADE,
            Table::ACTION_CASCADE
        )
        ->setComment('Store potential order references');
    $this->installer->getConnection()->createTable($queueTable);
}

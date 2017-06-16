<?php
use Magento\Framework\DB\Ddl\Table;

$tableName = $this->installer->getTable('quote');
$this->installer->getConnection()
    ->addColumn(
        $tableName,
        'payment_reference',
        [
            'type'     => Table::TYPE_TEXT,
            'length'   => 30,
            'unsigned' => true,
            'nullable' => true,
            'primary'  => false,
            'comment'  => 'Payment reference',
        ]
    );

$tableName = $this->installer->getTable('sales_order');
$this->installer->getConnection()
    ->addColumn(
        $tableName,
        'payment_reference',
        [
            'type'     => Table::TYPE_TEXT,
            'length'   => 30,
            'unsigned' => true,
            'nullable' => true,
            'primary'  => false,
            'comment'  => 'Payment reference',
        ]
    );

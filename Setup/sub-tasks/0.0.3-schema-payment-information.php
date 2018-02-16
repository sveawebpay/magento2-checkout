<?php
use Magento\Framework\DB\Ddl\Table;

$tableName = $this->installer->getTable('quote');
$this->installer->getConnection()
    ->addColumn(
        $tableName,
        'payment_information',
        [
            'type'     => Table::TYPE_TEXT,
            'length'   => '64k',
            'unsigned' => true,
            'nullable' => true,
            'primary'  => false,
            'comment'  => 'Payment information',
        ]
    );

$tableName = $this->installer->getTable('sales_order');
$this->installer->getConnection()
    ->addColumn(
        $tableName,
        'payment_information',
        [
            'type'     => Table::TYPE_TEXT,
            'length'   => '64k',
            'unsigned' => true,
            'nullable' => true,
            'primary'  => false,
            'comment'  => 'Payment information',
        ]
    );

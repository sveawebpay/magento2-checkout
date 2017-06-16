<?php
// Required tables
$statusTable      = $installer->getTable('sales_order_status');
$statusStateTable = $installer->getTable('sales_order_status_state');

// Insert statuses
$installer->getConnection()->insertArray(
    $statusTable,
    [
        'status',
        'label',
    ],
    [
        ['status' => 'sveacheckout_pending', 'label' => 'Svea Checkout new'],
        ['status' => 'sveacheckout_acknowledged', 'label' => 'Svea Checkout pending'],
    ]
);

// Insert states and mapping of statuses to states
$installer->getConnection()->insertArray(
    $statusStateTable,
    [
        'status',
        'state',
        'is_default',
    ],
    [
        [
            'status'     => 'sveacheckout_pending',
            'state'      => 'new',
            'is_default' => 1,
        ],
        [
            'status'     => 'sveacheckout_acknowledged',
            'state'      => 'new',
            'is_default' => 0,
        ],
    ]
);
$installer->endSetup();

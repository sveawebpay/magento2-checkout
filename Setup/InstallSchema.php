<?php

namespace Webbhuset\Sveacheckout\Setup;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;

/**
 * Class InstallSchema.
 *
 * @package Webbhuset\Sveacheckout\Setup
 */
class InstallSchema implements InstallSchemaInterface
{
    protected $installer;

    /**
     * @param \Magento\Framework\Setup\SchemaSetupInterface   $setup
     * @param \Magento\Framework\Setup\ModuleContextInterface $context
     */
    public function install(
        SchemaSetupInterface   $setup,
        ModuleContextInterface $context
    )
    {
        $installer       = $setup;
        $installer->startSetup();
        $this->installer = $installer;

        if (version_compare($context->getVersion(), '0.0.1') < 0) {
            include('sub-tasks/0.0.1-schema-payment-reference.php');
        }
        if (version_compare($context->getVersion(), '0.0.2') < 0) {
            include('sub-tasks/0.0.2-schema-queue_table.php');
        }

        $installer->endSetup();
    }

    /**
     * Retrieve 32bit UNIQUE HASH for a Table foreign key
     *
     * @param string $priTableName  the target table name
     * @param string $priColumnName the target table column name
     * @param string $refTableName  the reference table name
     * @param string $refColumnName the reference table column name
     * @param string $connectionName
     *
     * @return string
     */
    public function getFkName(
        $priTableName,
        $priColumnName,
        $refTableName,
        $refColumnName,
        $connectionName = ResourceConnection::DEFAULT_CONNECTION
    )
    {
        return $this->installer->getConnection($connectionName)->getForeignKeyName(
            $this->installer->getTable($priTableName),
            $priColumnName,
            $refTableName,
            $refColumnName
        );
    }
}

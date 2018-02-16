<?php
namespace Webbhuset\Sveacheckout\Setup;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;


/**
 * Class InstallSchema.
 *
 * @package Webbhuset\Sveacheckout\Setup
 */
class UpgradeSchema implements UpgradeSchemaInterface
{
    protected $installer;

    /**
     * Upgrades DB schema for a module
     *
     * @param SchemaSetupInterface $setup
     * @param ModuleContextInterface $context
     * @return void
     */
    public function upgrade(
        SchemaSetupInterface $setup,
        ModuleContextInterface $context
    )
    {
        $installer       = $setup;
        $installer->startSetup();
        $this->installer = $installer;

        if (version_compare($context->getVersion(), '0.0.3') < 0) {
            include('sub-tasks/0.0.3-schema-payment-information.php');
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

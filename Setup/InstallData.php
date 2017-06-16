<?php

namespace Webbhuset\Sveacheckout\Setup;

use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\ModuleContextInterface;

/**
 * Class InstallData.
 *
 * @package Webbhuset\Sveacheckout\Setup
 */
class InstallData implements
    InstallDataInterface
{
    protected $installer;

    /**
     * install.
     *
     * @param \Magento\Framework\Setup\ModuleDataSetupInterface $setup
     * @param \Magento\Framework\Setup\ModuleContextInterface   $context
     */
    public function install(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {

        $installer = $setup;
        $installer->startSetup();
        $this->installer = $installer;

        if (version_compare($context->getVersion(), '0.0.1') < 0) {
            include('sub-tasks/0.0.1-data_statuses_and_states.php');
        }

        $installer->endSetup();
    }
}

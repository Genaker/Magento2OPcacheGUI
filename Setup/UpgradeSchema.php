<?php


namespace Genaker\Opcache\Setup;

use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\DB\Ddl\Table;

/**
 * Class UpgradeSchema
 */
class UpgradeSchema implements UpgradeSchemaInterface
{

    /**
     * {@inheritdoc}
     */
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();
//      die("setup");
        if (version_compare($context->getVersion(), '9.0.0', '<')) {
            //$this->copyStaticFiles($setup);
        }

        $setup->endSetup();
    }

    /**
     * @param SchemaSetupInterface $setup
     */
    protected function copyStaticFiles(SchemaSetupInterface $setup)
    {
        $output = shell_exec('cp ' . BP. '/vendor/amnuts/* '. BP. '/pub/media/');
        echo $output;
    }
}


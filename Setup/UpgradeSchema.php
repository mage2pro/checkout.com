<?php

namespace Dfe\CheckoutCom\Setup;

/**
 *
 *
 *
 */

use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\DB\Ddl\Table;

class UpgradeSchema implements UpgradeSchemaInterface
{
    /**
     * Installs DB schema for a save customer cards module.
     *
     * @param SchemaSetupInterface   $setup
     * @param ModuleContextInterface $context
     */
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();
        if (version_compare($context->getVersion(), '1.0.1') < 0) {



            // Get module table
            $tableName = $setup->getTable('checkoutcom_customer');

            // Check if the table already exists
            if ($setup->getConnection()->isTableExists($tableName) == false) {

                /*
         * Create table 'checkoutcom_customer'
         */

                $table = $setup->getConnection()->newTable(
                    $setup->getTable('checkoutcom_customer')
                )->addColumn(
                    'entity_id',
                    \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                    null,
                    ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true],
                    'Entity_Id'
                )->addColumn(
                    'website_id',
                    \Magento\Framework\DB\Ddl\Table::TYPE_SMALLINT,
                    null,
                    ['unsigned' => true],
                    'Website ID'
                )->addColumn(
                    'store_id',
                    \Magento\Framework\DB\Ddl\Table::TYPE_SMALLINT,
                    null,
                    ['unsigned' => true, 'default' => '0'],
                    'Store ID'
                )->addColumn(
                    'created_at',
                    \Magento\Framework\DB\Ddl\Table::TYPE_TIMESTAMP,
                    null,
                    ['nullable' => false, 'default' => \Magento\Framework\DB\Ddl\Table::TIMESTAMP_INIT],
                    'Card Creation time '
                )->addColumn(
                    'updated_at',
                    \Magento\Framework\DB\Ddl\Table::TYPE_TIMESTAMP,
                    null,
                    ['nullable' => false, 'default' => \Magento\Framework\DB\Ddl\Table::TIMESTAMP_INIT_UPDATE],
                    'Card update time'
                )->addColumn(
                    'is_active',
                    \Magento\Framework\DB\Ddl\Table::TYPE_SMALLINT,
                    null,
                    ['unsigned' => true, 'nullable' => false, 'default' => '1'],
                    'Is Active'
                )->addColumn(
                    'customer_id',
                    \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                    null,
                    [],
                    'Customer ID'
                )->addColumn(
                    'checkoutcom_customer_id',
                    \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    null,
                    ['nullable' => false],
                    'Checkout.com Customer ID'
                )->addColumn(
                    'checkoutcom_email',
                    \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    null,
                    ['nullable' => false],
                    'Checkout.com User Email Address'
                )->addColumn(
                    'checkoutcom_card_id',
                    \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    null,
                    ['nullable' => false],
                    'Checkout.com Card ID'
                )->addColumn(
                    'last4',
                    \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    null,
                    ['nullable' => false],
                    'Last four digits of the card'
                )->setComment(
                    'Checkout.com customer for payment'
                );

                $setup->getConnection()->createTable($table);
            }
        }

        $setup->endSetup();
    }
}

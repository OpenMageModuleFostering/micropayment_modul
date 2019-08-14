<?php
/**
 *
 * @package    micropayment
 * @copyright  Copyright (c) 2015 Micropayment GmbH (http://www.micropayment.de)
 * @author     micropayment GmbH <shop-plugins@micropayment.de>
 */
$installer = $this;

/** @var $installer Mage_Core_Model_Resource_Setup */
$installer->startSetup();

$attribute = array(
    'TYPE'     => Varien_Db_Ddl_Table::TYPE_DECIMAL,
    'NULLABLE' => false,
    'LENGTH'   => '12,4',
    'COMMENT'  => 'Micropayment Payment Charge'
);

// Adding new Columns to Database
$installer->getConnection()->addColumn($installer->getTable('sales/order')         ,'mcp_payment_charge_tax' ,$attribute);
$installer->getConnection()->addColumn($installer->getTable('sales/order')         ,'base_mcp_payment_charge_tax' ,$attribute);
$installer->getConnection()->addColumn($installer->getTable('sales/order')         ,'mcp_rate' ,$attribute);

$installer->getConnection()->addColumn($installer->getTable('sales/quote_address') ,'mcp_payment_charge_tax' ,$attribute);
$installer->getConnection()->addColumn($installer->getTable('sales/quote_address') ,'base_mcp_payment_charge_tax' ,$attribute);
$installer->getConnection()->addColumn($installer->getTable('sales/quote_address') ,'mcp_rate' ,$attribute);

$installer->getConnection()->addColumn($installer->getTable('sales/invoice')       ,'mcp_payment_charge_tax' ,$attribute);
$installer->getConnection()->addColumn($installer->getTable('sales/invoice')       ,'base_mcp_payment_charge_tax' ,$attribute);
$installer->getConnection()->addColumn($installer->getTable('sales/invoice')       ,'mcp_rate' ,$attribute);

$installer->getConnection()->addColumn($installer->getTable('sales/creditmemo')    ,'mcp_payment_charge_tax' ,$attribute);
$installer->getConnection()->addColumn($installer->getTable('sales/creditmemo')    ,'base_mcp_payment_charge_tax' ,$attribute);
$installer->getConnection()->addColumn($installer->getTable('sales/creditmemo')    ,'mcp_rate' ,$attribute);

$installer->endSetup();
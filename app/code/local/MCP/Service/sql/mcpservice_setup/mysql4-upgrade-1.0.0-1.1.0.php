<?php
/**
 *
 * @package    micropayment
 * @copyright  Copyright (c) 2015 Micropayment GmbH (http://www.micropayment.de)
 * @author     micropayment GmbH <shop-plugins@micropayment.de>
 */
$installer = $this;

$salesOrderTable        = $this->getTable('sales/order');
$salesQuoteAddressTable = Mage::getSingleton('core/resource')->getTableName('sales_flat_quote_address');
$salesInvoiceTable      = Mage::getSingleton('core/resource')->getTableName('sales_flat_invoice');
$salesCreditmemoTable   = Mage::getSingleton('core/resource')->getTableName('sales_flat_creditmemo');

/** @var $installer Mage_Core_Model_Resource_Setup */
$installer->startSetup();

$attribute = array(
    'TYPE'     => Varien_Db_Ddl_Table::TYPE_DECIMAL,
    'NULLABLE' => false,
    'LENGTH'   => '12,4',
    'COMMENT'  => 'Micropayment Payment Charge'
);

// Adding new Columns to Database
$installer->getConnection()->addColumn($installer->getTable('sales/order')         ,'mcp_payment_charge' ,$attribute);
$installer->getConnection()->addColumn($installer->getTable('sales/order')         ,'base_mcp_payment_charge' ,$attribute);

$installer->getConnection()->addColumn($installer->getTable('sales/quote_address') ,'mcp_payment_charge' ,$attribute);
$installer->getConnection()->addColumn($installer->getTable('sales/quote_address') ,'base_mcp_payment_charge' ,$attribute);

$installer->getConnection()->addColumn($installer->getTable('sales/invoice')       ,'mcp_payment_charge' ,$attribute);
$installer->getConnection()->addColumn($installer->getTable('sales/invoice')       ,'base_mcp_payment_charge' ,$attribute);

$installer->getConnection()->addColumn($installer->getTable('sales/creditmemo')    ,'mcp_payment_charge' ,$attribute);
$installer->getConnection()->addColumn($installer->getTable('sales/creditmemo')    ,'base_mcp_payment_charge' ,$attribute);
// Moving Values to new Column
$installer->run('UPDATE '.$installer->getTable('sales/order').' SET `mcp_payment_charge` = `payment_fee` , `base_mcp_payment_charge` = `payment_fee`');

// Removing payment_fee column, we dont need it anymore
$installer->run('ALTER TABLE '.$installer->getTable('sales/order').' DROP COLUMN `payment_fee`');


$installer->endSetup();

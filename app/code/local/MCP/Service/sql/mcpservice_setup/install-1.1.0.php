<?php
/**
 *
 * @package    micropayment
 * @copyright  Copyright (c) 2015 Micropayment GmbH (http://www.micropayment.de)
 * @author     micropayment GmbH <shop-plugins@micropayment.de>
 */
$installer = $this;

$installer->startSetup();
$attribute = array(
    'TYPE'     => Varien_Db_Ddl_Table::TYPE_DECIMAL,
    'NULLABLE' => false,
    'LENGTH'   => '12,4',
    'COMMENT'  => 'Micropayment Payment Charge'
);

// Adding new Columns to Database
$installer->getConnection()->addColumn($installer->getTable('sales/order')         ,'mcp_payment_charge' ,$attribute);
$installer->getConnection()->addColumn($installer->getTable('sales/quote_address') ,'mcp_payment_charge' ,$attribute);
$installer->getConnection()->addColumn($installer->getTable('sales/invoice')       ,'mcp_payment_charge' ,$attribute);
$installer->getConnection()->addColumn($installer->getTable('sales/creditmemo')    ,'mcp_payment_charge' ,$attribute);

$installer->endSetup();
?>
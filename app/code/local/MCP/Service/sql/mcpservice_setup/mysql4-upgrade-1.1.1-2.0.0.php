<?php
/**
 *
 * @package    micropayment
 * @copyright  Copyright (c) 2015 Micropayment GmbH (http://www.micropayment.de)
 * @author     micropayment GmbH <shop-plugins@micropayment.de>
 */
$installer = $this;
$installer->startSetup();

$installer->getConnection()->addColumn(
    $installer->getTable('sales/order'),
    'micropayment_last_function', array(
    'TYPE' => Varien_Db_Ddl_Table::TYPE_TEXT,
    'NULLABLE' => true,
    'LENGTH' => 11,
    'COMMENT' => 'Calculate off subtotal option',
));

$installer->endSetup();
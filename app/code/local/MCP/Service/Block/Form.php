<?php
/**
 *
 * @package    micropayment
 * @copyright  Copyright (c) 2015 Micropayment GmbH (http://www.micropayment.de)
 * @author     micropayment GmbH <shop-plugins@micropayment.de>
 */
class MCP_Service_Block_Form extends Mage_Payment_Block_Form
{
    protected function _construct()
    {
        $this->setTemplate('mcpservice/form.phtml')
            ->setRedirectMessage(
            Mage::helper('mcpservice')->__('You will be redirected to micropayment.')
        );
        return parent::_construct();
    }
}

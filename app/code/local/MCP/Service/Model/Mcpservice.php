<?php
/**
 *
 * @package    micropayment
 * @copyright  Copyright (c) 2015 Micropayment GmbH (http://www.micropayment.de)
 * @author     micropayment GmbH <shop-plugins@micropayment.de>
 */
class MCP_Service_Model_Mcpservice extends Mage_Payment_Model_Method_Abstract
{
    /**
     * Availability options
     */
    protected $_moveToCurrency = 'EUR';

    protected $_code = 'mcpservice';

    public function isAvaiable($quote=null) {

        $account_id = Mage::getModel('mcpservice/mcpservice')->getConfigData('account');
        $accesskey  = Mage::getModel('mcpservice/mcpservice')->getConfigData('accesskey');
        Mage::helper('mcpservice/infoservice')->checkService();

        /**
         * @var MCP_Service_Helper_Dispatcher
         */
        $billing_url = Mage::helper('mcpservice')->getWebUrl();

        if(parent::isAvailable($quote) && $billing_url !== false && $account_id && $accesskey) {
            return true;
        }

        return false;

    }
}
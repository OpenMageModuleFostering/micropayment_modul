<?php
/**
 *
 * @package    micropayment
 * @copyright  Copyright (c) 2015 Micropayment GmbH (http://www.micropayment.de)
 * @author     micropayment GmbH <shop-plugins@micropayment.de>
 */
class MCP_EBank2Pay_Model_McpEBank2Pay extends Mage_Payment_Model_Method_Abstract
{
    protected $_code                    = 'mcpebank2pay';
    protected $_formBlockType	     	= 'mcpservice/form';
    public $_dispatcherUrl              = '/ebank2pay/event/';
    protected $_isInitializeNeeded      = true;
    protected $_canUseInternal          = false;
    protected $_canUseForMultishipping  = false;


    public function initialize($paymentAction, $stateObject)
    {
        $state = Mage_Sales_Model_Order::STATE_PENDING_PAYMENT;
        $stateObject->setStatus('pending_payment');
        $stateObject->setState($state);
        $stateObject->setIsNotified(false);
    }

    public function getGatewayUrl($order)
    {
        return Mage::helper('mcpservice/dispatcher')->generateGatewayUrl(
            $order,
            $order->getTotalDue(),
            $this->_dispatcherUrl
        );
    }

    public function getOrderPlaceRedirectUrl()
    {
        return Mage::getUrl('mcpservice/api/payment', array('_secure' => true, '_store' => Mage::app()->getStore()->getId()));
    }

    public function getSession()
    {
        return Mage::getSingleton('checkout/session');
    }


    public function getCheckout()
    {
        return Mage::getSingleton('checkout/session');
    }

    public function getQuote()
    {
        return $this->getCheckout()->getQuote();
    }

    public function createFormBlock($name)
    {
        $block = $this->getLayout()->createBlock('mcpservice/standard_form', $name)
            ->setMethod('mcpebank2pay')
            ->setPayment($this->getPayment())
            ->setTemplate('mcpservice/form.phtml');

        return $block;
    }
    /**
     * @param Mage_Sales_Model_Quote $quote
     * @return bool
     */
    public function isAvailable($quote=null)
    {
        Mage::helper('mcpservice/dispatcher')->checkService();
        $result = true;
        $model = Mage::getModel('mcpservice/mcpservice');
        $account_id = $model->getConfigData('account');
        $accesskey  = $model->getConfigData('accesskey');
        $payserviceUrl = $model->getConfigData('billing_url_ebank2pay','default');
        if(!$payserviceUrl) {
            return false;
        }
        $total = $quote->getBaseGrandTotal();
        $min = $this->getConfigData('limit_min');
        $max = $this->getConfigData('limit_max');
        if(!$this->getConfigData('active')) { return false; }
        if($total < $min) { $result = false; }
        if($total > $max) { $result = false; }
        if(!parent::isAvailable($quote)) { $result = false; }
        if(!$account_id) { $result = false; }
        if(!$accesskey) { $result = false; }

        return $result;
    }

}
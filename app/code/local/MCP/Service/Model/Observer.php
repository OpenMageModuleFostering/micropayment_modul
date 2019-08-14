<?php
/**
 *
 * @package    micropayment
 * @copyright  Copyright (c) 2015 Micropayment GmbH (http://www.micropayment.de)
 * @author     micropayment GmbH <shop-plugins@micropayment.de>
 */
class MCP_Service_Model_Observer
{
    public function submitAllAfter(Varien_Event_Observer $observer)
    {
        $order = $observer->getEvent()->getData('order');
        if(substr($order->getPayment()->getMethod(),0,3) != 'mcp') {
            return $this;
        }
        /** @var $session Mage_Checkout_Model_Session */
        $session = Mage::getSingleton('checkout/session');
        $session->getQuote()->setIsActive(true)->save();
        $collection = Mage::getResourceModel('sales/order_status_collection');
        $status = Mage::getConfig()->getStoresConfigByPath('payment/mcporderstatus/order_status_pending_payment');
        $status = $status[0];
        $collection->addStatusFilter($status);
        $status = $collection->getData();
        $status = (object) $status[0];
        $order->setState($status->state,$status->status,'Redirected to Micropayment',false)->save();
        return $this;
    }

    public function checkoutOnepageSaveOrder(Varien_Event_Observer $observer)
    {
        return true;
    }

    public function getCheckout()
    {
        return Mage::getSingleton('checkout/session');
    }
}

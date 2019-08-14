<?php
/**
 *
 * @package    micropayment
 * @copyright  Copyright (c) 2015 Micropayment GmbH (http://www.micropayment.de)
 * @author     micropayment GmbH <shop-plugins@micropayment.de>
 */

ob_start();
class MCP_Service_ApiController extends Mage_Core_Controller_Front_Action
{

    // API Calls
    const FORWARD          = 1;
    const TARGET           = '_top';

    const FUNCTION_STORNO  = 'storno';
    const FUNCTION_BILLING = 'billing';
    const FUNCTION_BACKPAY = 'backpay';
    const FUNCTION_REFUND  = 'refund';
    const FUNCTION_INIT    = 'init';
    const FUNCTION_PAYIN   = 'payin';
    const FUNCTION_EXPIRE  = 'expire';
    const FUNCTION_CHANGE  = 'change';
    const FUNCTION_ERROR   = 'error';
    const FUNCTION_TEST    = 'test';

    const MESSAGE_ADMIN_FUNCTION_INIT     = 'Prepayment, outstanding. Deadline till %s';
    const MESSAGE_ADMIN_FUNCTION_PAYIN    = 'Receipt of payment.<br />Payin %.2f %s.<br />Open %.2f %s.<br />Total Paid %.2f %s.<br />Auth %s';
    const MESSAGE_ADMIN_FUNCTION_ERROR    = 'ERR: %s - %s';
    const MESSAGE_ADMIN_FUNCTION_BILLING  = 'Payment complete. %.2f %s Auth %s';
    const MESSAGE_ADMIN_FUNCTION_EXPIRE   = 'Payment deadline has expired.';
    const MESSAGE_ADMIN_FUNCTION_CHANGE   = 'Payment has been reducted by  %.2f %s, paid: %.2f %s, still open is %.2f %s. Auth %s';
    const MESSAGE_ADMIN_FUNCTION_REFUND   = 'Order refunded. Refund amount: %.2f %s. Auth %s';
    const MESSAGE_ADMIN_FUNCTION_STORNO   = 'Order canceled. Auth %s';
    const MESSAGE_ADMIN_FUNCTION_BACKPAY  = 'Redeposit of %.2f %s. Auth %s';
    const MESSAGE_ADMIN_FUNCTION_WORKFLOW = 'EVENT-WORKFLOW-FAILURE: The logical cycle has been interrupted . %s cannot follow %s. Auth Code: %s';

    const MESSAGE_SECRET_FIELD_INVALID   = 'secret field is invalid';
    const MESSAGE_INVALID_ORDER_ID       = 'order id is invalid';
    const MESSAGE_NOT_MICROPAYMENT_ORDER = 'payment is not micropayment';

    const STATUS_OK    = 'ok';
    const STATUS_ERROR = 'error';

    const REGEX_SIMPLE_TEXT = '/^([a-zA-Z0-9 .?\[\]_\-\.\:\,]+)$/';
    const REGEX_INTEGER     = '/^[\d]{1,}$/';

    const TEMPLATE_STATUS_OK_URL = "STATUS=%s \r\n FORWARD=%s \r\n TARGET=%s \r\n URL=%s";
    const TEMPLATE_STATUS_OK     = "STATUS=%s";
    const TEMPLATE_STATUS_ERROR  = "STATUS=%s \r\n MESSAGE=%s";

    const ORDER_STATUS_NAME_PENDING_PAYMENT = 'order_status_pending_payment';
    const ORDER_STATUS_NAME_PROCESSING      = 'order_status_processing';
    const ORDER_STATUS_NAME_CANCELLED       = 'order_status_cancelled';
    const ORDER_STATUS_NAME_PAYMENT_REVIEW  = 'order_status_payment_review';
    const ORDER_STATUS_NAME_CONFLICT        = 'order_status_conflict';
    const ORDER_STATUS_NAME_PARTPAY         = 'order_status_partpay';

    private $api_functions = array(
        self::FUNCTION_STORNO,
        self::FUNCTION_BACKPAY,
        self::FUNCTION_BILLING,
        self::FUNCTION_REFUND,
        self::FUNCTION_INIT,
        self::FUNCTION_PAYIN,
        self::FUNCTION_EXPIRE,
        self::FUNCTION_CHANGE,
        self::FUNCTION_ERROR,
        self::FUNCTION_TEST
    );

    var $returnUrl     = null;
    var $returnMessage = null;
    var $returnStatus  = null;

    private $data     = null;
    private $apiCheck = false;

    /**
     * @var MCP_Service_Model_Mcpservice
     */
    private $McpServiceModel = null;

    /**
     * @var Mage_Sales_Model_Order
     */
    private $oOrder = null;

    public function init()
    {
        $this->McpServiceModel = Mage::getModel('mcpservice/mcpservice');
    }

    public function handleTest()
    {
        $encrypter = function($value) {
            return substr($value,0,1).str_repeat('x',strlen($value)-2).substr($value,strlen($value)-1);
        };
        Mage::helper('mcpservice/dispatcher')->checkService();
        $account = $this->McpServiceModel->getConfigData('account');
        if($account) {
            $account = $encrypter($account);
        } else {
            $account = 'not_set';
        }
        $accesskey = $this->McpServiceModel->getConfigData('accesskey');
        if($accesskey) {
            $accesskey = $encrypter($accesskey);
        } else {
            $accesskey = 'not_set';
        }

        $sfn = $this->McpServiceModel->getConfigData('secret_field_name');
        if($sfn) {
            $sfn = $encrypter($sfn);
        } else {
            $sfn = 'not_set';
        }
        $sfv = $this->McpServiceModel->getConfigData('secret_field_value');
        if($sfv) {
            $sfv = $encrypter($sfv);
        } else {
            $sfv = 'not_set';
        }
        $last_refresh = ($this->McpServiceModel->getConfigData('last_refresh'))?$this->McpServiceModel->getConfigData('last_refresh'):'-';
        $interval     = ($this->McpServiceModel->getConfigData('refresh_interval'))?$this->McpServiceModel->getConfigData('refresh_interval'):0;

        echo '<pre>';
        echo 'MICROPAYMENT GATEWAY TEST FUNCTION' . PHP_EOL;
        echo 'VERSION-SHOP ' . Mage::getVersion() . ' ; MOD ' . Mage::helper('mcpservice')->version . PHP_EOL;
        echo 'ACCOUNT-ID ' . $account . PHP_EOL;
        echo 'ACCESSKEY ' . $accesskey . PHP_EOL;
        echo 'SECRET_FIELD ' . $sfn . PHP_EOL;
        echo 'SECRET_VALUE ' . $sfv . PHP_EOL;
        echo 'LAST_REFRESH ' . date('Y-m-d H:i:s',$last_refresh) . ' ; INTERVAL ' . $interval . ' s' . PHP_EOL;
        echo 'CURRENT_VERSION '.$this->McpServiceModel->getConfigData('current.version','default');
        echo '</pre>';
        exit();
    }

    /**
     * indexAction calls API Action, only for config me easylier
     * @return bool
     */
    public function indexAction()
    {
        return $this->apiAction();
    }

    public function sendStatus()
    {
        if($this->returnStatus == self::STATUS_OK) {
            if($this->returnUrl) {
                $result = sprintf(
                    self::TEMPLATE_STATUS_OK_URL,
                    strtoupper($this->returnStatus),
                    self::FORWARD,
                    self::TARGET,
                    $this->returnUrl
                );
            } else {
                $result = sprintf(
                    self::TEMPLATE_STATUS_OK,
                    strtoupper($this->returnStatus)
                );
            }
        } else {
            $result = sprintf(
                self::TEMPLATE_STATUS_ERROR,
                strtoupper($this->returnStatus),
                urlencode($this->returnMessage)
            );
        }
        echo $result;
        exit();
    }


    /**
     * apiAction handles the Requests on mcpservice/notification/api
     */
    public function apiAction()
    {

        $this->init();
        if(isset($_REQUEST['function']) && $_REQUEST['function'] == self::FUNCTION_TEST) {
            $this->handleTest();
        }
        $this->fetchDataFromRequest();


        // If we use Secret Field for API calls, is this the Check
        $secretFieldName  = $this->McpServiceModel->getConfigData('secret_field_name');
        $secretFieldValue = $this->getParameter($secretFieldName,self::REGEX_SIMPLE_TEXT);

        if ($secretFieldValue != $this->McpServiceModel->getConfigData('secret_field_value')) {
            $this->returnMessage = self::MESSAGE_SECRET_FIELD_INVALID;
            $this->returnStatus  = self::STATUS_ERROR;
            $this->sendStatus();
            return false;
        }

        if (!$this->getParameter('orderid',self::REGEX_INTEGER)) {
            $this->returnMessage = self::MESSAGE_INVALID_ORDER_ID;
            $this->returnStatus = self::STATUS_ERROR;
            $this->sendStatus();
            return false;
        }
        // little Check
        if ($this->getParameter('function',$this->api_functions)) {
            $this->api();
        } else {
            $this->returnMessage = sprintf(
                self::MESSAGE_INVALID_FUNCTION,
                $this->getParameter(
                    'function',
                    self::REGEX_SIMPLE_TEXT
                )
            );
            $this->returnStatus = self::STATUS_ERROR;
            $this->sendStatus();
            return false;
        }
        return true;
    }

    private function getParameter($name,$allowed)
    {
        @$data = $this->data->{$name};
        if(is_array($allowed)) {
            return (in_array($data,$allowed))?$data:false;
        }
        if(preg_match($allowed,$data)) {
            return $data;
        } else {
            return false;
        }
    }

    /**
     * Returns the Products back to the Cart and readd it to the Stock
     * Cancels the Order
     *
     */
    public function abortAction()
    {
        /** @var $session Mage_Checkout_Model_Session */
        $session = Mage::getSingleton('checkout/session');
        $order = Mage::getModel('sales/order');

        try {
            /** @var $order Mage_Sales_Model_Order */

            $order->loadByIncrementId($session->getLastRealOrderId());
            $order->addStatusHistoryComment('Customer cancelled the payment',false);

            if (!$order->getId()) {
                Mage::throwException('No order for processing found');
            }
            if($order->canCancel()) {
                $order->cancel()->save();
            }
        } catch (Exception $e) {

        }

        $quote = Mage::getSingleton('sales/quote');
        $quote->load($order->getQuoteId());
        $session->setQuoteId($order->getQuoteId());
        $session->getQuote()->setIsActive(1)->save();

        // redirect customer to the cart (dirty)
        $redirectUrl = Mage::helper('checkout/cart')->getCartUrl();
        $block = new Mage_Core_Block_Template();
        $block->setTemplate('mcpservice/redirect.phtml');
        $block->assign('url',$redirectUrl);
        $block->assign('media_url',Mage::getBaseUrl() . '/../../media/micropayment/');
        $block->assign('year',date('Y'));
        $block->assign('payment_method',$order->getPayment());
        $block->assign('target','parent');

        echo $block->renderView();

    }

    /**
     * fetches the Order from Database by orderi
     */
    private function fetchOrder()
    {
        $this->oOrder = Mage::getSingleton('sales/order');
        try {
            $this->oOrder->load($this->getParameter('orderid',self::REGEX_INTEGER));
        } catch (Exception $e) {
            $this->returnMessage = self::MESSAGE_INVALID_ORDER_ID;
            $this->returnStatus  = self::STATUS_ERROR;
            $this->sendStatus();
        }

        if(!$this->oOrder->getPayment() || substr($this->oOrder->getPayment()->getMethod(),0,3) != 'mcp') {
            $this->returnMessage = self::MESSAGE_NOT_MICROPAYMENT_ORDER;
            $this->returnStatus  = self::STATUS_ERROR;
            $this->sendStatus();
        }
    }

    /**
     * Switch for API Call
     */
    public function api()
    {
        $function = $this->getParameter('function',$this->api_functions);

        $this->fetchOrder();

        $actualFunc = $this->oOrder->getData('micropayment_last_function');
        $this->oOrder->setData('micropayment_last_function',$function)->save();

        $this->checkEventWorkFlow($actualFunc,$function);
        $this->fetchOrder();
        switch ($function) {
            case self::FUNCTION_INIT:
                if($actualFunc)
                $this->handleInit();
            break;
            case self::FUNCTION_PAYIN:
        	    $this->handlePayin();
	        break;
            case self::FUNCTION_BILLING:
                $this->handleBilling();
            break;
            case self::FUNCTION_STORNO:
                $this->handleStorno();
            break;
            case self::FUNCTION_BACKPAY:
                $this->handleBackpay();
            break;
            case self::FUNCTION_REFUND:
                $this->handleRefund();
            break;
            case self::FUNCTION_EXPIRE:
                $this->handleExpire();
            break;
            case self::FUNCTION_CHANGE:
                $this->handleChange();
            break;
            case self::FUNCTION_TEST:
                $this->handleTest();
            break;
                case self::FUNCTION_ERROR:
                $this->handleError();
            break;
        }
        $this->sendStatus();
    }



    public function handleInit()
    {
        $status = $this->getOrderStatus('order_status_pending_payment');
        $msg    = sprintf(
            self::MESSAGE_ADMIN_FUNCTION_INIT,
            $this->getParameter('expire',self::REGEX_SIMPLE_TEXT),
            $this->getParameter('auth',self::REGEX_SIMPLE_TEXT)
        );

        $this->oOrder
            ->setState($status->state,$status->status,$msg,false)
            ->save();

        Mage::getSingleton('sales/quote')->load($this->oOrder->getQuoteId())->setIsActive(false)->save();

        $this->returnUrl = $this->checkUrlForSID(Mage::getUrl('mcpservice/api/redirect',array('_store' => Mage::app()->getStore()->getId())));
        $this->returnStatus = self::STATUS_OK;
        $this->sendStatus();
    }

    private function handlePayin()
    {
        $status = $this->getOrderStatus('order_status_partpay');
        $msg    = sprintf(
            self::MESSAGE_ADMIN_FUNCTION_PAYIN,
            ($this->getParameter('amount',self::REGEX_INTEGER)/100),
            $this->getParameter('currency',self::REGEX_SIMPLE_TEXT),
            ($this->getParameter('openamount',self::REGEX_INTEGER)/100),
            $this->getParameter('currency',self::REGEX_SIMPLE_TEXT),
            ($this->getParameter('paidamount',self::REGEX_INTEGER)/100),
            $this->getParameter('currency',self::REGEX_SIMPLE_TEXT),
            $this->getParameter('auth',self::REGEX_SIMPLE_TEXT)
        );
        $amount = ($this->getParameter('amount',self::REGEX_INTEGER) / 100);

        $this->oOrder
            ->setState($status->state,$status->status,$msg,false)
            ->setTotalPaid($this->oOrder->getTotalPaid()+$amount)
            ->setBaseTotalPaid($this->oOrder->getBaseTotalPaid()+$amount)
            ->save();

        $this->returnStatus = self::STATUS_OK;
        $this->sendStatus();
    }

    private function handleError()
    {
        $status       = $this->getOrderStatus('order_status_payment_review');
        $errorCode    = $this->getParameter('errorcode',self::REGEX_SIMPLE_TEXT);
        $errorMessage = $this->getParameter('errormessage',self::REGEX_SIMPLE_TEXT);
        $msg = sprintf(
            self::MESSAGE_ADMIN_FUNCTION_ERROR,
            $errorCode,
            $errorMessage,
            $this->getParameter('auth',self::REGEX_SIMPLE_TEXT)
        );

        $this->oOrder
            ->setState($status->state,$status->status,$msg,false)
            ->save();
        $this->returnStatus = self::STATUS_OK;
        $this->sendStatus();
    }

    private function handleBilling()
    {
        $status = $this->getOrderStatus('order_status_processing');
        $msg    = sprintf(
            self::MESSAGE_ADMIN_FUNCTION_BILLING,
            ($this->getParameter('amount',self::REGEX_INTEGER)/100),
            $this->getParameter('currency',self::REGEX_SIMPLE_TEXT),
            $this->getParameter('auth',self::REGEX_SIMPLE_TEXT)
        );
        $amount = ($this->getParameter('amount',self::REGEX_INTEGER) / 100);


        $this->oOrder
            ->setState($status->state,$status->status,$msg,false)
            ->setTotalPaid($this->oOrder->getTotalPaid() + $amount)
            ->setBaseTotalPaid($this->oOrder->getBaseTotalPaid() + $amount);

        // Clear the Quote
        Mage::getSingleton('sales/quote')->load($this->oOrder->getQuoteId())->setIsActive(false)->save();

        if($this->oOrder->getPayment()->getMethod() != 'mcpprepay') {
            $this->oOrder->sendNewOrderEmail();
            $this->oOrder->setEmailSent(true);
        }

        $this->oOrder->save();
        $this->returnStatus = self::STATUS_OK;
        $this->returnUrl    = $this->checkUrlForSID(Mage::getUrl('mcpservice/api/redirect',array('_store' => Mage::app()->getStore()->getId())));
        $this->sendStatus();
    }

    public function handleExpire()
    {
        $status = $this->getOrderStatus('order_status_cancelled');
        $msg = sprintf(
            self::MESSAGE_ADMIN_FUNCTION_EXPIRE,
            $this->getParameter('auth',self::REGEX_SIMPLE_TEXT)
        );
        $this->oOrder
            ->setState($status->state,$status->status,$msg,false);

        if($this->oOrder->canCancel()) {
            $this->oOrder->cancel();
        }
        $this->oOrder->save();

        $this->returnStatus = self::STATUS_OK;
        $this->sendStatus();
    }

    public function handleChange()
    {
        $status = $this->getOrderStatus('order_status_payment_review');
        $msg = sprintf(
            self::MESSAGE_ADMIN_FUNCTION_CHANGE,
            ($this->getParameter('amount',self::REGEX_INTEGER)/100),
            $this->getParameter('currency',self::REGEX_SIMPLE_TEXT),
            ($this->getParameter('paidamount',self::REGEX_INTEGER)/100),
            $this->getParameter('currency',self::REGEX_SIMPLE_TEXT),
            ($this->getParameter('openamount',self::REGEX_INTEGER)/100),
            $this->getParameter('currency',self::REGEX_SIMPLE_TEXT),

            $this->getParameter('auth',self::REGEX_SIMPLE_TEXT)
        );
        $amount = ($this->getParameter('amount',self::REGEX_INTEGER) / 100);

        if($this->oOrder->canCreditmemo()) {
            /** @var $creditmemo Mage_Sales_Model_Order_Creditmemo */

            $creditmemo = Mage::getSingleton('Mage_Sales_Model_Order_Creditmemo');
            $creditmemo
                ->setState(Mage_Sales_Model_Order_Creditmemo::STATE_OPEN)
                ->setOrder($this->oOrder)
                ->setGrandTotal($this->oOrder->getGrandTotal()-$this->oOrder - $amount)
                ->setBaseGrandTotal($this->oOrder->getBaseGrandTotal() - $amount)
                ->setAdjustmentNegative($amount)
                ->setBaseAdjustmentNegative($amount)
                ->setBaseToOrderRate(1)
                ->setStoreCurrencyCode($this->oOrder->getStore()->getId())
                ->addComment($msg)
                ->save();
        }

        $this->oOrder
            ->setAdjustmentNegative($amount)
            ->setBaseAdjustmentNegative($amount)
            ->setGrandTotal($this->oOrder->getGrandTotal()-$amount)
            ->setBaseGrandTotal($this->oOrder->getBaseGrandTotal()-$amount)
            ->setState($status->state,$status->status,$msg,false)
            ->save();
        $this->returnStatus = self::STATUS_OK;
        $this->sendStatus();
    }

    public function handleRefund()
    {
        $status = $this->getOrderStatus('order_status_refund');
        $msg = sprintf(
            self::MESSAGE_ADMIN_FUNCTION_REFUND,
            $this->getParameter('amount',self::REGEX_SIMPLE_TEXT)/100,
            $this->getParameter('currency',self::REGEX_SIMPLE_TEXT),
            $this->getParameter('auth',self::REGEX_SIMPLE_TEXT)
        );
        $amount = ($this->getParameter('amount',self::REGEX_INTEGER) / 100);

        $this->oOrder
            ->setState($status->state,$status->status,$msg,false)
            ->setTotalRefunded($amount)
            ->setBaseTotalRefunded($amount)
            ->save();

        $this->returnStatus = self::STATUS_OK;
        $this->sendStatus();
    }

    private function handleStorno()
    {
        $status = $this->getOrderStatus('order_status_cancelled');
        $msg = sprintf(
            self::MESSAGE_ADMIN_FUNCTION_STORNO,
            $this->getParameter('auth',self::REGEX_SIMPLE_TEXT)
        );

        $this->oOrder->setState($status->state,$status->status,$msg,false);
        if($this->oOrder->canCancel()) {
            $this->oOrder->cancel();
        }
        $this->oOrder->save();

        $this->returnStatus = self::STATUS_OK;
        $this->sendStatus();
    }

    private function handleBackpay()
    {
        $status = $this->getOrderStatus('order_status_payment_review');
        $msg = sprintf(
            self::MESSAGE_ADMIN_FUNCTION_BACKPAY,
            ($this->getParameter('amount',self::REGEX_SIMPLE_TEXT)/100),
            $this->getParameter('currency',self::REGEX_SIMPLE_TEXT),
            $this->getParameter('auth',self::REGEX_SIMPLE_TEXT)
        );
        $this->oOrder
            ->setState($status->state,$status->status,$msg,false)
            ->save();

        $this->returnStatus = self::STATUS_OK;
        $this->sendStatus();

    }


    public function checkUrlForSID(&$url)
    {
        return $url;
    }

    public function redirectAction()
    {
        $realSessionId = null;
        if(isset($_REQUEST['session'])) {
            $realSessionId = $_REQUEST['session'];
        }
        $session = Mage::getSingleton('checkout/session');

        if(!$session->getLastRealOrderId()) {
            if($realSessionId) {
                $session->load($realSessionId);
            } else {
                //Mage::throwException('Cant find correct Session!');
            }
        }

        $url = Mage::getUrl('checkout/onepage/success');

        $session->getQuote()->setIsActive(false)->save();

        $order = Mage::getModel('sales/order');
        //$order->loadByIncrementId($session->getLastRealOrderId());

        if (!$order->getId()) {
          //  Mage::throwException('No order for processing found');
        }

        try {
            $block = new Mage_Core_Block_Template();
            $block->setTemplate('mcpservice/redirect.phtml');
            $block->assign('url',$url);
            $block->assign('media_url', Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA) . '/micropayment/');

            $block->assign('year',date('Y'));
            $block->assign('is_backward',1);
            //$block->assign('payment_method',$order->getPayment()->getMethod());
            //$block->assign('payment_title',$order->getPayment()->getMethodInstance()->getConfigData('title'));
            $block->assign('shop_title', Mage::getStoreConfig('general/store_information/name'));

            echo $block->renderView();
        } catch(Exception $e) {
            die($e->getMessage());
        }
    }

    /**
     * Redirects the User to Micropayment and sets the status
     */
    public function paymentAction()
    {
        try {
            /* @var $session Mage_Checkout_Model_Session */
            $session = Mage::getSingleton('checkout/session');

            /**
             * @var $order Mage_Sales_Model_Order
             */
            $order = Mage::getModel('sales/order');
            $order->loadByIncrementId($session->getLastRealOrderId());

            if (!$order->getId()) {
                Mage::throwException('No order for processing found');
            }

            $paymentMethod = $order->getPayment()->getMethodInstance();

            $url = $paymentMethod->getGatewayUrl($order);

            if(!$order->getEmailSent()) {
                $order->sendNewOrderEmail();
                $order->setEmailSent(true);
            }

            /**
             * @var $quote Mage_Sales_Model_Quote
             */
            $quote = Mage::getSingleton('sales/quote');
            $quote->load($order->getQuoteId());
            $quote->setIsActive(true);
            $quote->save();

            // redirect customer to the cart (dirty)
            $block = new Mage_Core_Block_Template();
            $block->setTemplate('mcpservice/redirect.phtml');
            $block->assign('url',$url);
            $block->assign('media_url',Mage::getBaseUrl().'../media/micropayment/');
            $block->assign('year',date('Y'));
            $block->assign('payment_method',$order->getPayment()->getMethod());
            $block->assign('payment_title',$order->getPayment()->getMethodInstance()->getConfigData('title'));
            $block->assign('shop_title', Mage::getStoreConfig('general/store_information/name', Mage::app()->getStore()->getId()));

            echo $block->renderView();
        } catch (Exception $e) {

        }
    }

    /**
     * creates a Object from $_REQUEST
     *
     * @returns boolean
     */
    private function fetchDataFromRequest()
    {
        $this->data = (object)$_REQUEST;
        if ($this->getParameter('apicheck',self::REGEX_INTEGER)) {
            $this->apiCheck = true;
        }

        return true;
    }

    /**
     * @param $name
     *
     * @return object
     */
    private function getOrderStatus($name)
    {
        $collection = Mage::getResourceModel('sales/order_status_collection');
        $status = Mage::getConfig()->getStoresConfigByPath('payment/mcporderstatus/'.$name)[0];
        $collection->addStatusFilter($status);
        $dbStatus = (object) $collection->getData()[0];

        return $dbStatus;
    }

    private function checkEventWorkFlow($actualEvent,$newEvent)
    {


        $status = true;
        switch($actualEvent) {
            case 'new':
                if(!in_array($newEvent,array('init','billing','error'))) {
                    $status = false;
                }
                break;
            case self::FUNCTION_INIT:
                if(!in_array($newEvent,array('payin','expire','change','error'))) {
                    $status = false;
                }
                break;
            case self::FUNCTION_PAYIN:
                if(!in_array($newEvent,array('payin','billing','expire','change','error'))) {
                    $status = false;
                }
                break;
            case self::FUNCTION_BILLING:
                if(!in_array($newEvent,array('refund','storno','error'))) {
                    $status = false;
                }
                break;
            case self::FUNCTION_REFUND:
                if(!in_array($newEvent,array('storno','error'))) {
                    $status = false;
                }
                break;
            case self::FUNCTION_STORNO:
                if(!in_array($newEvent,array('backpay','error'))) {
                    $status = false;
                }
                break;
            case self::FUNCTION_BACKPAY:
                if(!in_array($newEvent,array('backpay','error'))) {
                    $status = false;
                }
                break;
            case self::FUNCTION_EXPIRE:
                if(!in_array($newEvent,array('refund','error'))) {
                    $status = false;
                }
                break;
            case self::FUNCTION_CHANGE:
                if(!in_array($newEvent,array('payin','expire','change','error'))) {
                    $status = false;
                }
                break;
            case self::FUNCTION_ERROR:
                $status = true;
            break;
        }
        if($status) {
            return true;
        } else {
            $this->returnStatus = self::STATUS_ERROR;
            $this->returnMessage = sprintf(
                self::MESSAGE_ADMIN_FUNCTION_WORKFLOW,
                $actualEvent,
                $newEvent,
                $this->getParameter('auth',self::REGEX_SIMPLE_TEXT)
            );
            $this->fetchOrder();
            $_status = $this->getOrderStatus('order_status_conflict');
            $this->oOrder->setState($_status->state,$_status->status,$this->returnMessage,false)->save();
            $this->sendStatus();
        }
    }

    public function cleanupAction()
    {
        $resource              = Mage::getSingleton('core/resource');
        $mcpServiceModel       = Mage::getSingleton('mcpservice/mcpservice');
        $con                   = $resource->getConnection('core_read');
        $orderPaymentTableName = $resource->getTableName('sales/order_payment'); // TODO
        $orderTableName        = $resource->getTableName('sales/order');         // TODO
        $days                  = $mcpServiceModel->getConfigData('cleanup_days'); // TODO

        $stmt  = ' SELECT ';
        $stmt .= ' `%s`.`entity_id`                  AS `order_id`,';
        $stmt .= ' `%s`.`created_at`                 AS `createdon`,';
        $stmt .= ' `%s`.`micropayment_last_function` AS `function`';
        $stmt .= ' FROM `%s`';
        $stmt .= ' LEFT JOIN `%s` ON `%s`.`parent_id` = `%s`.`entity_id`';
        $stmt .= ' WHERE';
        $stmt .= ' `%s`.`method` LIKE "%s%%"';
        $stmt .= ' AND `%s`.`micropayment_last_function` IN("new","error")';
        $stmt .= ' AND date(`%s`.`created_at`) <= date_sub(date(now()),INTERVAL %s DAY)';
        $stmt = sprintf(
            $stmt,
            $orderTableName, // 822
            $orderTableName, // 823
            $orderTableName, // 824
            $orderPaymentTableName, //825
            $orderTableName, // 826
            $orderPaymentTableName, // 826
            $orderTableName, // 826
            $orderPaymentTableName, // 827
            'mcp', // 828
            $orderTableName, // 829
            $orderTableName, // 830
            $days // 830
        );

        $data = $con->fetchAll($stmt);
        foreach($data as $row) {
            $orderId = $row['order_id'];
            $order = Mage::getModel('sales/order');
            $order->load($orderId);
            echo 'check order ('.$orderId.') ';
            if($order->canCancel()) {
                echo ' cancelled ';
                $order->cancel();
            } else {
                echo ' can not be cancelled ';
            }

            $order->setData('micropayment_last_function','cancelled');
            $order->save();
            echo ' <br />';
        }
        echo '<a href="javascript:history.back();">process done click here to return</a>';
    }

    public function iframeAction()
    {
        try {

            $block = $this->loadLayout()->getLayout()->createBlock(
                'Mage_Core_Block_Template',
                'micropayment_iframe',
                array(
                    'template' => 'mcpservice/iframe.phtml'
                )
            );
            $block->assign('redirect_url',Mage::getUrl('mcpservice/api/payment'));
            $this->getLayout()->getBlock('root')->setTemplate('page/1column.phtml');
            $this->getLayout()->getBlock('content')->append($block);
            $this->_initLayoutMessages('core/session');
            $this->renderLayout();

        } catch (Exception $e) {

        }

    }
}

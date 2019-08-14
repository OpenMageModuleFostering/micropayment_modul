<?php
/**
 *
 * @package    micropayment
 * @copyright  Copyright (c) 2015 Micropayment GmbH (http://www.micropayment.de)
 * @author     micropayment GmbH <shop-plugins@micropayment.de>
 */
/**
 * Class MCP_Service_Helper_Dispatcher
 */
class MCP_Service_Helper_Dispatcher extends Mage_Payment_Helper_Data
{
    const HTTP_TIMEOUT = 5;

    const INFO_SERVICE_URL = 'http://webservices.micropayment.de/public/info/index.php';
    const VERSION = '2.0.0';


    /**
     * Url Generator for micropayment Gateway
     *
     * @param \Mage_Sales_Model_order $order Order Object
     *
     * @return null|string
     */
    public
    function generateGatewayUrl(Mage_Sales_Model_Order $order)
    {
        $model = Mage::getModel('mcpservice/mcpservice');
        if(substr($order->getPayment()->getMethod(),0,3) != 'mcp') {
            return $this;
        }
        $payserviceCode = substr($order->getPayment()->getMethod(),3);
        $amount = abs(($order->getBaseGrandTotal()*100));
        $url = $model->getConfigData('billing_url_' . $payserviceCode);

        $session = Mage::getSingleton('core/session');
        $billing = $order->getBillingAddress();
        $params  = array();

        $params = array_merge(
            array(
                'project'  => $model->getConfigData('project_name'),
                'theme'    => $model->getConfigData('theme'),

                'amount'   => $amount,
                'paytext'  => str_replace('#ORDER#',$order->getRealOrderId(),$model->getConfigData('pay_text')),
                'orderid'  => $order->getId(),
                'currency' => Mage::app()->getStore()->getCurrentCurrencyCode(),
                'userid'   => $billing->getCustomerId(),

                'mp_user_email'     => $order->getCustomerEmail(),
                'mp_user_firstname' => $billing->getFirstname(),
                'mp_user_surname'   => $billing->getLastname(),
                'mp_user_address'   => $billing->getStreet(1),
                'mp_user_zip'       => $billing->getPostcode(),
                'mp_user_city'      => $billing->getCity(),
                'mp_user_country'   => strtolower($billing->getCountry()),
                'shop_version'      => $this->getShopSignatur(),

                'session' => $session->getEncryptedSessionId()
            ),
            $params
        );
        if($model->getConfigData('gfx')) {
            $params['gfx'] = $model->getConfigData('gfx');
        }
        if($model->getConfigData('bggfx')) {
            $params['bggfx'] = $model->getConfigData('bggfx');
        }
        if($model->getConfigData('bgcolor')) {
            $params['bgcolor'] = $model->getConfigData('bgcolor');
        }

        if(Mage::app()->getStore()->getCode() != '' && version_compare(Mage::getVersion(),'1.9.0.0','lt')) {
            $params['storecode'] = Mage::app()->getStore()->getCode();
        }

        $urlParams = http_build_query($params,null,'&');
        $url .= '?' . $urlParams;

        $seal = md5($urlParams . $model->getConfigData('accesskey'));
        $url .= '&seal='.$seal;

        // Adding "new" function call to initiate the order event workflow check
        $order->setData('micropayment_last_function','new')->save();

        return $url;
    }

    public function checkService()
    {
        try {
            $model         = Mage::getModel('mcpservice/mcpservice');
            $lastRefresh   = $model->getConfigData('last_refresh');
            $interval      = $model->getConfigData('refresh_interval');

            if(($lastRefresh + $interval) > time()) {
                return true;
            }

            $data = (array) $this->callInfoService('ShopModulService');
            if($data) {

                $config = Mage::getConfig();
                $config->saveConfig('payment/mcpservice/last_refresh', time());
                $config->saveConfig('payment/mcpservice/refresh_interval', $data['refresh.interval']);

                $config->saveConfig('payment/mcpservice/billing_url_creditcard',$data['billing.creditcard.url']);
                $config->saveConfig('payment/mcpservice/billing_url_debit',$data['billing.debit.url']);
                $config->saveConfig('payment/mcpservice/billing_url_ebank2pay',$data['billing.sofort.url']);
                $config->saveConfig('payment/mcpservice/billing_url_prepay',$data['billing.prepay.url']);

                Mage::getConfig()->reinit();
                Mage::app()->reinitStores();
            }
        } catch(Exception $e) {
            // Does nothing
        }
        return true;
    }

    private function getShopSignatur()
    {

        $version = Mage::getVersion();
        return 'magento:' . $version . ':'. self::VERSION;
    }

    private function callInfoService($modul,$params=null)
    {
        $model = Mage::getModel('mcpservice/mcpservice');

        if (!$model->getConfigData('account')) {
                return false;
        }
        $service_url = self::INFO_SERVICE_URL;

        $url_params = array(
            'action'       => $modul,
            'format'       => 'json',
            'account_id'   => $model->getConfigData('account'),
            'shop_version' => $this->getShopSignatur()
        );

        if($params) {
            $url_params = array_merge($params,$url_params);
        }

        try {
            if (extension_loaded('curl')) {
                $r = curl_init($service_url);
                curl_setopt($r, CURLOPT_POST, 1);
                curl_setopt($r, CURLOPT_POSTFIELDS, $url_params);
                curl_setopt($r, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($r, CURLOPT_TIMEOUT, self::HTTP_TIMEOUT);
                $response = curl_exec($r);

                curl_close($r);
            } else {
                $url3 = parse_url($service_url);
                $host = $url3["host"];
                $path = $url3["path"];
                $fp = fsockopen($host, 80, $errno, $errstr, self::HTTP_TIMEOUT);
                if ($fp) {
                    fputs($fp, "GET " . $path . "?" . http_build_query($url_params) . " HTTP/1.0\nHost: " . $host . "\n\n");
                    $buf = null;
                    while (!feof($fp)) {
                        $buf .= fgets($fp, 128);
                    }
                    $lines = explode("\n", $buf);
                    $response = $lines[count($lines) - 1];
                    fclose($fp);
                }
            }
        } catch(Exception $e) {
            return false;
        }

        try {
            $json = json_decode($response);
        } catch (Exception $e) {
            return false;
        }

        if (is_object($json)) {
            return $json;
        } else {
            return false;
        }
    }
}

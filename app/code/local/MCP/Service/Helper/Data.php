<?php
/**
 *
 * @package    micropayment
 * @copyright  Copyright (c) 2015 Micropayment GmbH (http://www.micropayment.de)
 * @author     micropayment GmbH <shop-plugins@micropayment.de>
 */
class MCP_Service_Helper_Data extends Mage_Payment_Helper_Data
{
    const DEBUG_ENABLED = false;
    public $debug_file  = 'mcp_debug.log';
    public $version     = '2.0.0';

    public
    function getMethodInstance($code)
    {
        $key = $code . '/model';
        $class = Mage::getStoreConfig($key);
        if (!$class) {
            Mage::throwException($this->__('Can not get configuration for payment method with code: %s', $code));
        }
        return Mage::getModel($class);
    }

    function log($message)
    {
        if (!self::DEBUG_ENABLED) {
            return false;
        }
        $template = '[%s]: %s';
        $message = sprintf($template, date('Y-m-d H:i:s', time()), $message);
        if (file_exists($this->debug_file)) {
            file_put_contents($this->debug_file, file_get_contents($this->debug_file) . PHP_EOL . $message);
        } else {
            touch($this->debug_file);
            file_put_contents($this->debug_file, $message);
        }
    }
}


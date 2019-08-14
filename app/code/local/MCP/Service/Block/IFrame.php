<?php
/**
 *
 * @package    micropayment
 * @copyright  Copyright (c) 2015 Micropayment GmbH (http://www.micropayment.de)
 * @author     micropayment GmbH <shop-plugins@micropayment.de>
 */
class MCP_Service_Block_IFrame extends Mage_Core_Block_Template
{
    /**
     * Request params
     * @var array
     */
    protected $_params = array();

    /**
     * Internal constructor
     * Set template for iframe
     *
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('mcpservice/iframe.phtml');
    }

    /**
     * Set output params
     *
     * @param array $params
     * @return Mage_Authorizenet_Block_Directpost_Iframe
     */
    public function setParams($params)
    {
        $this->_params = $params;
        return $this;
    }

    /**
     * Get params
     *
     * @return array
     */
    public function getParams()
    {
        return $this->_params;
    }
}

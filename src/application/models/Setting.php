<?php
/**
 * Class Setting
 *
 * @author Nguyen Huu Tam
 * @copyright Kobe Digital Labo, Inc
 * @since 2012/07/13
 */

class Application_Model_Setting
{
    protected $_fileName = 'setting.json';

    protected $_columns = array(
        'linkSystem',
        'demo',
        
        'pass',
        'staffCallCode',
        'orderStationFlag',
        'generallinkFlag',
        'printerIPAddress',
        'printerPortNo',
        'doPrintFlag',
        'doPrintSoundFlag',

        'bigSlipPageBreak',
        'printItemsPerPage',
        'charPrintSizeExpansion',

        'stationAddress',
        'receivePort',
        'sendPort',
        'socket_retry',
        'socket_waitSec',
        
        'smaregi_id',
        'smaregi_url',
        'smaregi_user',
        'smaregi_password',
        'smaregi_at'
    );
    
    public function __construct($name = null) {
        if ($name !== null) {
            $this->_fileName = $name;
        }
    }

    
    /**
     * Get file name
     * 
     * @return string
     * @author Nguyen Huu Tam
     * @since 2013/02/25
     */
    public function getFileName()
    {
        return $this->_fileName;
    }
    
    
    /**
     * Get columns data
     * 
     * @return array
     * @author Nguyen Huu Tam
     * @since 2013/02/25
     */
    public function getColumns()
    {
        return $this->_columns;
    }

    
    /**
     * Get file path
     * 
     * @return string
     * @author Nguyen Huu Tam
     * @since 2013/02/25
     */
    public function getFilePath()
    {
        $session = Globals::getSession();
        return $session->company_link
            . DIRECTORY_SEPARATOR
            . $this->getFileName();
    }
    
    
    /**
     * Get data
     * 
     * @return array
     * @author Nguyen Huu Tam
     * @since 2013/02/25
     */
    public function getData()
    {
        $config = new Zend_Config_Json($this->getFilePath());
        
        return $config->toArray();
    }
    
    
    /**
     * Get data by key
     * 
     * @param string $key
     * @return string|null
     * @author Nguyen Huu Tam
     * @since 2013/02/25
     */
    public function getDataByKey($key)
    {
        $data = $this->getData();
        if (!is_null($key) && isset($data[$key])) {
            return $data[$key];
        }
        
        return null;
    }
    
    
    /**
     * Get linkSystem Menu Tabs
     * 
     * @return array
     * @throws Zend_Config_Exception
     * @author Nguyen Huu Tam
     * @since 2013/02/25
     */
    public function getLinkSystemMenuTabs()
    {
        $config = Globals::getApplicationConfig('setting');
        $menuTabs = $config->linkSystem->menuTabs;
        
        if (!is_null($menuTabs) 
            && is_object($menuTabs)
        ) {
            return $menuTabs->toArray();
        } else {
            require_once 'Zend/Config/Exception.php';
            $msgConfig = Zend_Registry::get('MsgConfig');
            throw new Zend_Config_Exception(
                sprintf($msgConfig->E200_Invalid_ConfigInfo, '連携システム選択', 'linkSystem.menuTabs')
            );
        }
    }
}

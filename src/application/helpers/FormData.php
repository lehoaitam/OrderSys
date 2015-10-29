<?php


/**
 * @see Zend_Session
 */
require_once 'Zend/Session.php';

/**
 * @see Zend_Controller_Action_Helper_Abstract
 */
require_once 'Zend/Controller/Action/Helper/Abstract.php';

/**
 * Form Data
 *
 * @uses       Zend_Controller_Action_Helper_Abstract
 * @category   Helper
 * @package    
 * @subpackage 
 * @copyright  
 * @license    
 * @version   
 */
class Helpers_FormData extends Zend_Controller_Action_Helper_Abstract
{
    /**
     * $_data - Data from previous request
     *
     * @var array
     */
    static protected $_data = array();

    /**
     * $_session - Zend_Session storage object
     *
     * @var Zend_Session
     */
    static protected $_session = null;
       
    protected $_namespace = 'formData';
   
    /**
     * __construct() - Instance constructor, needed to get iterators, etc
     *
     * @param  string $namespace
     * @return void
     */
    public function __construct()
    {
        if (!self::$_session instanceof Zend_Session_Namespace) {
            self::$_session = new Zend_Session_Namespace($this->_namespace);
            foreach (self::$_session as $namespace => $data) {
                self::$_data[$namespace] = $data;
                unset(self::$_session->{$namespace});
            }
            self::$_session->setExpirationHops(1, null, true);
        }
    }
    
    /**
     * addData() - Add form data
     *
     * @param  string $data
     * @return My_Helper_FormData
     */
    public function addData($data)
    {
        if (!is_array(self::$_session->{$this->_namespace})) {
            self::$_session->{$this->_namespace} = array();
        }

        foreach ($data as $key => $value) {
            self::$_session->{$this->_namespace}[$key] = $value;
        }
        return $this;
    }
    
    /**
     * hasData() - Wether a specific namespace has data
     *
     * @return boolean
     */
    public function hasData()
    {
        return isset(self::$_data[$this->_namespace]);
    }
    
    /**
     * getData() - Get data from a specific namespace
     *
     * @return array
     */
    public function getData()
    {
        if ($this->hasData()) {
            return self::$_data[$this->_namespace];
        }

        return array();
    }
    
    /**
     * Clear all data from the previous request & current namespace
     *
     * @return boolean True if data were cleared, false if none existed
     */
    public function clearData()
    {
        if ($this->hasData()) {
            unset(self::$_data[$this->_namespace]);
            return true;
        }

        return false;
    }

    /**
     * count() - Complete the countable interface
     *
     * @return int
     */
    public function count()
    {
        if ($this->hasData()) {
            return count($this->getData());
        }

        return 0;
    }
    
    /**
     * Strategy pattern: proxy to addData()
     *
     * @param  string $data
     * @return void
     */
    public function direct($data)
    {
        return $this->addData($data);
    }
    
    
    /**
     * Clear csrf data
     * 
     * @param array $formData
     * @author Nguyen Huu Tam
     * @since 2013/02/26
     */
    public function removeCsrfData(&$formData)
    {
        $csrfConfig = Globals::getApplicationConfig('csrf');
        $keyName = $formData[$csrfConfig->formKey];

        unset($formData[$keyName]);
        unset($formData[$csrfConfig->formKey]);
    }
}

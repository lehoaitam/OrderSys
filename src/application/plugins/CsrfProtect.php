<?php

/**
 * A controller plugin for protecting forms from CSRF
 * 
 * Works by looking at the response and adding a hidden element to every
 * form, which contains an automatically generated key that is checked
 * on the next request against a key stored in the session
 * 
 * @author Jani Hartikainen <firstname at codeutopia net>
 * @customizer  Lam Thanh Huy
 */
class Plugins_CsrfProtect extends Zend_Controller_Plugin_Abstract 
{

    /**
     * Session storage
     * @var Zend_Session_Namespace
     */
    protected $_session = null;

    /**
     * The name of the form element which contains the key
     * @var string
     */
    protected $_keyName = null;
    
    /**
     * The id of the form element which contains the form id
     * @var string
     */
    protected $_formId = '___form_id';

    /**
     * How long until the csrf key expires (in seconds)
     * @var int
     */
    protected $_expiryTime = 300;

    /**
     * The previous request's token, set by _initializeToken
     * @var string
     */
    protected $_previousToken;

    
    public function __construct(array $params = array())
    {
        if (isset($params['expiryTime'])) {
            $this->setExpiryTime($params['expiryTime']);
        }
        
        if (isset($params['keyName'])) {
            $this->setKeyName($params['keyName']);
        }
        
        if (isset($params['formId'])) {
            $this->setFormId($params['formId']);
        }

        //$this->_session = new Zend_Session_Namespace('CsrfProtect');
        $this->_session = Globals::getSession();
    }

    
    /**
     * Set the expiry time of the csrf key
     * @param int $seconds expiry time in seconds. Set 0 for no expiration
     * @return CU_Controller_Plugin_CsrfProtect implements fluent interface
     */
    public function setExpiryTime($seconds)
    {
        $this->_expiryTime = $seconds;
        return $this;
    }

    
    /**
     * Set the name of the csrf form element
     * @param string $name
     * @return CU_Controller_Plugin_CsrfProtect implements fluent interface
     */
    public function setKeyName($name)
    {
        $this->_keyName = $name;
        return $this;
    }
    
    
    /**
     * Set id of the csrf form element
     * @param string $id
     * @return CU_Controller_Plugin_CsrfProtect implements fluent interface
     */
    public function setFormId($id)
    {
        $this->_formId = $id;
        return $this;
    }

    
    /**
     * Check if a token is valid for the previous request
     * @param string $value
     * @return bool
     */
    public function isValidToken($value)
    {
        if ($value != $this->_session->_previousToken[$this->_keyName]) {
            return false;
        }
        
        return true;
    }

    
    /**
      * Return the CSRF token for this request
      * @return string
      */
    public function getToken()
    {
        return $this->_session->_token[$this->_keyName];
    }
    
    
    /**
     * Initializes a new token
     */
    protected function _initializeTokens($keyName)
    {
        if (!empty($keyName)) {
            $this->setKeyName($keyName);

            if (isset($this->_session->key[$this->_keyName])) {
                $this->_session->_previousToken[$this->_keyName] = $this->_session->key[$this->_keyName];
            }

            $newKey = sha1(microtime() . mt_rand());
            $this->_session->key[$this->_keyName] = $newKey;

            if ($this->_expiryTime > 0) {
                $this->_session->setExpirationSeconds($this->_expiryTime);
            }

            $this->_session->_token[$this->_keyName] = $newKey;
        }
    }
    
    
    /**
     * Check Csrf
     * 
     * @param Zend_Controller_Request_Abstract $request
     * @throws RuntimeException 
     * 
     */
    public function checkCsrf(Zend_Controller_Request_Abstract $request)
    {
        if ($request->isPost() === true) {
            $params = $request->getParams();
           
            $keyName = $params[$this->_formId];
            $this->_initializeTokens($keyName);

            if (empty($this->_session->_previousToken[$this->_keyName])) {
                throw new RuntimeException('A possible CSRF attack detected - no token received');
            }

            if (!$this->isValidToken($params[$keyName])) {
                throw new RuntimeException('A possible CSRF attack detected - tokens do not match');
            }
        }
    }


    /**
     * Init hidden key
     * 
     * @return string 
     */
    public function initInput()
    {
        $front = Zend_Controller_Front::getInstance();
        $params = $front->getRequest()->getParams();
        
        $keyName = sha1(microtime() . mt_rand());
        
        $this->_initializeTokens($keyName);
        return $this->getElement();
    }

    
    /**
     *
     * @return string 
     */
    public function getElement()
    {
        $element = sprintf(
            '<input type="hidden" name="%s" value="%s" />
            <input type="hidden" name="%s" value="%s" />',
            $this->_keyName,
            $this->getToken(),
            $this->_formId,
            $this->_keyName
        );
        
        return $element;
    }
	
	/**
     *
     * @return string 
     */
	public function getKeyName() {
		return $this->_keyName;
	}
	
	/**
     *
     * @return string 
     */
	public function getFormId() {
		return $this->_formId;
	}
}

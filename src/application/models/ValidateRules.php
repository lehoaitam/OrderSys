<?php
/**
 * Create class to validate value on form
 *
 * @author Nguyen Thi Tho
 * @copyright Kobe Digital Labo, Inc
 * @since 2012/08/08
 */

class Application_Model_ValidateRules
{
    private $_specCharForName   = '@#$%^&"\'+=<>{}[\],';
    private $_specCharForFileName   = '"<>\' ';
    private $_specCharForLink   = '|^http(s)?://[a-z0-9-]+(.[a-z0-9-]+)*(:[0-9]+)?(/.*)?$|i';
    private $_specCharForIP     = '/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\z/';
    private $_specCharForHHMM   = '/^(([0-1]?[0-9])|([2][0-3])):([0-5][0-9])$/';
   
    
    public function checkSpecCharForName($value)
    {
        if (preg_match('~['.$this->_specCharForName.']+~', $value, $matches)) {
            return false;
        } else {
            return true;
        }

    }
	
	public function checkSpecCharForFileName($value)
    {
        if (preg_match('~['.$this->_specCharForFileName.']+~', $value, $matches)) {
            return false;
        } else {
            return true;
        }

    }

    public function checkSpecCharForLink($value)
    {
        if (preg_match($this->_specCharForLink, $value, $matches)) {
            return true;
        } else {
            return false;
        }
    }
    
    public function checkSpecCharForIP($value)
    {    
        if (preg_match($this->_specCharForIP, $value, $matches)) {
            $ipAddress = explode('.', $matches[0]);
            $result = array_filter(
                $ipAddress, 
                create_function('$element', 'return $element > 255;')
            );
            
            if (count($result)) {
                return false;
            }
            
            return true;
        } else {
            
            return false;
        }
    }
    
    public function checkSpecCharForHHMM($value)
    {
        if (preg_match($this->_specCharForHHMM, $value, $matches)) {
            return true;
        } else {
            return false;
        }
    }
}
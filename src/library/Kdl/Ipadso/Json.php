<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Config
 *
 * @author Sammy Guergachi <sguergachi at gmail.com>
 */
class Kdl_Ipadso_Json
{
    
    protected $_file = null;

    /**
     * 
     * @param string $jsonFile
     * @author Nguyen Huu Tam
     * @since 2013/03/02
     */
    public function __construct($jsonFile)
    {
        $this->_file = $jsonFile;
    }

    
    /**
     * Get setting file
     * 
     * @return string
     * @author Nguyen Huu Tam
     * @since 2013/03/02
     */
    public function getFilePath()
    {
        return $this->_file;
    }

        
    /**
     * Get setting file path
     *
     * @return string
     * @author Nguyen Huu Tam
     * @since 2013/03/02
     */
    protected function _getFile($createFile = true)
    {
        $jsonFile = $this->getFilePath();
        // If file no exist
        if (!file_exists($jsonFile)) {
            if ($createFile) {
                $config = new Zend_Config(array(), true);
                $this->_write($config, $jsonFile);
            } else {
                return null;
            }
        }
        
        return $jsonFile;
    }
    
    
    /**
     *
     * @param Zend_Config_Json $config 
     * @param string $file 
     * @author Nguyen Huu Tam
     * @since 2012/09/06
     */
    protected function _write($config, $file)
    {
        $writer = new Zend_Config_Writer_Json(
            array(
                'config'    => $config,
                'filename'  => $file
            )
        );

        $writer->write();
    }
    
    
    /**
     * Get setting information
     *
     * @param string $settingFile
     * @return \Zend_Config_Json 
     * @author Nguyen Huu Tam
     * @since 2012/09/05
     */
    public function getJsonConfig($createFile = true)
    {
        $file = $this->_getFile($createFile);
        if (!empty($file)) {
            $config = new Zend_Config_Json(
                $file,
                null,
                array('allowModifications' => true)
            );            
            return $config;
        }
        return null;
    }
    
    
    /**
     * Save data setting
     *
     * @param array $data 
     * @author Nguyen Huu Tam
     * @since 2012/09/05
     */
    public function save($data = array())
    {
        $config = $this->getJsonConfig();
        
        foreach ($data as $key => $value) {
            $config->$key = $value;
        }
        
        $this->_write($config, $this->_getFile());
    }
}
